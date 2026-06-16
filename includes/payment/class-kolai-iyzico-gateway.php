<?php
/**
 * iyzico Gateway - A lightweight WooCommerce payment gateway whose only job is
 * to back the native "Refund" button for Kolai orders. It is hidden from
 * checkout; orders are created through the Kolai REST API with this gateway's id.
 *
 * @package    Kolai
 * @subpackage Kolai/includes/payment
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * iyzico gateway class.
 */
class Kolai_Iyzico_Gateway extends WC_Payment_Gateway {

    /**
     * Refund service.
     *
     * @var Kolai_Refund_Service
     */
    private $refund_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = Kolai_Refund_Service::PAYMENT_METHOD; // 'kolai-app'
        $this->method_title       = __('Kolai App (iyzico)', 'kolai');
        $this->method_description = __('Kolai API ile olusturulan siparisler icin iyzico iade entegrasyonu. Odeme adimi sunmaz; yalnizca iade islemlerini yonetir.', 'kolai');
        $this->has_fields         = false;
        $this->title              = __('Kolai App', 'kolai');

        // Enable refunds via this gateway.
        $this->supports = array('refunds');

        $this->refund_service = new Kolai_Refund_Service();
    }

    /**
     * Never offered at checkout — orders are created through the Kolai API.
     *
     * @return bool
     */
    public function is_available() {
        return false;
    }

    /**
     * Process a refund triggered from the WooCommerce order screen.
     *
     * @param int        $order_id
     * @param float|null $amount
     * @param string     $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        if ($amount === null) {
            return new WP_Error('kolai_refund_error', __('Iade tutari belirtilmedi.', 'kolai'));
        }

        $result = $this->refund_service->refund_order($order_id, $amount, $reason);
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }
}
