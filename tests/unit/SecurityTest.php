<?php
/**
 * Unit tests for UtilitySign security utilities
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

use PHPUnit\Framework\TestCase;
use UtilitySign\Utils\Security;

class SecurityTest extends TestCase {
    
    /**
     * Test text sanitization
     */
    public function test_sanitize_text() {
        $input = '<script>alert("xss")</script>Hello World';
        $expected = 'alert("xss")Hello World';
        $this->assertEquals($expected, Security::sanitize_text($input));
    }
    
    /**
     * Test email sanitization
     */
    public function test_sanitize_email() {
        $input = 'test@example.com<script>alert("xss")</script>';
        $expected = 'test@example.com';
        $this->assertEquals($expected, Security::sanitize_email($input));
    }
    
    /**
     * Test URL sanitization
     */
    public function test_sanitize_url() {
        $input = 'https://example.com/path?param=value';
        $expected = 'https://example.com/path?param=value';
        $this->assertEquals($expected, Security::sanitize_url($input));
    }
    
    /**
     * Test HTML class sanitization
     */
    public function test_sanitize_html_class() {
        $input = 'test-class<script>alert("xss")</script>';
        $expected = 'test-class';
        $this->assertEquals($expected, Security::sanitize_html_class($input));
    }
    
    /**
     * Test boolean sanitization
     */
    public function test_sanitize_boolean() {
        $this->assertTrue(Security::sanitize_boolean('1'));
        $this->assertTrue(Security::sanitize_boolean('true'));
        $this->assertTrue(Security::sanitize_boolean(true));
        $this->assertFalse(Security::sanitize_boolean('0'));
        $this->assertFalse(Security::sanitize_boolean('false'));
        $this->assertFalse(Security::sanitize_boolean(false));
    }
    
    /**
     * Test integer sanitization
     */
    public function test_sanitize_integer() {
        $this->assertEquals(123, Security::sanitize_integer('123'));
        $this->assertEquals(0, Security::sanitize_integer('abc'));
        $this->assertEquals(456, Security::sanitize_integer(456.789));
    }
    
    /**
     * Test array sanitization
     */
    public function test_sanitize_array() {
        $input = [
            'text' => '<script>alert("xss")</script>Hello',
            'email' => 'test@example.com<script>alert("xss")</script>',
            'class' => 'test-class<script>alert("xss")</script>'
        ];
        
        $sanitized = Security::sanitize_array($input, 'text');
        $this->assertEquals('alert("xss")Hello', $sanitized['text']);
        
        $sanitized = Security::sanitize_array($input, 'email');
        $this->assertEquals('test@example.com', $sanitized['email']);
        
        $sanitized = Security::sanitize_array($input, 'html_class');
        $this->assertEquals('test-class', $sanitized['class']);
    }
    
    /**
     * Test document ID validation
     */
    public function test_validate_document_id() {
        $this->assertTrue(Security::validate_document_id('doc-123'));
        $this->assertTrue(Security::validate_document_id('document_456'));
        $this->assertTrue(Security::validate_document_id('test-doc-789'));
        $this->assertFalse(Security::validate_document_id('doc@123'));
        $this->assertFalse(Security::validate_document_id('doc#123'));
        $this->assertFalse(Security::validate_document_id('doc 123'));
        $this->assertFalse(Security::validate_document_id('doc<script>'));
    }
    
    /**
     * Test email validation
     */
    public function test_validate_email() {
        $this->assertTrue(Security::validate_email('test@example.com'));
        $this->assertTrue(Security::validate_email('user.name@domain.co.uk'));
        $this->assertFalse(Security::validate_email('invalid-email'));
        $this->assertFalse(Security::validate_email('test@'));
        $this->assertFalse(Security::validate_email('@example.com'));
    }
    
    /**
     * Test shortcode attributes sanitization
     */
    public function test_sanitize_shortcode_attributes() {
        $input = [
            'document_id' => 'doc-123<script>alert("xss")</script>',
            'enable_bank_id' => '1',
            'enable_email_notifications' => 'false',
            'class_name' => 'test-class<script>alert("xss")</script>',
            'invalid_attr' => 'should_be_ignored'
        ];
        
        $sanitized = Security::sanitize_shortcode_attributes($input);
        
        $this->assertEquals('doc-123', $sanitized['document_id']);
        $this->assertTrue($sanitized['enable_bank_id']);
        $this->assertFalse($sanitized['enable_email_notifications']);
        $this->assertEquals('test-class', $sanitized['class_name']);
        $this->assertArrayNotHasKey('invalid_attr', $sanitized);
    }
    
    /**
     * Test HTML escaping
     */
    public function test_esc_attr() {
        $input = 'test"value<script>alert("xss")</script>';
        $expected = 'test&quot;value&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
        $this->assertEquals($expected, Security::esc_attr($input));
    }
    
    /**
     * Test HTML content escaping
     */
    public function test_esc_html() {
        $input = 'test<script>alert("xss")</script>value';
        $expected = 'test&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;value';
        $this->assertEquals($expected, Security::esc_html($input));
    }
}
