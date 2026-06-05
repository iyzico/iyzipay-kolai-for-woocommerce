<?php
/**
 * Review Service - Business logic for product reviews
 *
 * @package    Kolai
 * @subpackage Kolai/includes/review
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Review Service class.
 *
 * WooCommerce stores product reviews as WordPress comments with
 * `comment_type = 'review'`. The 1-5 rating is in the `commentmeta` table
 * under the `rating` key, and the verified-purchase flag under `verified`.
 */
class Kolai_Review_Service {

    /**
     * Default page size when caller does not provide one.
     */
    const DEFAULT_PER_PAGE = 100;

    /**
     * Hard cap on per_page to protect the server from OOM/timeouts.
     */
    const MAX_PER_PAGE = 200;

    /**
     * Map of allowed `status` query values to WP comment status slugs used by
     * get_comments(). Anything else is rejected as a bad request.
     *
     * @var array
     */
    private static $allowed_status = array(
        'approved' => 'approve',
        'hold'     => 'hold',
        'spam'     => 'spam',
        'trash'    => 'trash',
        'all'      => 'all',
    );

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * List reviews for a single product.
     *
     * Accepted args:
     *   - page           int    1-based page index (default 1)
     *   - per_page       int    items per page (default DEFAULT_PER_PAGE, max MAX_PER_PAGE)
     *   - status         string approved|hold|spam|trash|all (default 'approved')
     *   - rating         int    1-5 exact match (optional)
     *   - modified_after string ISO-8601 date; only reviews on/after this point
     *
     * @param int   $product_id
     * @param array $args
     * @return array { items, pagination }
     */
    public function get_reviews_for_product($product_id, $args = array()) {
        if (!$this->is_woocommerce_active()) {
            Kolai_Logger::error('review', 'WooCommerce not active during review list');
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            throw new Kolai_Bad_Request_Exception('Invalid product ID', Kolai_Constants::ERROR_INVALID_PRODUCT_ID);
        }

        // Verify the product exists. We deliberately do NOT enforce
        // is_visible() here — historical reviews of hidden/draft products are
        // still useful for the consumer (sentiment analysis, audit, sync).
        $product = wc_get_product($product_id);
        if (!$product) {
            Kolai_Logger::warning('review', 'Product not found during review list', array(
                'product_id' => $product_id,
            ));
            throw new Kolai_Product_Not_Found_Exception();
        }

        $args = wp_parse_args($args, array(
            'page'           => 1,
            'per_page'       => self::DEFAULT_PER_PAGE,
            'status'         => 'approved',
            'rating'         => null,
            'modified_after' => '',
        ));

        $page     = max(1, (int) $args['page']);
        $per_page = max(1, min(self::MAX_PER_PAGE, (int) $args['per_page']));

        $status_key = is_string($args['status']) ? strtolower(trim($args['status'])) : 'approved';
        if (!isset(self::$allowed_status[$status_key])) {
            throw new Kolai_Invalid_Review_Request_Exception(
                'Invalid status filter; allowed: ' . implode(', ', array_keys(self::$allowed_status))
            );
        }
        $wp_status = self::$allowed_status[$status_key];

        $rating = null;
        if ($args['rating'] !== null && $args['rating'] !== '') {
            $rating = (int) $args['rating'];
            if ($rating < 1 || $rating > 5) {
                throw new Kolai_Invalid_Rating_Exception('rating must be between 1 and 5');
            }
        }

        $comment_args = array(
            'post_id' => $product_id,
            'type'    => 'review',
            'status'  => $wp_status,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'number'  => $per_page,
            'offset'  => ($page - 1) * $per_page,
        );

        if ($rating !== null) {
            $comment_args['meta_query'] = array(
                array(
                    'key'     => 'rating',
                    'value'   => $rating,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            );
        }

        if (!empty($args['modified_after'])) {
            $modified = strtotime((string) $args['modified_after']);
            if ($modified !== false) {
                $comment_args['date_query'] = array(
                    array(
                        'column' => 'comment_date_gmt',
                        'after'  => gmdate('Y-m-d H:i:s', $modified),
                    ),
                );
            }
        }

        Kolai_Logger::info('review', 'List query started', array(
            'product_id' => $product_id,
            'page'       => $page,
            'per_page'   => $per_page,
            'status'     => $status_key,
            'rating'     => $rating,
        ));

        $started = microtime(true);

        $comments = get_comments($comment_args);

        // Get total count separately (get_comments with count=true returns int)
        $count_args = $comment_args;
        unset($count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order']);
        $count_args['count'] = true;
        $total = (int) get_comments($count_args);
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

        $query_ms = (int) round((microtime(true) - $started) * 1000);
        Kolai_Logger::info('review', 'List query finished', array(
            'duration_ms' => $query_ms,
            'count'       => is_array($comments) ? count($comments) : 0,
            'total'       => $total,
            'total_pages' => $total_pages,
        ));

        $items = array();
        if (is_array($comments)) {
            foreach ($comments as $comment) {
                $items[] = $this->format_review_data($comment);
            }
        }

        return array(
            'items'      => $items,
            'pagination' => array(
                'page'       => $page,
                'perPage'    => $per_page,
                'total'      => $total,
                'totalPages' => $total_pages,
            ),
        );
    }

    /**
     * Get a single review by comment ID.
     *
     * @param int $review_id
     * @return array
     */
    public function get_review_by_id($review_id) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        $review_id = (int) $review_id;
        if ($review_id <= 0) {
            throw new Kolai_Invalid_Review_Request_Exception('Invalid review ID');
        }

        Kolai_Logger::info('review', 'Single review fetch started', array('review_id' => $review_id));

        $comment = get_comment($review_id);
        if (!$comment) {
            Kolai_Logger::warning('review', 'Review not found', array('review_id' => $review_id));
            throw new Kolai_Review_Not_Found_Exception();
        }

        // Must be a review type comment.
        if ($comment->comment_type !== 'review') {
            Kolai_Logger::warning('review', 'Comment is not a review', array(
                'review_id'    => $review_id,
                'comment_type' => $comment->comment_type,
            ));
            throw new Kolai_Review_Not_Found_Exception();
        }

        // Must be attached to a product.
        $post = get_post((int) $comment->comment_post_ID);
        if (!$post || $post->post_type !== 'product') {
            Kolai_Logger::warning('review', 'Review parent is not a product', array(
                'review_id' => $review_id,
                'post_id'   => $comment->comment_post_ID,
                'post_type' => $post ? $post->post_type : null,
            ));
            throw new Kolai_Review_Not_Found_Exception();
        }

        $data = $this->format_review_data($comment);

        Kolai_Logger::info('review', 'Single review fetch finished', array(
            'review_id'  => $review_id,
            'product_id' => $data['product_id'],
            'status'     => $data['status'],
        ));

        return $data;
    }

    /**
     * Format a WP_Comment object into the raw shape consumed by the mapper.
     * Intentionally excludes PII (author email/IP/user agent) so the mapper
     * never has the chance to leak them.
     *
     * @param WP_Comment $comment
     * @return array
     */
    private function format_review_data($comment) {
        $rating          = (int) get_comment_meta($comment->comment_ID, 'rating', true);
        $verified_buyer  = get_comment_meta($comment->comment_ID, 'verified', true);

        // WP comment_approved values: '1' approved, '0' hold, 'spam', 'trash'
        $status_map = array(
            '1'     => 'approved',
            '0'     => 'hold',
            'spam'  => 'spam',
            'trash' => 'trash',
        );
        $approved = (string) $comment->comment_approved;
        $status   = isset($status_map[$approved]) ? $status_map[$approved] : $approved;

        $date_gmt = $comment->comment_date_gmt;
        $iso      = $date_gmt ? gmdate('c', strtotime($date_gmt . ' UTC')) : null;

        return array(
            'id'              => (int) $comment->comment_ID,
            'product_id'      => (int) $comment->comment_post_ID,
            'rating'          => $rating > 0 ? $rating : null,
            'author'          => $comment->comment_author,
            'content'         => $comment->comment_content,
            'date'            => $iso,
            'status'          => $status,
            'verified_buyer'  => $verified_buyer === '1' || $verified_buyer === 1 || $verified_buyer === true,
            'parent_id'       => (int) $comment->comment_parent,
        );
    }
}
