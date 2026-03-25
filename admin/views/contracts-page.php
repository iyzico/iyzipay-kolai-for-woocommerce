<?php
/**
 * The admin view for the contracts page
 *
 * @package    Kolai
 * @subpackage Kolai/admin/views
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Show success message if settings saved
if (isset($_GET['settings-updated'])) {
    add_settings_error(
        'kolai_contracts_messages',
        'kolai_contracts_message',
        __('Sozlesme ayarlari kaydedildi.', 'kolai'),
        'updated'
    );
}

// Show any settings errors
settings_errors('kolai_contracts_messages');

// Load contract service for placeholder definitions and defaults
require_once KOLAI_INCLUDES_DIR . 'class-kolai-constants.php';
require_once KOLAI_INCLUDES_DIR . 'class-kolai-exceptions.php';
require_once KOLAI_INCLUDES_DIR . 'class-kolai-response.php';
require_once KOLAI_INCLUDES_DIR . 'contract/contract-service.php';

$contract_service = new Kolai_Contract_Service();
$placeholders = $contract_service->get_placeholder_definitions();
$types = $contract_service->get_available_types();

// Get current values (fall back to defaults via service)
$distance_sales_content = get_option('kolai_contract_distance_sales', '');
if (empty($distance_sales_content)) {
    $distance_sales_content = $contract_service->get_template('distance_sales');
}

$preliminary_info_content = get_option('kolai_contract_preliminary_info', '');
if (empty($preliminary_info_content)) {
    $preliminary_info_content = $contract_service->get_template('preliminary_info');
}

$seller_tax_id = get_option('kolai_seller_tax_id', '');
$seller_mersis_no = get_option('kolai_seller_mersis_no', '');
?>

<div class="wrap">
    <h1><?php esc_html_e('Sozlesmeler', 'kolai'); ?></h1>

    <form action="options.php" method="post">
        <?php settings_fields('kolai_contracts_group'); ?>

        <h2><?php esc_html_e('Satici Bilgileri', 'kolai'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="kolai_seller_tax_id"><?php esc_html_e('Vergi Kimlik Numarasi (VKN)', 'kolai'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="kolai_seller_tax_id"
                           id="kolai_seller_tax_id"
                           value="<?php echo esc_attr($seller_tax_id); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('VKN girin', 'kolai'); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="kolai_seller_mersis_no"><?php esc_html_e('MERSIS Numarasi', 'kolai'); ?></label>
                </th>
                <td>
                    <input type="text"
                           name="kolai_seller_mersis_no"
                           id="kolai_seller_mersis_no"
                           value="<?php echo esc_attr($seller_mersis_no); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('MERSIS numarasi girin', 'kolai'); ?>" />
                </td>
            </tr>
        </table>

        <hr>

        <h2><?php esc_html_e('Kullanilabilir Yer Tutucular', 'kolai'); ?></h2>
        <div id="kolai-placeholders-panel">
            <button type="button" class="button" onclick="var panel = document.getElementById('kolai-placeholders-list'); panel.style.display = panel.style.display === 'none' ? 'block' : 'none';">
                <?php esc_html_e('Yer Tutuculari Goster/Gizle', 'kolai'); ?>
            </button>
            <div id="kolai-placeholders-list" style="display:none; margin-top:10px;">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Yer Tutucu', 'kolai'); ?></th>
                            <th><?php esc_html_e('Aciklama', 'kolai'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($placeholders as $placeholder => $description) : ?>
                            <tr>
                                <td><code><?php echo esc_html($placeholder); ?></code></td>
                                <td><?php echo esc_html($description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <hr>

        <h2><?php echo esc_html($types['distance_sales']); ?></h2>
        <?php
        wp_editor(
            $distance_sales_content,
            'kolai_contract_distance_sales',
            array(
                'textarea_name' => 'kolai_contract_distance_sales',
                'textarea_rows' => 20,
                'media_buttons' => false,
            )
        );
        ?>

        <hr>

        <h2><?php echo esc_html($types['preliminary_info']); ?></h2>
        <?php
        wp_editor(
            $preliminary_info_content,
            'kolai_contract_preliminary_info',
            array(
                'textarea_name' => 'kolai_contract_preliminary_info',
                'textarea_rows' => 20,
                'media_buttons' => false,
            )
        );
        ?>

        <?php submit_button(__('Sozlesmeleri Kaydet', 'kolai')); ?>
    </form>
</div>
