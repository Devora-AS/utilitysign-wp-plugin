<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;
use UtilitySign\Utils\Security;

/**
 * API Authentication Service for UtilitySign WordPress Plugin
 * Handles Microsoft Entra ID JWT authentication and API key validation
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ApiAuthenticationService {
    use Base;

    /**
     * Authentication configuration
     * 
     * @var array
     */
    private $config;

    /**
     * JWT token cache
     * 
     * @var array
     */
    private $tokenCache = [];

    /**
     * Initialize authentication service
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->config = $this->get_auth_config();
        $this->init_authentication_hooks();
    }

    /**
     * Get authentication configuration
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_auth_config() {
        return wp_parse_args(
            get_option('utilitysign_auth_config', []),
            [
                'auth_method' => 'api_key',
                'api_key' => '',
                'entra_id_tenant_id' => '',
                'entra_id_client_id' => '',
                'entra_id_client_secret' => '',
                'jwt_secret' => wp_generate_password(64, false),
                'token_cache_duration' => 3600, // 1 hour
                'enable_token_validation' => true,
                'enable_token_refresh' => true,
                'token_refresh_threshold' => 300, // 5 minutes before expiry
                'enable_audit_logging' => true,
                'max_failed_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
            ]
        );
    }

    /**
     * Initialize authentication hooks
     * 
     * @since 1.0.0
     */
    private function init_authentication_hooks() {
        // Add authentication to all API endpoints
        add_action('wp_ajax_utilitysign_*', [$this, 'authenticate_request'], 1);
        add_action('wp_ajax_nopriv_utilitysign_*', [$this, 'authenticate_request'], 1);
        
        // Add token refresh endpoint
        add_action('wp_ajax_utilitysign_refresh_token', [$this, 'handle_token_refresh']);
        add_action('wp_ajax_nopriv_utilitysign_refresh_token', [$this, 'handle_token_refresh']);
    }

    /**
     * Authenticate API request
     * 
     * @since 1.0.0
     */
    public function authenticate_request() {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
        
        $is_authenticated = false;
        $auth_method = 'none';
        
        // Try JWT authentication first
        if (!empty($auth_header) && strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            $is_authenticated = $this->validate_jwt_token($token);
            $auth_method = 'jwt';
        }
        
        // Try API key authentication
        if (!$is_authenticated && !empty($api_key)) {
            $is_authenticated = $this->validate_api_key($api_key);
            $auth_method = 'api_key';
        }
        
        // Try Entra ID authentication
        if (!$is_authenticated && !empty($auth_header) && strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            $is_authenticated = $this->validate_entra_id_token($token);
            $auth_method = 'entra_id';
        }
        
        if (!$is_authenticated) {
            $this->log_auth_failure('authentication_failed', [
                'method' => $auth_method,
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'endpoint' => current_action()
            ]);
            
            wp_send_json_error([
                'message' => 'Authentication failed',
                'code' => 'AUTH_FAILED',
                'timestamp' => current_time('mysql')
            ], 401);
        }
        
        // Log successful authentication
        $this->log_auth_success($auth_method, [
            'ip' => $this->get_client_ip(),
            'endpoint' => current_action()
        ]);
    }

    /**
     * Validate JWT token
     * 
     * @since 1.0.0
     * @param string $token
     * @return bool
     */
    private function validate_jwt_token($token) {
        if (!$this->config['enable_token_validation']) {
            return true;
        }
        
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $header = json_decode($this->base64_url_decode($parts[0]), true);
            $payload = json_decode($this->base64_url_decode($parts[1]), true);
            $signature = $parts[2];
            
            // Verify signature
            $expected_signature = $this->create_jwt_signature($parts[0] . '.' . $parts[1], $this->config['jwt_secret']);
            if (!hash_equals($signature, $expected_signature)) {
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Check audience
            if (isset($payload['aud']) && $payload['aud'] !== get_site_url()) {
                return false;
            }
            
            // Cache valid token
            $this->tokenCache[$token] = [
                'payload' => $payload,
                'expires_at' => $payload['exp'] ?? (time() + 3600)
            ];
            
            return true;
        } catch (\Exception $e) {
            error_log('[UtilitySign Auth] JWT validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate API key
     * 
     * @since 1.0.0
     * @param string $api_key
     * @return bool
     */
    private function validate_api_key($api_key) {
        if (empty($this->config['api_key'])) {
            return false;
        }
        
        return hash_equals($this->config['api_key'], $api_key);
    }

    /**
     * Validate Entra ID token
     * 
     * @since 1.0.0
     * @param string $token
     * @return bool
     */
    private function validate_entra_id_token($token) {
        if (empty($this->config['entra_id_tenant_id']) || 
            empty($this->config['entra_id_client_id'])) {
            return false;
        }
        
        try {
            // Get public keys from Microsoft
            $keys_url = "https://login.microsoftonline.com/{$this->config['entra_id_tenant_id']}/discovery/v2.0/keys";
            $keys_response = wp_remote_get($keys_url);
            
            if (is_wp_error($keys_response)) {
                return false;
            }
            
            $keys_data = json_decode(wp_remote_retrieve_body($keys_response), true);
            if (!$keys_data || !isset($keys_data['keys'])) {
                return false;
            }
            
            // Decode token header
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $header = json_decode($this->base64_url_decode($parts[0]), true);
            if (!$header || !isset($header['kid'])) {
                return false;
            }
            
            // Find matching key
            $public_key = null;
            foreach ($keys_data['keys'] as $key) {
                if ($key['kid'] === $header['kid']) {
                    $public_key = $this->build_public_key($key);
                    break;
                }
            }
            
            if (!$public_key) {
                return false;
            }
            
            // Verify signature
            $signature = $this->base64_url_decode($parts[2]);
            $data = $parts[0] . '.' . $parts[1];
            
            $verified = openssl_verify(
                $data,
                $signature,
                $public_key,
                OPENSSL_ALGO_SHA256
            );
            
            if ($verified !== 1) {
                return false;
            }
            
            // Decode and validate payload
            $payload = json_decode($this->base64_url_decode($parts[1]), true);
            if (!$payload) {
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Check audience
            $expected_audience = $this->config['entra_id_client_id'];
            if (isset($payload['aud']) && $payload['aud'] !== $expected_audience) {
                return false;
            }
            
            // Check issuer
            $expected_issuer = "https://login.microsoftonline.com/{$this->config['entra_id_tenant_id']}/v2.0";
            if (isset($payload['iss']) && $payload['iss'] !== $expected_issuer) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('[UtilitySign Auth] Entra ID token validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle token refresh
     * 
     * @since 1.0.0
     */
    public function handle_token_refresh() {
        $refresh_token = $_POST['refresh_token'] ?? '';
        
        if (empty($refresh_token)) {
            wp_send_json_error([
                'message' => 'Refresh token required',
                'code' => 'REFRESH_TOKEN_REQUIRED'
            ], 400);
        }
        
        try {
            $new_token = $this->refresh_entra_id_token($refresh_token);
            
            if ($new_token) {
                wp_send_json_success([
                    'access_token' => $new_token['access_token'],
                    'expires_in' => $new_token['expires_in'],
                    'token_type' => $new_token['token_type']
                ]);
            } else {
                wp_send_json_error([
                    'message' => 'Token refresh failed',
                    'code' => 'REFRESH_FAILED'
                ], 401);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Token refresh error: ' . $e->getMessage(),
                'code' => 'REFRESH_ERROR'
            ], 500);
        }
    }

    /**
     * Refresh Entra ID token
     * 
     * @since 1.0.0
     * @param string $refresh_token
     * @return array|false
     */
    private function refresh_entra_id_token($refresh_token) {
        $token_endpoint = "https://login.microsoftonline.com/{$this->config['entra_id_tenant_id']}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_endpoint, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'client_id' => $this->config['entra_id_client_id'],
                'client_secret' => $this->config['entra_id_client_secret'],
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['error'])) {
            return false;
        }
        
        return $data;
    }

    /**
     * Generate JWT token
     * 
     * @since 1.0.0
     * @param array $payload
     * @return string
     */
    public function generate_jwt_token($payload = []) {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];
        
        $default_payload = [
            'iss' => get_site_url(),
            'aud' => get_site_url(),
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour
            'sub' => get_current_user_id()
        ];
        
        $payload = array_merge($default_payload, $payload);
        
        $header_encoded = $this->base64_url_encode(json_encode($header));
        $payload_encoded = $this->base64_url_encode(json_encode($payload));
        
        $signature = $this->create_jwt_signature($header_encoded . '.' . $payload_encoded, $this->config['jwt_secret']);
        
        return $header_encoded . '.' . $payload_encoded . '.' . $signature;
    }

    /**
     * Create JWT signature
     * 
     * @since 1.0.0
     * @param string $data
     * @param string $secret
     * @return string
     */
    private function create_jwt_signature($data, $secret) {
        return $this->base64_url_encode(hash_hmac('sha256', $data, $secret, true));
    }

    /**
     * Build public key from JWK
     * 
     * @since 1.0.0
     * @param array $jwk
     * @return resource|false
     */
    private function build_public_key($jwk) {
        $modulus = $this->base64_url_decode($jwk['n']);
        $exponent = $this->base64_url_decode($jwk['e']);
        
        $modulus = $this->base64_to_bigint($modulus);
        $exponent = $this->base64_to_bigint($exponent);
        
        $public_key = [
            'n' => $modulus,
            'e' => $exponent
        ];
        
        return openssl_pkey_get_public($this->array_to_pem($public_key));
    }

    /**
     * Convert base64 to bigint
     * 
     * @since 1.0.0
     * @param string $data
     * @return string
     */
    private function base64_to_bigint($data) {
        $hex = bin2hex($data);
        return '0x' . $hex;
    }

    /**
     * Convert array to PEM format
     * 
     * @since 1.0.0
     * @param array $key
     * @return string
     */
    private function array_to_pem($key) {
        // This is a simplified implementation
        // In production, use a proper JWK to PEM conversion library
        return "-----BEGIN PUBLIC KEY-----\n" . 
               base64_encode($key['n']) . "\n" . 
               "-----END PUBLIC KEY-----";
    }

    /**
     * Base64 URL decode
     * 
     * @since 1.0.0
     * @param string $data
     * @return string
     */
    private function base64_url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Base64 URL encode
     * 
     * @since 1.0.0
     * @param string $data
     * @return string
     */
    private function base64_url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Log authentication failure
     * 
     * @since 1.0.0
     * @param string $reason
     * @param array $data
     */
    private function log_auth_failure($reason, $data = []) {
        if (!$this->config['enable_audit_logging']) {
            return;
        }
        
        $log_data = array_merge([
            'timestamp' => current_time('mysql'),
            'event' => 'auth_failure',
            'reason' => $reason,
            'site_id' => get_current_blog_id()
        ], $data);
        
        error_log('[UtilitySign Auth] ' . json_encode($log_data));
        
        // Store in database
        $this->store_auth_log($log_data);
    }

    /**
     * Log authentication success
     * 
     * @since 1.0.0
     * @param string $method
     * @param array $data
     */
    private function log_auth_success($method, $data = []) {
        if (!$this->config['enable_audit_logging']) {
            return;
        }
        
        $log_data = array_merge([
            'timestamp' => current_time('mysql'),
            'event' => 'auth_success',
            'method' => $method,
            'site_id' => get_current_blog_id()
        ], $data);
        
        error_log('[UtilitySign Auth] ' . json_encode($log_data));
        
        // Store in database
        $this->store_auth_log($log_data);
    }

    /**
     * Store authentication log
     * 
     * @since 1.0.0
     * @param array $log_data
     */
    private function store_auth_log($log_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_auth_log';
        
        $wpdb->insert(
            $table_name,
            [
                'timestamp' => $log_data['timestamp'],
                'event' => $log_data['event'],
                'event_type' => $log_data['event'],  // Duplicate event as event_type for compatibility
                'method' => $log_data['method'] ?? '',
                'reason' => $log_data['reason'] ?? '',
                'data' => json_encode($log_data),
                'site_id' => $log_data['site_id']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }

    /**
     * Get client IP address
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Update authentication configuration
     * 
     * @since 1.0.0
     * @param array $new_config
     */
    public function update_config($new_config) {
        $this->config = wp_parse_args($new_config, $this->config);
        update_option('utilitysign_auth_config', $this->config);
    }

    /**
     * Get authentication configuration
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
}
