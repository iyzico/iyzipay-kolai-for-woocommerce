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
     * Calculate rates for a package without cart/session dependencies.
     *
     * @param array $package
     * @return array
     */
    private function get_rates_for_package($package) {
        $zone = WC_Shipping_Zones::get_zone_matching_package($package);
        $methods = $zone ? $zone->get_shipping_methods(true) : array();

        $rates = array();
        foreach ($methods as $method) {
            if (!$method->enabled) {
                continue;
            }

            $method_rates = $method->get_rates_for_package($package);
            if (!empty($method_rates)) {
                $rates = $rates + $method_rates;
            }
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
