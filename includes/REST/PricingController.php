<?php
namespace UtilitySign\REST;

use UtilitySign\Traits\Base;
use UtilitySign\Services\PricingCalculator;

/**
 * Pricing REST API Controller
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class PricingController {
    use Base;

    /**
     * Initialize pricing controller
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
        register_rest_route('utilitysign/v1', '/pricing/calculate', [
            'methods' => 'POST',
            'callback' => [$this, 'calculate_pricing'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'variation_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'supplier_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'customer_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'discount_code' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/pricing/variations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pricing_variations'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/pricing/discounts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_available_discounts'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'product_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'supplier_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Calculate pricing for a product
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function calculate_pricing($request) {
        try {
            $product_id = $request->get_param('product_id');
            $quantity = $request->get_param('quantity');
            $variation_id = $request->get_param('variation_id');
            $supplier_id = $request->get_param('supplier_id');
            $customer_type = $request->get_param('customer_type');
            $discount_code = $request->get_param('discount_code');

            // Validate product exists
            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'utilitysign_product') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Product not found', 'utilitysign')
                ], 404);
            }

            // Get pricing calculator
            $calculator = PricingCalculator::get_instance();

            // Calculate pricing
            $pricing = $calculator->calculate_pricing([
                'product_id' => $product_id,
                'quantity' => $quantity,
                'variation_id' => $variation_id,
                'supplier_id' => $supplier_id,
                'customer_type' => $customer_type,
                'discount_code' => $discount_code
            ]);

            if (!$pricing) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Failed to calculate pricing', 'utilitysign')
                ], 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'data' => $pricing
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pricing variations for a product
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_pricing_variations($request) {
        try {
            $product_id = $request->get_param('product_id');

            // Validate product exists
            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'utilitysign_product') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Product not found', 'utilitysign')
                ], 404);
            }

            // Get pricing calculator
            $calculator = PricingCalculator::get_instance();

            // Get pricing variations
            $variations = $calculator->get_pricing_variations($product_id);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $variations
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available discounts
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_available_discounts($request) {
        try {
            $product_id = $request->get_param('product_id');
            $supplier_id = $request->get_param('supplier_id');

            // Get pricing calculator
            $calculator = PricingCalculator::get_instance();

            // Get available discounts
            $discounts = $calculator->get_available_discounts([
                'product_id' => $product_id,
                'supplier_id' => $supplier_id
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $discounts
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check permissions for pricing endpoints
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_permissions($request) {
        // Allow public access to pricing calculations
        return true;
    }
}