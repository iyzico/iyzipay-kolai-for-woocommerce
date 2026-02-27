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
            'permission_callback' => '__return_true',
        ));

        register_rest_route('kolai/v1', '/order-types', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_types'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('kolai/v1', '/orders/(?P<orderId>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order'),
            'permission_callback' => '__return_true',
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
            'permission_callback' => '__return_true',
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
            return $this->order_service->create_order($params);
        });
    }

    /**
     * Get order status types (key-value)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_order_types($request) {
        return $this->handle(function() {
            return $this->order_service->get_order_types();
        });
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
            return $this->order_service->get_order_by_id($order_id);
        });
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
            return $this->order_service->update_order_status($order_id, $params ? $params : array());
        });
    }
}
