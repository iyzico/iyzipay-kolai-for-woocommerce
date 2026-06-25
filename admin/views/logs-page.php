<?php
/**
 * The admin-specific view for the logs page
 *
 * @package    Kolai
 * @subpackage Kolai/admin/views
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

if (isset($_GET['settings-updated'])) {
    add_settings_error(
        'kolai_logs_messages',
        'kolai_logs_message',
        __('Log ayarları kaydedildi.', 'kolai'),
        'updated'
    );
}

settings_errors('kolai_logs_messages');

$enabled        = (bool) get_option(Kolai_Logger::OPTION_ENABLED, false);
$current_level  = get_option(Kolai_Logger::OPTION_LEVEL, Kolai_Logger::LEVEL_INFO);
$retention_days = (int) get_option(Kolai_Logger::OPTION_RETENTION_DAYS, 7);
$total_count    = Kolai_Logger::count();
$contexts       = Kolai_Logger::distinct_contexts();
?>

<div class="wrap kolai-logs-wrap">
    <h1><?php esc_html_e('Kolai Loglar', 'kolai'); ?></h1>

    <h2 class="title"><?php esc_html_e('Log Ayarları', 'kolai'); ?></h2>
    <form action="options.php" method="post" class="kolai-logs-settings">
        <?php settings_fields('kolai_logs_group'); ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="kolai_logging_enabled"><?php esc_html_e('Log Tutmayı Etkinleştir', 'kolai'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="<?php echo esc_attr(Kolai_Logger::OPTION_ENABLED); ?>"
                                   id="kolai_logging_enabled"
                                   value="1"
                                   <?php checked($enabled, true); ?> />
                            <?php esc_html_e('API isteklerinde log tut', 'kolai'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Bu kutucuk işaretli ve "Ayarları Kaydet" butonu kullanılmadıkça hiçbir log yazılmaz.', 'kolai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="kolai_log_level"><?php esc_html_e('Minimum Log Seviyesi', 'kolai'); ?></label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr(Kolai_Logger::OPTION_LEVEL); ?>" id="kolai_log_level">
                            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_DEBUG); ?>"   <?php selected($current_level, Kolai_Logger::LEVEL_DEBUG); ?>><?php esc_html_e('Debug — her şey', 'kolai'); ?></option>
                            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_INFO); ?>"    <?php selected($current_level, Kolai_Logger::LEVEL_INFO); ?>><?php esc_html_e('Info — adım kayıtları', 'kolai'); ?></option>
                            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_WARNING); ?>" <?php selected($current_level, Kolai_Logger::LEVEL_WARNING); ?>><?php esc_html_e('Warning — yalnızca uyarı/hata', 'kolai'); ?></option>
                            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_ERROR); ?>"   <?php selected($current_level, Kolai_Logger::LEVEL_ERROR); ?>><?php esc_html_e('Error — yalnızca hata', 'kolai'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Sorun arıyorsanız Debug seviyesini önerin. Yoğun trafikte Info veya Warning daha güvenli.', 'kolai'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="kolai_log_retention_days"><?php esc_html_e('Log Saklama Süresi (gün)', 'kolai'); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               name="<?php echo esc_attr(Kolai_Logger::OPTION_RETENTION_DAYS); ?>"
                               id="kolai_log_retention_days"
                               class="small-text"
                               min="0"
                               max="365"
                               value="<?php echo esc_attr($retention_days); ?>" />
                        <p class="description">
                            <?php esc_html_e('Bu süreden eski loglar günlük cron işiyle otomatik silinir. 0 = sınırsız (önerilmez).', 'kolai'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(__('Ayarları Kaydet', 'kolai')); ?>
    </form>

    <hr />

    <h2 class="title">
        <?php esc_html_e('Log Kayıtları', 'kolai'); ?>
        <span class="kolai-logs-count">(<?php echo (int) $total_count; ?>)</span>
    </h2>

    <?php if (!$enabled) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php esc_html_e('Log tutma şu anda kapalı. Yeni istekler kaydedilmeyecek; aşağıda yalnızca eski kayıtlar görünür.', 'kolai'); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="kolai-logs-toolbar">
        <select id="kolai-logs-filter-level">
            <option value=""><?php esc_html_e('Tüm seviyeler', 'kolai'); ?></option>
            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_DEBUG); ?>">debug</option>
            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_INFO); ?>">info</option>
            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_WARNING); ?>">warning</option>
            <option value="<?php echo esc_attr(Kolai_Logger::LEVEL_ERROR); ?>">error</option>
        </select>

        <select id="kolai-logs-filter-context">
            <option value=""><?php esc_html_e('Tüm bağlamlar', 'kolai'); ?></option>
            <?php foreach ($contexts as $ctx) : ?>
                <option value="<?php echo esc_attr($ctx); ?>"><?php echo esc_html($ctx); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="search"
               id="kolai-logs-filter-search"
               placeholder="<?php esc_attr_e('Mesaj/data içinde ara…', 'kolai'); ?>"
               class="regular-text" />

        <button type="button" class="button" id="kolai-logs-refresh">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Yenile', 'kolai'); ?>
        </button>

        <button type="button" class="button" id="kolai-logs-auto" data-on="0">
            <?php esc_html_e('Otomatik yenileme: kapalı', 'kolai'); ?>
        </button>

        <button type="button" class="button button-link-delete" id="kolai-logs-clear">
            <?php esc_html_e('Tüm Logları Temizle', 'kolai'); ?>
        </button>
    </div>

    <table class="widefat striped kolai-logs-table">
        <thead>
            <tr>
                <th style="width: 160px;"><?php esc_html_e('Zaman (UTC)', 'kolai'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('Seviye', 'kolai'); ?></th>
                <th style="width: 100px;"><?php esc_html_e('Bağlam', 'kolai'); ?></th>
                <th style="width: 60px;"><?php esc_html_e('Yöntem', 'kolai'); ?></th>
                <th><?php esc_html_e('Rota', 'kolai'); ?></th>
                <th><?php esc_html_e('Mesaj', 'kolai'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('Süre', 'kolai'); ?></th>
            </tr>
        </thead>
        <tbody id="kolai-logs-tbody" aria-live="polite" aria-busy="false">
            <tr>
                <td colspan="7" class="kolai-logs-loading">
                    <?php esc_html_e('Yükleniyor…', 'kolai'); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="kolai-logs-pagination">
        <button type="button" class="button" id="kolai-logs-prev" disabled>‹ <?php esc_html_e('Önceki', 'kolai'); ?></button>
        <span id="kolai-logs-pageinfo"></span>
        <button type="button" class="button" id="kolai-logs-next" disabled><?php esc_html_e('Sonraki', 'kolai'); ?> ›</button>
    </div>
</div>
