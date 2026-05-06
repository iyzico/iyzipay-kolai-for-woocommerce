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
            error_log(sprintf('[Kolai] %s (%s)', $e->getMessage(), $e->get_error_code()));

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
            error_log(sprintf('[Kolai] Unexpected error: %s', $e->getMessage()));

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
     * Produce a request param snapshot safe to log (truncates long bodies).
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
            $body_summary = $json;
        } elseif (is_array($body_params) && !empty($body_params)) {
            $body_summary = $body_params;
        } else {
            $raw = $request->get_body();
            if (is_string($raw) && $raw !== '') {
                $body_summary = strlen($raw) > 500 ? substr($raw, 0, 500) . '... (truncated)' : $raw;
            }
        }

        return array(
            'url'   => $url_params,
            'query' => $query,
            'body'  => $body_summary,
        );
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
