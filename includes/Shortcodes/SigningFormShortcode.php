<?php
namespace UtilitySign\Shortcodes;

use UtilitySign\Libs\Utils\Shortcode;
use UtilitySign\Assets\Frontend;
use UtilitySign\Traits\Base;
use UtilitySign\Utils\Security;
use UtilitySign\Libs\Assets;

/**
 * SigningFormShortcode class
 * 
 * Handles the registration and rendering of the UtilitySign signing form shortcode.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SigningFormShortcode {
    use Base;

    /**
     * Initialize the shortcode
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->register();
    }

    /**
     * Register the shortcode
     * 
     * @since 1.0.0
     */
    public function register() {
        // Primary shortcode
        Shortcode::add()
            ->tag('utilitysign_signing_form')
            ->attrs([
                'document_id',
                'enable_bank_id',
                'enable_email_notifications',
                'class_name'
            ])
            ->render([$this, 'render_signing_form']);
            
        // Shorter alias for convenience
        Shortcode::add()
            ->tag('utilitysign_form')
            ->attrs([
                'document_id',
                'enable_bank_id',
                'enable_email_notifications',
                'class_name'
            ])
            ->render([$this, 'render_signing_form']);
    }

    /**
     * Render the signing form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     * @since 1.0.0
     */
    public function render_signing_form($atts, $content = null) {
        // Allow filtering of shortcode attributes
        $atts = apply_filters('utilitysign_shortcode_attributes', $atts, 'utilitysign_signing_form');
        
        // Sanitize and validate attributes using security utility
        $sanitized_atts = Security::sanitize_shortcode_attributes($atts);
        
        // Set defaults for missing attributes
        $document_id = $sanitized_atts['document_id'] ?? '';
        
        // Handle boolean attributes correctly - use filter_var only when attribute is present
        // Default to true if attribute is not specified
        $enable_bank_id = isset($sanitized_atts['enable_bank_id'])
            ? filter_var($sanitized_atts['enable_bank_id'], FILTER_VALIDATE_BOOLEAN)
            : true; // Default to true if not specified
        
        $enable_email_notifications = isset($sanitized_atts['enable_email_notifications'])
            ? filter_var($sanitized_atts['enable_email_notifications'], FILTER_VALIDATE_BOOLEAN)
            : true; // Default to true if not specified
        
        $class_name = $sanitized_atts['class_name'] ?? '';

        // Allow filtering of parsed attributes
        $parsed_atts = apply_filters('utilitysign_shortcode_parsed_attributes', [
            'document_id' => $document_id,
            'enable_bank_id' => $enable_bank_id,
            'enable_email_notifications' => $enable_email_notifications,
            'class_name' => $class_name,
        ], $atts);

        // Document ID is optional - if not provided, form will create a new signing request
        // This allows the shortcode to work as both:
        // 1. Standalone order form (no document_id) - creates new signing request
        // 2. Document signing form (with document_id) - signs existing document
        if (empty($parsed_atts['document_id'])) {
            // No document_id provided - this is normal for WordPress form submissions
            // The form will create a new signing request via the backend API
            $parsed_atts['document_id'] = ''; // Empty string to indicate "create new"
        }

                // Enqueue the frontend React app
                Frontend::get_instance()->enqueue_script('shortcode');
                
                // Also enqueue assets directly for shortcode usage
                if ( ! wp_script_is( Frontend::HANDLE, 'enqueued' ) ) {
                    Assets\enqueue_asset(
                        UTILITYSIGN_DIR . 'assets/frontend/dist',
                        Frontend::DEV_SCRIPT,
                        Frontend::get_instance()->get_config()
                    );
                    wp_localize_script( Frontend::HANDLE, Frontend::OBJ_NAME, Frontend::get_instance()->get_data() );
                }

        // Prepare configuration data
        $config_data = apply_filters('utilitysign_shortcode_config', [
            'documentId' => $parsed_atts['document_id'],
            'enableBankID' => $parsed_atts['enable_bank_id'],
            'enableEmailNotifications' => $parsed_atts['enable_email_notifications'],
            'className' => $parsed_atts['class_name'],
        ], $atts);

        // Localize script with shortcode attributes
        wp_localize_script(
            Frontend::HANDLE,
            'utilitySignShortcodeConfig',
            $config_data
        );

        // Generate unique ID for the form
        $form_id = 'utilitysign-signing-form-' . uniqid();
        
        // Allow filtering of form ID
        $form_id = apply_filters('utilitysign_shortcode_form_id', $form_id, $atts);

        // Prepare HTML attributes
        $html_attributes = apply_filters('utilitysign_shortcode_html_attributes', [
            'id' => Security::esc_attr($form_id),
            'class' => 'utilitysign-app utilitysign-signing-form ' . Security::esc_attr($parsed_atts['class_name']),
            'data-document-id' => Security::esc_attr($parsed_atts['document_id']),
            'data-enable-bank-id' => Security::esc_attr($parsed_atts['enable_bank_id'] ? 'true' : 'false'),
            'data-enable-email-notifications' => Security::esc_attr($parsed_atts['enable_email_notifications'] ? 'true' : 'false'),
        ], $atts);

        // Build HTML attributes string
        $attributes_string = '';
        foreach ($html_attributes as $key => $value) {
            $attributes_string .= sprintf(' %s="%s"', Security::esc_attr($key), $value);
        }

        // Allow filtering of the complete HTML output
        $html_output = sprintf('<div%s></div>', $attributes_string);
        
        return apply_filters('utilitysign_shortcode_output', $html_output, $atts, $content);
    }
}