<?php
namespace UtilitySign\Database\Migrations;

use UtilitySign\Interfaces\Migration;

/**
 * Authentication Log Migration
 * Creates the authentication log table for tracking authentication events
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class AuthLog implements Migration {
    /**
     * Run the migration
     * 
     * @since 1.0.0
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_auth_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            event varchar(50) NOT NULL,
            event_type varchar(50) NOT NULL,
            method varchar(50),
            reason varchar(100),
            data longtext,
            site_id bigint(20) NOT NULL DEFAULT 1,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event),
            KEY event_type (event_type),
            KEY timestamp (timestamp),
            KEY site_id (site_id),
            KEY method (method),
            KEY ip_address (ip_address)
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
        
        $table_name = $wpdb->prefix . 'utilitysign_auth_log';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
