<?php
/**
 * Plugin Name: Kolai
 * Plugin URI: https://example.com/kolai
 * Description: Kolai API entegrasyonu için ayarlar modülü
 * Version: 1.0.3
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: kolai
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KOLAI_VERSION', '1.0.3');
define('KOLAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KOLAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KOLAI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KOLAI_INCLUDES_DIR', KOLAI_PLUGIN_DIR . 'includes/');
define('KOLAI_ADMIN_DIR', KOLAI_PLUGIN_DIR . 'admin/');

/**
 * The code that runs during plugin activation
 */
function activate_kolai() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-activator.php';
    Kolai_Activator::activate();
}

/**
 * The code that runs during plugin deactivation
 */
function deactivate_kolai() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-deactivator.php';
    Kolai_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_kolai');
register_deactivation_hook(__FILE__, 'deactivate_kolai');

/**
 * Begins execution of the plugin
 */
function run_kolai() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-loader.php';
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-core.php';
    
    $plugin = new Kolai_Core();
    $plugin->run();
}

run_kolai();
