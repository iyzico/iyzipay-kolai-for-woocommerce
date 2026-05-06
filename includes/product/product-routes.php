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
        // List products (paginated)
        register_rest_route('kolai/v1', '/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_products'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_PRODUCTS),
            'args' => array(
                'page' => array(
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    },
                ),
                'per_page' => array(
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && (int) $param >= 1;
                    },
                ),
                'ids' => array(
                    'required' => false,
                ),
                'modified_after' => array(
                    'required' => false,
                ),
            ),
        ));

        // Get single product by ID
        register_rest_route('kolai/v1', '/products/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product'),
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_PRODUCT),
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
            'permission_callback' => Kolai_Auth::permission_callback(Kolai_Auth::SCOPE_RETRIEVE_PRODUCT_WITH_VARIANTS),
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
     * Get products endpoint handler.
     *
     * Supports:
     *   ?page=1&per_page=100
     *   ?ids=12,34,56
     *   ?modified_after=2026-01-01T00:00:00Z
     *
     * Pagination is exposed via response headers (X-Kolai-Total,
     * X-Kolai-Total-Pages, X-Kolai-Page, X-Kolai-Per-Page) to keep the
     * `data` field a flat array of products.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_products($request) {
        return $this->handle(function() use ($request) {
            $page = $request->get_param('page') !== null ? (int) $request->get_param('page') : 1;
            $per_page_raw = $request->get_param('per_page');
            $per_page = $per_page_raw !== null ? (int) $per_page_raw : Kolai_Product_Service::DEFAULT_PER_PAGE;

            $ids = array();
            $ids_param = $request->get_param('ids');
            if ($ids_param !== null && $ids_param !== '') {
                if (is_array($ids_param)) {
                    $ids = array_filter(array_map('intval', $ids_param));
                } else {
                    $ids = array_filter(array_map('intval', explode(',', (string) $ids_param)));
                }
            }

            $modified_after = (string) $request->get_param('modified_after');

            Kolai_Logger::debug('product', 'List endpoint params', array(
                'page'           => $page,
                'per_page'       => $per_page,
                'ids_count'      => count($ids),
                'modified_after' => $modified_after,
            ));

            $result = $this->product_service->get_all_products(array(
                'page'           => $page,
                'per_page'       => $per_page,
                'ids'            => $ids,
                'modified_after' => $modified_after,
            ));

            // Expose pagination metadata in the response body envelope (next
            // to `data`). Headers were tried previously but caused HTTP/2
            // "Header field must only have a single value" errors behind some
            // proxy stacks (e.g. Cloudflare strict mode).
            $this->add_response_meta('pagination', $result['pagination']);

            return Kolai_Product_Mapper::map_multiple($result['items']);
        }, $request);
    }

    /**
     * Get single product endpoint handler
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_product($request) {
        return $this->handle(function() use ($request) {
            $product_id = intval($request->get_param('id'));

            if (!$product_id) {
                throw new Kolai_Bad_Request_Exception('Invalid product ID', Kolai_Constants::ERROR_INVALID_PRODUCT_ID);
            }

            $product = $this->product_service->get_product_by_id($product_id);

            return Kolai_Product_Mapper::map_to_response($product);
        }, $request);
    }

    /**
     * Get product with variants endpoint handler.
     * If variation ID is provided, returns parent product with all variations.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_product_with_variants($request) {
        return $this->handle(function() use ($request) {
            $product_id = intval($request->get_param('id'));

            if (!$product_id) {
                throw new Kolai_Bad_Request_Exception('Invalid product ID', Kolai_Constants::ERROR_INVALID_PRODUCT_ID);
            }

            $product = $this->product_service->get_product_with_variants_by_id($product_id);

            return Kolai_Product_Mapper::map_to_response($product);
        }, $request);
    }
}
