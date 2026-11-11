<?php
/**
 * iyzico Curl - adds timeouts to the bundled iyzipay-php SDK's curl calls
 *
 * Loaded only after the SDK autoloader is registered, because it extends an
 * SDK class.
 *
 * @package    Kolai
 * @subpackage Kolai/includes/payment
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Curl transport for iyzipay-php with connect and total timeouts.
 *
 * The SDK's own transport sets no timeout, so a slow or unreachable iyzico
 * endpoint holds the PHP worker until max_execution_time kills the request.
 */
class Kolai_Iyzico_Curl extends \Iyzipay\Curl {

    /**
     * Seconds allowed to establish the connection.
     */
    const CONNECT_TIMEOUT = 10;

    /**
     * Seconds allowed for the whole request.
     */
    const TIMEOUT = 30;

    /**
     * @param string $url
     * @param array  $options
     * @return string|false
     */
    public function exec($url, $options) {
        $options += array(
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => static::TIMEOUT,
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if ($response === false) {
            Kolai_Logger::error('payment', 'iyzico HTTP request failed', array(
                'curl_errno' => curl_errno($ch),
                'curl_error' => curl_error($ch),
                'timed_out'  => curl_errno($ch) === CURLE_OPERATION_TIMEDOUT,
            ));
        }

        // No curl_close(): the SDK does not close handles either, and the call
        // is a deprecated no-op since PHP 8.5.
        return $response;
    }
}
