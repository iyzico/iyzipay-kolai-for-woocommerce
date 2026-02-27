<?php
/**
 * The admin-specific view for the settings page
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
        'kolai_messages',
        'kolai_message',
        __('Ayarlar kaydedildi.', 'kolai'),
        'updated'
    );
}

// Show any settings errors
settings_errors('kolai_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form action="options.php" method="post">
        <?php
        settings_fields('kolai_settings_group');
        do_settings_sections('kolai-settings');
        submit_button(__('Ayarları Kaydet', 'kolai'));
        ?>
    </form>
</div>
