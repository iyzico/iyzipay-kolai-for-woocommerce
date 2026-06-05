<?php
/**
 * Review Mapper - Maps review data to API response format
 *
 * @package    Kolai
 * @subpackage Kolai/includes/review
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Review Mapper class.
 *
 * Converts the snake_case shape produced by Kolai_Review_Service into the
 * camelCase DTO expected by the Java consumer. PII fields (author email/IP/
 * user agent) are not even passed through the service, so there is no risk
 * of them leaking through here.
 */
class Kolai_Review_Mapper {

    /**
     * Map a single review entry to the DTO shape.
     *
     * @param array $review_data
     * @return array|null
     */
    public static function map_to_response($review_data) {
        if (empty($review_data) || !is_array($review_data)) {
            return null;
        }

        $mapped = array();

        if (isset($review_data['id'])) {
            $mapped['id'] = (int) $review_data['id'];
        }
        if (isset($review_data['product_id'])) {
            $mapped['productId'] = (string) $review_data['product_id'];
        }
        if (array_key_exists('rating', $review_data)) {
            $mapped['rating'] = $review_data['rating'] !== null ? (int) $review_data['rating'] : null;
        }
        if (isset($review_data['author'])) {
            $mapped['author'] = (string) $review_data['author'];
        }
        if (isset($review_data['content'])) {
            $mapped['content'] = (string) $review_data['content'];
        }
        if (array_key_exists('date', $review_data)) {
            $mapped['date'] = $review_data['date'];
        }
        if (isset($review_data['status'])) {
            $mapped['status'] = (string) $review_data['status'];
        }
        if (array_key_exists('verified_buyer', $review_data)) {
            $mapped['verifiedBuyer'] = (bool) $review_data['verified_buyer'];
        }
        if (!empty($review_data['parent_id'])) {
            $mapped['parentId'] = (int) $review_data['parent_id'];
        }

        return $mapped;
    }

    /**
     * Map an array of reviews.
     *
     * @param array $reviews
     * @return array
     */
    public static function map_multiple($reviews) {
        $out = array();
        if (!is_array($reviews)) {
            return $out;
        }
        foreach ($reviews as $review) {
            $mapped = self::map_to_response($review);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }
        return $out;
    }
}
