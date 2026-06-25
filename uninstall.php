<?php
/**
 * Uninstall handler for Kolai.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Removes the
 * custom logs table, all kolai_* options and the scheduled cleanup cron so no
 * orphaned data remains. Order meta written onto WooCommerce orders is left
 * untouched (it belongs to the orders, not the plugin).
 *
 * @package    Kolai
 */

// Exit if not called by WordPress during uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1) Drop the custom logs table.
$table = $wpdb->prefix . 'kolai_logs';
$wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB

// 2) Delete every kolai_* option (covers API keys, contracts, logging,
//    meta field map, db version, etc.).
$like = $wpdb->esc_like('kolai_') . '%';
$option_names = $wpdb->get_col(
    $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like)
);
if (!empty($option_names)) {
    foreach ($option_names as $option_name) {
        delete_option($option_name);
    }
}

// 3) Clear the scheduled log cleanup cron.
wp_clear_scheduled_hook('kolai_logs_cleanup');
