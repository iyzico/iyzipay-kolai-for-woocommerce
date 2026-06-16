<?php
/**
 * Product Service - Business logic for products
 *
 * @package    Kolai
 * @subpackage Kolai/includes/product
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Service class
 */
class Kolai_Product_Service {

    /**
     * Default page size when caller does not provide one.
     */
    const DEFAULT_PER_PAGE = 100;

    /**
     * Hard cap on per_page to protect the server from OOM/timeouts.
     */
    const MAX_PER_PAGE = 200;

    /**
     * Cap on the number of variations returned per single product.
     * Anything larger is paginated/truncated to avoid OOM on enormous variable products.
     */
    const MAX_VARIATIONS_PER_PRODUCT = 100;

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Build a diagnostic snapshot describing why is_visible() may have
     * returned false for the given product. Used as the `data` payload of
     * the warning log entry — exposes status, catalog visibility, stock
     * status, parent id and the global `hide out of stock items` setting
     * so the operator can identify the failing condition without ssh-ing
     * into the box.
     *
     * @param WC_Product $product
     * @param array      $extra Optional extra context to merge in.
     * @return array
     */
    private static function visibility_diagnostics($product, $extra = array()) {
        $hide_oos = get_option('woocommerce_hide_out_of_stock_items') === 'yes';

        return array_merge(array(
            'product_id'         => $product->get_id(),
            'type'               => $product->get_type(),
            'status'             => $product->get_status(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'stock_status'       => $product->get_stock_status(),
            'in_stock'           => $product->is_in_stock(),
            'parent_id'          => $product->get_parent_id(),
            'hide_oos_setting'   => $hide_oos,
        ), $extra);
    }

    /**
     * List products. Always paginated to avoid OOM on shops with many SKUs.
     *
     * Accepted args:
     *   - page           int    1-based page index (default 1)
     *   - per_page       int    items per page (default DEFAULT_PER_PAGE, max MAX_PER_PAGE)
     *   - ids            int[]  optional explicit product IDs (bypasses pagination)
     *   - status         string post_status to query (default 'publish')
     *   - modified_after string ISO-8601 date; only products modified at/after this point
     *
     * Returns:
     *   array{
     *     items:      array<int,array>,     // raw product data ready for the mapper
     *     pagination: array{
     *       page: int,
     *       perPage: int,
     *       total: int,
     *       totalPages: int,
     *     }
     *   }
     *
     * @param array $args
     * @return array
     */
    public function get_all_products($args = array()) {
        if (!$this->is_woocommerce_active()) {
            Kolai_Logger::error('product', 'WooCommerce not active during list query');
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        $args = wp_parse_args($args, array(
            'page'           => 1,
            'per_page'       => self::DEFAULT_PER_PAGE,
            'ids'            => array(),
            'status'         => 'publish',
            'modified_after' => '',
        ));

        $page     = max(1, (int) $args['page']);
        $per_page = max(1, min(self::MAX_PER_PAGE, (int) $args['per_page']));
        $ids      = is_array($args['ids']) ? array_values(array_filter(array_map('intval', $args['ids']))) : array();

        Kolai_Logger::info('product', 'List query started', array(
            'page'     => $page,
            'per_page' => $per_page,
            'ids'      => $ids,
            'status'   => $args['status'],
        ));

        $query_args = array(
            'status'   => $args['status'],
            'paginate' => true,
            'limit'    => $per_page,
            'page'     => $page,
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'type'     => array_keys(wc_get_product_types()),
        );

        if (!empty($ids)) {
            // Explicit ID list — bypass pagination since the caller already
            // bounded the result set.
            $query_args['include'] = $ids;
            $query_args['limit']   = count($ids);
            $query_args['page']    = 1;
        }

        if (!empty($args['modified_after'])) {
            $query_args['date_modified'] = '>=' . $args['modified_after'];
        }

        $started = microtime(true);
        $result  = wc_get_products($query_args);
        $query_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_object($result) && isset($result->products)) {
            $products    = $result->products;
            $total       = (int) $result->total;
            $total_pages = (int) $result->max_num_pages;
        } else {
            $products    = (array) $result;
            $total       = count($products);
            $total_pages = 1;
        }

        Kolai_Logger::info('product', 'List query finished', array(
            'duration_ms' => $query_ms,
            'count'       => count($products),
            'total'       => $total,
            'total_pages' => $total_pages,
        ));

        if (empty($products)) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'page'       => $page,
                    'perPage'    => $per_page,
                    'total'      => $total,
                    'totalPages' => $total_pages,
                ),
            );
        }

        // Bulk-prime caches for the entire page so subsequent get_*() calls
        // don't generate one DB query per product.
        $product_ids = array();
        foreach ($products as $product) {
            $product_ids[] = $product->get_id();
        }
        $this->prime_caches_for_products($product_ids);

        // Format each product using the lightweight summary shape.
        $format_started = microtime(true);
        $items = array();
        foreach ($products as $product) {
            try {
                $items[] = $this->format_product_summary($product);
            } catch (Throwable $e) {
                Kolai_Logger::error('product', 'Failed to format product', array(
                    'product_id' => $product ? $product->get_id() : null,
                    'message'    => $e->getMessage(),
                ));
            }
        }
        $format_ms = (int) round((microtime(true) - $format_started) * 1000);

        Kolai_Logger::info('product', 'List formatting finished', array(
            'duration_ms' => $format_ms,
            'count'       => count($items),
        ));

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
     * Get product by ID from WooCommerce
     *
     * @param int $product_id Product ID
     * @return array Product data
     */
    public function get_product_by_id($product_id) {
        if (!$this->is_woocommerce_active()) {
            Kolai_Logger::error('product', 'WooCommerce not active during single fetch');
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        Kolai_Logger::info('product', 'Single product fetch started', array(
            'product_id' => $product_id,
        ));

        $started = microtime(true);
        $product = wc_get_product($product_id);

        if (!$product) {
            Kolai_Logger::warning('product', 'Product not found', array('product_id' => $product_id));
            throw new Kolai_Product_Not_Found_Exception();
        }

        if (!$product->is_visible()) {
            Kolai_Logger::warning('product', 'Product not visible', self::visibility_diagnostics($product));
            throw new Kolai_Product_Not_Visible_Exception();
        }

        $data = $this->format_product_data($product);

        Kolai_Logger::info('product', 'Single product fetch finished', array(
            'product_id'  => $product_id,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'has_variations' => !empty($data['variations']),
        ));

        return $data;
    }

    /**
     * Get product by ID; if it's a variation, return its parent.
     *
     * @param int $product_id Product ID or Variation ID
     * @return array Product data
     */
    public function get_product_with_variants_by_id($product_id) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }

        Kolai_Logger::info('product', 'Product with variants fetch started', array(
            'product_id' => $product_id,
        ));

        $started = microtime(true);
        $product = wc_get_product($product_id);

        if (!$product) {
            Kolai_Logger::warning('product', 'Product not found (variants)', array('product_id' => $product_id));
            throw new Kolai_Product_Not_Found_Exception();
        }

        if (!$product->is_visible()) {
            Kolai_Logger::warning('product', 'Product not visible (variants)', self::visibility_diagnostics($product));
            throw new Kolai_Product_Not_Visible_Exception();
        }

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();

            if (!$parent_id) {
                Kolai_Logger::warning('product', 'Variation has no parent', array('product_id' => $product_id));
                throw new Kolai_Product_Variation_Parent_Not_Found_Exception('Variation parent product not found');
            }

            $parent_product = wc_get_product($parent_id);

            if (!$parent_product) {
                Kolai_Logger::warning('product', 'Variation parent missing', array(
                    'variation_id' => $product_id,
                    'parent_id'    => $parent_id,
                ));
                throw new Kolai_Product_Variation_Parent_Not_Found_Exception('Variation parent product not found');
            }

            if (!$parent_product->is_visible()) {
                Kolai_Logger::warning('product', 'Variation parent not visible', self::visibility_diagnostics($parent_product, array(
                    'variation_id' => $product_id,
                )));
                throw new Kolai_Product_Not_Visible_Exception('Variation parent product not visible');
            }

            $data = $this->format_product_data($parent_product);
        } else {
            $data = $this->format_product_data($product);
        }

        Kolai_Logger::info('product', 'Product with variants fetch finished', array(
            'product_id'      => $product_id,
            'duration_ms'     => (int) round((microtime(true) - $started) * 1000),
            'variation_count' => isset($data['variations']) ? count($data['variations']) : 0,
        ));

        return $data;
    }

    /**
     * Lightweight formatter used for list responses. Only includes fields that
     * are cheap to read once caches have been primed; heavy data like
     * attributes/variations/gallery is excluded — fetch the single-product
     * endpoint for those.
     *
     * @param WC_Product $product
     * @return array
     */
    private function format_product_summary($product) {
        $product_id = $product->get_id();

        $summary = array(
            'id'                => $product_id,
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'type'              => $product->get_type(),
            'status'            => $product->get_status(),
            'sku'               => $product->get_sku(),
            'permalink'         => get_permalink($product_id),
            'date_created'      => $product->get_date_created() ? $product->get_date_created()->date('c') : null,
            'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->date('c') : null,

            // Prices
            'price'             => floatval($product->get_price()),
            'regular_price'     => floatval($product->get_regular_price()),
            'sale_price'        => $product->get_sale_price() ? floatval($product->get_sale_price()) : null,
            'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('c') : null,
            'date_on_sale_to'   => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('c') : null,

            // Stock & inventory
            'stock_status'      => $product->get_stock_status(),
            'manage_stock'      => $product->get_manage_stock(),
            'in_stock'          => $product->is_in_stock(),

            // Dimensions (cheap — already in postmeta)
            'weight'            => $product->get_weight() ? floatval($product->get_weight()) : null,
            'dimensions'        => array(
                'length' => $product->get_length() ? floatval($product->get_length()) : null,
                'width'  => $product->get_width()  ? floatval($product->get_width())  : null,
                'height' => $product->get_height() ? floatval($product->get_height()) : null,
            ),

            'parent_id'         => $product->get_parent_id(),

            // Main image only (gallery omitted from list)
            'image'             => $this->get_product_image($product),
        );

        // Override price/sale_price with tax-inclusive values and append the
        // included_tax / tax_price / tax_percentage breakdown.
        return array_merge($summary, $this->calculate_tax_fields(
            $product,
            $summary['price'],
            $summary['sale_price']
        ));
    }

    /**
     * Full formatter used for single-product responses. This fetches every
     * piece of data the mapper might need.
     *
     * @param WC_Product $product
     * @return array
     */
    private function format_product_data($product) {
        $product_id = $product->get_id();

        // Prime caches for THIS product (and any related image IDs) before we
        // start hitting them with attribute/term/meta lookups.
        $this->prime_caches_for_products(array($product_id));

        $data = array(
            // General Info
            'id' => $product_id,
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'featured' => $product->get_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku' => $product->get_sku(),
            'menu_order' => $product->get_menu_order(),
            'virtual' => $product->get_virtual(),
            'permalink' => get_permalink($product_id),
            'date_created' => $product->get_date_created() ? $product->get_date_created()->date('c') : null,
            'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date('c') : null,

            // Prices
            'price' => floatval($product->get_price()),
            'regular_price' => floatval($product->get_regular_price()),
            'sale_price' => $product->get_sale_price() ? floatval($product->get_sale_price()) : null,
            'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('c') : null,
            'date_on_sale_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('c') : null,
            'total_sales' => $product->get_total_sales(),

            // Tax, Shipping & Stock
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'manage_stock' => $product->get_manage_stock(),
            'stock_status' => $product->get_stock_status(),
            'sold_individually' => $product->get_sold_individually(),
            'purchase_note' => $product->get_purchase_note(),
            'shipping_class_id' => $product->get_shipping_class_id(),
            'in_stock' => $product->is_in_stock(),

            // Dimensions
            'weight' => $product->get_weight() ? floatval($product->get_weight()) : null,
            'dimensions' => array(
                'length' => $product->get_length() ? floatval($product->get_length()) : null,
                'width' => $product->get_width() ? floatval($product->get_width()) : null,
                'height' => $product->get_height() ? floatval($product->get_height()) : null,
            ),

            // Linked Products
            'upsell_ids' => $product->get_upsell_ids(),
            'cross_sell_ids' => $product->get_cross_sell_ids(),
            'parent_id' => $product->get_parent_id(),

            // Attributes & Variations
            'attributes' => $this->get_product_attributes($product),
            'default_attributes' => $product->get_default_attributes(),
            'variations' => array(),

            // Taxonomies
            'categories' => $this->get_product_categories($product),
            'tags' => $this->get_product_tags($product),

            // Downloads
            'downloadable' => $product->get_downloadable(),
            'downloads' => $this->format_product_downloads($product),
            'download_limit' => $product->get_download_limit(),
            'download_expiry' => $product->get_download_expiry(),

            // Images
            'image' => $this->get_product_image($product),
            'gallery' => $this->get_product_gallery($product),

            // Reviews
            'reviews_allowed' => $product->get_reviews_allowed(),
            'rating_counts' => $product->get_rating_counts(),
            'average_rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
        );

        // Override price/sale_price with tax-inclusive values and append the
        // included_tax / tax_price / tax_percentage breakdown.
        $data = array_merge($data, $this->calculate_tax_fields(
            $product,
            $data['price'],
            $data['sale_price']
        ));

        // Get variations if product is variable
        if ($product->is_type('variable')) {
            $data['variations'] = $this->get_product_variations($product);
        }

        return $data;
    }

    /**
     * Compute tax-inclusive prices and the tax breakdown for a product or
     * variation.
     *
     * Uses WooCommerce's native wc_get_price_including_tax/excluding_tax so the
     * store's "prices include tax" setting and the configured tax rates are
     * honoured. In a REST context WC()->customer may be null; WooCommerce then
     * falls back to the shop base location, so this is safe outside the cart.
     *
     * Returns the tax-inclusive `price` and `sale_price` (overriding the raw
     * net values) plus:
     *   - included_tax    bool   whether the product is taxable
     *   - tax_price       float  tax amount on the EFFECTIVE price
     *                            (sale price when on sale, otherwise price)
     *   - tax_percentage  float  effective tax rate (%) derived from that amount
     *
     * @param WC_Product $product
     * @param float      $price      Raw regular/active price (get_price()).
     * @param float|null $sale_price Raw sale price, or null when not on sale.
     * @return array{included_tax:bool, price:float, sale_price:float|null, tax_price:float, tax_percentage:float}
     */
    private function calculate_tax_fields($product, $price, $sale_price) {
        if (!$product->is_taxable()) {
            return array(
                'included_tax'   => false,
                'price'          => $price,
                'sale_price'     => $sale_price,
                'tax_price'      => 0.0,
                'tax_percentage' => 0.0,
            );
        }

        // Tax-inclusive / -exclusive values for the regular price.
        $price_incl = (float) wc_get_price_including_tax($product, array('price' => $price));
        $price_excl = (float) wc_get_price_excluding_tax($product, array('price' => $price));

        $sale_incl = null;
        if ($sale_price !== null) {
            $sale_incl = (float) wc_get_price_including_tax($product, array('price' => $sale_price));
        }

        // Effective price = sale price when on sale, otherwise the regular price.
        if ($sale_price !== null) {
            $effective_excl = (float) wc_get_price_excluding_tax($product, array('price' => $sale_price));
            $effective_incl = $sale_incl;
        } else {
            $effective_excl = $price_excl;
            $effective_incl = $price_incl;
        }

        $tax_price      = round($effective_incl - $effective_excl, 2);
        $tax_percentage = $effective_excl > 0
            ? round(($tax_price / $effective_excl) * 100, 2)
            : 0.0;

        return array(
            'included_tax'   => true,
            'price'          => $price_incl,
            'sale_price'     => $sale_incl,
            'tax_price'      => $tax_price,
            'tax_percentage' => $tax_percentage,
        );
    }

    /**
     * Prime WP/WC caches for a batch of product IDs so subsequent reads hit
     * memory instead of generating one query per product.
     *
     * @param int[] $product_ids
     */
    private function prime_caches_for_products(array $product_ids) {
        if (empty($product_ids)) {
            return;
        }

        $product_ids = array_values(array_unique(array_map('intval', $product_ids)));

        // Post objects + post meta + term relationships in three queries total.
        if (function_exists('_prime_post_caches')) {
            _prime_post_caches($product_ids, true, true);
        }
        if (function_exists('update_meta_cache')) {
            update_meta_cache('post', $product_ids);
        }
        if (function_exists('update_object_term_cache')) {
            update_object_term_cache($product_ids, 'product');
        }

        // Pre-load thumbnail IDs so wp_get_attachment_image_url() doesn't
        // re-query for each product.
        $thumbnail_ids = array();
        foreach ($product_ids as $pid) {
            $thumb = get_post_thumbnail_id($pid);
            if ($thumb) {
                $thumbnail_ids[] = (int) $thumb;
            }
        }
        if (!empty($thumbnail_ids) && function_exists('_prime_post_caches')) {
            _prime_post_caches(array_unique($thumbnail_ids), false, true);
        }
    }

    /**
     * Get product variations for variable products.
     *
     * @param WC_Product_Variable $product
     * @return array
     */
    private function get_product_variations($product) {
        $variations = array();
        $variation_ids = $product->get_children();

        if (empty($variation_ids)) {
            return $variations;
        }

        // Truncate at safety limit to avoid OOM on extreme variable products.
        $truncated = false;
        if (count($variation_ids) > self::MAX_VARIATIONS_PER_PRODUCT) {
            Kolai_Logger::warning('product', 'Variation list truncated', array(
                'parent_id'   => $product->get_id(),
                'total'       => count($variation_ids),
                'returned'    => self::MAX_VARIATIONS_PER_PRODUCT,
            ));
            $variation_ids = array_slice($variation_ids, 0, self::MAX_VARIATIONS_PER_PRODUCT);
            $truncated = true;
        }

        // Prime caches for variations (post + meta + thumbs).
        $this->prime_caches_for_products($variation_ids);

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation || !$variation->is_visible()) {
                continue;
            }

            $variation_data = array(
                'id' => $variation->get_id(),
                'sku' => $variation->get_sku(),
                'description' => $variation->get_description(),
                'price' => floatval($variation->get_price()),
                'sale_price' => $variation->get_sale_price() ? floatval($variation->get_sale_price()) : null,
                'stock_status' => $variation->get_stock_status(),
                'manage_stock' => $variation->get_manage_stock(),
                'in_stock' => $variation->is_in_stock(),
                'attributes' => $this->get_variation_attributes($variation),
                'image' => $this->get_product_image($variation),
            );

            // Override price/sale_price with tax-inclusive values and append the
            // included_tax / tax_price / tax_percentage breakdown.
            $variations[] = array_merge($variation_data, $this->calculate_tax_fields(
                $variation,
                $variation_data['price'],
                $variation_data['sale_price']
            ));
        }

        if ($truncated) {
            // Surface truncation in the payload so the consumer can detect it.
            $variations[] = array(
                '_truncated' => true,
                '_max'       => self::MAX_VARIATIONS_PER_PRODUCT,
            );
        }

        return $variations;
    }

    /**
     * Get variation attributes in formatted array
     *
     * @param WC_Product_Variation $variation
     * @return array
     */
    private function get_variation_attributes($variation) {
        $attributes = array();
        $variation_attributes = $variation->get_attributes();

        foreach ($variation_attributes as $attribute_name => $attribute_value) {
            $attribute_slug = str_replace('attribute_', '', $attribute_name);
            $attribute_label = wc_attribute_label($attribute_slug);
            $attribute_id = null;

            if (taxonomy_exists($attribute_slug)) {
                $term = get_term_by('slug', $attribute_value, $attribute_slug);
                if (!$term) {
                    $term = get_term_by('name', $attribute_value, $attribute_slug);
                }
                if ($term && !is_wp_error($term)) {
                    $attribute_id = (int) $term->term_id;
                }
            }

            $attributes[] = array(
                'id' => $attribute_id,
                'name' => $attribute_label,
                'slug' => $attribute_slug,
                'value' => $attribute_value,
            );
        }

        return $attributes;
    }

    /**
     * Get product main image
     *
     * @param WC_Product $product
     * @return array|null
     */
    private function get_product_image($product) {
        $image_id = $product->get_image_id();

        if (!$image_id) {
            return null;
        }

        return array(
            'id'  => $image_id,
            'url' => wp_get_attachment_image_url($image_id, 'full'),
            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
        );
    }

    /**
     * Get product gallery images
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_product_gallery($product) {
        $gallery_ids = $product->get_gallery_image_ids();
        if (empty($gallery_ids)) {
            return array();
        }

        // Prime attachment caches in one go.
        if (function_exists('_prime_post_caches')) {
            _prime_post_caches($gallery_ids, false, true);
        }

        $gallery = array();
        foreach ($gallery_ids as $image_id) {
            $gallery[] = array(
                'id'  => $image_id,
                'url' => wp_get_attachment_image_url($image_id, 'full'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
            );
        }
        return $gallery;
    }

    /**
     * Get product categories
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_product_categories($product) {
        $category_ids = $product->get_category_ids();
        if (empty($category_ids)) {
            return array();
        }

        // Single batch query instead of one get_term() per id.
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'include'    => $category_ids,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $categories = array();
        foreach ($terms as $term) {
            $categories[] = array(
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
        return $categories;
    }

    /**
     * Get product tags
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_product_tags($product) {
        $tag_ids = $product->get_tag_ids();
        if (empty($tag_ids)) {
            return array();
        }

        $terms = get_terms(array(
            'taxonomy'   => 'product_tag',
            'include'    => $tag_ids,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $tags = array();
        foreach ($terms as $term) {
            $tags[] = array(
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
        return $tags;
    }

    /**
     * Format product downloads
     *
     * @param WC_Product $product
     * @return array
     */
    private function format_product_downloads($product) {
        $downloads = array();
        foreach ($product->get_downloads() as $download_id => $download) {
            $downloads[] = array(
                'id'   => $download_id,
                'name' => $download->get_name(),
                'file' => $download->get_file(),
            );
        }
        return $downloads;
    }

    /**
     * Get product attributes
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();

        foreach ($product_attributes as $attribute_name => $attribute) {
            $is_taxonomy = false;
            $is_visible  = false;

            if (is_a($attribute, 'WC_Product_Attribute')) {
                $is_taxonomy = $attribute->is_taxonomy();
                $is_visible  = $attribute->get_visible();
            } else {
                $is_taxonomy = isset($attribute['is_taxonomy']) ? $attribute['is_taxonomy'] : false;
                $is_visible  = isset($attribute['is_visible']) ? $attribute['is_visible'] : false;
            }

            $attribute_data = array(
                'name'    => wc_attribute_label($attribute_name),
                'slug'    => $attribute_name,
                'type'    => $is_taxonomy ? 'taxonomy' : 'custom',
                'visible' => $is_visible,
                'options' => array(),
            );

            if ($is_taxonomy) {
                $terms = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'all'));
                foreach ($terms as $term) {
                    if ($term && !is_wp_error($term)) {
                        $attribute_data['options'][] = array(
                            'id'   => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        );
                    }
                }
            } else {
                if (is_a($attribute, 'WC_Product_Attribute')) {
                    $options = $attribute->get_options();
                } else {
                    $options = isset($attribute['value']) ? explode('|', $attribute['value']) : array();
                }

                foreach ($options as $option) {
                    $option = trim($option);
                    if ($option !== '') {
                        $attribute_data['options'][] = array(
                            'name' => $option,
                            'slug' => sanitize_title($option),
                        );
                    }
                }
            }

            $attributes[] = $attribute_data;
        }

        return $attributes;
    }
}
