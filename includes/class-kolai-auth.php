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

    /**
     * Authorization header prefix.
     */
    const AUTH_PREFIX = 'IYZ-TP-v2 ';

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

        // Validate clientId
        $stored_api_key = get_option('kolai_api_key', '');
        if ($stored_api_key === '' || $params['clientId'] !== $stored_api_key) {
            Kolai_Logger::warning('auth', 'Invalid client credentials', array(
                'stored_empty'    => ($stored_api_key === ''),
                'received_client' => $params['clientId'],
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

        // Reconstruct and verify HMAC-SHA256 signature
        $secret = get_option('kolai_secret_key', '');
        $payload = $params['salt'] . $params['scope'] . $uri_path . $body;
        $expected_signature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected_signature, $params['signature'])) {
            Kolai_Logger::warning('auth', 'Invalid signature', array(
                'uri_path'    => $uri_path,
                'body_length' => strlen($body),
                'salt'        => $params['salt'],
                'scope'       => $params['scope'],
                // Never log the actual signatures or the secret key — that defeats the purpose.
                'signature_match' => false,
            ));
            throw new Kolai_Unauthorized_Exception('Invalid signature');
        }

        Kolai_Logger::info('auth', 'Auth validation passed', array(
            'scope'    => $params['scope'],
            'uri_path' => $uri_path,
        ));
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
