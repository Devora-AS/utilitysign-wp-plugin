<?php

declare(strict_types=1);

namespace UtilitySign\Assets;

use UtilitySign\Core\Template;
use UtilitySign\Traits\Base;
use UtilitySign\Libs\Assets;
use UtilitySign\Utils\Security;
use UtilitySign\Admin\Settings;

/**
 * Class Frontend
 *
 * Handles frontend functionalities for the UtilitySign.
 *
 * @package UtilitySign\Assets
 */
class Frontend {

	use Base;

	/**
	 * Script handle for UtilitySign.
	 */
	const HANDLE = 'utilitysign-frontend';

	/**
	 * JS Object name for UtilitySign.
	 */
	const OBJ_NAME = 'utilitySignFrontend';

	/**
	 * Development script path for UtilitySign.
	 */
	const DEV_SCRIPT = 'src/frontend/main.jsx';

	/**
	 * List of allowed screens for script enqueue.
	 *
	 * @var array
	 */
	private $allowed_screens = array(
		'toplevel_page_utilitysign',
	);

	/**
	 * Frontend bootstrapper.
	 *
	 * @return void
	 */
	public function bootstrap() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_conflicting_scripts' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'defer_utilitysign_scripts' ), 101 );
	}

	/**
	 * Dequeue theme scripts that conflict with UtilitySign on pages with shortcodes
	 *
	 * @return void
	 */
	public function dequeue_conflicting_scripts() {
		// Only dequeue on pages with UtilitySign shortcodes
		if ( ! $this->has_shortcodes() ) {
			return;
		}

		// Dequeue Enfold theme scripts that don't exist and cause 404 errors
		wp_dequeue_script( 'order-form-script' );
		wp_deregister_script( 'order-form-script' );
		
		wp_dequeue_script( 'ajax-filter' );
		wp_deregister_script( 'ajax-filter' );
	}

	/**
	 * Enqueue script based on the current screen.
	 *
	 * @param string $screen The current screen.
	 */
	public function enqueue_script( $screen ) {
		$current_screen     = $screen;
		$template_file_name = Template::FRONTEND_TEMPLATE;
		$should_enqueue     = false;

		if ( ! is_admin() ) {
			$template_slug = get_page_template_slug();
			if ( $template_slug ) {

				if ( $template_slug === $template_file_name ) {
					array_push( $this->allowed_screens, $template_file_name );
					$current_screen = $template_file_name;
					$should_enqueue = true;
				}
			}

			// Check if shortcodes are present on the current page
			if ( ! $should_enqueue && $this->has_shortcodes() ) {
				$should_enqueue = true;
			}
		}

		if ( in_array( $current_screen, $this->allowed_screens, true ) || $should_enqueue ) {
			Assets\enqueue_asset(
				UTILITYSIGN_DIR . 'assets/frontend/dist',
				self::DEV_SCRIPT,
				$this->get_config()
			);
			wp_localize_script( self::HANDLE, self::OBJ_NAME, $this->get_data() );
		}
	}

	/**
	 * Check if UtilitySign shortcodes or blocks are present on the current page.
	 *
	 * @return bool True if shortcodes or blocks are present, false otherwise.
	 */
	private function has_shortcodes() {
		global $post;

		if ( ! $post || ! is_singular() ) {
			return false;
		}

		// Check if any UtilitySign shortcodes are present in the post content
		// Note: has_shortcode() requires exact tag names, not patterns
		$shortcode_tags = array(
			'utilitysign_signing_form',
			'utilitysign_form',
			'utilitysign_order_form',
			'utilitysign_product_selection',
			'utilitysign_products',
			'utilitysign_product_display',
			'utilitysign_signing_process',
		);

		foreach ( $shortcode_tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}

		// Check if any UtilitySign Gutenberg blocks are present in the post content
		if ( has_block( 'utilitysign/signing-form', $post ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the script configuration.
	 *
	 * @return array The script configuration.
	 */
	public function get_config() {
		return array(
			'dependencies' => array( 'react', 'react-dom' ),
			'handle'       => self::HANDLE,
			'in-footer'    => true,
		);
	}
	
	/**
	 * Add defer attribute to UtilitySign scripts for better performance
	 * 
	 * @since 1.0.0
	 */
	public function defer_utilitysign_scripts() {
		add_filter('script_loader_tag', function($tag, $handle) {
			if ($handle === self::HANDLE) {
				// Add defer attribute to UtilitySign main script
				$tag = str_replace(' src', ' defer src', $tag);
			}
			return $tag;
		}, 10, 2);
	}

	/**
	 * Get data for script localization.
	 *
	 * @return array The localized script data.
	 */
	public function get_data() {
		$plugin_key_info = Security::get_plugin_key_info();
		$plugin_key      = $plugin_key_info['plugin_key'];
		$plugin_status   = $plugin_key_info['status'];

		$debug_mode = false;
		if ( isset( $_GET['utilitysign_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$debug_param = sanitize_text_field( wp_unslash( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$debug_mode  = ( '1' === $debug_param || 'true' === strtolower( $debug_param ) );
		}

		if ( $debug_mode && 'valid' !== $plugin_status ) {
			error_log(
				sprintf(
					'[UtilitySign][Frontend] Plugin key status: %s %s',
					$plugin_status,
					empty( $plugin_key_info['reason'] ) ? '' : ' - ' . $plugin_key_info['reason']
				)
			);
		}

		$rest_nonce    = wp_create_nonce( 'wp_rest' );
		$rest_api_url  = rest_url( 'utilitysign/v1/' );
		$backend_api   = Security::get_backend_api_url();
		$client_id     = Security::get_site_client_id();
		$timestamp     = time();

		$should_expose_key = $debug_mode && current_user_can( 'manage_utilitysign' );

		// Load component settings from WordPress options
		$settings_instance = new Settings();
		$settings = $settings_instance->get_current_settings();
		
		// CRITICAL FIX: Ensure components array is always properly initialized
		$components = array();
		if ( isset( $settings['components'] ) && is_array( $settings['components'] ) ) {
			$components = $settings['components'];
		}
		
		// Merge with defaults to ensure all component settings exist
		$default_components = array(
			'theme' => 'light',
			'primaryColor' => '#3432A6',
			'secondaryColor' => '#968AB6',
			'accentColor' => '#FFFADE',
			'borderRadius' => 'devora',
			'fontFamily' => 'lato',
			'fontSize' => 'base',
			'buttonStyle' => 'devora',
			'cardStyle' => 'devora',
			'enableAnimations' => true,
			'enableShadows' => true,
			'enableGradients' => true,
			'customCSS' => '',
			'logoUrl' => '',
			'faviconUrl' => '',
			'enableCustomBranding' => false,
		);
		$components = wp_parse_args( $components, $default_components );

		return array(
			'developer' => 'prappo',
			'isAdmin'   => is_admin(),
			'apiUrl'    => esc_url_raw( $rest_api_url ),
			'backendApiUrl' => esc_url_raw( $backend_api ),
			'pluginKey' => $should_expose_key ? $plugin_key : '',
			'pluginKeyStatus' => $plugin_status,
			'clientId'  => $client_id,
			'restNonce' => $rest_nonce,
			'debugMode' => $debug_mode,
			'timestamp' => $timestamp,
			'userInfo'  => $this->get_user_data(),
			'components' => $components,
		);
	}

	/**
	 * Get user data for script localization.
	 *
	 * @return array The user data.
	 */
	private function get_user_data() {
		$username   = '';
		$avatar_url = '';

		if ( is_user_logged_in() ) {
			// Get current user's data .
			$current_user = wp_get_current_user();

			// Get username.
			$username = $current_user->user_login; // or use user_nicename, display_name, etc.

			// Get avatar URL.
			$avatar_url = get_avatar_url( $current_user->ID );
		}

		return array(
			'username' => $username,
			'avatar'   => $avatar_url,
		);
	}
}
