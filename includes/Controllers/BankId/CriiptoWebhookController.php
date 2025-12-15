<?php

namespace UtilitySign\Controllers\BankId;

use UtilitySign\Traits\Base;
use UtilitySign\Services\CriiptoService;

/**
 * Criipto Webhook Controller
 * Handles webhook callbacks from Criipto BankID service
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class CriiptoWebhookController {
    use Base;

    /**
     * Criipto Service instance
     * 
     * @var CriiptoService
     */
    private $criipto_service;

    /**
     * Constructor
     * 
     * @since 1.0.0
     */
    public function __construct() {
        $this->criipto_service = new CriiptoService();
    }

    /**
     * Initialize the controller
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     * 
     * @since 1.0.0
     */
    public function register_routes() {
        register_rest_route('utilitysign/v1', '/criipto/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'verify_webhook_signature'],
        ]);

        register_rest_route('utilitysign/v1', '/webhooks/signing-complete', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_signing_complete'],
            'permission_callback' => [$this, 'verify_webhook_signature'],
        ]);
    }

    /**
     * Verify webhook signature
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return bool
     */
    public function verify_webhook_signature($request) {
        $settings = get_option('utilitysign_settings', []);
        $webhook_secret = $settings['criipto']['webhookSecret'] ?? '';

        if (empty($webhook_secret)) {
            return true; // Allow if no secret is configured (for testing)
        }

        $signature = $request->get_header('X-Criipto-Signature');
        if (empty($signature)) {
            return false;
        }

        $payload = $request->get_body();
        
        // Use CriiptoService to verify signature
        return $this->criipto_service->verify_webhook_signature($payload, $signature);
    }

    /**
     * Handle webhook callback
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook($request) {
        $payload = $request->get_json_params();

        if (empty($payload)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid payload',
            ], 400);
        }

        // Log the webhook event
        $this->log_webhook_event($payload);

        // Get event type
        $event_type = $payload['type'] ?? '';

        // Handle different event types
        switch ($event_type) {
            case 'authentication.succeeded':
                $this->handle_authentication_succeeded($payload);
                break;

            case 'authentication.failed':
                $this->handle_authentication_failed($payload);
                break;

            case 'authentication.cancelled':
                $this->handle_authentication_cancelled($payload);
                break;

            case 'signature.completed':
                $this->handle_signature_completed($payload);
                break;

            case 'signature.failed':
                $this->handle_signature_failed($payload);
                break;

            default:
                $this->handle_unknown_event($payload);
                break;
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Webhook processed',
        ], 200);
    }

    /**
     * Handle authentication succeeded event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_authentication_succeeded($payload) {
        $session_id = $payload['data']['session_id'] ?? '';
        $user_data = $payload['data']['user'] ?? [];

        if (empty($session_id)) {
            return;
        }

        // Find the order associated with this session
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_bankid_session_id',
                    'value' => $session_id,
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        // Update order status
        update_post_meta($order->ID, '_order_status', 'awaiting_signature');
        update_post_meta($order->ID, '_order_bankid_authenticated_at', current_time('mysql'));
        update_post_meta($order->ID, '_order_bankid_user_data', $user_data);

        // Trigger action for other plugins to hook into
        do_action('utilitysign_bankid_authentication_succeeded', $order->ID, $user_data);

        // Send email notification
        $this->send_authentication_success_email($order->ID);
    }

    /**
     * Handle authentication failed event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_authentication_failed($payload) {
        $session_id = $payload['data']['session_id'] ?? '';
        $error_message = $payload['data']['error'] ?? 'Unknown error';

        if (empty($session_id)) {
            return;
        }

        // Find the order associated with this session
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_bankid_session_id',
                    'value' => $session_id,
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        // Update order status
        update_post_meta($order->ID, '_order_status', 'failed');
        update_post_meta($order->ID, '_order_bankid_error', $error_message);
        update_post_meta($order->ID, '_order_bankid_failed_at', current_time('mysql'));

        // Trigger action
        do_action('utilitysign_bankid_authentication_failed', $order->ID, $error_message);

        // Send email notification
        $this->send_authentication_failed_email($order->ID, $error_message);
    }

    /**
     * Handle authentication cancelled event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_authentication_cancelled($payload) {
        $session_id = $payload['data']['session_id'] ?? '';

        if (empty($session_id)) {
            return;
        }

        // Find the order associated with this session
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_bankid_session_id',
                    'value' => $session_id,
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        // Update order status
        update_post_meta($order->ID, '_order_status', 'cancelled');
        update_post_meta($order->ID, '_order_bankid_cancelled_at', current_time('mysql'));

        // Trigger action
        do_action('utilitysign_bankid_authentication_cancelled', $order->ID);
    }

    /**
     * Handle signature completed event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_signature_completed($payload) {
        $session_id = $payload['data']['session_id'] ?? '';
        $signature_data = $payload['data']['signature'] ?? [];

        if (empty($session_id)) {
            return;
        }

        // Find the order associated with this session
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_bankid_session_id',
                    'value' => $session_id,
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        // Update order status
        update_post_meta($order->ID, '_order_status', 'signed');
        update_post_meta($order->ID, '_order_signed_at', current_time('mysql'));
        update_post_meta($order->ID, '_order_signature_data', $signature_data);

        // Trigger action
        do_action('utilitysign_signature_completed', $order->ID, $signature_data);

        // Send email notification
        $this->send_signature_completed_email($order->ID);
    }

    /**
     * Handle signature failed event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_signature_failed($payload) {
        $session_id = $payload['data']['session_id'] ?? '';
        $error_message = $payload['data']['error'] ?? 'Unknown error';

        if (empty($session_id)) {
            return;
        }

        // Find the order associated with this session
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_bankid_session_id',
                    'value' => $session_id,
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        // Update order status
        update_post_meta($order->ID, '_order_status', 'failed');
        update_post_meta($order->ID, '_order_signature_error', $error_message);
        update_post_meta($order->ID, '_order_signature_failed_at', current_time('mysql'));

        // Trigger action
        do_action('utilitysign_signature_failed', $order->ID, $error_message);

        // Send email notification
        $this->send_signature_failed_email($order->ID, $error_message);
    }

    /**
     * Handle unknown event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function handle_unknown_event($payload) {
        // Log for debugging
        error_log('UtilitySign: Unknown Criipto webhook event: ' . wp_json_encode($payload));
    }

    /**
     * Log webhook event
     * 
     * @since 1.0.0
     * @param array $payload
     */
    private function log_webhook_event($payload) {
        // Store in custom table or use WordPress logging
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $payload['type'] ?? 'unknown',
            'payload' => wp_json_encode($payload),
        ];

        // Store in option (for simple logging)
        $logs = get_option('utilitysign_webhook_logs', []);
        $logs[] = $log_entry;

        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('utilitysign_webhook_logs', $logs);
    }

    /**
     * Send authentication success email
     * 
     * @since 1.0.0
     * @param int $order_id
     */
    private function send_authentication_success_email($order_id) {
        $customer_email = get_post_meta($order_id, '_order_customer_email', true);
        $customer_name = get_post_meta($order_id, '_order_customer_name', true);

        if (empty($customer_email)) {
            return;
        }

        $subject = __('BankID Authentication Successful', 'utilitysign');
        $message = sprintf(
            __('Hello %s,\n\nYour BankID authentication was successful. You can now proceed with signing the document.\n\nBest regards,\nUtilitySign Team', 'utilitysign'),
            $customer_name
        );

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Send authentication failed email
     * 
     * @since 1.0.0
     * @param int $order_id
     * @param string $error_message
     */
    private function send_authentication_failed_email($order_id, $error_message) {
        $customer_email = get_post_meta($order_id, '_order_customer_email', true);
        $customer_name = get_post_meta($order_id, '_order_customer_name', true);

        if (empty($customer_email)) {
            return;
        }

        $subject = __('BankID Authentication Failed', 'utilitysign');
        $message = sprintf(
            __('Hello %s,\n\nYour BankID authentication failed: %s\n\nPlease try again or contact support if the problem persists.\n\nBest regards,\nUtilitySign Team', 'utilitysign'),
            $customer_name,
            $error_message
        );

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Send signature completed email
     * 
     * @since 1.0.0
     * @param int $order_id
     */
    private function send_signature_completed_email($order_id) {
        $customer_email = get_post_meta($order_id, '_order_customer_email', true);
        $customer_name = get_post_meta($order_id, '_order_customer_name', true);

        if (empty($customer_email)) {
            return;
        }

        $subject = __('Document Signed Successfully', 'utilitysign');
        $message = sprintf(
            __('Hello %s,\n\nYour document has been signed successfully. You will receive a copy of the signed document shortly.\n\nBest regards,\nUtilitySign Team', 'utilitysign'),
            $customer_name
        );

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Send signature failed email
     * 
     * @since 1.0.0
     * @param int $order_id
     * @param string $error_message
     */
    private function send_signature_failed_email($order_id, $error_message) {
        $customer_email = get_post_meta($order_id, '_order_customer_email', true);
        $customer_name = get_post_meta($order_id, '_order_customer_name', true);

        if (empty($customer_email)) {
            return;
        }

        $subject = __('Document Signing Failed', 'utilitysign');
        $message = sprintf(
            __('Hello %s,\n\nDocument signing failed: %s\n\nPlease try again or contact support if the problem persists.\n\nBest regards,\nUtilitySign Team', 'utilitysign'),
            $customer_name,
            $error_message
        );

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Handle signing complete webhook
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_signing_complete($request) {
        $payload = $request->get_json_params();

        if (empty($payload)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid payload',
            ], 400);
        }

        // Use CriiptoService to handle signing completion
        $result = $this->criipto_service->handle_signing_completion($payload);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Signing completion processed',
            'data' => $result,
        ], 200);
    }
}

