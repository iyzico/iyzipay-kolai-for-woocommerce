<?php
/**
 * Central resolver for order meta key names and invoice value mapping.
 *
 * Stores a single option (an associative array) that lets store owners
 * override the meta key under which Kolai writes/reads order data, and map
 * the canonical invoice type values (personal/company) to custom values
 * expected by third-party invoice/accounting plugins.
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta key resolver.
 */
class Kolai_Meta_Keys {

    /**
     * Option name holding the meta field map.
     */
    const OPTION = 'kolai_meta_field_map';

    /**
     * Field identifier => default meta key.
     *
     * @var array<string,string>
     */
    const DEFAULTS = array(
        'invoice_type'          => 'billing_invoice_type',
        'tax_id'                => 'billing_tax_id',
        'tax_office'            => 'billing_tax_office',
        'payment_id'            => 'kolai_payment_id',
        'item_transactions'     => 'kolai_item_transactions',
        'refunded_transactions' => 'kolai_refunded_transactions',
        'cancel_result'         => 'kolai_iyzico_cancel_result',
    );

    /**
     * Canonical invoice type => default stored value.
     *
     * @var array<string,string>
     */
    const INVOICE_VALUE_DEFAULTS = array(
        'personal' => 'personal',
        'company'  => 'company',
    );

    /**
     * Resolve the configured meta key for a field, falling back to the default.
     *
     * @param string $field One of the DEFAULTS identifiers.
     * @return string
     */
    public static function get($field) {
        $map = get_option(self::OPTION, array());
        if (is_array($map) && isset($map[$field]) && is_string($map[$field])) {
            $key = self::sanitize_meta_key($map[$field]);
            if ($key !== '') {
                return $key;
            }
        }
        return isset(self::DEFAULTS[$field]) ? self::DEFAULTS[$field] : $field;
    }

    /**
     * Map a canonical invoice type value (personal/company) to the value that
     * should be stored, using the configured override when present.
     *
     * @param string $canonical 'personal' or 'company'.
     * @return string
     */
    public static function invoice_value($canonical) {
        $canonical = strtolower(trim((string) $canonical));
        $field = 'invoice_value_' . $canonical;
        $map = get_option(self::OPTION, array());
        if (is_array($map) && isset($map[$field]) && is_string($map[$field]) && trim($map[$field]) !== '') {
            return $map[$field];
        }
        return isset(self::INVOICE_VALUE_DEFAULTS[$canonical]) ? self::INVOICE_VALUE_DEFAULTS[$canonical] : $canonical;
    }

    /**
     * Sanitize a meta key while preserving case and a leading underscore
     * (third-party plugins often use protected keys like _billing_invoice_type).
     *
     * @param string $key
     * @return string
     */
    public static function sanitize_meta_key($key) {
        $key = trim((string) $key);
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $key);
    }
}
