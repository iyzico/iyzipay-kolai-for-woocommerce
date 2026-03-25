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
            'callback'            => array($this, 'retrieve_contract'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_CONTRACT),
        ));
    }

    /**
     * Retrieve contract template with seller info filled in.
     * Remaining placeholders are returned as-is for the client to replace.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response
     */
    public function retrieve_contract($request) {
        return $this->handle(function () use ($request) {
            $body = $request->get_json_params();

            if (empty($body) || !isset($body['type'])) {
                throw new Kolai_Invalid_Contract_Request_Exception('Missing required field: type');
            }

            $type = sanitize_text_field($body['type']);
            $types = $this->contract_service->get_available_types();

            if (!isset($types[$type])) {
                throw new Kolai_Invalid_Contract_Type_Exception("Invalid contract type: {$type}");
            }

            $contract = $this->contract_service->get_contract($type);

            return array(
                'type'         => $type,
                'title'        => $types[$type],
                'content'      => $contract['content'],
                'placeholders' => $contract['placeholders'],
            );
        });
    }
}
