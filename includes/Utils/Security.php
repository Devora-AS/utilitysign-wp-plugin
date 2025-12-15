<?php
/**
 * Security utility class for UtilitySign plugin.
 *
 * @package UtilitySign\Utils
 * @since 1.0.0
 */

namespace UtilitySign\Utils;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security utility class
 * 
 * Provides security functions for nonce verification, data sanitization,
 * and input validation for the UtilitySign plugin.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class Security {
    
    /**
     * Regex pattern for valid plugin keys.
     */
    const PLUGIN_KEY_PATTERN = '/wp_[a-f0-9]{32}/i';

    /**
     * Verify WordPress nonce
     * 
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool True if nonce is valid, false otherwise
     * @since 1.0.0
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Create a nonce for a specific action
     * 
     * @param string $action The action name
     * @return string The nonce
     * @since 1.0.0
     */
    public static function create_nonce($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * Sanitize text field
     * 
     * @param mixed $value The value to sanitize
     * @return string Sanitized text
     * @since 1.0.0
     */
    public static function sanitize_text($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize_text'], $value);
        }
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize email address
     * 
     * @param string $email The email to sanitize
     * @return string Sanitized email
     * @since 1.0.0
     */
    public static function sanitize_email($email) {
        return sanitize_email($email);
    }
    
    /**
     * Sanitize URL
     * 
     * @param string $url The URL to sanitize
     * @return string Sanitized URL
     * @since 1.0.0
     */
    public static function sanitize_url($url) {
        return esc_url_raw($url);
    }
    
    /**
     * Sanitize HTML class name
     * 
     * @param string $class The class name to sanitize
     * @return string Sanitized class name
     * @since 1.0.0
     */
    public static function sanitize_html_class($class) {
        return sanitize_html_class($class);
    }
    
    /**
     * Sanitize boolean value
     * 
     * @param mixed $value The value to sanitize
     * @return bool Sanitized boolean
     * @since 1.0.0
     */
    public static function sanitize_boolean($value) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitize integer value
     * 
     * @param mixed $value The value to sanitize
     * @return int Sanitized integer
     * @since 1.0.0
     */
    public static function sanitize_integer($value) {
        return intval($value);
    }
    
    /**
     * Sanitize array of values
     * 
     * @param array $values The values to sanitize
     * @param string $type The sanitization type
     * @return array Sanitized array
     * @since 1.0.0
     */
    public static function sanitize_array($values, $type = 'text') {
        if (!is_array($values)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($values as $key => $value) {
            $sanitized_key = sanitize_key($key);
            switch ($type) {
                case 'email':
                    $sanitized[$sanitized_key] = self::sanitize_email($value);
                    break;
                case 'url':
                    $sanitized[$sanitized_key] = self::sanitize_url($value);
                    break;
                case 'html_class':
                    $sanitized[$sanitized_key] = self::sanitize_html_class($value);
                    break;
                case 'boolean':
                    $sanitized[$sanitized_key] = self::sanitize_boolean($value);
                    break;
                case 'integer':
                    $sanitized[$sanitized_key] = self::sanitize_integer($value);
                    break;
                default:
                    $sanitized[$sanitized_key] = self::sanitize_text($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate document ID format
     * 
     * @param string $document_id The document ID to validate
     * @return bool True if valid, false otherwise
     * @since 1.0.0
     */
    public static function validate_document_id($document_id) {
        // Document ID should be alphanumeric with hyphens and underscores
        return preg_match('/^[a-zA-Z0-9_-]+$/', $document_id) === 1;
    }
    
    /**
     * Validate email address
     * 
     * @param string $email The email to validate
     * @return bool True if valid, false otherwise
     * @since 1.0.0
     */
    public static function validate_email($email) {
        return is_email($email) !== false;
    }
    
    /**
     * Check if user has required capability
     * 
     * @param string $capability The capability to check
     * @return bool True if user has capability, false otherwise
     * @since 1.0.0
     */
    public static function current_user_can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Escape HTML attributes
     * 
     * @param string $value The value to escape
     * @return string Escaped value
     * @since 1.0.0
     */
    public static function esc_attr($value) {
        return esc_attr($value);
    }
    
    /**
     * Escape HTML content
     * 
     * @param string $value The value to escape
     * @return string Escaped value
     * @since 1.0.0
     */
    public static function esc_html($value) {
        return esc_html($value);
    }
    
    /**
     * Escape URL
     * 
     * @param string $url The URL to escape
     * @return string Escaped URL
     * @since 1.0.0
     */
    public static function esc_url($url) {
        return esc_url($url);
    }
    
    /**
     * Sanitize float value
     * 
     * @param mixed $value The value to sanitize
     * @return float Sanitized float
     * @since 1.0.0
     */
    public static function sanitize_float($value) {
        return floatval($value);
    }

    /**
     * Sanitize HTML content
     * 
     * @param string $value The value to sanitize
     * @return string Sanitized HTML
     * @since 1.0.0
     */
    public static function sanitize_html($value) {
        return wp_kses_post($value);
    }

    /**
     * Sanitize and validate shortcode attributes
     * 
     * @param array $atts The attributes to sanitize
     * @return array Sanitized attributes
     * @since 1.0.0
     */
    public static function sanitize_shortcode_attributes($atts) {
        $sanitized = [];
        
        // Document ID - required and validated
        if (isset($atts['document_id'])) {
            $document_id = self::sanitize_text($atts['document_id']);
            if (self::validate_document_id($document_id)) {
                $sanitized['document_id'] = $document_id;
            }
        }
        
        // Enable BankID - boolean
        if (isset($atts['enable_bank_id'])) {
            $sanitized['enable_bank_id'] = self::sanitize_boolean($atts['enable_bank_id']);
        }
        
        // Enable Email Notifications - boolean
        if (isset($atts['enable_email_notifications'])) {
            $sanitized['enable_email_notifications'] = self::sanitize_boolean($atts['enable_email_notifications']);
        }
        
        // Class name - HTML class
        if (isset($atts['class_name'])) {
            $sanitized['class_name'] = self::sanitize_html_class($atts['class_name']);
        }
        
        return $sanitized;
    }

    /**
     * Encrypt sensitive value using WordPress salts
     *
     * @param string $value Plain text value
     * @return string Encrypted value with IV prefix
     */
    public static function encrypt_secret( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) );
        $iv  = openssl_random_pseudo_bytes( 16 );

        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $cipher ) {
            return '';
        }

        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt sensitive value
     *
     * @param string $encoded Encrypted value from encrypt_secret
     * @return string Plain text value
     */
    public static function decrypt_secret( $encoded ) {
        if ( empty( $encoded ) ) {
            return '';
        }

        $raw = base64_decode( $encoded, true );

        if ( false === $raw || strlen( $raw ) < 17 ) {
            return '';
        }

        $iv   = substr( $raw, 0, 16 );
        $data = substr( $raw, 16 );
        $key  = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) );

        $plain = openssl_decrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return false === $plain ? '' : $plain;
    }

    /**
     * Mask secret for UI display
     *
     * @param string $value Plain text secret
     * @return string Masked secret (**** suffix)
     */
    public static function mask_secret( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $visible = substr( $value, -4 );
        return '••••••••' . $visible;
    }

    /**
     * Determine if stored secret is encrypted
     *
     * @param string $value Stored value
     * @return bool
     */
    public static function is_encrypted_secret( $value ) {
        if ( empty( $value ) ) {
            return false;
        }

        return base64_encode( base64_decode( $value, true ) ) === $value;
    }

    /**
     * Retrieve the stored plugin key and validation status.
     *
     * @return array{plugin_key:string,status:string,reason:string}
     */
    public static function get_plugin_key_info() : array {
        $stored_key = get_option( 'utilitysign_api_key', '' );
        $status     = 'missing';
        $reason     = '';
        $plugin_key = '';

        if ( ! empty( $stored_key ) ) {
            if ( self::is_encrypted_secret( $stored_key ) ) {
                $stored_key = self::decrypt_secret( $stored_key );
            }

            $stored_key = trim( (string) $stored_key );

            if ( 0 === strpos( $stored_key, 'default-client-id' ) ) {
                $stored_key = preg_replace( '/^default-client-id/', '', $stored_key );
            }

            if ( preg_match( self::PLUGIN_KEY_PATTERN, $stored_key, $matches ) ) {
                $plugin_key = strtolower( $matches[0] );
                $status     = 'valid';
            } else {
                $status = 'invalid';
                $reason = __( 'Plugin key must match the wp_{32 hex} format. Please paste the key exactly as provided in UtilitySign Admin.', 'utilitysign' );
            }
        }

        return array(
            'plugin_key' => $plugin_key,
            'status'     => $status,
            'reason'     => $reason,
        );
    }

    /**
     * Determine whether the current site should force staging configuration.
     *
     * @return bool
     */
    public static function is_staging_environment() : bool {
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

        if ( ! empty( $host ) ) {
            if ( false !== stripos( $host, 'pilot.' ) || false !== stripos( $host, 'staging.' ) ) {
                return true;
            }
        }

        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'staging' === WP_ENVIRONMENT_TYPE ) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the backend API URL that WordPress should use for server-to-server calls.
     *
     * @return string
     */
    public static function get_backend_api_url() : string {
        if ( self::is_staging_environment() ) {
            return 'https://devora-utilitysign-api-staging.azurewebsites.net';
        }

        $stored = get_option( 'utilitysign_api_url', '' );
        if ( empty( $stored ) ) {
            return 'https://api.utilitysign.devora.no';
        }

        return esc_url_raw( $stored );
    }

    /**
     * Generate a stable client identifier for telemetry and logging.
     *
     * @return string
     */
    public static function get_site_client_id() : string {
        $site_url = get_bloginfo( 'url' );
        if ( empty( $site_url ) ) {
            return 'utilitysign-wordpress-plugin';
        }

        $host = wp_parse_url( $site_url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            return 'utilitysign-wordpress-plugin';
        }

        return sanitize_title( $host );
    }
}
