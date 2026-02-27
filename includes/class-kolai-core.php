<?php
/**
 * The core plugin class
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class
 */
class Kolai_Core {
    
    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var Kolai_Loader
     */
    protected $loader;
    
    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected $plugin_name;
    
    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected $version;
    
    /**
     * Define the core functionality of the plugin
     */
    public function __construct() {
        $this->version = KOLAI_VERSION;
        $this->plugin_name = 'kolai';
        
        // Check WooCommerce dependency before loading
        if (!$this->check_woocommerce_dependency()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
    }
    
    /**
     * Check if WooCommerce is installed and active
     *
     * @return bool True if WooCommerce is active, false otherwise
     */
    private function check_woocommerce_dependency() {
        // Check if WooCommerce class exists
        if (class_exists('WooCommerce')) {
            return true;
        }
        
        // Check if WooCommerce function exists
        if (function_exists('WC')) {
            return true;
        }
        
        // Check if plugin is active
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Display admin notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Kolai', 'kolai'); ?></strong>: 
                <?php esc_html_e('Bu plugin WooCommerce gerektirir. Lütfen WooCommerce\'i yükleyip aktifleştirin.', 'kolai'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Load the required dependencies for this plugin
     */
    private function load_dependencies() {
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-loader.php';
        require_once KOLAI_INCLUDES_DIR . 'class-kolai-api.php';
        require_once KOLAI_ADMIN_DIR . 'class-kolai-admin.php';
        require_once KOLAI_ADMIN_DIR . 'class-kolai-settings.php';
        
        $this->loader = new Kolai_Loader();
        
        // Initialize REST API
        new Kolai_API();
    }
    
    /**
     * Define the locale for this plugin for internationalization
     */
    private function set_locale() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }
    
    /**
     * Load the plugin text domain for translation
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'kolai',
            false,
            dirname(KOLAI_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks() {
        $plugin_admin = new Kolai_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_settings = new Kolai_Settings($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }
    
    /**
     * Run the loader to execute all of the hooks with WordPress
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * The name of the plugin used to uniquely identify it
     *
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    /**
     * The reference to the class that orchestrates the hooks
     *
     * @return Kolai_Loader
     */
    public function get_loader() {
        return $this->loader;
    }
    
    /**
     * Retrieve the version number of the plugin
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
