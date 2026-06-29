<?php
/**
 * Fired during plugin deactivation
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin deactivation
 */
class Kolai_Deactivator {
    
    /**
     * Unschedule the daily log-cleanup cron event on deactivation.
     *
     * The log table and its data are intentionally left in place so logs survive
     * a deactivate/reactivate cycle; only the scheduled cron is removed here.
     */
    public static function deactivate() {
        // Unschedule the daily log cleanup cron. The log table itself is kept
        // intact so existing log data survives plugin deactivation/reactivation.
        if (defined('KOLAI_INCLUDES_DIR')) {
            require_once KOLAI_INCLUDES_DIR . 'class-kolai-logger.php';
        }
        $hook = class_exists('Kolai_Logger') ? Kolai_Logger::CRON_HOOK : 'kolai_logs_cleanup';
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
}
