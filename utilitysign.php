<?php
/**
 * Plugin Name: UtilitySign
 * Description: WordPress plugin for document signing workflows with BankID integration
 * Author: Devora AS
 * Author URI: https://devora.no
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.0.5-rc1
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Update URI: devora-as/utilitysign-wp-plugin
 * Text Domain: utilitysign
 * Domain Path: /languages
 *
 * @package UtilitySign
 */

use UtilitySign\Core\Install;
use UtilitySign\Core\Api;
use UtilitySign\Admin\Menu;
use UtilitySign\Admin\Settings;
use UtilitySign\Core\Template;
use UtilitySign\Assets\Frontend;
use UtilitySign\Assets\Admin;
use UtilitySign\Shortcodes\SigningFormShortcode;
use UtilitySign\Core\SecurityService;
use UtilitySign\Core\ApiAuthenticationService;
use UtilitySign\Core\CacheService;
use UtilitySign\Core\ErrorHandlingService;
use UtilitySign\Core\MultisiteService;
use UtilitySign\Core\UpdateService;
use UtilitySign\Admin\SecuritySettings;
use UtilitySign\Traits\Base;

defined( 'ABSPATH' ) || exit;

// Suppress deprecation warnings from third-party libraries (wp-eloquent) during autoload.
// This prevents PHP 8.x deprecation notices from being treated as fatal errors.
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
$utilitysign_error_level = error_reporting();
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
error_reporting( $utilitysign_error_level & ~E_DEPRECATED );

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

// Restore original error reporting level.
// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
error_reporting( $utilitysign_error_level );

// Define constants
if ( ! defined( 'UTILITYSIGN_VERSION' ) ) {
	define( 'UTILITYSIGN_VERSION', '1.0.4' );
	define( 'UTILITYSIGN_PLUGIN_FILE', __FILE__ );
	define( 'UTILITYSIGN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'UTILITYSIGN_URL', plugin_dir_url( __FILE__ ) );
	define( 'UTILITYSIGN_ASSETS_URL', UTILITYSIGN_URL . '/assets' );
	define( 'UTILITYSIGN_ROUTE_PREFIX', 'utilitysign/v1' );
	
	// Backward compatibility
	define( 'WPB_VERSION', UTILITYSIGN_VERSION );
	define( 'WPB_PLUGIN_FILE', UTILITYSIGN_PLUGIN_FILE );
	define( 'WPB_DIR', UTILITYSIGN_DIR );
	define( 'WPB_URL', UTILITYSIGN_URL );
	define( 'WPB_ASSETS_URL', UTILITYSIGN_ASSETS_URL );
	define( 'WPB_ROUTE_PREFIX', UTILITYSIGN_ROUTE_PREFIX );
}

/**
 * Main UtilitySign class
 */
final class UtilitySign {

	use Base;

	/**
	 * Class constructor to set up constants for the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		// Constants already defined above
	}

	/**
	 * Main execution point where the plugin will fire up.
	 *
	 * Initializes necessary components for both admin and frontend.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		// Initialize security services first
		SecurityService::get_instance()->init();
		ApiAuthenticationService::get_instance()->init();
		CacheService::get_instance()->init();
		ErrorHandlingService::get_instance()->init();
		MultisiteService::get_instance()->init();
		UpdateService::get_instance()->init();

		// Initialize Settings globally - required for REST API route registration
		// REST routes must be registered in all contexts, not just admin
		Settings::get_instance()->init();

    if ( is_admin() ) {
        Menu::get_instance()->init();
        Admin::get_instance()->bootstrap();
        SecuritySettings::get_instance()->init();
        \UtilitySign\Admin\ProductManager::get_instance()->init();
        \UtilitySign\Admin\OrderManager::get_instance()->init();
        \UtilitySign\Admin\SupplierManager::get_instance()->init();
    }

    // Initialize blocks
    \UtilitySign\Blocks\ProductDisplayBlock::get_instance()->init();
    \UtilitySign\Blocks\OrderFormBlock::get_instance()->init();
    \UtilitySign\Blocks\SupplierSelectionBlock::get_instance()->init();

    // Initialize performance optimization
    \UtilitySign\Core\PerformanceOptimizer::get_instance()->init();

    // Initialize services
    \UtilitySign\Services\PricingCalculator::get_instance();
    \UtilitySign\Services\SupplierAnalytics::get_instance();
    
    // Initialize REST API controllers
    \UtilitySign\REST\PricingController::get_instance()->init();
    \UtilitySign\REST\ProductsController::get_instance()->init();
    \UtilitySign\REST\SupplierAnalyticsController::get_instance()->init();
    \UtilitySign\REST\SigningController::get_instance()->init();

		// Initialize core functionalities.
		Frontend::get_instance()->bootstrap();
		API::get_instance()->init();
		Template::get_instance()->init();
		SigningFormShortcode::get_instance()->init();
		\UtilitySign\Shortcodes\ProductShortcodes::get_instance()->init();
		
		// Initialize controllers
		\UtilitySign\Controllers\Orders\OrderFormController::get_instance()->init();
		\UtilitySign\Controllers\BankId\CriiptoWebhookController::get_instance()->init();
		
		// Initialize AJAX handlers
		new \UtilitySign\Ajax\OrderActions();
		new \UtilitySign\Ajax\ProductActions();

		add_action( 'init', array( $this, 'i18n' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	public function register_blocks() {
		register_block_type( __DIR__ . '/assets/blocks/block-1' );
		register_block_type( __DIR__ . '/assets/blocks/signing-form' );
	}

	/**
	 * Register custom post types
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types() {
		// Register UtilitySign Product post type
		register_post_type( 'utilitysign_product', array(
			'labels' => array(
				'name' => __( 'Products', 'utilitysign' ),
				'singular_name' => __( 'Product', 'utilitysign' ),
				'add_new' => __( 'Add New Product', 'utilitysign' ),
				'add_new_item' => __( 'Add New Product', 'utilitysign' ),
				'edit_item' => __( 'Edit Product', 'utilitysign' ),
				'new_item' => __( 'New Product', 'utilitysign' ),
				'view_item' => __( 'View Product', 'utilitysign' ),
				'search_items' => __( 'Search Products', 'utilitysign' ),
				'not_found' => __( 'No products found', 'utilitysign' ),
				'not_found_in_trash' => __( 'No products found in trash', 'utilitysign' ),
				'parent_item_colon' => __( 'Parent Product:', 'utilitysign' ),
				'menu_name' => __( 'Products', 'utilitysign' ),
			),
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => false, // Hidden from admin menu per plan - managed in SaaS admin
			'show_in_nav_menus' => true,
			'show_in_admin_bar' => true,
			'show_in_rest' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'products' ),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => 25,
			'menu_icon' => 'dashicons-products',
			'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'taxonomies' => array(),
		) );
	}

	/**
	 * Internationalization setup for language translations.
	 *
	 * Loads the plugin text domain for localization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'utilitysign', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}

/**
 * Initializes the UtilitySign plugin when plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function utilitysign_init() {
	UtilitySign::get_instance()->init();
}

// Hook for plugin initialization.
add_action( 'plugins_loaded', 'utilitysign_init' );

/**
 * Ensure custom capabilities exist for relevant roles.
 *
 * @return void
 */
function utilitysign_register_capabilities() {
	$roles = array( 'administrator' );

	foreach ( $roles as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}

		if ( ! $role->has_cap( 'manage_utilitysign' ) ) {
			$role->add_cap( 'manage_utilitysign' );
		}

		if ( ! $role->has_cap( 'manage_utilitysign_internal' ) ) {
			$role->add_cap( 'manage_utilitysign_internal' );
		}
	}
}

add_action( 'init', 'utilitysign_register_capabilities' );

/**
 * Plugin activation handler.
 *
 * @param bool $network_wide Whether plugin is network-activated.
 * @return void
 */
function utilitysign_activate( $network_wide ) {
	utilitysign_register_capabilities();

	if ( is_multisite() && $network_wide ) {
		global $wpdb;
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			Install::get_instance()->init();
			// Flush permalinks to register REST routes
			flush_rewrite_rules();
			restore_current_blog();
		}
		return;
	}

	Install::get_instance()->init();
	
	// Flush permalinks to register REST routes (fixes 404 on REST endpoints)
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'utilitysign_activate' );
