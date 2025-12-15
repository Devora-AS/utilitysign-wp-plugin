<?php
/**
 * Order AJAX Actions
 * 
 * Handles AJAX requests for order operations.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Ajax;

use UtilitySign\Services\OrderService;
use UtilitySign\Core\ApiClient;

defined('ABSPATH') || exit;

class OrderActions {
    /**
     * Order Service instance
     * 
     * @var OrderService
     */
    private $order_service;
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $api_client = new ApiClient();
        $this->order_service = new OrderService($api_client);
        
        // Register AJAX handlers
        add_action('wp_ajax_utilitysign_submit_order', array($this, 'submit_order'));
        add_action('wp_ajax_nopriv_utilitysign_submit_order', array($this, 'submit_order'));
        
        add_action('wp_ajax_utilitysign_get_order_status', array($this, 'get_order_status'));
        add_action('wp_ajax_nopriv_utilitysign_get_order_status', array($this, 'get_order_status'));
        
        add_action('wp_ajax_utilitysign_get_signing_status', array($this, 'get_signing_status'));
        add_action('wp_ajax_nopriv_utilitysign_get_signing_status', array($this, 'get_signing_status'));
    }
    
    /**
     * Submit order
     * 
     * @since 1.0.0
     */
    public function submit_order() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Prepare order data from form submission
        $order_data = $this->order_service->prepare_order_data($_POST);
        
        // Validate order data
        $validation = $this->order_service->validate_order_data($order_data);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
            return;
        }
        
        // Submit order to backend
        $result = $this->order_service->submit_order($order_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get order status
     * 
     * @since 1.0.0
     */
    public function get_order_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        
        if (empty($order_id)) {
            wp_send_json_error('Order ID is required');
            return;
        }
        
        // Get order status from backend
        $result = $this->order_service->get_order_status($order_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get signing status
     * 
     * @since 1.0.0
     */
    public function get_signing_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $signing_id = sanitize_text_field($_POST['signing_id'] ?? '');
        
        if (empty($signing_id)) {
            wp_send_json_error('Signing ID is required');
            return;
        }
        
        // Get signing status from backend
        $result = $this->order_service->get_signing_status($signing_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
}

