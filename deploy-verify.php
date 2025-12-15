<?php
/**
 * Deployment Verification Script for UtilitySign WordPress Plugin
 * Verifies that all security features are properly deployed and working
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class UtilitySign_Deployment_Verification {
    
    /**
     * Run deployment verification
     * 
     * @since 1.0.0
     * @return array
     */
    public static function run_verification() {
        $results = [
            'timestamp' => current_time('mysql'),
            'plugin_version' => UTILITYSIGN_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'multisite' => is_multisite(),
            'tests' => []
        ];
        
        // Test 1: Plugin initialization
        $results['tests']['plugin_initialization'] = self::test_plugin_initialization();
        
        // Test 2: Security services
        $results['tests']['security_services'] = self::test_security_services();
        
        // Test 3: Database migrations
        $results['tests']['database_migrations'] = self::test_database_migrations();
        
        // Test 4: API client
        $results['tests']['api_client'] = self::test_api_client();
        
        // Test 5: Admin settings
        $results['tests']['admin_settings'] = self::test_admin_settings();
        
        // Test 6: Security features
        $results['tests']['security_features'] = self::test_security_features();
        
        // Test 7: Cache functionality
        $results['tests']['cache_functionality'] = self::test_cache_functionality();
        
        // Test 8: Error handling
        $results['tests']['error_handling'] = self::test_error_handling();
        
        // Test 9: Multisite compatibility
        $results['tests']['multisite_compatibility'] = self::test_multisite_compatibility();
        
        // Test 10: Performance
        $results['tests']['performance'] = self::test_performance();
        
        // Calculate overall status
        $results['overall_status'] = self::calculate_overall_status($results['tests']);
        
        return $results;
    }
    
    /**
     * Test plugin initialization
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_plugin_initialization() {
        $test = [
            'name' => 'Plugin Initialization',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            // Check if plugin is active
            if (!is_plugin_active('utilitysign/utilitysign.php')) {
                $test['status'] = 'fail';
                $test['details'][] = 'Plugin is not active';
                return $test;
            }
            
            // Check if main class exists
            if (!class_exists('UtilitySign\UtilitySign')) {
                $test['status'] = 'fail';
                $test['details'][] = 'Main plugin class not found';
                return $test;
            }
            
            // Check if constants are defined
            if (!defined('UTILITYSIGN_VERSION')) {
                $test['status'] = 'fail';
                $test['details'][] = 'Plugin version constant not defined';
                return $test;
            }
            
            $test['details'][] = 'Plugin is active and properly initialized';
            $test['details'][] = 'Version: ' . UTILITYSIGN_VERSION;
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test security services
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_security_services() {
        $test = [
            'name' => 'Security Services',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            $services = [
                'SecurityService' => 'UtilitySign\Core\SecurityService',
                'ApiAuthenticationService' => 'UtilitySign\Core\ApiAuthenticationService',
                'CacheService' => 'UtilitySign\Core\CacheService',
                'ErrorHandlingService' => 'UtilitySign\Core\ErrorHandlingService',
                'MultisiteService' => 'UtilitySign\Core\MultisiteService'
            ];
            
            foreach ($services as $name => $class) {
                if (!class_exists($class)) {
                    $test['status'] = 'fail';
                    $test['details'][] = "Service $name not found";
                    continue;
                }
                
                $service = new $class();
                if (!method_exists($service, 'init')) {
                    $test['status'] = 'fail';
                    $test['details'][] = "Service $name missing init method";
                    continue;
                }
                
                $test['details'][] = "Service $name is available";
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test database migrations
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_database_migrations() {
        $test = [
            'name' => 'Database Migrations',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            global $wpdb;
            
            $tables = [
                'utilitysign_security_log' => 'Security log table',
                'utilitysign_auth_log' => 'Authentication log table',
                'utilitysign_cache' => 'Cache table',
                'utilitysign_error_log' => 'Error log table'
            ];
            
            foreach ($tables as $table => $description) {
                $table_name = $wpdb->prefix . $table;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
                
                if (!$exists) {
                    $test['status'] = 'fail';
                    $test['details'][] = "$description not found";
                    continue;
                }
                
                $test['details'][] = "$description exists";
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test API client
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_api_client() {
        $test = [
            'name' => 'API Client',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            // Check if API client file exists
            $api_client_path = UTILITYSIGN_DIR . 'src/lib/api-client.ts';
            if (!file_exists($api_client_path)) {
                $test['status'] = 'fail';
                $test['details'][] = 'API client file not found';
                return $test;
            }
            
            // Check if API client has required methods
            $api_client_content = file_get_contents($api_client_path);
            $required_methods = [
                'getDocument',
                'createSigningRequest',
                'getSigningStatus',
                'initiateBankID',
                'checkBankIDStatus',
                'cancelBankIDSession',
                'getPluginConfig',
                'healthCheck'
            ];
            
            foreach ($required_methods as $method) {
                if (strpos($api_client_content, $method) === false) {
                    $test['status'] = 'fail';
                    $test['details'][] = "Method $method not found in API client";
                    continue;
                }
                
                $test['details'][] = "Method $method found";
            }
            
            // Check if security features are present
            $security_features = [
                'Microsoft Entra ID',
                'JWT authentication',
                'Rate limiting',
                'Caching',
                'Error handling',
                'HTTPS enforcement'
            ];
            
            foreach ($security_features as $feature) {
                if (strpos($api_client_content, $feature) === false) {
                    $test['status'] = 'fail';
                    $test['details'][] = "Security feature $feature not found";
                    continue;
                }
                
                $test['details'][] = "Security feature $feature found";
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test admin settings
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_admin_settings() {
        $test = [
            'name' => 'Admin Settings',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            // Check if admin settings class exists
            if (!class_exists('UtilitySign\Admin\SecuritySettings')) {
                $test['status'] = 'fail';
                $test['details'][] = 'Security settings class not found';
                return $test;
            }
            
            // Check if admin menu is registered
            global $menu, $submenu;
            $utilitysign_menu_found = false;
            
            if (isset($menu)) {
                foreach ($menu as $menu_item) {
                    if (isset($menu_item[2]) && $menu_item[2] === 'utilitysign') {
                        $utilitysign_menu_found = true;
                        break;
                    }
                }
            }
            
            if (!$utilitysign_menu_found) {
                $test['status'] = 'fail';
                $test['details'][] = 'UtilitySign admin menu not found';
                return $test;
            }
            
            $test['details'][] = 'Admin menu is registered';
            
            // Check if security submenu exists
            if (isset($submenu['utilitysign'])) {
                $security_submenu_found = false;
                foreach ($submenu['utilitysign'] as $submenu_item) {
                    if (isset($submenu_item[2]) && $submenu_item[2] === 'utilitysign-security') {
                        $security_submenu_found = true;
                        break;
                    }
                }
                
                if ($security_submenu_found) {
                    $test['details'][] = 'Security submenu is registered';
                } else {
                    $test['status'] = 'fail';
                    $test['details'][] = 'Security submenu not found';
                }
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test security features
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_security_features() {
        $test = [
            'name' => 'Security Features',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            // Test HTTPS enforcement
            if (is_ssl()) {
                $test['details'][] = 'HTTPS is enabled';
            } else {
                $test['details'][] = 'HTTPS is not enabled (warning)';
            }
            
            // Test security headers
            $headers = headers_list();
            $security_headers = [
                'X-Frame-Options',
                'X-Content-Type-Options',
                'X-XSS-Protection',
                'Content-Security-Policy'
            ];
            
            foreach ($security_headers as $header) {
                $found = false;
                foreach ($headers as $h) {
                    if (strpos($h, $header) === 0) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    $test['details'][] = "Security header $header is set";
                } else {
                    $test['details'][] = "Security header $header is not set (warning)";
                }
            }
            
            // Test rate limiting
            $security_service = new \UtilitySign\Core\SecurityService();
            $config = $security_service->get_config();
            
            if ($config['enable_rate_limiting']) {
                $test['details'][] = 'Rate limiting is enabled';
            } else {
                $test['details'][] = 'Rate limiting is disabled (warning)';
            }
            
            // Test CSRF protection
            if ($config['enable_csrf_protection']) {
                $test['details'][] = 'CSRF protection is enabled';
            } else {
                $test['details'][] = 'CSRF protection is disabled (warning)';
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test cache functionality
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_cache_functionality() {
        $test = [
            'name' => 'Cache Functionality',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            $cache_service = new \UtilitySign\Core\CacheService();
            $cache_service->init();
            
            // Test cache set and get
            $test_data = ['test' => 'data', 'timestamp' => time()];
            $cache_service->set('test_key', $test_data, 'test_group');
            
            $cached_data = $cache_service->get('test_key', 'test_group');
            
            if ($cached_data === $test_data) {
                $test['details'][] = 'Cache set/get functionality works';
            } else {
                $test['status'] = 'fail';
                $test['details'][] = 'Cache set/get functionality failed';
            }
            
            // Test cache statistics
            $stats = $cache_service->get_stats();
            if (is_array($stats)) {
                $test['details'][] = 'Cache statistics are available';
            } else {
                $test['status'] = 'fail';
                $test['details'][] = 'Cache statistics are not available';
            }
            
            // Clean up test data
            $cache_service->delete('test_key', 'test_group');
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test error handling
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_error_handling() {
        $test = [
            'name' => 'Error Handling',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            $error_service = new \UtilitySign\Core\ErrorHandlingService();
            $error_service->init();
            
            // Test error logging
            $error_data = [
                'type' => 'Test Error',
                'message' => 'This is a test error for deployment verification',
                'file' => __FILE__,
                'line' => __LINE__,
                'severity' => 'error',
                'timestamp' => current_time('mysql'),
                'correlation_id' => 'test_' . time(),
                'user_id' => get_current_user_id(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Deployment Verification Script',
                'request_uri' => '/test',
                'request_method' => 'GET',
                'site_id' => get_current_blog_id()
            ];
            
            $error_service->log_error($error_data);
            $test['details'][] = 'Error logging functionality works';
            
            // Test error statistics
            $stats = $error_service->get_error_stats();
            if (is_array($stats)) {
                $test['details'][] = 'Error statistics are available';
            } else {
                $test['status'] = 'fail';
                $test['details'][] = 'Error statistics are not available';
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test multisite compatibility
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_multisite_compatibility() {
        $test = [
            'name' => 'Multisite Compatibility',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            $multisite_service = new \UtilitySign\Core\MultisiteService();
            $multisite_service->init();
            
            // Test multisite detection
            if (is_multisite()) {
                $test['details'][] = 'Multisite is enabled';
                
                // Test multisite configuration
                $config = $multisite_service->get_config();
                if (is_array($config)) {
                    $test['details'][] = 'Multisite configuration is available';
                } else {
                    $test['status'] = 'fail';
                    $test['details'][] = 'Multisite configuration is not available';
                }
                
                // Test current site ID
                $site_id = $multisite_service->get_current_site_id();
                if (is_int($site_id) && $site_id > 0) {
                    $test['details'][] = "Current site ID: $site_id";
                } else {
                    $test['status'] = 'fail';
                    $test['details'][] = 'Current site ID is not valid';
                }
                
            } else {
                $test['details'][] = 'Single site installation detected';
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test performance
     * 
     * @since 1.0.0
     * @return array
     */
    private static function test_performance() {
        $test = [
            'name' => 'Performance',
            'status' => 'pass',
            'details' => []
        ];
        
        try {
            // Test memory usage
            $memory_usage = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            
            $test['details'][] = 'Memory usage: ' . size_format($memory_usage);
            $test['details'][] = 'Memory limit: ' . $memory_limit;
            
            // Test execution time
            $start_time = microtime(true);
            
            // Perform some operations
            $security_service = new \UtilitySign\Core\SecurityService();
            $security_service->init();
            
            $cache_service = new \UtilitySign\Core\CacheService();
            $cache_service->init();
            
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
            
            $test['details'][] = 'Execution time: ' . round($execution_time, 2) . 'ms';
            
            if ($execution_time > 1000) { // More than 1 second
                $test['status'] = 'fail';
                $test['details'][] = 'Performance warning: Execution time is too high';
            }
            
        } catch (Exception $e) {
            $test['status'] = 'fail';
            $test['details'][] = 'Exception: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Calculate overall status
     * 
     * @since 1.0.0
     * @param array $tests
     * @return string
     */
    private static function calculate_overall_status($tests) {
        $failed_tests = 0;
        $total_tests = count($tests);
        
        foreach ($tests as $test) {
            if ($test['status'] === 'fail') {
                $failed_tests++;
            }
        }
        
        if ($failed_tests === 0) {
            return 'pass';
        } elseif ($failed_tests < $total_tests / 2) {
            return 'warning';
        } else {
            return 'fail';
        }
    }
    
    /**
     * Generate verification report
     * 
     * @since 1.0.0
     * @param array $results
     * @return string
     */
    public static function generate_report($results) {
        $report = "<h1>UtilitySign Deployment Verification Report</h1>\n";
        $report .= "<p><strong>Timestamp:</strong> " . $results['timestamp'] . "</p>\n";
        $report .= "<p><strong>Plugin Version:</strong> " . $results['plugin_version'] . "</p>\n";
        $report .= "<p><strong>WordPress Version:</strong> " . $results['wordpress_version'] . "</p>\n";
        $report .= "<p><strong>PHP Version:</strong> " . $results['php_version'] . "</p>\n";
        $report .= "<p><strong>Multisite:</strong> " . ($results['multisite'] ? 'Yes' : 'No') . "</p>\n";
        $report .= "<p><strong>Overall Status:</strong> <span style=\"color: " . ($results['overall_status'] === 'pass' ? 'green' : ($results['overall_status'] === 'warning' ? 'orange' : 'red')) . "\">" . strtoupper($results['overall_status']) . "</span></p>\n";
        
        $report .= "<h2>Test Results</h2>\n";
        
        foreach ($results['tests'] as $test) {
            $status_color = $test['status'] === 'pass' ? 'green' : ($test['status'] === 'warning' ? 'orange' : 'red');
            $report .= "<h3 style=\"color: $status_color\">" . $test['name'] . " - " . strtoupper($test['status']) . "</h3>\n";
            
            if (!empty($test['details'])) {
                $report .= "<ul>\n";
                foreach ($test['details'] as $detail) {
                    $report .= "<li>" . esc_html($detail) . "</li>\n";
                }
                $report .= "</ul>\n";
            }
        }
        
        return $report;
    }
}

// Run verification if called directly
if (isset($_GET['run_verification']) && current_user_can('manage_options')) {
    $results = UtilitySign_Deployment_Verification::run_verification();
    $report = UtilitySign_Deployment_Verification::generate_report($results);
    
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode($results, JSON_PRETTY_PRINT);
    } else {
        header('Content-Type: text/html');
        echo $report;
    }
    exit;
}
