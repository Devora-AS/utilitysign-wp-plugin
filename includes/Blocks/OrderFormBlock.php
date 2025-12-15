<?php
/**
 * Order Form Block
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Blocks;

use UtilitySign\Traits\Base;

/**
 * Order Form Block class
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class OrderFormBlock {
    use Base;

    /**
     * Initialize the block
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_utilitysign_submit_order', [$this, 'handle_order_submission']);
        add_action('wp_ajax_nopriv_utilitysign_submit_order', [$this, 'handle_order_submission']);
    }

    /**
     * Register the block
     * 
     * @since 1.0.0
     */
    public function register_block() {
        register_block_type('utilitysign/order-form', [
            'attributes' => [
                'productId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'supplierId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'showProductSelection' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showSupplierSelection' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showQuantitySelector' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showCustomerDetails' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showTermsCheckbox' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'redirectUrl' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'successMessage' => [
                    'type' => 'string',
                    'default' => __('Order submitted successfully!', 'utilitysign')
                ],
                'buttonText' => [
                    'type' => 'string',
                    'default' => __('Submit Order', 'utilitysign')
                ],
                'formTitle' => [
                    'type' => 'string',
                    'default' => __('Place Your Order', 'utilitysign')
                ]
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'utilitysign-order-form-block',
            'editor_style' => 'utilitysign-order-form-block-editor',
            'style' => 'utilitysign-order-form-block'
        ]);
    }

    /**
     * Enqueue block editor assets
     * 
     * @since 1.0.0
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'utilitysign-order-form-block',
            UTILITYSIGN_URL . 'assets/js/blocks/order-form.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            UTILITYSIGN_VERSION,
            true
        );

        wp_enqueue_style(
            'utilitysign-order-form-block-editor',
            UTILITYSIGN_URL . 'assets/css/blocks/order-form-editor.css',
            ['wp-edit-blocks'],
            UTILITYSIGN_VERSION
        );

        // Localize script with data
        wp_localize_script('utilitysign-order-form-block', 'utilitySignOrderForm', [
            'products' => $this->get_products_for_editor(),
            'suppliers' => $this->get_suppliers_for_editor()
        ]);
    }

    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        if (has_block('utilitysign/order-form')) {
            wp_enqueue_style(
                'utilitysign-order-form-block',
                UTILITYSIGN_URL . 'assets/css/blocks/order-form.css',
                [],
                UTILITYSIGN_VERSION
            );

            wp_enqueue_script(
                'utilitysign-order-form-frontend',
                UTILITYSIGN_URL . 'assets/js/frontend/order-form.js',
                ['jquery'],
                UTILITYSIGN_VERSION,
                true
            );

            wp_localize_script('utilitysign-order-form-frontend', 'utilitySignOrderForm', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('utilitysign_nonce'),
                'apiUrl' => rest_url('utilitysign/v1/')
            ]);
        }
    }

    /**
     * Render the block
     * 
     * @since 1.0.0
     * @param array $attributes
     * @return string
     */
    public function render_block($attributes) {
        $product_id = $attributes['productId'] ?? 0;
        $supplier_id = $attributes['supplierId'] ?? 0;
        $show_product_selection = $attributes['showProductSelection'] ?? true;
        $show_supplier_selection = $attributes['showSupplierSelection'] ?? true;
        $show_quantity_selector = $attributes['showQuantitySelector'] ?? true;
        $show_customer_details = $attributes['showCustomerDetails'] ?? true;
        $show_terms_checkbox = $attributes['showTermsCheckbox'] ?? true;
        $redirect_url = $attributes['redirectUrl'] ?? '';
        $success_message = $attributes['successMessage'] ?? __('Order submitted successfully!', 'utilitysign');
        $button_text = $attributes['buttonText'] ?? __('Submit Order', 'utilitysign');
        $form_title = $attributes['formTitle'] ?? __('Place Your Order', 'utilitysign');

        ob_start();
        ?>
        <div class="utilitysign-order-form" data-redirect-url="<?php echo esc_attr($redirect_url); ?>" data-success-message="<?php echo esc_attr($success_message); ?>">
            <form id="utilitysign-order-form" class="order-form" method="post">
                <?php wp_nonce_field('utilitysign_order_form', 'utilitysign_order_nonce'); ?>
                
                <h3 class="form-title"><?php echo esc_html($form_title); ?></h3>
                
                <div class="form-sections">
                    <?php if ($show_supplier_selection): ?>
                        <div class="form-section supplier-selection">
                            <h4><?php _e('Select Supplier', 'utilitysign'); ?></h4>
                            <div class="field-group">
                                <label for="order_supplier_id"><?php _e('Supplier', 'utilitysign'); ?> <span class="required">*</span></label>
                                <select id="order_supplier_id" name="order_supplier_id" required>
                                    <option value=""><?php _e('Choose a supplier', 'utilitysign'); ?></option>
                                    <?php
                                    $suppliers = $this->get_suppliers_for_form();
                                    foreach ($suppliers as $supplier) {
                                        $selected = ($supplier_id == $supplier['id']) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($supplier['id']) . '" ' . $selected . '>' . esc_html($supplier['title']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="order_supplier_id" value="<?php echo esc_attr($supplier_id); ?>" />
                    <?php endif; ?>

                    <?php if ($show_product_selection): ?>
                        <div class="form-section product-selection">
                            <h4><?php _e('Select Product', 'utilitysign'); ?></h4>
                            <div class="field-group">
                                <label for="order_product_id"><?php _e('Product', 'utilitysign'); ?> <span class="required">*</span></label>
                                <select id="order_product_id" name="order_product_id" required>
                                    <option value=""><?php _e('Choose a product', 'utilitysign'); ?></option>
                                    <?php
                                    $products = $this->get_products_for_form($supplier_id);
                                    foreach ($products as $product) {
                                        $selected = ($product_id == $product['id']) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($product['id']) . '" ' . $selected . ' data-price="' . esc_attr($product['price']) . '">' . esc_html($product['title']) . ' - ' . esc_html($product['price']) . ' NOK</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="order_product_id" value="<?php echo esc_attr($product_id); ?>" />
                    <?php endif; ?>

                    <?php if ($show_quantity_selector): ?>
                        <div class="form-section quantity-selection">
                            <h4><?php _e('Quantity', 'utilitysign'); ?></h4>
                            <div class="field-group">
                                <label for="order_quantity"><?php _e('Quantity', 'utilitysign'); ?> <span class="required">*</span></label>
                                <input type="number" id="order_quantity" name="order_quantity" min="1" value="1" required />
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_customer_details): ?>
                        <div class="form-section customer-details">
                            <h4><?php _e('Customer Information', 'utilitysign'); ?></h4>
                            <div class="field-group">
                                <label for="order_customer_name"><?php _e('Full Name', 'utilitysign'); ?> <span class="required">*</span></label>
                                <input type="text" id="order_customer_name" name="order_customer_name" required />
                            </div>
                            <div class="field-group">
                                <label for="order_customer_email"><?php _e('Email Address', 'utilitysign'); ?> <span class="required">*</span></label>
                                <input type="email" id="order_customer_email" name="order_customer_email" required />
                            </div>
                            <div class="field-group">
                                <label for="order_customer_phone"><?php _e('Phone Number', 'utilitysign'); ?></label>
                                <input type="tel" id="order_customer_phone" name="order_customer_phone" />
                            </div>
                            <div class="field-group">
                                <label for="order_customer_company"><?php _e('Company', 'utilitysign'); ?></label>
                                <input type="text" id="order_customer_company" name="order_customer_company" />
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-section order-summary">
                        <h4><?php _e('Order Summary', 'utilitysign'); ?></h4>
                        <div class="order-totals">
                            <div class="total-line">
                                <span class="label"><?php _e('Product Price:', 'utilitysign'); ?></span>
                                <span class="value" id="product-price">0 NOK</span>
                            </div>
                            <div class="total-line">
                                <span class="label"><?php _e('Quantity:', 'utilitysign'); ?></span>
                                <span class="value" id="quantity-display">1</span>
                            </div>
                            <div class="total-line total">
                                <span class="label"><?php _e('Total:', 'utilitysign'); ?></span>
                                <span class="value" id="total-price">0 NOK</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($show_terms_checkbox): ?>
                        <div class="form-section terms-checkbox">
                            <div class="field-group">
                                <label>
                                    <input type="checkbox" name="order_terms_accepted" value="1" required />
                                    <?php _e('I agree to the terms and conditions', 'utilitysign'); ?> <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-section submit-section">
                        <button type="submit" class="submit-order-btn">
                            <?php echo esc_html($button_text); ?>
                        </button>
                        <div class="form-messages"></div>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle order submission
     * 
     * @since 1.0.0
     */
    public function handle_order_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['utilitysign_order_nonce'], 'utilitysign_order_form')) {
            wp_die(__('Security check failed', 'utilitysign'));
        }

        // Get form data
        $supplier_id = intval($_POST['order_supplier_id'] ?? 0);
        $product_id = intval($_POST['order_product_id'] ?? 0);
        $quantity = intval($_POST['order_quantity'] ?? 1);
        $customer_name = sanitize_text_field($_POST['order_customer_name'] ?? '');
        $customer_email = sanitize_email($_POST['order_customer_email'] ?? '');
        $customer_phone = sanitize_text_field($_POST['order_customer_phone'] ?? '');
        $customer_company = sanitize_text_field($_POST['order_customer_company'] ?? '');
        $terms_accepted = isset($_POST['order_terms_accepted']);

        // Validate required fields
        if (empty($supplier_id) || empty($product_id) || empty($customer_name) || empty($customer_email)) {
            wp_send_json_error(__('Please fill in all required fields', 'utilitysign'));
        }

        if (!$terms_accepted) {
            wp_send_json_error(__('You must accept the terms and conditions', 'utilitysign'));
        }

        // Get product price
        $product_price = get_post_meta($product_id, '_product_price', true);
        if (!$product_price) {
            wp_send_json_error(__('Product price not found', 'utilitysign'));
        }

        $total_price = floatval($product_price) * $quantity;

        // Create order
        $order_data = [
            'post_title' => sprintf(__('Order for %s', 'utilitysign'), $customer_name),
            'post_content' => sprintf(__('Order for %s (%s)', 'utilitysign'), $customer_name, $customer_email),
            'post_status' => 'publish',
            'post_type' => 'utilitysign_order',
            'meta_input' => [
                '_order_supplier_id' => $supplier_id,
                '_order_product_id' => $product_id,
                '_order_quantity' => $quantity,
                '_order_customer_name' => $customer_name,
                '_order_customer_email' => $customer_email,
                '_order_customer_phone' => $customer_phone,
                '_order_customer_company' => $customer_company,
                '_order_total_price' => $total_price,
                '_order_status' => 'pending',
                '_order_terms_accepted' => $terms_accepted ? '1' : '0',
                '_order_created_date' => current_time('mysql')
            ]
        ];

        $order_id = wp_insert_post($order_data);

        if (is_wp_error($order_id)) {
            wp_send_json_error(__('Failed to create order', 'utilitysign'));
        }

        // Send notification email
        $this->send_order_notification($order_id, $customer_email, $customer_name);

        wp_send_json_success([
            'message' => __('Order submitted successfully!', 'utilitysign'),
            'order_id' => $order_id
        ]);
    }

    /**
     * Send order notification email
     * 
     * @since 1.0.0
     * @param int $order_id
     * @param string $customer_email
     * @param string $customer_name
     */
    private function send_order_notification($order_id, $customer_email, $customer_name) {
        $subject = sprintf(__('Order Confirmation #%d', 'utilitysign'), $order_id);
        $message = sprintf(
            __('Hello %s,\n\nYour order has been received and is being processed.\n\nOrder ID: %d\n\nThank you for your business!', 'utilitysign'),
            $customer_name,
            $order_id
        );

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Get products for form
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @return array
     */
    private function get_products_for_form($supplier_id = 0) {
        $query_args = [
            'post_type' => 'utilitysign_product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        if ($supplier_id > 0) {
            $query_args['meta_query'] = [
                [
                    'key' => '_product_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ];
        }

        $query = new \WP_Query($query_args);
        $products = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $products[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'price' => get_post_meta($post_id, '_product_price', true)
                ];
            }
            wp_reset_postdata();
        }

        return $products;
    }

    /**
     * Get suppliers for form
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_suppliers_for_form() {
        $query = new \WP_Query([
            'post_type' => 'utilitysign_supplier',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $suppliers = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $suppliers[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title()
                ];
            }
            wp_reset_postdata();
        }

        return $suppliers;
    }

    /**
     * Get products for editor
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_products_for_editor() {
        $query = new \WP_Query([
            'post_type' => 'utilitysign_product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $products = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $products[] = [
                    'value' => get_the_ID(),
                    'label' => get_the_title()
                ];
            }
            wp_reset_postdata();
        }

        return $products;
    }

    /**
     * Get suppliers for editor
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_suppliers_for_editor() {
        $query = new \WP_Query([
            'post_type' => 'utilitysign_supplier',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $suppliers = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $suppliers[] = [
                    'value' => get_the_ID(),
                    'label' => get_the_title()
                ];
            }
            wp_reset_postdata();
        }

        return $suppliers;
    }
}