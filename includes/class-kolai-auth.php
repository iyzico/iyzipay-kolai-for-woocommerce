<?php
/**
 * Authentication handler for Kolai API
 *
 * Validates HMAC-SHA256 signed requests using the IYZ-TP-v2 authorization scheme.
 *
 * @package    Kolai
 * @subpackage Kolai/includes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kolai Auth class — validates HMAC-SHA256 signatures from the Java system.
 */
class Kolai_Auth {

    // Scope constants matching Java WooCommerceScope enum
    const SCOPE_RETRIEVE_PRODUCT             = 'RETRIEVE_PRODUCT';
    const SCOPE_RETRIEVE_PRODUCTS            = 'RETRIEVE_PRODUCTS';
    const SCOPE_RETRIEVE_PRODUCT_WITH_VARIANTS = 'RETRIEVE_PRODUCT_WITH_VARIANTS';
    const SCOPE_RETRIEVE_SHIPMENT_OPTIONS    = 'RETRIEVE_SHIPMENT_OPTIONS';
    const SCOPE_CREATE_ORDER                 = 'CREATE_ORDER';
    const SCOPE_UPDATE_ORDER_STATUS          = 'UPDATE_ORDER_STATUS';
    const SCOPE_RETRIEVE_ORDER               = 'RETRIEVE_ORDER';
    const SCOPE_RETRIEVE_ORDER_TYPES         = 'RETRIEVE_ORDER_TYPES';
    const SCOPE_RETRIEVE_CONTRACT            = 'RETRIEVE_CONTRACT';
    const SCOPE_RETRIEVE_REVIEWS             = 'RETRIEVE_REVIEWS';
    const SCOPE_RETRIEVE_REVIEW              = 'RETRIEVE_REVIEW';

    /**
     * Authorization header prefix.
     */
    const AUTH_PREFIX = 'IYZ-TP-v2 ';

    /**
     * Maximum allowed clock skew (seconds) for a signed `timestamp`, when the
     * client sends one. Requests older/newer than this are rejected as stale.
     */
    const MAX_TIMESTAMP_SKEW = 300;

    /**
     * How long (seconds) a consumed request salt is remembered to block replays.
     * Bounds the replay window for clients that do not yet sign a timestamp.
     */
    const NONCE_TTL = 600;

    /**
     * Object-cache / transient group for consumed nonces.
     */
    const NONCE_GROUP = 'kolai_auth_nonce';

    /**
     * Validate the HMAC-SHA256 signed request.
     *
     * @param WP_REST_Request $request        The incoming REST request.
     * @param string          $expected_scope  The scope constant expected for this endpoint.
     * @throws Kolai_Unauthorized_Exception On any validation failure.
     */
    public static function validate($request, $expected_scope) {
        Kolai_Logger::debug('auth', 'Auth validation started', array(
            'expected_scope' => $expected_scope,
        ));

        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header) || strpos($auth_header, self::AUTH_PREFIX) !== 0) {
            Kolai_Logger::warning('auth', 'Missing or invalid authorization header', array(
                'header_present' => !empty($auth_header),
            ));
            throw new Kolai_Unauthorized_Exception('Missing or invalid authorization header');
        }

        // Detect multi-valued Authorization (RFC 7230 §3.2.2 joins repeated
        // headers with ", "). Most often caused by a Postman client that
        // both runs a pre-request script AND has Auth tab set to Bearer/Basic
        // — two Authorization headers leave the client and PHP joins them.
        // Base64 alphabet is [A-Za-z0-9+/=] so a comma-space pair never
        // appears in a valid encoded payload.
        if (strpos($auth_header, ', ') !== false || stripos($auth_header, 'Bearer ') !== false) {
            Kolai_Logger::warning('auth', 'Duplicate Authorization headers detected', array(
                'parts'         => substr_count($auth_header, ', ') + 1,
                'header_length' => strlen($auth_header),
            ));
            throw new Kolai_Unauthorized_Exception(
                'Duplicate Authorization headers detected. Send only the IYZ-TP-v2 header (Postman: request -> Authorization tab -> No Auth).'
            );
        }

        // Strip prefix and base64-decode
        $encoded = substr($auth_header, strlen(self::AUTH_PREFIX));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            Kolai_Logger::warning('auth', 'Invalid authorization encoding (base64 decode failed)', array(
                'encoded_length' => strlen($encoded),
            ));
            throw new Kolai_Unauthorized_Exception('Invalid authorization encoding');
        }

        // Parse key-value pairs: clientId:{val}&salt:{val}&scope:{val}&signature:{val}
        $params = array();
        foreach (explode('&', $decoded) as $pair) {
            $pos = strpos($pair, ':');
            if ($pos === false) {
                Kolai_Logger::warning('auth', 'Malformed authorization payload', array(
                    'pair' => $pair,
                ));
                throw new Kolai_Unauthorized_Exception('Malformed authorization payload');
            }
            $params[substr($pair, 0, $pos)] = substr($pair, $pos + 1);
        }

        $required_keys = array('clientId', 'salt', 'scope', 'signature');
        foreach ($required_keys as $key) {
            if (!isset($params[$key]) || $params[$key] === '') {
                Kolai_Logger::warning('auth', 'Missing authorization parameter: ' . $key, array(
                    'present_keys' => array_keys($params),
                ));
                throw new Kolai_Unauthorized_Exception('Missing authorization parameter: ' . $key);
            }
        }

        // Load both credentials and FAIL CLOSED unless both are configured and
        // non-empty. An empty secret would let anyone forge a valid HMAC with the
        // known empty key; an empty client id can never match a real one.
        $stored_api_key = (string) get_option('kolai_api_key', '');
        $stored_secret  = (string) get_option('kolai_secret_key', '');
        if ($stored_api_key === '' || $stored_secret === '') {
            Kolai_Logger::warning('auth', 'Server credentials not configured', array(
                'api_key_empty' => ($stored_api_key === ''),
                'secret_empty'  => ($stored_secret === ''),
            ));
            throw new Kolai_Unauthorized_Exception('Server credentials not configured');
        }

        // Validate clientId in constant time. Never log the received value.
        if (!hash_equals($stored_api_key, (string) $params['clientId'])) {
            Kolai_Logger::warning('auth', 'Invalid client credentials', array(
                'received_length' => strlen((string) $params['clientId']),
            ));
            throw new Kolai_Unauthorized_Exception('Invalid client credentials');
        }

        // Validate scope
        if ($params['scope'] !== $expected_scope) {
            Kolai_Logger::warning('auth', 'Invalid scope', array(
                'expected' => $expected_scope,
                'received' => $params['scope'],
            ));
            throw new Kolai_Unauthorized_Exception('Invalid scope');
        }

        // Build URI path (strip query string) — matches Java URI.getPath()
        $uri_path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $qpos = strpos($uri_path, '?');
        if ($qpos !== false) {
            $uri_path = substr($uri_path, 0, $qpos);
        }

        // Raw request body (empty string for GET)
        $body = $request->get_body();

        // Reconstruct and verify HMAC-SHA256 signature.
        //
        // Replay protection (backward compatible): when the client sends a signed
        // `timestamp` (unix epoch seconds), it is appended to the HMAC message so
        // it cannot be tampered with, and the request is rejected if it falls
        // outside MAX_TIMESTAMP_SKEW. Clients that do not yet send a timestamp are
        // still protected by single-use salt consumption below (bounded by
        // NONCE_TTL); a deprecation notice is logged so the signer can be updated.
        $timestamp = isset($params['timestamp']) ? (string) $params['timestamp'] : '';

        $payload = $params['salt'] . $params['scope'] . $uri_path . $body;
        if ($timestamp !== '') {
            $payload .= $timestamp;
        }
        $expected_signature = hash_hmac('sha256', $payload, $stored_secret);

        if (!hash_equals($expected_signature, $params['signature'])) {
            Kolai_Logger::warning('auth', 'Invalid signature', array(
                'uri_path'    => $uri_path,
                'body_length' => strlen($body),
                'salt'        => $params['salt'],
                'scope'       => $params['scope'],
                'has_timestamp' => ($timestamp !== ''),
                // Never log the actual signatures or the secret key — that defeats the purpose.
                'signature_match' => false,
            ));
            throw new Kolai_Unauthorized_Exception('Invalid signature');
        }

        // Signature is authentic: the timestamp (if any) is now trustworthy.
        if ($timestamp !== '') {
            if (!is_numeric($timestamp)) {
                throw new Kolai_Unauthorized_Exception('Invalid timestamp');
            }
            $skew = abs(time() - (int) $timestamp);
            if ($skew > self::MAX_TIMESTAMP_SKEW) {
                Kolai_Logger::warning('auth', 'Stale request rejected', array(
                    'skew_seconds' => $skew,
                    'max_skew'     => self::MAX_TIMESTAMP_SKEW,
                ));
                throw new Kolai_Unauthorized_Exception('Request timestamp outside allowed window');
            }
        } else {
            Kolai_Logger::warning('auth', 'Request signed without a timestamp (deprecated)', array(
                'scope'    => $params['scope'],
                'uri_path' => $uri_path,
            ));
        }

        // Single-use salt: an exact replay reuses the same (already signed) salt.
        // Consume it only after the signature is verified, so invalid traffic can
        // never poison the nonce store.
        if (!self::consume_nonce($params['clientId'] . '|' . $params['salt'])) {
            Kolai_Logger::warning('auth', 'Replayed request rejected', array(
                'scope'    => $params['scope'],
                'uri_path' => $uri_path,
            ));
            throw new Kolai_Unauthorized_Exception('Replayed request rejected');
        }

        Kolai_Logger::info('auth', 'Auth validation passed', array(
            'scope'    => $params['scope'],
            'uri_path' => $uri_path,
        ));
    }

    /**
     * Atomically consume a one-time request identifier (the signed salt). Returns
     * true on first use, false if the identifier was already seen within NONCE_TTL.
     *
     * Uses an atomic object-cache add when a persistent cache is present
     * (Redis/Memcached); otherwise falls back to a transient. The transient path
     * has a narrow check-then-set window but still blocks practical replays.
     *
     * @param string $identifier
     * @return bool
     */
    private static function consume_nonce($identifier) {
        $key = 'kolai_nonce_' . md5($identifier);

        if (wp_using_ext_object_cache()) {
            return (bool) wp_cache_add($key, 1, self::NONCE_GROUP, self::NONCE_TTL);
        }

        $transient = 'kolai_n_' . md5($identifier);
        if (get_transient($transient) !== false) {
            return false;
        }
        set_transient($transient, 1, self::NONCE_TTL);
        return true;
    }

    /**
     * Return a permission_callback closure for the given scope.
     *
     * @param string $scope One of the SCOPE_* constants.
     * @return callable
     */
    public static function permission_callback($scope) {
        return function ($request) use ($scope) {
            try {
                self::validate($request, $scope);
                return true;
            } catch (Kolai_Unauthorized_Exception $e) {
                return new WP_Error(
                    'kolai_unauthorized',
                    $e->getMessage(),
                    array('status' => 401)
                );
            }
        };
    }
}
