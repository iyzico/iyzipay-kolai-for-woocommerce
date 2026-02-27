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
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Get all products from WooCommerce
     *
     * @return array Array of product data
     */
    public function get_all_products() {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }
        
        $args = array(
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids',
        );
        
        $product_ids = wc_get_products($args);
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = $this->format_product_data($product);
            }
        }
        
        return $products;
    }
    
    /**
     * Get product by ID from WooCommerce
     *
     * @param int $product_id Product ID
     * @return array|null Product data or null if not found
     */
    public function get_product_by_id($product_id) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Kolai_Product_Not_Found_Exception();
        }

        if (!$product->is_visible()) {
            throw new Kolai_Product_Not_Visible_Exception();
        }
        
        return $this->format_product_data($product);
    }
    
    /**
     * Get product by ID from WooCommerce
     * If the product is a variation (child), returns the parent product instead
     *
     * @param int $product_id Product ID or Variation ID
     * @return array|null Product data or null if not found
     */
    public function get_product_with_variants_by_id($product_id) {
        if (!$this->is_woocommerce_active()) {
            throw new Kolai_WooCommerce_Inactive_Exception();
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Kolai_Product_Not_Found_Exception();
        }

        if (!$product->is_visible()) {
            throw new Kolai_Product_Not_Visible_Exception();
        }
        
        // Check if this is a variation (child product)
        // If it is, get the parent product instead
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            
            if (!$parent_id) {
                throw new Kolai_Product_Variation_Parent_Not_Found_Exception('Variation parent product not found');
            }
            
            $parent_product = wc_get_product($parent_id);
            
            if (!$parent_product) {
                throw new Kolai_Product_Variation_Parent_Not_Found_Exception('Variation parent product not found');
            }
            
            if (!$parent_product->is_visible()) {
                throw new Kolai_Product_Not_Visible_Exception('Variation parent product not visible');
            }
            
            return $this->format_product_data($parent_product);
        }
        
        return $this->format_product_data($product);
    }
    
    /**
     * Format WooCommerce product data to array
     *
     * @param WC_Product $product WooCommerce product object
     * @return array Formatted product data
     */
    private function format_product_data($product) {
        $product_id = $product->get_id();
        
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
        
        // Get variations if product is variable
        if ($product->is_type('variable')) {
            $data['variations'] = $this->get_product_variations($product);
        }
        
        return $data;
    }
    
    /**
     * Get product variations for variable products
     *
     * @param WC_Product_Variable $product Variable product object
     * @return array Array of variation data
     */
    private function get_product_variations($product) {
        $variations = array();
        $variation_ids = $product->get_children();
        
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
            
            $variations[] = $variation_data;
        }
        
        return $variations;
    }
    
    /**
     * Get variation attributes in formatted array
     *
     * @param WC_Product_Variation $variation Variation product object
     * @return array Array of variation attributes
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
     * @param WC_Product $product Product object
     * @return array|null Image data or null
     */
    private function get_product_image($product) {
        $image_id = $product->get_image_id();
        
        if (!$image_id) {
            return null;
        }
        
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        
        return array(
            'id' => $image_id,
            'url' => $image_url,
            'alt' => $image_alt,
        );
    }
    
    /**
     * Get product gallery images
     *
     * @param WC_Product $product Product object
     * @return array Array of image data
     */
    private function get_product_gallery($product) {
        $gallery_ids = $product->get_gallery_image_ids();
        $gallery = array();
        
        foreach ($gallery_ids as $image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            
            $gallery[] = array(
                'id' => $image_id,
                'url' => $image_url,
                'alt' => $image_alt,
            );
        }
        
        return $gallery;
    }
    
    /**
     * Get product categories
     *
     * @param WC_Product $product Product object
     * @return array Array of category data
     */
    private function get_product_categories($product) {
        $categories = array();
        $category_ids = $product->get_category_ids();
        
        foreach ($category_ids as $category_id) {
            $term = get_term($category_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
        
        return $categories;
    }
    
    /**
     * Get product tags
     *
     * @param WC_Product $product Product object
     * @return array Array of tag data
     */
    private function get_product_tags($product) {
        $tags = array();
        $tag_ids = $product->get_tag_ids();
        
        foreach ($tag_ids as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $tags[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
        
        return $tags;
    }
    
    /**
     * Format product downloads
     *
     * @param WC_Product $product Product object
     * @return array Array of download data
     */
    private function format_product_downloads($product) {
        $downloads = array();
        $product_downloads = $product->get_downloads();
        
        foreach ($product_downloads as $download_id => $download) {
            $downloads[] = array(
                'id' => $download_id,
                'name' => $download->get_name(),
                'file' => $download->get_file(),
            );
        }
        
        return $downloads;
    }
    
    /**
     * Get product attributes
     *
     * @param WC_Product $product Product object
     * @return array Array of attribute data
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute_name => $attribute) {
            // Check if attribute is a WC_Product_Attribute object or array
            $is_taxonomy = false;
            $is_visible = false;
            $options = array();
            
            if (is_a($attribute, 'WC_Product_Attribute')) {
                $is_taxonomy = $attribute->is_taxonomy();
                $is_visible = $attribute->get_visible();
            } else {
                // Handle array format (legacy)
                $is_taxonomy = isset($attribute['is_taxonomy']) ? $attribute['is_taxonomy'] : false;
                $is_visible = isset($attribute['is_visible']) ? $attribute['is_visible'] : false;
            }
            
            $attribute_data = array(
                'name' => wc_attribute_label($attribute_name),
                'slug' => $attribute_name,
                'type' => $is_taxonomy ? 'taxonomy' : 'custom',
                'visible' => $is_visible,
                'options' => array(),
            );
            
            if ($is_taxonomy) {
                $terms = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'all'));
                foreach ($terms as $term) {
                    if ($term && !is_wp_error($term)) {
                        $attribute_data['options'][] = array(
                            'id' => $term->term_id,
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
                    if (!empty($option)) {
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
