<?php
/**
 * Logger for Kolai plugin
 *
 * Writes structured logs to a custom DB table when enabled in settings.
 * All public methods are no-ops (with near-zero overhead) when disabled,
 * so production sites can leave logging off without performance impact.
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kolai Logger.
 */
class Kolai_Logger {

    const TABLE = 'kolai_logs';

    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    const OPTION_ENABLED        = 'kolai_logging_enabled';
    const OPTION_LEVEL          = 'kolai_log_level';
    const OPTION_RETENTION_DAYS = 'kolai_log_retention_days';

    const CRON_HOOK = 'kolai_logs_cleanup';

    /**
     * Numeric severity for level filtering.
     *
     * @var array
     */
    private static $level_weight = array(
        self::LEVEL_DEBUG   => 10,
        self::LEVEL_INFO    => 20,
        self::LEVEL_WARNING => 30,
        self::LEVEL_ERROR   => 40,
    );

    /**
     * Cached enabled flag for the current request.
     *
     * @var bool|null
     */
    private static $enabled_cache = null;

    /**
     * Cached numeric weight of the configured min level.
     *
     * @var int|null
     */
    private static $min_weight_cache = null;

    /**
     * Active request id (groups all logs from the same HTTP call).
     *
     * @var string|null
     */
    private static $request_id = null;

    /**
     * Active request method.
     *
     * @var string|null
     */
    private static $request_method = null;

    /**
     * Active request route (REST path).
     *
     * @var string|null
     */
    private static $request_route = null;

    /**
     * Active request start time (microtime float).
     *
     * @var float|null
     */
    private static $request_started_at = null;

    /**
     * Get the full DB table name (with WP prefix).
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Check if logging is enabled (cached per request).
     *
     * Returns false if the toggle is off OR if the database table is missing
     * — that way callers don't have to worry about misconfigured installs
     * where the schema migration never ran.
     *
     * @return bool
     */
    public static function is_enabled() {
        if (self::$enabled_cache === null) {
            $enabled = (bool) get_option(self::OPTION_ENABLED, false);
            if ($enabled && !self::table_exists()) {
                $enabled = false;
            }
            self::$enabled_cache = $enabled;
        }
        return self::$enabled_cache;
    }

    /**
     * Whether the logs table exists in the current database.
     * Cached per request via $enabled_cache so we don't issue SHOW TABLES on
     * every single log call.
     *
     * @return bool
     */
    private static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $result === $table;
    }

    /**
     * Reset cached settings (called after settings save).
     */
    public static function reset_cache() {
        self::$enabled_cache    = null;
        self::$min_weight_cache = null;
    }

    /**
     * Min severity weight currently configured.
     *
     * @return int
     */
    private static function min_weight() {
        if (self::$min_weight_cache === null) {
            $level = get_option(self::OPTION_LEVEL, self::LEVEL_INFO);
            self::$min_weight_cache = isset(self::$level_weight[$level])
                ? self::$level_weight[$level]
                : self::$level_weight[self::LEVEL_INFO];
        }
        return self::$min_weight_cache;
    }

    /**
     * Whether the given level should be persisted.
     *
     * @param string $level
     * @return bool
     */
    private static function should_log($level) {
        if (!self::is_enabled()) {
            return false;
        }
        $weight = isset(self::$level_weight[$level]) ? self::$level_weight[$level] : 0;
        return $weight >= self::min_weight();
    }

    /**
     * Begin tracking a request. Generates a request id and stores method/route.
     *
     * @param string $method REST method (GET/POST/...).
     * @param string $route  REST path (e.g. /kolai/v1/products).
     * @return string|null Request id, or null when logging disabled.
     */
    public static function start_request($method, $route) {
        if (!self::is_enabled()) {
            return null;
        }
        self::$request_id         = self::generate_request_id();
        self::$request_method     = $method;
        self::$request_route      = $route;
        self::$request_started_at = microtime(true);
        return self::$request_id;
    }

    /**
     * Mark the active request as ended. Writes a final summary log entry
     * with elapsed duration and outcome.
     *
     * @param int|string $status     HTTP status code.
     * @param string     $outcome    'success' or 'failure'.
     * @param array      $extra      Optional extra data.
     */
    public static function end_request($status, $outcome = 'success', $extra = array()) {
        if (!self::is_enabled() || self::$request_id === null) {
            self::clear_request_state();
            return;
        }

        $duration_ms = self::$request_started_at !== null
            ? (int) round((microtime(true) - self::$request_started_at) * 1000)
            : null;

        $data = array_merge(array(
            'status'  => (int) $status,
            'outcome' => $outcome,
        ), $extra);

        $level = ($outcome === 'success') ? self::LEVEL_INFO : self::LEVEL_WARNING;
        if ((int) $status >= 500) {
            $level = self::LEVEL_ERROR;
        }

        self::write($level, 'request', sprintf('Request finished (%d)', (int) $status), $data, $duration_ms);
        self::clear_request_state();
    }

    /**
     * Reset transient request tracking state.
     */
    private static function clear_request_state() {
        self::$request_id         = null;
        self::$request_method     = null;
        self::$request_route      = null;
        self::$request_started_at = null;
    }

    /**
     * Convenience: debug-level log.
     */
    public static function debug($context, $message, $data = array()) {
        self::log(self::LEVEL_DEBUG, $context, $message, $data);
    }

    /**
     * Convenience: info-level log.
     */
    public static function info($context, $message, $data = array()) {
        self::log(self::LEVEL_INFO, $context, $message, $data);
    }

    /**
     * Convenience: warning-level log.
     */
    public static function warning($context, $message, $data = array()) {
        self::log(self::LEVEL_WARNING, $context, $message, $data);
    }

    /**
     * Convenience: error-level log.
     */
    public static function error($context, $message, $data = array()) {
        self::log(self::LEVEL_ERROR, $context, $message, $data);
    }

    /**
     * Generic log entry.
     *
     * @param string $level
     * @param string $context  e.g. auth, product, order, shipping, contract
     * @param string $message
     * @param array  $data     Extra context (will be JSON encoded).
     */
    public static function log($level, $context, $message, $data = array()) {
        if (!self::should_log($level)) {
            return;
        }
        self::write($level, $context, $message, $data, null);
    }

    /**
     * Persist a single log row. Wraps DB insert in a try/catch so logging
     * failures never break the request.
     *
     * @param string   $level
     * @param string   $context
     * @param string   $message
     * @param array    $data
     * @param int|null $duration_ms
     */
    private static function write($level, $context, $message, $data, $duration_ms) {
        global $wpdb;

        try {
            $payload = !empty($data) ? wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            $wpdb->insert(
                self::table_name(),
                array(
                    'created_at'  => current_time('mysql', true),
                    'level'       => $level,
                    'context'     => substr((string) $context, 0, 50),
                    'request_id'  => self::$request_id,
                    'method'      => self::$request_method ? substr(self::$request_method, 0, 10) : null,
                    'route'       => self::$request_route ? substr(self::$request_route, 0, 255) : null,
                    'message'     => substr((string) $message, 0, 1000),
                    'data'        => $payload,
                    'duration_ms' => $duration_ms !== null ? (int) $duration_ms : null,
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        } catch (Exception $e) {
            // Swallow logging failures. Fall back to error_log so we don't lose the trace entirely.
            error_log(sprintf('[Kolai Logger] %s', $e->getMessage()));
        }
    }

    /**
     * Generate a UUIDv4-shaped request id (no hard dependency on uuid lib).
     *
     * @return string
     */
    private static function generate_request_id() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Fetch logs with optional filters.
     *
     * @param array $filters {
     *     @type string $level   Filter by exact level.
     *     @type string $context Filter by exact context.
     *     @type string $search  LIKE on message.
     *     @type int    $limit   Max rows (default 100).
     *     @type int    $offset  Offset (default 0).
     *     @type string $request_id Filter to a single request id.
     * }
     * @return array { rows: array, total: int }
     */
    public static function get_logs($filters = array()) {
        global $wpdb;

        $table = self::table_name();
        $where = array('1=1');
        $args  = array();

        if (!empty($filters['level'])) {
            $where[] = 'level = %s';
            $args[]  = $filters['level'];
        }
        if (!empty($filters['context'])) {
            $where[] = 'context = %s';
            $args[]  = $filters['context'];
        }
        if (!empty($filters['request_id'])) {
            $where[] = 'request_id = %s';
            $args[]  = $filters['request_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(message LIKE %s OR data LIKE %s)';
            $like    = '%' . $wpdb->esc_like($filters['search']) . '%';
            $args[]  = $like;
            $args[]  = $like;
        }

        $limit  = isset($filters['limit'])  ? max(1, min(500, (int) $filters['limit']))   : 100;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $rows_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";

        if (!empty($args)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $args));
            $rows  = $wpdb->get_results($wpdb->prepare($rows_sql, array_merge($args, array($limit, $offset))));
        } else {
            $total = (int) $wpdb->get_var($count_sql);
            $rows  = $wpdb->get_results($wpdb->prepare($rows_sql, array($limit, $offset)));
        }

        return array(
            'rows'  => $rows ? $rows : array(),
            'total' => $total,
        );
    }

    /**
     * Total log row count.
     *
     * @return int
     */
    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table_name());
    }

    /**
     * Truncate all logs.
     *
     * @return int Rows deleted.
     */
    public static function clear() {
        global $wpdb;
        $count = self::count();
        $wpdb->query('TRUNCATE TABLE ' . self::table_name());
        return $count;
    }

    /**
     * Delete logs older than the configured retention window.
     *
     * @return int Rows deleted.
     */
    public static function cleanup() {
        global $wpdb;

        $days = (int) get_option(self::OPTION_RETENTION_DAYS, 7);
        if ($days <= 0) {
            return 0;
        }

        return (int) $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s',
                gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
            )
        );
    }

    /**
     * Create the logs table. Called from the activator.
     */
    public static function create_table() {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            context VARCHAR(50) NOT NULL DEFAULT '',
            request_id VARCHAR(40) DEFAULT NULL,
            method VARCHAR(10) DEFAULT NULL,
            route VARCHAR(255) DEFAULT NULL,
            message VARCHAR(1000) NOT NULL DEFAULT '',
            data LONGTEXT DEFAULT NULL,
            duration_ms INT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_level (level),
            KEY idx_context (context),
            KEY idx_request_id (request_id)
        ) {$charset_collate};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);
    }

    /**
     * Drop the logs table. Used during uninstall (NOT deactivation).
     */
    public static function drop_table() {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS ' . self::table_name());
    }

    /**
     * Distinct context values currently present in the logs (for filter dropdown).
     *
     * @return array
     */
    public static function distinct_contexts() {
        global $wpdb;
        $rows = $wpdb->get_col('SELECT DISTINCT context FROM ' . self::table_name() . ' ORDER BY context ASC');
        return $rows ? $rows : array();
    }
}
