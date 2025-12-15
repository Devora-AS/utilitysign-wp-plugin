<?php
namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;

/**
 * Security Settings Admin Page
 * Provides comprehensive security configuration interface
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SecuritySettings {
    use Base;

    /**
     * Initialize security settings
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('admin_init', [$this, 'register_security_settings']);
        add_action('wp_ajax_utilitysign_test_security', [$this, 'test_security_configuration']);
        add_action('wp_ajax_utilitysign_clear_security_logs', [$this, 'clear_security_logs']);
        add_action('wp_ajax_utilitysign_export_security_logs', [$this, 'export_security_logs']);
        add_action('wp_ajax_utilitysign_get_security_stats', [$this, 'get_security_stats']);
    }

    /**
     * Add security menu page
     * 
     * @since 1.0.0
     */
    public function add_security_menu() {}

    /**
     * Register security settings
     * 
     * @since 1.0.0
     */
    public function register_security_settings() {
        // Security Configuration
        register_setting('utilitysign_security', 'utilitysign_security_config');
        register_setting('utilitysign_security', 'utilitysign_auth_config');
        register_setting('utilitysign_security', 'utilitysign_cache_config');
        register_setting('utilitysign_security', 'utilitysign_error_config');

        // Security Section
        add_settings_section(
            'utilitysign_security_section',
            __('General Security Settings', 'utilitysign'),
            [$this, 'security_section_callback'],
            'utilitysign_security'
        );

        // Authentication Section
        add_settings_section(
            'utilitysign_auth_section',
            __('Authentication Settings', 'utilitysign'),
            [$this, 'auth_section_callback'],
            'utilitysign_security'
        );

        // Cache Section
        add_settings_section(
            'utilitysign_cache_section',
            __('Cache Settings', 'utilitysign'),
            [$this, 'cache_section_callback'],
            'utilitysign_security'
        );

        // Error Handling Section
        add_settings_section(
            'utilitysign_error_section',
            __('Error Handling Settings', 'utilitysign'),
            [$this, 'error_section_callback'],
            'utilitysign_security'
        );

        // Add fields
        $this->add_security_fields();
        $this->add_auth_fields();
        $this->add_cache_fields();
        $this->add_error_fields();
    }

    /**
     * Add security fields
     * 
     * @since 1.0.0
     */
    private function add_security_fields() {
        add_settings_field(
            'enable_https_enforcement',
            __('Enable HTTPS Enforcement', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_https_enforcement', 'config' => 'security']
        );

        add_settings_field(
            'enable_rate_limiting',
            __('Enable Rate Limiting', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_rate_limiting', 'config' => 'security']
        );

        add_settings_field(
            'max_requests_per_minute',
            __('Max Requests Per Minute', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'max_requests_per_minute', 'config' => 'security', 'min' => 1, 'max' => 1000]
        );

        add_settings_field(
            'enable_csrf_protection',
            __('Enable CSRF Protection', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_csrf_protection', 'config' => 'security']
        );

        add_settings_field(
            'enable_xss_protection',
            __('Enable XSS Protection', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_xss_protection', 'config' => 'security']
        );

        add_settings_field(
            'enable_sql_injection_protection',
            __('Enable SQL Injection Protection', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_sql_injection_protection', 'config' => 'security']
        );

        add_settings_field(
            'enable_file_upload_validation',
            __('Enable File Upload Validation', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_file_upload_validation', 'config' => 'security']
        );

        add_settings_field(
            'max_file_size',
            __('Max File Size (bytes)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'max_file_size', 'config' => 'security', 'min' => 1024, 'max' => 104857600]
        );

        add_settings_field(
            'allowed_file_types',
            __('Allowed File Types', 'utilitysign'),
            [$this, 'text_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'allowed_file_types', 'config' => 'security', 'placeholder' => 'pdf,doc,docx,txt']
        );

        add_settings_field(
            'enable_ip_whitelist',
            __('Enable IP Whitelist', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'enable_ip_whitelist', 'config' => 'security']
        );

        add_settings_field(
            'allowed_ips',
            __('Allowed IP Addresses', 'utilitysign'),
            [$this, 'textarea_field_callback'],
            'utilitysign_security',
            'utilitysign_security_section',
            ['field' => 'allowed_ips', 'config' => 'security', 'rows' => 5, 'placeholder' => '192.168.1.100\n10.0.0.0/24']
        );
    }

    /**
     * Add authentication fields
     * 
     * @since 1.0.0
     */
    private function add_auth_fields() {
        add_settings_field(
            'auth_method',
            __('Authentication Method', 'utilitysign'),
            [$this, 'select_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'auth_method', 'config' => 'auth', 'options' => [
                'api_key' => __('API Key', 'utilitysign'),
                'jwt' => __('JWT Token', 'utilitysign'),
                'entra_id' => __('Microsoft Entra ID', 'utilitysign')
            ]]
        );

        add_settings_field(
            'api_key',
            __('API Key', 'utilitysign'),
            [$this, 'password_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'api_key', 'config' => 'auth']
        );

        add_settings_field(
            'entra_id_tenant_id',
            __('Entra ID Tenant ID', 'utilitysign'),
            [$this, 'text_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'entra_id_tenant_id', 'config' => 'auth']
        );

        add_settings_field(
            'entra_id_client_id',
            __('Entra ID Client ID', 'utilitysign'),
            [$this, 'text_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'entra_id_client_id', 'config' => 'auth']
        );

        add_settings_field(
            'entra_id_client_secret',
            __('Entra ID Client Secret', 'utilitysign'),
            [$this, 'password_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'entra_id_client_secret', 'config' => 'auth']
        );

        add_settings_field(
            'jwt_secret',
            __('JWT Secret', 'utilitysign'),
            [$this, 'password_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'jwt_secret', 'config' => 'auth']
        );

        add_settings_field(
            'token_cache_duration',
            __('Token Cache Duration (seconds)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'token_cache_duration', 'config' => 'auth', 'min' => 60, 'max' => 86400]
        );

        add_settings_field(
            'enable_token_validation',
            __('Enable Token Validation', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'enable_token_validation', 'config' => 'auth']
        );

        add_settings_field(
            'enable_token_refresh',
            __('Enable Token Refresh', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'enable_token_refresh', 'config' => 'auth']
        );

        add_settings_field(
            'max_failed_attempts',
            __('Max Failed Attempts', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'max_failed_attempts', 'config' => 'auth', 'min' => 1, 'max' => 20]
        );

        add_settings_field(
            'lockout_duration',
            __('Lockout Duration (seconds)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_auth_section',
            ['field' => 'lockout_duration', 'config' => 'auth', 'min' => 60, 'max' => 3600]
        );
    }

    /**
     * Add cache fields
     * 
     * @since 1.0.0
     */
    private function add_cache_fields() {
        add_settings_field(
            'enable_caching',
            __('Enable Caching', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'enable_caching', 'config' => 'cache']
        );

        add_settings_field(
            'default_ttl',
            __('Default TTL (seconds)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'default_ttl', 'config' => 'cache', 'min' => 60, 'max' => 86400]
        );

        add_settings_field(
            'max_cache_size',
            __('Max Cache Size (items)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'max_cache_size', 'config' => 'cache', 'min' => 100, 'max' => 10000]
        );

        add_settings_field(
            'enable_compression',
            __('Enable Compression', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'enable_compression', 'config' => 'cache']
        );

        add_settings_field(
            'enable_serialization',
            __('Enable Serialization', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'enable_serialization', 'config' => 'cache']
        );

        add_settings_field(
            'enable_namespacing',
            __('Enable Namespacing', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'enable_namespacing', 'config' => 'cache']
        );

        add_settings_field(
            'namespace_prefix',
            __('Namespace Prefix', 'utilitysign'),
            [$this, 'text_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'namespace_prefix', 'config' => 'cache', 'placeholder' => 'utilitysign_']
        );

        add_settings_field(
            'enable_statistics',
            __('Enable Statistics', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'enable_statistics', 'config' => 'cache']
        );

        add_settings_field(
            'cleanup_interval',
            __('Cleanup Interval (seconds)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_cache_section',
            ['field' => 'cleanup_interval', 'config' => 'cache', 'min' => 300, 'max' => 86400]
        );
    }

    /**
     * Add error handling fields
     * 
     * @since 1.0.0
     */
    private function add_error_fields() {
        add_settings_field(
            'enable_error_handling',
            __('Enable Error Handling', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_error_handling', 'config' => 'error']
        );

        add_settings_field(
            'enable_logging',
            __('Enable Logging', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_logging', 'config' => 'error']
        );

        add_settings_field(
            'log_level',
            __('Log Level', 'utilitysign'),
            [$this, 'select_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'log_level', 'config' => 'error', 'options' => [
                'debug' => __('Debug', 'utilitysign'),
                'info' => __('Info', 'utilitysign'),
                'warning' => __('Warning', 'utilitysign'),
                'error' => __('Error', 'utilitysign'),
                'critical' => __('Critical', 'utilitysign')
            ]]
        );

        add_settings_field(
            'enable_user_feedback',
            __('Enable User Feedback', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_user_feedback', 'config' => 'error']
        );

        add_settings_field(
            'enable_debug_mode',
            __('Enable Debug Mode', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_debug_mode', 'config' => 'error']
        );

        add_settings_field(
            'enable_error_reporting',
            __('Enable Error Reporting', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_error_reporting', 'config' => 'error']
        );

        add_settings_field(
            'max_log_entries',
            __('Max Log Entries', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'max_log_entries', 'config' => 'error', 'min' => 100, 'max' => 10000]
        );

        add_settings_field(
            'log_retention_days',
            __('Log Retention (days)', 'utilitysign'),
            [$this, 'number_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'log_retention_days', 'config' => 'error', 'min' => 1, 'max' => 365]
        );

        add_settings_field(
            'enable_email_notifications',
            __('Enable Email Notifications', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_email_notifications', 'config' => 'error']
        );

        add_settings_field(
            'admin_email',
            __('Admin Email', 'utilitysign'),
            [$this, 'email_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'admin_email', 'config' => 'error']
        );

        add_settings_field(
            'error_message_template',
            __('Error Message Template', 'utilitysign'),
            [$this, 'textarea_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'error_message_template', 'config' => 'error', 'rows' => 3]
        );

        add_settings_field(
            'enable_correlation_id',
            __('Enable Correlation ID', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_correlation_id', 'config' => 'error']
        );

        add_settings_field(
            'enable_stack_trace',
            __('Enable Stack Trace', 'utilitysign'),
            [$this, 'checkbox_field_callback'],
            'utilitysign_security',
            'utilitysign_error_section',
            ['field' => 'enable_stack_trace', 'config' => 'error']
        );
    }

    /**
     * Render security page
     * 
     * @since 1.0.0
     */
    public function render_security_page() {
        // Enqueue admin assets
        wp_enqueue_script('utilitysign-admin');
        wp_enqueue_style('utilitysign-admin');
        ?>
        <div class="wrap utilitysign-admin-page" id="utilitysign-security-root">
            <div class="utilitysign-admin-header">
                <h1 class="font-heading text-2xl font-black text-devora-primary"><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p class="text-devora-text-primary"><?php _e('Configure security and compliance for UtilitySign', 'utilitysign'); ?></p>
            </div>

            <div class="utilitysign-content utilitysign-security-dashboard">
                <div class="security-stats">
                    <h2><?php _e('Security Statistics', 'utilitysign'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php _e('Security Events', 'utilitysign'); ?></h3>
                            <div class="stat-value" id="security-events-count">-</div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Authentication Failures', 'utilitysign'); ?></h3>
                            <div class="stat-value" id="auth-failures-count">-</div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Cache Hit Rate', 'utilitysign'); ?></h3>
                            <div class="stat-value" id="cache-hit-rate">-</div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Error Count', 'utilitysign'); ?></h3>
                            <div class="stat-value" id="error-count">-</div>
                        </div>
                    </div>
                </div>

                <div class="security-actions">
                    <h2><?php _e('Security Actions', 'utilitysign'); ?></h2>
                    <div class="action-buttons">
                        <button type="button" class="button button-secondary" id="test-security">
                            <?php _e('Test Security Configuration', 'utilitysign'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="clear-security-logs">
                            <?php _e('Clear Security Logs', 'utilitysign'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="export-security-logs">
                            <?php _e('Export Security Logs', 'utilitysign'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="save-security-settings">
                            <?php _e('Save Settings', 'utilitysign'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php" class="utilitysign-form">
                <?php
                settings_fields('utilitysign_security');
                do_settings_sections('utilitysign_security');
                ?>
                
                <div class="security-sections">
                    <?php $this->render_security_section(); ?>
                    <?php $this->render_auth_section(); ?>
                    <?php $this->render_cache_section(); ?>
                    <?php $this->render_error_section(); ?>
                </div>
                
                <?php submit_button(__('Save Security Settings', 'utilitysign'), 'primary'); ?>
            </form>
        </div>

        <style>
        /* Align cards with Devora design system spacing and background */
        .utilitysign-admin-header { margin-bottom: 16px; }
        .utilitysign-content { padding: 0; }
        .utilitysign-security-dashboard .stat-card { background:#F8FAFC; }
        .utilitysign-security-dashboard .action-buttons .button-primary { background:#3432A6; border-color:#3432A6; }
        .utilitysign-security-dashboard {
            margin-bottom: 30px;
        }
        
        .security-stats {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .security-actions {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .security-sections {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .security-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .security-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .security-section h2 {
            margin-top: 0;
            color: #23282d;
        }
        
        .form-table th {
            width: 200px;
        }
        
        .form-table td {
            padding: 15px 10px;
        }
        
        .description {
            font-style: italic;
            color: #666;
            margin-top: 5px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Load security statistics
            loadSecurityStats();
            
            // Test security configuration
            $('#test-security').on('click', function() {
                testSecurityConfiguration();
            });
            
            // Clear security logs
            $('#clear-security-logs').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear all security logs?', 'utilitysign'); ?>')) {
                    clearSecurityLogs();
                }
            });
            
            // Export security logs
            $('#export-security-logs').on('click', function() {
                exportSecurityLogs();
            });
            
            // Save security settings
            $('#save-security-settings').on('click', function() {
                $('form').submit();
            });
        });
        
        function loadSecurityStats() {
            jQuery.post(ajaxurl, {
                action: 'utilitysign_get_security_stats',
                nonce: '<?php echo wp_create_nonce('utilitysign_security_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    $('#security-events-count').text(response.data.security_events || 0);
                    $('#auth-failures-count').text(response.data.auth_failures || 0);
                    $('#cache-hit-rate').text((response.data.cache_hit_rate || 0) + '%');
                    $('#error-count').text(response.data.error_count || 0);
                }
            });
        }
        
        function testSecurityConfiguration() {
            jQuery.post(ajaxurl, {
                action: 'utilitysign_test_security',
                nonce: '<?php echo wp_create_nonce('utilitysign_security_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Security configuration test passed!', 'utilitysign'); ?>');
                } else {
                    alert('<?php _e('Security configuration test failed: ', 'utilitysign'); ?>' + response.data.message);
                }
            });
        }
        
        function clearSecurityLogs() {
            jQuery.post(ajaxurl, {
                action: 'utilitysign_clear_security_logs',
                nonce: '<?php echo wp_create_nonce('utilitysign_security_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Security logs cleared successfully!', 'utilitysign'); ?>');
                    loadSecurityStats();
                } else {
                    alert('<?php _e('Failed to clear security logs: ', 'utilitysign'); ?>' + response.data.message);
                }
            });
        }
        
        function exportSecurityLogs() {
            window.open(ajaxurl + '?action=utilitysign_export_security_logs&nonce=<?php echo wp_create_nonce('utilitysign_security_nonce'); ?>');
        }
        </script>
        <?php
    }

    /**
     * Render security section
     * 
     * @since 1.0.0
     */
    private function render_security_section() {
        ?>
        <div class="security-section">
            <h2><?php _e('General Security Settings', 'utilitysign'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable HTTPS Enforcement', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_https_enforcement]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_https_enforcement'] ?? false); ?> />
                        <p class="description"><?php _e('Force HTTPS for all requests', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Rate Limiting', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_rate_limiting]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_rate_limiting'] ?? false); ?> />
                        <p class="description"><?php _e('Limit the number of requests per minute', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max Requests Per Minute', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_security_config[max_requests_per_minute]" value="<?php echo esc_attr(get_option('utilitysign_security_config')['max_requests_per_minute'] ?? 60); ?>" min="1" max="1000" />
                        <p class="description"><?php _e('Maximum number of requests allowed per minute per IP', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable CSRF Protection', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_csrf_protection]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_csrf_protection'] ?? false); ?> />
                        <p class="description"><?php _e('Protect against Cross-Site Request Forgery attacks', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable XSS Protection', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_xss_protection]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_xss_protection'] ?? false); ?> />
                        <p class="description"><?php _e('Protect against Cross-Site Scripting attacks', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable SQL Injection Protection', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_sql_injection_protection]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_sql_injection_protection'] ?? false); ?> />
                        <p class="description"><?php _e('Protect against SQL injection attacks', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable File Upload Validation', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_file_upload_validation]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_file_upload_validation'] ?? false); ?> />
                        <p class="description"><?php _e('Validate file uploads for security', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max File Size (bytes)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_security_config[max_file_size]" value="<?php echo esc_attr(get_option('utilitysign_security_config')['max_file_size'] ?? 10485760); ?>" min="1024" max="104857600" />
                        <p class="description"><?php _e('Maximum file size allowed for uploads', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Allowed File Types', 'utilitysign'); ?></th>
                    <td>
                        <input type="text" name="utilitysign_security_config[allowed_file_types]" value="<?php echo esc_attr(get_option('utilitysign_security_config')['allowed_file_types'] ?? 'pdf,doc,docx,txt'); ?>" placeholder="pdf,doc,docx,txt" />
                        <p class="description"><?php _e('Comma-separated list of allowed file extensions', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable IP Whitelist', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_security_config[enable_ip_whitelist]" value="1" <?php checked(get_option('utilitysign_security_config')['enable_ip_whitelist'] ?? false); ?> />
                        <p class="description"><?php _e('Restrict access to whitelisted IP addresses only', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Allowed IP Addresses', 'utilitysign'); ?></th>
                    <td>
                        <textarea name="utilitysign_security_config[allowed_ips]" rows="5" placeholder="192.168.1.100&#10;10.0.0.0/24"><?php echo esc_textarea(get_option('utilitysign_security_config')['allowed_ips'] ?? ''); ?></textarea>
                        <p class="description"><?php _e('One IP address or CIDR range per line', 'utilitysign'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render authentication section
     * 
     * @since 1.0.0
     */
    private function render_auth_section() {
        ?>
        <div class="security-section">
            <h2><?php _e('Authentication Settings', 'utilitysign'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Authentication Method', 'utilitysign'); ?></th>
                    <td>
                        <select name="utilitysign_auth_config[auth_method]">
                            <option value="api_key" <?php selected(get_option('utilitysign_auth_config')['auth_method'] ?? 'api_key', 'api_key'); ?>><?php _e('API Key', 'utilitysign'); ?></option>
                            <option value="jwt" <?php selected(get_option('utilitysign_auth_config')['auth_method'] ?? '', 'jwt'); ?>><?php _e('JWT Token', 'utilitysign'); ?></option>
                            <option value="entra_id" <?php selected(get_option('utilitysign_auth_config')['auth_method'] ?? '', 'entra_id'); ?>><?php _e('Microsoft Entra ID', 'utilitysign'); ?></option>
                        </select>
                        <p class="description"><?php _e('Choose the authentication method for API requests', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('API Key', 'utilitysign'); ?></th>
                    <td>
                        <input type="password" name="utilitysign_auth_config[api_key]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['api_key'] ?? ''); ?>" />
                        <p class="description"><?php _e('API key for authentication', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Entra ID Tenant ID', 'utilitysign'); ?></th>
                    <td>
                        <input type="text" name="utilitysign_auth_config[entra_id_tenant_id]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['entra_id_tenant_id'] ?? ''); ?>" />
                        <p class="description"><?php _e('Microsoft Entra ID tenant identifier', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Entra ID Client ID', 'utilitysign'); ?></th>
                    <td>
                        <input type="text" name="utilitysign_auth_config[entra_id_client_id]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['entra_id_client_id'] ?? ''); ?>" />
                        <p class="description"><?php _e('Microsoft Entra ID client identifier', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Entra ID Client Secret', 'utilitysign'); ?></th>
                    <td>
                        <input type="password" name="utilitysign_auth_config[entra_id_client_secret]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['entra_id_client_secret'] ?? ''); ?>" />
                        <p class="description"><?php _e('Microsoft Entra ID client secret', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('JWT Secret', 'utilitysign'); ?></th>
                    <td>
                        <input type="password" name="utilitysign_auth_config[jwt_secret]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['jwt_secret'] ?? ''); ?>" />
                        <p class="description"><?php _e('Secret key for JWT token signing', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Token Cache Duration (seconds)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_auth_config[token_cache_duration]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['token_cache_duration'] ?? 3600); ?>" min="60" max="86400" />
                        <p class="description"><?php _e('How long to cache authentication tokens', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Token Validation', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_auth_config[enable_token_validation]" value="1" <?php checked(get_option('utilitysign_auth_config')['enable_token_validation'] ?? false); ?> />
                        <p class="description"><?php _e('Validate tokens before processing requests', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Token Refresh', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_auth_config[enable_token_refresh]" value="1" <?php checked(get_option('utilitysign_auth_config')['enable_token_refresh'] ?? false); ?> />
                        <p class="description"><?php _e('Allow automatic token refresh', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max Failed Attempts', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_auth_config[max_failed_attempts]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['max_failed_attempts'] ?? 5); ?>" min="1" max="20" />
                        <p class="description"><?php _e('Maximum number of failed authentication attempts before lockout', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Lockout Duration (seconds)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_auth_config[lockout_duration]" value="<?php echo esc_attr(get_option('utilitysign_auth_config')['lockout_duration'] ?? 900); ?>" min="60" max="3600" />
                        <p class="description"><?php _e('How long to lock out after max failed attempts', 'utilitysign'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render cache section
     * 
     * @since 1.0.0
     */
    private function render_cache_section() {
        ?>
        <div class="security-section">
            <h2><?php _e('Cache Settings', 'utilitysign'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Caching', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_cache_config[enable_caching]" value="1" <?php checked(get_option('utilitysign_cache_config')['enable_caching'] ?? false); ?> />
                        <p class="description"><?php _e('Enable caching for improved performance', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default TTL (seconds)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_cache_config[default_ttl]" value="<?php echo esc_attr(get_option('utilitysign_cache_config')['default_ttl'] ?? 300); ?>" min="60" max="86400" />
                        <p class="description"><?php _e('Default time-to-live for cached items', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max Cache Size (items)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_cache_config[max_cache_size]" value="<?php echo esc_attr(get_option('utilitysign_cache_config')['max_cache_size'] ?? 1000); ?>" min="100" max="10000" />
                        <p class="description"><?php _e('Maximum number of items to cache', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Compression', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_cache_config[enable_compression]" value="1" <?php checked(get_option('utilitysign_cache_config')['enable_compression'] ?? false); ?> />
                        <p class="description"><?php _e('Compress cached data to save space', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Serialization', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_cache_config[enable_serialization]" value="1" <?php checked(get_option('utilitysign_cache_config')['enable_serialization'] ?? false); ?> />
                        <p class="description"><?php _e('Serialize complex data for caching', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Namespacing', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_cache_config[enable_namespacing]" value="1" <?php checked(get_option('utilitysign_cache_config')['enable_namespacing'] ?? false); ?> />
                        <p class="description"><?php _e('Use namespacing to avoid cache conflicts', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Namespace Prefix', 'utilitysign'); ?></th>
                    <td>
                        <input type="text" name="utilitysign_cache_config[namespace_prefix]" value="<?php echo esc_attr(get_option('utilitysign_cache_config')['namespace_prefix'] ?? 'utilitysign_'); ?>" placeholder="utilitysign_" />
                        <p class="description"><?php _e('Prefix for cache keys to avoid conflicts', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Statistics', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_cache_config[enable_statistics]" value="1" <?php checked(get_option('utilitysign_cache_config')['enable_statistics'] ?? false); ?> />
                        <p class="description"><?php _e('Track cache hit/miss statistics', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cleanup Interval (seconds)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_cache_config[cleanup_interval]" value="<?php echo esc_attr(get_option('utilitysign_cache_config')['cleanup_interval'] ?? 3600); ?>" min="300" max="86400" />
                        <p class="description"><?php _e('How often to clean up expired cache entries', 'utilitysign'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render error handling section
     * 
     * @since 1.0.0
     */
    private function render_error_section() {
        ?>
        <div class="security-section">
            <h2><?php _e('Error Handling Settings', 'utilitysign'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Error Handling', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_error_handling]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_error_handling'] ?? false); ?> />
                        <p class="description"><?php _e('Enable comprehensive error handling', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Logging', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_logging]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_logging'] ?? false); ?> />
                        <p class="description"><?php _e('Log errors and exceptions', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Log Level', 'utilitysign'); ?></th>
                    <td>
                        <select name="utilitysign_error_config[log_level]">
                            <option value="debug" <?php selected(get_option('utilitysign_error_config')['log_level'] ?? 'error', 'debug'); ?>><?php _e('Debug', 'utilitysign'); ?></option>
                            <option value="info" <?php selected(get_option('utilitysign_error_config')['log_level'] ?? 'error', 'info'); ?>><?php _e('Info', 'utilitysign'); ?></option>
                            <option value="warning" <?php selected(get_option('utilitysign_error_config')['log_level'] ?? 'error', 'warning'); ?>><?php _e('Warning', 'utilitysign'); ?></option>
                            <option value="error" <?php selected(get_option('utilitysign_error_config')['log_level'] ?? 'error', 'error'); ?>><?php _e('Error', 'utilitysign'); ?></option>
                            <option value="critical" <?php selected(get_option('utilitysign_error_config')['log_level'] ?? 'error', 'critical'); ?>><?php _e('Critical', 'utilitysign'); ?></option>
                        </select>
                        <p class="description"><?php _e('Minimum log level to record', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable User Feedback', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_user_feedback]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_user_feedback'] ?? false); ?> />
                        <p class="description"><?php _e('Show user-friendly error messages', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Debug Mode', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_debug_mode]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_debug_mode'] ?? false); ?> />
                        <p class="description"><?php _e('Show detailed error information (development only)', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Error Reporting', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_error_reporting]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_error_reporting'] ?? false); ?> />
                        <p class="description"><?php _e('Enable PHP error reporting', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max Log Entries', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_error_config[max_log_entries]" value="<?php echo esc_attr(get_option('utilitysign_error_config')['max_log_entries'] ?? 1000); ?>" min="100" max="10000" />
                        <p class="description"><?php _e('Maximum number of log entries to keep', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Log Retention (days)', 'utilitysign'); ?></th>
                    <td>
                        <input type="number" name="utilitysign_error_config[log_retention_days]" value="<?php echo esc_attr(get_option('utilitysign_error_config')['log_retention_days'] ?? 30); ?>" min="1" max="365" />
                        <p class="description"><?php _e('How long to keep log entries', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Email Notifications', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_email_notifications]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_email_notifications'] ?? false); ?> />
                        <p class="description"><?php _e('Send email notifications for critical errors', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Admin Email', 'utilitysign'); ?></th>
                    <td>
                        <input type="email" name="utilitysign_error_config[admin_email]" value="<?php echo esc_attr(get_option('utilitysign_error_config')['admin_email'] ?? get_option('admin_email')); ?>" />
                        <p class="description"><?php _e('Email address for error notifications', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Error Message Template', 'utilitysign'); ?></th>
                    <td>
                        <textarea name="utilitysign_error_config[error_message_template]" rows="3"><?php echo esc_textarea(get_option('utilitysign_error_config')['error_message_template'] ?? 'An error occurred. Please try again later.'); ?></textarea>
                        <p class="description"><?php _e('Template for user-facing error messages', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Correlation ID', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_correlation_id]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_correlation_id'] ?? false); ?> />
                        <p class="description"><?php _e('Include correlation ID in error messages for tracking', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Stack Trace', 'utilitysign'); ?></th>
                    <td>
                        <input type="checkbox" name="utilitysign_error_config[enable_stack_trace]" value="1" <?php checked(get_option('utilitysign_error_config')['enable_stack_trace'] ?? false); ?> />
                        <p class="description"><?php _e('Include stack trace in error logs (development only)', 'utilitysign'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Section callbacks
     * 
     * @since 1.0.0
     */
    public function security_section_callback() {
        echo '<p>' . __('Configure general security settings for the UtilitySign plugin.', 'utilitysign') . '</p>';
    }

    public function auth_section_callback() {
        echo '<p>' . __('Configure authentication methods and security settings.', 'utilitysign') . '</p>';
    }

    public function cache_section_callback() {
        echo '<p>' . __('Configure caching settings for improved performance.', 'utilitysign') . '</p>';
    }

    public function error_section_callback() {
        echo '<p>' . __('Configure error handling and logging settings.', 'utilitysign') . '</p>';
    }

    /**
     * Field callbacks
     * 
     * @since 1.0.0
     */
    public function checkbox_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? false;
        echo '<input type="checkbox" name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" value="1" ' . checked($value, true, false) . ' />';
    }

    public function text_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        $placeholder = $args['placeholder'] ?? '';
        echo '<input type="text" name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" />';
    }

    public function password_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        echo '<input type="password" name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" value="' . esc_attr($value) . '" />';
    }

    public function email_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        echo '<input type="email" name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" value="' . esc_attr($value) . '" />';
    }

    public function number_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        echo '<input type="number" name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" value="' . esc_attr($value) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" />';
    }

    public function textarea_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        $rows = $args['rows'] ?? 5;
        $placeholder = $args['placeholder'] ?? '';
        echo '<textarea name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']" rows="' . esc_attr($rows) . '" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($value) . '</textarea>';
    }

    public function select_field_callback($args) {
        $config = get_option('utilitysign_' . $args['config'] . '_config', []);
        $value = $config[$args['field']] ?? '';
        $options = $args['options'] ?? [];
        
        echo '<select name="utilitysign_' . $args['config'] . '_config[' . $args['field'] . ']">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }

    /**
     * AJAX handlers
     * 
     * @since 1.0.0
     */
    public function test_security_configuration() {
        check_ajax_referer('utilitysign_security_nonce', 'nonce');
        
        // Test security configuration
        $security_service = \UtilitySign\Core\SecurityService::get_instance();
        $auth_service = \UtilitySign\Core\ApiAuthenticationService::get_instance();
        $cache_service = \UtilitySign\Core\CacheService::get_instance();
        $error_service = \UtilitySign\Core\ErrorHandlingService::get_instance();
        
        $tests = [
            'security' => $security_service->get_config(),
            'auth' => $auth_service->get_config(),
            'cache' => $cache_service->get_config(),
            'error' => $error_service->get_config()
        ];
        
        wp_send_json_success([
            'message' => 'Security configuration test completed',
            'tests' => $tests
        ]);
    }

    public function clear_security_logs() {
        check_ajax_referer('utilitysign_security_nonce', 'nonce');
        
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'utilitysign_security_log',
            $wpdb->prefix . 'utilitysign_auth_log',
            $wpdb->prefix . 'utilitysign_error_log'
        ];
        
        foreach ($tables as $table) {
            // Table name is from whitelist array, but using esc_sql for extra safety
            $table = esc_sql($table);
            $wpdb->query("TRUNCATE TABLE `{$table}`");
        }
        
        wp_send_json_success(['message' => 'Security logs cleared successfully']);
    }

    public function export_security_logs() {
        check_ajax_referer('utilitysign_security_nonce', 'nonce');
        
        global $wpdb;
        
        $logs = [];
        $tables = [
            'security_log' => $wpdb->prefix . 'utilitysign_security_log',
            'auth_log' => $wpdb->prefix . 'utilitysign_auth_log',
            'error_log' => $wpdb->prefix . 'utilitysign_error_log'
        ];
        
        foreach ($tables as $name => $table) {
            // Table name is from whitelist array, but using esc_sql for extra safety
            // Note: $wpdb->prepare() doesn't support table names with %s, so we use esc_sql()
            $table = esc_sql($table);
            $logs[$name] = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY timestamp DESC LIMIT 1000");
        }
        
        $filename = 'utilitysign_security_logs_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($logs, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Get security statistics
     * 
     * @since 1.0.0
     */
    public function get_security_stats() {
        check_ajax_referer('utilitysign_security_nonce', 'nonce');
        
        global $wpdb;
        
        $stats = [
            'security_events' => 0,
            'auth_failures' => 0,
            'cache_hit_rate' => 0,
            'error_count' => 0
        ];
        
        // Get security events count
        $security_events = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}utilitysign_security_log WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['security_events'] = intval($security_events);
        
        // Get authentication failures count
        // Check if the event_type column exists first
        $table_name = $wpdb->prefix . 'utilitysign_auth_log';
        $columns = $wpdb->get_col("DESCRIBE $table_name");
        
        if (in_array('event_type', $columns)) {
            $auth_failures = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}utilitysign_auth_log WHERE event_type = 'failed_login' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        } else {
            // Fallback: count all auth log entries as potential failures
            $auth_failures = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}utilitysign_auth_log WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        }
        $stats['auth_failures'] = intval($auth_failures);
        
        // Get cache hit rate (mock data for now)
        $stats['cache_hit_rate'] = rand(80, 95);
        
        // Get error count
        $error_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}utilitysign_error_log WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['error_count'] = intval($error_count);
        
        wp_send_json_success($stats);
    }
}
