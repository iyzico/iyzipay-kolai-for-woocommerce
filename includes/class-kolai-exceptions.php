<?php
/**
 * Custom exceptions for Kolai API
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Kolai exception.
 */
class Kolai_Exception extends Exception {

    /**
     * @var string
     */
    protected $error_code;

    /**
     * @param string $message
     * @param string $error_code
     * @param int    $status
     */
    public function __construct($message, $error_code, $status = 400) {
        parent::__construct($message, (int) $status);
        $this->error_code = $error_code;
    }

    /**
     * @return string
     */
    public function get_error_code() {
        return $this->error_code;
    }

    /**
     * Convert exception to standard response array.
     *
     * @return array
     */
    public function to_response() {
        return Kolai_Response::from_exception($this);
    }
}

/**
 * 400 Bad Request exception.
 */
class Kolai_Bad_Request_Exception extends Kolai_Exception {
    public function __construct($message, $error_code = Kolai_Constants::ERROR_BAD_REQUEST) {
        parent::__construct($message, $error_code, 400);
    }
}

/**
 * 404 Not Found exception.
 */
class Kolai_Not_Found_Exception extends Kolai_Exception {
    public function __construct($message, $error_code = Kolai_Constants::ERROR_NOT_FOUND) {
        parent::__construct($message, $error_code, 404);
    }
}

/**
 * 500 Internal Server Error exception.
 */
class Kolai_Internal_Error_Exception extends Kolai_Exception {
    public function __construct($message, $error_code = Kolai_Constants::ERROR_INTERNAL_ERROR) {
        parent::__construct($message, $error_code, 500);
    }
}

/**
 * 503 Service Unavailable exception.
 */
class Kolai_Service_Unavailable_Exception extends Kolai_Exception {
    public function __construct($message, $error_code = Kolai_Constants::ERROR_SERVICE_UNAVAILABLE) {
        parent::__construct($message, $error_code, 503);
    }
}

/**
 * Product not found exception.
 */
class Kolai_Product_Not_Found_Exception extends Kolai_Not_Found_Exception {
    public function __construct($message = 'Product not found', $error_code = Kolai_Constants::ERROR_PRODUCT_NOT_FOUND) {
        parent::__construct($message, $error_code);
    }
}

/**
 * WooCommerce inactive exception.
 */
class Kolai_WooCommerce_Inactive_Exception extends Kolai_Service_Unavailable_Exception {
    public function __construct($message = 'WooCommerce is not active', $error_code = Kolai_Constants::ERROR_WOOCOMMERCE_INACTIVE) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Product not visible exception.
 */
class Kolai_Product_Not_Visible_Exception extends Kolai_Not_Found_Exception {
    public function __construct($message = 'Product not visible', $error_code = Kolai_Constants::ERROR_PRODUCT_NOT_VISIBLE) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Product variation parent not found exception.
 */
class Kolai_Product_Variation_Parent_Not_Found_Exception extends Kolai_Not_Found_Exception {
    public function __construct($message = 'Variation parent product not found', $error_code = Kolai_Constants::ERROR_VARIATION_PARENT_NOT_FOUND) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Invalid product list exception.
 */
class Kolai_Invalid_Product_List_Exception extends Kolai_Bad_Request_Exception {
    public function __construct($message = 'Invalid product list', $error_code = Kolai_Constants::ERROR_INVALID_PRODUCT_LIST) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Invalid address exception.
 */
class Kolai_Invalid_Address_Exception extends Kolai_Bad_Request_Exception {
    public function __construct($message = 'Invalid address', $error_code = Kolai_Constants::ERROR_INVALID_ADDRESS) {
        parent::__construct($message, $error_code);
    }
}

/**
 * No shipping options exception.
 */
class Kolai_No_Shipping_Options_Exception extends Kolai_Not_Found_Exception {
    public function __construct($message = 'No shipping options available', $error_code = Kolai_Constants::ERROR_NO_SHIPPING_OPTIONS) {
        parent::__construct($message, $error_code);
    }
}

/**
 * 409 Conflict exception.
 */
class Kolai_Conflict_Exception extends Kolai_Exception {
    public function __construct($message, $error_code) {
        parent::__construct($message, $error_code, 409);
    }
}

/**
 * Invalid order request exception.
 */
class Kolai_Invalid_Order_Request_Exception extends Kolai_Bad_Request_Exception {
    public function __construct($message = 'Invalid order request', $error_code = Kolai_Constants::ERROR_INVALID_ORDER_REQUEST) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Invalid shipment option exception.
 */
class Kolai_Invalid_Shipment_Option_Exception extends Kolai_Bad_Request_Exception {
    public function __construct($message = 'Invalid shipment option', $error_code = Kolai_Constants::ERROR_INVALID_SHIPMENT_OPTION) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Insufficient stock exception.
 */
class Kolai_Insufficient_Stock_Exception extends Kolai_Conflict_Exception {
    public function __construct($message = 'Insufficient stock', $error_code = Kolai_Constants::ERROR_INSUFFICIENT_STOCK) {
        parent::__construct($message, $error_code);
    }
}

/**
 * Discount exceeds total exception.
 */
class Kolai_Discount_Exceeds_Total_Exception extends Kolai_Bad_Request_Exception {
    public function __construct($message = 'Discount exceeds order total', $error_code = Kolai_Constants::ERROR_DISCOUNT_EXCEEDS_TOTAL) {
        parent::__construct($message, $error_code);
    }
}
