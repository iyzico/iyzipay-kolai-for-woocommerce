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
        $city = sanitize_text_field($address['districtId']);

        // WooCommerce TR state codes are usually like TR34. Normalize if numeric.
        if ($country === 'TR' && preg_match('/^\d+$/', $state)) {
            $state = 'TR' . $state;
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

        // WooCommerce TR state codes are usually like TR34. Normalize if numeric.
        if ($country === 'TR' && preg_match('/^\d+$/', $state)) {
            $state = 'TR' . $state;
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
            $order_address['phone'] = isset($buyer['phone']) ? sanitize_text_field($buyer['phone']) : '';
        }

        return $order_address;
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

        if (empty($address['countryId']) || empty($address['cityId']) || empty($address['districtId'])) {
            throw new Kolai_Invalid_Address_Exception('countryId, cityId and districtId are required');
        }
    }
}
