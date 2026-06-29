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
     * @param array $products Each entry is either a numeric product id (legacy)
     *                        or an object: { productId, variationId?, quantity? }.
     * @param array $address
     * @return array
     */
    public function get_shipment_options($products, $address) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        if (!is_array($products) || empty($products)) {
            throw new Kolai_Invalid_Product_List_Exception('Products list is required');
        }

        $destination = Kolai_Address::normalize_destination_minimal($address);
        $package = $this->build_package($products, $destination);

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
     * Normalize a products list into shipping lines with real quantities.
     *
     * Accepts both the legacy form (an array of numeric product ids) and the
     * quantity-aware form (objects with productId/variationId/quantity), so a
     * client can adopt quantities incrementally without breaking.
     *
     * @param array $products
     * @return array<int,array{product_id:int,variation_id:int,quantity:int}>
     */
    private function normalize_lines($products) {
        $lines = array();

        foreach ($products as $entry) {
            $variation_id = 0;
            $quantity     = 1;

            if (is_array($entry)) {
                $product_id = 0;
                foreach (array('productId', 'product_id', 'id') as $k) {
                    if (isset($entry[$k]) && is_numeric($entry[$k])) {
                        $product_id = (int) $entry[$k];
                        break;
                    }
                }
                foreach (array('variationId', 'variation_id') as $k) {
                    if (isset($entry[$k]) && is_numeric($entry[$k])) {
                        $variation_id = (int) $entry[$k];
                        break;
                    }
                }
                foreach (array('quantity', 'qty') as $k) {
                    if (isset($entry[$k]) && is_numeric($entry[$k])) {
                        $quantity = (int) $entry[$k];
                        break;
                    }
                }
            } elseif (is_numeric($entry)) {
                $product_id = (int) $entry;
            } else {
                throw new Kolai_Invalid_Product_List_Exception('Product IDs must be numeric');
            }

            if ($product_id <= 0) {
                throw new Kolai_Invalid_Product_List_Exception('Product IDs must be numeric');
            }
            if ($quantity < 1) {
                $quantity = 1;
            }

            $lines[] = array(
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
            );
        }

        return $lines;
    }

    /**
     * Build a shipping package for calculation.
     *
     * Honors the real per-line quantity so quantity/weight-based methods price
     * correctly and free-shipping minimums are evaluated against the true
     * subtotal.
     *
     * @param array $products    Legacy id list or quantity-aware line objects.
     * @param array $destination
     * @return array
     */
    private function build_package($products, $destination) {
        $lines = $this->normalize_lines($products);

        $contents = array();
        $contents_cost = 0.0;
        $index = 0;
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        foreach ($lines as $line) {
            // Use the variation when present so its own price/weight are applied.
            $lookup_id = $line['variation_id'] > 0 ? $line['variation_id'] : $line['product_id'];
            $product = wc_get_product($lookup_id);
            if (!$product) {
                throw new Kolai_Product_Not_Found_Exception();
            }

            if (!$product->needs_shipping()) {
                continue;
            }

            $quantity   = $line['quantity'];
            $price       = (float) $product->get_price();
            $line_total = round($price * $quantity, $decimals);
            $contents_cost += $line_total;

            $contents[$index] = array(
                'key' => (string) $product->get_id() . '-' . $index,
                'product_id' => $line['product_id'],
                'variation_id' => $line['variation_id'],
                'variation' => array(),
                'quantity' => $quantity,
                'data' => $product,
                'line_total' => $line_total,
                'line_subtotal' => $line_total,
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
            Kolai_Logger::warning('shipping', 'No shipping rates resolved for package', array(
                'zone_id'     => $zone_id,
                'destination' => $package['destination'],
            ));
        }

        return $rates;
    }

    /**
     * Get a specific rate by id for given products and address.
     *
     * @param array  $products Legacy id list or quantity-aware line objects.
     * @param array  $address
     * @param string $rate_id
     * @return WC_Shipping_Rate
     */
    public function get_rate_by_id($products, $address, $rate_id) {
        $destination = Kolai_Address::normalize_destination($address);
        $package = $this->build_package($products, $destination);
        $this->prime_customer_context($destination);

        $rates = $this->get_rates_for_package($package);
        if (empty($rates) || !isset($rates[$rate_id])) {
            throw new Kolai_Invalid_Shipment_Option_Exception('Invalid shipment option');
        }

        return $rates[$rate_id];
    }
}
