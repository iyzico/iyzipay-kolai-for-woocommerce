<?php
/**
 * Constants for Kolai API
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kolai constants class.
 */
class Kolai_Constants {

    // Status strings
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';

    // 1xxx: Kolai plugin errors
    const ERROR_INTERNAL_ERROR = '1000';
    const ERROR_BAD_REQUEST = '1001';
    const ERROR_NOT_FOUND = '1002';
    const ERROR_SERVICE_UNAVAILABLE = '1003';
    const ERROR_WOOCOMMERCE_INACTIVE = '1004';

    // 2xxx: Product errors
    const ERROR_INVALID_PRODUCT_ID = '2000';
    const ERROR_PRODUCT_NOT_FOUND = '2001';
    const ERROR_PRODUCT_NOT_VISIBLE = '2002';
    const ERROR_VARIATION_PARENT_NOT_FOUND = '2003';
    const ERROR_INVALID_PRODUCT_LIST = '2004';

    // 3xxx: Shipping errors
    const ERROR_INVALID_ADDRESS = '3000';
    const ERROR_NO_SHIPPING_OPTIONS = '3001';

    // 4xxx: Order errors
    const ERROR_INVALID_ORDER_REQUEST = '4000';
    const ERROR_INVALID_SHIPMENT_OPTION = '4001';
    const ERROR_INSUFFICIENT_STOCK = '4002';
    const ERROR_DISCOUNT_EXCEEDS_TOTAL = '4003';
}
