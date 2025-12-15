<?php
/**
 * Database configuration using Eloquent ORM.
 *
 * @package WordPress_Plugin_Boilerplate
 * @subpackage Database
 * @since 1.0.0
 */

namespace UtilitySign\Libs\DatabaseConnection;

use Prappo\WpEloquent\Application;

/**
 * Boot the Eloquent ORM safely.
 *
 * This function is hooked to 'plugins_loaded' to ensure WordPress is fully initialized
 * before attempting to use the database connection.
 *
 * @return void
 */
function utilitysign_boot_eloquent() {
    global $wpdb;
    
    // Only boot if:
    // 1. WordPress is loaded (ABSPATH defined)
    // 2. $wpdb is a proper WordPress database object (not just stdClass)
    // 3. We're not in test mode
    // 4. Eloquent hasn't already been booted
    if (
        defined( 'ABSPATH' ) &&
        ! defined( 'UTILITYSIGN_TEST_MODE' ) &&
        ! defined( 'UTILITYSIGN_ELOQUENT_BOOTED' ) &&
        isset( $wpdb ) &&
        $wpdb instanceof \wpdb &&
        method_exists( $wpdb, 'db_version' )
    ) {
        try {
    Application::bootWp();
            define( 'UTILITYSIGN_ELOQUENT_BOOTED', true );
        } catch ( \Throwable $e ) {
            // Silently fail - Eloquent features will be unavailable but plugin will still work
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'UtilitySign: Failed to boot Eloquent ORM: ' . $e->getMessage() );
            }
        }
    }
}

// Hook to plugins_loaded with a low priority to ensure WordPress is fully loaded
if ( function_exists( 'add_action' ) ) {
    add_action( 'plugins_loaded', __NAMESPACE__ . '\utilitysign_boot_eloquent', 999 );
}
