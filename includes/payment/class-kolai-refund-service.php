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

    // Cross-process refund lock (prevents concurrent double-allocation).
    const LOCK_PREFIX  = 'kolai_refund_lock_';
    const LOCK_GROUP   = 'kolai';
    const LOCK_TTL      = 60; // seconds, only used for the object-cache lock path.

    // Refund meta written on the reconciling local refund record.
    const REFUND_OPERATION_META = '_kolai_refund_operation_id';

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

        $order_id = $order->get_id();

        // Serialize all refund activity for this order. Without this, two
        // concurrent refund requests could read the same refunded ledger, both
        // allocate the same available balance, and double-refund at iyzico.
        $lock = $this->acquire_lock($order_id);
        if ($lock === false) {
            return new WP_Error('kolai_refund_locked', __(
                'Bu siparis icin baska bir iade islemi devam ediyor. Lutfen birkac saniye sonra tekrar deneyin.',
                'kolai'
            ));
        }

        try {
            // Reload the order AFTER acquiring the lock so we plan against the
            // freshest state, never a value read before a concurrent refund.
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('kolai_refund_error', __('Siparis bulunamadi.', 'kolai'));
            }

            $transactions = $this->get_item_transactions($order);
            if (empty($transactions)) {
                return new WP_Error('kolai_refund_error', __('Bu siparis icin iyzico islem bilgisi bulunamadi.', 'kolai'));
            }

            $refunded = $this->get_refunded_map($order);

            // Build the allocation plan before sending anything to iyzico. Amounts
            // already refunded (per the persisted ledger) are skipped, which makes
            // a retry after a partial failure refund only the outstanding balance.
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

            // Unique id for this refund attempt. Threaded into the iyzico
            // conversationId so two separate partial refunds against the same
            // transaction are never conflated, and recorded in the operation
            // ledger as an audit trail independent of WooCommerce refund objects.
            $operation_id   = $this->generate_operation_id();
            $currency       = $order->get_currency();
            $succeeded       = array();
            $succeeded_total = 0.0;

            foreach ($plan as $step) {
                $conversation_id = 'order-' . $order_id . '-tx-' . $step['paymentTransactionId'] . '-' . substr($operation_id, 0, 8);
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

                    if ($succeeded_total > self::EPSILON) {
                        // Some money already moved at iyzico. WooCommerce will delete
                        // the refund object it created (because we return an error),
                        // so persist the partial state and create a reconciling local
                        // refund record for the amount that actually moved — once
                        // WooCommerce has finished deleting its own record (shutdown).
                        $this->record_operation($order, $operation_id, $amount, $succeeded, 'partial', $message);
                        $this->schedule_partial_refund_record($order_id, $succeeded_total, $operation_id, $reason);
                        $order->add_order_note(sprintf(
                            /* translators: 1: amount refunded, 2: amount that failed, 3: error message */
                            __('iyzico KISMI iade: %1$s iade edildi, %2$s BASARISIZ - %3$s. Kalan tutar icin iadeyi tekrar calistirin; mukerrer iade olmaz.', 'kolai'),
                            number_format($succeeded_total, 2, '.', ''),
                            number_format(round($amount - $succeeded_total, 2), 2, '.', ''),
                            $message
                        ));
                    } else {
                        $this->record_operation($order, $operation_id, $amount, $succeeded, 'failed', $message);
                        $order->add_order_note(sprintf(
                            /* translators: 1: amount, 2: transaction id, 3: error message */
                            __('iyzico iade BASARISIZ: %1$s (islem %2$s) - %3$s', 'kolai'),
                            number_format($step['amount'], 2, '.', ''),
                            $step['paymentTransactionId'],
                            $message
                        ));
                    }

                    return new WP_Error('kolai_refund_error', $message);
                }

                // Track the money that just moved BEFORE persisting, so the ledger
                // and any reconciliation reflect reality even if the write fails.
                $refunded[$step['paymentTransactionId']] = round(
                    (isset($refunded[$step['paymentTransactionId']]) ? (float) $refunded[$step['paymentTransactionId']] : 0.0)
                    + $step['amount'],
                    2
                );
                $succeeded[$step['paymentTransactionId']] = round(
                    (isset($succeeded[$step['paymentTransactionId']]) ? (float) $succeeded[$step['paymentTransactionId']] : 0.0)
                    + $step['amount'],
                    2
                );
                $succeeded_total = round($succeeded_total + $step['amount'], 2);

                // The refunded ledger MUST persist now — it is what makes a retry
                // SKIP this transaction. If the write fails, money has already moved
                // at iyzico, so stop immediately instead of refunding further steps
                // or letting a blind retry re-refund this one. Flag for manual
                // reconciliation and preserve a local refund record for what moved.
                if (!$this->save_refunded_map($order, $refunded)) {
                    Kolai_Logger::error('payment', 'iyzico refund succeeded but refunded-ledger persistence failed', array(
                        'order_id'             => $order_id,
                        'operation_id'         => $operation_id,
                        'paymentTransactionId' => $step['paymentTransactionId'],
                        'amount'               => $step['amount'],
                    ));
                    $this->record_operation($order, $operation_id, $amount, $succeeded, 'needs_reconciliation', 'refunded ledger persistence failed');
                    $this->schedule_partial_refund_record($order_id, $succeeded_total, $operation_id, $reason);
                    $order->add_order_note(sprintf(
                        /* translators: 1: amount, 2: transaction id */
                        __('iyzico iadesi ALINDI (%1$s, islem %2$s) ancak yerel iade kaydi guncellenemedi. Mukerrer iadeyi onlemek icin iadeyi yeniden calistirmadan once durumu MANUEL kontrol edin.', 'kolai'),
                        number_format($step['amount'], 2, '.', ''),
                        $step['paymentTransactionId']
                    ));
                    return new WP_Error('kolai_refund_error', __(
                        'iyzico iadesi alindi ancak yerel kayit guncellenemedi. Mukerrer iadeyi onlemek icin manuel kontrol gerekiyor.',
                        'kolai'
                    ));
                }

                $order->add_order_note(sprintf(
                    /* translators: 1: amount, 2: transaction id */
                    __('iyzico iade basarili: %1$s (islem %2$s)', 'kolai'),
                    number_format($step['amount'], 2, '.', ''),
                    $step['paymentTransactionId']
                ));
            }

            $this->record_operation($order, $operation_id, $amount, $succeeded, 'success', '');

            return true;
        } finally {
            $this->release_lock($order_id, $lock);
        }
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
        try {
            $order->update_meta_data(Kolai_Meta_Keys::get('refunded_transactions'), wp_json_encode($map));
            $saved = $order->save();
            // HPOS data stores throw on write failure; the legacy store returns the
            // order id. Treat a falsy id as a failed persist.
            return (bool) $saved;
        } catch (\Throwable $e) {
            Kolai_Logger::error('payment', 'Failed to persist refunded ledger', array(
                'order_id' => $order->get_id(),
                'error'    => $e->getMessage(),
            ));
            return false;
        }
    }

    /**
     * Acquire an exclusive, cross-process lock for refunds on an order.
     *
     * Returns an opaque handle to pass back to release_lock(), or false when the
     * lock is already held (a concurrent refund is in progress).
     *
     * Strategy: when a persistent object cache is present (Redis/Memcached),
     * wp_cache_add() is atomic across processes — use it. Otherwise fall back to
     * a MySQL named lock (GET_LOCK), which is atomic on a single primary DB and
     * auto-releases when the connection closes, so a fatal can never deadlock it.
     *
     * @param int $order_id
     * @return string|false 'cache'|'db' handle, or false if already locked.
     */
    private function acquire_lock($order_id) {
        $key = self::LOCK_PREFIX . $order_id;

        if (wp_using_ext_object_cache()) {
            return wp_cache_add($key, 1, self::LOCK_GROUP, self::LOCK_TTL) ? 'cache' : false;
        }

        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $key, 0));
        return ((string) $got === '1') ? 'db' : false;
    }

    /**
     * Release a lock acquired via acquire_lock().
     *
     * @param int          $order_id
     * @param string|false $handle
     * @return void
     */
    private function release_lock($order_id, $handle) {
        if (!$handle) {
            return;
        }
        $key = self::LOCK_PREFIX . $order_id;
        if ($handle === 'cache') {
            wp_cache_delete($key, self::LOCK_GROUP);
            return;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
    }

    /**
     * Generate a unique idempotency/operation id for a refund attempt.
     *
     * @return string
     */
    private function generate_operation_id() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return uniqid('kolai-refund-', true);
    }

    /**
     * Append an entry to the durable refund operation ledger stored on the order.
     *
     * This survives independently of WooCommerce refund objects, so even when a
     * partial remote refund forces WooCommerce to delete its own refund record,
     * there is always an auditable trail of exactly what moved at iyzico.
     *
     * @param WC_Order            $order
     * @param string              $operation_id
     * @param float               $requested
     * @param array<string,float> $allocations Per-transaction amounts that succeeded.
     * @param string              $status      success|partial|failed
     * @param string              $message
     * @return void
     */
    private function record_operation($order, $operation_id, $requested, $allocations, $status, $message) {
        $raw = $order->get_meta(Kolai_Meta_Keys::get('refund_operations'));
        $log = $raw ? json_decode($raw, true) : array();
        if (!is_array($log)) {
            $log = array();
        }

        $log[] = array(
            'operationId' => $operation_id,
            'requested'   => round((float) $requested, 2),
            'allocations' => $allocations,
            'status'      => $status,
            'message'     => $message,
            'at'          => gmdate('c'),
        );

        // Bound the ledger so the meta value cannot grow without limit.
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }

        // The ledger is an audit aid: never let a persistence failure here break
        // the refund flow (which has its own, checked persistence for the map).
        try {
            $order->update_meta_data(Kolai_Meta_Keys::get('refund_operations'), wp_json_encode($log));
            $order->save();
        } catch (\Throwable $e) {
            Kolai_Logger::error('payment', 'Failed to persist refund operation ledger', array(
                'order_id'     => $order->get_id(),
                'operation_id' => $operation_id,
                'error'        => $e->getMessage(),
            ));
        }
    }

    /**
     * Schedule creation of a local WooCommerce refund record for the portion of a
     * refund that succeeded remotely before a later step failed.
     *
     * WooCommerce deletes the refund object it created whenever the gateway's
     * process_refund() returns WP_Error. Creating our own record immediately would
     * collide with that pending (about-to-be-deleted) object's amount validation,
     * so we defer to 'shutdown' — by then WooCommerce has deleted its record and
     * the order's remaining-refundable amount is restored.
     *
     * @param int    $order_id
     * @param float  $amount       Total amount actually refunded at iyzico.
     * @param string $operation_id
     * @param string $reason
     * @return void
     */
    private function schedule_partial_refund_record($order_id, $amount, $operation_id, $reason) {
        add_action('shutdown', function () use ($order_id, $amount, $operation_id, $reason) {
            $this->create_local_refund_record($order_id, $amount, $operation_id, $reason);
        });
    }

    /**
     * Create a local-only WooCommerce refund record (no gateway call) reflecting
     * money already moved at iyzico. Idempotent per operation id.
     *
     * @param int    $order_id
     * @param float  $amount
     * @param string $operation_id
     * @param string $reason
     * @return void
     */
    private function create_local_refund_record($order_id, $amount, $operation_id, $reason) {
        if (!function_exists('wc_create_refund')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $amount = round((float) $amount, 2);
        if ($amount <= self::EPSILON) {
            return;
        }

        // Idempotency: never create two reconciling records for one operation.
        foreach ($order->get_refunds() as $existing) {
            if ((string) $existing->get_meta(self::REFUND_OPERATION_META) === (string) $operation_id) {
                return;
            }
        }

        // Never exceed the order's remaining refundable amount.
        $remaining = (float) $order->get_remaining_refund_amount();
        if ($amount - $remaining > self::EPSILON) {
            $amount = round($remaining, 2);
        }
        if ($amount <= self::EPSILON) {
            Kolai_Logger::warning('payment', 'Skipping reconciling refund record: no remaining refundable amount', array(
                'order_id'     => $order_id,
                'operation_id' => $operation_id,
            ));
            return;
        }

        try {
            $refund = wc_create_refund(array(
                'amount'         => $amount,
                'reason'         => sprintf(
                    /* translators: 1: operation id, 2: original refund reason */
                    __('Kolai kismi iade mutabakati (islem %1$s). %2$s', 'kolai'),
                    $operation_id,
                    $reason
                ),
                'order_id'       => $order_id,
                'refund_payment' => false, // Money already moved at iyzico; record locally only.
                'restock_items'  => false,
            ));

            if (is_wp_error($refund)) {
                Kolai_Logger::error('payment', 'Failed to create reconciling partial refund record', array(
                    'order_id'     => $order_id,
                    'operation_id' => $operation_id,
                    'amount'       => $amount,
                    'error'        => $refund->get_error_message(),
                ));
                return;
            }

            $refund->update_meta_data(self::REFUND_OPERATION_META, $operation_id);
            $refund->save();

            $order->add_order_note(sprintf(
                /* translators: %s: amount */
                __('Kolai: kismi iade icin yerel WooCommerce iade kaydi olusturuldu (%s).', 'kolai'),
                number_format($amount, 2, '.', '')
            ));
        } catch (\Throwable $e) {
            Kolai_Logger::error('payment', 'Exception creating reconciling refund record', array(
                'order_id'     => $order_id,
                'operation_id' => $operation_id,
                'error'        => $e->getMessage(),
            ));
        }
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
