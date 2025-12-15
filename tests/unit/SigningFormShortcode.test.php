<?php
/**
 * Test suite for SigningFormShortcode
 *
 * @package UtilitySign
 * @since 1.0.0
 */

use UtilitySign\Shortcodes\SigningFormShortcode;
use UtilitySign\Utils\Security;

class SigningFormShortcodeTest extends WP_UnitTestCase {
    
    private $shortcode;
    
    public function setUp(): void {
        parent::setUp();
        $this->shortcode = new SigningFormShortcode();
    }
    
    public function test_shortcode_registration() {
        global $shortcode_tags;
        
        $this->shortcode->init();
        
        $this->assertArrayHasKey('utilitysign_signing_form', $shortcode_tags);
    }
    
    public function test_shortcode_renders_with_valid_document_id() {
        $this->shortcode->init();
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123"]');
        
        $this->assertStringContainsString('utilitysign-signing-form', $output);
        $this->assertStringContainsString('data-document-id="test-doc-123"', $output);
        $this->assertStringNotContainsString('utilitysign-error', $output);
    }
    
    public function test_shortcode_renders_error_without_document_id() {
        $this->shortcode->init();
        
        $output = do_shortcode('[utilitysign_signing_form]');
        
        $this->assertStringContainsString('utilitysign-error', $output);
        $this->assertStringContainsString('Document ID is required', $output);
    }
    
    public function test_shortcode_handles_attributes_correctly() {
        $this->shortcode->init();
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="false" enable_email_notifications="true" class_name="custom-class"]');
        
        $this->assertStringContainsString('data-document-id="test-doc-123"', $output);
        $this->assertStringContainsString('data-enable-bank-id="false"', $output);
        $this->assertStringContainsString('data-enable-email-notifications="true"', $output);
        $this->assertStringContainsString('custom-class', $output);
    }
    
    public function test_shortcode_sanitizes_attributes() {
        $this->shortcode->init();
        
        $output = do_shortcode('[utilitysign_signing_form document_id="<script>alert(\'xss\')</script>" class_name="<img src=x onerror=alert(1)>"]');
        
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('onerror=', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
    
    public function test_shortcode_applies_filters() {
        $this->shortcode->init();
        
        // Test attribute filtering
        add_filter('utilitysign_shortcode_attributes', function($atts, $tag) {
            if ($tag === 'utilitysign_signing_form') {
                $atts['document_id'] = 'filtered-doc-123';
            }
            return $atts;
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="original-doc-123"]');
        
        $this->assertStringContainsString('data-document-id="filtered-doc-123"', $output);
        
        remove_all_filters('utilitysign_shortcode_attributes');
    }
    
    public function test_shortcode_error_message_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_error_message', function($message, $error_type) {
            if ($error_type === 'missing_document_id') {
                return '<div class="custom-error">Custom error message</div>';
            }
            return $message;
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form]');
        
        $this->assertStringContainsString('custom-error', $output);
        $this->assertStringContainsString('Custom error message', $output);
        
        remove_all_filters('utilitysign_shortcode_error_message');
    }
    
    public function test_shortcode_config_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_config', function($config, $atts) {
            $config['customAttribute'] = 'custom-value';
            return $config;
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123"]');
        
        // The config is passed to wp_localize_script, so we can't easily test it here
        // But we can verify the shortcode still works
        $this->assertStringContainsString('utilitysign-signing-form', $output);
        
        remove_all_filters('utilitysign_shortcode_config');
    }
    
    public function test_shortcode_html_attributes_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_html_attributes', function($attributes, $atts) {
            $attributes['data-custom'] = 'custom-value';
            return $attributes;
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123"]');
        
        $this->assertStringContainsString('data-custom="custom-value"', $output);
        
        remove_all_filters('utilitysign_shortcode_html_attributes');
    }
    
    public function test_shortcode_output_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_output', function($output, $atts, $content) {
            return '<div class="custom-wrapper">' . $output . '</div>';
        }, 10, 3);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123"]');
        
        $this->assertStringContainsString('custom-wrapper', $output);
        
        remove_all_filters('utilitysign_shortcode_output');
    }
    
    public function test_shortcode_form_id_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_form_id', function($form_id, $atts) {
            return 'custom-form-id';
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123"]');
        
        $this->assertStringContainsString('id="custom-form-id"', $output);
        
        remove_all_filters('utilitysign_shortcode_form_id');
    }
    
    public function test_shortcode_parsed_attributes_filter() {
        $this->shortcode->init();
        
        add_filter('utilitysign_shortcode_parsed_attributes', function($parsed_atts, $atts) {
            $parsed_atts['enable_bank_id'] = false;
            return $parsed_atts;
        }, 10, 2);
        
        $output = do_shortcode('[utilitysign_signing_form document_id="test-doc-123" enable_bank_id="true"]');
        
        $this->assertStringContainsString('data-enable-bank-id="false"', $output);
        
        remove_all_filters('utilitysign_shortcode_parsed_attributes');
    }
}
