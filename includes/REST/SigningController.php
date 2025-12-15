<?php
/**
 * Signing REST API proxy controller.
 *
 * Proxies public signing requests through WordPress so plugin keys never touch the browser.
 *
 * @package UtilitySign\REST
 * @since 1.0.0
 */

namespace UtilitySign\REST;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UtilitySign\Traits\Base;
use UtilitySign\Utils\Security;
use UtilitySign\Utils\FodselsnummerValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Signing REST API proxy controller.
 *
 * Proxies public signing requests through WordPress so plugin keys never touch the browser.
 */
class SigningController {
	use Base;

	/**
	 * Bootstrap controller.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_signing_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing/(?P<request_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_signing_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'request_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing/bankid/initiate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'initiate_bankid' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing/bankid/status/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bankid_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'session_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing/bankid/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_bankid_session' ),
				'permission_callback' => '__return_true',
			)
		);

		// Route to trigger signing completion (for post-signing email)
		register_rest_route(
			UTILITYSIGN_ROUTE_PREFIX,
			'/signing/(?P<request_id>[a-zA-Z0-9_-]+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_signing_completion' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'request_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle signing request creation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_signing_request( WP_REST_Request $request ) {
		// Debug logging
		$debug_mode = isset( $_GET['utilitysign_debug'] ) && ( '1' === $_GET['utilitysign_debug'] || 'true' === strtolower( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		// Track request timing and correlation ID
		$start_time = microtime( true );
		$payload = $request->get_json_params();
		$correlation_id = isset( $payload['correlationId'] ) ? sanitize_text_field( $payload['correlationId'] ) : ( 'wp-' . wp_generate_uuid4() );
		
		// #region agent log
		$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
		$log_entry = json_encode(array('id'=>'log_'.time().'_php15','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:134','message'=>'create_signing_request entry','data'=>array('hasPayload'=>!empty($payload),'payloadKeys'=>$payload?array_keys($payload):array(),'correlationId'=>$correlation_id),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
		@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
		// #endregion
		
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] create_signing_request called - CorrelationId: %s', $correlation_id ) );
		}
		
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php16','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:148','message'=>'Nonce verification failed','data'=>array('errorCode'=>$nonce_check->get_error_code(),'errorMessage'=>$nonce_check->get_error_message()),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Nonce verification failed' );
			}
			return $nonce_check;
		}

		// Payload already extracted above for correlation ID
		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] Payload received: ' . wp_json_encode( $payload ) );
		}

		$signer_name  = isset( $payload['signerName'] ) ? sanitize_text_field( $payload['signerName'] ) : '';
		$signer_email = isset( $payload['signerEmail'] ) ? sanitize_email( $payload['signerEmail'] ) : '';
		// Handle documentId: can be null, empty string, or a valid ID
		$document_id_raw = isset( $payload['documentId'] ) ? $payload['documentId'] : null;
		$document_id     = ( null !== $document_id_raw && '' !== $document_id_raw ) ? sanitize_text_field( $document_id_raw ) : null;
		$title           = isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';
		
		// Extract optional fields
		$phone         = isset( $payload['phone'] ) ? sanitize_text_field( $payload['phone'] ) : null;
		$date_of_birth = isset( $payload['dateOfBirth'] ) ? sanitize_text_field( $payload['dateOfBirth'] ) : null;
		$first_name    = isset( $payload['firstName'] ) ? sanitize_text_field( $payload['firstName'] ) : null;
		$last_name     = isset( $payload['lastName'] ) ? sanitize_text_field( $payload['lastName'] ) : null;
		$address       = isset( $payload['address'] ) ? sanitize_text_field( $payload['address'] ) : null;
		$city          = isset( $payload['city'] ) ? sanitize_text_field( $payload['city'] ) : null;
		$zip           = isset( $payload['zip'] ) ? sanitize_text_field( $payload['zip'] ) : null;
		$takeover_date = isset( $payload['takeoverDate'] ) ? sanitize_text_field( $payload['takeoverDate'] ) : null;
		$meter_number  = isset( $payload['meterNumber'] ) ? sanitize_text_field( $payload['meterNumber'] ) : null;
		$serial_number = isset( $payload['serialNumber'] ) ? sanitize_text_field( $payload['serialNumber'] ) : null;
		// Billing address fields (Fakturaaddresse) - optional (only present when different from delivery address)
		$billing_address = isset( $payload['billingAddress'] ) ? sanitize_text_field( $payload['billingAddress'] ) : null;
		$billing_city    = isset( $payload['billingCity'] ) ? sanitize_text_field( $payload['billingCity'] ) : null;
		$billing_zip     = isset( $payload['billingZip'] ) ? sanitize_text_field( $payload['billingZip'] ) : null;
		
		// #region agent log
		error_log( '[UtilitySign][SigningController] Billing address extracted - hasBillingAddress=' . (!empty($billing_address) ? 'YES (' . strlen($billing_address) . ' chars: ' . substr($billing_address, 0, 30) . '...)' : 'NO') . ', hasBillingCity=' . (!empty($billing_city) ? 'YES (' . strlen($billing_city) . ' chars)' : 'NO') . ', hasBillingZip=' . (!empty($billing_zip) ? 'YES (' . strlen($billing_zip) . ' chars)' : 'NO') . ', payloadHasBillingAddress=' . (isset($payload['billingAddress']) ? 'YES' : 'NO') . ', payloadHasBillingCity=' . (isset($payload['billingCity']) ? 'YES' : 'NO') . ', payloadHasBillingZip=' . (isset($payload['billingZip']) ? 'YES' : 'NO') );
		// #endregion
		
		// Business customer fields (for bedrift products)
		$company_name        = isset( $payload['companyName'] ) ? sanitize_text_field( $payload['companyName'] ) : null;
		$organization_number = isset( $payload['organizationNumber'] ) ? sanitize_text_field( $payload['organizationNumber'] ) : null;
		
		// Phase 3: Sports team and marketing consent fields
		$sports_team             = isset( $payload['sportsTeam'] ) ? sanitize_text_field( $payload['sportsTeam'] ) : null;
		$marketing_consent_email = isset( $payload['marketingConsentEmail'] ) ? (bool) $payload['marketingConsentEmail'] : false;
		$marketing_consent_sms   = isset( $payload['marketingConsentSms'] ) ? (bool) $payload['marketingConsentSms'] : false;
		
		// Optional fødselsnummer (personal number) field
		$fodselsnummer_raw = isset( $payload['fodselsnummer'] ) ? $payload['fodselsnummer'] : null;
		$fodselsnummer     = null;
		if ( null !== $fodselsnummer_raw && '' !== $fodselsnummer_raw ) {
			// Validate fødselsnummer if provided
			$validation_result = FodselsnummerValidator::validate( $fodselsnummer_raw );
			if ( ! $validation_result['is_valid'] ) {
				if ( $debug_mode ) {
					error_log( sprintf( '[UtilitySign][SigningController] Fødselsnummer validation failed: %s', $validation_result['error'] ) );
				}
				return new WP_Error( 'invalid_fodselsnummer', $validation_result['error'], array( 'status' => 400 ) );
			}
			// Use cleaned version
			$fodselsnummer = FodselsnummerValidator::clean( $fodselsnummer_raw );
		}

		// Extract ProductId and SupplierId for template resolution and form submission tracking
		$product_id    = isset( $payload['productId'] ) ? sanitize_text_field( $payload['productId'] ) : null;
		$supplier_id   = isset( $payload['supplierId'] ) ? sanitize_text_field( $payload['supplierId'] ) : null;

		if ( $debug_mode ) {
			error_log( sprintf(
				'[UtilitySign][SigningController] Extracted values: signerName="%s" (len=%d), signerEmail="%s", documentId=%s, title="%s", phone=%s, dateOfBirth=%s, firstName=%s, lastName=%s, address=%s, city=%s, zip=%s, takeoverDate=%s, meterNumber=%s, serialNumber=%s, companyName=%s, organizationNumber=%s, productId=%s, supplierId=%s',
				$signer_name,
				strlen( $signer_name ),
				$signer_email,
				$document_id === null ? 'null' : '"' . $document_id . '"',
				$title,
				$phone === null ? 'null' : '"' . $phone . '"',
				$date_of_birth === null ? 'null' : '"' . $date_of_birth . '"',
				$first_name === null ? 'null' : '"' . $first_name . '"',
				$last_name === null ? 'null' : '"' . $last_name . '"',
				$address === null ? 'null' : '"' . $address . '"',
				$city === null ? 'null' : '"' . $city . '"',
				$zip === null ? 'null' : '"' . $zip . '"',
				$takeover_date === null ? 'null' : '"' . $takeover_date . '"',
				$meter_number === null ? 'null' : '"' . $meter_number . '"',
				$serial_number === null ? 'null' : '"' . $serial_number . '"',
				$company_name === null ? 'null' : '"' . $company_name . '"',
				$organization_number === null ? 'null' : '"' . $organization_number . '"',
				$product_id === null ? 'null' : '"' . $product_id . '"',
				$supplier_id === null ? 'null' : '"' . $supplier_id . '"'
			) );
		}

		if ( empty( $signer_name ) || strlen( $signer_name ) < 2 ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Validation failed: signer_name empty or too short' );
			}
			return new WP_Error( 'invalid_signer_name', __( 'Signer name is required and must be at least two characters.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		if ( empty( $signer_email ) || ! is_email( $signer_email ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Validation failed: signer_email empty or invalid' );
			}
			return new WP_Error( 'invalid_signer_email', __( 'Please provide a valid signer email.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		$idempotency_key = isset( $payload['idempotencyKey'] ) ? sanitize_text_field( $payload['idempotencyKey'] ) : '';
		if ( empty( $idempotency_key ) ) {
			$idempotency_key = 'wp-signing-' . wp_generate_uuid4();
		}

		// Get supplier name for title/description (if available, fetch from database or use default)
		$supplier_name_for_email = 'Eiker Energi'; // Default fallback
		
		$backend_payload = array(
			'Title'          => $title ?: sprintf( 'Signeringsforespørsel for %s', $signer_name ),
			'Description'    => sprintf( __( 'Signeringsforespørsel fra %s', 'utilitysign' ), $supplier_name_for_email ),
			'SignerEmail'    => $signer_email,
			'SignerName'     => $signer_name,
			'CorrelationId'  => isset( $payload['correlationId'] ) ? sanitize_text_field( $payload['correlationId'] ) : ( 'wp-' . wp_generate_uuid4() ),
			'Environment'    => Security::is_staging_environment() ? 'staging' : 'production',
		);
		
		// Only include DocumentId if it's not null/empty
		if ( null !== $document_id && '' !== $document_id ) {
			$backend_payload['DocumentId'] = $document_id;
		}
		
		// Add optional fields if provided
		if ( null !== $phone && '' !== $phone ) {
			$backend_payload['SignerPhone'] = $phone;
		}
		if ( null !== $date_of_birth && '' !== $date_of_birth ) {
			$backend_payload['DateOfBirth'] = $date_of_birth;
		}
		if ( null !== $first_name && '' !== $first_name ) {
			$backend_payload['FirstName'] = $first_name;
		}
		if ( null !== $last_name && '' !== $last_name ) {
			$backend_payload['LastName'] = $last_name;
		}
		if ( null !== $address && '' !== $address ) {
			$backend_payload['Address'] = $address;
		}
		if ( null !== $city && '' !== $city ) {
			$backend_payload['City'] = $city;
		}
		if ( null !== $zip && '' !== $zip ) {
			$backend_payload['Zip'] = $zip;
		}
		// Billing address (only if provided - different from delivery address)
		if ( null !== $billing_address && '' !== $billing_address ) {
			$backend_payload['BillingAddress'] = $billing_address;
		}
		if ( null !== $billing_city && '' !== $billing_city ) {
			$backend_payload['BillingCity'] = $billing_city;
		}
		if ( null !== $billing_zip && '' !== $billing_zip ) {
			$backend_payload['BillingZip'] = $billing_zip;
		}
		
		// #region agent log
		error_log( '[UtilitySign][SigningController] Billing address added to backend payload - hasBillingAddress=' . (isset($backend_payload['BillingAddress']) ? 'YES (' . substr($backend_payload['BillingAddress'], 0, 30) . '...)' : 'NO') . ', hasBillingCity=' . (isset($backend_payload['BillingCity']) ? 'YES' : 'NO') . ', hasBillingZip=' . (isset($backend_payload['BillingZip']) ? 'YES' : 'NO') );
		// #endregion
		if ( null !== $takeover_date && '' !== $takeover_date ) {
			$backend_payload['TakeoverDate'] = $takeover_date;
		}
		if ( null !== $meter_number && '' !== $meter_number ) {
			$backend_payload['MeterNumber'] = $meter_number;
		}
		if ( null !== $serial_number && '' !== $serial_number ) {
			$backend_payload['SerialNumber'] = $serial_number;
		}
		
		// Business customer fields (for bedrift products)
		if ( null !== $company_name && '' !== $company_name ) {
			$backend_payload['CompanyName'] = $company_name;
		}
		if ( null !== $organization_number && '' !== $organization_number ) {
			$backend_payload['OrganizationNumber'] = $organization_number;
		}
		
		// Phase 3: Sports team and marketing consent fields
		if ( null !== $sports_team && '' !== $sports_team ) {
			$backend_payload['SupportedSportsTeam'] = $sports_team;
		}
		// Always include marketing consent fields (even if false) to ensure proper storage
		$backend_payload['MarketingConsentEmail'] = $marketing_consent_email;
		$backend_payload['MarketingConsentSms']   = $marketing_consent_sms;
		
		// Optional fødselsnummer (personal number) - forward to backend if provided
		if ( null !== $fodselsnummer && '' !== $fodselsnummer ) {
			$backend_payload['PersonalNumber'] = $fodselsnummer;
		}
		
		// Forward ProductId and SupplierId to backend for template resolution and form submission tracking
		if ( null !== $product_id && '' !== $product_id ) {
			$backend_payload['ProductId'] = $product_id;
		}
		if ( null !== $supplier_id && '' !== $supplier_id ) {
			$backend_payload['SupplierId'] = $supplier_id;
		}
		
		// Include appearance config with redirect URI (if configured)
		// This leverages the backend's existing CriiptoAppearanceResolver infrastructure
		// Priority order: Template > Supplier > WordPress > Global
		$redirect_uri = \UtilitySign\Admin\Settings::get_signatory_redirect_uri( $payload );
		
		if ( ! empty( $redirect_uri ) && filter_var( $redirect_uri, FILTER_VALIDATE_URL ) ) {
			// Build appearance config matching backend's CriiptoAppearanceConfig structure
			// The backend will merge this with Template/Supplier/Global config (WordPress has priority over Global)
			$backend_payload['Appearance'] = array(
				'Ui' => array(
					'SignatoryRedirectUri' => $redirect_uri,
					'Language'             => 'NB_NO' // Default Norwegian, can be overridden by higher-priority sources
				)
			);
			
			if ( $debug_mode ) {
				error_log( sprintf( '[UtilitySign][SigningController] Including WordPress redirect URI in appearance config: %s', $redirect_uri ) );
			}
		}
		
		// #region agent log
		$billing_payload_keys = array();
		foreach (array('BillingAddress', 'BillingCity', 'BillingZip') as $key) {
			if (isset($backend_payload[$key])) {
				$billing_payload_keys[$key] = substr($backend_payload[$key], 0, 30);
			}
		}
		error_log( '[UtilitySign][SigningController] Final backend payload - hasBillingAddress=' . (isset($backend_payload['BillingAddress']) ? 'YES (' . strlen($backend_payload['BillingAddress']) . ' chars)' : 'NO') . ', hasBillingCity=' . (isset($backend_payload['BillingCity']) ? 'YES (' . strlen($backend_payload['BillingCity']) . ' chars)' : 'NO') . ', hasBillingZip=' . (isset($backend_payload['BillingZip']) ? 'YES (' . strlen($backend_payload['BillingZip']) . ' chars)' : 'NO') . ', billingValues=' . wp_json_encode($billing_payload_keys) . ', allPayloadKeys=' . implode(',', array_keys($backend_payload)) );
		// #endregion
		
		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] Backend payload: ' . wp_json_encode( $backend_payload ) );
		}

		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] Calling proxy_backend_request...' );
		}
		
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] Calling backend API - CorrelationId: %s, ProductId: %s, SupplierId: %s', 
				$correlation_id,
				$product_id ?? 'null',
				$supplier_id ?? 'null' ) );
		}
		
		$response = $this->proxy_backend_request(
			'POST',
			'/api/v1.0/wordpress/signing',
			$backend_payload,
			array( 
				'X-Idempotency-Key' => $idempotency_key,
				'x-correlation-id' => $correlation_id // Forward correlation ID to backend
			)
		);

		$duration = microtime( true ) - $start_time;
		
		if ( is_wp_error( $response ) ) {
			if ( $debug_mode ) {
				error_log( sprintf( '[UtilitySign][SigningController] Backend request failed - CorrelationId: %s, Duration: %.2fs, Error: %s', 
					$correlation_id,
					$duration,
					$response->get_error_message() ) );
			}
			error_log( sprintf( '[UtilitySign][SigningController] Backend request failed - CorrelationId: %s, Duration: %.2fs, Error: %s', 
				$correlation_id,
				$duration,
				$response->get_error_message() ) ); // Always log errors, not just in debug mode
			return $response;
		}

		$status_code = $response->get_status();
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] Backend request succeeded - CorrelationId: %s, Duration: %.2fs, Status: %d', 
				$correlation_id,
				$duration,
				$status_code ) );
		}
		error_log( sprintf( '[UtilitySign][SigningController] Backend request completed - CorrelationId: %s, Duration: %.2fs, Status: %d', 
			$correlation_id,
			$duration,
			$status_code ) ); // Always log completion for monitoring

		// Map backend response to frontend format (PascalCase -> snake_case)
		$response_data = $response->get_data();
		if ( is_array( $response_data ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Response data keys: ' . implode( ', ', array_keys( $response_data ) ) );
				error_log( '[UtilitySign][SigningController] SigningUrl in response: ' . ( isset( $response_data['SigningUrl'] ) ? 'yes (' . substr( $response_data['SigningUrl'], 0, 50 ) . '...)' : 'no' ) );
			}
			
			// Map SigningUrl (PascalCase) to signing_url (snake_case) for frontend compatibility
			if ( isset( $response_data['SigningUrl'] ) && ! isset( $response_data['signing_url'] ) ) {
				$response_data['signing_url'] = $response_data['SigningUrl'];
				if ( $debug_mode ) {
					error_log( '[UtilitySign][SigningController] Mapped SigningUrl to signing_url' );
				}
			}
			// Map other PascalCase fields to snake_case
			if ( isset( $response_data['Id'] ) && ! isset( $response_data['id'] ) ) {
				$response_data['id'] = $response_data['Id'];
			}
			if ( isset( $response_data['DocumentId'] ) && ! isset( $response_data['document_id'] ) ) {
				$response_data['document_id'] = $response_data['DocumentId'];
			}
			if ( isset( $response_data['SignerEmail'] ) && ! isset( $response_data['signer_email'] ) ) {
				$response_data['signer_email'] = $response_data['SignerEmail'];
			}
			if ( isset( $response_data['SignerName'] ) && ! isset( $response_data['signer_name'] ) ) {
				$response_data['signer_name'] = $response_data['SignerName'];
			}
			if ( isset( $response_data['Status'] ) && ! isset( $response_data['status'] ) ) {
				$response_data['status'] = $response_data['Status'];
			}
			if ( isset( $response_data['CreatedAt'] ) && ! isset( $response_data['created_at'] ) ) {
				$response_data['created_at'] = $response_data['CreatedAt'];
			}
			if ( isset( $response_data['ExpiresAt'] ) && ! isset( $response_data['expires_at'] ) ) {
				$response_data['expires_at'] = $response_data['ExpiresAt'];
			}
			if ( isset( $response_data['CompletedAt'] ) && ! isset( $response_data['completed_at'] ) ) {
				$response_data['completed_at'] = $response_data['CompletedAt'];
			}
			if ( isset( $response_data['CriiptoOrderId'] ) && ! isset( $response_data['criiptoOrderId'] ) ) {
				$response_data['criiptoOrderId'] = $response_data['CriiptoOrderId'];
			}
			
			$rest_response = new WP_REST_Response( $response_data, $response->get_status() );
			// Ensure UTF-8 charset in response headers
			$rest_response->header( 'Content-Type', 'application/json; charset=UTF-8' );
			return $rest_response;
		}

		// Ensure UTF-8 charset for error responses too
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$rest_response = $response;
		if ( $rest_response instanceof WP_REST_Response ) {
			$rest_response->header( 'Content-Type', 'application/json; charset=UTF-8' );
		}
		return $rest_response;
	}

	/**
	 * Retrieve signing status (or document details).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_signing_status( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$request_id = sanitize_text_field( $request->get_param( 'request_id' ) );
		if ( empty( $request_id ) ) {
			return new WP_Error( 'invalid_request_id', __( 'Signing request ID is required.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		return $this->proxy_backend_request(
			'GET',
			sprintf( '/api/v1.0/signing/%s', rawurlencode( $request_id ) )
		);
	}

	/**
	 * Initiate BankID session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function initiate_bankid( WP_REST_Request $request ) {
		$debug_mode = isset( $_GET['utilitysign_debug'] ) && ( '1' === $_GET['utilitysign_debug'] || 'true' === strtolower( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] initiate_bankid called' );
		}
		
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Nonce verification failed in initiate_bankid' );
			}
			return $nonce_check;
		}

		$payload    = $request->get_json_params();
		$request_id = isset( $payload['requestId'] ) ? sanitize_text_field( $payload['requestId'] ) : '';

		if ( empty( $request_id ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Missing requestId in initiate_bankid' );
			}
			return new WP_Error( 'invalid_request_id', __( 'Signing request ID is required to initiate BankID.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		// For Criipto flow, we need to retrieve the signing request to get the SigningUrl
		// First, try to get the signing request to retrieve the SigningUrl
		$signing_request_response = $this->proxy_backend_request(
			'GET',
			sprintf( '/api/v1.0/signing/%s', rawurlencode( $request_id ) )
		);

		if ( is_wp_error( $signing_request_response ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Failed to retrieve signing request: ' . $signing_request_response->get_error_message() );
			}
			return $signing_request_response;
		}

		$signing_request_data = $signing_request_response->get_data();
		// Check both PascalCase (from backend) and snake_case (mapped) formats
		$signing_url = isset( $signing_request_data['SigningUrl'] ) ? $signing_request_data['SigningUrl'] : 
		              ( isset( $signing_request_data['signingUrl'] ) ? $signing_request_data['signingUrl'] : 
		              ( isset( $signing_request_data['signing_url'] ) ? $signing_request_data['signing_url'] : null ) );

		if ( ! empty( $signing_url ) ) {
			// Signing URL is already available - return it directly
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Signing URL found in signing request: ' . substr( $signing_url, 0, 50 ) . '...' );
			}
			
			// Get CriiptoOrderId from response (check both PascalCase and camelCase)
			$criipto_order_id = isset( $signing_request_data['CriiptoOrderId'] ) ? $signing_request_data['CriiptoOrderId'] : 
			                    ( isset( $signing_request_data['criiptoOrderId'] ) ? $signing_request_data['criiptoOrderId'] : $request_id );
			
			return new WP_REST_Response( array(
				'auth_url'      => $signing_url,
				'session_id'   => $criipto_order_id,
				'correlation_id' => isset( $payload['correlationId'] ) ? sanitize_text_field( $payload['correlationId'] ) : ( 'wp-bankid-' . wp_generate_uuid4() ),
			), 200 );
		}

		// Fallback: If SigningUrl is not available, try BankID flow endpoint
		// Note: This requires PersonalNumber which we don't have, so this will likely fail
		// But we include it for backward compatibility
		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] Signing URL not found, attempting BankID flow endpoint (may fail without PersonalNumber)' );
		}

		$body = array(
			'DocumentId'     => $request_id, // PascalCase for .NET backend
			'PersonalNumber' => '', // Required but empty - will cause validation error
			'EndUserIp'      => '', // PascalCase
		);

		return $this->proxy_backend_request(
			'POST',
			'/api/v1.0/bankid/flow/signing',
			$body
		);
	}

	/**
	 * Poll BankID session status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_bankid_status( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		if ( empty( $session_id ) ) {
			return new WP_Error( 'invalid_session_id', __( 'BankID session ID is required.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		return $this->proxy_backend_request(
			'GET',
			sprintf( '/api/v1.0/bankid/auth/status/%s', rawurlencode( $session_id ) )
		);
	}

	/**
	 * Cancel BankID session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_bankid_session( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$payload    = $request->get_json_params();
		$session_id = isset( $payload['sessionId'] ) ? sanitize_text_field( $payload['sessionId'] ) : '';

		if ( empty( $session_id ) ) {
			return new WP_Error( 'invalid_session_id', __( 'BankID session ID is required to cancel.', 'utilitysign' ), array( 'status' => 400 ) );
		}

		$body = array(
			'orderRef' => $session_id,
		);

		return $this->proxy_backend_request(
			'POST',
			'/api/v1.0/bankid/auth/cancel',
			$body
		);
	}

	/**
	 * Verify REST nonce header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function verify_nonce( WP_REST_Request $request ) {
		$debug_mode = isset( $_GET['utilitysign_debug'] ) && ( '1' === $_GET['utilitysign_debug'] || 'true' === strtolower( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		$nonce = $request->get_header( 'X-WP-Nonce' );
		
		if ( $debug_mode ) {
			error_log( sprintf(
				'[UtilitySign][SigningController] Nonce verification: nonce=%s, length=%d, empty=%s',
				$nonce ? substr( $nonce, 0, 10 ) . '...' : 'null',
				$nonce ? strlen( $nonce ) : 0,
				empty( $nonce ) ? 'yes' : 'no'
			) );
		}
		
		if ( empty( $nonce ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Nonce is empty' );
			}
			return new WP_Error(
				'invalid_nonce',
				__( 'Security check failed. Refresh the page and try again.', 'utilitysign' ),
				array( 'status' => 403 )
			);
		}
		
		$verified = wp_verify_nonce( $nonce, 'wp_rest' );
		if ( ! $verified ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Nonce verification failed: wp_verify_nonce returned false' );
			}
			return new WP_Error(
				'invalid_nonce',
				__( 'Security check failed. Refresh the page and try again.', 'utilitysign' ),
				array( 'status' => 403 )
			);
		}
		
		if ( $debug_mode ) {
			error_log( '[UtilitySign][SigningController] Nonce verification successful' );
		}

		return true;
	}

	/**
	 * Proxy request to .NET backend with plugin key authentication.
	 *
	 * @param string     $method        HTTP method.
	 * @param string     $path          Backend path.
	 * @param array|null $body          Request body.
	 * @param array      $extra_headers Additional headers.
	 * @return WP_REST_Response|WP_Error
	 */
	private function proxy_backend_request( string $method, string $path, ?array $body = null, array $extra_headers = array() ) {
		$key_info = Security::get_plugin_key_info();
		if ( 'valid' !== $key_info['status'] ) {
			return new WP_Error(
				'missing_plugin_key',
				__( 'Plugin key is not configured. Please enter the plugin key in UtilitySign → Settings.', 'utilitysign' ),
				array( 'status' => 500 )
			);
		}

		// Use ApiClient to get a properly authenticated token
		try {
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php17','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:714','message'=>'Creating ApiClient for signing','data'=>array(),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			$api_client = new \UtilitySign\Core\ApiClient();
			$config_status = $api_client->get_config_status();
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php18','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:715','message'=>'ApiClient config status for signing','data'=>array('apiUrl'=>$config_status['api_url'],'hasPluginKey'=>$config_status['has_plugin_key'],'hasPluginSecret'=>$config_status['has_plugin_secret'],'isConfigured'=>$config_status['is_configured']),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			$auth_result = $api_client->authenticate();
			
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php19','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:716','message'=>'Authentication result for signing','data'=>array('isWpError'=>is_wp_error($auth_result),'errorCode'=>is_wp_error($auth_result)?$auth_result->get_error_code():null,'errorMessage'=>is_wp_error($auth_result)?$auth_result->get_error_message():null,'hasAccessToken'=>!is_wp_error($auth_result)&&isset($auth_result['accessToken']),'hasCached'=>!is_wp_error($auth_result)&&isset($auth_result['cached'])),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			
			if ( is_wp_error( $auth_result ) ) {
				// #region agent log
				$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
				$log_entry = json_encode(array('id'=>'log_'.time().'_php20','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:717','message'=>'Authentication failed in signing','data'=>array('errorCode'=>$auth_result->get_error_code(),'errorMessage'=>$auth_result->get_error_message()),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
				@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
				// #endregion
				error_log( '[UtilitySign][SigningController] Authentication failed: ' . $auth_result->get_error_message() );
				return $auth_result;
			}
			
			$access_token = isset( $auth_result['accessToken'] ) ? $auth_result['accessToken'] : null;
			if ( empty( $access_token ) ) {
				// #region agent log
				$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
				$log_entry = json_encode(array('id'=>'log_'.time().'_php21','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:723','message'=>'No access token in auth result','data'=>array('authResultKeys'=>is_array($auth_result)?array_keys($auth_result):array()),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
				@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
				// #endregion
				error_log( '[UtilitySign][SigningController] Authentication succeeded but no accessToken in response' );
				return new WP_Error(
					'auth_token_missing',
					__( 'Authentication succeeded but no access token received', 'utilitysign' ),
					array( 'status' => 500 )
				);
			}
			
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$token_preview = substr($access_token, 0, 50);
			$log_entry = json_encode(array('id'=>'log_'.time().'_php27','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:730','message'=>'Access token obtained for signing','data'=>array('tokenLength'=>strlen($access_token),'tokenPreview'=>$token_preview,'tokenStart'=>substr($access_token,0,20)),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
		} catch ( \Exception $e ) {
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php22','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:731','message'=>'ApiClient exception in signing','data'=>array('error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			error_log( '[UtilitySign][SigningController] ApiClient error: ' . $e->getMessage() );
			return new WP_Error(
				'api_client_error',
				__( 'Failed to initialize API client', 'utilitysign' ),
				array( 'status' => 500 )
			);
		}

		$endpoint   = trailingslashit( Security::get_backend_api_url() ) . ltrim( $path, '/' );

		$correlation_id = 'wp-rest-' . wp_generate_uuid4();
		$headers = array_merge(
			array(
				'Authorization'       => 'Bearer ' . $access_token,
				'Content-Type'        => 'application/json; charset=UTF-8',
				'Accept'              => 'application/json; charset=UTF-8',
				'x-request-source'    => 'wordpress-plugin',
				'x-plugin-version'    => UTILITYSIGN_VERSION,
				'x-client-id'         => Security::get_site_client_id(),
				'x-correlation-id'    => $correlation_id,
			),
			$extra_headers
		);
		
		$debug_mode = isset( $_GET['utilitysign_debug'] ) && ( '1' === $_GET['utilitysign_debug'] || 'true' === strtolower( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		$default_timeout = 90;
		$timeout_seconds = apply_filters( 'utilitysign_backend_timeout_seconds', $default_timeout );
		if ( ! is_numeric( $timeout_seconds ) || (int) $timeout_seconds < 10 ) {
			$timeout_seconds = $default_timeout;
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => (int) $timeout_seconds, // Filterable via utilitysign_backend_timeout_seconds
		);

		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] Backend timeout set to %ds via filter', (int) $timeout_seconds ) );
		}

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Backend request: ' . $method . ' ' . $endpoint );
				error_log( '[UtilitySign][SigningController] Request body: ' . $args['body'] );
			}
		}

		// #region agent log
		$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
		$log_entry = json_encode(array('id'=>'log_'.time().'_php23','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:783','message'=>'Making backend request for signing','data'=>array('endpoint'=>$endpoint,'method'=>$method,'hasAccessToken'=>!empty($access_token),'tokenLength'=>strlen($access_token??''),'hasBody'=>null!==$body),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
		@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
		// #endregion
		
		$request_start_time = microtime( true );
		$response = wp_remote_request( $endpoint, $args );
		$request_duration = microtime( true ) - $request_start_time;
		
		// #region agent log
		$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
		$status_code = !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : null;
		$body_preview = !is_wp_error($response) ? substr(wp_remote_retrieve_body($response),0,200) : null;
		$log_entry = json_encode(array('id'=>'log_'.time().'_php24','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:784','message'=>'Backend response received for signing','data'=>array('isWpError'=>is_wp_error($response),'errorCode'=>is_wp_error($response)?$response->get_error_code():null,'errorMessage'=>is_wp_error($response)?$response->get_error_message():null,'statusCode'=>$status_code,'bodyPreview'=>$body_preview),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
		@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
		// #endregion
		
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$error_message = $response->get_error_message();
			
			if ( $debug_mode ) {
				error_log(
					sprintf(
						'[UtilitySign][SigningController] wp_remote_request WP_Error - Endpoint: %s, Duration: %.2fs, Code: %s, Message: %s',
						$endpoint,
						$request_duration,
						$error_code,
						$error_message
					)
				);
			}
			
			// Always log errors for monitoring
			error_log(
				sprintf(
					'[UtilitySign][SigningController] Backend request failed - CorrelationId: %s, Endpoint: %s, DurationMs: %.2f, Code: %s, Message: %s',
					$correlation_id,
					$endpoint,
					$request_duration * 1000,
					$error_code,
					$error_message
				)
			);
			
			// Check if it's a timeout
			if ( 'http_request_failed' === $error_code && ( strpos( $error_message, 'timeout' ) !== false || $request_duration >= 89 ) ) {
				error_log( sprintf( '[UtilitySign][SigningController] Backend request TIMEOUT - Duration: %.2fs (timeout set to 90s)', $request_duration ) );
			}
			
			return new WP_Error(
				'backend_unreachable',
				sprintf(
					/* translators: %s: error message */
					__( 'Unable to reach UtilitySign API: %s', 'utilitysign' ),
					$error_message
				),
				array( 'status' => 502 )
			);
		}

		$status_code      = wp_remote_retrieve_response_code( $response );
		$body_raw         = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response );
		$header_subset    = array();
		if ( is_object( $response_headers ) && method_exists( $response_headers, 'getAll' ) ) {
			$response_headers = $response_headers->getAll();
		}

		$header_keys_to_log = array( 'date', 'content-type', 'server', 'request-context', 'x-correlation-id', 'x-request-id' );
		foreach ( $header_keys_to_log as $header_key ) {
			if ( isset( $response_headers[ $header_key ] ) ) {
				$header_subset[ $header_key ] = $response_headers[ $header_key ];
			}
		}

		error_log(
			sprintf(
				'[UtilitySign][SigningController] Backend response summary - CorrelationId: %s, Status: %d, DurationMs: %.2f, Headers: %s',
				$correlation_id,
				$status_code,
				$request_duration * 1000,
				wp_json_encode( $header_subset )
			)
		);
		
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] Backend response: Status=%d, Body=%s', $status_code, substr( $body_raw, 0, 500 ) ) );
		}
		
		$data = json_decode( $body_raw, true );

		// Handle error responses from backend
		if ( $status_code >= 400 ) {
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php25','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:862','message'=>'Backend error response for signing','data'=>array('statusCode'=>$status_code,'dataKeys'=>is_array($data)?array_keys($data):array(),'hasMessage'=>is_array($data)&&isset($data['message']),'hasError'=>is_array($data)&&isset($data['error']),'hasCode'=>is_array($data)&&isset($data['code']),'bodyPreview'=>substr($body_raw,0,300)),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			$error_message = 'Backend request failed';
			$error_code = null;
			$mapped_status = $status_code;
			
			if ( is_array( $data ) ) {
				// Try to extract error message from backend response
				if ( isset( $data['message'] ) ) {
					$error_message = $data['message'];
				} elseif ( isset( $data['error'] ) ) {
					$error_message = is_string( $data['error'] ) ? $data['error'] : wp_json_encode( $data['error'] );
				} elseif ( isset( $data['errors'] ) ) {
					// ASP.NET Core ModelState format: { "field": ["error1", "error2"] }
					$errors = array();
					foreach ( $data['errors'] as $field => $field_errors ) {
						if ( is_array( $field_errors ) ) {
							$errors[] = $field . ': ' . implode( ', ', $field_errors );
						} else {
							$errors[] = $field . ': ' . $field_errors;
						}
					}
					$error_message = implode( '; ', $errors );
				} else {
					$error_message = $body_raw;
				}
				
				// Extract errorCode from backend response for frontend consumption
				if ( isset( $data['errorCode'] ) ) {
					$error_code = sanitize_text_field( $data['errorCode'] );
				}
				
				// #region agent log
				$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
				$log_entry = json_encode(array('id'=>'log_'.time().'_php26','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:888','message'=>'Extracted error details for signing','data'=>array('errorMessage'=>$error_message,'errorCode'=>$error_code,'mappedStatus'=>$mapped_status,'dataCode'=>isset($data['code'])?$data['code']:null),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
				@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
				// #endregion
				
				// Map backend error codes to appropriate HTTP status codes
				// Configuration errors (400 from backend) should remain 400
				// Service unavailability (503 from backend) should remain 503
				// But we can also map specific error codes for better frontend handling
				if ( $error_code === 'CRIIPTO_NOT_CONFIGURED' || $error_code === 'CRIIPTO_SUPPLIER_NOT_CONFIGURED' ) {
					// Configuration errors are client errors (400), not service unavailability (503)
					$mapped_status = 400;
				} elseif ( $status_code === 503 && ( $error_code === null || strpos( $error_message, 'timeout' ) !== false || strpos( $error_message, 'unavailable' ) !== false ) ) {
					// True service unavailability remains 503
					$mapped_status = 503;
				} else {
					// Preserve backend status code for other errors
					$mapped_status = $status_code;
				}
			} else {
				$error_message = $body_raw;
			}
			
			// Structured logging with correlation ID
			$log_data = array(
				'timestamp' => current_time( 'mysql', true ),
				'level' => $mapped_status >= 500 ? 'error' : 'warning',
				'event' => 'backend_request_failed',
				'correlation_id' => $correlation_id,
				'route' => $path,
				'status' => $mapped_status,
				'backend_status' => $status_code,
				'error_code' => $error_code,
				'message' => $error_message,
				'duration_ms' => round( $request_duration * 1000, 2 ),
			);
			error_log( '[UtilitySign] ' . wp_json_encode( $log_data ) );
			
			// #region agent log
			$log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
			$log_entry = json_encode(array('id'=>'log_'.time().'_php28','timestamp'=>round(microtime(true)*1000),'location'=>'SigningController.php:926','message'=>'Creating WP_Error for backend failure','data'=>array('errorMessage'=>$error_message,'errorCode'=>$error_code,'mappedStatus'=>$mapped_status,'statusCode'=>$status_code,'dataCode'=>isset($data['code'])?$data['code']:null,'dataMessage'=>isset($data['message'])?$data['message']:null),'sessionId'=>'debug-session','runId'=>'run2','hypothesisId'=>'F'))."\n";
			@file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
			// #endregion
			
			// Check if backend returned a specific error code that should be preserved
			$wp_error_code = 'backend_request_failed';
			if ( isset( $data['code'] ) && ! empty( $data['code'] ) ) {
				// Preserve backend error code if present (e.g., 'rest_plugin_auth_error')
				$wp_error_code = sanitize_text_field( $data['code'] );
			}
			
			return new WP_Error(
				$wp_error_code,
				$error_message,
				array( 
					'status' => $mapped_status, 
					'backend_response' => $data,
					'error_code' => $error_code,
					'correlation_id' => $correlation_id,
				)
			);
		}

		if ( null === $data ) {
			$data = array(
				'success' => $status_code >= 200 && $status_code < 300,
				'message' => $body_raw,
			);
		}

		return new WP_REST_Response( $data, $status_code );
	}

	/**
	 * Trigger signing completion to ensure post-signing email is sent.
	 * 
	 * This endpoint should be called after the user returns from Criipto signing.
	 * It triggers the backend to check Criipto for completion status and send
	 * the completion email if the signing is complete.
	 *
	 * @param WP_REST_Request $request Request containing request_id.
	 * @return WP_REST_Response|WP_Error
	 */
	public function trigger_signing_completion( WP_REST_Request $request ) {
		$debug_mode = isset( $_GET['utilitysign_debug'] ) && ( '1' === $_GET['utilitysign_debug'] || 'true' === strtolower( $_GET['utilitysign_debug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		$request_id = $request->get_param( 'request_id' );
		
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] trigger_signing_completion called - RequestId: %s', $request_id ) );
		}
		
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			if ( $debug_mode ) {
				error_log( '[UtilitySign][SigningController] Nonce verification failed for trigger_signing_completion' );
			}
			return $nonce_check;
		}
		
		if ( empty( $request_id ) ) {
			return new WP_Error( 
				'invalid_request_id', 
				__( 'Signing request ID is required.', 'utilitysign' ), 
				array( 'status' => 400 ) 
			);
		}
		
		// Proxy the request to the backend
		// Note: Backend endpoint is /check-completion (not /complete)
		$backend_path = sprintf( 'api/v1.0/signing/%s/check-completion', rawurlencode( $request_id ) );
		
		if ( $debug_mode ) {
			error_log( sprintf( '[UtilitySign][SigningController] Proxying to backend: POST %s', $backend_path ) );
		}
		
		return $this->proxy_backend_request( 'POST', $backend_path );
	}
}

