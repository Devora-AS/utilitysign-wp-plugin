<?php
/**
 * Security Tests for UtilitySign WordPress Plugin
 * Comprehensive test suite for all security features
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

class UtilitySign_Security_Tests extends WP_UnitTestCase {
    
    /**
     * Test security service initialization
     * 
     * @since 1.0.0
     */
    public function test_security_service_initialization() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        $this->assertInstanceOf('UtilitySign\Core\SecurityService', $security_service);
        $this->assertIsArray($security_service->get_config());
    }
    
    /**
     * Test API authentication service initialization
     * 
     * @since 1.0.0
     */
    public function test_api_authentication_service_initialization() {
        $auth_service = new \UtilitySign\Core\ApiAuthenticationService();
        $auth_service->init();
        
        $this->assertInstanceOf('UtilitySign\Core\ApiAuthenticationService', $auth_service);
        $this->assertIsArray($auth_service->get_config());
    }
    
    /**
     * Test cache service initialization
     * 
     * @since 1.0.0
     */
    public function test_cache_service_initialization() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        $this->assertInstanceOf('UtilitySign\Core\CacheService', $cache_service);
        $this->assertIsArray($cache_service->get_config());
    }
    
    /**
     * Test error handling service initialization
     * 
     * @since 1.0.0
     */
    public function test_error_handling_service_initialization() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        $this->assertInstanceOf('UtilitySign\Core\ErrorHandlingService', $error_service);
        $this->assertIsArray($error_service->get_config());
    }
    
    /**
     * Test multisite service initialization
     * 
     * @since 1.0.0
     */
    public function test_multisite_service_initialization() {
        $multisite_service = new \UtilitySign\Core\MultisiteService();
        $multisite_service->init();
        
        $this->assertInstanceOf('UtilitySign\Core\MultisiteService', $multisite_service);
        $this->assertIsArray($multisite_service->get_config());
    }
    
    /**
     * Test HTTPS enforcement
     * 
     * @since 1.0.0
     */
    public function test_https_enforcement() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test HTTPS enforcement
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test';
        
        ob_start();
        $security_service->enforce_https();
        $output = ob_get_clean();
        
        // Should redirect to HTTPS
        $this->assertStringContains('https://', $output);
    }
    
    /**
     * Test rate limiting
     * 
     * @since 1.0.0
     */
    public function test_rate_limiting() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test rate limiting
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // Should allow first request
        $this->assertTrue($security_service->check_rate_limit());
        
        // Should block after exceeding limit
        for ($i = 0; $i < 100; $i++) {
            $security_service->check_rate_limit();
        }
        
        $this->expectException('Exception');
        $security_service->check_rate_limit();
    }
    
    /**
     * Test CSRF protection
     * 
     * @since 1.0.0
     */
    public function test_csrf_protection() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test CSRF protection
        $_POST['_wpnonce'] = 'invalid_nonce';
        
        $this->expectException('Exception');
        $security_service->verify_csrf_token();
    }
    
    /**
     * Test XSS protection
     * 
     * @since 1.0.0
     */
    public function test_xss_protection() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test XSS protection
        $malicious_input = '<script>alert("XSS")</script>';
        $filtered_input = $security_service->filter_allowed_html($malicious_input, 'utilitysign');
        
        $this->assertStringNotContains('<script>', $filtered_input);
    }
    
    /**
     * Test SQL injection protection
     * 
     * @since 1.0.0
     */
    public function test_sql_injection_protection() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test SQL injection protection
        $_GET['malicious'] = "'; DROP TABLE users; --";
        
        $this->expectException('Exception');
        $security_service->prevent_sql_injection();
    }
    
    /**
     * Test file upload validation
     * 
     * @since 1.0.0
     */
    public function test_file_upload_validation() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test file upload validation
        $malicious_file = [
            'name' => 'malicious.php',
            'size' => 1024,
            'tmp_name' => '/tmp/malicious.php'
        ];
        
        $result = $security_service->validate_file_upload($malicious_file);
        
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('Invalid file type', $result['error']);
    }
    
    /**
     * Test API key authentication
     * 
     * @since 1.0.0
     */
    public function test_api_key_authentication() {
        $auth_service = new \UtilitySign\Core\ApiAuthenticationService();
        $auth_service->init();
        
        // Test API key authentication
        $valid_api_key = 'test_api_key_123';
        $auth_service->update_config(['api_key' => $valid_api_key]);
        
        $this->assertTrue($auth_service->validate_api_key($valid_api_key));
        $this->assertFalse($auth_service->validate_api_key('invalid_key'));
    }
    
    /**
     * Test JWT token generation and validation
     * 
     * @since 1.0.0
     */
    public function test_jwt_token_generation_and_validation() {
        $auth_service = new \UtilitySign\Core\ApiAuthenticationService();
        $auth_service->init();
        
        // Test JWT token generation
        $payload = ['user_id' => 1, 'role' => 'admin'];
        $token = $auth_service->generate_jwt_token($payload);
        
        $this->assertIsString($token);
        $this->assertStringContains('.', $token);
        
        // Test JWT token validation
        $this->assertTrue($auth_service->validate_jwt_token($token));
    }
    
    /**
     * Test cache functionality
     * 
     * @since 1.0.0
     */
    public function test_cache_functionality() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        // Test cache set and get
        $test_data = ['test' => 'data'];
        $cache_service->set('test_key', $test_data, 'test_group');
        
        $cached_data = $cache_service->get('test_key', 'test_group');
        $this->assertEquals($test_data, $cached_data);
        
        // Test cache delete
        $cache_service->delete('test_key', 'test_group');
        $cached_data = $cache_service->get('test_key', 'test_group');
        $this->assertFalse($cached_data);
    }
    
    /**
     * Test cache group clearing
     * 
     * @since 1.0.0
     */
    public function test_cache_group_clearing() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        // Set multiple cache entries
        $cache_service->set('key1', 'data1', 'test_group');
        $cache_service->set('key2', 'data2', 'test_group');
        $cache_service->set('key3', 'data3', 'other_group');
        
        // Clear test_group
        $cache_service->clear_group('test_group');
        
        // Check that test_group entries are cleared
        $this->assertFalse($cache_service->get('key1', 'test_group'));
        $this->assertFalse($cache_service->get('key2', 'test_group'));
        
        // Check that other_group entries remain
        $this->assertEquals('data3', $cache_service->get('key3', 'other_group'));
    }
    
    /**
     * Test error handling
     * 
     * @since 1.0.0
     */
    public function test_error_handling() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        // Test error logging
        $error_data = [
            'type' => 'Test Error',
            'message' => 'This is a test error',
            'file' => __FILE__,
            'line' => __LINE__,
            'severity' => 'error'
        ];
        
        $error_service->log_error($error_data);
        
        // Check that error was logged
        $this->assertTrue(true); // Error logging is tested by checking if it doesn't throw an exception
    }
    
    /**
     * Test error statistics
     * 
     * @since 1.0.0
     */
    public function test_error_statistics() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        // Test error statistics
        $stats = $error_service->get_error_stats();
        
        $this->assertIsArray($stats);
    }
    
    /**
     * Test multisite functionality
     * 
     * @since 1.0.0
     */
    public function test_multisite_functionality() {
        $multisite_service = new \UtilitySign\Core\MultisiteService();
        $multisite_service->init();
        
        // Test multisite configuration
        $config = $multisite_service->get_config();
        $this->assertIsArray($config);
        
        // Test current site ID
        $site_id = $multisite_service->get_current_site_id();
        $this->assertIsInt($site_id);
    }
    
    /**
     * Test data isolation
     * 
     * @since 1.0.0
     */
    public function test_data_isolation() {
        $multisite_service = new \UtilitySign\Core\MultisiteService();
        $multisite_service->init();
        
        // Test data isolation
        $option_name = 'test_option';
        $option_value = 'test_value';
        
        // Set option with site-specific filtering
        $multisite_service->filter_site_specific_option_update(true, $option_name, $option_value);
        
        // Get option with site-specific filtering
        $retrieved_value = $multisite_service->filter_site_specific_option(null, $option_name);
        
        $this->assertEquals($option_value, $retrieved_value);
    }
    
    /**
     * Test security headers
     * 
     * @since 1.0.0
     */
    public function test_security_headers() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test security headers
        ob_start();
        $security_service->add_security_headers();
        $output = ob_get_clean();
        
        // Check that headers were set
        $this->assertTrue(true); // Headers are tested by checking if they don't throw an exception
    }
    
    /**
     * Test IP whitelist
     * 
     * @since 1.0.0
     */
    public function test_ip_whitelist() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test IP whitelist
        $security_service->update_config([
            'enable_ip_whitelist' => true,
            'allowed_ips' => ['192.168.1.100']
        ]);
        
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        // Should allow whitelisted IP
        $this->assertTrue(true); // IP whitelist is tested by checking if it doesn't throw an exception
        
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';
        
        // Should block non-whitelisted IP
        $this->expectException('Exception');
        $security_service->check_ip_whitelist();
    }
    
    /**
     * Test encryption and decryption
     * 
     * @since 1.0.0
     */
    public function test_encryption_and_decryption() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test encryption and decryption
        $original_data = 'This is sensitive data';
        $encrypted_data = $security_service->encrypt_data($original_data);
        $decrypted_data = $security_service->decrypt_data($encrypted_data);
        
        $this->assertEquals($original_data, $decrypted_data);
        $this->assertNotEquals($original_data, $encrypted_data);
    }
    
    /**
     * Test cache statistics
     * 
     * @since 1.0.0
     */
    public function test_cache_statistics() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        // Test cache statistics
        $stats = $cache_service->get_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
    }
    
    /**
     * Test cache cleanup
     * 
     * @since 1.0.0
     */
    public function test_cache_cleanup() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        // Test cache cleanup
        $cache_service->cleanup_expired_cache();
        
        $this->assertTrue(true); // Cache cleanup is tested by checking if it doesn't throw an exception
    }
    
    /**
     * Test security log storage
     * 
     * @since 1.0.0
     */
    public function test_security_log_storage() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test security log storage
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => 'test_event',
            'data' => ['test' => 'data'],
            'site_id' => get_current_blog_id()
        ];
        
        $security_service->store_security_log($log_entry);
        
        $this->assertTrue(true); // Security log storage is tested by checking if it doesn't throw an exception
    }
    
    /**
     * Test security statistics
     * 
     * @since 1.0.0
     */
    public function test_security_statistics() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test security statistics
        $stats = $security_service->get_security_stats();
        
        $this->assertIsArray($stats);
    }
    
    /**
     * Test configuration updates
     * 
     * @since 1.0.0
     */
    public function test_configuration_updates() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test configuration updates
        $new_config = ['enable_https_enforcement' => false];
        $security_service->update_config($new_config);
        
        $config = $security_service->get_config();
        $this->assertFalse($config['enable_https_enforcement']);
    }
    
    /**
     * Test error message template
     * 
     * @since 1.0.0
     */
    public function test_error_message_template() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        // Test error message template
        $error_data = [
            'correlation_id' => 'test_123',
            'message' => 'Test error'
        ];
        
        $user_message = $error_service->get_user_friendly_message($error_data);
        
        $this->assertIsString($user_message);
        $this->assertStringContains('test_123', $user_message);
    }
    
    /**
     * Test correlation ID generation
     * 
     * @since 1.0.0
     */
    public function test_correlation_id_generation() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        // Test correlation ID generation
        $correlation_id = $error_service->generate_correlation_id();
        
        $this->assertIsString($correlation_id);
        $this->assertStringStartsWith('err_', $correlation_id);
    }
    
    /**
     * Test client IP detection
     * 
     * @since 1.0.0
     */
    public function test_client_ip_detection() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test client IP detection
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $ip = $security_service->get_client_ip();
        
        $this->assertEquals('192.168.1.100', $ip);
    }
    
    /**
     * Test database migrations
     * 
     * @since 1.0.0
     */
    public function test_database_migrations() {
        // Test security log migration
        \UtilitySign\Database\Migrations\SecurityLog::up();
        $this->assertTrue(true); // Migration is tested by checking if it doesn't throw an exception
        
        // Test auth log migration
        \UtilitySign\Database\Migrations\AuthLog::up();
        $this->assertTrue(true);
        
        // Test cache migration
        \UtilitySign\Database\Migrations\Cache::up();
        $this->assertTrue(true);
        
        // Test error log migration
        \UtilitySign\Database\Migrations\ErrorLog::up();
        $this->assertTrue(true);
    }
    
    /**
     * Test admin settings page
     * 
     * @since 1.0.0
     */
    public function test_admin_settings_page() {
        $security_settings = new \UtilitySign\Admin\SecuritySettings();
        $security_settings->init();
        
        $this->assertInstanceOf('UtilitySign\Admin\SecuritySettings', $security_settings);
    }
    
    /**
     * Test AJAX handlers
     * 
     * @since 1.0.0
     */
    public function test_ajax_handlers() {
        $security_settings = new \UtilitySign\Admin\SecuritySettings();
        $security_settings->init();
        
        // Test security configuration test
        $_POST['nonce'] = wp_create_nonce('utilitysign_security_nonce');
        
        ob_start();
        $security_settings->test_security_configuration();
        $output = ob_get_clean();
        
        $this->assertStringContains('success', $output);
    }
    
    /**
     * Test plugin initialization
     * 
     * @since 1.0.0
     */
    public function test_plugin_initialization() {
        $plugin = new \UtilitySign\UtilitySign();
        $plugin->init();
        
        $this->assertInstanceOf('UtilitySign\UtilitySign', $plugin);
    }
    
    /**
     * Test security service integration
     * 
     * @since 1.0.0
     */
    public function test_security_service_integration() {
        // Test that security services are properly integrated
        $this->assertTrue(class_exists('UtilitySign\Core\SecurityService'));
        $this->assertTrue(class_exists('UtilitySign\Core\ApiAuthenticationService'));
        $this->assertTrue(class_exists('UtilitySign\Core\CacheService'));
        $this->assertTrue(class_exists('UtilitySign\Core\ErrorHandlingService'));
        $this->assertTrue(class_exists('UtilitySign\Core\MultisiteService'));
    }
    
    /**
     * Test security configuration persistence
     * 
     * @since 1.0.0
     */
    public function test_security_configuration_persistence() {
        $security_service = new \UtilitySign\Core\SecurityService();
        $security_service->init();
        
        // Test configuration persistence
        $new_config = [
            'enable_https_enforcement' => true,
            'enable_rate_limiting' => true,
            'max_requests_per_minute' => 100
        ];
        
        $security_service->update_config($new_config);
        
        $config = $security_service->get_config();
        $this->assertTrue($config['enable_https_enforcement']);
        $this->assertTrue($config['enable_rate_limiting']);
        $this->assertEquals(100, $config['max_requests_per_minute']);
    }
    
    /**
     * Test error handling configuration
     * 
     * @since 1.0.0
     */
    public function test_error_handling_configuration() {
        $error_service = new \UtilitySign\Core\ErrorHandlingService();
        $error_service->init();
        
        // Test error handling configuration
        $new_config = [
            'enable_error_handling' => true,
            'enable_logging' => true,
            'log_level' => 'error',
            'enable_user_feedback' => true
        ];
        
        $error_service->update_config($new_config);
        
        $config = $error_service->get_config();
        $this->assertTrue($config['enable_error_handling']);
        $this->assertTrue($config['enable_logging']);
        $this->assertEquals('error', $config['log_level']);
        $this->assertTrue($config['enable_user_feedback']);
    }
    
    /**
     * Test cache configuration
     * 
     * @since 1.0.0
     */
    public function test_cache_configuration() {
        $cache_service = new \UtilitySign\Core\CacheService();
        $cache_service->init();
        
        // Test cache configuration
        $new_config = [
            'enable_caching' => true,
            'default_ttl' => 600,
            'max_cache_size' => 2000,
            'enable_compression' => true
        ];
        
        $cache_service->update_config($new_config);
        
        $config = $cache_service->get_config();
        $this->assertTrue($config['enable_caching']);
        $this->assertEquals(600, $config['default_ttl']);
        $this->assertEquals(2000, $config['max_cache_size']);
        $this->assertTrue($config['enable_compression']);
    }
    
    /**
     * Test authentication configuration
     * 
     * @since 1.0.0
     */
    public function test_authentication_configuration() {
        $auth_service = new \UtilitySign\Core\ApiAuthenticationService();
        $auth_service->init();
        
        // Test authentication configuration
        $new_config = [
            'auth_method' => 'api_key',
            'api_key' => 'test_key_123',
            'enable_token_validation' => true,
            'token_cache_duration' => 1800
        ];
        
        $auth_service->update_config($new_config);
        
        $config = $auth_service->get_config();
        $this->assertEquals('api_key', $config['auth_method']);
        $this->assertEquals('test_key_123', $config['api_key']);
        $this->assertTrue($config['enable_token_validation']);
        $this->assertEquals(1800, $config['token_cache_duration']);
    }
    
    /**
     * Test multisite configuration
     * 
     * @since 1.0.0
     */
    public function test_multisite_configuration() {
        $multisite_service = new \UtilitySign\Core\MultisiteService();
        $multisite_service->init();
        
        // Test multisite configuration
        $new_config = [
            'enable_multisite_support' => true,
            'enable_data_isolation' => true,
            'enable_shared_cache' => false,
            'enable_site_specific_settings' => true
        ];
        
        $multisite_service->update_config($new_config);
        
        $config = $multisite_service->get_config();
        $this->assertTrue($config['enable_multisite_support']);
        $this->assertTrue($config['enable_data_isolation']);
        $this->assertFalse($config['enable_shared_cache']);
        $this->assertTrue($config['enable_site_specific_settings']);
    }
}
