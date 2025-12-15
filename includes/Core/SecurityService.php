<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;
use UtilitySign\Utils\Security;

/**
 * Security Service for UtilitySign WordPress Plugin
 * Handles comprehensive security features including authentication, validation, and monitoring
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SecurityService {
    use Base;

    /**
     * Security configuration
     * 
     * @var array
     */
    private $config;

    /**
     * Rate limiting storage
     * 
     * @var array
     */
    private $rateLimits = [];

    /**
     * Security events log
     * 
     * @var array
     */
    private $securityLog = [];

    /**
     * Initialize security service
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->config = $this->get_security_config();
        $this->init_security_hooks();
        $this->init_rate_limiting();
        $this->init_logging();
    }

    /**
     * Get security configuration
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_security_config() {
        return \wp_parse_args(
            \get_option('utilitysign_security_config', []),
            [
                'enable_https_enforcement' => true,
                'enable_rate_limiting' => true,
                'max_requests_per_minute' => 60,
                'enable_request_validation' => true,
                'enable_logging' => true,
                'log_level' => 'info',
                'enable_csrf_protection' => true,
                'enable_xss_protection' => true,
                'enable_sql_injection_protection' => true,
                'enable_file_upload_validation' => true,
                'max_file_size' => 10485760, // 10MB
                'allowed_file_types' => ['pdf', 'doc', 'docx', 'txt'],
                'enable_ip_whitelist' => false,
                'allowed_ips' => [],
                'enable_audit_logging' => true,
                'session_timeout' => 3600, // 1 hour
                'max_login_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
                'enable_encryption' => true,
                'encryption_key' => \wp_generate_password(32, false),
            ]
        );
    }

    /**
     * Initialize security hooks
     * 
     * @since 1.0.0
     */
    private function init_security_hooks() {
        // HTTPS enforcement
        if ($this->config['enable_https_enforcement']) {
            \add_action('init', [$this, 'enforce_https']);
        }

        // Security headers
        \add_action('send_headers', [$this, 'add_security_headers']);
        
        // CORS headers for REST API
        \add_action('rest_api_init', [$this, 'add_cors_headers']);

        // Input validation
        if ($this->config['enable_request_validation']) {
            \add_action('init', [$this, 'validate_requests']);
        }

        // CSRF protection
        if ($this->config['enable_csrf_protection']) {
            \add_action('wp_ajax_utilitysign_*', [$this, 'verify_csrf_token'], 1);
            \add_action('wp_ajax_nopriv_utilitysign_*', [$this, 'verify_csrf_token'], 1);
        }

        // XSS protection
        if ($this->config['enable_xss_protection']) {
            \add_filter('wp_kses_allowed_html', [$this, 'filter_allowed_html'], 10, 2);
        }

        // SQL injection protection
        if ($this->config['enable_sql_injection_protection']) {
            \add_action('init', [$this, 'prevent_sql_injection']);
        }

        // File upload validation
        if ($this->config['enable_file_upload_validation']) {
            \add_filter('wp_handle_upload_prefilter', [$this, 'validate_file_upload']);
        }

        // IP whitelist
        if ($this->config['enable_ip_whitelist']) {
            \add_action('init', [$this, 'check_ip_whitelist']);
        }

        // Audit logging
        if ($this->config['enable_audit_logging']) {
            \add_action('wp_login', [$this, 'log_user_login'], 10, 2);
            \add_action('wp_logout', [$this, 'log_user_logout']);
            \add_action('wp_login_failed', [$this, 'log_failed_login']);
        }
    }

    /**
     * Initialize rate limiting
     * 
     * @since 1.0.0
     */
    private function init_rate_limiting() {
        if (!$this->config['enable_rate_limiting']) {
            return;
        }

        \add_action('init', [$this, 'check_rate_limit']);
    }

    /**
     * Initialize logging
     * 
     * @since 1.0.0
     */
    private function init_logging() {
        if (!$this->config['enable_logging']) {
            return;
        }

        \add_action('wp_ajax_utilitysign_*', [$this, 'log_api_request'], 1);
        \add_action('wp_ajax_nopriv_utilitysign_*', [$this, 'log_api_request'], 1);
    }

    /**
     * Enforce HTTPS
     * 
     * @since 1.0.0
     */
    public function enforce_https() {
        if (!is_ssl() && !is_admin() && !wp_doing_ajax()) {
            $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            wp_redirect($redirect_url, 301);
            exit;
        }
    }

    /**
     * Add security headers
     * 
     * @since 1.0.0
     */
    public function add_security_headers() {
        if (headers_sent()) {
            return;
        }

        // Content Security Policy
        // Allow blob: for Web Workers, consent.cookiebot.com for cookie consent
        // worker-src explicitly set to allow blob: for React app workers
        // frame-src allows Cookiebot iframe embeds from consentcdn.cookiebot.com
        // Google reCAPTCHA requires www.google.com and www.gstatic.com
        // Facebook Pixel requires connect.facebook.net for fbevents.js
        // Note: Facebook Topics API intentionally blocked for privacy (suppress feature in Facebook Pixel config)
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://consent.cookiebot.com https://www.google.com https://www.gstatic.com https://connect.facebook.net blob:; script-src-elem \'self\' \'unsafe-inline\' https://consent.cookiebot.com https://www.google.com https://www.gstatic.com https://connect.facebook.net blob:; worker-src \'self\' blob:; frame-src \'self\' https://consentcdn.cookiebot.com https://www.google.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; img-src \'self\' data: https:; font-src \'self\' data: https://fonts.gstatic.com; connect-src \'self\' https://api.utilitysign.devora.no https://api-staging.utilitysign.devora.no https://devora-utilitysign-api-staging.azurewebsites.net https://www.google.com https://www.gstatic.com https://connect.facebook.net; frame-ancestors \'self\';');

        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions-Policy
        // Use empty allowlists () to deny features (not "(none)")
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), accelerometer=(), gyroscope=(), magnetometer=(), payment=(), usb=()');

        // Strict-Transport-Security (only on HTTPS)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Add CORS headers for REST API
     * 
     * @since 1.0.0
     */
    public function add_cors_headers() {
        if (headers_sent()) {
            return;
        }

        // Allow requests from the same origin and local development
        $allowed_origins = [
            home_url(),
            'http://devora-test.local',
            'https://devora-test.local',
            'http://localhost',
            'https://localhost'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins) || empty($origin)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Validate requests
     * 
     * @since 1.0.0
     */
    public function validate_requests() {
        // Validate request method
        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])) {
            $this->log_security_event('invalid_request_method', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            wp_die('Invalid request method', 'Bad Request', ['response' => 400]);
        }

        // Validate request size
        $max_size = 8 * 1024 * 1024; // 8MB
        if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > $max_size) {
            $this->log_security_event('request_too_large', [
                'size' => $_SERVER['CONTENT_LENGTH'],
                'max_size' => $max_size,
                'ip' => $this->get_client_ip()
            ]);
            wp_die('Request too large', 'Request Entity Too Large', ['response' => 413]);
        }

        // Validate user agent
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->log_security_event('missing_user_agent', [
                'ip' => $this->get_client_ip()
            ]);
            wp_die('Missing User-Agent', 'Bad Request', ['response' => 400]);
        }
    }

    /**
     * Verify CSRF token
     * 
     * @since 1.0.0
     */
    public function verify_csrf_token() {
        $nonce = $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'utilitysign_nonce')) {
            $this->log_security_event('csrf_token_invalid', [
                'ip' => $this->get_client_ip(),
                'user_id' => get_current_user_id(),
                'action' => current_action()
            ]);
            wp_die('Security check failed', 'Forbidden', ['response' => 403]);
        }
    }

    /**
     * Filter allowed HTML for XSS protection
     * 
     * @since 1.0.0
     * @param array $allowed_html
     * @param string $context
     * @return array
     */
    public function filter_allowed_html($allowed_html, $context) {
        if ($context === 'utilitysign') {
            return [
                'div' => [
                    'class' => [],
                    'id' => [],
                    'data-*' => []
                ],
                'span' => [
                    'class' => [],
                    'id' => []
                ],
                'input' => [
                    'type' => [],
                    'name' => [],
                    'value' => [],
                    'class' => [],
                    'id' => [],
                    'placeholder' => [],
                    'required' => [],
                    'disabled' => []
                ],
                'button' => [
                    'type' => [],
                    'class' => [],
                    'id' => [],
                    'disabled' => []
                ],
                'form' => [
                    'action' => [],
                    'method' => [],
                    'class' => [],
                    'id' => []
                ]
            ];
        }
        
        return $allowed_html;
    }

    /**
     * Prevent SQL injection
     * 
     * @since 1.0.0
     */
    public function prevent_sql_injection() {
        $suspicious_patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bsp_\w+)/i',
            '/(\bxp_\w+)/i'
        ];

        $input_data = array_merge($_GET, $_POST, $_COOKIE);
        
        foreach ($input_data as $key => $value) {
            if (is_string($value)) {
                foreach ($suspicious_patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->log_security_event('sql_injection_attempt', [
                            'pattern' => $pattern,
                            'input' => $key,
                            'value' => substr($value, 0, 100),
                            'ip' => $this->get_client_ip()
                        ]);
                        wp_die('Suspicious input detected', 'Bad Request', ['response' => 400]);
                    }
                }
            }
        }
    }

    /**
     * Validate file upload
     * 
     * @since 1.0.0
     * @param array $file
     * @return array
     */
    public function validate_file_upload($file) {
        // Skip validation for WordPress core operations (plugin/theme uploads)
        // Check if this is a plugin/theme upload by examining the request context
        if (isset($file['name'])) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Check if this is a plugin/theme upload context
            $is_plugin_upload = (
                (isset($_REQUEST['action']) && $_REQUEST['action'] === 'upload-plugin') ||
                (isset($_REQUEST['action']) && $_REQUEST['action'] === 'upload-theme') ||
                (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'update.php') !== false) ||
                (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'plugin-install.php') !== false)
            );
            
            // Allow zip, gz, tar files for plugin/theme installations
            if ($is_plugin_upload && in_array($file_extension, ['zip', 'gz', 'tar'])) {
                return $file;
            }
            
            // Also allow zip files in admin context (fallback for edge cases)
            if (\is_admin() && $file_extension === 'zip') {
                return $file;
            }
        }

        // Check file size
        if ($file['size'] > $this->config['max_file_size']) {
            $this->log_security_event('file_too_large', [
                'filename' => $file['name'],
                'size' => $file['size'],
                'max_size' => $this->config['max_file_size'],
                'ip' => $this->get_client_ip()
            ]);
            $file['error'] = 'File too large. Maximum size is ' . size_format($this->config['max_file_size']);
            return $file;
        }

        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->config['allowed_file_types'])) {
            $this->log_security_event('invalid_file_type', [
                'filename' => $file['name'],
                'extension' => $file_extension,
                'allowed_types' => $this->config['allowed_file_types'],
                'ip' => $this->get_client_ip()
            ]);
            $file['error'] = 'Invalid file type. Allowed types: ' . implode(', ', $this->config['allowed_file_types']);
            return $file;
        }

        // Check for malicious content
        $file_content = file_get_contents($file['tmp_name']);
        $malicious_patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i'
        ];

        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $file_content)) {
                $this->log_security_event('malicious_file_content', [
                    'filename' => $file['name'],
                    'pattern' => $pattern,
                    'ip' => $this->get_client_ip()
                ]);
                $file['error'] = 'File contains potentially malicious content';
                return $file;
            }
        }

        return $file;
    }

    /**
     * Check IP whitelist
     * 
     * @since 1.0.0
     */
    public function check_ip_whitelist() {
        $client_ip = $this->get_client_ip();
        $allowed_ips = $this->config['allowed_ips'];

        if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
            $this->log_security_event('ip_not_whitelisted', [
                'ip' => $client_ip,
                'allowed_ips' => $allowed_ips
            ]);
            wp_die('Access denied', 'Forbidden', ['response' => 403]);
        }
    }

    /**
     * Check rate limit
     * 
     * @since 1.0.0
     */
    public function check_rate_limit() {
        if (!$this->config['enable_rate_limiting']) {
            return;
        }

        $client_ip = $this->get_client_ip();
        $current_time = time();
        $window_start = $current_time - 60; // 1 minute window

        // Clean old entries
        if (isset($this->rateLimits[$client_ip])) {
            $this->rateLimits[$client_ip] = array_filter(
                $this->rateLimits[$client_ip],
                function($timestamp) use ($window_start) {
                    return $timestamp > $window_start;
                }
            );
        } else {
            $this->rateLimits[$client_ip] = [];
        }

        // Check if limit exceeded
        if (count($this->rateLimits[$client_ip]) >= $this->config['max_requests_per_minute']) {
            $this->log_security_event('rate_limit_exceeded', [
                'ip' => $client_ip,
                'requests' => count($this->rateLimits[$client_ip]),
                'limit' => $this->config['max_requests_per_minute']
            ]);
            wp_die('Rate limit exceeded', 'Too Many Requests', ['response' => 429]);
        }

        // Add current request
        $this->rateLimits[$client_ip][] = $current_time;
    }

    /**
     * Log API request
     * 
     * @since 1.0.0
     */
    public function log_api_request() {
        $this->log_security_event('api_request', [
            'action' => current_action(),
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $this->get_client_ip(),
            'user_id' => get_current_user_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Log user login
     * 
     * @since 1.0.0
     * @param string $user_login
     * @param WP_User $user
     */
    public function log_user_login($user_login, $user) {
        $this->log_security_event('user_login', [
            'user_id' => $user->ID,
            'username' => $user_login,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Log user logout
     * 
     * @since 1.0.0
     */
    public function log_user_logout() {
        $this->log_security_event('user_logout', [
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Log failed login
     * 
     * @since 1.0.0
     * @param string $username
     */
    public function log_failed_login($username) {
        $this->log_security_event('failed_login', [
            'username' => $username,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Log security event
     * 
     * @since 1.0.0
     * @param string $event_type
     * @param array $data
     */
    private function log_security_event($event_type, $data = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'data' => $data,
            'site_id' => get_current_blog_id()
        ];

        $this->securityLog[] = $log_entry;

        // Store in database
        $this->store_security_log($log_entry);

        // Log to WordPress error log if debug mode is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[UtilitySign Security] ' . $event_type . ': ' . json_encode($data));
        }
    }

    /**
     * Store security log in database
     * 
     * @since 1.0.0
     * @param array $log_entry
     */
    private function store_security_log($log_entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_security_log';
        
        $wpdb->insert(
            $table_name,
            [
                'timestamp' => $log_entry['timestamp'],
                'event_type' => $log_entry['event_type'],
                'data' => json_encode($log_entry['data']),
                'site_id' => $log_entry['site_id']
            ],
            ['%s', '%s', '%s', '%d']
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
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
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
     * Encrypt data
     * 
     * @since 1.0.0
     * @param string $data
     * @return string
     */
    public function encrypt_data($data) {
        if (!$this->config['enable_encryption']) {
            return $data;
        }

        $key = $this->config['encryption_key'];
        $iv = \wp_generate_password(16, false);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     * 
     * @since 1.0.0
     * @param string $encrypted_data
     * @return string
     */
    public function decrypt_data($encrypted_data) {
        if (!$this->config['enable_encryption']) {
            return $encrypted_data;
        }

        $key = $this->config['encryption_key'];
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get security statistics
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_security_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_security_log';
        
        $stats = $wpdb->get_results("
            SELECT 
                event_type,
                COUNT(*) as count,
                DATE(timestamp) as date
            FROM {$table_name}
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY event_type, DATE(timestamp)
            ORDER BY date DESC
        ");

        return $stats;
    }

    /**
     * Clean old security logs
     * 
     * @since 1.0.0
     * @param int $days
     */
    public function clean_old_logs($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_security_log';
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }

    /**
     * Update security configuration
     * 
     * @since 1.0.0
     * @param array $new_config
     */
    public function update_config($new_config) {
        $this->config = \wp_parse_args($new_config, $this->config);
        \update_option('utilitysign_security_config', $this->config);
    }

    /**
     * Get security configuration
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
}
