<?php
/**
 * Order Service - Business logic for orders
 *
 * @package    Kolai
 * @subpackage Kolai/includes/order
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Service class
 */
class Kolai_Order_Service {

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Create order from external request.
     *
     * @param array $payload
     * @return array
     */
    public function create_order($payload) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        if (!is_array($payload)) {
            Kolai_Logger::warning('order', 'Order payload is not an array');
            throw new Kolai_Invalid_Order_Request_Exception('Invalid request body');
        }

        $buyer = isset($payload['buyer']) ? $payload['buyer'] : null;
        $billing = isset($payload['billingAddress']) ? $payload['billingAddress'] : null;
        $shipping = isset($payload['shippingAddress']) ? $payload['shippingAddress'] : null;
        $products = isset($payload['products']) ? $payload['products'] : null;
        $shipment_option_id = isset($payload['shipmentOptionId']) ? $payload['shipmentOptionId'] : null;
        $discount_amount = isset($payload['discountAmount']) ? $payload['discountAmount'] : null;

        Kolai_Logger::debug('order', 'create_order parsing payload', array(
            'product_count'        => is_array($products) ? count($products) : 0,
            'has_billing'          => is_array($billing),
            'has_shipping'         => is_array($shipping),
            'shipment_option_id'   => $shipment_option_id,
            'has_discount'         => $discount_amount !== null,
        ));

        $this->validate_buyer($buyer);
        $this->validate_billing_invoice($billing);
        Kolai_Address::validate_address($billing);
        Kolai_Address::validate_address($shipping);
        $items = $this->validate_products($products);

        Kolai_Logger::debug('order', 'create_order validation passed', array(
            'item_count' => count($items),
        ));

        if (empty($shipment_option_id)) {
            throw new Kolai_Invalid_Shipment_Option_Exception('shipmentOptionId is required');
        }

        $order = wc_create_order();
        if (is_wp_error($order)) {
            Kolai_Logger::error('order', 'wc_create_order failed', array(
                'error' => $order->get_error_message(),
            ));
            throw new Kolai_Internal_Error_Exception('Order creation failed');
        }

        Kolai_Logger::info('order', 'Order shell created', array('order_id' => $order->get_id()));

        // Everything below mutates the freshly-created order shell. If any step
        // fails (invalid shipment option, discount over total, …) delete the
        // shell so a failed request never leaves an orphan order behind.
        try {
            $customer_id = $this->resolve_customer_id($buyer['email']);
            if ($customer_id) {
                $order->set_customer_id($customer_id);
            }

            $order->set_address(Kolai_Address::build_order_address($billing, $buyer, true), 'billing');
            $order->set_address(Kolai_Address::build_order_address($shipping, $buyer, false), 'shipping');

            // Address mapping diagnostic: caller sends cityId (-> state) and
            // districtId (-> city). Recurring "city empty" / "wrong state"
            // complaints trace to the caller omitting districtId or sending a
            // cityId whose plate code doesn't match the address. Log the mapped
            // il/ilce (no street/name/phone PII) so we can see what arrived.
            Kolai_Logger::info('order', 'Address mapped', array(
                'order_id'            => $order->get_id(),
                'billing_cityId'      => isset($billing['cityId']) ? sanitize_text_field($billing['cityId']) : null,
                'billing_state'       => $order->get_billing_state(),
                'billing_district'    => $billing['district'] ?? $billing['districtId'] ?? null,
                'billing_city'        => $order->get_billing_city(),
                'shipping_cityId'     => isset($shipping['cityId']) ? sanitize_text_field($shipping['cityId']) : null,
                'shipping_state'      => $order->get_shipping_state(),
                'shipping_district'   => $shipping['district'] ?? $shipping['districtId'] ?? null,
                'shipping_city'       => $order->get_shipping_city(),
            ));

            $this->apply_billing_invoice_meta($order, $billing);

            foreach ($items as $item) {
                $order->add_product($item['product'], $item['quantity']);
            }

            // Pass the real quantities (and variation ids) so the shipping rate is
            // calculated against the actual package, not one unit per product.
            $shipping_service = new Kolai_Shipping_Service();
            $rate = $shipping_service->get_rate_by_id($this->extract_shipping_lines($items), $shipping, $shipment_option_id);

            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_shipping_rate($rate);
            $order->add_item($shipping_item);

            $order->set_currency(get_woocommerce_currency());
            $order->set_payment_method('kolai-app');
            $order->set_payment_method_title('Kolai App');

            $order->calculate_totals();

            if (!is_null($discount_amount)) {
                $this->apply_discount($order, $discount_amount);
            }

            $order->set_status('pending');
            $order->save();
        } catch (Throwable $e) {
            $this->delete_order_shell($order);
            throw $e;
        }

        Kolai_Logger::info('order', 'Order saved', array(
            'order_id' => $order->get_id(),
            'total'    => (float) $order->get_total(),
            'status'   => $order->get_status(),
        ));

        $hold_minutes = (int) get_option('woocommerce_hold_stock_minutes', 60);
        if ($hold_minutes < 1) {
            $hold_minutes = 60;
        }
        $order_expire_at = gmdate('c', time() + ($hold_minutes * 60));

        return array(
            'orderId' => $order->get_id(),
            'orderNumber' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'paymentMethod' => $order->get_payment_method(),
            'orderExpireAt' => $order_expire_at,
        );
    }

    /**
     * Force-delete a partially-built order shell after a creation failure.
     *
     * @param WC_Order $order
     * @return void
     */
    private function delete_order_shell($order) {
        try {
            $order->delete(true);
            Kolai_Logger::info('order', 'Deleted orphan order shell after creation failure', array(
                'order_id' => $order->get_id(),
            ));
        } catch (Throwable $inner) {
            Kolai_Logger::error('order', 'Failed to delete orphan order shell', array(
                'order_id' => $order->get_id(),
                'error'    => $inner->getMessage(),
            ));
        }
    }

    /**
     * Get order status types as key-value (status slug => label).
     *
     * @return array<string, string>
     */
    public function get_order_types() {
        if (!$this->is_woocommerce_active()) {
            return array();
        }
        $statuses = wc_get_order_statuses();
        $result = array();
        foreach ($statuses as $key => $label) {
            $slug = strpos($key, 'wc-') === 0 ? substr($key, 3) : $key;
            $result[ $slug ] = $label;
        }
        return $result;
    }

    /**
     * Get order by ID.
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_by_id($order_id) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_id()) {
            throw new Kolai_Not_Found_Exception('Order not found');
        }
        return $this->format_order_response($order);
    }

    /**
     * Update order status.
     *
     * @param int   $order_id
     * @param array $payload Must contain orderStatus (valid WooCommerce status slug).
     * @return array
     */
    public function update_order_status($order_id, $payload) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_id()) {
            throw new Kolai_Not_Found_Exception('Order not found');
        }
        $new_status = isset($payload['orderStatus']) ? trim((string) $payload['orderStatus']) : '';
        if ($new_status === '') {
            throw new Kolai_Invalid_Order_Request_Exception('orderStatus is required');
        }
        $valid_slugs = array_keys($this->get_order_types());
        if (!in_array($new_status, $valid_slugs, true)) {
            throw new Kolai_Invalid_Order_Request_Exception('Invalid orderStatus: ' . $new_status);
        }

        // Persist iyzico payment fields first so payment_complete() and any
        // downstream integrations observe them on the order.
        $this->apply_payment_meta($order, $payload);

        // Reconcile the total with the charged paidPrice (installment interest or
        // discount) before payment_complete() so emails/totals reflect it.
        $this->apply_installment_adjustment($order, $payload);

        $payment_id    = isset($payload['paymentId']) ? trim((string) $payload['paymentId']) : '';
        $paid_statuses = function_exists('wc_get_is_paid_statuses')
            ? wc_get_is_paid_statuses()
            : array('processing', 'completed');

        if ($payment_id !== '' && in_array($new_status, $paid_statuses, true)) {
            // A successful payment must go through WooCommerce's payment-completion
            // lifecycle, not a bare set_status(): payment_complete() records the
            // transaction id and date_paid, reduces stock exactly once, and fires
            // woocommerce_payment_complete for downstream integrations. It is a
            // no-op when the order is already paid, so calling it is idempotent.
            $order->payment_complete($payment_id);

            // payment_complete() picks the status via the
            // woocommerce_payment_complete_order_status filter (default
            // 'processing'); honor an explicitly requested paid status if different.
            if ($order->get_status() !== $new_status) {
                $order->set_status($new_status);
            }
            // Ensure queued meta and any status override persist even when
            // payment_complete() short-circuited on an already-paid order.
            $order->save();
        } else {
            $order->set_status($new_status);
            $order->save();
        }

        return $this->format_order_response($order);
    }

    /**
     * Persist payment fields (paymentId, itemTransactions) as order meta.
     *
     * @param WC_Order $order
     * @param array    $payload
     * @return void
     */
    private function apply_payment_meta($order, $payload) {
        if (!is_array($payload)) {
            return;
        }

        if (isset($payload['paymentId'])) {
            $payment_id = trim((string) $payload['paymentId']);
            if ($payment_id !== '') {
                $order->update_meta_data(Kolai_Meta_Keys::get('payment_id'), sanitize_text_field($payment_id));
            }
        }

        if (isset($payload['itemTransactions']) && is_array($payload['itemTransactions'])) {
            $transactions = array();
            foreach ($payload['itemTransactions'] as $transaction) {
                if (!is_array($transaction)) {
                    continue;
                }
                $transactions[] = array(
                    'itemId'                => isset($transaction['itemId']) ? sanitize_text_field(trim((string) $transaction['itemId'])) : '',
                    'paymentTransactionId'  => isset($transaction['paymentTransactionId']) ? sanitize_text_field(trim((string) $transaction['paymentTransactionId'])) : '',
                    'transactionStatus'     => isset($transaction['transactionStatus']) ? (int) $transaction['transactionStatus'] : null,
                    'price'                 => isset($transaction['price']) ? (float) $transaction['price'] : null,
                    'paidPrice'             => isset($transaction['paidPrice']) ? (float) $transaction['paidPrice'] : null,
                );
            }
            if (!empty($transactions)) {
                $order->update_meta_data(Kolai_Meta_Keys::get('item_transactions'), wp_json_encode($transactions));
            }
        }
    }

    /**
     * Format order for API response.
     *
     * @param WC_Order $order
     * @return array
     */
    private function format_order_response($order) {
        $hold_minutes = (int) get_option('woocommerce_hold_stock_minutes', 60);
        if ($hold_minutes < 1) {
            $hold_minutes = 60;
        }
        $date_created = $order->get_date_created();
        $order_expire_at = $date_created
            ? gmdate('c', $date_created->getTimestamp() + ($hold_minutes * 60))
            : null;
        return array(
            'orderId' => $order->get_id(),
            'orderNumber' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'paymentMethod' => $order->get_payment_method(),
            'orderExpireAt' => $order_expire_at,
            'dateCreated' => $date_created ? $date_created->format('c') : null,
            'dateModified' => $order->get_date_modified() ? $order->get_date_modified()->format('c') : null,
        );
    }

    /**
     * Validate buyer info.
     *
     * @param array $buyer
     * @return void
     */
    private function validate_buyer($buyer) {
        if (!is_array($buyer) || empty($buyer['email'])) {
            throw new Kolai_Invalid_Order_Request_Exception('buyer.email is required');
        }
    }

    /**
     * Validate billing invoice fields.
     *
     * @param array $billing
     * @return void
     */
    private function validate_billing_invoice($billing) {
        if (!is_array($billing)) {
            return;
        }

        $invoice_type = isset($billing['invoiceType']) ? strtolower(trim((string) $billing['invoiceType'])) : '';
        if ($invoice_type === '') {
            return;
        }

        if (!in_array($invoice_type, array('personal', 'company'), true)) {
            throw new Kolai_Invalid_Order_Request_Exception('billingAddress.invoiceType must be personal or company');
        }

        $tax_id = isset($billing['taxId']) ? trim((string) $billing['taxId']) : '';
        if ($tax_id !== '') {
            if ($invoice_type === 'company' && !preg_match('/^\d{10}$/', $tax_id)) {
                throw new Kolai_Invalid_Order_Request_Exception('billingAddress.taxId must be 10 digits for company invoice');
            }
            if ($invoice_type === 'personal' && !preg_match('/^\d{11}$/', $tax_id)) {
                throw new Kolai_Invalid_Order_Request_Exception('billingAddress.taxId must be 11 digits for personal invoice');
            }
        }
    }

    /**
     * Persist billing invoice fields as order meta.
     *
     * @param WC_Order $order
     * @param array    $billing
     * @return void
     */
    private function apply_billing_invoice_meta($order, $billing) {
        if (!is_array($billing)) {
            return;
        }

        if (isset($billing['invoiceType'])) {
            $invoice_type = strtolower(trim((string) $billing['invoiceType']));
            if ($invoice_type !== '') {
                $order->update_meta_data(
                    Kolai_Meta_Keys::get('invoice_type'),
                    sanitize_text_field(Kolai_Meta_Keys::invoice_value($invoice_type))
                );
            }
        }

        if (isset($billing['taxId'])) {
            $tax_id = trim((string) $billing['taxId']);
            if ($tax_id !== '') {
                $invoice_type = isset($billing['invoiceType']) ? $billing['invoiceType'] : '';
                $order->update_meta_data(Kolai_Meta_Keys::tax_id_key($invoice_type), sanitize_text_field($tax_id));
            }
        }

        if (isset($billing['taxOffice'])) {
            $tax_office = trim((string) $billing['taxOffice']);
            if ($tax_office !== '') {
                $order->update_meta_data(Kolai_Meta_Keys::get('tax_office'), sanitize_text_field($tax_office));
            }
        }
    }

    /**
     * Validate product items and check stock.
     *
     * @param array $products
     * @return array
     */
    private function validate_products($products) {
        if (!is_array($products) || empty($products)) {
            throw new Kolai_Invalid_Product_List_Exception('Products list is required');
        }

        $items = array();
        foreach ($products as $item) {
            if (!is_array($item) || empty($item['productId'])) {
                throw new Kolai_Invalid_Product_List_Exception('productId is required');
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            if ($quantity < 1) {
                throw new Kolai_Invalid_Product_List_Exception('quantity must be at least 1');
            }

            $product = wc_get_product((int) $item['productId']);
            if (!$product) {
                throw new Kolai_Product_Not_Found_Exception();
            }

            $this->assert_stock($product, $quantity);

            $items[] = array(
                'product' => $product,
                'quantity' => $quantity,
            );
        }

        return $items;
    }

    /**
     * Check stock rules.
     *
     * @param WC_Product $product
     * @param int        $quantity
     * @return void
     */
    private function assert_stock($product, $quantity) {
        if (!$product->is_in_stock() && !$product->backorders_allowed()) {
            throw new Kolai_Insufficient_Stock_Exception('Product is out of stock');
        }

        if ($product->managing_stock()) {
            $stock = (int) $product->get_stock_quantity();
            if ($stock < $quantity && !$product->backorders_allowed()) {
                throw new Kolai_Insufficient_Stock_Exception('Insufficient stock quantity');
            }
        }
    }

    /**
     * Resolve customer id by email.
     *
     * @param string $email
     * @return int
     */
    private function resolve_customer_id($email) {
        $user = get_user_by('email', $email);
        return $user ? (int) $user->ID : 0;
    }

    /**
     * Build quantity-aware shipping lines from validated order items so the
     * shipping rate is calculated against the real package (quantities and
     * variations), not one unit per product.
     *
     * @param array $items
     * @return array<int,array{productId:int,variationId:int,quantity:int}>
     */
    private function extract_shipping_lines($items) {
        $lines = array();
        foreach ($items as $item) {
            $product      = $item['product'];
            $product_id   = $product->get_id();
            $variation_id = 0;
            if ($product->is_type('variation')) {
                $variation_id = $product->get_id();
                $product_id   = $product->get_parent_id();
            }
            $lines[] = array(
                'productId'   => $product_id,
                'variationId' => $variation_id,
                'quantity'    => (int) $item['quantity'],
            );
        }
        return $lines;
    }

    /**
     * Apply discount to order (tax included).
     *
     * @param WC_Order $order
     * @param mixed    $discount_amount
     * @return void
     */
    private function apply_discount($order, $discount_amount) {
        if (!is_numeric($discount_amount)) {
            throw new Kolai_Discount_Exceeds_Total_Exception('discountAmount must be numeric');
        }

        $discount = (float) $discount_amount;
        if ($discount <= 0) {
            throw new Kolai_Invalid_Order_Request_Exception('discountAmount must be greater than 0');
        }

        $total_before = (float) $order->get_total();
        if ($discount > $total_before) {
            throw new Kolai_Discount_Exceeds_Total_Exception();
        }

        // discountAmount is a tax-inclusive (gross) reduction, applied as a
        // negative NON-taxable fee: the full gross comes off the order total
        // without splitting out KDV (matches the vade farki treatment).
        $order->add_item($this->build_gross_fee_item(-$discount, 'Discount'));
        $order->calculate_totals(false);

        // Invariant: the order total must drop by exactly the requested discount.
        // Per-rate rounding can introduce sub-cent drift; flag anything larger so
        // a real allocation bug is never silently shipped.
        $total_after = (float) $order->get_total();
        $drift = round(($total_before - $total_after) - $discount, 2);
        if (abs($drift) > 0.01) {
            Kolai_Logger::warning('order', 'Discount invariant drift detected', array(
                'order_id'     => $order->get_id(),
                'total_before' => round($total_before, 2),
                'total_after'  => round($total_after, 2),
                'discount'     => round($discount, 2),
                'drift'        => $drift,
            ));
        }
    }

    /**
     * Build a non-taxable fee item for a signed gross amount.
     *
     * The full gross is added to the order total without splitting out KDV, so
     * get_total() moves by exactly $gross. Positive raises the total (vade
     * farki), negative lowers it (indirim). Caller must add_item() it and
     * calculate_totals(false).
     *
     * @param float  $gross Signed amount.
     * @param string $name  Fee line label.
     * @return WC_Order_Item_Fee
     */
    private function build_gross_fee_item($gross, $name) {
        $fee = new WC_Order_Item_Fee();
        $fee->set_name($name);
        $fee->set_amount(round($gross, 2));
        $fee->set_total(round($gross, 2));
        $fee->set_tax_status('none');
        $fee->set_taxes(array());

        return $fee;
    }

    /**
     * Reconcile the order total with the paid price after an installment payment.
     *
     * The PATCH payload carries the actually-charged `paidPrice` and the
     * `installment` count. The difference between paidPrice and the order's
     * current base total is the installment interest ("vade farki", positive) or,
     * when the buyer paid less, a discount ("indirim", negative). It is recorded
     * as a fee line so get_total() equals paidPrice, plus meta under the
     * configurable installment_count / installment_fee keys.
     *
     * Idempotent: any previously-added installment fee is stripped and the total
     * recomputed before measuring the difference, so repeated PATCHes with the
     * same paidPrice converge instead of stacking fees.
     *
     * @param WC_Order $order
     * @param array    $payload
     * @return void
     */
    private function apply_installment_adjustment($order, $payload) {
        if (!isset($payload['paidPrice']) || !is_numeric($payload['paidPrice'])) {
            return;
        }
        $paid_price = round((float) $payload['paidPrice'], 2);
        if ($paid_price <= 0) {
            return;
        }
        $installment = isset($payload['installment']) ? (int) $payload['installment'] : 1;

        // Strip any prior installment fee and recalc so get_total() reflects the
        // base price (products + shipping + discount) without our adjustment.
        $removed = false;
        foreach ($order->get_items('fee') as $item_id => $fee_item) {
            if ($fee_item->get_meta('_kolai_installment_fee') === 'yes') {
                $order->remove_item($item_id);
                $removed = true;
            }
        }
        if ($removed) {
            $order->calculate_totals(false);
        }

        if ($installment > 1) {
            $order->update_meta_data(Kolai_Meta_Keys::get('installment_count'), $installment);
        }

        $base_total = round((float) $order->get_total(), 2);
        $diff = round($paid_price - $base_total, 2);

        if ($diff === 0.0) {
            $order->delete_meta_data(Kolai_Meta_Keys::get('installment_fee'));
            return;
        }

        // A negative adjustment (indirim) must not drive the total below zero.
        if ($diff < 0 && abs($diff) > $base_total) {
            Kolai_Logger::warning('order', 'Installment adjustment exceeds order total; skipped', array(
                'order_id'   => $order->get_id(),
                'paid_price' => $paid_price,
                'base_total' => $base_total,
                'diff'       => $diff,
            ));
            return;
        }

        if ($diff > 0) {
            $name = ($installment > 1)
                /* translators: %d: installment count */
                ? sprintf(__('%d Taksit icin Vade Farki', 'kolai'), $installment)
                : __('Vade Farki', 'kolai');
        } else {
            $name = __('Indirim', 'kolai');
        }
        // Vade farki is a non-taxable fee: the full gross lands on the order total
        // (matching iyzico paidPrice); its KDV is not tracked in WC tax reports.
        $fee = $this->build_gross_fee_item($diff, $name);
        $fee->add_meta_data('_kolai_installment_fee', 'yes', true);
        $order->add_item($fee);
        $order->calculate_totals(false);

        $order->update_meta_data(Kolai_Meta_Keys::get('installment_fee'), $diff);
    }
}
