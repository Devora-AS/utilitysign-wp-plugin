<?php
/**
 * API Client for UtilitySign Backend
 * 
 * Handles all communication with the Azure backend API.
 * Uses WordPress HTTP API for reliable, secure requests.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Core;

defined('ABSPATH') || exit;

class ApiClient {
    /**
     * API base URL
     * 
     * @var string
     */
    private $api_url;
    
    /**
     * API Key
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * API Secret
     * 
     * @var string
     */
    private $api_secret;
    
    /**
     * Authentication token (cached)
     * 
     * @var string|null
     */
    private $auth_token = null;
    
    /**
     * Token expiration time
     * 
     * @var int|null
     */
    private $token_expires_at = null;
    
    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        
        $this->api_url = get_option('utilitysign_api_url', 'https://api.utilitysign.devora.no');
        
        // Get plugin key from WordPress option (saved by Settings.php)
        $api_key = get_option('utilitysign_api_key', '');
        $api_secret = get_option('utilitysign_api_secret', '');
        
        // Decrypt if encrypted (security enhancement)
        // Note: Only decrypt if the decryption would actually produce a value
        // If the value looks encrypted but decryption returns empty, treat the original value as plaintext
        if (!empty($api_key) && \UtilitySign\Utils\Security::is_encrypted_secret($api_key)) {
            $decrypted_key = \UtilitySign\Utils\Security::decrypt_secret($api_key);
            if (!empty($decrypted_key)) {
                $api_key = $decrypted_key;
            }
            // Otherwise keep the original $api_key value
        }
        
        if (!empty($api_secret) && \UtilitySign\Utils\Security::is_encrypted_secret($api_secret)) {
            $decrypted_secret = \UtilitySign\Utils\Security::decrypt_secret($api_secret);
            if (!empty($decrypted_secret)) {
                $api_secret = $decrypted_secret;
            }
            // Otherwise keep the original $api_secret value (it's not actually encrypted)
        }
        
        // For backward compatibility: if no key found, try to extract from main settings
        if (empty($api_key)) {
            $settings = get_option('utilitysign_settings', array());
            if (is_array($settings) && !empty($settings['pluginKey'])) {
                $api_key = $settings['pluginKey'];
                if (\UtilitySign\Utils\Security::is_encrypted_secret($api_key)) {
                    $api_key = \UtilitySign\Utils\Security::decrypt_secret($api_key);
                }
            }
        }
        
        // For backward compatibility: if no secret found, try to extract from main settings
        // Priority 1: pluginSecret (correct field)
        // Priority 2: clientSecret (fallback - user may have entered it in wrong field)
        if (empty($api_secret)) {
            $settings = get_option('utilitysign_settings', array());
            if (is_array($settings)) {
                // Try pluginSecret first (correct field)
                if (!empty($settings['pluginSecret'])) {
                    $api_secret = $settings['pluginSecret'];
                    if (\UtilitySign\Utils\Security::is_encrypted_secret($api_secret)) {
                        $api_secret = \UtilitySign\Utils\Security::decrypt_secret($api_secret);
                    }
                }
                // Fallback to clientSecret if pluginSecret is empty (user may have used wrong field)
                elseif (!empty($settings['clientSecret'])) {
                    $api_secret = $settings['clientSecret'];
                    if (\UtilitySign\Utils\Security::is_encrypted_secret($api_secret)) {
                        $api_secret = \UtilitySign\Utils\Security::decrypt_secret($api_secret);
                    }
                }
            }
        }
        
        // Backend API requires both plugin key and secret for authentication
        // These are sent as X-API-Key and X-API-Secret headers
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        
        // Load cached authentication token from WordPress transient (persists across requests)
        // This eliminates the ~0.3s authentication delay on subsequent requests
        if (!empty($api_key)) {
            $transient_key = 'utilitysign_api_token_' . md5($api_key);
            $cached_token_data = get_transient($transient_key);
            
            if ($cached_token_data && is_array($cached_token_data)) {
                $this->auth_token = isset($cached_token_data['token']) ? $cached_token_data['token'] : null;
                $this->token_expires_at = isset($cached_token_data['expires_at']) ? $cached_token_data['expires_at'] : null;
                
                // Validate expiration (safety check)
                if ($this->token_expires_at && time() >= $this->token_expires_at) {
                    // Token expired, clear it
                    $this->auth_token = null;
                    $this->token_expires_at = null;
                    delete_transient($transient_key);
                }
            }
        }
    }
    
    /**
     * Authenticate with backend API
     * 
     * @since 1.0.0
     * @return array|WP_Error Response data or error
     */
    public function authenticate() {
        // Check if we have cached token that's still valid
        if ($this->auth_token && $this->token_expires_at && time() < $this->token_expires_at) {
            return array(
                'success' => true,
                'accessToken' => $this->auth_token, // Use 'accessToken' for consistency with backend response
                'cached' => true
            );
        }
        
        // DEBUG: Log credentials being sent (masked for security)
        error_log('UtilitySign Auth Debug: api_key=' . substr($this->api_key, 0, 10) . '... (len=' . strlen($this->api_key) . '), api_secret=' . substr($this->api_secret, 0, 10) . '... (len=' . strlen($this->api_secret) . ')');

        $response = $this->request('POST', '/api/v1/wordpress/authenticate', array(
            'PluginKey' => $this->api_key,
            'PluginSecret' => $this->api_secret
        ), false); // Don't include auth token for authentication request
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Backend returns 'accessToken' (not 'token')
        if (isset($response['accessToken'])) {
            $this->auth_token = $response['accessToken'];
            
            // Use expiresAt from backend response if available, otherwise default to 1 hour
            if (isset($response['expiresAt'])) {
                $expires_at = strtotime($response['expiresAt']);
                if ($expires_at !== false) {
                    $this->token_expires_at = $expires_at;
                } else {
                    // Fallback to 1 hour if parsing fails
                    $this->token_expires_at = time() + 3600;
                }
            } else {
                // Default to 1 hour if expiresAt not provided
                $this->token_expires_at = time() + 3600;
            }
            
            // Cache token in WordPress transient for persistence across requests
            // This eliminates the ~0.3s authentication delay on subsequent requests
            if (!empty($this->api_key) && $this->token_expires_at) {
                $transient_key = 'utilitysign_api_token_' . md5($this->api_key);
                $ttl = max(1, $this->token_expires_at - time()); // TTL in seconds (minimum 1 second)
                
                set_transient($transient_key, array(
                    'token' => $this->auth_token,
                    'expires_at' => $this->token_expires_at
                ), $ttl);
            }
            
            // Return normalized response format (consistent with cached path)
            return array(
                'success' => true,
                'accessToken' => $this->auth_token,
                'expiresAt' => isset($response['expiresAt']) ? $response['expiresAt'] : null
            );
        }
        
        // If no accessToken in response, return the raw response (may contain error info)
        return $response;
    }
    
    /**
     * Get products for this supplier
     * 
     * @since 1.0.0
     * @return array|WP_Error Array of products or error
     */
    public function get_products() {
        // Backend expects POST with empty body (not GET)
        return $this->request('POST', '/api/v1/wordpress/products', array());
    }
    
    /**
     * Get single product by ID
     * 
     * @since 1.0.0
     * @param string $product_id Product UUID
     * @return array|WP_Error Product data or error
     */
    public function get_product($product_id) {
        // #region agent log
        $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
        $log_entry = json_encode(array('id'=>'log_'.time().'_php8','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:210','message'=>'get_product entry','data'=>array('productId'=>$product_id,'apiUrl'=>$this->api_url,'hasAuthToken'=>!empty($this->auth_token),'endpoint'=>'/api/v1/wordpress/products/'.$product_id),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        // #endregion
        // Backend endpoint: GET /api/v1/wordpress/products/{id}
        $result = $this->request('GET', "/api/v1/wordpress/products/{$product_id}");
        // #region agent log
        $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
        $log_entry = json_encode(array('id'=>'log_'.time().'_php9','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:213','message'=>'get_product request completed','data'=>array('isWpError'=>is_wp_error($result),'errorCode'=>is_wp_error($result)?$result->get_error_code():null,'errorMessage'=>is_wp_error($result)?$result->get_error_message():null,'isArray'=>is_array($result),'hasName'=>is_array($result)&&isset($result['name']),'hasTitle'=>is_array($result)&&isset($result['title'])),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        // #endregion
        return $result;
    }
    
    /**
     * Get product terms (contract terms)
     * 
     * @since 1.0.0
     * @param string $product_id Product ID
     * @return array|WP_Error Product terms or error
     */
    public function get_product_terms($product_id) {
        // Correct endpoint path: /api/v1/product-terms/product/{productId}
        return $this->request('GET', "/api/v1/product-terms/product/{$product_id}");
    }
    
    /**
     * Submit new order
     * 
     * @since 1.0.0
     * @param array $order_data Order data
     * @return array|WP_Error Order response or error
     */
    public function submit_order($order_data) {
        return $this->request('POST', '/api/v1/wordpress/orders', $order_data);
    }
    
    /**
     * Get order status
     * 
     * @since 1.0.0
     * @param string $order_id Order ID
     * @return array|WP_Error Order status or error
     */
    public function get_order_status($order_id) {
        return $this->request('GET', "/api/v1/wordpress/orders/{$order_id}");
    }
    
    /**
     * Get signing status
     * 
     * @since 1.0.0
     * @param string $signing_id Signing request ID
     * @return array|WP_Error Signing status or error
     */
    public function get_signing_status($signing_id) {
        // Correct endpoint path: /api/v1/signing/{id}
        return $this->request('GET', "/api/v1/signing/{$signing_id}");
    }
    
    /**
     * Test API connection
     * 
     * @since 1.0.0
     * @return array|WP_Error Connection test result
     */
    public function test_connection() {
        $auth_result = $this->authenticate();
        
        if (is_wp_error($auth_result)) {
            return array(
                'success' => false,
                'message' => 'Authentication failed: ' . $auth_result->get_error_message()
            );
        }
        
        // Check for 'accessToken' (normalized format used by authenticate())
        if (!isset($auth_result['accessToken'])) {
            return array(
                'success' => false,
                'message' => 'No authentication token received'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Connection successful!',
            'token_received' => true
        );
    }
    
    /**
     * Generic request method
     * 
     * @since 1.0.0
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @param bool $include_auth Whether to include authentication token
     * @return array|WP_Error Response data or error
     */
    private function request($method, $endpoint, $data = null, $include_auth = true) {
        // Ensure we're authenticated (unless this IS the authentication request)
        if ($include_auth) {
            $auth_result = $this->authenticate();
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'sslverify' => true, // Always verify SSL in production
        );
        
        // Add authentication header if we have a token
        if ($include_auth && $this->auth_token) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->auth_token;
        }
        
        // Add API key headers for authentication endpoint
        if (!$include_auth) {
            $args['headers']['X-API-Key'] = $this->api_key;
            $args['headers']['X-API-Secret'] = $this->api_secret;
        }
        
        // Add request body for POST/PUT
        if ($data && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
        }
        
        // #region agent log
        $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
        $log_entry = json_encode(array('id'=>'log_'.time().'_php10','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:339','message'=>'Making wp_remote_request','data'=>array('url'=>$url,'method'=>$method,'hasAuthToken'=>!empty($this->auth_token),'hasApiKey'=>!empty($this->api_key),'hasApiSecret'=>!empty($this->api_secret)),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        // #endregion
        
        // Make request
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            // #region agent log
            $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
            $log_entry = json_encode(array('id'=>'log_'.time().'_php11','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:342','message'=>'wp_remote_request WP_Error','data'=>array('errorCode'=>$response->get_error_code(),'errorMessage'=>$response->get_error_message()),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            // #endregion
            error_log('UtilitySign API Error: ' . $response->get_error_message());
            return $response;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // #region agent log
        $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
        $log_entry = json_encode(array('id'=>'log_'.time().'_php12','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:348','message'=>'wp_remote_request response received','data'=>array('responseCode'=>$response_code,'bodyLength'=>strlen($body),'bodyPreview'=>substr($body,0,200),'isErrorCode'=>$response_code>=400),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        // #endregion
        
        // Parse JSON response
        $parsed_body = json_decode($body, true);
        
        // Handle error responses
        if ($response_code >= 400) {
            $error_message = isset($parsed_body['message']) 
                ? $parsed_body['message'] 
                : "HTTP {$response_code}: Request failed";
            
            // #region agent log
            $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
            $log_entry = json_encode(array('id'=>'log_'.time().'_php13','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:355','message'=>'Backend API error response','data'=>array('responseCode'=>$response_code,'errorMessage'=>$error_message,'parsedBodyKeys'=>is_array($parsed_body)?array_keys($parsed_body):array()),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            // #endregion
            
            error_log("UtilitySign API Error ({$response_code}): {$error_message}");
            
            return new \WP_Error(
                'utilitysign_api_error',
                $error_message,
                array('status' => $response_code)
            );
        }
        
        // #region agent log
        $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
        $log_entry = json_encode(array('id'=>'log_'.time().'_php14','timestamp'=>round(microtime(true)*1000),'location'=>'ApiClient.php:370','message'=>'Backend API success response','data'=>array('responseCode'=>$response_code,'parsedBodyKeys'=>is_array($parsed_body)?array_keys($parsed_body):array(),'hasName'=>is_array($parsed_body)&&isset($parsed_body['name']),'hasTitle'=>is_array($parsed_body)&&isset($parsed_body['title'])),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        // #endregion
        
        // Return parsed response
        return $parsed_body;
    }
    
    /**
     * Check if API credentials are configured
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_configured() {
        // Both plugin key and plugin secret are required for authentication
        // They are sent as X-API-Key and X-API-Secret headers to the backend
        return !empty($this->api_key) && !empty($this->api_secret);
    }
    
    /**
     * Get API configuration status
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config_status() {
        return array(
            'api_url' => $this->api_url,
            'has_plugin_key' => !empty($this->api_key),
            'has_plugin_secret' => !empty($this->api_secret),
            'is_configured' => $this->is_configured()
        );
    }
}

