<?php
/**
 * Base class for Kolai REST routes
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base route helper for standardized responses.
 */
abstract class Kolai_Route_Base {

    /**
     * Hard cap (bytes) on the JSON-encoded request body stored in a log entry.
     * Protects against unbounded PII/log growth from large payloads.
     */
    const MAX_LOG_BODY = 2000;

    /**
     * Extra top-level meta fields to attach to the next 200 response body
     * (e.g. pagination metadata). Embedded in the JSON envelope rather than
     * sent as HTTP headers — some HTTP/2 / proxy stacks reject custom
     * response headers from non-builtin endpoints with a "Header field must
     * only have a single value" protocol error.
     *
     * Subclasses populate this from inside the handle() callable. The list is
     * consumed and reset on every handle() invocation.
     *
     * @var array
     */
    protected $next_response_meta = array();

    /**
     * Schedule a meta field to be merged into the next successful response
     * envelope. Field names appear at the top level of the JSON body, next
     * to `data`.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function add_response_meta($key, $value) {
        $this->next_response_meta[$key] = $value;
    }

    /**
     * Execute a handler with standardized error handling and logging.
     *
     * @param callable             $handler
     * @param WP_REST_Request|null $request Optional request, used for logging context.
     * @return WP_REST_Response
     */
    protected function handle($handler, $request = null) {
        $started = microtime(true);

        if (Kolai_Logger::is_enabled() && $request) {
            $route  = $request->get_route();
            $method = $request->get_method();
            Kolai_Logger::start_request($method, $route);
            Kolai_Logger::info('request', 'Request started', array(
                'route'  => $route,
                'method' => $method,
                'params' => self::safe_params($request),
            ));
        } elseif (Kolai_Logger::is_enabled()) {
            // Fallback when handlers don't pass the request — still group logs.
            $route = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
            Kolai_Logger::start_request($method, $route);
        }

        try {
            $data = call_user_func($handler);
            $response = Kolai_Response::success($data);
            $duration_ms = (int) round((microtime(true) - $started) * 1000);

            Kolai_Logger::info('request', 'Request succeeded', array(
                'duration_ms'   => $duration_ms,
                'response_size' => is_array($data) ? count($data) : null,
            ));
            Kolai_Logger::end_request(200, 'success', array('duration_ms' => $duration_ms));

            if (!empty($this->next_response_meta)) {
                foreach ($this->next_response_meta as $mkey => $mval) {
                    $response[$mkey] = $mval;
                }
                $this->next_response_meta = array();
            }
            return new WP_REST_Response($response, 200);
        } catch (Kolai_Exception $e) {
            self::fallback_log(sprintf('%s (%s)', $e->getMessage(), $e->get_error_code()), 'warning');

            Kolai_Logger::warning('request', 'Domain exception thrown', array(
                'class'        => get_class($e),
                'error_code'   => $e->get_error_code(),
                'http_status'  => $e->getCode(),
                'message'      => $e->getMessage(),
            ));
            Kolai_Logger::end_request($e->getCode(), 'failure', array(
                'error_code' => $e->get_error_code(),
            ));

            $this->next_response_meta = array();
            $response = $e->to_response();
            return new WP_REST_Response($response, $e->getCode());
        } catch (Throwable $e) {
            // Throwable covers both Error (e.g. fatal type errors) and Exception in PHP 7+.
            self::fallback_log(sprintf('Unexpected error: %s', $e->getMessage()), 'error');

            Kolai_Logger::error('request', 'Unhandled exception', array(
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => self::short_trace($e),
            ));
            Kolai_Logger::end_request(500, 'failure', array(
                'exception' => get_class($e),
            ));

            $this->next_response_meta = array();
            $response = Kolai_Response::unexpected_error();
            return new WP_REST_Response($response, 500);
        }
    }

    /**
     * Produce a request param snapshot safe to log. Order/shipping bodies carry
     * names, email, phone, tax id, and full billing/shipping addresses; this
     * redacts every sensitive field and caps the encoded payload size so the log
     * table never becomes a long-lived duplicate PII store.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    private static function safe_params($request) {
        $url_params  = $request->get_url_params();
        $query       = $request->get_query_params();
        $body_params = $request->get_body_params();
        $json        = $request->get_json_params();

        $body_summary = null;
        if (is_array($json) && !empty($json)) {
            $body_summary = self::redact_payload($json);
        } elseif (is_array($body_params) && !empty($body_params)) {
            $body_summary = self::redact_payload($body_params);
        } else {
            $raw = $request->get_body();
            if (is_string($raw) && $raw !== '') {
                // Unparsed body: structure is unknown so it cannot be field-redacted.
                // Record only its size, never its contents.
                $body_summary = array('_unparsed_bytes' => strlen($raw));
            }
        }

        return array(
            // Route params ({id}) are non-sensitive numerics.
            'url'   => $url_params,
            'query' => self::redact_payload(is_array($query) ? $query : array()),
            'body'  => $body_summary,
        );
    }

    /**
     * Redact sensitive fields from a structured payload and cap its encoded size.
     *
     * @param array $data
     * @return array
     */
    private static function redact_payload($data) {
        $redacted = self::redact_value($data, 0);
        $encoded  = wp_json_encode($redacted);
        if (is_string($encoded) && strlen($encoded) > self::MAX_LOG_BODY) {
            return array('_redacted_summary' => substr($encoded, 0, self::MAX_LOG_BODY) . '… (truncated)');
        }
        return is_array($redacted) ? $redacted : array('_value' => $redacted);
    }

    /**
     * Recursively replace sensitive values with a redaction marker. Sensitive
     * container keys (buyer/billingAddress/shippingAddress/address) are dropped
     * wholesale; sensitive scalar keys anywhere are masked.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    private static function redact_value($value, $depth) {
        if ($depth > 6) {
            return '[redacted-depth]';
        }
        if (is_array($value)) {
            $out = array();
            foreach ($value as $k => $v) {
                if (is_string($k) && self::is_sensitive_key($k)) {
                    $out[$k] = '[redacted]';
                    continue;
                }
                $out[$k] = self::redact_value($v, $depth + 1);
            }
            return $out;
        }
        if (is_string($value) && strlen($value) > 120) {
            return substr($value, 0, 120) . '…';
        }
        return $value;
    }

    /**
     * Whether a payload key names personal / contact / tax / payment data that
     * must never be persisted to the log table.
     *
     * @param string $key
     * @return bool
     */
    private static function is_sensitive_key($key) {
        $k = strtolower($key);

        static $exact = array(
            'buyer', 'billingaddress', 'shippingaddress', 'address', 'itemtransactions',
        );
        if (in_array($k, $exact, true)) {
            return true;
        }

        // Substrings checked anywhere in a key. Kept specific enough to avoid
        // false positives on benign keys (e.g. 'pan' would wrongly match
        // 'company'; bare 'lat'/'lng' would match 'late'/'flat'). Card data is
        // covered by 'card'/'iban'/'cvv'; coordinates by 'latitude'/'longitude'.
        static $fragments = array(
            'email', 'phone', 'gsm', 'name', 'surname', 'identity', 'tckn',
            'taxid', 'taxoffice', 'address', 'city', 'district', 'town', 'zip',
            'postcode', 'postal', 'street', 'iban', 'card', 'cvv', 'cvc',
            'payment', 'latitude', 'longitude', 'contact',
        );
        foreach ($fragments as $fragment) {
            if (strpos($k, $fragment) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Last-resort log to WooCommerce's structured logger (falls back to the PHP
     * error log only when WooCommerce is unavailable).
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    private static function fallback_log($message, $level = 'error') {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, array('source' => 'kolai'));
            return;
        }
        error_log('[Kolai] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    /**
     * Compact stack trace (top 8 frames) for log payload.
     *
     * @param Throwable $e
     * @return array
     */
    private static function short_trace($e) {
        $frames = array();
        foreach (array_slice($e->getTrace(), 0, 8) as $frame) {
            $frames[] = sprintf(
                '%s%s%s() at %s:%d',
                isset($frame['class']) ? $frame['class'] : '',
                isset($frame['type']) ? $frame['type'] : '',
                isset($frame['function']) ? $frame['function'] : '',
                isset($frame['file']) ? $frame['file'] : '?',
                isset($frame['line']) ? $frame['line'] : 0
            );
        }
        return $frames;
    }
}
