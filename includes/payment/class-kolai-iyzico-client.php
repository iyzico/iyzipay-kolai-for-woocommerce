<?php
/**
 * iyzico Client - Thin wrapper around the bundled iyzipay-php SDK for
 * refund and cancel operations.
 *
 * @package    Kolai
 * @subpackage Kolai/includes/payment
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * iyzico client class.
 */
class Kolai_Iyzico_Client {

    // Option names for the iyzico refund/cancel credentials.
    const OPTION_API_KEY     = 'kolai_iyzico_api_key';
    const OPTION_SECRET_KEY  = 'kolai_iyzico_secret_key';
    const OPTION_ENVIRONMENT = 'kolai_iyzico_environment';

    const ENV_SANDBOX    = 'sandbox';
    const ENV_PRODUCTION = 'production';

    const BASE_URL_SANDBOX    = 'https://sandbox-api.iyzipay.com';
    const BASE_URL_PRODUCTION = 'https://api.iyzipay.com';

    /**
     * Whether iyzico credentials are configured.
     *
     * @return bool
     */
    public function is_configured() {
        return $this->get_api_key() !== '' && $this->get_secret_key() !== '';
    }

    /**
     * Refund a single payment transaction (one basket item), full or partial.
     *
     * @param string $payment_transaction_id iyzico paymentTransactionId.
     * @param float  $price                  Amount to refund.
     * @param string $currency               WooCommerce currency code (e.g. TRY).
     * @param string $conversation_id        Correlation id for traceability.
     * @return array{success:bool,errorCode:?string,errorMessage:?string,raw:?object}
     */
    public function refund($payment_transaction_id, $price, $currency, $conversation_id = '') {
        if (!$this->bootstrap()) {
            return $this->sdk_unavailable_result();
        }

        $request = new \Iyzipay\Request\CreateRefundRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        if ($conversation_id !== '') {
            $request->setConversationId($conversation_id);
        }
        $request->setPaymentTransactionId($payment_transaction_id);
        $request->setPrice($this->format_price($price));
        $request->setCurrency($this->map_currency($currency));

        Kolai_Logger::info('payment', 'iyzico refund request', array(
            'paymentTransactionId' => $payment_transaction_id,
            'price'                => $this->format_price($price),
            'currency'             => $this->map_currency($currency),
            'conversationId'       => $conversation_id,
        ));

        try {
            $result = \Iyzipay\Model\Refund::create($request, $this->build_options());
        } catch (\Throwable $e) {
            Kolai_Logger::error('payment', 'iyzico refund threw exception', array(
                'paymentTransactionId' => $payment_transaction_id,
                'error'                => $e->getMessage(),
            ));
            return array(
                'success'      => false,
                'errorCode'    => null,
                'errorMessage' => $e->getMessage(),
                'raw'          => null,
            );
        }

        return $this->normalize_result($result, 'refund', array(
            'paymentTransactionId' => $payment_transaction_id,
        ));
    }

    /**
     * Cancel an entire payment (same-day, non-partial).
     *
     * @param string $payment_id      iyzico paymentId.
     * @param string $conversation_id Correlation id for traceability.
     * @return array{success:bool,errorCode:?string,errorMessage:?string,raw:?object}
     */
    public function cancel($payment_id, $conversation_id = '') {
        if (!$this->bootstrap()) {
            return $this->sdk_unavailable_result();
        }

        $request = new \Iyzipay\Request\CreateCancelRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        if ($conversation_id !== '') {
            $request->setConversationId($conversation_id);
        }
        $request->setPaymentId($payment_id);

        Kolai_Logger::info('payment', 'iyzico cancel request', array(
            'paymentId'      => $payment_id,
            'conversationId' => $conversation_id,
        ));

        try {
            $result = \Iyzipay\Model\Cancel::create($request, $this->build_options());
        } catch (\Throwable $e) {
            Kolai_Logger::error('payment', 'iyzico cancel threw exception', array(
                'paymentId' => $payment_id,
                'error'     => $e->getMessage(),
            ));
            return array(
                'success'      => false,
                'errorCode'    => null,
                'errorMessage' => $e->getMessage(),
                'raw'          => null,
            );
        }

        return $this->normalize_result($result, 'cancel', array(
            'paymentId' => $payment_id,
        ));
    }

    /**
     * Normalize an iyzipay SDK resource into a simple result array.
     *
     * @param object $result  iyzipay resource (Refund|Cancel).
     * @param string $op      Operation label for logging.
     * @param array  $context Extra log context.
     * @return array
     */
    private function normalize_result($result, $op, $context) {
        $status  = method_exists($result, 'getStatus') ? $result->getStatus() : null;
        $success = ($status === \Iyzipay\Model\Status::SUCCESS);

        $normalized = array(
            'success'      => $success,
            'errorCode'    => method_exists($result, 'getErrorCode') ? $result->getErrorCode() : null,
            'errorMessage' => method_exists($result, 'getErrorMessage') ? $result->getErrorMessage() : null,
            'raw'          => $result,
        );

        if ($success) {
            Kolai_Logger::info('payment', 'iyzico ' . $op . ' succeeded', $context);
        } else {
            Kolai_Logger::error('payment', 'iyzico ' . $op . ' failed', array_merge($context, array(
                'errorCode'    => $normalized['errorCode'],
                'errorMessage' => $normalized['errorMessage'],
            )));
        }

        return $normalized;
    }

    /**
     * Build the iyzipay Options object from stored credentials.
     *
     * @return \Iyzipay\Options
     */
    private function build_options() {
        $options = new \Iyzipay\Options();
        $options->setApiKey($this->get_api_key());
        $options->setSecretKey($this->get_secret_key());
        $options->setBaseUrl($this->get_base_url());
        return $options;
    }

    /**
     * Load the bundled iyzipay-php SDK autoloader (idempotent).
     *
     * @return bool True if the SDK classes are available.
     */
    private function bootstrap() {
        if (class_exists('\Iyzipay\Options')) {
            return true;
        }

        $bootstrap_file = KOLAI_VENDOR_DIR . 'iyzipay-php/IyzipayBootstrap.php';
        if (!file_exists($bootstrap_file)) {
            Kolai_Logger::error('payment', 'iyzipay-php SDK bootstrap not found', array(
                'path' => $bootstrap_file,
            ));
            return false;
        }

        // The bootstrap declares global IyzipayBootstrap/SplClassLoader classes;
        // guard against redeclaration if another plugin already bundled it.
        if (!class_exists('IyzipayBootstrap')) {
            require_once $bootstrap_file;
        }
        IyzipayBootstrap::init(KOLAI_VENDOR_DIR . 'iyzipay-php/src');

        return class_exists('\Iyzipay\Options');
    }

    /**
     * Result returned when the SDK could not be loaded.
     *
     * @return array
     */
    private function sdk_unavailable_result() {
        return array(
            'success'      => false,
            'errorCode'    => null,
            'errorMessage' => __('iyzico SDK yuklenemedi.', 'kolai'),
            'raw'          => null,
        );
    }

    /**
     * Map a WooCommerce currency code to an iyzipay currency constant value.
     * iyzipay uses "TRY" for Turkish Lira; other codes pass through.
     *
     * @param string $currency
     * @return string
     */
    private function map_currency($currency) {
        $currency = strtoupper((string) $currency);
        if ($currency === 'TL') {
            return \Iyzipay\Model\Currency::TL;
        }
        return $currency !== '' ? $currency : \Iyzipay\Model\Currency::TL;
    }

    /**
     * Format a price to the 2-decimal string iyzico expects.
     *
     * @param mixed $price
     * @return string
     */
    private function format_price($price) {
        return number_format((float) $price, 2, '.', '');
    }

    /**
     * @return string
     */
    private function get_api_key() {
        return trim((string) get_option(self::OPTION_API_KEY, ''));
    }

    /**
     * @return string
     */
    private function get_secret_key() {
        return trim((string) get_option(self::OPTION_SECRET_KEY, ''));
    }

    /**
     * @return string
     */
    private function get_environment() {
        $env = (string) get_option(self::OPTION_ENVIRONMENT, self::ENV_SANDBOX);
        return $env === self::ENV_PRODUCTION ? self::ENV_PRODUCTION : self::ENV_SANDBOX;
    }

    /**
     * @return string
     */
    private function get_base_url() {
        return $this->get_environment() === self::ENV_PRODUCTION
            ? self::BASE_URL_PRODUCTION
            : self::BASE_URL_SANDBOX;
    }
}
