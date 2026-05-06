<?php
/**
 * Order Routes - REST API route definitions for orders
 *
 * @package    Kolai
 * @subpackage Kolai/includes/order
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Routes class
 */
class Kolai_Order_Routes extends Kolai_Route_Base {

    /**
     * Order service instance
     *
     * @var Kolai_Order_Service
     */
    private $order_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->order_service = new Kolai_Order_Service();
    }

    /**
     * Register order routes
     */
    public function register_routes() {
        register_rest_route('kolai/v1', '/orders', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_order'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_CREATE_ORDER),
        ));

        register_rest_route('kolai/v1', '/order-types', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_types'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_ORDER_TYPES),
        ));

        register_rest_route('kolai/v1', '/orders/(?P<orderId>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_ORDER),
            'args' => array(
                'orderId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        register_rest_route('kolai/v1', '/orders/(?P<orderId>\d+)', array(
            'methods' => 'PATCH',
            'callback' => array($this, 'update_order'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_UPDATE_ORDER_STATUS),
            'args' => array(
                'orderId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Create order endpoint handler
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_order($request) {
        return $this->handle(function() use ($request) {
            $params = $request->get_json_params();
            Kolai_Logger::info('order', 'create_order called', array(
                'has_params'    => is_array($params),
                'product_count' => is_array($params) && isset($params['products']) ? count($params['products']) : 0,
            ));
            return $this->order_service->create_order($params);
        }, $request);
    }

    /**
     * Get order status types (key-value)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_order_types($request) {
        return $this->handle(function() {
            Kolai_Logger::info('order', 'get_order_types called');
            return $this->order_service->get_order_types();
        }, $request);
    }

    /**
     * Get single order by ID
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_order($request) {
        return $this->handle(function() use ($request) {
            $order_id = (int) $request->get_param('orderId');
            Kolai_Logger::info('order', 'get_order called', array('order_id' => $order_id));
            return $this->order_service->get_order_by_id($order_id);
        }, $request);
    }

    /**
     * Update order (e.g. orderStatus)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_order($request) {
        return $this->handle(function() use ($request) {
            $order_id = (int) $request->get_param('orderId');
            $params = $request->get_json_params();
            Kolai_Logger::info('order', 'update_order called', array(
                'order_id' => $order_id,
                'fields'   => is_array($params) ? array_keys($params) : array(),
            ));
            return $this->order_service->update_order_status($order_id, $params ? $params : array());
        }, $request);
    }
}
