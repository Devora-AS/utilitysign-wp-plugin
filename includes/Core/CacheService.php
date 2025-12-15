<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;

/**
 * Cache Service for UtilitySign WordPress Plugin
 * Provides comprehensive caching functionality with TTL, invalidation, and monitoring
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class CacheService {
    use Base;

    /**
     * Cache configuration
     * 
     * @var array
     */
    private $config;

    /**
     * Cache statistics
     * 
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'invalidations' => 0
    ];

    /**
     * Cache groups
     * 
     * @var array
     */
    private $groups = [
        'api_responses' => 300,      // 5 minutes
        'user_data' => 1800,         // 30 minutes
        'configuration' => 3600,     // 1 hour
        'templates' => 7200,         // 2 hours
        'static_data' => 86400,      // 24 hours
    ];

    /**
     * Initialize cache service
     * 
     * @since 1.0.0
     */
    public function init() {
        $this->config = $this->get_cache_config();
        $this->init_cache_hooks();
        $this->init_cleanup_schedule();
    }

    /**
     * Get cache configuration
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_cache_config() {
        return wp_parse_args(
            get_option('utilitysign_cache_config', []),
            [
                'enable_caching' => true,
                'default_ttl' => 300, // 5 minutes
                'max_cache_size' => 1000, // Maximum number of items
                'enable_compression' => true,
                'enable_serialization' => true,
                'enable_namespacing' => true,
                'namespace_prefix' => 'utilitysign_',
                'enable_statistics' => true,
                'cleanup_interval' => 3600, // 1 hour
                'enable_wp_cache' => true,
                'enable_transients' => true,
                'enable_object_cache' => true,
            ]
        );
    }

    /**
     * Initialize cache hooks
     * 
     * @since 1.0.0
     */
    private function init_cache_hooks() {
        // Clear cache on plugin deactivation
        register_deactivation_hook(UTILITYSIGN_PLUGIN_FILE, [$this, 'clear_all_cache']);
        
        // Clear cache on theme switch
        add_action('switch_theme', [$this, 'clear_all_cache']);
        
        // Clear cache on plugin updates
        add_action('upgrader_process_complete', [$this, 'clear_all_cache']);
        
        // Clear cache on user profile updates
        add_action('profile_update', [$this, 'clear_user_cache']);
        add_action('user_register', [$this, 'clear_user_cache']);
        add_action('delete_user', [$this, 'clear_user_cache']);
        
        // Clear cache on post updates
        add_action('save_post', [$this, 'clear_post_cache']);
        add_action('delete_post', [$this, 'clear_post_cache']);
        
        // Clear cache on option updates
        add_action('update_option_utilitysign_*', [$this, 'clear_config_cache']);
    }

    /**
     * Initialize cleanup schedule
     * 
     * @since 1.0.0
     */
    private function init_cleanup_schedule() {
        if (!wp_next_scheduled('utilitysign_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'utilitysign_cache_cleanup');
        }
        
        add_action('utilitysign_cache_cleanup', [$this, 'cleanup_expired_cache']);
    }

    /**
     * Get cache key
     * 
     * @since 1.0.0
     * @param string $key
     * @param string $group
     * @return string
     */
    private function get_cache_key($key, $group = 'default') {
        if ($this->config['enable_namespacing']) {
            return $this->config['namespace_prefix'] . $group . '_' . $key;
        }
        
        return $key;
    }

    /**
     * Get cache TTL for group
     * 
     * @since 1.0.0
     * @param string $group
     * @return int
     */
    private function get_group_ttl($group) {
        return $this->groups[$group] ?? $this->config['default_ttl'];
    }

    /**
     * Get cached data
     * 
     * @since 1.0.0
     * @param string $key
     * @param string $group
     * @return mixed|false
     */
    public function get($key, $group = 'default') {
        if (!$this->config['enable_caching']) {
            $this->stats['misses']++;
            return false;
        }

        $cache_key = $this->get_cache_key($key, $group);
        
        // Try WordPress object cache first
        if ($this->config['enable_object_cache']) {
            $data = wp_cache_get($cache_key, 'utilitysign');
            if ($data !== false) {
                $this->stats['hits']++;
                return $this->unserialize_data($data);
            }
        }
        
        // Try WordPress transients
        if ($this->config['enable_transients']) {
            $data = get_transient($cache_key);
            if ($data !== false) {
                $this->stats['hits']++;
                return $this->unserialize_data($data);
            }
        }
        
        // Try custom cache table
        $data = $this->get_from_cache_table($cache_key);
        if ($data !== false) {
            $this->stats['hits']++;
            return $this->unserialize_data($data);
        }
        
        $this->stats['misses']++;
        return false;
    }

    /**
     * Set cached data
     * 
     * @since 1.0.0
     * @param string $key
     * @param mixed $data
     * @param string $group
     * @param int $ttl
     * @return bool
     */
    public function set($key, $data, $group = 'default', $ttl = null) {
        if (!$this->config['enable_caching']) {
            return false;
        }

        $cache_key = $this->get_cache_key($key, $group);
        $ttl = $ttl ?? $this->get_group_ttl($group);
        $serialized_data = $this->serialize_data($data);
        
        // Store in WordPress object cache
        if ($this->config['enable_object_cache']) {
            wp_cache_set($cache_key, $serialized_data, 'utilitysign', $ttl);
        }
        
        // Store in WordPress transients
        if ($this->config['enable_transients']) {
            set_transient($cache_key, $serialized_data, $ttl);
        }
        
        // Store in custom cache table
        $this->set_in_cache_table($cache_key, $serialized_data, $ttl);
        
        $this->stats['sets']++;
        return true;
    }

    /**
     * Delete cached data
     * 
     * @since 1.0.0
     * @param string $key
     * @param string $group
     * @return bool
     */
    public function delete($key, $group = 'default') {
        if (!$this->config['enable_caching']) {
            return false;
        }

        $cache_key = $this->get_cache_key($key, $group);
        
        // Delete from WordPress object cache
        if ($this->config['enable_object_cache']) {
            wp_cache_delete($cache_key, 'utilitysign');
        }
        
        // Delete from WordPress transients
        if ($this->config['enable_transients']) {
            delete_transient($cache_key);
        }
        
        // Delete from custom cache table
        $this->delete_from_cache_table($cache_key);
        
        $this->stats['deletes']++;
        return true;
    }

    /**
     * Clear cache group
     * 
     * @since 1.0.0
     * @param string $group
     * @return bool
     */
    public function clear_group($group) {
        if (!$this->config['enable_caching']) {
            return false;
        }

        $pattern = $this->config['namespace_prefix'] . $group . '_*';
        
        // Clear from WordPress object cache
        if ($this->config['enable_object_cache']) {
            wp_cache_flush_group('utilitysign');
        }
        
        // Clear from WordPress transients
        if ($this->config['enable_transients']) {
            $this->clear_transients_by_pattern($pattern);
        }
        
        // Clear from custom cache table
        $this->clear_cache_table_by_group($group);
        
        $this->stats['invalidations']++;
        return true;
    }

    /**
     * Clear all cache
     * 
     * @since 1.0.0
     * @return bool
     */
    public function clear_all_cache() {
        if (!$this->config['enable_caching']) {
            return false;
        }

        // Clear WordPress object cache
        if ($this->config['enable_object_cache']) {
            wp_cache_flush();
        }
        
        // Clear WordPress transients
        if ($this->config['enable_transients']) {
            $this->clear_all_transients();
        }
        
        // Clear custom cache table
        $this->clear_all_cache_table();
        
        $this->stats['invalidations']++;
        return true;
    }

    /**
     * Get cache statistics
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_stats() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0 ? round(($this->stats['hits'] / $total_requests) * 100, 2) : 0;
        
        return array_merge($this->stats, [
            'hit_rate' => $hit_rate,
            'total_requests' => $total_requests,
            'cache_size' => $this->get_cache_size(),
            'groups' => array_keys($this->groups)
        ]);
    }

    /**
     * Reset cache statistics
     * 
     * @since 1.0.0
     */
    public function reset_stats() {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'invalidations' => 0
        ];
    }

    /**
     * Serialize data for storage
     * 
     * @since 1.0.0
     * @param mixed $data
     * @return string
     */
    private function serialize_data($data) {
        if (!$this->config['enable_serialization']) {
            return $data;
        }
        
        $serialized = serialize($data);
        
        if ($this->config['enable_compression'] && strlen($serialized) > 1024) {
            return gzcompress($serialized);
        }
        
        return $serialized;
    }

    /**
     * Unserialize data from storage
     * 
     * @since 1.0.0
     * @param string $data
     * @return mixed
     */
    private function unserialize_data($data) {
        if (!$this->config['enable_serialization']) {
            return $data;
        }
        
        if ($this->config['enable_compression'] && $this->is_compressed($data)) {
            $data = gzuncompress($data);
        }
        
        return unserialize($data);
    }

    /**
     * Check if data is compressed
     * 
     * @since 1.0.0
     * @param string $data
     * @return bool
     */
    private function is_compressed($data) {
        return substr($data, 0, 2) === "\x1f\x8b";
    }

    /**
     * Get data from cache table
     * 
     * @since 1.0.0
     * @param string $key
     * @return mixed|false
     */
    private function get_from_cache_table($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT data, expires_at FROM $table_name WHERE cache_key = %s AND expires_at > %s",
            $key,
            current_time('mysql')
        ));
        
        if ($result) {
            return $result->data;
        }
        
        return false;
    }

    /**
     * Set data in cache table
     * 
     * @since 1.0.0
     * @param string $key
     * @param string $data
     * @param int $ttl
     * @return bool
     */
    private function set_in_cache_table($key, $data, $ttl) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);
        
        $result = $wpdb->replace(
            $table_name,
            [
                'cache_key' => $key,
                'data' => $data,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }

    /**
     * Delete data from cache table
     * 
     * @since 1.0.0
     * @param string $key
     * @return bool
     */
    private function delete_from_cache_table($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $result = $wpdb->delete(
            $table_name,
            ['cache_key' => $key],
            ['%s']
        );
        
        return $result !== false;
    }

    /**
     * Clear cache table by group
     * 
     * @since 1.0.0
     * @param string $group
     * @return bool
     */
    private function clear_cache_table_by_group($group) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        $pattern = $this->config['namespace_prefix'] . $group . '_%';
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE cache_key LIKE %s",
            $pattern
        ));
        
        return $result !== false;
    }

    /**
     * Clear all cache table
     * 
     * @since 1.0.0
     * @return bool
     */
    private function clear_all_cache_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        return $result !== false;
    }

    /**
     * Clear transients by pattern
     * 
     * @since 1.0.0
     * @param string $pattern
     * @return bool
     */
    private function clear_transients_by_pattern($pattern) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $pattern,
            '_transient_timeout_' . $pattern
        ));
        
        return true;
    }

    /**
     * Clear all transients
     * 
     * @since 1.0.0
     * @return bool
     */
    private function clear_all_transients() {
        global $wpdb;
        
        // Using prepare() for security even with hardcoded patterns
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_utilitysign_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_utilitysign_%'
        ));
        
        return true;
    }

    /**
     * Get cache size
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_cache_size() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        return (int) $count;
    }

    /**
     * Cleanup expired cache
     * 
     * @since 1.0.0
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        // Safe query - no user input, using NOW() function
        $wpdb->query("DELETE FROM $table_name WHERE expires_at < NOW()");
        
        // Cleanup old transients - using prepare() for security even with hardcoded patterns
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP()",
            '_transient_timeout_utilitysign_%'
        ));
    }

    /**
     * Clear user cache
     * 
     * @since 1.0.0
     * @param int $user_id
     */
    public function clear_user_cache($user_id) {
        $this->clear_group('user_data');
        $this->delete('user_' . $user_id, 'user_data');
    }

    /**
     * Clear post cache
     * 
     * @since 1.0.0
     * @param int $post_id
     */
    public function clear_post_cache($post_id) {
        $this->clear_group('api_responses');
        $this->delete('post_' . $post_id, 'api_responses');
    }

    /**
     * Clear configuration cache
     * 
     * @since 1.0.0
     */
    public function clear_config_cache() {
        $this->clear_group('configuration');
    }

    /**
     * Update cache configuration
     * 
     * @since 1.0.0
     * @param array $new_config
     */
    public function update_config($new_config) {
        $this->config = wp_parse_args($new_config, $this->config);
        update_option('utilitysign_cache_config', $this->config);
    }

    /**
     * Get cache configuration
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return $this->config;
    }
}
