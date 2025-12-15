<?php
/**
 * Unit tests for Products controller
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use UtilitySign\Controllers\Products\Actions;

class ProductsControllerTest extends TestCase {
    
    private $controller;
    
    protected function setUp(): void {
        parent::setUp();
        $this->controller = new Actions();
    }
    
    /**
     * Test get_all_products method structure
     */
    public function test_get_all_products_structure() {
        // Mock WordPress functions
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }
        
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return strip_tags($str);
            }
        }
        
        if (!function_exists('wp_send_json_success')) {
            function wp_send_json_success($data = null, $status_code = null, $options = 0) {
                return array('success' => true, 'data' => $data);
            }
        }
        
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data = null, $status_code = null, $options = 0) {
                return array('success' => false, 'data' => $data);
            }
        }
        
        // Test that the method exists and is callable
        $this->assertTrue(method_exists($this->controller, 'get_all_products'));
        $this->assertTrue(is_callable([$this->controller, 'get_all_products']));
    }
    
    /**
     * Test get_product method structure
     */
    public function test_get_product_structure() {
        $this->assertTrue(method_exists($this->controller, 'get_product'));
        $this->assertTrue(is_callable([$this->controller, 'get_product']));
    }
    
    /**
     * Test create_product method structure
     */
    public function test_create_product_structure() {
        $this->assertTrue(method_exists($this->controller, 'create_product'));
        $this->assertTrue(is_callable([$this->controller, 'create_product']));
    }
    
    /**
     * Test update_product method structure
     */
    public function test_update_product_structure() {
        $this->assertTrue(method_exists($this->controller, 'update_product'));
        $this->assertTrue(is_callable([$this->controller, 'update_product']));
    }
    
    /**
     * Test delete_product method structure
     */
    public function test_delete_product_structure() {
        $this->assertTrue(method_exists($this->controller, 'delete_product'));
        $this->assertTrue(is_callable([$this->controller, 'delete_product']));
    }
    
    /**
     * Test get_suppliers method structure
     */
    public function test_get_suppliers_structure() {
        $this->assertTrue(method_exists($this->controller, 'get_suppliers'));
        $this->assertTrue(is_callable([$this->controller, 'get_suppliers']));
    }
    
    /**
     * Test controller uses Base trait
     */
    public function test_uses_base_trait() {
        $traits = class_uses($this->controller);
        $this->assertContains('UtilitySign\Traits\Base', $traits);
    }
}
