<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;

/**
 * Multisite Service for UtilitySign WordPress Plugin
 * Handles WordPress multisite compatibility and data isolation
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class MultisiteService {
    use Base;

    /**
     * Multisite configuration
     * 
     * @var array
     */
    private $config;

    /**
     * Current site ID
     * 
     * @var int
     */
    private $current_site_id;

    /**
     * Initialize multisite service
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->config = $this->get_multisite_config();
        $this->current_site_id = get_current_blog_id();
        $this->init_multisite_hooks();
    }

    /**
     * Get multisite configuration
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_multisite_config() {
        return wp_parse_args(
            get_option('utilitysign_multisite_config', []),
            [
                'enable_multisite_support' => is_multisite(),
                'enable_data_isolation' => true,
                'enable_shared_cache' => false,
                'enable_shared_logs' => false,
                'enable_network_admin' => false,
                'enable_site_specific_settings' => true,
                'enable_network_wide_activation' => false,
                'enable_site_switching' => true,
                'enable_cross_site_requests' => false,
                'enable_network_wide_notifications' => false,
                'enable_site_specific_authentication' => true,
                'enable_network_wide_security' => false,
                'enable_site_specific_caching' => true,
                'enable_network_wide_caching' => false,
                'enable_site_specific_error_handling' => true,
                'enable_network_wide_error_handling' => false,
            ]
        );
    }

    /**
     * Initialize multisite hooks
     * 
     * @since 1.0.0
     */
    private function init_multisite_hooks() {
        if (!$this->config['enable_multisite_support']) {
            return;
        }

        // Site switching hooks
        if ($this->config['enable_site_switching']) {
            add_action('switch_blog', [$this, 'handle_site_switch']);
            add_action('restore_current_blog', [$this, 'handle_site_restore']);
        }

        // Data isolation hooks
        if ($this->config['enable_data_isolation']) {
            add_filter('utilitysign_get_option', [$this, 'filter_site_specific_option'], 10, 2);
            add_filter('utilitysign_update_option', [$this, 'filter_site_specific_option_update'], 10, 3);
            add_filter('utilitysign_delete_option', [$this, 'filter_site_specific_option_delete'], 10, 2);
        }

        // Network admin hooks
        if ($this->config['enable_network_admin'] && is_network_admin()) {
            add_action('network_admin_menu', [$this, 'add_network_admin_menu']);
            add_action('network_admin_edit_utilitysign_network_settings', [$this, 'handle_network_settings_save']);
        }

        // Cross-site request hooks
        if ($this->config['enable_cross_site_requests']) {
            add_action('wp_ajax_utilitysign_cross_site_request', [$this, 'handle_cross_site_request']);
            add_action('wp_ajax_nopriv_utilitysign_cross_site_request', [$this, 'handle_cross_site_request']);
        }

        // Network-wide notifications
        if ($this->config['enable_network_wide_notifications']) {
            add_action('utilitysign_send_notification', [$this, 'send_network_wide_notification'], 10, 2);
        }
    }

    /**
     * Handle site switch
     * 
     * @since 1.0.0
     * @param int $new_site_id
     */
    public function handle_site_switch($new_site_id) {
        $this->current_site_id = $new_site_id;
        
        // Clear any cached data for the new site
        if ($this->config['enable_site_specific_caching']) {
            $this->clear_site_cache($new_site_id);
        }
        
        // Update current site context
        $this->update_site_context($new_site_id);
    }

    /**
     * Handle site restore
     * 
     * @since 1.0.0
     */
    public function handle_site_restore() {
        $this->current_site_id = get_current_blog_id();
        
        // Restore site context
        $this->restore_site_context();
    }

    /**
     * Filter site-specific option
     * 
     * @since 1.0.0
     * @param mixed $value
     * @param string $option_name
     * @return mixed
     */
    public function filter_site_specific_option($value, $option_name) {
        if (!$this->config['enable_data_isolation']) {
            return $value;
        }

        $site_specific_option = $option_name . '_site_' . $this->current_site_id;
        return get_option($site_specific_option, $value);
    }

    /**
     * Filter site-specific option update
     * 
     * @since 1.0.0
     * @param bool $result
     * @param string $option_name
     * @param mixed $value
     * @return bool
     */
    public function filter_site_specific_option_update($result, $option_name, $value) {
        if (!$this->config['enable_data_isolation']) {
            return $result;
        }

        $site_specific_option = $option_name . '_site_' . $this->current_site_id;
        return update_option($site_specific_option, $value);
    }

    /**
     * Filter site-specific option delete
     * 
     * @since 1.0.0
     * @param bool $result
     * @param string $option_name
     * @return bool
     */
    public function filter_site_specific_option_delete($result, $option_name) {
        if (!$this->config['enable_data_isolation']) {
            return $result;
        }

        $site_specific_option = $option_name . '_site_' . $this->current_site_id;
        return delete_option($site_specific_option);
    }

    /**
     * Add network admin menu
     * 
     * @since 1.0.0
     */
    public function add_network_admin_menu() {
        add_menu_page(
            __('UtilitySign Network', 'utilitysign'),
            __('UtilitySign', 'utilitysign'),
            'manage_network_options',
            'utilitysign-network',
            [$this, 'render_network_admin_page'],
            'dashicons-email',
            3
        );

        add_submenu_page(
            'utilitysign-network',
            __('Network Settings', 'utilitysign'),
            __('Settings', 'utilitysign'),
            'manage_network_options',
            'utilitysign-network-settings',
            [$this, 'render_network_settings_page']
        );

        add_submenu_page(
            'utilitysign-network',
            __('Network Statistics', 'utilitysign'),
            __('Statistics', 'utilitysign'),
            'manage_network_options',
            'utilitysign-network-stats',
            [$this, 'render_network_stats_page']
        );
    }

    /**
     * Render network admin page
     * 
     * @since 1.0.0
     */
    public function render_network_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('UtilitySign Network Administration', 'utilitysign'); ?></h1>
            
            <div class="network-dashboard">
                <div class="network-stats">
                    <h2><?php _e('Network Statistics', 'utilitysign'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php _e('Total Sites', 'utilitysign'); ?></h3>
                            <div class="stat-value"><?php echo get_blog_count(); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Active Sites', 'utilitysign'); ?></h3>
                            <div class="stat-value"><?php echo $this->get_active_sites_count(); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Total Users', 'utilitysign'); ?></h3>
                            <div class="stat-value"><?php echo $this->get_network_users_count(); ?></div>
                        </div>
                        <div class="stat-card">
                            <h3><?php _e('Network Storage', 'utilitysign'); ?></h3>
                            <div class="stat-value"><?php echo $this->get_network_storage_usage(); ?></div>
                        </div>
                    </div>
                </div>

                <div class="network-actions">
                    <h2><?php _e('Network Actions', 'utilitysign'); ?></h2>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary" id="sync-network-settings">
                            <?php _e('Sync Network Settings', 'utilitysign'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="clear-network-cache">
                            <?php _e('Clear Network Cache', 'utilitysign'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="export-network-data">
                            <?php _e('Export Network Data', 'utilitysign'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render network settings page
     * 
     * @since 1.0.0
     */
    public function render_network_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Network Settings', 'utilitysign'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="utilitysign_network_settings" />
                <?php wp_nonce_field('utilitysign_network_settings', 'utilitysign_network_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Network-Wide Security', 'utilitysign'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_network_wide_security" value="1" <?php checked($this->config['enable_network_wide_security']); ?> />
                            <p class="description"><?php _e('Apply security settings across all sites in the network', 'utilitysign'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable Network-Wide Caching', 'utilitysign'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_network_wide_caching" value="1" <?php checked($this->config['enable_network_wide_caching']); ?> />
                            <p class="description"><?php _e('Share cache across all sites in the network', 'utilitysign'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable Network-Wide Error Handling', 'utilitysign'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_network_wide_error_handling" value="1" <?php checked($this->config['enable_network_wide_error_handling']); ?> />
                            <p class="description"><?php _e('Centralize error handling across all sites', 'utilitysign'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable Shared Logs', 'utilitysign'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_shared_logs" value="1" <?php checked($this->config['enable_shared_logs']); ?> />
                            <p class="description"><?php _e('Share logs across all sites in the network', 'utilitysign'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable Cross-Site Requests', 'utilitysign'); ?></th>
                        <td>
                            <input type="checkbox" name="enable_cross_site_requests" value="1" <?php checked($this->config['enable_cross_site_requests']); ?> />
                            <p class="description"><?php _e('Allow requests between sites in the network', 'utilitysign'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Network Settings', 'utilitysign')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render network stats page
     * 
     * @since 1.0.0
     */
    public function render_network_stats_page() {
        $stats = $this->get_network_statistics();
        ?>
        <div class="wrap">
            <h1><?php _e('Network Statistics', 'utilitysign'); ?></h1>
            
            <div class="network-stats-detail">
                <h2><?php _e('Site Statistics', 'utilitysign'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Site ID', 'utilitysign'); ?></th>
                            <th><?php _e('Site Name', 'utilitysign'); ?></th>
                            <th><?php _e('Domain', 'utilitysign'); ?></th>
                            <th><?php _e('Status', 'utilitysign'); ?></th>
                            <th><?php _e('Users', 'utilitysign'); ?></th>
                            <th><?php _e('Storage', 'utilitysign'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['sites'] as $site): ?>
                        <tr>
                            <td><?php echo $site['id']; ?></td>
                            <td><?php echo esc_html($site['name']); ?></td>
                            <td><?php echo esc_html($site['domain']); ?></td>
                            <td><?php echo $site['status']; ?></td>
                            <td><?php echo $site['users']; ?></td>
                            <td><?php echo $site['storage']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="network-stats-summary">
                <h2><?php _e('Summary Statistics', 'utilitysign'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php _e('Total API Requests', 'utilitysign'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_api_requests']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Total Security Events', 'utilitysign'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_security_events']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Total Cache Hits', 'utilitysign'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_cache_hits']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Total Errors', 'utilitysign'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_errors']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle network settings save
     * 
     * @since 1.0.0
     */
    public function handle_network_settings_save() {
        if (!wp_verify_nonce($_POST['utilitysign_network_nonce'], 'utilitysign_network_settings')) {
            wp_die('Security check failed');
        }

        $new_config = [
            'enable_network_wide_security' => isset($_POST['enable_network_wide_security']),
            'enable_network_wide_caching' => isset($_POST['enable_network_wide_caching']),
            'enable_network_wide_error_handling' => isset($_POST['enable_network_wide_error_handling']),
            'enable_shared_logs' => isset($_POST['enable_shared_logs']),
            'enable_cross_site_requests' => isset($_POST['enable_cross_site_requests']),
        ];

        $this->config = wp_parse_args($new_config, $this->config);
        update_site_option('utilitysign_multisite_config', $this->config);

        wp_redirect(add_query_arg('updated', '1', network_admin_url('admin.php?page=utilitysign-network-settings')));
        exit;
    }

    /**
     * Handle cross-site request
     * 
     * @since 1.0.0
     */
    public function handle_cross_site_request() {
        if (!$this->config['enable_cross_site_requests']) {
            wp_send_json_error(['message' => 'Cross-site requests are disabled']);
        }

        $target_site_id = intval($_POST['target_site_id'] ?? 0);
        $action = sanitize_text_field($_POST['action'] ?? '');
        $data = $_POST['data'] ?? [];

        if (!$target_site_id || !$action) {
            wp_send_json_error(['message' => 'Invalid request parameters']);
        }

        // Switch to target site
        switch_to_blog($target_site_id);

        try {
            // Execute the requested action
            $result = $this->execute_cross_site_action($action, $data);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } finally {
            // Restore current site
            restore_current_blog();
        }
    }

    /**
     * Execute cross-site action
     * 
     * @since 1.0.0
     * @param string $action
     * @param array $data
     * @return mixed
     */
    private function execute_cross_site_action($action, $data) {
        switch ($action) {
            case 'get_site_data':
                return $this->get_site_data($data);
            case 'update_site_settings':
                return $this->update_site_settings($data);
            case 'clear_site_cache':
                return $this->clear_site_cache(get_current_blog_id());
            case 'get_site_statistics':
                return $this->get_site_statistics();
            default:
                throw new \Exception('Unknown action: ' . $action);
        }
    }

    /**
     * Get site data
     * 
     * @since 1.0.0
     * @param array $data
     * @return array
     */
    private function get_site_data($data) {
        $site_id = get_current_blog_id();
        $site_data = get_blog_details($site_id);
        
        return [
            'site_id' => $site_id,
            'site_name' => $site_data->blogname,
            'site_url' => $site_data->siteurl,
            'admin_email' => get_option('admin_email'),
            'users_count' => count_users()['total_users'],
            'plugins' => get_option('active_plugins', []),
            'theme' => get_option('stylesheet'),
        ];
    }

    /**
     * Update site settings
     * 
     * @since 1.0.0
     * @param array $data
     * @return bool
     */
    private function update_site_settings($data) {
        $settings = $data['settings'] ?? [];
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        return true;
    }

    /**
     * Clear site cache
     * 
     * @since 1.0.0
     * @param int $site_id
     * @return bool
     */
    public function clear_site_cache($site_id) {
        if ($this->config['enable_site_specific_caching']) {
            // Clear site-specific cache
            wp_cache_flush();
            
            // Clear transients
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_utilitysign_%'
            ));
        }
        
        return true;
    }

    /**
     * Get site statistics
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_site_statistics() {
        return [
            'site_id' => get_current_blog_id(),
            'api_requests' => $this->get_api_requests_count(),
            'security_events' => $this->get_security_events_count(),
            'cache_hits' => $this->get_cache_hits_count(),
            'errors' => $this->get_errors_count(),
            'storage_usage' => $this->get_storage_usage(),
        ];
    }

    /**
     * Get network statistics
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_network_statistics() {
        $sites = get_sites(['number' => 0]);
        $network_stats = [
            'sites' => [],
            'total_api_requests' => 0,
            'total_security_events' => 0,
            'total_cache_hits' => 0,
            'total_errors' => 0,
        ];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $site_stats = $this->get_site_statistics();
            $site_data = get_blog_details($site->blog_id);
            
            $network_stats['sites'][] = [
                'id' => $site->blog_id,
                'name' => $site_data->blogname,
                'domain' => $site_data->domain,
                'status' => $site_data->public ? 'Public' : 'Private',
                'users' => $site_stats['api_requests'],
                'storage' => $site_stats['storage_usage'],
            ];
            
            $network_stats['total_api_requests'] += $site_stats['api_requests'];
            $network_stats['total_security_events'] += $site_stats['security_events'];
            $network_stats['total_cache_hits'] += $site_stats['cache_hits'];
            $network_stats['total_errors'] += $site_stats['errors'];
            
            restore_current_blog();
        }

        return $network_stats;
    }

    /**
     * Get active sites count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_active_sites_count() {
        $sites = get_sites(['number' => 0, 'archived' => 0, 'deleted' => 0]);
        return count($sites);
    }

    /**
     * Get network users count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_network_users_count() {
        $users = get_users(['blog_id' => 0]);
        return count($users);
    }

    /**
     * Get network storage usage
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_network_storage_usage() {
        $upload_dir = wp_upload_dir();
        $total_size = 0;
        
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $site_upload_dir = wp_upload_dir();
            if (is_dir($site_upload_dir['basedir'])) {
                $total_size += $this->get_directory_size($site_upload_dir['basedir']);
            }
            restore_current_blog();
        }
        
        return size_format($total_size);
    }

    /**
     * Get directory size
     * 
     * @since 1.0.0
     * @param string $directory
     * @return int
     */
    private function get_directory_size($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    /**
     * Get API requests count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_api_requests_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'utilitysign_auth_log';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE event = 'auth_success'");
    }

    /**
     * Get security events count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_security_events_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'utilitysign_security_log';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    /**
     * Get cache hits count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_cache_hits_count() {
        // This would need to be implemented based on your cache service
        return 0;
    }

    /**
     * Get errors count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_errors_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    /**
     * Get storage usage
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_storage_usage() {
        $upload_dir = wp_upload_dir();
        if (is_dir($upload_dir['basedir'])) {
            $size = $this->get_directory_size($upload_dir['basedir']);
            return size_format($size);
        }
        return '0 B';
    }

    /**
     * Update site context
     * 
     * @since 1.0.0
     * @param int $site_id
     */
    private function update_site_context($site_id) {
        // Update any site-specific context variables
        $this->current_site_id = $site_id;
    }

    /**
     * Restore site context
     * 
     * @since 1.0.0
     */
    private function restore_site_context() {
        // Restore any site-specific context variables
        $this->current_site_id = get_current_blog_id();
    }

    /**
     * Send network-wide notification
     * 
     * @since 1.0.0
     * @param string $message
     * @param string $type
     */
    public function send_network_wide_notification($message, $type = 'info') {
        if (!$this->config['enable_network_wide_notifications']) {
            return;
        }

        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Send notification to site admins
            $admin_users = get_users(['role' => 'administrator']);
            foreach ($admin_users as $user) {
                wp_mail(
                    $user->user_email,
                    'UtilitySign Network Notification',
                    $message,
                    ['Content-Type: text/html; charset=UTF-8']
                );
            }
            
            restore_current_blog();
        }
    }

    /**
     * Update multisite configuration
     * 
     * @since 1.0.0
     * @param array $new_config
     */
    public function update_config($new_config) {
        $this->config = wp_parse_args($new_config, $this->config);
        
        if (is_multisite()) {
            update_site_option('utilitysign_multisite_config', $this->config);
        } else {
            update_option('utilitysign_multisite_config', $this->config);
        }
    }

    /**
     * Get multisite configuration
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Check if multisite is enabled
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_multisite() {
        return is_multisite();
    }

    /**
     * Get current site ID
     * 
     * @since 1.0.0
     * @return int
     */
    public function get_current_site_id() {
        return $this->current_site_id;
    }
}
