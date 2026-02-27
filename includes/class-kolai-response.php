<?php
/**
 * Base API response builder for Kolai
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kolai API response helper
 */
class Kolai_Response {

    /**
     * Build a base response array.
     *
     * @param mixed  $data          Response payload data.
     * @param int    $status        HTTP status code.
     * @param string $error_code    Optional error code.
     * @param string $error_message Optional error message.
     * @return array
     */
    protected static function build($data, $status = 200, $error_code = null, $error_message = null) {
        $is_success = ((int) $status) < 400;
        return array(
            'status' => $is_success ? Kolai_Constants::STATUS_SUCCESS : Kolai_Constants::STATUS_FAILURE,
            'systemTime' => current_time('c'),
            'errorCode' => $error_code,
            'errorMessage' => $error_message,
            'woocommerceVersion' => self::get_woocommerce_version(),
            'wordpressVersion' => get_bloginfo('version'),
            'phpVersion' => PHP_VERSION,
            'data' => $data,
        );
    }

    /**
     * Build a success response.
     *
     * @param mixed $data Response payload data.
     * @return array
     */
    public static function success($data) {
        return self::build($data, 200, null, null);
    }

    /**
     * Build an error response.
     *
     * @param int    $status        HTTP status code.
     * @param string $error_code    Error code.
     * @param string $error_message Error message.
     * @return array
     */
    protected static function error($status, $error_code, $error_message) {
        return self::build(null, (int) $status, $error_code, $error_message);
    }

    /**
     * Build a response from a Kolai exception.
     *
     * @param Kolai_Exception $exception
     * @return array
     */
    public static function from_exception($exception) {
        return self::error(
            $exception->getCode(),
            $exception->get_error_code(),
            $exception->getMessage()
        );
    }

    /**
     * Build a standard unexpected error response.
     *
     * @return array
     */
    public static function unexpected_error() {
        return self::error(500, Kolai_Constants::ERROR_INTERNAL_ERROR, 'Unexpected error');
    }

    /**
     * Get WooCommerce version if available.
     *
     * @return string|null
     */
    private static function get_woocommerce_version() {
        if (function_exists('WC') && is_object(WC())) {
            return WC()->version;
        }

        $version = get_option('woocommerce_version');
        if ($version) {
            return $version;
        }

        return null;
    }
}
