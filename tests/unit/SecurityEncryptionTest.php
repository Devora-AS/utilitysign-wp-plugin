<?php
/**
 * Security Encryption Tests
 * 
 * Tests for encryption, decryption, and secret masking functionality.
 * 
 * @package UtilitySign
 * @subpackage Tests
 */

namespace UtilitySign\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UtilitySign\Utils\Security;
use Brain\Monkey;
use Brain\Monkey\Functions;

class SecurityEncryptionTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress salt functions
        Functions\when('wp_salt')->justReturn('test-salt-auth');
    }
    
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
    
    /**
     * Test encryption and decryption roundtrip maintains plaintext
     */
    public function testEncryptDecryptRoundtrip() {
        $original = 'super-secret-password';
        
        $encrypted = Security::encrypt_secret($original);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($original, $encrypted);
        
        $decrypted = Security::decrypt_secret($encrypted);
        $this->assertEquals($original, $decrypted);
    }
    
    /**
     * Test encrypting empty string returns empty
     */
    public function testEncryptEmptyString() {
        $result = Security::encrypt_secret('');
        $this->assertEquals('', $result);
    }
    
    /**
     * Test decrypting invalid data returns empty
     */
    public function testDecryptInvalidData() {
        $result = Security::decrypt_secret('not-valid-base64!!!');
        $this->assertEquals('', $result);
    }
    
    /**
     * Test decrypting corrupted base64 returns empty
     */
    public function testDecryptCorruptedBase64() {
        $result = Security::decrypt_secret('YWJjZGVm'); // Valid base64 but too short (< 17 bytes)
        $this->assertEquals('', $result);
    }
    
    /**
     * Test mask_secret shows last 4 characters with bullets
     */
    public function testMaskSecretStandard() {
        $secret = 'my-secret-key-1234';
        $masked = Security::mask_secret($secret);
        
        $this->assertEquals('••••••••1234', $masked);
        $this->assertStringEndsWith('1234', $masked);
    }
    
    /**
     * Test mask_secret with short string (< 4 chars)
     */
    public function testMaskSecretShortString() {
        $secret = 'abc';
        $masked = Security::mask_secret($secret);
        
        // Should still show last 4 chars (or all if shorter)
        $this->assertStringEndsWith('abc', $masked);
    }
    
    /**
     * Test mask_secret with empty string
     */
    public function testMaskSecretEmpty() {
        $result = Security::mask_secret('');
        $this->assertEquals('', $result);
    }
    
    /**
     * Test is_encrypted_secret with valid encrypted data
     */
    public function testIsEncryptedSecretValid() {
        $encrypted = Security::encrypt_secret('test-value');
        $this->assertTrue(Security::is_encrypted_secret($encrypted));
    }
    
    /**
     * Test is_encrypted_secret with invalid data
     */
    public function testIsEncryptedSecretInvalid() {
        $this->assertFalse(Security::is_encrypted_secret('plain-text'));
        $this->assertFalse(Security::is_encrypted_secret('not-base64!!!'));
    }
    
    /**
     * Test encryption uses random IV (different ciphertexts)
     */
    public function testEncryptionUsesRandomIV() {
        $plaintext = 'same-plaintext';
        
        $encrypted1 = Security::encrypt_secret($plaintext);
        $encrypted2 = Security::encrypt_secret($plaintext);
        
        // Same plaintext should produce different ciphertexts due to random IV
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both should decrypt to same plaintext
        $this->assertEquals($plaintext, Security::decrypt_secret($encrypted1));
        $this->assertEquals($plaintext, Security::decrypt_secret($encrypted2));
    }
    
    /**
     * Test decryption fails with wrong key (simulated by changing salt)
     */
    public function testDecryptionFailsWithWrongKey() {
        $original = 'secret-value';
        $encrypted = Security::encrypt_secret($original);
        
        // Change the salt mock to simulate wrong key
        Functions\when('wp_salt')->justReturn('different-salt');
        
        $decrypted = Security::decrypt_secret($encrypted);
        
        // Should return empty string or garbage (not original)
        $this->assertNotEquals($original, $decrypted);
    }
    
    /**
     * Test mask_secret with special characters
     */
    public function testMaskSecretSpecialCharacters() {
        $secret = 'key-with-special-chars-!@#$';
        $masked = Security::mask_secret($secret);
        
        $this->assertStringEndsWith('!@#$', $masked);
    }
    
    /**
     * Test encrypting long string (> 1KB)
     */
    public function testEncryptLongString() {
        $longString = str_repeat('A', 2048); // 2KB string
        
        $encrypted = Security::encrypt_secret($longString);
        $this->assertNotEmpty($encrypted);
        
        $decrypted = Security::decrypt_secret($encrypted);
        $this->assertEquals($longString, $decrypted);
    }
    
    /**
     * Test decrypting data with insufficient length
     */
    public function testDecryptShortData() {
        // Create valid base64 but with length < 17 bytes
        $shortData = base64_encode('short');
        
        $result = Security::decrypt_secret($shortData);
        $this->assertEquals('', $result);
    }
    
    /**
     * Test is_encrypted_secret with empty string
     */
    public function testIsEncryptedSecretEmpty() {
        $this->assertFalse(Security::is_encrypted_secret(''));
    }
}

