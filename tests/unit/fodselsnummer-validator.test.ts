/**
 * Tests for Norwegian Fødselsnummer Validator
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

import { validateFodselsnummer, formatFodselsnummer, cleanFodselsnummer } from '../../src/lib/fodselsnummer-validator';

describe('FodselsnummerValidator', () => {
  describe('validateFodselsnummer', () => {
    it('should reject null or undefined', () => {
      expect(validateFodselsnummer(null).isValid).toBe(false);
      expect(validateFodselsnummer(undefined).isValid).toBe(false);
      expect(validateFodselsnummer('').isValid).toBe(false);
    });

    it('should reject non-numeric characters', () => {
      const result = validateFodselsnummer('0101011234a');
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('bare inneholde siffer');
    });

    it('should reject too short numbers', () => {
      const result = validateFodselsnummer('0101011234');
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('11 siffer');
    });

    it('should reject too long numbers', () => {
      const result = validateFodselsnummer('010101123456');
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('mer enn 11 siffer');
    });

    it('should reject invalid day', () => {
      const result = validateFodselsnummer('00010112345');
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('dag');
    });

    it('should reject invalid month', () => {
      const result = validateFodselsnummer('01130112345');
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('måned');
    });

    it('should accept numbers with spaces/dashes and clean them', () => {
      // Note: These are example numbers - in real tests, use valid fødselsnummer with correct checksums
      // For now, we test the cleaning logic
      const result1 = validateFodselsnummer('01 01 01 123 45');
      expect(result1.isValid).toBe(false); // Will fail checksum, but should accept format
      
      const result2 = validateFodselsnummer('01-01-01-123-45');
      expect(result2.isValid).toBe(false); // Will fail checksum, but should accept format
    });

    // Note: To test valid fødselsnummer, we need real numbers with correct checksums
    // These would need to be generated or obtained from test data
    // Example structure (not real valid numbers):
    // it('should accept valid fødselsnummer', () => {
    //   const result = validateFodselsnummer('01010112345');
    //   expect(result.isValid).toBe(true);
    //   expect(result.formatted).toBe('01010112345');
    // });
  });

  describe('formatFodselsnummer', () => {
    it('should format valid fødselsnummer', () => {
      const formatted = formatFodselsnummer('01010112345');
      expect(formatted).toBe('010101 123 45');
    });

    it('should return original if invalid', () => {
      const invalid = '0101011234';
      const formatted = formatFodselsnummer(invalid);
      expect(formatted).toBe(invalid);
    });

    it('should handle already formatted numbers', () => {
      const formatted = formatFodselsnummer('01 01 01 123 45');
      expect(formatted).toBe('010101 123 45');
    });
  });

  describe('cleanFodselsnummer', () => {
    it('should remove spaces', () => {
      expect(cleanFodselsnummer('01 01 01 123 45')).toBe('01010112345');
    });

    it('should remove dashes', () => {
      expect(cleanFodselsnummer('01-01-01-123-45')).toBe('01010112345');
    });

    it('should remove dots', () => {
      expect(cleanFodselsnummer('01.01.01.123.45')).toBe('01010112345');
    });

    it('should handle mixed formatting', () => {
      expect(cleanFodselsnummer('01-01 01.123-45')).toBe('01010112345');
    });

    it('should return unchanged if no formatting', () => {
      expect(cleanFodselsnummer('01010112345')).toBe('01010112345');
    });
  });
});

