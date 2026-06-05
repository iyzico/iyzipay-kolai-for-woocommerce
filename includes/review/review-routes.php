<?php
/**
 * Review Routes - REST API route definitions for product reviews
 *
 * @package    Kolai
 * @subpackage Kolai/includes/review
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Review Routes class
 */
class Kolai_Review_Routes extends Kolai_Route_Base {

    /**
     * Review service instance
     *
     * @var Kolai_Review_Service
     */
    private $review_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->review_service = new Kolai_Review_Service();
    }

    /**
     * Register review routes
     */
    public function register_routes() {
        // List reviews for a product
        register_rest_route('kolai/v1', '/products/(?P<product_id>\d+)/reviews', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_product_reviews'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_REVIEWS),
            'args'                => array(
                'product_id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ),
                'page' => array(
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    },
                ),
                'per_page' => array(
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    },
                ),
                'status' => array(
                    'required' => false,
                ),
                'rating' => array(
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        if ($param === '' || $param === null) {
                            return true;
                        }
                        return is_numeric($param) && (int) $param >= 1 && (int) $param <= 5;
                    },
                ),
                'modified_after' => array(
                    'required' => false,
                ),
            ),
        ));

        // Get a single review by ID
        register_rest_route('kolai/v1', '/reviews/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_review'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_REVIEW),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param > 0;
                    },
                ),
            ),
        ));
    }

    /**
     * GET /products/{product_id}/reviews
     *
     * Query params:
     *   - page (default 1)
     *   - per_page (default 100, max 200)
     *   - status (default 'approved'; one of approved|hold|spam|trash|all)
     *   - rating (1-5, exact match)
     *   - modified_after (ISO-8601)
     *
     * Pagination metadata is returned in the response body envelope under a
     * top-level `pagination` field (mirroring /products list shape) — this
     * avoids the HTTP/2 multi-value header issues seen with custom X-* response headers.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_product_reviews($request) {
        return $this->handle(function () use ($request) {
            $product_id = (int) $request->get_param('product_id');

            $page         = $request->get_param('page') !== null ? (int) $request->get_param('page') : 1;
            $per_page_raw = $request->get_param('per_page');
            $per_page     = $per_page_raw !== null
                ? (int) $per_page_raw
                : Kolai_Review_Service::DEFAULT_PER_PAGE;

            $status         = (string) $request->get_param('status');
            $rating         = $request->get_param('rating');
            $modified_after = (string) $request->get_param('modified_after');

            Kolai_Logger::debug('review', 'List endpoint params', array(
                'product_id'     => $product_id,
                'page'           => $page,
                'per_page'       => $per_page,
                'status'         => $status,
                'rating'         => $rating,
                'modified_after' => $modified_after,
            ));

            $result = $this->review_service->get_reviews_for_product($product_id, array(
                'page'           => $page,
                'per_page'       => $per_page,
                'status'         => $status !== '' ? $status : 'approved',
                'rating'         => ($rating === '' || $rating === null) ? null : (int) $rating,
                'modified_after' => $modified_after,
            ));

            $this->add_response_meta('pagination', $result['pagination']);

            return Kolai_Review_Mapper::map_multiple($result['items']);
        }, $request);
    }

    /**
     * GET /reviews/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_review($request) {
        return $this->handle(function () use ($request) {
            $review_id = (int) $request->get_param('id');
            $data = $this->review_service->get_review_by_id($review_id);
            return Kolai_Review_Mapper::map_to_response($data);
        }, $request);
    }
}
