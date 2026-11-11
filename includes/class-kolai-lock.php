<?php
/**
 * Kolai Lock - cross-process named lock
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cross-process named lock that waits for a bounded time.
 *
 * When a persistent object cache is present (Redis/Memcached), wp_cache_add()
 * is atomic across processes and is polled until the deadline. Otherwise a
 * MySQL named lock (GET_LOCK) is used; it is atomic on a single primary DB and
 * is released automatically when the connection closes, so a fatal error can
 * never leave it held.
 */
class Kolai_Lock {

    const GROUP = 'kolai';

    /**
     * Expiry for the object-cache lock, so a crashed process cannot hold it forever.
     */
    const CACHE_TTL = 60;

    /**
     * Poll interval for the object-cache path, in microseconds.
     */
    const POLL_INTERVAL_US = 200000;

    /**
     * Acquire the lock, waiting up to $wait_seconds for a concurrent holder.
     *
     * @param string $name         Lock name (at most 64 characters).
     * @param int    $wait_seconds Maximum time to wait.
     * @return string|false Handle for release(), or false when the wait timed out.
     */
    public static function acquire($name, $wait_seconds) {
        $wait_seconds = max(0, (int) $wait_seconds);

        if (wp_using_ext_object_cache()) {
            $deadline = microtime(true) + $wait_seconds;
            do {
                if (wp_cache_add($name, 1, self::GROUP, self::CACHE_TTL)) {
                    return 'cache';
                }
                if (microtime(true) >= $deadline) {
                    return false;
                }
                usleep(self::POLL_INTERVAL_US);
            } while (true);
        }

        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, $wait_seconds));
        return ((string) $got === '1') ? 'db' : false;
    }

    /**
     * Release a lock acquired via acquire().
     *
     * @param string       $name
     * @param string|false $handle
     * @return void
     */
    public static function release($name, $handle) {
        if (!$handle) {
            return;
        }
        if ($handle === 'cache') {
            wp_cache_delete($name, self::GROUP);
            return;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}
