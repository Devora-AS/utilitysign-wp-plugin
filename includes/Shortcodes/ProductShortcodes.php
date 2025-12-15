<?php

namespace UtilitySign\Shortcodes;

use UtilitySign\Traits\Base;

/**
 * Product Shortcodes Handler
 * Handles product-related shortcodes for the UtilitySign plugin
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ProductShortcodes {
    use Base;

    /**
     * Initialize shortcodes
     * 
     * @since 1.0.0
     */
    public function init() {
        add_shortcode('utilitysign_product_selection', [$this, 'render_product_selection']);
        add_shortcode('utilitysign_products', [$this, 'render_product_selection']); // Alias for easier use
        add_shortcode('utilitysign_product_display', [$this, 'render_product_display']);
        add_shortcode('utilitysign_order_form', [$this, 'render_order_form']);
        add_shortcode('utilitysign_signing_process', [$this, 'render_signing_process']);
    }

    /**
     * Render product selection shortcode
     * 
     * Usage: [utilitysign_products] or [utilitysign_products category="Spot" max="3"]
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_product_selection($atts) {
        $atts = shortcode_atts([
            'category' => '',
            'max' => '',
            'show_categories' => 'true',
            'class' => '',
        ], $atts, 'utilitysign_product_selection');

        // Sanitize attributes
        $category = sanitize_text_field($atts['category']);
        $max = absint($atts['max']);
        $show_categories = $atts['show_categories'] === 'true';
        $class = sanitize_text_field($atts['class']);

        // Enqueue frontend assets
        $this->enqueue_assets();

        // Generate unique ID
        $container_id = 'utilitysign-products-' . uniqid();

        // Render container
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($container_id); ?>" 
            class="utilitysign-products-container <?php echo esc_attr($class); ?>"
            data-category="<?php echo esc_attr($category); ?>"
            data-max="<?php echo esc_attr($max); ?>"
            data-show-categories="<?php echo esc_attr($show_categories ? 'true' : 'false'); ?>"
        >
            <!-- ProductList React component will be mounted here -->
            <div class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-devora-primary mb-4"></div>
                <p class="text-devora-text-muted">Laster produkter...</p>
            </div>
        </div>
        <script>
            // Mount React component when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                if (window.UtilitySignApp && window.UtilitySignApp.mountProductList) {
                    window.UtilitySignApp.mountProductList('<?php echo esc_js($container_id); ?>', {
                        category: '<?php echo esc_js($category); ?>',
                        maxProducts: <?php echo $max ? $max : 'null'; ?>,
                        showCategories: <?php echo $show_categories ? 'true' : 'false'; ?>
                    });
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render product display shortcode
     * 
     * Usage: [utilitysign_product_display id="123" show_price="true" show_description="true"]
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_product_display($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'show_price' => 'true',
            'show_description' => 'true',
            'show_variations' => 'false',
            'show_button' => 'true',
            'button_text' => __('Order Now', 'utilitysign'),
            'class' => '',
        ], $atts, 'utilitysign_product_display');

        // Validate required attributes
        if (empty($atts['id'])) {
            return '<div class="utilitysign-error">' . 
                   esc_html__('Product ID is required.', 'utilitysign') . 
                   '</div>';
        }

        // Sanitize attributes
        $product_id = sanitize_text_field($atts['id']);
        $show_price = filter_var($atts['show_price'], FILTER_VALIDATE_BOOLEAN);
        $show_description = filter_var($atts['show_description'], FILTER_VALIDATE_BOOLEAN);
        $show_variations = filter_var($atts['show_variations'], FILTER_VALIDATE_BOOLEAN);
        $show_button = filter_var($atts['show_button'], FILTER_VALIDATE_BOOLEAN);
        $button_text = sanitize_text_field($atts['button_text']);
        $class = sanitize_text_field($atts['class']);

        // Get product data
        $product = get_post($product_id);

        if (!$product || $product->post_type !== 'utilitysign_product') {
            return '<div class="utilitysign-error">' . 
                   esc_html__('Product not found.', 'utilitysign') . 
                   '</div>';
        }

        // Get product meta
        $base_price = get_post_meta($product_id, '_product_base_price', true);
        $currency = get_post_meta($product_id, '_product_currency', true);
        $billing_cycle = get_post_meta($product_id, '_product_billing_cycle', true);
        $category = get_post_meta($product_id, '_product_category', true);
        $variations = get_post_meta($product_id, '_product_variations', true);

        // Enqueue assets
        $this->enqueue_assets();

        // Render product display
        ob_start();
        ?>
        <div class="utilitysign-product-display <?php echo esc_attr($class); ?>">
            <div class="devora-card devora-card-white">
                <div class="devora-card-header">
                    <h3 class="font-heading text-2xl font-black text-devora-primary">
                        <?php echo esc_html($product->post_title); ?>
                    </h3>
                    <?php if ($category): ?>
                        <span class="devora-badge-secondary text-xs mt-2">
                            <?php echo esc_html(ucfirst($category)); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="devora-card-content">
                    <?php if ($show_description && !empty($product->post_content)): ?>
                        <div class="text-devora-text-primary mb-4">
                            <?php echo wp_kses_post($product->post_content); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_price && !empty($base_price)): ?>
                        <div class="flex items-baseline mb-4">
                            <span class="font-heading text-3xl font-black text-devora-primary">
                                <?php echo esc_html(number_format($base_price, 2)); ?>
                            </span>
                            <span class="text-devora-text-primary ml-2">
                                <?php echo esc_html($currency ?: 'NOK'); ?>
                            </span>
                            <span class="text-sm text-devora-text-primary ml-2">
                                /<?php echo esc_html($billing_cycle ?: 'monthly'); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_variations && is_array($variations) && !empty($variations)): ?>
                        <div class="mb-4">
                            <h4 class="font-ui font-bold text-devora-primary mb-2">
                                <?php _e('Available Options:', 'utilitysign'); ?>
                            </h4>
                            <ul class="space-y-2">
                                <?php foreach ($variations as $variation): ?>
                                    <li class="flex items-center justify-between p-2 bg-devora-background-light rounded-devora-button">
                                        <span class="font-ui font-bold text-devora-primary">
                                            <?php echo esc_html($variation['name'] ?? ''); ?>
                                        </span>
                                        <?php if (!empty($variation['price_modifier']) && $variation['price_modifier'] != 0): ?>
                                            <span class="text-sm text-devora-text-primary">
                                                <?php echo $variation['price_modifier'] > 0 ? '+' : ''; ?>
                                                <?php echo esc_html(number_format($variation['price_modifier'], 2)); ?>
                                                <?php echo esc_html($currency ?: 'NOK'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_button): ?>
                        <a href="<?php echo esc_url(add_query_arg('product_id', $product_id, get_permalink())); ?>" 
                           class="devora-button devora-button-primary w-full text-center">
                            <?php echo esc_html($button_text); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render order form shortcode
     * 
     * Usage: [utilitysign_order_form product_id="affb118d-f367-4d64-a380-2e2413cce181" supplier_id="df210673..."]
     * 
     * Supports both:
     * - WordPress custom post products (legacy)
     * - Backend API products (GUID format for multi-tenant SaaS)
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_order_form($atts) {
        $atts = shortcode_atts([
            'product_id' => '',
            'supplier_id' => '',
            'class' => '',
        ], $atts, 'utilitysign_order_form');

        // Validate required attributes
        if (empty($atts['product_id'])) {
            return '<div class="utilitysign-error">' . 
                   esc_html__('Product ID is required.', 'utilitysign') . 
                   '</div>';
        }

        // Sanitize attributes
        $product_id = sanitize_text_field($atts['product_id']);
        $supplier_id = sanitize_text_field($atts['supplier_id']);
        $class = sanitize_text_field($atts['class']);

        // Check if this is a WordPress post ID (numeric) or API GUID (UUID format)
        $is_api_product = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $product_id);

        if (!$is_api_product) {
            // Legacy: WordPress custom post product
            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'utilitysign_product') {
                return '<div class="utilitysign-error">' . 
                       esc_html__('Product not found.', 'utilitysign') . 
                       '</div>';
            }
        } else {
            // New: Backend API product (GUID)
            // Validation: Just verify it's a valid GUID format (already checked above)
            // The React component will fetch product details from the API
            if (empty($supplier_id)) {
                return '<div class="utilitysign-error">' . 
                       esc_html__('Supplier ID is required for API products.', 'utilitysign') . 
                       '</div>';
            }
        }

        // Enqueue assets
        $this->enqueue_assets();

        // Generate unique ID for container (use hyphens for CSS)
        $container_id = 'utilitysign-order-form-' . uniqid();
        
        // Generate valid JavaScript variable name (use underscores, not hyphens)
        $js_var_name = 'utilitySignOrderForm_' . str_replace('-', '_', $container_id);

        // Localize script with both product and supplier IDs
        wp_localize_script('utilitysign-frontend', $js_var_name, [
            'productId' => $product_id,
            'supplierId' => $supplier_id,
            'containerId' => $container_id,
            'isApiProduct' => $is_api_product,
        ]);

        // Render container
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($container_id); ?>" 
            class="utilitysign-order-form <?php echo esc_attr($class); ?>"
            data-product-id="<?php echo esc_attr($product_id); ?>"
            data-supplier-id="<?php echo esc_attr($supplier_id); ?>"
            data-is-api-product="<?php echo $is_api_product ? 'true' : 'false'; ?>"
        >
            <!-- React component will be mounted here -->
            <div class="utilitysign-loading">
                <p><?php esc_html_e('Laster bestillingsskjema...', 'utilitysign'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render complete signing process shortcode
     * 
     * Usage: [utilitysign_signing_process supplier="123" category="electricity" product_id="456"]
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_signing_process($atts) {
        $atts = shortcode_atts([
            'supplier' => '',
            'category' => '',
            'product_id' => '',
            'class' => '',
        ], $atts, 'utilitysign_signing_process');

        // Sanitize attributes
        $supplier_id = sanitize_text_field($atts['supplier']);
        $category = sanitize_text_field($atts['category']);
        $product_id = sanitize_text_field($atts['product_id']);
        $class = sanitize_text_field($atts['class']);

        // Enqueue assets
        $this->enqueue_assets();

        // Generate unique ID
        $container_id = 'utilitysign-signing-process-' . uniqid();

        // Localize script
        wp_localize_script('utilitysign-frontend', 'utilitySignSigningProcess_' . $container_id, [
            'supplierId' => $supplier_id,
            'category' => $category,
            'productId' => $product_id,
            'containerId' => $container_id,
        ]);

        // Render container
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($container_id); ?>" 
            class="utilitysign-signing-process <?php echo esc_attr($class); ?>"
            data-supplier="<?php echo esc_attr($supplier_id); ?>"
            data-category="<?php echo esc_attr($category); ?>"
            data-product-id="<?php echo esc_attr($product_id); ?>"
        >
            <!-- React component will be mounted here -->
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue frontend assets using Vite manifest
     * 
     * @since 1.0.0
     */
    private function enqueue_assets() {
        // Use the proper Vite asset enqueue system like SigningFormShortcode does
        if (!wp_script_is('utilitysign-frontend', 'enqueued')) {
            \UtilitySign\Libs\Assets\enqueue_asset(
                UTILITYSIGN_DIR . 'assets/frontend/dist',
                'src/frontend/main.jsx',
                [
                    'dependencies' => ['react', 'react-dom'],
                    'handle'       => 'utilitysign-frontend',
                    'in-footer'    => true,
                ]
            );
            
            // Localize common script data
            wp_localize_script('utilitysign-frontend', 'utilitySign', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('utilitysign_nonce'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'apiUrl' => rest_url('utilitysign/v1/'),
            ]);
        }
    }
}

