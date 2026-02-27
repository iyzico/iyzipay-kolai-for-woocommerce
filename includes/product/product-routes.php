<?php
/**
 * Product Routes - REST API route definitions for products
 *
 * @package    Kolai
 * @subpackage Kolai/includes/product
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Routes class
 */
class Kolai_Product_Routes extends Kolai_Route_Base {
    
    /**
     * Product service instance
     *
     * @var Kolai_Product_Service
     */
    private $product_service;
    
    /**
     * Product mapper instance
     *
     * @var Kolai_Product_Mapper
     */
    private $product_mapper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->product_service = new Kolai_Product_Service();
        $this->product_mapper = new Kolai_Product_Mapper();
    }
    
    /**
     * Register product routes
     */
    public function register_routes() {
        // Get all products
        register_rest_route('kolai/v1', '/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_products'),
            'permission_callback' => '__return_true',
        ));
        
        // Get single product by ID
        register_rest_route('kolai/v1', '/products/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        // Get product with variants by ID (if variation ID, returns parent product)
        register_rest_route('kolai/v1', '/products-with-variants/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product_with_variants'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }
    
    /**
     * Get products endpoint handler
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response
     */
    public function get_products($request) {
        return $this->handle(function() {
            $products = $this->product_service->get_all_products();
            return Kolai_Product_Mapper::map_multiple($products);
        });
    }
    
    /**
     * Get single product endpoint handler
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_product($request) {
        return $this->handle(function() use ($request) {
            $product_id = intval($request->get_param('id'));
            
            if (!$product_id) {
                throw new Kolai_Bad_Request_Exception('Invalid product ID', Kolai_Constants::ERROR_INVALID_PRODUCT_ID);
            }
            
            $product = $this->product_service->get_product_by_id($product_id);
            
            return Kolai_Product_Mapper::map_to_response($product);
        });
    }
    
    /**
     * Get product with variants endpoint handler
     * If variation ID is provided, returns parent product with all variations
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_product_with_variants($request) {
        return $this->handle(function() use ($request) {
            $product_id = intval($request->get_param('id'));
            
            if (!$product_id) {
                throw new Kolai_Bad_Request_Exception('Invalid product ID', Kolai_Constants::ERROR_INVALID_PRODUCT_ID);
            }
            
            $product = $this->product_service->get_product_with_variants_by_id($product_id);
            
            return Kolai_Product_Mapper::map_to_response($product);
        });
    }
}
