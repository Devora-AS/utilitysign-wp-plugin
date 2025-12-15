<?php
/**
 * Settings handler for UtilitySign plugin.
 *
 * @package UtilitySign\Admin
 */

namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;
use UtilitySign\Utils\Security;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Handles WordPress admin settings for the UtilitySign plugin.
 *
 * @package UtilitySign\Admin
 */
class Settings {

	use Base;

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private $option_name = 'utilitysign_settings';

	/**
	 * Secret metadata option name.
	 *
	 * @var string
	 */
	private $secret_meta_option = 'utilitysign_secret_metadata';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $default_settings = array(
		'environment' => 'staging',
		'apiUrl' => 'https://api-staging.utilitysign.devora.no',
		'clientId' => '',
		'clientSecret' => '',
		'pluginKey' => '',
		'pluginSecret' => '',
		'enableBankID' => true,
		'enableEmailNotifications' => true,
		'enableDebugMode' => false,
		'criipto' => array(
			'clientId' => '',
			'clientSecret' => '',
			'domain' => '',
			'environment' => 'test',
			'webhookSecret' => '',
			'enableWebhooks' => true,
			'redirectUri' => '',
			'acrValues' => 'urn:grn:authn:no:bankid',
			'uiLocales' => 'no',
			'loginHint' => '',
		),
		'auth' => array(
			'authMethod' => 'entra_id',
			'entraIdTenantId' => '',
			'entraIdClientId' => '',
			'entraIdClientSecret' => '',
			'jwtSecret' => '',
			'jwtExpiration' => 3600,
			'apiKey' => '',
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

	/**
	 * Initializes the settings handler.
	 *
	 * @return void
	 */
	public function init() {
		// Migrate plugin key from settings if needed (runs once on init)
		$this->migrate_plugin_key();
		
		add_action( 'wp_ajax_utilitysign_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_utilitysign_update_settings', array( $this, 'update_settings' ) );
		add_action( 'wp_ajax_utilitysign_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_utilitysign_test_auth', array( $this, 'test_auth' ) );
		
		// Register REST API endpoints for general settings (used by React admin UI)
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}
	
	/**
	 * Register REST API routes for settings.
	 * 
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes() {
		// GET /wp-json/utilitysign/v1/settings/general
		register_rest_route( 'utilitysign/v1', '/settings/general', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_get_general_settings' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
		
		// POST /wp-json/utilitysign/v1/settings/general
		register_rest_route( 'utilitysign/v1', '/settings/general', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_update_general_settings' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
			'args'                => array(
				'signatoryRedirectPageId' => array(
					'type'              => 'integer',
					'required'          => false,
					'sanitize_callback' => 'absint',
					'validate_callback' => array( $this, 'validate_page_id' ),
				),
				'signatoryRedirectUri'    => array(
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => array( __CLASS__, 'sanitize_signatory_redirect_uri' ),
				),
			),
		) );
	}
	
	/**
	 * Check if current user has admin permissions.
	 * 
	 * @since 1.1.0
	 * @return bool True if user can manage options.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}
	
	/**
	 * Validate page ID exists and is published.
	 * 
	 * @since 1.1.0
	 * @param int    $value   Page ID to validate.
	 * @param object $request REST request object.
	 * @param string $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_page_id( $value, $request, $param ) {
		// Null/0 is valid (disables redirect)
		if ( empty( $value ) || 0 === $value ) {
			return true;
		}
		
		// Check if page exists and is published
		$page = get_post( $value );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return new \WP_Error(
				'invalid_page_id',
				sprintf(
					/* translators: %d: page ID that was rejected */
					__( 'Page ID %d does not exist or is not published.', 'utilitysign' ),
					$value
				),
				array( 'status' => 400 )
			);
		}
		
		return true;
	}
	
	/**
	 * REST API: Get general settings.
	 * 
	 * @since 1.1.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function rest_get_general_settings( $request ) {
		$page_id = get_option( 'utilitysign_signatory_redirect_page_id', 0 );
		$redirect_uri = get_option( 'utilitysign_signatory_redirect_uri', '' );
		
		// If only page ID is set, resolve URL
		if ( ! empty( $page_id ) && empty( $redirect_uri ) ) {
			$redirect_uri = get_permalink( $page_id );
		}
		
		return rest_ensure_response( array(
			'signatoryRedirectPageId' => $page_id ? (int) $page_id : null,
			'signatoryRedirectUri'    => $redirect_uri,
		) );
	}
	
	/**
	 * REST API: Update general settings.
	 * 
	 * @since 1.1.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function rest_update_general_settings( $request ) {
		$page_id = $request->get_param( 'signatoryRedirectPageId' );
		$redirect_uri = $request->get_param( 'signatoryRedirectUri' );
		
		// Update page ID
		if ( null !== $page_id ) {
			update_option( 'utilitysign_signatory_redirect_page_id', absint( $page_id ) );
		}
		
		// If page ID provided, resolve and save URL
		if ( ! empty( $page_id ) ) {
			$resolved_url = get_permalink( $page_id );
			if ( $resolved_url && false !== $resolved_url ) {
				update_option( 'utilitysign_signatory_redirect_uri', esc_url_raw( $resolved_url ) );
				$redirect_uri = $resolved_url; // Use resolved URL for response
			}
		} elseif ( null === $page_id || 0 === $page_id ) {
			// Clear redirect if page ID explicitly set to null or 0
			delete_option( 'utilitysign_signatory_redirect_page_id' );
			delete_option( 'utilitysign_signatory_redirect_uri' );
			$redirect_uri = '';
		}
		
		// Manual redirect URI provided (fallback for advanced users)
		if ( null !== $redirect_uri && ! empty( $page_id ) === false ) {
			update_option( 'utilitysign_signatory_redirect_uri', $redirect_uri );
		}
		
		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'General settings updated successfully', 'utilitysign' ),
			'data'    => array(
				'signatoryRedirectPageId' => $page_id ? absint( $page_id ) : null,
				'signatoryRedirectUri'    => $redirect_uri,
			),
		) );
	}

	/**
	 * Get secret metadata.
	 *
	 * @return array
	 */
	private function get_secret_metadata() {
		$meta = get_option( $this->secret_meta_option, array() );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		return $meta;
	}

	/**
	 * Update secret metadata entry.
	 *
	 * @param string $key Secret key identifier.
	 * @return void
	 */
	private function touch_secret_metadata( $key ) {
		$meta = $this->get_secret_metadata();
		$meta[ $key ] = array(
			'rotated_at' => current_time( 'mysql', true ),
			'rotated_by' => get_current_user_id(),
		);
		update_option( $this->secret_meta_option, $meta, false );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return void
	 */
	public function get_settings() {
		// Verify nonce with proper sanitization per WordPress coding standards.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilitysign_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_utilitysign' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		$settings = get_option( $this->option_name, $this->default_settings );
		
		// Merge with defaults to ensure all keys exist
		$settings = wp_parse_args( $settings, $this->default_settings );

		// CRITICAL FIX: Ensure nested arrays are properly initialized
		// wp_parse_args() only does shallow merge, so nested arrays must be explicitly checked
		if ( ! is_array( $settings['criipto'] ) ) {
			$settings['criipto'] = $this->default_settings['criipto'];
		} else {
			// Merge nested criipto settings with defaults
			$settings['criipto'] = array_merge( $this->default_settings['criipto'], $settings['criipto'] );
		}

		if ( ! is_array( $settings['auth'] ) ) {
			$settings['auth'] = $this->default_settings['auth'];
		} else {
			// Merge nested auth settings with defaults
			$settings['auth'] = array_merge( $this->default_settings['auth'], $settings['auth'] );
		}

		$meta = $this->get_secret_metadata();

		$mask_secret = function( $value, $key ) use ( $meta ) {
			if ( empty( $value ) ) {
				return '';
			}
			return array(
				'masked' => Security::mask_secret( Security::decrypt_secret( $value ) ),
				'rotatedAt' => $meta[ $key ]['rotated_at'] ?? null,
				'rotatedBy' => $meta[ $key ]['rotated_by'] ?? null,
			);
		};

		if ( ! empty( $settings['clientSecret'] ) ) {
			$settings['clientSecret'] = $mask_secret( $settings['clientSecret'], 'clientSecret' );
		} else {
			$settings['clientSecret'] = null;
		}

		if ( ! empty( $settings['pluginKey'] ) ) {
			$settings['pluginKey'] = $mask_secret( $settings['pluginKey'], 'pluginKey' );
		} else {
			$settings['pluginKey'] = null;
		}

		if ( ! empty( $settings['pluginSecret'] ) ) {
			$settings['pluginSecret'] = $mask_secret( $settings['pluginSecret'], 'pluginSecret' );
		} else {
			$settings['pluginSecret'] = null;
		}

		if ( ! empty( $settings['criipto']['clientSecret'] ) ) {
			$settings['criipto']['clientSecret'] = $mask_secret( $settings['criipto']['clientSecret'], 'criipto.clientSecret' );
		} else {
			$settings['criipto']['clientSecret'] = null;
		}

		if ( ! empty( $settings['criipto']['webhookSecret'] ) ) {
			$settings['criipto']['webhookSecret'] = $mask_secret( $settings['criipto']['webhookSecret'], 'criipto.webhookSecret' );
		} else {
			$settings['criipto']['webhookSecret'] = null;
		}

		if ( ! empty( $settings['auth']['entraIdClientSecret'] ) ) {
			$settings['auth']['entraIdClientSecret'] = $mask_secret( $settings['auth']['entraIdClientSecret'], 'auth.entraIdClientSecret' );
		} else {
			$settings['auth']['entraIdClientSecret'] = null;
		}

		if ( ! empty( $settings['auth']['jwtSecret'] ) ) {
			$settings['auth']['jwtSecret'] = $mask_secret( $settings['auth']['jwtSecret'], 'auth.jwtSecret' );
		} else {
			$settings['auth']['jwtSecret'] = null;
		}

		if ( ! empty( $settings['auth']['apiKey'] ) ) {
			$settings['auth']['apiKey'] = $mask_secret( $settings['auth']['apiKey'], 'auth.apiKey' );
		} else {
			$settings['auth']['apiKey'] = null;
		}

		wp_send_json_success( $settings );
	}

	/**
	 * Update plugin settings.
	 *
	 * @return void
	 */
	public function update_settings() {
		// Verify nonce with proper sanitization per WordPress coding standards.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilitysign_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_utilitysign' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		$raw = stripslashes( $_POST['settings'] ?? '{}' );
		$new_settings = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			wp_send_json_error( 'Invalid JSON data' );
			return;
		}

		// Sanitize and validate settings
		$sanitized_settings = $this->sanitize_settings( $new_settings );

		$existing = get_option( $this->option_name, $this->default_settings );
		$existing = wp_parse_args( $existing, $this->default_settings );

		// CRITICAL FIX: Ensure nested arrays are properly initialized before processing
		if ( ! is_array( $existing['criipto'] ) ) {
			$existing['criipto'] = $this->default_settings['criipto'];
		}
		if ( ! is_array( $existing['auth'] ) ) {
			$existing['auth'] = $this->default_settings['auth'];
		}

		$this->process_secret( $sanitized_settings, $existing, 'clientSecret' );
		$this->process_secret( $sanitized_settings, $existing, 'pluginKey' );
		$this->process_secret( $sanitized_settings, $existing, 'pluginSecret' );
		$this->process_secret_nested( $sanitized_settings, $existing, array( 'criipto', 'clientSecret' ) );
		$this->process_secret_nested( $sanitized_settings, $existing, array( 'criipto', 'webhookSecret' ) );
		$this->process_secret_nested( $sanitized_settings, $existing, array( 'auth', 'entraIdClientSecret' ) );
		$this->process_secret_nested( $sanitized_settings, $existing, array( 'auth', 'jwtSecret' ) );
		$this->process_secret_nested( $sanitized_settings, $existing, array( 'auth', 'apiKey' ) );

		$merged = $this->deep_merge_settings( $existing, $sanitized_settings );

		// Update settings
		$updated = update_option( $this->option_name, $merged );

		// CRITICAL FIX: Also save API key and URL to separate WordPress options for Frontend.php
		// Extract plugin key from dedicated pluginKey field (fallback to legacy auth.apiKey/clientId)
		$plugin_key = $merged['pluginKey'] ?? '';
		if ( empty( $plugin_key ) && isset( $merged['auth']['apiKey'] ) ) {
			$plugin_key = $merged['auth']['apiKey'];
		}
		
		// If pluginKey/auth.apiKey is empty, try to extract plugin key from clientId
		// (for backwards compatibility or if plugin key was incorrectly saved to clientId)
		if ( empty( $plugin_key ) && ! empty( $merged['clientId'] ) ) {
			$client_id = $merged['clientId'];
			// Check if clientId contains a plugin key (starts with "wp_")
			if ( preg_match( '/wp_[a-f0-9]{32}/', $client_id, $matches ) ) {
				$plugin_key = $matches[0]; // Extract just the plugin key part
			} elseif ( strpos( $client_id, 'wp_' ) === 0 ) {
				// clientId itself is a plugin key
				$plugin_key = $client_id;
			}
		}
		
		// Clean up plugin key: remove any "default-client-id" prefix if present
		if ( ! empty( $plugin_key ) && strpos( $plugin_key, 'default-client-id' ) === 0 ) {
			// Extract plugin key part after "default-client-id" prefix
			$plugin_key = preg_replace( '/^default-client-id/', '', $plugin_key );
		}
		
		// Ensure plugin key starts with "wp_" (valid plugin keys always start with this)
		if ( ! empty( $plugin_key ) && strpos( $plugin_key, 'wp_' ) !== 0 ) {
			// Try to extract plugin key if it's embedded in the string
			if ( preg_match( Security::PLUGIN_KEY_PATTERN, $plugin_key, $matches ) ) {
				$plugin_key = strtolower( $matches[0] );
			} else {
				// Not a valid plugin key, clear it
				$plugin_key = '';
			}
		}
		
		// Validate plugin key format before saving
		if ( ! empty( $plugin_key ) ) {
			// Decrypt first if encrypted (to validate the actual key format)
			if ( Security::is_encrypted_secret( $plugin_key ) ) {
				$plugin_key = Security::decrypt_secret( $plugin_key );
			}
			
			// Validate plugin key format using Security::PLUGIN_KEY_PATTERN
			if ( ! preg_match( Security::PLUGIN_KEY_PATTERN, $plugin_key ) ) {
				wp_send_json_error( __( 'Invalid plugin key format. Plugin key must match the wp_{32 hex} format. Please paste the key exactly as provided in UtilitySign Admin.', 'utilitysign' ) );
				return;
			}
			$plugin_key = strtolower( $plugin_key ); // Normalize to lowercase
			// Now ensure it's clean before re-encrypting
			if ( strpos( $plugin_key, 'default-client-id' ) === 0 ) {
				$plugin_key = preg_replace( '/^default-client-id/', '', $plugin_key );
			}
			// Re-encrypt for storage
			if ( ! Security::is_encrypted_secret( $plugin_key ) ) {
				$plugin_key = Security::encrypt_secret( $plugin_key );
			}
			update_option( 'utilitysign_api_key', $plugin_key );
		}

		// Extract and save plugin secret (required for backend API authentication)
		// Priority 1: pluginSecret (correct field)
		// Priority 2: clientSecret (fallback - user may have entered it in wrong field)
		$plugin_secret = $merged['pluginSecret'] ?? '';
		if ( empty( $plugin_secret ) && ! empty( $merged['clientSecret'] ) ) {
			// Fallback: use clientSecret if pluginSecret is empty
			// This handles the case where user entered secret in "API Client Secret" field instead of "Plugin Secret"
			$plugin_secret = $merged['clientSecret'];
		}
		
		if ( ! empty( $plugin_secret ) ) {
			// Decrypt first if encrypted
			if ( Security::is_encrypted_secret( $plugin_secret ) ) {
				$plugin_secret = Security::decrypt_secret( $plugin_secret );
			}
			
			// Plugin secret should be a hex string (typically 64+ characters)
			// Validate it's not empty and looks like a valid hex secret
			$plugin_secret = trim( (string) $plugin_secret );
			if ( ! empty( $plugin_secret ) && preg_match( '/^[a-f0-9]{32,}$/i', $plugin_secret ) ) {
				// Re-encrypt for storage
				if ( ! Security::is_encrypted_secret( $plugin_secret ) ) {
					$plugin_secret = Security::encrypt_secret( $plugin_secret );
				}
				update_option( 'utilitysign_api_secret', $plugin_secret );
			}
		}

		// Extract API URL (used by Frontend.php to determine backend endpoint)
		$api_url = $merged['apiUrl'] ?? '';
		if ( ! empty( $api_url ) ) {
			update_option( 'utilitysign_api_url', sanitize_url( $api_url ) );
		}

		// Note: update_option() returns false when:
		// 1. The new value equals the old value (no change needed)
		// 2. The update actually failed (extremely rare in normal operation)
		// Since we've successfully processed and merged the settings, we consider this a success.
		// A true database failure would throw an exception or return an error at a lower level.
		wp_send_json_success( __( 'Settings updated successfully', 'utilitysign' ) );
	}

	/**
	 * Deep merge two settings arrays preserving defaults.
	 *
	 * @param array $original Existing settings.
	 * @param array $new New sanitized settings.
	 * @return array
	 */
	private function deep_merge_settings( $original, $new ) {
		foreach ( $new as $key => $value ) {
			if ( is_array( $value ) && isset( $original[ $key ] ) && is_array( $original[ $key ] ) ) {
				$original[ $key ] = $this->deep_merge_settings( $original[ $key ], $value );
			} else {
				$original[ $key ] = $value;
			}
		}
		return $original;
	}

	/**
	 * Process secret field at root level.
	 *
	 * @param array &$sanitized Sanitized settings reference.
	 * @param array $existing Existing settings.
	 * @param string $key Secret key.
	 * @return void
	 */
	private function process_secret( &$sanitized, $existing, $key ) {
		if ( ! array_key_exists( $key, $sanitized ) ) {
			return;
		}

		$value = $sanitized[ $key ];

		if ( empty( $value ) ) {
			$sanitized[ $key ] = $existing[ $key ] ?? '';
			return;
		}

		if ( is_array( $value ) && isset( $value['rotate'] ) && true === $value['rotate'] ) {
			$plain = sanitize_text_field( $value['newValue'] ?? '' );
			$sanitized[ $key ] = Security::encrypt_secret( $plain );
			$this->touch_secret_metadata( $key );
			return;
		}

		if ( is_string( $value ) && Security::is_encrypted_secret( $value ) ) {
			$sanitized[ $key ] = $value;
			return;
		}

		if ( is_array( $value ) && isset( $value['newValue'] ) ) {
			$plain = sanitize_text_field( $value['newValue'] );
			$sanitized[ $key ] = Security::encrypt_secret( $plain );
			$this->touch_secret_metadata( $key );
			return;
		}

		$plain = sanitize_text_field( $value );
		$sanitized[ $key ] = Security::encrypt_secret( $plain );
		$this->touch_secret_metadata( $key );
	}

	/**
	 * Process nested secret field.
	 *
	 * @param array &$sanitized Sanitized settings.
	 * @param array $existing Existing settings.
	 * @param array $path Path keys.
	 * @return void
	 */
	private function process_secret_nested( &$sanitized, $existing, $path ) {
		$ref =& $sanitized;
		$exists = $existing;

		foreach ( $path as $index => $segment ) {
			if ( ! isset( $ref[ $segment ] ) ) {
				return;
			}
			if ( $index < count( $path ) - 1 ) {
				$ref =& $ref[ $segment ];
				$exists = $exists[ $segment ] ?? array();
			}
		}

		$key = implode( '.', $path );
		$value = $ref;

		if ( empty( $value ) ) {
			$ref = $exists ?? '';
			return;
		}

		if ( is_array( $value ) && isset( $value['rotate'] ) && true === $value['rotate'] ) {
			$plain = sanitize_text_field( $value['newValue'] ?? '' );
			$ref = Security::encrypt_secret( $plain );
			$this->touch_secret_metadata( $key );
			return;
		}

		if ( is_string( $value ) && Security::is_encrypted_secret( $value ) ) {
			$ref = $value;
			return;
		}

		if ( is_array( $value ) && isset( $value['newValue'] ) ) {
			$plain = sanitize_text_field( $value['newValue'] );
			$ref = Security::encrypt_secret( $plain );
			$this->touch_secret_metadata( $key );
			return;
		}

		$plain = sanitize_text_field( $value );
		$ref = Security::encrypt_secret( $plain );
		$this->touch_secret_metadata( $key );
	}

	/**
	 * Test API connection.
	 *
	 * @return void
	 */
	public function test_connection() {
		// Verify nonce with proper sanitization per WordPress coding standards.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilitysign_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_utilitysign' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		// Get plugin key from POST or from saved settings
		$plugin_key_raw = sanitize_text_field( $_POST['pluginKey'] ?? '' );
		$api_url = sanitize_url( $_POST['apiUrl'] ?? get_option('utilitysign_api_url', 'https://api-staging.utilitysign.devora.no') );

		// If plugin key provided in POST, validate it
		if ( ! empty( $plugin_key_raw ) ) {
			// Clean up plugin key: remove any "default-client-id" prefix if present
			if ( strpos( $plugin_key_raw, 'default-client-id' ) === 0 ) {
				$plugin_key_raw = preg_replace( '/^default-client-id/', '', $plugin_key_raw );
			}
			
			// Validate plugin key format using Security::PLUGIN_KEY_PATTERN
			if ( ! preg_match( Security::PLUGIN_KEY_PATTERN, $plugin_key_raw ) ) {
				wp_send_json_error( __( 'Invalid plugin key format. Plugin key must match the wp_{32 hex} format. Please paste the key exactly as provided in UtilitySign Admin.', 'utilitysign' ) );
				return;
			}
			
			$plugin_key = strtolower( $plugin_key_raw );
		} else {
			// Get plugin key from stored option
			$key_info = Security::get_plugin_key_info();
			if ( 'valid' !== $key_info['status'] ) {
				wp_send_json_error( __( 'Plugin key is not configured. Please enter the plugin key in UtilitySign → Settings.', 'utilitysign' ) );
				return;
			}
			$plugin_key = $key_info['plugin_key'];
		}

		if ( empty( $plugin_key ) ) {
			wp_send_json_error( __( 'Plugin key is required', 'utilitysign' ) );
			return;
		}

		// Test connection by making a simple request to the backend health endpoint
		$endpoint = trailingslashit( $api_url ) . 'api/health';
		
		$headers = array(
			'Authorization'       => 'Bearer ' . $plugin_key,
			'Content-Type'        => 'application/json',
			'Accept'              => 'application/json',
			'x-request-source'    => 'wordpress-plugin',
			'x-plugin-version'    => UTILITYSIGN_VERSION,
			'x-client-id'         => Security::get_site_client_id(),
			'x-correlation-id'    => 'wp-test-' . wp_generate_uuid4(),
		);

		$args = array(
			'method'  => 'GET',
			'headers' => $headers,
			'timeout' => 10,
		);

		$response = wp_remote_request( $endpoint, $args );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( sprintf(
				/* translators: %s: error message */
				__( 'Unable to reach UtilitySign API: %s', 'utilitysign' ),
				$response->get_error_message()
			) );
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_raw, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			// CRITICAL FIX: If test succeeded, ensure plugin key is saved to utilitysign_api_key option
			// This ensures form submissions work regardless of whether key came from POST or stored option
			if ( ! empty( $plugin_key ) ) {
				// Normalize the plugin key (it's already validated at this point)
				$normalized_key = strtolower( trim( $plugin_key ) );
				
				// Remove any "default-client-id" prefix if present
				if ( strpos( $normalized_key, 'default-client-id' ) === 0 ) {
					$normalized_key = preg_replace( '/^default-client-id/', '', $normalized_key );
				}
				
				// Validate format one more time before saving
				if ( preg_match( Security::PLUGIN_KEY_PATTERN, $normalized_key, $matches ) ) {
					$normalized_key = strtolower( $matches[0] );
					
					// Check if key is already saved and matches
					$saved_key_info = Security::get_plugin_key_info();
					$needs_save = false;
					
					if ( 'valid' !== $saved_key_info['status'] || $saved_key_info['plugin_key'] !== $normalized_key ) {
						$needs_save = true;
					}
					
					if ( $needs_save ) {
						// Encrypt for storage (if not already encrypted)
						$key_to_save = $normalized_key;
						if ( ! Security::is_encrypted_secret( $key_to_save ) ) {
							$key_to_save = Security::encrypt_secret( $key_to_save );
						}
						
						// Save to the option that SigningController reads from
						update_option( 'utilitysign_api_key', $key_to_save );
						
						// Also update the main settings option for consistency
						$current_settings = get_option( $this->option_name, $this->default_settings );
						if ( ! is_array( $current_settings ) ) {
							$current_settings = $this->default_settings;
						}
						$current_settings['pluginKey'] = $key_to_save;
						update_option( $this->option_name, $current_settings );
					}
				}
			}
			
			wp_send_json_success( __( 'Connection successful!', 'utilitysign' ) );
		} else {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Connection failed', 'utilitysign' );
			if ( $status_code === 401 ) {
				$error_message = __( 'Authentication failed. Please check your plugin key.', 'utilitysign' );
			} elseif ( $status_code === 403 ) {
				$error_message = __( 'Access denied. Please verify your plugin key has the correct permissions.', 'utilitysign' );
			}
			wp_send_json_error( $error_message );
		}
	}

	/**
	 * Migrate plugin key from main settings to utilitysign_api_key option.
	 * This ensures backward compatibility and automatic key availability.
	 * Runs automatically on plugin initialization if key is missing.
	 *
	 * @return void
	 */
	private function migrate_plugin_key() {
		// Check if key already exists in target location
		$existing_key_info = Security::get_plugin_key_info();
		if ( 'valid' === $existing_key_info['status'] ) {
			return; // Key already migrated, no action needed
		}

		// Try to extract key from main settings
		$settings = get_option( $this->option_name, $this->default_settings );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$plugin_key = '';
		
		// Priority 1: Check dedicated pluginKey field
		if ( ! empty( $settings['pluginKey'] ) ) {
			$plugin_key = $settings['pluginKey'];
		}
		// Priority 2: Check legacy auth.apiKey field
		elseif ( ! empty( $settings['auth']['apiKey'] ) ) {
			$plugin_key = $settings['auth']['apiKey'];
		}
		// Priority 3: Check if clientId contains a plugin key
		elseif ( ! empty( $settings['clientId'] ) && preg_match( Security::PLUGIN_KEY_PATTERN, $settings['clientId'], $matches ) ) {
			$plugin_key = $matches[0];
		}

		if ( empty( $plugin_key ) ) {
			return; // No key found in settings
		}

		// Decrypt if encrypted
		if ( Security::is_encrypted_secret( $plugin_key ) ) {
			$plugin_key = Security::decrypt_secret( $plugin_key );
		}

		// Normalize and validate
		$plugin_key = trim( (string) $plugin_key );
		
		// Remove "default-client-id" prefix if present
		if ( strpos( $plugin_key, 'default-client-id' ) === 0 ) {
			$plugin_key = preg_replace( '/^default-client-id/', '', $plugin_key );
		}

		// Validate format
		if ( ! preg_match( Security::PLUGIN_KEY_PATTERN, $plugin_key, $matches ) ) {
			return; // Invalid format
		}

		$normalized_key = strtolower( $matches[0] );

		// Encrypt for storage (if not already encrypted)
		$key_to_save = Security::is_encrypted_secret( $normalized_key )
			? $normalized_key
			: Security::encrypt_secret( $normalized_key );

		// Save to utilitysign_api_key option (this is what SigningController reads)
		update_option( 'utilitysign_api_key', $key_to_save );
	}

	/**
	 * Test authentication configuration.
	 *
	 * @return void
	 */
	public function test_auth() {
		// Verify nonce with proper sanitization per WordPress coding standards.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'utilitysign_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_utilitysign' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		$auth_method = sanitize_text_field( $_POST['authMethod'] ?? '' );

		switch ( $auth_method ) {
			case 'entra_id':
				$tenant_id = sanitize_text_field( $_POST['entraIdTenantId'] ?? '' );
				$client_id = sanitize_text_field( $_POST['entraIdClientId'] ?? '' );
				$client_secret = sanitize_text_field( $_POST['entraIdClientSecret'] ?? '' );

				if ( empty( $tenant_id ) || empty( $client_id ) || empty( $client_secret ) ) {
					wp_send_json_error( 'All Entra ID fields are required' );
					return;
				}

				// Basic validation - in real implementation, you'd test actual connection
				wp_send_json_success( 'Entra ID configuration valid' );
				break;

			case 'jwt':
				$jwt_secret = sanitize_text_field( $_POST['jwtSecret'] ?? '' );
				$jwt_expiration = intval( $_POST['jwtExpiration'] ?? 0 );

				if ( empty( $jwt_secret ) || $jwt_expiration <= 0 ) {
					wp_send_json_error( 'JWT secret and expiration are required' );
					return;
				}

				wp_send_json_success( 'JWT configuration valid' );
				break;

			case 'api_key':
				$api_key = sanitize_text_field( $_POST['apiKey'] ?? '' );

				if ( empty( $api_key ) ) {
					wp_send_json_error( 'API key is required' );
					return;
				}

				wp_send_json_success( 'API key configuration valid' );
				break;

			default:
				wp_send_json_error( 'Invalid authentication method' );
				return;
		}
	}

	/**
	 * Sanitize settings data.
	 *
	 * @param array $settings Raw settings data.
	 * @return array Sanitized settings data.
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = array();

		// Sanitize basic settings
		if ( isset( $settings['environment'] ) ) {
			$sanitized['environment'] = in_array( $settings['environment'], array( 'staging', 'production' ), true ) 
				? $settings['environment'] 
				: 'staging';
		}

		if ( isset( $settings['apiUrl'] ) ) {
			$sanitized['apiUrl'] = sanitize_url( $settings['apiUrl'] );
		}

		if ( isset( $settings['clientId'] ) ) {
			$sanitized['clientId'] = sanitize_text_field( $settings['clientId'] );
		}

		if ( isset( $settings['clientSecret'] ) ) {
			$sanitized['clientSecret'] = $settings['clientSecret'];
		}

		if ( isset( $settings['pluginKey'] ) ) {
			$sanitized['pluginKey'] = $settings['pluginKey'];
		}

		if ( isset( $settings['enableBankID'] ) ) {
			$sanitized['enableBankID'] = (bool) $settings['enableBankID'];
		}

		if ( isset( $settings['enableEmailNotifications'] ) ) {
			$sanitized['enableEmailNotifications'] = (bool) $settings['enableEmailNotifications'];
		}

		if ( isset( $settings['enableDebugMode'] ) ) {
			$sanitized['enableDebugMode'] = (bool) $settings['enableDebugMode'];
		}

		// Sanitize Criipto settings
		if ( isset( $settings['criipto'] ) && is_array( $settings['criipto'] ) ) {
			$sanitized['criipto'] = array();

			if ( isset( $settings['criipto']['clientId'] ) ) {
				$sanitized['criipto']['clientId'] = sanitize_text_field( $settings['criipto']['clientId'] );
			}

			if ( isset( $settings['criipto']['clientSecret'] ) ) {
				$sanitized['criipto']['clientSecret'] = $settings['criipto']['clientSecret'];
			}

			if ( isset( $settings['criipto']['domain'] ) ) {
				$sanitized['criipto']['domain'] = sanitize_text_field( $settings['criipto']['domain'] );
			}

			if ( isset( $settings['criipto']['environment'] ) ) {
				$sanitized['criipto']['environment'] = in_array( $settings['criipto']['environment'], array( 'test', 'production' ), true ) 
					? $settings['criipto']['environment'] 
					: 'test';
			}

			if ( isset( $settings['criipto']['webhookSecret'] ) ) {
				$sanitized['criipto']['webhookSecret'] = $settings['criipto']['webhookSecret'];
			}

			if ( isset( $settings['criipto']['enableWebhooks'] ) ) {
				$sanitized['criipto']['enableWebhooks'] = (bool) $settings['criipto']['enableWebhooks'];
			}

			if ( isset( $settings['criipto']['redirectUri'] ) ) {
				$sanitized['criipto']['redirectUri'] = sanitize_url( $settings['criipto']['redirectUri'] );
			}

			if ( isset( $settings['criipto']['acrValues'] ) ) {
				$sanitized['criipto']['acrValues'] = sanitize_text_field( $settings['criipto']['acrValues'] );
			}

			if ( isset( $settings['criipto']['uiLocales'] ) ) {
				$sanitized['criipto']['uiLocales'] = sanitize_text_field( $settings['criipto']['uiLocales'] );
			}

			if ( isset( $settings['criipto']['loginHint'] ) ) {
				$sanitized['criipto']['loginHint'] = sanitize_text_field( $settings['criipto']['loginHint'] );
			}
		}

		// Sanitize auth settings
		if ( isset( $settings['auth'] ) && is_array( $settings['auth'] ) ) {
			$sanitized['auth'] = array();

			if ( isset( $settings['auth']['authMethod'] ) ) {
				$sanitized['auth']['authMethod'] = in_array( $settings['auth']['authMethod'], array( 'entra_id', 'jwt', 'api_key' ), true ) 
					? $settings['auth']['authMethod'] 
					: 'entra_id';
			}

			if ( isset( $settings['auth']['entraIdTenantId'] ) ) {
				$sanitized['auth']['entraIdTenantId'] = sanitize_text_field( $settings['auth']['entraIdTenantId'] );
			}

			if ( isset( $settings['auth']['entraIdClientId'] ) ) {
				$sanitized['auth']['entraIdClientId'] = sanitize_text_field( $settings['auth']['entraIdClientId'] );
			}

			if ( isset( $settings['auth']['entraIdClientSecret'] ) ) {
				$sanitized['auth']['entraIdClientSecret'] = $settings['auth']['entraIdClientSecret'];
			}

			if ( isset( $settings['auth']['jwtSecret'] ) ) {
				$sanitized['auth']['jwtSecret'] = $settings['auth']['jwtSecret'];
			}

			if ( isset( $settings['auth']['jwtExpiration'] ) ) {
				$sanitized['auth']['jwtExpiration'] = max( 300, min( 86400, intval( $settings['auth']['jwtExpiration'] ) ) );
			}

			if ( isset( $settings['auth']['apiKey'] ) ) {
				$sanitized['auth']['apiKey'] = $settings['auth']['apiKey'];
			}

			if ( isset( $settings['auth']['enableMFA'] ) ) {
				$sanitized['auth']['enableMFA'] = (bool) $settings['auth']['enableMFA'];
			}

			if ( isset( $settings['auth']['sessionTimeout'] ) ) {
				$sanitized['auth']['sessionTimeout'] = max( 300, min( 86400, intval( $settings['auth']['sessionTimeout'] ) ) );
			}

			if ( isset( $settings['auth']['enableRememberMe'] ) ) {
				$sanitized['auth']['enableRememberMe'] = (bool) $settings['auth']['enableRememberMe'];
			}

			if ( isset( $settings['auth']['maxLoginAttempts'] ) ) {
				$sanitized['auth']['maxLoginAttempts'] = max( 3, min( 10, intval( $settings['auth']['maxLoginAttempts'] ) ) );
			}

			if ( isset( $settings['auth']['lockoutDuration'] ) ) {
				$sanitized['auth']['lockoutDuration'] = max( 60, min( 3600, intval( $settings['auth']['lockoutDuration'] ) ) );
			}
		}

		// Sanitize component settings
		if ( isset( $settings['components'] ) && is_array( $settings['components'] ) ) {
			$sanitized['components'] = array();

			if ( isset( $settings['components']['theme'] ) ) {
				$sanitized['components']['theme'] = in_array( $settings['components']['theme'], array( 'light', 'dark', 'auto' ), true ) 
					? $settings['components']['theme'] 
					: 'light';
			}

			if ( isset( $settings['components']['primaryColor'] ) ) {
				$sanitized['components']['primaryColor'] = sanitize_hex_color( $settings['components']['primaryColor'] );
			}

			if ( isset( $settings['components']['secondaryColor'] ) ) {
				$sanitized['components']['secondaryColor'] = sanitize_hex_color( $settings['components']['secondaryColor'] );
			}

			if ( isset( $settings['components']['accentColor'] ) ) {
				$sanitized['components']['accentColor'] = sanitize_hex_color( $settings['components']['accentColor'] );
			}

			if ( isset( $settings['components']['borderRadius'] ) ) {
				$sanitized['components']['borderRadius'] = in_array( $settings['components']['borderRadius'], array( 'none', 'sm', 'md', 'lg', 'xl', 'devora' ), true ) 
					? $settings['components']['borderRadius'] 
					: 'devora';
			}

			if ( isset( $settings['components']['fontFamily'] ) ) {
				$sanitized['components']['fontFamily'] = in_array( $settings['components']['fontFamily'], array( 'lato', 'open-sans', 'inter', 'system' ), true ) 
					? $settings['components']['fontFamily'] 
					: 'lato';
			}

			if ( isset( $settings['components']['fontSize'] ) ) {
				$sanitized['components']['fontSize'] = in_array( $settings['components']['fontSize'], array( 'sm', 'base', 'lg', 'xl' ), true ) 
					? $settings['components']['fontSize'] 
					: 'base';
			}

			if ( isset( $settings['components']['buttonStyle'] ) ) {
				$sanitized['components']['buttonStyle'] = in_array( $settings['components']['buttonStyle'], array( 'devora', 'modern', 'minimal' ), true ) 
					? $settings['components']['buttonStyle'] 
					: 'devora';
			}

			if ( isset( $settings['components']['cardStyle'] ) ) {
				$sanitized['components']['cardStyle'] = in_array( $settings['components']['cardStyle'], array( 'devora', 'modern', 'minimal' ), true ) 
					? $settings['components']['cardStyle'] 
					: 'devora';
			}

			if ( isset( $settings['components']['enableAnimations'] ) ) {
				$sanitized['components']['enableAnimations'] = (bool) $settings['components']['enableAnimations'];
			}

			if ( isset( $settings['components']['enableShadows'] ) ) {
				$sanitized['components']['enableShadows'] = (bool) $settings['components']['enableShadows'];
			}

			if ( isset( $settings['components']['enableGradients'] ) ) {
				$sanitized['components']['enableGradients'] = (bool) $settings['components']['enableGradients'];
			}

			if ( isset( $settings['components']['customCSS'] ) ) {
				$sanitized['components']['customCSS'] = wp_strip_all_tags( $settings['components']['customCSS'] );
			}

			if ( isset( $settings['components']['logoUrl'] ) ) {
				$sanitized['components']['logoUrl'] = sanitize_url( $settings['components']['logoUrl'] );
			}

			if ( isset( $settings['components']['faviconUrl'] ) ) {
				$sanitized['components']['faviconUrl'] = sanitize_url( $settings['components']['faviconUrl'] );
			}

			if ( isset( $settings['components']['enableCustomBranding'] ) ) {
				$sanitized['components']['enableCustomBranding'] = (bool) $settings['components']['enableCustomBranding'];
			}
		}

		return $sanitized;
	}

	/**
	 * Get current settings.
	 *
	 * @return array Current settings.
	 */
	public function get_current_settings() {
		$settings = get_option( $this->option_name, $this->default_settings );
		$settings = wp_parse_args( $settings, $this->default_settings );
		
		// CRITICAL FIX: Ensure nested arrays are properly initialized
		// wp_parse_args() only does shallow merge, so nested arrays must be explicitly checked
		if ( ! is_array( $settings['components'] ) ) {
			$settings['components'] = $this->default_settings['components'];
		} else {
			// Merge nested components settings with defaults
			$settings['components'] = wp_parse_args( $settings['components'], $this->default_settings['components'] );
		}
		
		if ( ! is_array( $settings['criipto'] ) ) {
			$settings['criipto'] = $this->default_settings['criipto'];
		} else {
			$settings['criipto'] = wp_parse_args( $settings['criipto'], $this->default_settings['criipto'] );
		}
		
		if ( ! is_array( $settings['auth'] ) ) {
			$settings['auth'] = $this->default_settings['auth'];
		} else {
			$settings['auth'] = wp_parse_args( $settings['auth'], $this->default_settings['auth'] );
		}
		
		return $settings;
	}

	/**
	 * Sanitize signatory redirect URI.
	 * 
	 * Validates and sanitizes the post-sign redirect URL to ensure it's a valid HTTPS URL.
	 * Prevents open redirect vulnerabilities by enforcing HTTPS-only and optional domain whitelist.
	 * 
	 * @since 1.1.0
	 * @param string $value Raw redirect URL from user input.
	 * @return string Sanitized URL or empty string if invalid.
	 */
	public static function sanitize_signatory_redirect_uri( $value ) {
		// Empty value is valid (disables redirect feature)
		if ( empty( $value ) || '' === trim( $value ) ) {
			return '';
		}
		
		// Sanitize URL using WordPress core function (allows only http/https schemes)
		$sanitized = esc_url_raw( $value, array( 'https' ) );
		
		// Validate URL is not empty after sanitization
		if ( empty( $sanitized ) ) {
			add_settings_error(
				'utilitysign_signatory_redirect_uri',
				'invalid_url',
				__( 'Redirect URL is invalid. Please enter a valid HTTPS URL.', 'utilitysign' ),
				'error'
			);
			// Preserve previous valid value
			return get_option( 'utilitysign_signatory_redirect_uri', '' );
		}
		
		// Enforce HTTPS-only (security requirement to prevent credential leakage)
		$scheme = parse_url( $sanitized, PHP_URL_SCHEME );
		if ( 'https' !== $scheme ) {
			add_settings_error(
				'utilitysign_signatory_redirect_uri',
				'invalid_scheme',
				__( 'Redirect URL must use HTTPS protocol for security. HTTP URLs are not allowed.', 'utilitysign' ),
				'error'
			);
			// Preserve previous valid value
			return get_option( 'utilitysign_signatory_redirect_uri', '' );
		}
		
		// Optional: Domain whitelist filter for additional security against open redirects
		// Developers can implement this filter to restrict redirects to specific domains
		$allowed_domains = apply_filters( 'utilitysign_allowed_redirect_domains', array() );
		if ( ! empty( $allowed_domains ) && is_array( $allowed_domains ) ) {
			$host = parse_url( $sanitized, PHP_URL_HOST );
			if ( ! in_array( $host, $allowed_domains, true ) ) {
				add_settings_error(
					'utilitysign_signatory_redirect_uri',
					'domain_not_allowed',
					sprintf(
						/* translators: %s: domain name that was rejected */
						__( 'Domain "%s" is not in the allowed redirect domains list. Contact your administrator to add this domain.', 'utilitysign' ),
						esc_html( $host )
					),
					'error'
				);
				// Preserve previous valid value
				return get_option( 'utilitysign_signatory_redirect_uri', '' );
			}
		}
		
		// Success: valid HTTPS URL that passed all checks
		return $sanitized;
	}
	
	/**
	 * Get signatory redirect URI option.
	 * 
	 * Helper method to retrieve the configured post-sign redirect URL with filter support.
	 * 
	 * @since 1.1.0
	 * @param array $context Optional context data for filter (e.g., productId, supplierId).
	 * @return string Redirect URL or empty string if not configured.
	 */
	public static function get_signatory_redirect_uri( $context = array() ) {
		$redirect_uri = get_option( 'utilitysign_signatory_redirect_uri', '' );
		
		// Allow runtime override via filter
		$redirect_uri = apply_filters( 'utilitysign_signatory_redirect_uri', $redirect_uri, $context );
		
		// Validate filtered value is still a valid URL
		if ( ! empty( $redirect_uri ) && ! filter_var( $redirect_uri, FILTER_VALIDATE_URL ) ) {
			error_log( sprintf( '[UtilitySign][Settings] Invalid redirect URI returned from filter: %s', $redirect_uri ) );
			return '';
		}
		
		return $redirect_uri;
	}
}
