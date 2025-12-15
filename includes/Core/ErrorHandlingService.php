<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;

/**
 * Error Handling Service for UtilitySign WordPress Plugin
 * Provides comprehensive error handling, logging, and user feedback
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ErrorHandlingService {
    use Base;

    /**
     * Error configuration
     * 
     * @var array
     */
    private $config;

    /**
     * Error log
     * 
     * @var array
     */
    private $errorLog = [];

    /**
     * Initialize error handling service
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->config = $this->get_error_config();
        $this->init_error_handlers();
        $this->init_logging();
    }

    /**
     * Get error configuration
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_error_config() {
        return wp_parse_args(
            get_option('utilitysign_error_config', []),
            [
                'enable_error_handling' => true,
                'enable_logging' => true,
                'log_level' => 'error',
                'enable_user_feedback' => true,
                'enable_debug_mode' => false,
                'enable_error_reporting' => true,
                'max_log_entries' => 1000,
                'log_retention_days' => 30,
                'enable_email_notifications' => false,
                'admin_email' => get_option('admin_email'),
                'enable_api_error_handling' => true,
                'enable_frontend_error_handling' => true,
                'error_message_template' => 'An error occurred. Please try again later.',
                'enable_correlation_id' => true,
                'enable_stack_trace' => false,
            ]
        );
    }

    /**
     * Initialize error handlers
     * 
     * @since 1.0.0
     */
    private function init_error_handlers() {
        if (!$this->config['enable_error_handling']) {
            return;
        }

        // Set error reporting level
        if ($this->config['enable_error_reporting']) {
            error_reporting(E_ALL);
            ini_set('display_errors', $this->config['enable_debug_mode'] ? 1 : 0);
        }

        // Set custom error handler
        set_error_handler([$this, 'handle_php_error']);

        // Set custom exception handler
        set_exception_handler([$this, 'handle_exception']);

        // Set shutdown handler for fatal errors
        register_shutdown_function([$this, 'handle_shutdown']);

        // Add AJAX error handling
        add_action('wp_ajax_utilitysign_*', [$this, 'handle_ajax_error'], 1);
        add_action('wp_ajax_nopriv_utilitysign_*', [$this, 'handle_ajax_error'], 1);
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

        // Add logging hooks
        add_action('utilitysign_error_log', [$this, 'log_error'], 10, 2);
        add_action('utilitysign_warning_log', [$this, 'log_warning'], 10, 2);
        add_action('utilitysign_info_log', [$this, 'log_info'], 10, 2);
    }

    /**
     * Handle PHP errors
     * 
     * @since 1.0.0
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        $error_types = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        $error_type = $error_types[$errno] ?? 'Unknown Error';
        
        $error_data = [
            'type' => $error_type,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'severity' => $this->get_error_severity($errno),
            'timestamp' => current_time('mysql'),
            'correlation_id' => $this->generate_correlation_id(),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'site_id' => get_current_blog_id()
        ];

        $this->log_error($error_data);

        // Send email notification for critical errors
        if ($this->is_critical_error($errno) && $this->config['enable_email_notifications']) {
            $this->send_error_notification($error_data);
        }

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle exceptions
     * 
     * @since 1.0.0
     * @param Throwable $exception
     */
    public function handle_exception($exception) {
        $error_data = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'severity' => 'critical',
            'timestamp' => current_time('mysql'),
            'correlation_id' => $this->generate_correlation_id(),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'site_id' => get_current_blog_id(),
            'stack_trace' => $this->config['enable_stack_trace'] ? $exception->getTraceAsString() : null
        ];

        $this->log_error($error_data);

        // Send email notification for exceptions
        if ($this->config['enable_email_notifications']) {
            $this->send_error_notification($error_data);
        }

        // Handle AJAX requests
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $this->get_user_friendly_message($error_data),
                'code' => 'EXCEPTION_ERROR',
                'correlation_id' => $error_data['correlation_id'],
                'timestamp' => $error_data['timestamp']
            ], 500);
        }

        // Handle regular requests
        if (!$this->config['enable_debug_mode']) {
            wp_die(
                $this->get_user_friendly_message($error_data),
                'Error',
                ['response' => 500]
            );
        }
    }

    /**
     * Handle shutdown errors
     * 
     * @since 1.0.0
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && $this->is_fatal_error($error['type'])) {
            $error_data = [
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'severity' => 'critical',
                'timestamp' => current_time('mysql'),
                'correlation_id' => $this->generate_correlation_id(),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'site_id' => get_current_blog_id()
            ];

            $this->log_error($error_data);

            // Send email notification for fatal errors
            if ($this->config['enable_email_notifications']) {
                $this->send_error_notification($error_data);
            }
        }
    }

    /**
     * Handle AJAX errors
     * 
     * @since 1.0.0
     */
    public function handle_ajax_error() {
        // This will be called before the actual AJAX handler
        // We can add pre-processing here if needed
    }

    /**
     * Log error
     * 
     * @since 1.0.0
     * @param array $error_data
     * @param string $level
     */
    public function log_error($error_data, $level = 'error') {
        if (!$this->config['enable_logging']) {
            return;
        }

        // Add to error log
        $this->errorLog[] = $error_data;

        // Store in database
        $this->store_error_log($error_data);

        // Log to WordPress error log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[UtilitySign %s] %s in %s on line %d - %s',
                strtoupper($level),
                $error_data['message'],
                $error_data['file'],
                $error_data['line'],
                $error_data['correlation_id']
            );
            
            error_log($log_message);
        }
    }

    /**
     * Log warning
     * 
     * @since 1.0.0
     * @param array $warning_data
     * @param string $level
     */
    public function log_warning($warning_data, $level = 'warning') {
        $this->log_error($warning_data, $level);
    }

    /**
     * Log info
     * 
     * @since 1.0.0
     * @param array $info_data
     * @param string $level
     */
    public function log_info($info_data, $level = 'info') {
        $this->log_error($info_data, $level);
    }

    /**
     * Store error log in database
     * 
     * @since 1.0.0
     * @param array $error_data
     */
    private function store_error_log($error_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        
        $wpdb->insert(
            $table_name,
            [
                'type' => $error_data['type'],
                'message' => $error_data['message'],
                'file' => $error_data['file'],
                'line' => $error_data['line'],
                'severity' => $error_data['severity'],
                'correlation_id' => $error_data['correlation_id'],
                'user_id' => $error_data['user_id'],
                'ip_address' => $error_data['ip_address'],
                'user_agent' => $error_data['user_agent'],
                'request_uri' => $error_data['request_uri'],
                'request_method' => $error_data['request_method'],
                'stack_trace' => $error_data['stack_trace'] ?? null,
                'site_id' => $error_data['site_id'],
                'timestamp' => $error_data['timestamp']
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Send error notification email
     * 
     * @since 1.0.0
     * @param array $error_data
     */
    private function send_error_notification($error_data) {
        if (empty($this->config['admin_email'])) {
            return;
        }

        $subject = sprintf(
            '[%s] %s Error: %s',
            get_bloginfo('name'),
            $error_data['type'],
            substr($error_data['message'], 0, 100)
        );

        $message = $this->format_error_email($error_data);

        wp_mail(
            $this->config['admin_email'],
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /**
     * Format error email
     * 
     * @since 1.0.0
     * @param array $error_data
     * @return string
     */
    private function format_error_email($error_data) {
        $html = '<html><body>';
        $html .= '<h2>Error Notification</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<tr><td><strong>Type:</strong></td><td>' . esc_html($error_data['type']) . '</td></tr>';
        $html .= '<tr><td><strong>Message:</strong></td><td>' . esc_html($error_data['message']) . '</td></tr>';
        $html .= '<tr><td><strong>File:</strong></td><td>' . esc_html($error_data['file']) . '</td></tr>';
        $html .= '<tr><td><strong>Line:</strong></td><td>' . esc_html($error_data['line']) . '</td></tr>';
        $html .= '<tr><td><strong>Severity:</strong></td><td>' . esc_html($error_data['severity']) . '</td></tr>';
        $html .= '<tr><td><strong>Correlation ID:</strong></td><td>' . esc_html($error_data['correlation_id']) . '</td></tr>';
        $html .= '<tr><td><strong>User ID:</strong></td><td>' . esc_html($error_data['user_id']) . '</td></tr>';
        $html .= '<tr><td><strong>IP Address:</strong></td><td>' . esc_html($error_data['ip_address']) . '</td></tr>';
        $html .= '<tr><td><strong>User Agent:</strong></td><td>' . esc_html($error_data['user_agent']) . '</td></tr>';
        $html .= '<tr><td><strong>Request URI:</strong></td><td>' . esc_html($error_data['request_uri']) . '</td></tr>';
        $html .= '<tr><td><strong>Request Method:</strong></td><td>' . esc_html($error_data['request_method']) . '</td></tr>';
        $html .= '<tr><td><strong>Site ID:</strong></td><td>' . esc_html($error_data['site_id']) . '</td></tr>';
        $html .= '<tr><td><strong>Timestamp:</strong></td><td>' . esc_html($error_data['timestamp']) . '</td></tr>';
        
        if (!empty($error_data['stack_trace'])) {
            $html .= '<tr><td><strong>Stack Trace:</strong></td><td><pre>' . esc_html($error_data['stack_trace']) . '</pre></td></tr>';
        }
        
        $html .= '</table>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get user-friendly error message
     * 
     * @since 1.0.0
     * @param array $error_data
     * @return string
     */
    private function get_user_friendly_message($error_data) {
        if (!$this->config['enable_user_feedback']) {
            return $this->config['error_message_template'];
        }

        $correlation_id = $error_data['correlation_id'] ?? 'N/A';
        
        return sprintf(
            '%s (Reference: %s)',
            $this->config['error_message_template'],
            $correlation_id
        );
    }

    /**
     * Get error severity
     * 
     * @since 1.0.0
     * @param int $errno
     * @return string
     */
    private function get_error_severity($errno) {
        $severity_map = [
            E_ERROR => 'critical',
            E_PARSE => 'critical',
            E_CORE_ERROR => 'critical',
            E_COMPILE_ERROR => 'critical',
            E_USER_ERROR => 'error',
            E_RECOVERABLE_ERROR => 'error',
            E_WARNING => 'warning',
            E_CORE_WARNING => 'warning',
            E_COMPILE_WARNING => 'warning',
            E_USER_WARNING => 'warning',
            E_NOTICE => 'notice',
            E_USER_NOTICE => 'notice',
            E_STRICT => 'notice',
            E_DEPRECATED => 'notice',
            E_USER_DEPRECATED => 'notice'
        ];

        return $severity_map[$errno] ?? 'unknown';
    }

    /**
     * Check if error is critical
     * 
     * @since 1.0.0
     * @param int $errno
     * @return bool
     */
    private function is_critical_error($errno) {
        $critical_errors = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR
        ];

        return in_array($errno, $critical_errors);
    }

    /**
     * Check if error is fatal
     * 
     * @since 1.0.0
     * @param int $type
     * @return bool
     */
    private function is_fatal_error($type) {
        $fatal_errors = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR
        ];

        return in_array($type, $fatal_errors);
    }

    /**
     * Generate correlation ID
     * 
     * @since 1.0.0
     * @return string
     */
    private function generate_correlation_id() {
        if (!$this->config['enable_correlation_id']) {
            return '';
        }

        return 'err_' . time() . '_' . wp_generate_password(8, false);
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
     * Get error statistics
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_error_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        
        $stats = $wpdb->get_results("
            SELECT 
                severity,
                COUNT(*) as count,
                DATE(timestamp) as date
            FROM {$table_name}
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY severity, DATE(timestamp)
            ORDER BY date DESC
        ");

        return $stats;
    }

    /**
     * Clean old error logs
     * 
     * @since 1.0.0
     * @param int $days
     */
    public function clean_old_logs($days = null) {
        $days = $days ?? $this->config['log_retention_days'];
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }

    /**
     * Update error configuration
     * 
     * @since 1.0.0
     * @param array $new_config
     */
    public function update_config($new_config) {
        $this->config = wp_parse_args($new_config, $this->config);
        update_option('utilitysign_error_config', $this->config);
    }

    /**
     * Get error configuration
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
}
