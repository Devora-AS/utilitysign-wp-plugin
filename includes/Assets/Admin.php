<?php

declare(strict_types=1);

namespace UtilitySign\Assets;

use UtilitySign\Core\Template;
use UtilitySign\Traits\Base;
use UtilitySign\Libs\Assets;

/**
 * Class Admin
 *
 * Handles admin functionalities for the UtilitySign.
 *
 * @package UtilitySign\Admin
 */
class Admin {

	use Base;

	/**
	 * Script handle for UtilitySign.
	 */
	const HANDLE = 'utilitysign';

	/**
	 * JS Object name for UtilitySign.
	 */
	const OBJ_NAME = 'utilitySign';

	/**
	 * Development script path for UtilitySign.
	 */
	const DEV_SCRIPT = 'src/admin/main.jsx';

	/**
	 * List of allowed screens for script enqueue.
	 *
	 * @var array
	 */
	private $allowed_screens = array(
		'toplevel_page_utilitysign',
		// Ensure assets load on submenu pages as well
		'utilitysign_page_utilitysign',
		'utilitysign_page_utilitysign-security',
	);

	/**
	 * Frontend bootstrapper.
	 *
	 * @return void
	 */
	public function bootstrap() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Enqueue script based on the current screen.
	 *
	 * @param string $screen The current screen.
	 */
	public function enqueue_script( $screen ) {
		$current_screen     = $screen;
		$template_file_name = Template::FRONTEND_TEMPLATE;

		if ( ! is_admin() ) {
			$template_slug = get_page_template_slug();
			if ( $template_slug ) {

				if ( $template_slug === $template_file_name ) {
					array_push( $this->allowed_screens, $template_file_name );
					$current_screen = $template_file_name;
				}
			}
		}

		// Always load assets on our plugin admin pages
		$on_plugin_admin_page = false;
		if ( is_admin() ) {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( $page && ( $page === 'utilitysign' || strpos( $page, 'utilitysign-' ) === 0 ) ) {
				$on_plugin_admin_page = true;
			}
		}

		if ( $on_plugin_admin_page || in_array( $current_screen, $this->allowed_screens, true ) ) {
			$manifest_dir = UTILITYSIGN_DIR . 'assets/admin/dist';
			Assets\enqueue_asset(
				$manifest_dir,
				self::DEV_SCRIPT,
				$this->get_config()
			);
			wp_localize_script( self::HANDLE, self::OBJ_NAME, $this->get_data() );
			wp_localize_script( self::HANDLE, 'utilitySignConfig', $this->get_utility_sign_config() );
		}
	}

	/**
	 * Get the script configuration.
	 *
	 * @return array The script configuration.
	 */
	public function get_config() {
		return array(
			'dependencies' => array( 'react', 'react-dom', 'wp-api' ),
			'handle'       => self::HANDLE,
			'in-footer'    => true,
		);
	}

	/**
	 * Get data for script localization.
	 *
	 * @return array The localized script data.
	 */
	public function get_data() {
		$plugin_key_info = \UtilitySign\Utils\Security::get_plugin_key_info();
		$plugin_key      = $plugin_key_info['plugin_key'];
		$plugin_status   = $plugin_key_info['status'];

		$debug_mode = false;
		if ( isset( $_GET['utilitysign_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$debug_param = sanitize_text_field( wp_unslash( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$debug_mode  = ( '1' === $debug_param || 'true' === strtolower( $debug_param ) );
		}

		$rest_nonce    = wp_create_nonce( 'wp_rest' );
		$rest_api_url  = rest_url( 'utilitysign/v1/' );
		$backend_api   = \UtilitySign\Utils\Security::get_backend_api_url();
		$client_id     = \UtilitySign\Utils\Security::get_site_client_id();
		$timestamp     = time();

		$should_expose_key = $debug_mode && current_user_can( 'manage_utilitysign' );

		return array(
			'developer' => 'prappo',
			'isAdmin'   => is_admin(),
			'apiUrl'    => esc_url_raw( $rest_api_url ),
			'backendApiUrl' => esc_url_raw( $backend_api ),
			'pluginKey' => $should_expose_key ? $plugin_key : '',
			'pluginKeyStatus' => $plugin_status,
			'restNonce' => $rest_nonce,
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'utilitysign_admin_nonce' ),
			'clientId'  => $client_id,
			'debugMode' => $debug_mode,
			'timestamp' => $timestamp,
			'userInfo'  => $this->get_user_data(),
			'defaultConfig' => $this->get_default_config(),
			'currentPageSlug' => $this->get_current_page_slug(),
			'routeMap' => $this->get_admin_route_map(),
			'adminBaseUrl' => admin_url( 'admin.php?page=' ),
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

	/**
	 * Get default configuration for the frontend.
	 *
	 * @return array The default configuration.
	 */
	private function get_default_config() {
		return array(
			'environment' => 'staging',
			'apiUrl' => 'https://api-staging.utilitysign.devora.no',
			'clientId' => 'default-client-id', // Temporary default value
			'clientSecret' => 'default-client-secret', // Temporary default value
			'pluginKey' => null,
			'enableBankID' => true,
			'enableEmailNotifications' => true,
			'enableDebugMode' => false,
			'auth' => array(
				'authMethod' => 'entra_id',
				'entraIdTenantId' => 'default-tenant-id',
				'entraIdClientId' => 'default-client-id',
				'entraIdClientSecret' => 'default-client-secret',
				'jwtSecret' => 'default-jwt-secret',
				'jwtExpiration' => 3600,
				'apiKey' => 'default-api-key',
				'enableMFA' => true,
				'sessionTimeout' => 1800,
				'enableRememberMe' => true,
				'maxLoginAttempts' => 5,
				'lockoutDuration' => 900,
			),
			'components' => array(
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
			),
		);
	}

	/**
	 * Get configuration specifically for the APIClient.
	 *
	 * @return array The APIClient configuration.
	 */
	private function get_utility_sign_config() {
		$default_config = $this->get_default_config();
		
		return array(
			'clientId' => $default_config['clientId'],
			'enableBankID' => $default_config['enableBankID'],
			'enableEmailNotifications' => $default_config['enableEmailNotifications'],
		);
	}

	/**
	 * Get the current admin page slug.
	 *
	 * @return string
	 */
	private function get_current_page_slug() {
		if ( ! is_admin() ) {
			return 'frontend';
		}

		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'utilitysign';

		return $slug ?: 'utilitysign';
	}

	/**
	 * Map admin menu slugs to SPA routes.
	 *
	 * @return array
	 */
	private function get_admin_route_map() {
		return array(
			'utilitysign'           => '/',
			'utilitysign-dashboard' => '/',
			'utilitysign-settings'  => '/settings',
		);
	}
}
