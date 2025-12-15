<?php
/**
 * Product Service
 * 
 * Handles product-related operations for WordPress plugin.
 * Fetches products from Azure backend API.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Services;

use UtilitySign\Core\ApiClient;

defined('ABSPATH') || exit;

class ProductService {
    /**
     * API Client instance
     * 
     * @var ApiClient
     */
    private $api_client;
    
    /**
     * Cached products
     * 
     * @var array|null
     */
    private $cached_products = null;
    
    /**
     * Cache expiration time (seconds)
     * 
     * @var int
     */
    private $cache_duration = 300; // 5 minutes
    
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
     * Get all products from backend
     * 
     * @since 1.0.0
     * @param bool $force_refresh Force refresh from API (bypass cache)
     * @return array|WP_Error Array of products or error
     */
    public function get_products($force_refresh = false) {
        // Check cache first
        if (!$force_refresh && $this->cached_products !== null) {
            $cached_time = get_transient('utilitysign_products_cache_time');
            if ($cached_time && (time() - $cached_time) < $this->cache_duration) {
                return $this->cached_products;
            }
        }
        
        // Fetch from API
        $response = $this->api_client->get_products();
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Extract products from response
        $products = isset($response['products']) ? $response['products'] : array();
        
        // Cache the results
        $this->cached_products = $products;
        set_transient('utilitysign_products_cache_time', time(), $this->cache_duration);
        
        return $products;
    }
    
    /**
     * Get single product by ID
     * 
     * @since 1.0.0
     * @param string $product_id Product ID
     * @return array|null|WP_Error Product data, null if not found, or error
     */
    public function get_product($product_id) {
        $products = $this->get_products();
        
        if (is_wp_error($products)) {
            return $products;
        }
        
        foreach ($products as $product) {
            if (isset($product['id']) && $product['id'] === $product_id) {
                return $product;
            }
        }
        
        return null;
    }
    
    /**
     * Get product terms (contract terms)
     * 
     * @since 1.0.0
     * @param string $product_id Product ID
     * @return array|WP_Error Product terms or error
     */
    public function get_product_terms($product_id) {
        return $this->api_client->get_product_terms($product_id);
    }
    
    /**
     * Get products by category
     * 
     * @since 1.0.0
     * @param string $category Category name (Spot, Fixed, Variable)
     * @return array|WP_Error Filtered products or error
     */
    public function get_products_by_category($category) {
        $products = $this->get_products();
        
        if (is_wp_error($products)) {
            return $products;
        }
        
        return array_filter($products, function($product) use ($category) {
            return isset($product['category']) && $product['category'] === $category;
        });
    }
    
    /**
     * Get active products only
     * 
     * @since 1.0.0
     * @return array|WP_Error Active products or error
     */
    public function get_active_products() {
        $products = $this->get_products();
        
        if (is_wp_error($products)) {
            return $products;
        }
        
        return array_filter($products, function($product) {
            return isset($product['isActive']) && $product['isActive'] === true;
        });
    }
    
    /**
     * Clear product cache
     * 
     * @since 1.0.0
     */
    public function clear_cache() {
        $this->cached_products = null;
        delete_transient('utilitysign_products_cache_time');
    }
    
    /**
     * Format product for display
     * 
     * @since 1.0.0
     * @param array $product Raw product data
     * @return array Formatted product data
     */
    public function format_product($product) {
        return array(
            'id' => $product['id'] ?? '',
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => $product['price'] ?? 0,
            'category' => $product['category'] ?? '',
            'sku' => $product['sku'] ?? '',
            'isActive' => $product['isActive'] ?? false,
            'isDigital' => $product['isDigital'] ?? false,
            'formatted_price' => number_format($product['price'] ?? 0, 2, ',', ' ') . ' kr',
        );
    }
}

