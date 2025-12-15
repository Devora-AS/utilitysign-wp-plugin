/**
 * Unit tests for MålepunktID validation
 * 
 * GS1 MålepunktID format validation:
 * - 18 digits total
 * - Starts with 70 (Norwegian country code)
 * - Next 5 digits: 70575 (industry code for Energi Norge)
 * - Example: 707057500088553215
 * 
 * @see https://gs1.no/kort-om-malepunkt-id/
 */

/**
 * Validates a Norwegian MålepunktID (GS1 format)
 * @param meterNumber - The meter number to validate
 * @returns Object with isValid boolean and error message if invalid
 */
export function validateMalepunktId(meterNumber: string): { isValid: boolean; error?: string } {
  if (!meterNumber) {
    // Field is optional, empty is valid
    return { isValid: true };
  }

  // Remove any whitespace
  const cleaned = meterNumber.replace(/\s/g, '');

  // Must be exactly 18 digits
  if (!/^\d{18}$/.test(cleaned)) {
    return {
      isValid: false,
      error: 'MålepunktID må være nøyaktig 18 siffer'
    };
  }

  // Must start with country code 70 + industry code 70575 (7070575)
  if (!cleaned.startsWith('7070575')) {
    return {
      isValid: false,
      error: 'MålepunktID må starte med 7070575 (norsk landkode + bransjekode)'
    };
  }

  return { isValid: true };
}

describe('MålepunktID Validation', () => {
  describe('validateMalepunktId', () => {
    it('should accept empty string (optional field)', () => {
      const result = validateMalepunktId('');
      expect(result.isValid).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should accept valid 18-digit MålepunktID starting with 7070575', () => {
      const validId = '707057500088553215';
      const result = validateMalepunktId(validId);
      expect(result.isValid).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should accept valid MålepunktID with whitespace (whitespace is removed)', () => {
      const validIdWithSpaces = '7070575 00088553215';
      const result = validateMalepunktId(validIdWithSpaces);
      expect(result.isValid).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should reject MålepunktID with less than 18 digits', () => {
      const shortId = '70705750008855321'; // 17 digits
      const result = validateMalepunktId(shortId);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må være nøyaktig 18 siffer');
    });

    it('should reject MålepunktID with more than 18 digits', () => {
      const longId = '7070575000885532151'; // 19 digits
      const result = validateMalepunktId(longId);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må være nøyaktig 18 siffer');
    });

    it('should reject MålepunktID with non-numeric characters', () => {
      const invalidId = '70705750008855321a'; // contains letter
      const result = validateMalepunktId(invalidId);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må være nøyaktig 18 siffer');
    });

    it('should reject MålepunktID that does not start with 7070575', () => {
      const invalidPrefix = '701234500088553215'; // wrong prefix
      const result = validateMalepunktId(invalidPrefix);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må starte med 7070575 (norsk landkode + bransjekode)');
    });

    it('should reject MålepunktID starting with 70 but not 7070575', () => {
      const wrongIndustryCode = '701234500088553215'; // starts with 70 but wrong industry code
      const result = validateMalepunktId(wrongIndustryCode);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må starte med 7070575 (norsk landkode + bransjekode)');
    });

    it('should reject MålepunktID that does not start with 70', () => {
      const wrongCountryCode = '801234500088553215'; // wrong country code
      const result = validateMalepunktId(wrongCountryCode);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('MålepunktID må starte med 7070575 (norsk landkode + bransjekode)');
    });

    it('should handle various edge cases', () => {
      // All zeros (valid format, but unlikely in practice)
      const allZeros = '707057500000000000';
      expect(validateMalepunktId(allZeros).isValid).toBe(true);

      // Maximum valid value
      const maxValue = '707057599999999999';
      expect(validateMalepunktId(maxValue).isValid).toBe(true);

      // Minimum valid value (with zeros in serial number)
      const minValue = '707057500000000000';
      expect(validateMalepunktId(minValue).isValid).toBe(true);
    });

    it('should handle null and undefined as empty (optional field)', () => {
      expect(validateMalepunktId(null as any).isValid).toBe(true);
      expect(validateMalepunktId(undefined as any).isValid).toBe(true);
    });
  });
});
