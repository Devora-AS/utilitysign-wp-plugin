<?php
/**
 * Criipto Service
 * 
 * Handles Criipto BankID integration for document signing.
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Services;

defined('ABSPATH') || exit;

class CriiptoService {
    /**
     * Criipto API base URL
     * 
     * @var string
     */
    private $api_url;

    /**
     * Criipto Client ID
     * 
     * @var string
     */
    private $client_id;

    /**
     * Criipto Client Secret
     * 
     * @var string
     */
    private $client_secret;

    /**
     * Criipto Domain
     * 
     * @var string
     */
    private $domain;

    /**
     * Environment (staging/production)
     * 
     * @var string
     */
    private $environment;

    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $settings = get_option('utilitysign_settings', []);
        $criipto_settings = $settings['criipto'] ?? [];

        $this->environment = $criipto_settings['environment'] ?? 'staging';
        $this->client_id = $criipto_settings['clientId'] ?? '';
        $this->client_secret = $criipto_settings['clientSecret'] ?? '';
        $this->domain = $criipto_settings['domain'] ?? '';
        
        // Set API URL based on environment
        $this->api_url = $this->environment === 'production' 
            ? 'https://signatures.criipto.io/api/v1' 
            : 'https://signatures-staging.criipto.io/api/v1';
    }

    /**
     * Initiate signing for a document
     * 
     * @since 1.0.0
     * @param array $params Signing parameters
     * @return array|WP_Error Signing result or error
     */
    public function initiate_signing($params) {
        // Validate required parameters
        $required_params = ['documentId', 'orderId', 'signerEmail', 'signerName', 'webhookUrl', 'redirectUrl'];
        foreach ($required_params as $param) {
            if (empty($params[$param])) {
                return new \WP_Error(
                    'missing_parameter',
                    sprintf(__('Required parameter missing: %s', 'utilitysign'), $param)
                );
            }
        }

        // Build signing request
        $request_data = [
            'documentId' => $params['documentId'],
            'metadata' => [
                'orderId' => $params['orderId'],
                'wpOrderId' => $params['wpOrderId'] ?? null,
            ],
            'signer' => [
                'email' => $params['signerEmail'],
                'name' => $params['signerName'],
            ],
            'webhookUrl' => $params['webhookUrl'],
            'redirectUrl' => $params['redirectUrl'],
            'acrValues' => 'urn:grn:authn:no:bankid', // Norwegian BankID
            'language' => 'nb', // Norwegian Bokmål
        ];

        // Send request to Criipto API
        $response = wp_remote_post($this->api_url . '/signatures', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_access_token(),
            ],
            'body' => wp_json_encode($request_data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 && $status_code !== 201) {
            return new \WP_Error(
                'criipto_api_error',
                sprintf(__('Criipto API error: %s', 'utilitysign'), $data['message'] ?? 'Unknown error'),
                ['status_code' => $status_code, 'response' => $data]
            );
        }

        return [
            'signing_id' => $data['id'] ?? null,
            'signing_url' => $data['url'] ?? null,
            'status' => $data['status'] ?? 'initiated',
            'expires_at' => $data['expiresAt'] ?? null,
        ];
    }

    /**
     * Get signing status
     * 
     * @since 1.0.0
     * @param string $signing_id Signing request ID
     * @return array|WP_Error Signing status or error
     */
    public function get_signing_status($signing_id) {
        if (empty($signing_id)) {
            return new \WP_Error(
                'missing_signing_id',
                __('Signing ID is required', 'utilitysign')
            );
        }

        $response = wp_remote_get($this->api_url . '/signatures/' . $signing_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_access_token(),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            return new \WP_Error(
                'criipto_api_error',
                sprintf(__('Failed to get signing status: %s', 'utilitysign'), $data['message'] ?? 'Unknown error'),
                ['status_code' => $status_code]
            );
        }

        return [
            'signing_id' => $data['id'] ?? null,
            'status' => $data['status'] ?? 'unknown',
            'signed_at' => $data['signedAt'] ?? null,
            'signer_name' => $data['signer']['name'] ?? null,
            'signer_email' => $data['signer']['email'] ?? null,
            'document_url' => $data['documentUrl'] ?? null,
        ];
    }

    /**
     * Get OAuth access token for Criipto API
     * 
     * @since 1.0.0
     * @return string|WP_Error Access token or error
     */
    private function get_access_token() {
        // Check if we have a cached token
        $cached_token = get_transient('utilitysign_criipto_access_token');
        if ($cached_token) {
            return $cached_token;
        }

        // Request new token
        $token_url = 'https://' . $this->domain . '/oauth2/token';
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'signatures',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to get Criipto access token: ' . $response->get_error_message());
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            error_log('No access token in Criipto response: ' . $body);
            return '';
        }

        // Cache the token (expires in 1 hour, cache for 55 minutes)
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) - 300 : 3300;
        set_transient('utilitysign_criipto_access_token', $data['access_token'], $expires_in);

        return $data['access_token'];
    }

    /**
     * Verify webhook signature
     * 
     * @since 1.0.0
     * @param string $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool True if signature is valid
     */
    public function verify_webhook_signature($payload, $signature) {
        $settings = get_option('utilitysign_settings', []);
        $webhook_secret = $settings['criipto']['webhookSecret'] ?? '';

        if (empty($webhook_secret)) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Handle signing completion webhook
     * 
     * @since 1.0.0
     * @param array $webhook_data Webhook data
     * @return array|WP_Error Result or error
     */
    public function handle_signing_completion($webhook_data) {
        $signing_id = $webhook_data['id'] ?? null;
        $status = $webhook_data['status'] ?? null;
        $order_id = $webhook_data['metadata']['orderId'] ?? null;
        $wp_order_id = $webhook_data['metadata']['wpOrderId'] ?? null;

        if (!$signing_id || !$status || !$wp_order_id) {
            return new \WP_Error(
                'invalid_webhook_data',
                __('Invalid webhook data', 'utilitysign')
            );
        }

        // Find the order by WordPress ID
        $order = get_post($wp_order_id);
        if (!$order || $order->post_type !== 'utilitysign_order') {
            return new \WP_Error(
                'order_not_found',
                __('Order not found', 'utilitysign')
            );
        }

        // Update order based on signing status
        if ($status === 'signed') {
            update_post_meta($wp_order_id, '_order_status', 'signed');
            update_post_meta($wp_order_id, '_order_signed_at', current_time('mysql'));
            update_post_meta($wp_order_id, '_order_signing_completed', true);
            
            // Store signed document URL if available
            if (!empty($webhook_data['documentUrl'])) {
                update_post_meta($wp_order_id, '_order_signed_document_url', $webhook_data['documentUrl']);
            }

            // Trigger action for post-signing processing
            do_action('utilitysign_order_signed', $wp_order_id, $webhook_data);
        } elseif ($status === 'rejected' || $status === 'expired') {
            update_post_meta($wp_order_id, '_order_status', 'signing_failed');
            update_post_meta($wp_order_id, '_order_signing_failure_reason', $status);
            
            // Trigger action for signing failure
            do_action('utilitysign_order_signing_failed', $wp_order_id, $status, $webhook_data);
        }

        return [
            'success' => true,
            'order_id' => $wp_order_id,
            'status' => $status,
        ];
    }
}

