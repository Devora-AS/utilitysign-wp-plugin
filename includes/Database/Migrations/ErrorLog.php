<?php
namespace UtilitySign\Database\Migrations;

use UtilitySign\Interfaces\Migration;

/**
 * Error Log Migration
 * Creates the error log table for tracking errors and exceptions
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ErrorLog implements Migration {
    /**
     * Run the migration
     * 
     * @since 1.0.0
     */
    public static function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(100) NOT NULL,
            message text NOT NULL,
            file varchar(255),
            line int(11),
            severity varchar(20) NOT NULL,
            correlation_id varchar(50),
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            request_uri varchar(500),
            request_method varchar(10),
            stack_trace longtext,
            site_id bigint(20) NOT NULL DEFAULT 1,
            timestamp datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY severity (severity),
            KEY correlation_id (correlation_id),
            KEY user_id (user_id),
            KEY site_id (site_id),
            KEY timestamp (timestamp),
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
        
        $table_name = $wpdb->prefix . 'utilitysign_error_log';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
}
