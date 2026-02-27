<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package    Kolai
 * @subpackage Kolai/admin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin
 */
class Kolai_Admin {
    
    /**
     * The ID of this plugin
     *
     * @var string
     */
    private $plugin_name;
    
    /**
     * The version of this plugin
     *
     * @var string
     */
    private $version;
    
    /**
     * Initialize the class and set its properties
     *
     * @param string $plugin_name The name of this plugin
     * @param string $version     The version of this plugin
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Register the stylesheets for the admin area
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            KOLAI_PLUGIN_URL . 'admin/css/kolai-admin.css',
            array(),
            $this->version,
            'all'
        );
    }
    
    /**
     * Register the JavaScript for the admin area
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            KOLAI_PLUGIN_URL . 'admin/js/kolai-admin.js',
            array('jquery'),
            $this->version,
            false
        );
    }
    
    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Kolai Ayarlar', 'kolai'),
            __('Kolai', 'kolai'),
            'manage_options',
            'kolai-settings',
            array($this, 'display_settings_page'),
            'dashicons-admin-generic',
            30
        );
    }
    
    /**
     * Render the settings page for this plugin
     */
    public function display_settings_page() {
        include_once KOLAI_ADMIN_DIR . 'views/settings-page.php';
    }
}
