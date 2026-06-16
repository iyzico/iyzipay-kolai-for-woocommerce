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

        register_setting(
            'kolai_settings_group',
            'kolai_clarification_text_page_id',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0
            )
        );

        add_settings_field(
            'kolai_clarification_text_page_id',
            __('Aydinlatma Metni Sayfasi', 'kolai'),
            array($this, 'render_clarification_text_page_field'),
            'kolai-settings',
            'kolai_api_section',
            array('label_for' => 'kolai_clarification_text_page_id')
        );

        // iyzico refund/cancel credentials
        register_setting(
            'kolai_settings_group',
            'kolai_iyzico_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_settings_group',
            'kolai_iyzico_secret_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_settings_group',
            'kolai_iyzico_environment',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_iyzico_environment'),
                'default' => 'sandbox'
            )
        );

        add_settings_section(
            'kolai_iyzico_section',
            __('iyzico Iade / Iptal Ayarlari', 'kolai'),
            array($this, 'render_iyzico_section_callback'),
            'kolai-settings'
        );

        add_settings_field(
            'kolai_iyzico_api_key',
            __('iyzico API Key', 'kolai'),
            array($this, 'render_iyzico_api_key_field'),
            'kolai-settings',
            'kolai_iyzico_section',
            array('label_for' => 'kolai_iyzico_api_key')
        );

        add_settings_field(
            'kolai_iyzico_secret_key',
            __('iyzico Secret Key', 'kolai'),
            array($this, 'render_iyzico_secret_key_field'),
            'kolai-settings',
            'kolai_iyzico_section',
            array('label_for' => 'kolai_iyzico_secret_key')
        );

        add_settings_field(
            'kolai_iyzico_environment',
            __('iyzico Ortam', 'kolai'),
            array($this, 'render_iyzico_environment_field'),
            'kolai-settings',
            'kolai_iyzico_section',
            array('label_for' => 'kolai_iyzico_environment')
        );

        // Seller info settings
        register_setting(
            'kolai_contracts_group',
            'kolai_seller_name',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_seller_address',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_seller_phone',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_seller_email',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => ''
            )
        );

        // Delivery and withdrawal settings
        register_setting(
            'kolai_contracts_group',
            'kolai_delivery_date',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_right_of_withdrawal_period',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Contract settings
        register_setting(
            'kolai_contracts_group',
            'kolai_contract_distance_sales',
            array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_contract_preliminary_info',
            array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_seller_tax_id',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'kolai_contracts_group',
            'kolai_seller_mersis_no',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Logging settings (saved on the dedicated Logs admin page)
        register_setting(
            'kolai_logs_group',
            Kolai_Logger::OPTION_ENABLED,
            array(
                'type' => 'boolean',
                'sanitize_callback' => array($this, 'sanitize_bool'),
                'default' => 0,
            )
        );

        register_setting(
            'kolai_logs_group',
            Kolai_Logger::OPTION_LEVEL,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_log_level'),
                'default' => Kolai_Logger::LEVEL_INFO,
            )
        );

        register_setting(
            'kolai_logs_group',
            Kolai_Logger::OPTION_RETENTION_DAYS,
            array(
                'type' => 'integer',
                'sanitize_callback' => array($this, 'sanitize_retention_days'),
                'default' => 7,
            )
        );
    }

    /**
     * Sanitize a checkbox/boolean value (returns 1 or 0).
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_bool($value) {
        return (!empty($value) && $value !== '0') ? 1 : 0;
    }

    /**
     * Sanitize log level — must be one of the supported constants.
     *
     * @param string $value
     * @return string
     */
    public function sanitize_log_level($value) {
        $allowed = array(
            Kolai_Logger::LEVEL_DEBUG,
            Kolai_Logger::LEVEL_INFO,
            Kolai_Logger::LEVEL_WARNING,
            Kolai_Logger::LEVEL_ERROR,
        );
        return in_array($value, $allowed, true) ? $value : Kolai_Logger::LEVEL_INFO;
    }

    /**
     * Clamp retention days to a sensible range (0 = unlimited).
     *
     * @param mixed $value
     * @return int
     */
    public function sanitize_retention_days($value) {
        $value = (int) $value;
        if ($value < 0) {
            return 0;
        }
        if ($value > 365) {
            return 365;
        }
        return $value;
    }
    
    /**
     * Sanitize the iyzico environment — must be sandbox or production.
     *
     * @param string $value
     * @return string
     */
    public function sanitize_iyzico_environment($value) {
        return ($value === 'production') ? 'production' : 'sandbox';
    }

    /**
     * Render the section description
     */
    public function render_section_callback() {
        echo '<p>' . __('Kolai API entegrasyonu için gerekli bilgileri girin.', 'kolai') . '</p>';
    }

    /**
     * Render the iyzico section description
     */
    public function render_iyzico_section_callback() {
        echo '<p>' . esc_html__('Siparis iade ve iptal islemlerinin iyzico\'ya iletilmesi icin iyzico API bilgilerinizi girin.', 'kolai') . '</p>';
    }

    /**
     * Render iyzico API Key field
     */
    public function render_iyzico_api_key_field() {
        $api_key = get_option('kolai_iyzico_api_key', '');
        ?>
        <input type="text"
               name="kolai_iyzico_api_key"
               id="kolai_iyzico_api_key"
               value="<?php echo esc_attr($api_key); ?>"
               class="regular-text"
               placeholder="<?php esc_attr_e('iyzico API Key girin', 'kolai'); ?>" />
        <p class="description"><?php esc_html_e('iyzico API Key\'inizi buraya girin.', 'kolai'); ?></p>
        <?php
    }

    /**
     * Render iyzico Secret Key field
     */
    public function render_iyzico_secret_key_field() {
        $secret_key = get_option('kolai_iyzico_secret_key', '');
        ?>
        <input type="password"
               name="kolai_iyzico_secret_key"
               id="kolai_iyzico_secret_key"
               value="<?php echo esc_attr($secret_key); ?>"
               class="regular-text"
               placeholder="<?php esc_attr_e('iyzico Secret Key girin', 'kolai'); ?>" />
        <p class="description"><?php esc_html_e('iyzico Secret Key\'inizi buraya girin.', 'kolai'); ?></p>
        <?php
    }

    /**
     * Render iyzico environment selector
     */
    public function render_iyzico_environment_field() {
        $environment = get_option('kolai_iyzico_environment', 'sandbox');
        ?>
        <select name="kolai_iyzico_environment" id="kolai_iyzico_environment">
            <option value="sandbox" <?php selected($environment, 'sandbox'); ?>><?php esc_html_e('Sandbox (Test)', 'kolai'); ?></option>
            <option value="production" <?php selected($environment, 'production'); ?>><?php esc_html_e('Production (Canli)', 'kolai'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Test icin Sandbox, canli islemler icin Production secin.', 'kolai'); ?></p>
        <?php
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

    /**
     * Render Clarification Text page field
     */
    public function render_clarification_text_page_field() {
        $selected_page_id = absint(get_option('kolai_clarification_text_page_id', 0));
        $pages = get_pages(array(
            'sort_column' => 'post_title',
            'post_status' => array('publish'),
        ));
        ?>
        <select name="kolai_clarification_text_page_id"
                id="kolai_clarification_text_page_id">
            <option value="0"><?php esc_html_e('Sayfa secin', 'kolai'); ?></option>
            <?php foreach ($pages as $page) : ?>
                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_page_id, $page->ID); ?>>
                    <?php echo esc_html($page->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('API icin var olan sayfalardan bir Aydinlatma Metni sayfasi secin.', 'kolai'); ?></p>
        <?php if ($selected_page_id) : ?>
            <?php $page_url = get_permalink($selected_page_id); ?>
            <?php if ($page_url) : ?>
                <p class="description">
                    <?php esc_html_e('Secili sayfa linki:', 'kolai'); ?>
                    <a href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($page_url); ?></a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }
}
