<?php
/**
 * Norwegian Fødselsnummer (Personal Number) Validator
 * 
 * Validates Norwegian fødselsnummer (11 digits) with Modulo 11 checksum algorithm.
 * 
 * Format: DDMMYYIIICC (11 digits)
 * - DD: Day of birth (01-31)
 * - MM: Month of birth (01-12)
 * - YY: Year of birth (00-99)
 * - III: Individual number (000-999)
 * - CC: Control digits (calculated via Modulo 11)
 * 
 * @package UtilitySign\Utils
 * @since 1.0.0
 */

namespace UtilitySign\Utils;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fødselsnummer validation utility class
 */
class FodselsnummerValidator {
	
	/**
	 * Validates a Norwegian fødselsnummer
	 * 
	 * @param string|null $fodselsnummer The fødselsnummer to validate (11 digits, may include spaces/dashes)
	 * @return array Validation result with 'is_valid' flag and optional 'error' message
	 */
	public static function validate( $fodselsnummer ) {
		// Handle null/empty
		if ( empty( $fodselsnummer ) ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Fødselsnummer er påkrevd', 'utilitysign' ),
			);
		}

		// Remove spaces, dashes, and other non-digit characters
		$cleaned = preg_replace( '/[\s\-\.]/', '', $fodselsnummer );

		// Check length
		if ( strlen( $cleaned ) !== 11 ) {
			return array(
				'is_valid' => false,
				'error'    => strlen( $cleaned ) < 11
					? __( 'Fødselsnummer må være 11 siffer', 'utilitysign' )
					: __( 'Fødselsnummer kan ikke være mer enn 11 siffer', 'utilitysign' ),
			);
		}

		// Check that all characters are digits
		if ( ! preg_match( '/^\d{11}$/', $cleaned ) ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Fødselsnummer kan bare inneholde siffer', 'utilitysign' ),
			);
		}

		// Extract components
		$day        = (int) substr( $cleaned, 0, 2 );
		$month      = (int) substr( $cleaned, 2, 2 );
		$year       = (int) substr( $cleaned, 4, 2 );
		$individual = (int) substr( $cleaned, 6, 3 );
		$control1   = (int) substr( $cleaned, 9, 1 );
		$control2   = (int) substr( $cleaned, 10, 1 );

		// Validate date components (basic validation)
		if ( $day < 1 || $day > 31 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsdato (dag)', 'utilitysign' ),
			);
		}

		if ( $month < 1 || $month > 12 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsdato (måned)', 'utilitysign' ),
			);
		}

		// Validate control digits using Modulo 11 algorithm
		$weights1 = array( 3, 7, 6, 1, 8, 9, 4, 5, 2 );
		$digits1  = array_map( 'intval', str_split( substr( $cleaned, 0, 9 ) ) );

		$sum1 = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum1 += $digits1[ $i ] * $weights1[ $i ];
		}

		$calculated_control1 = 11 - ( $sum1 % 11 );
		if ( $calculated_control1 === 11 ) {
			$calculated_control1 = 0;
		}
		if ( $calculated_control1 === 10 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsnummer (kontrollsiffer 1)', 'utilitysign' ),
			);
		}

		if ( $calculated_control1 !== $control1 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsnummer (kontrollsiffer 1 stemmer ikke)', 'utilitysign' ),
			);
		}

		// Calculate second control digit
		$weights2 = array( 5, 4, 3, 2, 7, 6, 5, 4, 3, 2 );
		$digits2  = array_map( 'intval', str_split( substr( $cleaned, 0, 10 ) ) );

		$sum2 = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$sum2 += $digits2[ $i ] * $weights2[ $i ];
		}

		$calculated_control2 = 11 - ( $sum2 % 11 );
		if ( $calculated_control2 === 11 ) {
			$calculated_control2 = 0;
		}
		if ( $calculated_control2 === 10 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsnummer (kontrollsiffer 2)', 'utilitysign' ),
			);
		}

		if ( $calculated_control2 !== $control2 ) {
			return array(
				'is_valid' => false,
				'error'    => __( 'Ugyldig fødselsnummer (kontrollsiffer 2 stemmer ikke)', 'utilitysign' ),
			);
		}

		// All validations passed
		return array(
			'is_valid' => true,
			'formatted' => $cleaned, // Return cleaned version without formatting
		);
	}

	/**
	 * Formats a fødselsnummer for display (DDMMYY III CC)
	 * 
	 * @param string $fodselsnummer The fødselsnummer to format
	 * @return string Formatted string or original if invalid
	 */
	public static function format( $fodselsnummer ) {
		$cleaned = preg_replace( '/[\s\-\.]/', '', $fodselsnummer );
		if ( strlen( $cleaned ) !== 11 || ! preg_match( '/^\d{11}$/', $cleaned ) ) {
			return $fodselsnummer; // Return original if invalid
		}
		return substr( $cleaned, 0, 6 ) . ' ' . substr( $cleaned, 6, 3 ) . ' ' . substr( $cleaned, 9, 2 );
	}

	/**
	 * Removes formatting from a fødselsnummer (spaces, dashes, dots)
	 * 
	 * @param string $fodselsnummer The fødselsnummer to clean
	 * @return string Cleaned 11-digit string
	 */
	public static function clean( $fodselsnummer ) {
		return preg_replace( '/[\s\-\.]/', '', $fodselsnummer );
	}
}

