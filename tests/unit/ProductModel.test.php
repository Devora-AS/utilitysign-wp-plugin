<?php
/**
 * Unit tests for Product model
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use UtilitySign\Models\Product;

class ProductModelTest extends TestCase {
    
    private $model;
    
    protected function setUp(): void {
        parent::setUp();
        $this->model = new Product();
    }
    
    /**
     * Test model table configuration
     */
    public function test_table_configuration() {
        $this->assertEquals('posts', $this->model->getTable());
        $this->assertEquals('ID', $this->model->getKeyName());
    }
    
    /**
     * Test fillable attributes
     */
    public function test_fillable_attributes() {
        $fillable = $this->model->getFillable();
        $expected = [
            'post_title',
            'post_content',
            'post_status',
            'post_type',
            'post_author',
            'post_date',
            'post_date_gmt',
            'post_modified',
            'post_modified_gmt',
        ];
        
        foreach ($expected as $field) {
            $this->assertContains($field, $fillable);
        }
    }
    
    /**
     * Test casts configuration
     */
    public function test_casts_configuration() {
        $casts = $this->model->getCasts();
        $this->assertArrayHasKey('post_date', $casts);
        $this->assertArrayHasKey('post_date_gmt', $casts);
        $this->assertArrayHasKey('post_modified', $casts);
        $this->assertArrayHasKey('post_modified_gmt', $casts);
    }
    
    /**
     * Test post type is set correctly
     */
    public function test_post_type() {
        $this->assertEquals('utilitysign_product', $this->model->post_type);
    }
    
    /**
     * Test model has required methods
     */
    public function test_has_required_methods() {
        $methods = [
            'getSupplierAttribute',
            'setSupplierAttribute',
            'getProductCodeAttribute',
            'setProductCodeAttribute',
            'getPriceAttribute',
            'setPriceAttribute',
            'getCurrencyAttribute',
            'setCurrencyAttribute',
            'getDescriptionAttribute',
            'setDescriptionAttribute',
            'getFeaturesAttribute',
            'setFeaturesAttribute',
            'getRequirementsAttribute',
            'setRequirementsAttribute',
            'getIsActiveAttribute',
            'setIsActiveAttribute',
            'scopeProducts',
            'scopeActive',
            'scopeBySupplier',
            'scopeSearch',
            'meta',
            'getFormattedPriceAttribute',
            'isAvailableForOrdering',
            'getSummary',
        ];
        
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->model, $method), "Method {$method} should exist");
        }
    }
    
    /**
     * Test scope methods exist
     */
    public function test_scope_methods() {
        $this->assertTrue(method_exists($this->model, 'scopeProducts'));
        $this->assertTrue(method_exists($this->model, 'scopeActive'));
        $this->assertTrue(method_exists($this->model, 'scopeBySupplier'));
        $this->assertTrue(method_exists($this->model, 'scopeSearch'));
    }
    
    /**
     * Test relationship methods exist
     */
    public function test_relationship_methods() {
        $this->assertTrue(method_exists($this->model, 'meta'));
    }
    
    /**
     * Test utility methods exist
     */
    public function test_utility_methods() {
        $this->assertTrue(method_exists($this->model, 'getFormattedPriceAttribute'));
        $this->assertTrue(method_exists($this->model, 'isAvailableForOrdering'));
        $this->assertTrue(method_exists($this->model, 'getSummary'));
    }
}
