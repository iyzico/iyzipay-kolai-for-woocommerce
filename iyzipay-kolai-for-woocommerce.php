<?php
/**
 * Plugin Name: iyzico Kolai for WooCommerce
 * Plugin URI: https://github.com/iyzico/iyzipay-kolai-for-woocommerce
 * Description: Kolai API entegrasyonu için ayarlar modülü
 * Version: 1.8.2
 * Author: iyzico
 * Author URI: https://www.iyzico.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kolai
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 10.9.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KOLAI_VERSION', '1.8.2');
define('KOLAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KOLAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KOLAI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KOLAI_INCLUDES_DIR', KOLAI_PLUGIN_DIR . 'includes/');
define('KOLAI_ADMIN_DIR', KOLAI_PLUGIN_DIR . 'admin/');
define('KOLAI_VENDOR_DIR', KOLAI_INCLUDES_DIR . 'vendor/');

/**
 * The code that runs during plugin activation
 */
function kolai_activate() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-activator.php';
    Kolai_Activator::activate();
}

/**
 * The code that runs during plugin deactivation
 */
function kolai_deactivate() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-deactivator.php';
    Kolai_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'kolai_activate');
register_deactivation_hook(__FILE__, 'kolai_deactivate');

/**
 * Declare compatibility with WooCommerce features.
 *
 * - High-Performance Order Storage (HPOS / custom order tables): the plugin
 *   uses CRUD APIs (WC_Order getters/setters, wc_get_order) and never queries
 *   the posts table directly, so it is HPOS-safe.
 * - Cart/Checkout Blocks: the bundled gateway is hidden from checkout
 *   (is_available() returns false), so it imposes no requirement on the
 *   classic-vs-blocks checkout and is compatible with both.
 * - Product Block Editor: the plugin adds no product-edit UI / meta boxes, so
 *   it is neutral with respect to the new block-based product editor.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, true);
    }
});

/**
 * Begins execution of the plugin
 */
function kolai_run() {
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-loader.php';
    require_once KOLAI_INCLUDES_DIR . 'class-kolai-core.php';

    $plugin = new Kolai_Core();
    $plugin->run();
}

kolai_run();
