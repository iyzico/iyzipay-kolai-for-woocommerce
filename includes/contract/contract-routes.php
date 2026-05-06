<?php
/**
 * Contract Routes - REST API route definitions for contracts
 *
 * @package    Kolai
 * @subpackage Kolai/includes/contract
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract Routes class
 */
class Kolai_Contract_Routes extends Kolai_Route_Base {

    /**
     * Contract service instance
     *
     * @var Kolai_Contract_Service
     */
    private $contract_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->contract_service = new Kolai_Contract_Service();
    }

    /**
     * Register contract routes
     */
    public function register_routes() {
        register_rest_route('kolai/v1', '/contracts', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'retrieve_contracts'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_CONTRACT),
        ));

        register_rest_route('kolai/v1', '/contracts/clarification-text', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_clarification_text'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_CONTRACT),
        ));
    }

    /**
     * Retrieve all contract templates with placeholders preserved for the client.
     *
     * @param WP_REST_Request $_request REST request object.
     * @return WP_REST_Response
     */
    public function retrieve_contracts($_request) {
        return $this->handle(function () {
            Kolai_Logger::info('contract', 'retrieve_contracts called');
            return $this->contract_service->get_contracts();
        }, $_request);
    }

    /**
     * Retrieve the configured clarification text page URL.
     *
     * @param WP_REST_Request $_request REST request object.
     * @return WP_REST_Response
     */
    public function get_clarification_text($_request) {
        return $this->handle(function () {
            Kolai_Logger::info('contract', 'get_clarification_text called');
            return $this->contract_service->get_clarification_text_link();
        }, $_request);
    }
}
