<?php
/**
 * Unit tests for Settings::sanitize_signatory_redirect_uri()
 *
 * Tests redirect URL validation and sanitization to ensure security and WordPress standards compliance.
 *
 * @package UtilitySign\Tests\Unit
 * @since 1.1.0
 */

namespace UtilitySign\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UtilitySign\Admin\Settings;
use WP_Mock;

/**
 * Test suite for redirect URI sanitization
 */
class SettingsRedirectTest extends TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		WP_Mock::setUp();
		
		// Mock WordPress functions used in sanitization
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnUsing(
			function( $url, $protocols = null ) {
				// Simplified esc_url_raw mock
				if ( empty( $url ) ) {
					return '';
				}
				$parsed = parse_url( $url );
				if ( false === $parsed || ! isset( $parsed['scheme'] ) ) {
					return '';
				}
				if ( $protocols && ! in_array( $parsed['scheme'], $protocols, true ) ) {
					return '';
				}
				return $url;
			}
		);
		
		WP_Mock::userFunction( 'add_settings_error' )->andReturnNull();
		WP_Mock::userFunction( 'get_option' )->andReturn( '' );
		WP_Mock::userFunction( '__' )->andReturnUsing(
			function( $text ) {
				return $text;
			}
		);
		WP_Mock::userFunction( 'esc_html' )->andReturnUsing(
			function( $text ) {
				return htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
			function( $tag, $value ) {
				return $value;
			}
		);
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Test: Empty value should be accepted (disables redirect feature)
	 */
	public function test_empty_value_accepted() {
		$result = Settings::sanitize_signatory_redirect_uri( '' );
		$this->assertSame( '', $result, 'Empty value should be accepted (disables redirect)' );
	}

	/**
	 * Test: Whitespace-only value should return empty string
	 */
	public function test_whitespace_value_returns_empty() {
		$result = Settings::sanitize_signatory_redirect_uri( '   ' );
		$this->assertSame( '', $result, 'Whitespace-only value should return empty string' );
	}

	/**
	 * Test: Valid HTTPS URL should be accepted
	 */
	public function test_valid_https_url_accepted() {
		$url = 'https://eiker.devora.no/thank-you';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'Valid HTTPS URL should be accepted' );
	}

	/**
	 * Test: HTTPS URL with query parameters should be accepted
	 */
	public function test_https_url_with_query_params_accepted() {
		$url = 'https://example.com/thank-you?source=bankid&utm_medium=signature';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'HTTPS URL with query parameters should be accepted' );
	}

	/**
	 * Test: HTTPS URL with fragment should be accepted
	 */
	public function test_https_url_with_fragment_accepted() {
		$url = 'https://example.com/thank-you#success';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'HTTPS URL with fragment should be accepted' );
	}

	/**
	 * Test: HTTP URL should be rejected (security requirement)
	 */
	public function test_http_url_rejected() {
		WP_Mock::userFunction( 'esc_url_raw' )->andReturn( '' ); // HTTP will be rejected by esc_url_raw with https protocol filter
		
		WP_Mock::userFunction( 'add_settings_error' )
			->once()
			->with(
				'utilitysign_signatory_redirect_uri',
				'invalid_url',
				WP_Mock\Functions::type( 'string' ),
				'error'
			);
		
		$result = Settings::sanitize_signatory_redirect_uri( 'http://insecure.com/thank-you' );
		$this->assertSame( '', $result, 'HTTP URL should be rejected (returns empty or previous value)' );
	}

	/**
	 * Test: Invalid URL should be rejected
	 */
	public function test_invalid_url_rejected() {
		WP_Mock::userFunction( 'esc_url_raw' )->andReturn( '' );
		
		WP_Mock::userFunction( 'add_settings_error' )
			->once()
			->with(
				'utilitysign_signatory_redirect_uri',
				'invalid_url',
				WP_Mock\Functions::type( 'string' ),
				'error'
			);
		
		$result = Settings::sanitize_signatory_redirect_uri( 'not-a-valid-url' );
		$this->assertSame( '', $result, 'Invalid URL should be rejected' );
	}

	/**
	 * Test: JavaScript protocol should be rejected (XSS prevention)
	 */
	public function test_javascript_protocol_rejected() {
		WP_Mock::userFunction( 'esc_url_raw' )->andReturn( '' );
		
		WP_Mock::userFunction( 'add_settings_error' )
			->once()
			->with(
				'utilitysign_signatory_redirect_uri',
				'invalid_url',
				WP_Mock\Functions::type( 'string' ),
				'error'
			);
		
		$result = Settings::sanitize_signatory_redirect_uri( 'javascript:alert(1)' );
		$this->assertSame( '', $result, 'JavaScript protocol should be rejected' );
	}

	/**
	 * Test: Data URI should be rejected
	 */
	public function test_data_uri_rejected() {
		WP_Mock::userFunction( 'esc_url_raw' )->andReturn( '' );
		
		WP_Mock::userFunction( 'add_settings_error' )
			->once()
			->with(
				'utilitysign_signatory_redirect_uri',
				'invalid_url',
				WP_Mock\Functions::type( 'string' ),
				'error'
			);
		
		$result = Settings::sanitize_signatory_redirect_uri( 'data:text/html,<script>alert(1)</script>' );
		$this->assertSame( '', $result, 'Data URI should be rejected' );
	}

	/**
	 * Test: URL with port number should be accepted
	 */
	public function test_url_with_port_accepted() {
		$url = 'https://localhost:8443/thank-you';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'HTTPS URL with port should be accepted' );
	}

	/**
	 * Test: URL with subdomain should be accepted
	 */
	public function test_url_with_subdomain_accepted() {
		$url = 'https://pilot.eiker.devora.no/thank-you';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'HTTPS URL with subdomain should be accepted' );
	}

	/**
	 * Test: URL with path should be accepted
	 */
	public function test_url_with_path_accepted() {
		$url = 'https://example.com/path/to/thank-you';
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'HTTPS URL with path should be accepted' );
	}

	/**
	 * Test: Domain whitelist filter - allowed domain should be accepted
	 */
	public function test_domain_whitelist_allowed_domain_accepted() {
		$url = 'https://eiker.devora.no/thank-you';
		
		// Mock filter to return whitelist
		WP_Mock::onFilter( 'utilitysign_allowed_redirect_domains' )
			->with( array() )
			->reply( array( 'eiker.devora.no', 'pilot.eiker.devora.no' ) );
		
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( $url, $result, 'URL with whitelisted domain should be accepted' );
	}

	/**
	 * Test: Domain whitelist filter - disallowed domain should be rejected
	 */
	public function test_domain_whitelist_disallowed_domain_rejected() {
		$url = 'https://malicious.com/phishing';
		
		// Mock filter to return whitelist
		WP_Mock::onFilter( 'utilitysign_allowed_redirect_domains' )
			->with( array() )
			->reply( array( 'eiker.devora.no', 'pilot.eiker.devora.no' ) );
		
		WP_Mock::userFunction( 'add_settings_error' )
			->once()
			->with(
				'utilitysign_signatory_redirect_uri',
				'domain_not_allowed',
				WP_Mock\Functions::type( 'string' ),
				'error'
			);
		
		$result = Settings::sanitize_signatory_redirect_uri( $url );
		$this->assertSame( '', $result, 'URL with non-whitelisted domain should be rejected when whitelist is enabled' );
	}

	/**
	 * Test: get_signatory_redirect_uri() returns option value
	 */
	public function test_get_signatory_redirect_uri_returns_option() {
		$expected_url = 'https://example.com/thank-you';
		
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', '' )
			->andReturn( $expected_url );
		
		WP_Mock::userFunction( 'apply_filters' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', $expected_url, array() )
			->andReturn( $expected_url );
		
		$result = Settings::get_signatory_redirect_uri();
		$this->assertSame( $expected_url, $result, 'Should return configured redirect URI' );
	}

	/**
	 * Test: get_signatory_redirect_uri() applies filter
	 */
	public function test_get_signatory_redirect_uri_applies_filter() {
		$stored_url = 'https://example.com/default';
		$filtered_url = 'https://example.com/custom';
		$context = array( 'productId' => 'test-product' );
		
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', '' )
			->andReturn( $stored_url );
		
		WP_Mock::userFunction( 'apply_filters' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', $stored_url, $context )
			->andReturn( $filtered_url );
		
		$result = Settings::get_signatory_redirect_uri( $context );
		$this->assertSame( $filtered_url, $result, 'Should apply filter to modify redirect URI' );
	}

	/**
	 * Test: get_signatory_redirect_uri() validates filtered value
	 */
	public function test_get_signatory_redirect_uri_validates_filtered_value() {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', '' )
			->andReturn( 'https://valid.com' );
		
		// Filter returns invalid URL
		WP_Mock::userFunction( 'apply_filters' )
			->once()
			->with( 'utilitysign_signatory_redirect_uri', 'https://valid.com', array() )
			->andReturn( 'invalid-url' );
		
		WP_Mock::userFunction( 'error_log' )
			->once()
			->with( WP_Mock\Functions::type( 'string' ) );
		
		$result = Settings::get_signatory_redirect_uri();
		$this->assertSame( '', $result, 'Should return empty string if filtered value is invalid' );
	}

	/**
	 * Test: Real-world URL patterns
	 * 
	 * @dataProvider realWorldUrlProvider
	 */
	public function test_real_world_urls( $url, $expected_valid, $description ) {
		if ( $expected_valid ) {
			$result = Settings::sanitize_signatory_redirect_uri( $url );
			$this->assertSame( $url, $result, $description );
		} else {
			WP_Mock::userFunction( 'add_settings_error' )->atLeast()->once();
			$result = Settings::sanitize_signatory_redirect_uri( $url );
			$this->assertSame( '', $result, $description );
		}
	}

	/**
	 * Data provider for real-world URL test cases
	 */
	public function realWorldUrlProvider() {
		return array(
			// Valid URLs
			array( 'https://eiker.devora.no/thank-you', true, 'Production domain thank you page' ),
			array( 'https://pilot.eiker.devora.no/thank-you', true, 'Staging domain thank you page' ),
			array( 'https://example.com/order-confirmed', true, 'Order confirmed page' ),
			array( 'https://example.com/success?ref=utilitysign', true, 'Success page with query param' ),
			array( 'https://example.com/thanks?utm_source=bankid&utm_medium=signature', true, 'UTM tracking parameters' ),
			array( 'https://www.example.com/page', true, 'WWW subdomain' ),
			array( 'https://example.com:443/page', true, 'Explicit HTTPS port' ),
			array( 'https://example.com/path/to/page', true, 'Deep path' ),
			
			// Invalid URLs (security concerns)
			array( 'http://example.com/thank-you', false, 'HTTP (not HTTPS) should be rejected' ),
			array( 'ftp://example.com/file', false, 'FTP protocol should be rejected' ),
			array( 'javascript:alert(1)', false, 'JavaScript protocol (XSS) should be rejected' ),
			array( 'data:text/html,<script>alert(1)</script>', false, 'Data URI (XSS) should be rejected' ),
			array( 'file:///etc/passwd', false, 'File protocol should be rejected' ),
			array( '//example.com/page', false, 'Protocol-relative URL should be rejected' ),
			array( 'not-a-valid-url', false, 'Plain text (not URL) should be rejected' ),
			array( 'example.com/page', false, 'URL without scheme should be rejected' ),
		);
	}
}
