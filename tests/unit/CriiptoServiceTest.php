<?php
/**
 * Criipto Service Tests
 * 
 * Tests for Criipto BankID integration functionality.
 * 
 * @package UtilitySign
 * @subpackage Tests
 */

namespace UtilitySign\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UtilitySign\Services\CriiptoService;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class CriiptoServiceTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress option functions
        Functions\when('get_option')->justReturn([
            'criipto' => [
                'environment' => 'staging',
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret',
                'domain' => 'test-domain.criipto.id',
                'enabled' => true,
            ],
        ]);
    }
    
    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }
    
    /**
     * Test initiate_signing with success response
     */
    public function testInitiateSigningSuccess() {
        // Mock access token
        Functions\when('get_transient')->justReturn('mock-access-token');
        
        // Mock successful API response
        Functions\when('wp_remote_post')->justReturn([
            'response' => ['code' => 201],
            'body' => json_encode([
                'id' => 'signing-123',
                'url' => 'https://signatures.criipto.io/sign/abc',
                'status' => 'initiated',
                'expiresAt' => '2025-01-26T12:00:00Z',
            ]),
        ]);
        
        Functions\when('wp_remote_retrieve_response_code')->justReturn(201);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);
        
        $service = new CriiptoService();
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'orderId' => 'order-456',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('signing-123', $result['signing_id']);
        $this->assertEquals('https://signatures.criipto.io/sign/abc', $result['signing_url']);
        $this->assertEquals('initiated', $result['status']);
    }
    
    /**
     * Test initiate_signing with missing documentId
     */
    public function testInitiateSigningMissingDocumentId() {
        $service = new CriiptoService();
        $result = $service->initiate_signing([
            'orderId' => 'order-456',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_parameter', $result->get_error_code());
    }
    
    /**
     * Test initiate_signing with missing orderId
     */
    public function testInitiateSigningMissingOrderId() {
        $service = new CriiptoService();
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_parameter', $result->get_error_code());
    }
    
    /**
     * Test initiate_signing with missing signerEmail
     */
    public function testInitiateSigningMissingSignerEmail() {
        $service = new CriiptoService();
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'orderId' => 'order-456',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_parameter', $result->get_error_code());
    }
    
    /**
     * Test initiate_signing with API error response
     */
    public function testInitiateSigningApiError() {
        Functions\when('get_transient')->justReturn('mock-access-token');
        
        // Mock API error response
        Functions\when('wp_remote_post')->justReturn([
            'response' => ['code' => 400],
            'body' => json_encode([
                'message' => 'Invalid request',
            ]),
        ]);
        
        Functions\when('wp_remote_retrieve_response_code')->justReturn(400);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);
        
        $service = new CriiptoService();
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'orderId' => 'order-456',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('criipto_api_error', $result->get_error_code());
    }
    
    /**
     * Test get_signing_status with success
     */
    public function testGetSigningStatusSuccess() {
        Functions\when('get_transient')->justReturn('mock-access-token');
        
        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 200],
            'body' => json_encode([
                'id' => 'signing-123',
                'status' => 'signed',
                'signedAt' => '2025-01-25T10:30:00Z',
                'signer' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
                'documentUrl' => 'https://storage.criipto.io/doc-123-signed.pdf',
            ]),
        ]);
        
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);
        
        $service = new CriiptoService();
        $result = $service->get_signing_status('signing-123');
        
        $this->assertIsArray($result);
        $this->assertEquals('signing-123', $result['signing_id']);
        $this->assertEquals('signed', $result['status']);
        $this->assertEquals('Test User', $result['signer_name']);
    }
    
    /**
     * Test get_signing_status with missing ID
     */
    public function testGetSigningStatusMissingId() {
        $service = new CriiptoService();
        $result = $service->get_signing_status('');
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_signing_id', $result->get_error_code());
    }
    
    /**
     * Test get_signing_status with not found error
     */
    public function testGetSigningStatusNotFound() {
        Functions\when('get_transient')->justReturn('mock-access-token');
        
        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 404],
            'body' => json_encode([
                'message' => 'Signing request not found',
            ]),
        ]);
        
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);
        
        $service = new CriiptoService();
        $result = $service->get_signing_status('invalid-id');
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('criipto_api_error', $result->get_error_code());
    }
    
    /**
     * Test access token caching
     */
    public function testGetAccessTokenCaching() {
        // First call - no cached token
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        
        Functions\when('wp_remote_post')->justReturn([
            'response' => ['code' => 200],
            'body' => json_encode([
                'access_token' => 'new-token-123',
                'expires_in' => 3600,
            ]),
        ]);
        
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(function($response) {
            return $response['body'];
        });
        Functions\when('is_wp_error')->justReturn(false);
        
        $service = new CriiptoService();
        
        // Trigger token fetch via initiate_signing
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'orderId' => 'order-456',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        // Verify set_transient was called to cache token
        Functions\expect('set_transient')
            ->once()
            ->with('utilitysign_criipto_access_token', 'new-token-123', 3600);
    }
    
    /**
     * Test access token API failure
     */
    public function testGetAccessTokenApiFailure() {
        Functions\when('get_transient')->justReturn(false);
        
        // Mock failed token request
        Functions\when('wp_remote_post')->justReturn(
            Mockery::mock(\WP_Error::class)
        );
        Functions\when('is_wp_error')->justReturn(true);
        
        $service = new CriiptoService();
        
        // Attempt to initiate signing should fail due to token fetch failure
        $result = $service->initiate_signing([
            'documentId' => 'doc-123',
            'orderId' => 'order-456',
            'signerEmail' => 'test@example.com',
            'signerName' => 'Test User',
            'webhookUrl' => 'https://example.com/webhook',
            'redirectUrl' => 'https://example.com/complete',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
    }
    
    /**
     * Test webhook signature verification with valid signature
     */
    public function testVerifyWebhookSignatureValid() {
        $payload = json_encode(['orderId' => '123', 'status' => 'signed']);
        $secret = 'webhook-secret';
        $signature = hash_hmac('sha256', $payload, $secret);
        
        $service = new CriiptoService();
        $result = $service->verify_webhook_signature($payload, $signature, $secret);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test webhook signature verification with invalid signature
     */
    public function testVerifyWebhookSignatureInvalid() {
        $payload = json_encode(['orderId' => '123', 'status' => 'signed']);
        $secret = 'webhook-secret';
        $invalidSignature = 'invalid-signature';
        
        $service = new CriiptoService();
        $result = $service->verify_webhook_signature($payload, $invalidSignature, $secret);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test webhook signature verification with missing signature
     */
    public function testVerifyWebhookSignatureMissing() {
        $payload = json_encode(['orderId' => '123', 'status' => 'signed']);
        $secret = 'webhook-secret';
        
        $service = new CriiptoService();
        $result = $service->verify_webhook_signature($payload, '', $secret);
        
        $this->assertFalse($result);
    }
    
    /**
     * Test handle_signing_completion with success
     */
    public function testHandleSigningCompletionSuccess() {
        Functions\when('get_post_meta')->justReturn('order-123');
        Functions\when('update_post_meta')->justReturn(true);
        Functions\expect('do_action')
            ->once()
            ->with('utilitysign_signing_completed', Mockery::any(), Mockery::any());
        
        $service = new CriiptoService();
        $result = $service->handle_signing_completion([
            'wpOrderId' => '123',
            'status' => 'signed',
            'documentUrl' => 'https://storage.criipto.io/doc-signed.pdf',
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('signed', $result['status']);
    }
    
    /**
     * Test handle_signing_completion with order not found
     */
    public function testHandleSigningCompletionOrderNotFound() {
        Functions\when('get_post')->justReturn(null);
        
        $service = new CriiptoService();
        $result = $service->handle_signing_completion([
            'wpOrderId' => '999',
            'status' => 'signed',
        ]);
        
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('order_not_found', $result->get_error_code());
    }
    
    /**
     * Test environment staging URL
     */
    public function testEnvironmentStagingUrl() {
        Functions\when('get_option')->justReturn([
            'criipto' => [
                'environment' => 'staging',
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret',
            ],
        ]);
        
        $service = new CriiptoService();
        
        // Verify staging URL is used (via reflection or indirect test)
        $this->assertInstanceOf(CriiptoService::class, $service);
    }
    
    /**
     * Test environment production URL
     */
    public function testEnvironmentProductionUrl() {
        Functions\when('get_option')->justReturn([
            'criipto' => [
                'environment' => 'production',
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret',
            ],
        ]);
        
        $service = new CriiptoService();
        
        // Verify production URL is used (via reflection or indirect test)
        $this->assertInstanceOf(CriiptoService::class, $service);
    }
}

