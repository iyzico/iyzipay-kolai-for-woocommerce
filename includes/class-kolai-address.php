<?php
/**
 * Address helper for Kolai
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Address helper class.
 */
class Kolai_Address {

    /**
     * Normalize destination array for shipping calculations.
     *
     * @param array $address
     * @return array
     */
    public static function normalize_destination($address) {
        self::validate_address($address);

        $country = sanitize_text_field($address['countryId']);
        $state = sanitize_text_field($address['cityId']);
        // iyzico now sends the district (ilce) as `town`; earlier it was
        // `district`, and the original contract used `districtId`. Take the first
        // non-empty of the three (handles the transition and empty-string fields)
        // so `city` is not silently left empty.
        $district = '';
        foreach (array('town', 'district', 'districtId') as $key) {
            if (!empty($address[$key])) {
                $district = $address[$key];
                break;
            }
        }
        $city = sanitize_text_field($district);

        // WooCommerce TR state codes are zero-padded 2-digit: TR01..TR81.
        if ($country === 'TR' && preg_match('/^\d+$/', $state)) {
            $state = 'TR' . str_pad($state, 2, '0', STR_PAD_LEFT);
        }

        // No district? Fall back to the province (il) name from WooCommerce's TR
        // state list (TR01 -> "Adana") so `city` is not left empty. Best-effort:
        // the il name is coarser than the real ilce, but better than a blank.
        if ($city === '' && $country === 'TR' && function_exists('WC')) {
            $states = WC()->countries->get_states('TR');
            if (!empty($states[$state])) {
                $city = $states[$state];
            }
        }

        return array(
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'postcode' => isset($address['postcode']) ? sanitize_text_field($address['postcode']) : '',
            'address_1' => isset($address['addressLine']) ? sanitize_text_field($address['addressLine']) : '',
            'address_2' => '',
        );
    }

    /**
     * Normalize destination for shipping options where only countryId/cityId are required.
     *
     * @param array $address
     * @return array
     */
    public static function normalize_destination_minimal($address) {
        if (!is_array($address) || empty($address['countryId']) || empty($address['cityId'])) {
            throw new Kolai_Invalid_Address_Exception('countryId and cityId are required');
        }

        $country = sanitize_text_field($address['countryId']);
        $state = sanitize_text_field($address['cityId']);

        // WooCommerce TR state codes are zero-padded 2-digit: TR01..TR81.
        if ($country === 'TR' && preg_match('/^\d+$/', $state)) {
            $state = 'TR' . str_pad($state, 2, '0', STR_PAD_LEFT);
        }

        return array(
            'country' => $country,
            'state' => $state,
            'city' => '',
            'postcode' => '',
            'address_1' => '',
            'address_2' => '',
        );
    }

    /**
     * Build order address array.
     *
     * @param array $address
     * @param array $buyer
     * @param bool  $include_contact
     * @return array
     */
    public static function build_order_address($address, $buyer, $include_contact) {
        $destination = self::normalize_destination($address);

        $order_address = array(
            'first_name' => isset($buyer['firstName']) ? sanitize_text_field($buyer['firstName']) : '',
            'last_name' => isset($buyer['lastName']) ? sanitize_text_field($buyer['lastName']) : '',
            'company' => isset($address['companyName']) ? sanitize_text_field($address['companyName']) : '',
            'address_1' => $destination['address_1'],
            'address_2' => $destination['address_2'],
            'city' => $destination['city'],
            'state' => $destination['state'],
            'postcode' => $destination['postcode'],
            'country' => $destination['country'],
        );

        if ($include_contact) {
            $order_address['email'] = isset($buyer['email']) ? sanitize_email($buyer['email']) : '';
            $order_address['phone'] = isset($buyer['phone']) ? self::normalize_phone($buyer['phone']) : '';
        }

        return $order_address;
    }

    /**
     * Normalize a Turkish phone number to bare 10-digit form.
     *
     * Strips a leading +90 / 0090 / 90 country code or a leading 0, so that
     * "+905355401122" is stored as "5355401122". Any input that is not a
     * recognised TR mobile shape (10 digits after stripping) is returned as the
     * sanitized original so foreign / malformed numbers are not silently mangled.
     *
     * @param string $phone
     * @return string
     */
    private static function normalize_phone($phone) {
        $sanitized = sanitize_text_field($phone);
        $digits = preg_replace('/\D+/', '', $sanitized);

        if (strlen($digits) === 12 && strpos($digits, '90') === 0) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : $sanitized;
    }

    /**
     * Validate address input.
     *
     * @param array $address
     * @return void
     */
    public static function validate_address($address) {
        if (!is_array($address)) {
            throw new Kolai_Invalid_Address_Exception('Address is required');
        }

        if (empty($address['countryId']) || empty($address['cityId'])) {
            throw new Kolai_Invalid_Address_Exception('countryId and cityId are required');
        }
    }
}
