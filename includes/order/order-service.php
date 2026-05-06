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

        $customer_id = $this->resolve_customer_id($buyer['email']);
        if ($customer_id) {
            $order->set_customer_id($customer_id);
        }

        $order->set_address(Kolai_Address::build_order_address($billing, $buyer, true), 'billing');
        $order->set_address(Kolai_Address::build_order_address($shipping, $buyer, false), 'shipping');
        $this->apply_billing_invoice_meta($order, $billing);

        foreach ($items as $item) {
            $order->add_product($item['product'], $item['quantity']);
        }

        $shipping_service = new Kolai_Shipping_Service();
        $rate = $shipping_service->get_rate_by_id($this->extract_product_ids($items), $shipping, $shipment_option_id);

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
        $order->set_status($new_status);
        $order->save();
        return $this->format_order_response($order);
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
                $order->update_meta_data('billing_invoice_type', $invoice_type);
            }
        }

        if (isset($billing['taxId'])) {
            $tax_id = trim((string) $billing['taxId']);
            if ($tax_id !== '') {
                $order->update_meta_data('billing_tax_id', sanitize_text_field($tax_id));
            }
        }

        if (isset($billing['taxOffice'])) {
            $tax_office = trim((string) $billing['taxOffice']);
            if ($tax_office !== '') {
                $order->update_meta_data('billing_tax_office', sanitize_text_field($tax_office));
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
     * Extract product ids from items.
     *
     * @param array $items
     * @return array
     */
    private function extract_product_ids($items) {
        $ids = array();
        foreach ($items as $item) {
            $ids[] = $item['product']->get_id();
        }
        return $ids;
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

        $fee = new WC_Order_Item_Fee();
        $fee->set_name('Discount');
        $fee->set_amount(-$discount);
        $fee->set_total(-$discount);
        $fee->set_tax_status('none');
        $fee->set_taxes(array());

        $order->add_item($fee);
        $order->calculate_totals();
    }
}
