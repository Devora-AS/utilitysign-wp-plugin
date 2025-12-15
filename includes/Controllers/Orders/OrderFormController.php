<?php

namespace UtilitySign\Controllers\Orders;

use UtilitySign\Traits\Base;
use UtilitySign\Core\ApiClient;
use UtilitySign\Services\CriiptoService;

/**
 * Order Form Controller
 * Handles order form submissions and validation
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class OrderFormController {
    use Base;

    /**
     * API Client instance
     * 
     * @var ApiClient
     */
    private $api_client;

    /**
     * Criipto Service instance
     * 
     * @var CriiptoService
     */
    private $criipto_service;

    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $this->api_client = new ApiClient();
        $this->criipto_service = new CriiptoService();
    }

    /**
     * Initialize the controller
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('wp_ajax_utilitysign_submit_order', [$this, 'submit_order']);
        add_action('wp_ajax_nopriv_utilitysign_submit_order', [$this, 'submit_order']);
        add_action('wp_ajax_utilitysign_validate_field', [$this, 'validate_field']);
        add_action('wp_ajax_nopriv_utilitysign_validate_field', [$this, 'validate_field']);
    }

    /**
     * Submit order form
     * 
     * @since 1.0.0
     */
    public function submit_order() {
        // Verify nonce
        if (!check_ajax_referer('utilitysign_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'utilitysign'),
            ], 403);
        }

        // Get and sanitize form data
        $product_id = sanitize_text_field($_POST['product_id'] ?? '');
        $product_title = sanitize_text_field($_POST['product_title'] ?? '');
        $selected_variation = sanitize_text_field($_POST['selected_variation'] ?? '');
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $terms_accepted = filter_var($_POST['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $total_price = floatval($_POST['total_price'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'NOK');
        $billing_cycle = sanitize_text_field($_POST['billing_cycle'] ?? 'monthly');

        // Validate required fields
        $errors = [];

        if (empty($product_id)) {
            $errors['product_id'] = __('Product ID is required', 'utilitysign');
        }

        if (empty($customer_name)) {
            $errors['customer_name'] = __('Name is required', 'utilitysign');
        }

        if (empty($customer_email)) {
            $errors['customer_email'] = __('Email is required', 'utilitysign');
        } elseif (!is_email($customer_email)) {
            $errors['customer_email'] = __('Invalid email format', 'utilitysign');
        }

        if (empty($customer_phone)) {
            $errors['customer_phone'] = __('Phone is required', 'utilitysign');
        }

        // Check if terms acceptance is required
        $product_post = get_post($product_id);
        if ($product_post) {
            $require_acceptance = get_post_meta($product_id, '_product_require_acceptance', true);
            if ($require_acceptance && !$terms_accepted) {
                $errors['terms_accepted'] = __('You must accept the terms and conditions', 'utilitysign');
            }
        } else {
            $errors['product_id'] = __('Product not found', 'utilitysign');
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => __('Validation failed', 'utilitysign'),
                'errors' => $errors,
            ], 400);
        }

        // Create order post
        $order_data = [
            'post_type' => 'utilitysign_order',
            'post_title' => sprintf(__('Order from %s', 'utilitysign'), $customer_name),
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 0,
        ];

        $order_id = wp_insert_post($order_data);

        if (is_wp_error($order_id)) {
            wp_send_json_error([
                'message' => __('Failed to create order', 'utilitysign'),
            ], 500);
        }

        // Save order meta data
        update_post_meta($order_id, '_order_product_id', $product_id);
        update_post_meta($order_id, '_order_product_title', $product_title);
        update_post_meta($order_id, '_order_selected_variation', $selected_variation);
        update_post_meta($order_id, '_order_customer_name', $customer_name);
        update_post_meta($order_id, '_order_customer_email', $customer_email);
        update_post_meta($order_id, '_order_customer_phone', $customer_phone);
        update_post_meta($order_id, '_order_terms_accepted', $terms_accepted ? '1' : '0');
        update_post_meta($order_id, '_order_total_price', $total_price);
        update_post_meta($order_id, '_order_currency', $currency);
        update_post_meta($order_id, '_order_billing_cycle', $billing_cycle);
        update_post_meta($order_id, '_order_status', 'pending');
        update_post_meta($order_id, '_order_created_at', current_time('mysql'));

        // Save custom fields
        $custom_fields = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'custom_') === 0) {
                $field_name = substr($key, 7); // Remove 'custom_' prefix
                $custom_fields[$field_name] = sanitize_text_field($value);
            }
        }
        if (!empty($custom_fields)) {
            update_post_meta($order_id, '_order_custom_fields', $custom_fields);
        }

        // Submit order to Azure backend
        $azure_result = $this->submit_to_azure($order_id, [
            'productId' => $product_id,
            'customerName' => $customer_name,
            'customerEmail' => $customer_email,
            'customerPhone' => $customer_phone,
            'totalPrice' => $total_price,
            'currency' => $currency,
            'billingCycle' => $billing_cycle,
            'selectedVariation' => $selected_variation,
            'customFields' => $custom_fields,
        ]);

        if (is_wp_error($azure_result)) {
            // Azure submission failed - mark order as failed but don't delete it
            update_post_meta($order_id, '_order_status', 'failed');
            update_post_meta($order_id, '_order_error_message', $azure_result->get_error_message());
            
            wp_send_json_error([
                'message' => sprintf(__('Order created locally but Azure submission failed: %s', 'utilitysign'), $azure_result->get_error_message()),
                'order_id' => $order_id,
            ], 500);
        }

        // Store Azure order ID
        $azure_order_id = $azure_result['orderId'] ?? null;
        if ($azure_order_id) {
            update_post_meta($order_id, '_order_azure_id', $azure_order_id);
        }

        // Update order status to processing
        update_post_meta($order_id, '_order_status', 'processing');

        // Initiate Criipto signing if enabled
        $signing_url = null;
        $signing_id = null;
        
        $settings = get_option('utilitysign_settings', []);
        $criipto_enabled = $settings['criipto']['enabled'] ?? false;

        if ($criipto_enabled) {
            $signing_result = $this->initiate_signing($order_id, $azure_order_id, $customer_email, $customer_name);
            
            if (is_wp_error($signing_result)) {
                // Signing initiation failed - update order status
                update_post_meta($order_id, '_order_status', 'signing_failed');
                update_post_meta($order_id, '_order_signing_error', $signing_result->get_error_message());
            } else {
                $signing_url = $signing_result['signing_url'] ?? null;
                $signing_id = $signing_result['signing_id'] ?? null;
                
                if ($signing_id) {
                    update_post_meta($order_id, '_order_signing_id', $signing_id);
                    update_post_meta($order_id, '_order_status', 'awaiting_signature');
                }
            }
        }

        // Log the order creation
        do_action('utilitysign_order_created', $order_id, [
            'product_id' => $product_id,
            'customer_email' => $customer_email,
            'total_price' => $total_price,
            'azure_order_id' => $azure_order_id,
            'signing_id' => $signing_id,
        ]);

        // Return success response
        wp_send_json_success([
            'message' => __('Order created successfully', 'utilitysign'),
            'order_id' => $order_id,
            'azure_order_id' => $azure_order_id,
            'signing_url' => $signing_url,
            'signing_id' => $signing_id,
            'order_data' => [
                'id' => $order_id,
                'azure_id' => $azure_order_id,
                'product_id' => $product_id,
                'product_title' => $product_title,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'total_price' => $total_price,
                'currency' => $currency,
                'status' => get_post_meta($order_id, '_order_status', true),
                'signing_url' => $signing_url,
            ],
        ]);
    }

    /**
     * Validate a single field
     * 
     * @since 1.0.0
     */
    public function validate_field() {
        // Verify nonce
        if (!check_ajax_referer('utilitysign_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed', 'utilitysign'),
            ], 403);
        }

        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        $field_value = sanitize_text_field($_POST['field_value'] ?? '');
        $field_type = sanitize_text_field($_POST['field_type'] ?? 'text');

        $error = null;

        switch ($field_name) {
            case 'customer_email':
                if (!is_email($field_value)) {
                    $error = __('Invalid email format', 'utilitysign');
                }
                break;

            case 'customer_phone':
                if (!preg_match('/^[\d\s\+\-\(\)]+$/', $field_value)) {
                    $error = __('Invalid phone format', 'utilitysign');
                }
                break;

            default:
                // Apply custom validation if needed
                $error = apply_filters('utilitysign_validate_field', null, $field_name, $field_value, $field_type);
                break;
        }

        if ($error) {
            wp_send_json_error([
                'field' => $field_name,
                'error' => $error,
            ]);
        }

        wp_send_json_success([
            'field' => $field_name,
            'valid' => true,
        ]);
    }

    /**
     * Get product data for order form
     * 
     * @since 1.0.0
     * @param int $product_id
     * @return array|null
     */
    public function get_product_data($product_id) {
        $product = get_post($product_id);

        if (!$product || $product->post_type !== 'utilitysign_product') {
            return null;
        }

        // Get product meta
        $base_price = get_post_meta($product_id, '_product_base_price', true);
        $currency = get_post_meta($product_id, '_product_currency', true);
        $billing_cycle = get_post_meta($product_id, '_product_billing_cycle', true);
        $category = get_post_meta($product_id, '_product_category', true);
        $variations = get_post_meta($product_id, '_product_variations', true);
        $terms_content = get_post_meta($product_id, '_product_terms_content', true);
        $require_acceptance = get_post_meta($product_id, '_product_require_acceptance', true);

        return [
            'id' => $product_id,
            'title' => $product->post_title,
            'description' => $product->post_content,
            'base_price' => floatval($base_price),
            'currency' => $currency ?: 'NOK',
            'billing_cycle' => $billing_cycle ?: 'monthly',
            'category' => $category,
            'variations' => is_array($variations) ? $variations : [],
            'terms_content' => $terms_content,
            'require_acceptance' => $require_acceptance === '1',
        ];
    }

    /**
     * Submit order to Azure backend
     * 
     * @since 1.0.0
     * @param int $wp_order_id WordPress order ID
     * @param array $order_data Order data to submit
     * @return array|WP_Error Azure response or error
     */
    private function submit_to_azure($wp_order_id, $order_data) {
        try {
            // Add WordPress order ID to metadata
            $order_data['wpOrderId'] = $wp_order_id;
            
            // Submit to Azure backend
            $result = $this->api_client->submit_order($order_data);
            
            if (is_wp_error($result)) {
                // Log the error
                error_log('Azure order submission failed for WP order ' . $wp_order_id . ': ' . $result->get_error_message());
                return $result;
            }
            
            // Log successful submission
            do_action('utilitysign_order_submitted_to_azure', $wp_order_id, $result);
            
            return $result;
        } catch (\Exception $e) {
            error_log('Exception during Azure submission for WP order ' . $wp_order_id . ': ' . $e->getMessage());
            return new \WP_Error(
                'azure_submission_exception',
                $e->getMessage()
            );
        }
    }

    /**
     * Initiate Criipto signing for order
     * 
     * @since 1.0.0
     * @param int $wp_order_id WordPress order ID
     * @param string $azure_order_id Azure order ID
     * @param string $signer_email Signer email
     * @param string $signer_name Signer name
     * @return array|WP_Error Signing result or error
     */
    private function initiate_signing($wp_order_id, $azure_order_id, $signer_email, $signer_name) {
        try {
            // Generate document/contract for signing
            // In a real implementation, this would generate or retrieve the PDF document
            $document_id = 'doc_' . $azure_order_id;
            
            // Initiate signing via Criipto service
            $signing_result = $this->criipto_service->initiate_signing([
                'documentId' => $document_id,
                'orderId' => $azure_order_id,
                'wpOrderId' => $wp_order_id,
                'signerEmail' => $signer_email,
                'signerName' => $signer_name,
                'webhookUrl' => home_url('/wp-json/utilitysign/v1/webhooks/signing-complete'),
                'redirectUrl' => home_url('/signing-complete/?order=' . $wp_order_id),
            ]);
            
            if (is_wp_error($signing_result)) {
                error_log('Criipto signing initiation failed for WP order ' . $wp_order_id . ': ' . $signing_result->get_error_message());
                return $signing_result;
            }
            
            // Log successful signing initiation
            do_action('utilitysign_signing_initiated', $wp_order_id, $signing_result);
            
            return $signing_result;
        } catch (\Exception $e) {
            error_log('Exception during signing initiation for WP order ' . $wp_order_id . ': ' . $e->getMessage());
            return new \WP_Error(
                'signing_initiation_exception',
                $e->getMessage()
            );
        }
    }
}

