<?php
namespace UtilitySign\Database\Migrations;

use UtilitySign\Interfaces\Migration;

/**
 * Cache Migration
 * Creates the cache table for storing cached data
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class Cache implements Migration {
    /**
     * Run the migration
     * 
     * @since 1.0.0
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            data longtext,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            site_id bigint(20) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expires_at (expires_at),
            KEY site_id (site_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Reverse the migration
     * 
     * @since 1.0.0
     */
    public static function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_cache';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
