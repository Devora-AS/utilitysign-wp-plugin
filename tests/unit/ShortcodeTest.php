<?php
/**
 * Unit tests for UtilitySign shortcodes
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use UtilitySign\Shortcodes\SigningFormShortcode;
use UtilitySign\Utils\Security;

class ShortcodeTest extends TestCase {
    
    private $shortcode;
    
    protected function setUp(): void {
        parent::setUp();
        $this->shortcode = new SigningFormShortcode();
    }
    
    /**
     * Test shortcode registration
     */
    public function test_shortcode_registration() {
        $this->assertTrue(shortcode_exists('utilitysign_signing_form'));
    }
    
    /**
     * Test shortcode rendering with valid attributes
     */
    public function test_shortcode_rendering_with_valid_attributes() {
        $atts = [
            'document_id' => 'test-doc-123',
            'enable_bank_id' => 'true',
            'enable_email_notifications' => 'false',
            'class_name' => 'test-class'
        ];
        
        $output = $this->shortcode->render_signing_form($atts);
        
        $this->assertStringContainsString('utilitysign-signing-form', $output);
        $this->assertStringContainsString('data-document-id="test-doc-123"', $output);
        $this->assertStringContainsString('data-enable-bank-id="true"', $output);
        $this->assertStringContainsString('data-enable-email-notifications="false"', $output);
        $this->assertStringContainsString('test-class', $output);
    }
    
    /**
     * Test shortcode rendering with missing document ID
     */
    public function test_shortcode_rendering_with_missing_document_id() {
        $atts = [
            'enable_bank_id' => 'true',
            'enable_email_notifications' => 'false'
        ];
        
        $output = $this->shortcode->render_signing_form($atts);
        
        $this->assertStringContainsString('utilitysign-error', $output);
        $this->assertStringContainsString('Document ID is required', $output);
    }
    
    /**
     * Test shortcode attribute sanitization
     */
    public function test_shortcode_attribute_sanitization() {
        $atts = [
            'document_id' => 'test-doc-123<script>alert("xss")</script>',
            'enable_bank_id' => '1',
            'enable_email_notifications' => '0',
            'class_name' => 'test-class<script>alert("xss")</script>'
        ];
        
        $output = $this->shortcode->render_signing_form($atts);
        
        // Should not contain script tags
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert("xss")', $output);
        
        // Should contain sanitized values
        $this->assertStringContainsString('data-document-id="test-doc-123"', $output);
        $this->assertStringContainsString('test-class', $output);
    }
    
    /**
     * Test shortcode with invalid document ID format
     */
    public function test_shortcode_with_invalid_document_id() {
        $atts = [
            'document_id' => 'invalid@document#id',
            'enable_bank_id' => 'true'
        ];
        
        $output = $this->shortcode->render_signing_form($atts);
        
        $this->assertStringContainsString('utilitysign-error', $output);
        $this->assertStringContainsString('Document ID is required', $output);
    }
    
    /**
     * Test shortcode with default values
     */
    public function test_shortcode_with_default_values() {
        $atts = [
            'document_id' => 'test-doc-123'
        ];
        
        $output = $this->shortcode->render_signing_form($atts);
        
        $this->assertStringContainsString('data-enable-bank-id="true"', $output);
        $this->assertStringContainsString('data-enable-email-notifications="true"', $output);
    }
    
    /**
     * Test shortcode filters
     */
    public function test_shortcode_filters() {
        // Add a filter to modify the output
        add_filter('utilitysign_shortcode_output', function($output, $atts, $content) {
            return '<div class="filtered-output">' . $output . '</div>';
        }, 10, 3);
        
        $atts = ['document_id' => 'test-doc-123'];
        $output = $this->shortcode->render_signing_form($atts);
        
        $this->assertStringContainsString('filtered-output', $output);
        
        // Clean up
        remove_all_filters('utilitysign_shortcode_output');
    }
}
