<?php
/**
 * Product Mapper - Maps product data to API response format
 *
 * @package    Kolai
 * @subpackage Kolai/includes/product
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Mapper class
 */
class Kolai_Product_Mapper {
    
    /**
     * Map raw product data to API response format matching Java DTO structure
     *
     * @param array $product_data Raw product data
     * @return array Mapped product data
     */
    public static function map_to_response($product_data) {
        if (empty($product_data)) {
            return null;
        }
        
        $mapped = array();
        
        // Basic Product Data
        if (isset($product_data['id'])) $mapped['id'] = (string) $product_data['id'];
        if (isset($product_data['name'])) $mapped['title'] = $product_data['name'];
        if (isset($product_data['description'])) $mapped['description'] = $product_data['description'];
        if (isset($product_data['permalink'])) $mapped['link'] = $product_data['permalink'];
        
        // Image Links
        if (isset($product_data['image']) && isset($product_data['image']['url'])) {
            $mapped['imageLink'] = $product_data['image']['url'];
        }
        
        // Additional Image Links (from gallery)
        if (isset($product_data['gallery']) && is_array($product_data['gallery']) && !empty($product_data['gallery'])) {
            $mapped['additionalImageLinks'] = array();
            foreach ($product_data['gallery'] as $gallery_item) {
                if (isset($gallery_item['url'])) {
                    $mapped['additionalImageLinks'][] = $gallery_item['url'];
                }
            }
        }
        
        // Stock (boolean only)
        if (isset($product_data['in_stock'])) {
            $mapped['inStock'] = (bool) $product_data['in_stock'];
        }
        
        // Price (as string with currency format)
        if (isset($product_data['price'])) {
            $mapped['price'] = (string) number_format(floatval($product_data['price']), 2, '.', '');
        }
        
        // Sale Price (as string)
        if (isset($product_data['sale_price']) && $product_data['sale_price']) {
            $mapped['salePrice'] = (string) number_format(floatval($product_data['sale_price']), 2, '.', '');
        }
        
        // Sale Price Effective Date (combine from and to dates)
        if (isset($product_data['date_on_sale_from']) && isset($product_data['date_on_sale_to']) 
            && $product_data['date_on_sale_from'] && $product_data['date_on_sale_to']) {
            $mapped['salePriceEffectiveDate'] = $product_data['date_on_sale_from'] . '/' . $product_data['date_on_sale_to'];
        }
        
        // Product Category
        if (isset($product_data['type'])) $mapped['productType'] = $product_data['type'];
        
        // Product Identifiers
        if (isset($product_data['sku']) && $product_data['sku']) {
            $mapped['gtin'] = $product_data['sku'];
            $mapped['mpn'] = $product_data['sku'];
        }
        
        if (isset($product_data['parent_id']) && $product_data['parent_id']) {
            $mapped['itemGroupId'] = (string) $product_data['parent_id'];
        }
        
        // Detailed Product Description
        if (isset($product_data['type']) && $product_data['type'] === 'bundle') {
            $mapped['isBundle'] = 'yes';
        }
        
        // Dimensions
        if (isset($product_data['dimensions']['length']) && $product_data['dimensions']['length']) {
            $mapped['productLength'] = (string) $product_data['dimensions']['length'];
        }
        
        if (isset($product_data['dimensions']['width']) && $product_data['dimensions']['width']) {
            $mapped['productWidth'] = (string) $product_data['dimensions']['width'];
        }
        
        if (isset($product_data['dimensions']['height']) && $product_data['dimensions']['height']) {
            $mapped['productHeight'] = (string) $product_data['dimensions']['height'];
        }
        
        if (isset($product_data['weight']) && $product_data['weight']) {
            $mapped['productWeight'] = (string) $product_data['weight'];
        }
        
        // Additional fields for compatibility - WooCommerce default structure
        if (isset($product_data['variations']) && is_array($product_data['variations'])) {
            $mapped['variations'] = self::map_variations($product_data['variations']);
        }
        if (isset($product_data['attributes'])) $mapped['attributes'] = $product_data['attributes'];
        
        return $mapped;
    }
    
    /**
     * Map variations array to camelCase format
     *
     * @param array $variations Array of variation data
     * @return array Array of mapped variations
     */
    private static function map_variations($variations) {
        $mapped_variations = array();
        
        foreach ($variations as $variation) {
            $mapped = array();
            
            // Basic fields
            if (isset($variation['id'])) $mapped['id'] = intval($variation['id']);
            if (isset($variation['sku'])) $mapped['sku'] = $variation['sku'];
            if (isset($variation['description'])) $mapped['description'] = $variation['description'];
            
            // Prices (as string)
            if (isset($variation['price'])) {
                $mapped['price'] = (string) number_format(floatval($variation['price']), 2, '.', '');
            }
            if (isset($variation['sale_price']) && $variation['sale_price']) {
                $mapped['salePrice'] = (string) number_format(floatval($variation['sale_price']), 2, '.', '');
            }
            
            // Stock information
            if (isset($variation['in_stock'])) $mapped['inStock'] = (bool) $variation['in_stock'];
            
            // Attributes and Image
            if (isset($variation['attributes'])) $mapped['attributes'] = $variation['attributes'];
            if (isset($variation['image'])) $mapped['image'] = $variation['image'];
            
            $mapped_variations[] = $mapped;
        }
        
        return $mapped_variations;
    }
    
    /**
     * Map multiple products to API response format
     *
     * @param array $products_array Array of product data
     * @return array Array of mapped products
     */
    public static function map_multiple($products_array) {
        $mapped = array();
        foreach ($products_array as $product) {
            $mapped[] = self::map_to_response($product);
        }
        return $mapped;
    }
}
