<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the test environment for PHPUnit tests.
 * 
 * @package UtilitySign
 * @subpackage Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Brain Monkey setup
\Brain\Monkey\setUp();

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Mock WordPress functions that are commonly used
\Brain\Monkey\Functions\when('__')->returnArg();
\Brain\Monkey\Functions\when('_e')->returnArg();
\Brain\Monkey\Functions\when('esc_html')->returnArg();
\Brain\Monkey\Functions\when('esc_html__')->returnArg();
\Brain\Monkey\Functions\when('esc_attr')->returnArg();
\Brain\Monkey\Functions\when('esc_url')->returnArg();
\Brain\Monkey\Functions\when('esc_url_raw')->returnArg();
\Brain\Monkey\Functions\when('sanitize_text_field')->returnArg();
\Brain\Monkey\Functions\when('sanitize_email')->returnArg();
\Brain\Monkey\Functions\when('sanitize_key')->returnArg();
\Brain\Monkey\Functions\when('wp_kses_post')->returnArg();

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;
        
        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        
        public function get_error_code() {
            return $this->code;
        }
        
        public function get_error_message() {
            return $this->message;
        }
        
        public function get_error_data() {
            return $this->data;
        }
    }
}

// Register shutdown function to tear down Brain Monkey
register_shutdown_function(function() {
    \Brain\Monkey\tearDown();
});

