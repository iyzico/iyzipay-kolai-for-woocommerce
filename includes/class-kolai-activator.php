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

        // Create logs table
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-logger.php';
        Kolai_Logger::create_table();

        // Default logging options (only set if not already configured)
        if (get_option(Kolai_Logger::OPTION_ENABLED, null) === null) {
            add_option(Kolai_Logger::OPTION_ENABLED, 0);
        }
        if (get_option(Kolai_Logger::OPTION_LEVEL, null) === null) {
            add_option(Kolai_Logger::OPTION_LEVEL, Kolai_Logger::LEVEL_INFO);
        }
        if (get_option(Kolai_Logger::OPTION_RETENTION_DAYS, null) === null) {
            add_option(Kolai_Logger::OPTION_RETENTION_DAYS, 7);
        }

        // Schedule daily log retention cleanup
        if (!wp_next_scheduled(Kolai_Logger::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', Kolai_Logger::CRON_HOOK);
        }
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
