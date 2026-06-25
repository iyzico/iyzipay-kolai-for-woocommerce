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
     * Slug used by the Logs admin page (matches add_submenu_page menu_slug).
     */
    const LOGS_PAGE_SLUG = 'kolai-logs';

    /**
     * Initialize the class and set its properties
     *
     * @param string $plugin_name The name of this plugin
     * @param string $version     The version of this plugin
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // AJAX handlers for the Log Viewer page (admin only).
        add_action('wp_ajax_kolai_logs_fetch', array($this, 'ajax_fetch_logs'));
        add_action('wp_ajax_kolai_logs_clear', array($this, 'ajax_clear_logs'));

        // Reset cached settings whenever the logging options are saved.
        add_action('update_option_' . Kolai_Logger::OPTION_ENABLED, array('Kolai_Logger', 'reset_cache'));
        add_action('update_option_' . Kolai_Logger::OPTION_LEVEL,   array('Kolai_Logger', 'reset_cache'));

        // Add a "Settings" shortcut on the Plugins list row.
        add_filter('plugin_action_links_' . KOLAI_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Whether the current admin screen belongs to this plugin (all Kolai page
     * slugs contain "kolai"), so assets load only where they are needed.
     *
     * @param string $hook Current admin page hook suffix.
     * @return bool
     */
    private function is_kolai_screen($hook) {
        return is_string($hook) && strpos($hook, 'kolai') !== false;
    }

    /**
     * Add a "Settings" action link to the plugin row.
     *
     * @param array $links
     * @return array
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=kolai-settings')) . '">'
            . esc_html__('Ayarlar', 'kolai') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Register the stylesheets for the admin area
     */
    public function enqueue_styles($hook) {
        if (!$this->is_kolai_screen($hook)) {
            return;
        }
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
    public function enqueue_scripts($hook) {
        if (!$this->is_kolai_screen($hook)) {
            return;
        }
        wp_enqueue_script(
            $this->plugin_name,
            KOLAI_PLUGIN_URL . 'admin/js/kolai-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Only load the log viewer bundle on the Logs page.
        if (strpos((string) $hook, self::LOGS_PAGE_SLUG) !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-logs',
                KOLAI_PLUGIN_URL . 'admin/js/kolai-logs.js',
                array('jquery'),
                $this->version,
                true
            );
            wp_localize_script(
                $this->plugin_name . '-logs',
                'KolaiLogs',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('kolai_logs_nonce'),
                    'i18n'    => array(
                        'confirmClear' => __('Tüm loglar silinecek. Devam edilsin mi?', 'kolai'),
                        'clearError'   => __('Loglar silinirken bir hata oluştu.', 'kolai'),
                        'fetchError'   => __('Loglar yüklenirken bir hata oluştu.', 'kolai'),
                        'noLogs'       => __('Kayıt bulunamadı.', 'kolai'),
                        'cleared'      => __('Loglar temizlendi.', 'kolai'),
                    ),
                )
            );
        }
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

        add_submenu_page(
            'kolai-settings',
            __('Sozlesmeler', 'kolai'),
            __('Sozlesmeler', 'kolai'),
            'manage_options',
            'kolai-contracts',
            array($this, 'display_contracts_page')
        );

        add_submenu_page(
            'kolai-settings',
            __('Meta Alan Eslesme', 'kolai'),
            __('Meta Eslesme', 'kolai'),
            'manage_options',
            'kolai-meta-map',
            array($this, 'display_meta_map_page')
        );

        add_submenu_page(
            'kolai-settings',
            __('Loglar', 'kolai'),
            __('Loglar', 'kolai'),
            'manage_options',
            self::LOGS_PAGE_SLUG,
            array($this, 'display_logs_page')
        );
    }

    /**
     * Render the settings page for this plugin
     */
    public function display_settings_page() {
        include_once KOLAI_ADMIN_DIR . 'views/settings-page.php';
    }

    /**
     * Render the contracts page
     */
    public function display_contracts_page() {
        include_once KOLAI_ADMIN_DIR . 'views/contracts-page.php';
    }

    /**
     * Render the meta field mapping page
     */
    public function display_meta_map_page() {
        include_once KOLAI_ADMIN_DIR . 'views/meta-map-page.php';
    }

    /**
     * Render the logs page
     */
    public function display_logs_page() {
        include_once KOLAI_ADMIN_DIR . 'views/logs-page.php';
    }

    /**
     * AJAX: fetch a page of log rows.
     */
    public function ajax_fetch_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('İzniniz yok.', 'kolai')), 403);
        }
        check_ajax_referer('kolai_logs_nonce', 'nonce');

        $filters = array(
            'level'   => isset($_POST['level']) ? sanitize_text_field(wp_unslash($_POST['level'])) : '',
            'context' => isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : '',
            'search'  => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'request_id' => isset($_POST['request_id']) ? sanitize_text_field(wp_unslash($_POST['request_id'])) : '',
            'limit'   => isset($_POST['limit']) ? (int) $_POST['limit'] : 100,
            'offset'  => isset($_POST['offset']) ? (int) $_POST['offset'] : 0,
        );

        $result = Kolai_Logger::get_logs($filters);
        $contexts = Kolai_Logger::distinct_contexts();

        // Decode the JSON `data` column once on the server so the UI doesn't
        // have to handle string-or-object ambiguity.
        $rows = array();
        foreach ($result['rows'] as $row) {
            $decoded = null;
            if (!empty($row->data)) {
                $decoded = json_decode($row->data, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = $row->data;
                }
            }
            $rows[] = array(
                'id'          => (int) $row->id,
                'created_at'  => $row->created_at,
                'level'       => $row->level,
                'context'     => $row->context,
                'request_id'  => $row->request_id,
                'method'      => $row->method,
                'route'       => $row->route,
                'message'     => $row->message,
                'data'        => $decoded,
                'duration_ms' => $row->duration_ms !== null ? (int) $row->duration_ms : null,
            );
        }

        wp_send_json_success(array(
            'rows'     => $rows,
            'total'    => (int) $result['total'],
            'contexts' => $contexts,
            'enabled'  => Kolai_Logger::is_enabled(),
        ));
    }

    /**
     * AJAX: clear all log rows.
     */
    public function ajax_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('İzniniz yok.', 'kolai')), 403);
        }
        check_ajax_referer('kolai_logs_nonce', 'nonce');

        $deleted = Kolai_Logger::clear();

        wp_send_json_success(array(
            'deleted' => (int) $deleted,
        ));
    }
}
