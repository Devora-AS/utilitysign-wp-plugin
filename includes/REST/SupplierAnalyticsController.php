<?php
namespace UtilitySign\REST;

use UtilitySign\Traits\Base;
use UtilitySign\Services\SupplierAnalytics;

/**
 * Supplier Analytics REST API Controller
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SupplierAnalyticsController {
    use Base;

    /**
     * Initialize supplier analytics controller
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
        register_rest_route('utilitysign/v1', '/analytics/supplier/(?P<supplier_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supplier_analytics'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'supplier_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date_from' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/analytics/supplier/(?P<supplier_id>\d+)/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supplier_product_analytics'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'supplier_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date_from' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/analytics/supplier/(?P<supplier_id>\d+)/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supplier_order_analytics'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'supplier_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date_from' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/analytics/supplier/(?P<supplier_id>\d+)/performance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supplier_performance_analytics'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'supplier_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date_from' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/analytics/supplier/(?P<supplier_id>\d+)/trends', [
            'methods' => 'GET',
            'callback' => [$this, 'get_supplier_trends'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'supplier_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'date_from' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date_to' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get supplier analytics
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_supplier_analytics($request) {
        try {
            $supplier_id = $request->get_param('supplier_id');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate supplier exists
            $supplier = get_post($supplier_id);
            if (!$supplier || $supplier->post_type !== 'utilitysign_supplier') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Supplier not found', 'utilitysign')
                ], 404);
            }

            // Get supplier analytics service
            $analytics = SupplierAnalytics::get_instance();

            // Get analytics data
            $data = $analytics->get_supplier_analytics($supplier_id, $date_from, $date_to);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier product analytics
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_supplier_product_analytics($request) {
        try {
            $supplier_id = $request->get_param('supplier_id');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate supplier exists
            $supplier = get_post($supplier_id);
            if (!$supplier || $supplier->post_type !== 'utilitysign_supplier') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Supplier not found', 'utilitysign')
                ], 404);
            }

            // Get supplier analytics service
            $analytics = SupplierAnalytics::get_instance();

            // Get product analytics
            $data = $analytics->get_supplier_product_analytics($supplier_id, $date_from, $date_to);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier order analytics
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_supplier_order_analytics($request) {
        try {
            $supplier_id = $request->get_param('supplier_id');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate supplier exists
            $supplier = get_post($supplier_id);
            if (!$supplier || $supplier->post_type !== 'utilitysign_supplier') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Supplier not found', 'utilitysign')
                ], 404);
            }

            // Get supplier analytics service
            $analytics = SupplierAnalytics::get_instance();

            // Get order analytics
            $data = $analytics->get_supplier_order_analytics($supplier_id, $date_from, $date_to);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier performance analytics
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_supplier_performance_analytics($request) {
        try {
            $supplier_id = $request->get_param('supplier_id');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate supplier exists
            $supplier = get_post($supplier_id);
            if (!$supplier || $supplier->post_type !== 'utilitysign_supplier') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Supplier not found', 'utilitysign')
                ], 404);
            }

            // Get supplier analytics service
            $analytics = SupplierAnalytics::get_instance();

            // Get performance analytics
            $data = $analytics->get_supplier_performance_analytics($supplier_id, $date_from, $date_to);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier trends
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_supplier_trends($request) {
        try {
            $supplier_id = $request->get_param('supplier_id');
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');

            // Validate supplier exists
            $supplier = get_post($supplier_id);
            if (!$supplier || $supplier->post_type !== 'utilitysign_supplier') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Supplier not found', 'utilitysign')
                ], 404);
            }

            // Get supplier analytics service
            $analytics = SupplierAnalytics::get_instance();

            // Get trends
            $data = $analytics->get_supplier_trends($supplier_id, $date_from, $date_to);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check permissions for analytics endpoints
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_permissions($request) {
        // Only allow logged-in users to access analytics
        return is_user_logged_in();
    }
}