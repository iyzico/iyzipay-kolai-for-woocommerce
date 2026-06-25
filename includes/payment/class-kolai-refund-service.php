<?php
/**
 * Refund Service - Maps WooCommerce refund/cancel actions to iyzico
 * refund/cancel operations using stored payment meta.
 *
 * @package    Kolai
 * @subpackage Kolai/includes/payment
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Refund service class.
 */
class Kolai_Refund_Service {

    // Order meta keys (paymentId / itemTransactions are written by the order service).
    const META_PAYMENT_ID            = 'kolai_payment_id';
    const META_ITEM_TRANSACTIONS     = 'kolai_item_transactions';
    const META_REFUNDED_TRANSACTIONS = 'kolai_refunded_transactions';
    const META_CANCEL_RESULT         = 'kolai_iyzico_cancel_result';

    // Payment method the API assigns to orders created through Kolai.
    const PAYMENT_METHOD = 'kolai-app';

    // Float comparison tolerance for currency amounts.
    const EPSILON = 0.005;

    /**
     * iyzico client.
     *
     * @var Kolai_Iyzico_Client
     */
    private $client;

    /**
     * Constructor.
     *
     * @param Kolai_Iyzico_Client|null $client
     */
    public function __construct($client = null) {
        $this->client = $client ? $client : new Kolai_Iyzico_Client();
    }

    /**
     * Refund an amount against an order by allocating it across the stored
     * iyzico item transactions (per paymentTransactionId).
     *
     * @param WC_Order|int $order  Order or order id.
     * @param float        $amount Amount to refund.
     * @param string       $reason Optional reason.
     * @return true|WP_Error
     */
    public function refund_order($order, $amount, $reason = '') {
        $order = $this->resolve_order($order);
        if (!$order) {
            return new WP_Error('kolai_refund_error', __('Siparis bulunamadi.', 'kolai'));
        }

        if (!$this->client->is_configured()) {
            return new WP_Error('kolai_refund_error', __('iyzico API anahtarlari ayarlanmamis.', 'kolai'));
        }

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return new WP_Error('kolai_refund_error', __('Iade tutari sifirdan buyuk olmalidir.', 'kolai'));
        }

        $transactions = $this->get_item_transactions($order);
        if (empty($transactions)) {
            return new WP_Error('kolai_refund_error', __('Bu siparis icin iyzico islem bilgisi bulunamadi.', 'kolai'));
        }

        $refunded = $this->get_refunded_map($order);

        // Build the allocation plan before sending anything to iyzico.
        $plan = array();
        $remaining = $amount;
        foreach ($transactions as $tx) {
            if ($remaining <= self::EPSILON) {
                break;
            }
            $tx_id = isset($tx['paymentTransactionId']) ? (string) $tx['paymentTransactionId'] : '';
            if ($tx_id === '') {
                continue;
            }
            $paid = isset($tx['paidPrice']) ? (float) $tx['paidPrice'] : 0.0;
            $already = isset($refunded[$tx_id]) ? (float) $refunded[$tx_id] : 0.0;
            $refundable = round($paid - $already, 2);
            if ($refundable <= self::EPSILON) {
                continue;
            }
            $amt = round(min($remaining, $refundable), 2);
            $plan[] = array('paymentTransactionId' => $tx_id, 'amount' => $amt);
            $remaining = round($remaining - $amt, 2);
        }

        if ($remaining > self::EPSILON) {
            return new WP_Error('kolai_refund_error', sprintf(
                /* translators: %s: remaining amount that cannot be refunded */
                __('Iade edilebilir tutar yetersiz. Karsilanamayan tutar: %s', 'kolai'),
                number_format($remaining, 2, '.', '')
            ));
        }

        // Execute the plan; persist successful allocations as we go.
        $currency = $order->get_currency();
        foreach ($plan as $step) {
            $conversation_id = 'order-' . $order->get_id() . '-tx-' . $step['paymentTransactionId'];
            $result = $this->client->refund(
                $step['paymentTransactionId'],
                $step['amount'],
                $currency,
                $conversation_id
            );

            if (empty($result['success'])) {
                $message = !empty($result['errorMessage'])
                    ? $result['errorMessage']
                    : __('iyzico iade islemi basarisiz.', 'kolai');
                $order->add_order_note(sprintf(
                    /* translators: 1: amount, 2: transaction id, 3: error message */
                    __('iyzico iade BASARISIZ: %1$s (islem %2$s) - %3$s', 'kolai'),
                    number_format($step['amount'], 2, '.', ''),
                    $step['paymentTransactionId'],
                    $message
                ));
                return new WP_Error('kolai_refund_error', $message);
            }

            $refunded[$step['paymentTransactionId']] = round(
                (isset($refunded[$step['paymentTransactionId']]) ? (float) $refunded[$step['paymentTransactionId']] : 0.0)
                + $step['amount'],
                2
            );
            $this->save_refunded_map($order, $refunded);

            $order->add_order_note(sprintf(
                /* translators: 1: amount, 2: transaction id */
                __('iyzico iade basarili: %1$s (islem %2$s)', 'kolai'),
                number_format($step['amount'], 2, '.', ''),
                $step['paymentTransactionId']
            ));
        }

        return true;
    }

    /**
     * Cancel an entire order payment in iyzico (same-day, full amount).
     *
     * @param WC_Order|int $order
     * @return array{success:bool,errorCode:?string,errorMessage:?string,raw:?object}|WP_Error
     */
    public function cancel_order($order) {
        $order = $this->resolve_order($order);
        if (!$order) {
            return new WP_Error('kolai_cancel_error', __('Siparis bulunamadi.', 'kolai'));
        }

        if (!$this->client->is_configured()) {
            return new WP_Error('kolai_cancel_error', __('iyzico API anahtarlari ayarlanmamis.', 'kolai'));
        }

        $payment_id = trim((string) $order->get_meta(Kolai_Meta_Keys::get('payment_id')));
        if ($payment_id === '') {
            return new WP_Error('kolai_cancel_error', __('Bu siparis icin iyzico paymentId bulunamadi.', 'kolai'));
        }

        $conversation_id = 'order-' . $order->get_id() . '-cancel';
        $result = $this->client->cancel($payment_id, $conversation_id);

        $order->update_meta_data(Kolai_Meta_Keys::get('cancel_result'), wp_json_encode(array(
            'success'      => !empty($result['success']),
            'errorCode'    => isset($result['errorCode']) ? $result['errorCode'] : null,
            'errorMessage' => isset($result['errorMessage']) ? $result['errorMessage'] : null,
        )));
        $order->save();

        if (empty($result['success'])) {
            $message = !empty($result['errorMessage'])
                ? $result['errorMessage']
                : __('iyzico iptal islemi basarisiz.', 'kolai');
            $order->add_order_note(sprintf(
                /* translators: 1: paymentId, 2: error message */
                __('iyzico iptal BASARISIZ: paymentId %1$s - %2$s', 'kolai'),
                $payment_id,
                $message
            ));
        } else {
            $order->add_order_note(sprintf(
                /* translators: %s: paymentId */
                __('iyzico iptal basarili: paymentId %s', 'kolai'),
                $payment_id
            ));
        }

        return $result;
    }

    /**
     * Handler for the woocommerce_order_status_cancelled action.
     *
     * @param int           $order_id
     * @param WC_Order|null $order
     * @return void
     */
    public function on_order_cancelled($order_id, $order = null) {
        $order = $this->resolve_order($order ? $order : $order_id);
        if (!$order) {
            return;
        }

        if ($order->get_payment_method() !== self::PAYMENT_METHOD) {
            return;
        }

        // Idempotency: skip if iyzico already reported a successful cancel.
        $existing = $order->get_meta(Kolai_Meta_Keys::get('cancel_result'));
        if ($existing) {
            $decoded = json_decode($existing, true);
            if (is_array($decoded) && !empty($decoded['success'])) {
                return;
            }
        }

        if (!$this->client->is_configured()) {
            Kolai_Logger::warning('payment', 'Order cancelled but iyzico not configured; skipping iyzico cancel', array(
                'order_id' => $order->get_id(),
            ));
            $order->add_order_note(__('Siparis iptal edildi ancak iyzico API anahtarlari ayarli olmadigi icin iyzico iptali yapilamadi.', 'kolai'));
            return;
        }

        $this->cancel_order($order);
    }

    /**
     * Decode the stored item transactions meta into an array.
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_item_transactions($order) {
        $raw = $order->get_meta(Kolai_Meta_Keys::get('item_transactions'));
        if (empty($raw)) {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Decode the per-transaction refunded-amount tracker.
     *
     * @param WC_Order $order
     * @return array<string,float>
     */
    private function get_refunded_map($order) {
        $raw = $order->get_meta(Kolai_Meta_Keys::get('refunded_transactions'));
        if (empty($raw)) {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Persist the per-transaction refunded-amount tracker.
     *
     * @param WC_Order            $order
     * @param array<string,float> $map
     * @return void
     */
    private function save_refunded_map($order, $map) {
        $order->update_meta_data(Kolai_Meta_Keys::get('refunded_transactions'), wp_json_encode($map));
        $order->save();
    }

    /**
     * Resolve a WC_Order from an order object or id.
     *
     * @param WC_Order|int $order
     * @return WC_Order|null
     */
    private function resolve_order($order) {
        if ($order instanceof WC_Order) {
            return $order;
        }
        $resolved = wc_get_order($order);
        return ($resolved instanceof WC_Order) ? $resolved : null;
    }
}
