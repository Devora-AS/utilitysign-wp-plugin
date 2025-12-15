<?php
/**
 * Order Service
 * 
 * Handles order-related operations for WordPress plugin.
 * Submits orders to Azure backend API.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Services;

use UtilitySign\Core\ApiClient;

defined('ABSPATH') || exit;

class OrderService {
    /**
     * API Client instance
     * 
     * @var ApiClient
     */
    private $api_client;
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     * @param ApiClient $api_client API client instance
     */
    public function __construct($api_client) {
        $this->api_client = $api_client;
    }
    
    /**
     * Submit new order
     * 
     * @since 1.0.0
     * @param array $order_data Order data
     * @return array|WP_Error Order response or error
     */
    public function submit_order($order_data) {
        // Validate required fields
        $required_fields = array('productId', 'customerEmail', 'customerName');
        foreach ($required_fields as $field) {
            if (empty($order_data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field missing: %s', 'utilitysign'), $field)
                );
            }
        }
        
        // Validate email
        if (!is_email($order_data['customerEmail'])) {
            return new \WP_Error(
                'invalid_email',
                __('Invalid email address', 'utilitysign')
            );
        }
        
        // Submit to backend
        return $this->api_client->submit_order($order_data);
    }
    
    /**
     * Get order status
     * 
     * @since 1.0.0
     * @param string $order_id Order ID
     * @return array|WP_Error Order status or error
     */
    public function get_order_status($order_id) {
        if (empty($order_id)) {
            return new \WP_Error(
                'missing_order_id',
                __('Order ID is required', 'utilitysign')
            );
        }
        
        return $this->api_client->get_order_status($order_id);
    }
    
    /**
     * Get signing status
     * 
     * @since 1.0.0
     * @param string $signing_id Signing request ID
     * @return array|WP_Error Signing status or error
     */
    public function get_signing_status($signing_id) {
        if (empty($signing_id)) {
            return new \WP_Error(
                'missing_signing_id',
                __('Signing ID is required', 'utilitysign')
            );
        }
        
        return $this->api_client->get_signing_status($signing_id);
    }
    
    /**
     * Prepare order data from form submission
     * 
     * @since 1.0.0
     * @param array $form_data Raw form data
     * @return array Formatted order data
     */
    public function prepare_order_data($form_data) {
        return array(
            'productId' => sanitize_text_field($form_data['product_id'] ?? ''),
            'customerEmail' => sanitize_email($form_data['customer_email'] ?? ''),
            'customerName' => sanitize_text_field($form_data['customer_name'] ?? ''),
            'customerPhone' => sanitize_text_field($form_data['customer_phone'] ?? ''),
            'customerAddress' => sanitize_textarea_field($form_data['customer_address'] ?? ''),
            'customerPostalCode' => sanitize_text_field($form_data['customer_postal_code'] ?? ''),
            'customerCity' => sanitize_text_field($form_data['customer_city'] ?? ''),
            'meterNumber' => sanitize_text_field($form_data['meter_number'] ?? ''),
            'installationAddress' => sanitize_textarea_field($form_data['installation_address'] ?? ''),
            'notes' => sanitize_textarea_field($form_data['notes'] ?? ''),
        );
    }
    
    /**
     * Validate order data
     * 
     * @since 1.0.0
     * @param array $order_data Order data to validate
     * @return array|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_order_data($order_data) {
        $errors = array();
        
        // Required fields
        if (empty($order_data['productId'])) {
            $errors[] = __('Product is required', 'utilitysign');
        }
        
        if (empty($order_data['customerEmail'])) {
            $errors[] = __('Email is required', 'utilitysign');
        } elseif (!is_email($order_data['customerEmail'])) {
            $errors[] = __('Invalid email address', 'utilitysign');
        }
        
        if (empty($order_data['customerName'])) {
            $errors[] = __('Name is required', 'utilitysign');
        }
        
        if (empty($order_data['meterNumber'])) {
            $errors[] = __('Meter number is required', 'utilitysign');
        }
        
        // Return errors if any
        if (!empty($errors)) {
            return new \WP_Error(
                'validation_failed',
                implode(', ', $errors)
            );
        }
        
        return true;
    }
}

