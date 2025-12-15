<?php
/**
 * Product AJAX Actions
 * 
 * Handles AJAX requests for product operations.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Ajax;

use UtilitySign\Services\ProductService;
use UtilitySign\Core\ApiClient;

defined('ABSPATH') || exit;

class ProductActions {
    /**
     * Product Service instance
     * 
     * @var ProductService
     */
    private $product_service;
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $api_client = new ApiClient();
        $this->product_service = new ProductService($api_client);
        
        // Register AJAX handlers
        add_action('wp_ajax_utilitysign_get_products', array($this, 'get_products'));
        add_action('wp_ajax_nopriv_utilitysign_get_products', array($this, 'get_products'));
        
        add_action('wp_ajax_utilitysign_get_product', array($this, 'get_product'));
        add_action('wp_ajax_nopriv_utilitysign_get_product', array($this, 'get_product'));
        
        add_action('wp_ajax_utilitysign_get_product_terms', array($this, 'get_product_terms'));
        add_action('wp_ajax_nopriv_utilitysign_get_product_terms', array($this, 'get_product_terms'));
    }
    
    /**
     * Get all products
     * 
     * @since 1.0.0
     */
    public function get_products() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Get products from backend
        $products = $this->product_service->get_active_products();
        
        if (is_wp_error($products)) {
            wp_send_json_error($products->get_error_message());
            return;
        }
        
        // Format products for frontend
        $formatted_products = array_map(
            array($this->product_service, 'format_product'),
            $products
        );
        
        wp_send_json_success($formatted_products);
    }
    
    /**
     * Get single product
     * 
     * @since 1.0.0
     */
    public function get_product() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $product_id = sanitize_text_field($_POST['product_id'] ?? '');
        
        if (empty($product_id)) {
            wp_send_json_error('Product ID is required');
            return;
        }
        
        // Get product from backend
        $product = $this->product_service->get_product($product_id);
        
        if (is_wp_error($product)) {
            wp_send_json_error($product->get_error_message());
            return;
        }
        
        if ($product === null) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Format product for frontend
        $formatted_product = $this->product_service->format_product($product);
        
        wp_send_json_success($formatted_product);
    }
    
    /**
     * Get product terms
     * 
     * @since 1.0.0
     */
    public function get_product_terms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'utilitysign_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $product_id = sanitize_text_field($_POST['product_id'] ?? '');
        
        if (empty($product_id)) {
            wp_send_json_error('Product ID is required');
            return;
        }
        
        // Get product terms from backend
        $terms = $this->product_service->get_product_terms($product_id);
        
        if (is_wp_error($terms)) {
            wp_send_json_error($terms->get_error_message());
            return;
        }
        
        wp_send_json_success($terms);
    }
}

