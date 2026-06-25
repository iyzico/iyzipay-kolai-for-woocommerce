<?php
/**
 * Shipping Service - Business logic for shipment options
 *
 * @package    Kolai
 * @subpackage Kolai/includes/shipping
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping Service class
 */
class Kolai_Shipping_Service {

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Get shipment options for given products and address.
     *
     * @param array $product_ids
     * @param array $address
     * @return array
     */
    public function get_shipment_options($product_ids, $address) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        if (!is_array($product_ids) || empty($product_ids)) {
            throw new Kolai_Invalid_Product_List_Exception('Products list is required');
        }

        $destination = Kolai_Address::normalize_destination_minimal($address);
        $package = $this->build_package($product_ids, $destination);

        $this->prime_customer_context($destination);
        $rates = $this->get_rates_for_package($package);
        if (empty($rates)) {
            throw new Kolai_No_Shipping_Options_Exception();
        }

        $options = array();
        foreach ($rates as $rate_id => $rate) {
            $taxes = array_values($rate->get_taxes());
            $tax_total = 0.0;
            foreach ($taxes as $tax) {
                $tax_total += (float) $tax;
            }

            $options[] = array(
                'id' => $rate->get_id(),
                'label' => $rate->get_label(),
                'methodId' => $rate->get_method_id(),
                'cost' => (float) $rate->get_cost(),
                'tax' => $tax_total,
                'price' => (float) $rate->get_cost() + $tax_total,
            );
        }

        return array(
            'options' => $options,
        );
    }

    /**
     * Build destination array for shipping.
     *
     * @param array $address
     * @return array
     */
    /**
     * Build a shipping package for calculation.
     *
     * @param array $product_ids
     * @param array $destination
     * @return array
     */
    private function build_package($product_ids, $destination) {
        $contents = array();
        $contents_cost = 0.0;
        $index = 0;

        foreach ($product_ids as $product_id) {
            if (!is_numeric($product_id)) {
                throw new Kolai_Invalid_Product_List_Exception('Product IDs must be numeric');
            }

            $product = wc_get_product((int) $product_id);
            if (!$product) {
                throw new Kolai_Product_Not_Found_Exception();
            }

            if (!$product->needs_shipping()) {
                continue;
            }

            $price = (float) $product->get_price();
            $contents_cost += $price;

            $contents[$index] = array(
                'key' => (string) $product->get_id(),
                'product_id' => $product->get_id(),
                'variation_id' => 0,
                'variation' => array(),
                'quantity' => 1,
                'data' => $product,
                'line_total' => $price,
                'line_subtotal' => $price,
                'line_tax' => 0,
                'line_subtotal_tax' => 0,
            );

            $index++;
        }

        if (empty($contents)) {
            throw new Kolai_No_Shipping_Options_Exception('No shippable products found');
        }

        return array(
            'contents' => $contents,
            'contents_cost' => $contents_cost,
            'applied_coupons' => array(),
            'destination' => $destination,
            'user' => array(
                'ID' => get_current_user_id(),
            ),
        );
    }

    /**
     * Prime WooCommerce customer shipping context.
     *
     * @param array $destination
     * @return void
     */
    private function prime_customer_context($destination) {
        // Free Shipping's is_available() accesses WC()->cart unconditionally
        // (WC_Shipping_Free_Shipping::is_available, lines ~165/178) before the
        // availability filter runs, so a null cart fatals in this REST context.
        // Ensure a cart exists first; correctness of the min_amount check is
        // handled by the filter in get_rates_for_package().
        $this->ensure_cart();

        if (is_null(WC()->customer)) {
            WC()->customer = new WC_Customer(get_current_user_id(), true);
        }

        WC()->customer->set_shipping_location(
            $destination['country'],
            $destination['state'],
            $destination['postcode'],
            $destination['city']
        );
        WC()->customer->set_billing_location(
            $destination['country'],
            $destination['state'],
            $destination['postcode'],
            $destination['city']
        );
    }

    /**
     * Ensure WC()->cart is initialized.
     *
     * Shipping methods (e.g. Free Shipping with a minimum-amount requirement)
     * read from WC()->cart during is_available(). In a REST context the cart is
     * not bootstrapped automatically, so load it once if missing.
     *
     * @return void
     */
    private function ensure_cart() {
        if (is_null(WC()->cart) && function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    /**
     * Evaluate Free Shipping availability from the package instead of the cart.
     *
     * The cart we load for the REST request is empty, so WooCommerce's own
     * min_amount check would always fail. Re-evaluate it against the package
     * contents. Coupon-based requirements ('coupon', 'both') cannot be assessed
     * without a cart and are left to WooCommerce's (false) result.
     *
     * @param bool              $is_available
     * @param array             $package
     * @param WC_Shipping_Method $method
     * @return bool
     */
    public function evaluate_free_shipping_for_package($is_available, $package, $method) {
        $requires = isset($method->requires) ? $method->requires : '';
        if (!in_array($requires, array('min_amount', 'either'), true)) {
            return $is_available;
        }

        $total = 0.0;
        if (!empty($package['contents'])) {
            foreach ($package['contents'] as $item) {
                $total += isset($item['line_total']) ? (float) $item['line_total'] : 0.0;
            }
        }

        // 'min_amount' and 'either' both succeed when the minimum is met; in this
        // headless context there are no coupons, so 'either' reduces to this.
        return $total >= (float) $method->min_amount;
    }

    /**
     * Calculate rates for a package without cart/session dependencies.
     *
     * @param array $package
     * @return array
     */
    private function get_rates_for_package($package) {
        add_filter('woocommerce_shipping_free_shipping_is_available', array($this, 'evaluate_free_shipping_for_package'), 10, 3);

        $rates = array();
        try {
            $zone = WC_Shipping_Zones::get_zone_matching_package($package);
            $methods = $zone ? $zone->get_shipping_methods(true) : array();

            foreach ($methods as $method) {
                if (!$method->enabled) {
                    continue;
                }

                $method_rates = $method->get_rates_for_package($package);
                if (!empty($method_rates)) {
                    $rates = $rates + $method_rates;
                }
            }
        } finally {
            remove_filter('woocommerce_shipping_free_shipping_is_available', array($this, 'evaluate_free_shipping_for_package'), 10);
        }

        if (empty($rates)) {
            $zone_id = $zone ? $zone->get_id() : 0;
            error_log(sprintf('[Kolai] No rates. Zone: %s Destination: %s', $zone_id, wp_json_encode($package['destination'])));
        }

        return $rates;
    }

    /**
     * Get a specific rate by id for given products and address.
     *
     * @param array  $product_ids
     * @param array  $address
     * @param string $rate_id
     * @return WC_Shipping_Rate
     */
    public function get_rate_by_id($product_ids, $address, $rate_id) {
        $destination = Kolai_Address::normalize_destination($address);
        $package = $this->build_package($product_ids, $destination);
        $this->prime_customer_context($destination);

        $rates = $this->get_rates_for_package($package);
        if (empty($rates) || !isset($rates[$rate_id])) {
            throw new Kolai_Invalid_Shipment_Option_Exception('Invalid shipment option');
        }

        return $rates[$rate_id];
    }
}
