<?php
/**
 * REST API endpoints for Kolai plugin
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API class
 */
class Kolai_API {
    
    /**
     * Product routes instance
     *
     * @var Kolai_Product_Routes
     */
    private $product_routes;

    /**
     * Shipping routes instance
     *
     * @var Kolai_Shipping_Routes
     */
    private $shipping_routes;

    /**
     * Order routes instance
     *
     * @var Kolai_Order_Routes
     */
    private $order_routes;
    
    /**
     * Register REST API routes
     */
    public function __construct() {
        // Load product classes
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-constants.php';
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-exceptions.php';
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-response.php';
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-route-base.php';
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-address.php';
        require_once KOLAI_INCLUDES_DIR . 'product/product-mapper.php';
        require_once KOLAI_INCLUDES_DIR . 'product/product-service.php';
        require_once KOLAI_INCLUDES_DIR . 'product/product-routes.php';
        require_once KOLAI_INCLUDES_DIR . 'shipping/shipping-service.php';
        require_once KOLAI_INCLUDES_DIR . 'shipping/shipping-routes.php';
        require_once KOLAI_INCLUDES_DIR . 'order/order-service.php';
        require_once KOLAI_INCLUDES_DIR . 'order/order-routes.php';
        
        // Initialize product routes
        $this->product_routes = new Kolai_Product_Routes();
        $this->shipping_routes = new Kolai_Shipping_Routes();
        $this->order_routes = new Kolai_Order_Routes();
        
        // Register routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $this->product_routes->register_routes();
        $this->shipping_routes->register_routes();
        $this->order_routes->register_routes();
    }
}
