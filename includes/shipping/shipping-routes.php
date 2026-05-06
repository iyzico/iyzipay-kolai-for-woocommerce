<?php
/**
 * Shipping Routes - REST API route definitions for shipping options
 *
 * @package    Kolai
 * @subpackage Kolai/includes/shipping
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping Routes class
 */
class Kolai_Shipping_Routes extends Kolai_Route_Base {

    /**
     * Shipping service instance
     *
     * @var Kolai_Shipping_Service
     */
    private $shipping_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->shipping_service = new Kolai_Shipping_Service();
    }

    /**
     * Register shipping routes
     */
    public function register_routes() {
        register_rest_route('kolai/v1', '/shipment-options', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_shipment_options'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_SHIPMENT_OPTIONS),
        ));
    }

    /**
     * Shipment options endpoint handler
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_shipment_options($request) {
        return $this->handle(function() use ($request) {
            $params = $request->get_json_params();

            if (!is_array($params)) {
                Kolai_Logger::warning('shipping', 'Invalid request body for shipment options');
                throw new Kolai_Bad_Request_Exception('Invalid request body', Kolai_Constants::ERROR_BAD_REQUEST);
            }

            $products = isset($params['products']) ? $params['products'] : null;
            $address = isset($params['address']) ? $params['address'] : null;

            Kolai_Logger::info('shipping', 'get_shipment_options called', array(
                'product_count' => is_array($products) ? count($products) : 0,
                'has_address'   => is_array($address),
            ));

            return $this->shipping_service->get_shipment_options($products, $address);
        }, $request);
    }
}
