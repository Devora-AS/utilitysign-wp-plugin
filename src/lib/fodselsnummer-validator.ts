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
 * @package UtilitySign
 * @since 1.0.0
 */

export interface FodselsnummerValidationResult {
  isValid: boolean;
  error?: string;
  formatted?: string;
}

/**
 * Validates a Norwegian fødselsnummer
 * 
 * @param fodselsnummer - The fødselsnummer to validate (11 digits, may include spaces/dashes)
 * @returns Validation result with isValid flag and optional error message
 */
export function validateFodselsnummer(fodselsnummer: string | null | undefined): FodselsnummerValidationResult {
  // Handle null/undefined
  if (!fodselsnummer) {
    return {
      isValid: false,
      error: 'Fødselsnummer er påkrevd',
    };
  }

  // Remove spaces, dashes, and other non-digit characters
  const cleaned = fodselsnummer.replace(/[\s\-\.]/g, '');

  // Check length
  if (cleaned.length !== 11) {
    return {
      isValid: false,
      error: cleaned.length < 11 
        ? 'Fødselsnummer må være 11 siffer' 
        : 'Fødselsnummer kan ikke være mer enn 11 siffer',
    };
  }

  // Check that all characters are digits
  if (!/^\d{11}$/.test(cleaned)) {
    return {
      isValid: false,
      error: 'Fødselsnummer kan bare inneholde siffer',
    };
  }

  // Extract components
  const day = parseInt(cleaned.substring(0, 2), 10);
  const month = parseInt(cleaned.substring(2, 4), 10);
  const year = parseInt(cleaned.substring(4, 6), 10);
  const individual = parseInt(cleaned.substring(6, 9), 10);
  const control1 = parseInt(cleaned.substring(9, 10), 10);
  const control2 = parseInt(cleaned.substring(10, 11), 10);

  // Validate date components (basic validation)
  if (day < 1 || day > 31) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsdato (dag)',
    };
  }

  if (month < 1 || month > 12) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsdato (måned)',
    };
  }

  // Validate control digits using Modulo 11 algorithm
  const weights1 = [3, 7, 6, 1, 8, 9, 4, 5, 2];
  const digits1 = cleaned.substring(0, 9).split('').map(Number);
  
  let sum1 = 0;
  for (let i = 0; i < 9; i++) {
    sum1 += digits1[i] * weights1[i];
  }

  let calculatedControl1 = 11 - (sum1 % 11);
  if (calculatedControl1 === 11) {
    calculatedControl1 = 0;
  }
  if (calculatedControl1 === 10) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsnummer (kontrollsiffer 1)',
    };
  }

  if (calculatedControl1 !== control1) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsnummer (kontrollsiffer 1 stemmer ikke)',
    };
  }

  // Calculate second control digit
  const weights2 = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
  const digits2 = cleaned.substring(0, 10).split('').map(Number);
  
  let sum2 = 0;
  for (let i = 0; i < 10; i++) {
    sum2 += digits2[i] * weights2[i];
  }

  let calculatedControl2 = 11 - (sum2 % 11);
  if (calculatedControl2 === 11) {
    calculatedControl2 = 0;
  }
  if (calculatedControl2 === 10) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsnummer (kontrollsiffer 2)',
    };
  }

  if (calculatedControl2 !== control2) {
    return {
      isValid: false,
      error: 'Ugyldig fødselsnummer (kontrollsiffer 2 stemmer ikke)',
    };
  }

  // All validations passed
  return {
    isValid: true,
    formatted: cleaned, // Return cleaned version without formatting
  };
}

/**
 * Formats a fødselsnummer for display (DDMMYY III CC)
 * 
 * @param fodselsnummer - The fødselsnummer to format
 * @returns Formatted string or original if invalid
 */
export function formatFodselsnummer(fodselsnummer: string): string {
  const cleaned = fodselsnummer.replace(/[\s\-\.]/g, '');
  if (cleaned.length !== 11 || !/^\d{11}$/.test(cleaned)) {
    return fodselsnummer; // Return original if invalid
  }
  return `${cleaned.substring(0, 6)} ${cleaned.substring(6, 9)} ${cleaned.substring(9, 11)}`;
}

/**
 * Removes formatting from a fødselsnummer (spaces, dashes, dots)
 * 
 * @param fodselsnummer - The fødselsnummer to clean
 * @returns Cleaned 11-digit string
 */
export function cleanFodselsnummer(fodselsnummer: string): string {
  return fodselsnummer.replace(/[\s\-\.]/g, '');
}

