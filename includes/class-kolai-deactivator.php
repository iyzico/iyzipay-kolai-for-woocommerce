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
     * Short Description. (use period)
     *
     * Long Description.
     */
    public static function deactivate() {
        // Add any deactivation logic here
        // For example: cleanup temporary data, etc.
    }
}
