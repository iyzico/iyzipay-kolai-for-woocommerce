<?php
/**
 * Base class for Kolai REST routes
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base route helper for standardized responses.
 */
abstract class Kolai_Route_Base {

    /**
     * Execute a handler with standardized error handling.
     *
     * @param callable $handler
     * @return WP_REST_Response
     */
    protected function handle($handler) {
        try {
            $data = call_user_func($handler);
            $response = Kolai_Response::success($data);
            return new WP_REST_Response($response, 200);
        } catch (Kolai_Exception $e) {
            error_log(sprintf('[Kolai] %s (%s)', $e->getMessage(), $e->get_error_code()));
            $response = $e->to_response();
            return new WP_REST_Response($response, $e->getCode());
        } catch (Exception $e) {
            error_log(sprintf('[Kolai] Unexpected error: %s', $e->getMessage()));
            $response = Kolai_Response::unexpected_error();
            return new WP_REST_Response($response, 500);
        }
    }
}
