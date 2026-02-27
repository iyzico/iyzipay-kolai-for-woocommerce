<?php
/**
 * Fired during plugin activation
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation
 */
class Kolai_Activator {
    
    /**
     * Plugin activation logic
     *
     * Checks if WooCommerce is installed and active before activation
     */
    public static function activate() {
        // Check if WooCommerce is installed and active
        if (!self::is_woocommerce_active()) {
            // Deactivate the plugin
            deactivate_plugins(KOLAI_PLUGIN_BASENAME);
            
            // Show error message
            wp_die(
                __('Kolai plugin\'i WooCommerce gerektirir. Lütfen önce WooCommerce\'i yükleyip aktifleştirin.', 'kolai'),
                __('Plugin Aktivasyon Hatası', 'kolai'),
                array('back_link' => true)
            );
        }
        
        // Add any additional activation logic here
        // For example: create database tables, set default options, etc.
    }
    
    /**
     * Check if WooCommerce is installed and active
     *
     * @return bool True if WooCommerce is active, false otherwise
     */
    private static function is_woocommerce_active() {
        // Method 1: Check if WooCommerce class exists
        if (class_exists('WooCommerce')) {
            return true;
        }
        
        // Method 2: Check if WooCommerce function exists
        if (function_exists('WC')) {
            return true;
        }
        
        // Method 3: Check if plugin is active
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            return true;
        }
        
        return false;
    }
}
