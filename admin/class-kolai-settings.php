<?php
/**
 * Register all settings for the plugin
 *
 * @package    Kolai
 * @subpackage Kolai/admin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all settings for the plugin
 */
class Kolai_Settings {
    
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
     * Register all settings
     */
    public function register_settings() {
        // Register API Key setting
        register_setting(
            'kolai_settings_group',
            'kolai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        // Register Secret Key setting
        register_setting(
            'kolai_settings_group',
            'kolai_secret_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        // Add settings section
        add_settings_section(
            'kolai_api_section',
            __('API Ayarları', 'kolai'),
            array($this, 'render_section_callback'),
            'kolai-settings'
        );
        
        // Add API Key field
        add_settings_field(
            'kolai_api_key',
            __('API Key', 'kolai'),
            array($this, 'render_api_key_field'),
            'kolai-settings',
            'kolai_api_section',
            array('label_for' => 'kolai_api_key')
        );
        
        // Add Secret Key field
        add_settings_field(
            'kolai_secret_key',
            __('Secret Key', 'kolai'),
            array($this, 'render_secret_key_field'),
            'kolai-settings',
            'kolai_api_section',
            array('label_for' => 'kolai_secret_key')
        );
    }
    
    /**
     * Render the section description
     */
    public function render_section_callback() {
        echo '<p>' . __('Kolai API entegrasyonu için gerekli bilgileri girin.', 'kolai') . '</p>';
    }
    
    /**
     * Render API Key field
     */
    public function render_api_key_field() {
        $api_key = get_option('kolai_api_key', '');
        ?>
        <input type="text" 
               name="kolai_api_key" 
               id="kolai_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e('API Key girin', 'kolai'); ?>" />
        <p class="description"><?php esc_html_e('Kolai API Key\'inizi buraya girin.', 'kolai'); ?></p>
        <?php
    }
    
    /**
     * Render Secret Key field
     */
    public function render_secret_key_field() {
        $secret_key = get_option('kolai_secret_key', '');
        ?>
        <input type="password" 
               name="kolai_secret_key" 
               id="kolai_secret_key" 
               value="<?php echo esc_attr($secret_key); ?>" 
               class="regular-text" 
               placeholder="<?php esc_attr_e('Secret Key girin', 'kolai'); ?>" />
        <p class="description"><?php esc_html_e('Kolai Secret Key\'inizi buraya girin.', 'kolai'); ?></p>
        <?php
    }
}
