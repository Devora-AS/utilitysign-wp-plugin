<?php
/**
 * Unit tests for Norwegian Fødselsnummer Validator
 * 
 * @package UtilitySign\Tests
 * @since 1.0.0
 */

namespace UtilitySign\Tests;

use UtilitySign\Utils\FodselsnummerValidator;

/**
 * Test class for FodselsnummerValidator
 */
class FodselsnummerValidatorTest extends \PHPUnit\Framework\TestCase {
	
	/**
	 * Test null/empty validation
	 */
	public function test_validate_rejects_null_or_empty() {
		$result1 = FodselsnummerValidator::validate( null );
		$this->assertFalse( $result1['is_valid'] );
		$this->assertNotEmpty( $result1['error'] );
		
		$result2 = FodselsnummerValidator::validate( '' );
		$this->assertFalse( $result2['is_valid'] );
		$this->assertNotEmpty( $result2['error'] );
	}
	
	/**
	 * Test non-numeric characters
	 */
	public function test_validate_rejects_non_numeric() {
		$result = FodselsnummerValidator::validate( '0101011234a' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( 'bare inneholde siffer', $result['error'] );
	}
	
	/**
	 * Test too short numbers
	 */
	public function test_validate_rejects_too_short() {
		$result = FodselsnummerValidator::validate( '0101011234' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( '11 siffer', $result['error'] );
	}
	
	/**
	 * Test too long numbers
	 */
	public function test_validate_rejects_too_long() {
		$result = FodselsnummerValidator::validate( '010101123456' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( 'mer enn 11 siffer', $result['error'] );
	}
	
	/**
	 * Test invalid day
	 */
	public function test_validate_rejects_invalid_day() {
		$result = FodselsnummerValidator::validate( '00010112345' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( 'dag', $result['error'] );
	}
	
	/**
	 * Test invalid month
	 */
	public function test_validate_rejects_invalid_month() {
		$result = FodselsnummerValidator::validate( '01130112345' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertStringContainsString( 'måned', $result['error'] );
	}
	
	/**
	 * Test cleaning function
	 */
	public function test_clean_removes_formatting() {
		$this->assertEquals( '01010112345', FodselsnummerValidator::clean( '01 01 01 123 45' ) );
		$this->assertEquals( '01010112345', FodselsnummerValidator::clean( '01-01-01-123-45' ) );
		$this->assertEquals( '01010112345', FodselsnummerValidator::clean( '01.01.01.123.45' ) );
		$this->assertEquals( '01010112345', FodselsnummerValidator::clean( '01-01 01.123-45' ) );
		$this->assertEquals( '01010112345', FodselsnummerValidator::clean( '01010112345' ) );
	}
	
	/**
	 * Test formatting function
	 */
	public function test_format_formats_correctly() {
		$formatted = FodselsnummerValidator::format( '01010112345' );
		$this->assertEquals( '010101 123 45', $formatted );
	}
	
	/**
	 * Test formatting returns original if invalid
	 */
	public function test_format_returns_original_if_invalid() {
		$invalid = '0101011234';
		$formatted = FodselsnummerValidator::format( $invalid );
		$this->assertEquals( $invalid, $formatted );
	}
	
	// Note: To test valid fødselsnummer, we need real numbers with correct checksums
	// These would need to be generated or obtained from test data
	// Example structure (not real valid numbers):
	// public function test_validate_accepts_valid_fodselsnummer() {
	//     $result = FodselsnummerValidator::validate( '01010112345' );
	//     $this->assertTrue( $result['is_valid'] );
	//     $this->assertEquals( '01010112345', $result['formatted'] );
	// }
}

