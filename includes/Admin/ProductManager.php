<?php

namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;

/**
 * Product Manager for UtilitySign Plugin
 * Handles custom post type for products and admin interface
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ProductManager {
    use Base;

    /**
     * Custom post type name
     */
    const POST_TYPE = 'utilitysign_product';

    /**
     * Initialize the product manager
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_product_meta']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'display_custom_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_columns_sortable']);
        add_action('restrict_manage_posts', [$this, 'add_category_filter']);
        add_action('parse_query', [$this, 'filter_by_category']);
    }

    /**
     * Register custom post type for products
     * 
     * @since 1.0.0
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Products', 'utilitysign'),
            'singular_name' => __('Product', 'utilitysign'),
            'menu_name' => __('Products', 'utilitysign'),
            'add_new' => __('Add New Product', 'utilitysign'),
            'add_new_item' => __('Add New Product', 'utilitysign'),
            'edit_item' => __('Edit Product', 'utilitysign'),
            'new_item' => __('New Product', 'utilitysign'),
            'view_item' => __('View Product', 'utilitysign'),
            'search_items' => __('Search Products', 'utilitysign'),
            'not_found' => __('No products found', 'utilitysign'),
            'not_found_in_trash' => __('No products found in trash', 'utilitysign'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // Hidden from admin menu per plan - managed in SaaS admin
            'query_var' => true,
            'rewrite' => ['slug' => 'products'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-products',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base' => 'products',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register taxonomies for products
     * 
     * @since 1.0.0
     */
    public function register_taxonomies() {
        // Product Categories Taxonomy
        $category_labels = [
            'name' => __('Product Categories', 'utilitysign'),
            'singular_name' => __('Product Category', 'utilitysign'),
            'menu_name' => __('Categories', 'utilitysign'),
            'all_items' => __('All Categories', 'utilitysign'),
            'parent_item' => __('Parent Category', 'utilitysign'),
            'parent_item_colon' => __('Parent Category:', 'utilitysign'),
            'new_item_name' => __('New Category Name', 'utilitysign'),
            'add_new_item' => __('Add New Category', 'utilitysign'),
            'edit_item' => __('Edit Category', 'utilitysign'),
            'update_item' => __('Update Category', 'utilitysign'),
            'view_item' => __('View Category', 'utilitysign'),
            'separate_items_with_commas' => __('Separate categories with commas', 'utilitysign'),
            'add_or_remove_items' => __('Add or remove categories', 'utilitysign'),
            'choose_from_most_used' => __('Choose from the most used', 'utilitysign'),
            'popular_items' => __('Popular Categories', 'utilitysign'),
            'search_items' => __('Search Categories', 'utilitysign'),
            'not_found' => __('Not Found', 'utilitysign'),
            'no_terms' => __('No categories', 'utilitysign'),
            'items_list' => __('Categories list', 'utilitysign'),
            'items_list_navigation' => __('Categories list navigation', 'utilitysign'),
        ];

        $category_args = [
            'labels' => $category_labels,
            'hierarchical' => true,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'rest_base' => 'product-categories',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'rewrite' => ['slug' => 'product-category'],
        ];

        register_taxonomy('utilitysign_product_category', self::POST_TYPE, $category_args);

        // Product Tags Taxonomy
        $tag_labels = [
            'name' => __('Product Tags', 'utilitysign'),
            'singular_name' => __('Product Tag', 'utilitysign'),
            'menu_name' => __('Tags', 'utilitysign'),
            'all_items' => __('All Tags', 'utilitysign'),
            'new_item_name' => __('New Tag Name', 'utilitysign'),
            'add_new_item' => __('Add New Tag', 'utilitysign'),
            'edit_item' => __('Edit Tag', 'utilitysign'),
            'update_item' => __('Update Tag', 'utilitysign'),
            'view_item' => __('View Tag', 'utilitysign'),
            'separate_items_with_commas' => __('Separate tags with commas', 'utilitysign'),
            'add_or_remove_items' => __('Add or remove tags', 'utilitysign'),
            'choose_from_most_used' => __('Choose from the most used', 'utilitysign'),
            'popular_items' => __('Popular Tags', 'utilitysign'),
            'search_items' => __('Search Tags', 'utilitysign'),
            'not_found' => __('Not Found', 'utilitysign'),
            'no_terms' => __('No tags', 'utilitysign'),
            'items_list' => __('Tags list', 'utilitysign'),
            'items_list_navigation' => __('Tags list navigation', 'utilitysign'),
        ];

        $tag_args = [
            'labels' => $tag_labels,
            'hierarchical' => false,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'rest_base' => 'product-tags',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'rewrite' => ['slug' => 'product-tag'],
        ];

        register_taxonomy('utilitysign_product_tag', self::POST_TYPE, $tag_args);
    }

    /**
     * Add meta boxes for product data
     * 
     * @since 1.0.0
     */
    public function add_meta_boxes() {
        add_meta_box(
            'product_details',
            __('Product Details', 'utilitysign'),
            [$this, 'product_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'product_pricing',
            __('Pricing & Configuration', 'utilitysign'),
            [$this, 'product_pricing_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'product_supplier',
            __('Supplier Information', 'utilitysign'),
            [$this, 'product_supplier_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'product_variations',
            __('Product Variations', 'utilitysign'),
            [$this, 'product_variations_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'product_terms',
            __('Terms & Conditions', 'utilitysign'),
            [$this, 'product_terms_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'product_advanced_pricing',
            __('Advanced Pricing Models', 'utilitysign'),
            [$this, 'product_advanced_pricing_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * Product details meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_details_meta_box($post) {
        wp_nonce_field('product_meta_nonce', 'product_meta_nonce');
        
        $product_id = get_post_meta($post->ID, '_product_id', true);
        $description = get_post_meta($post->ID, '_product_description', true);
        $category = get_post_meta($post->ID, '_product_category', true);
        $status = get_post_meta($post->ID, '_product_status', true);
        $is_active = get_post_meta($post->ID, '_product_is_active', true);
        $sort_order = get_post_meta($post->ID, '_product_sort_order', true);
        $created_at = get_post_meta($post->ID, '_product_created_at', true);
        $updated_at = get_post_meta($post->ID, '_product_updated_at', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="product_id"><?php _e('Product ID', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="product_id" name="product_id" value="<?php echo esc_attr($product_id); ?>" class="regular-text" />
                    <p class="description"><?php _e('Unique identifier for the product', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="product_description"><?php _e('Description', 'utilitysign'); ?></label></th>
                <td>
                    <textarea id="product_description" name="product_description" rows="4" cols="50"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="product_category"><?php _e('Category', 'utilitysign'); ?></label></th>
                <td>
                    <select id="product_category" name="product_category">
                        <option value=""><?php _e('Select Category', 'utilitysign'); ?></option>
                        <option value="electricity" <?php selected($category, 'electricity'); ?>><?php _e('Electricity', 'utilitysign'); ?></option>
                        <option value="water" <?php selected($category, 'water'); ?>><?php _e('Water', 'utilitysign'); ?></option>
                        <option value="gas" <?php selected($category, 'gas'); ?>><?php _e('Gas', 'utilitysign'); ?></option>
                        <option value="internet" <?php selected($category, 'internet'); ?>><?php _e('Internet', 'utilitysign'); ?></option>
                        <option value="other" <?php selected($category, 'other'); ?>><?php _e('Other', 'utilitysign'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="product_status"><?php _e('Status', 'utilitysign'); ?></label></th>
                <td>
                    <select id="product_status" name="product_status">
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'utilitysign'); ?></option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>><?php _e('Inactive', 'utilitysign'); ?></option>
                        <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'utilitysign'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="product_is_active"><?php _e('Is Active', 'utilitysign'); ?></label></th>
                <td>
                    <input type="checkbox" id="product_is_active" name="product_is_active" value="1" <?php checked($is_active, '1'); ?> />
                    <label for="product_is_active"><?php _e('Product is currently active', 'utilitysign'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="product_sort_order"><?php _e('Sort Order', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="product_sort_order" name="product_sort_order" value="<?php echo esc_attr($sort_order); ?>" min="0" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Product pricing meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_pricing_meta_box($post) {
        $base_price = get_post_meta($post->ID, '_product_base_price', true);
        $currency = get_post_meta($post->ID, '_product_currency', true);
        $billing_cycle = get_post_meta($post->ID, '_product_billing_cycle', true);
        $setup_fee = get_post_meta($post->ID, '_product_setup_fee', true);
        $trial_period = get_post_meta($post->ID, '_product_trial_period', true);
        $cancellation_fee = get_post_meta($post->ID, '_product_cancellation_fee', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="product_base_price"><?php _e('Base Price', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="product_base_price" name="product_base_price" value="<?php echo esc_attr($base_price); ?>" step="0.01" min="0" />
                </td>
            </tr>
            <tr>
                <th><label for="product_currency"><?php _e('Currency', 'utilitysign'); ?></label></th>
                <td>
                    <select id="product_currency" name="product_currency">
                        <option value="NOK" <?php selected($currency, 'NOK'); ?>><?php _e('NOK', 'utilitysign'); ?></option>
                        <option value="EUR" <?php selected($currency, 'EUR'); ?>><?php _e('EUR', 'utilitysign'); ?></option>
                        <option value="USD" <?php selected($currency, 'USD'); ?>><?php _e('USD', 'utilitysign'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="product_billing_cycle"><?php _e('Billing Cycle', 'utilitysign'); ?></label></th>
                <td>
                    <select id="product_billing_cycle" name="product_billing_cycle">
                        <option value="monthly" <?php selected($billing_cycle, 'monthly'); ?>><?php _e('Monthly', 'utilitysign'); ?></option>
                        <option value="quarterly" <?php selected($billing_cycle, 'quarterly'); ?>><?php _e('Quarterly', 'utilitysign'); ?></option>
                        <option value="annually" <?php selected($billing_cycle, 'annually'); ?>><?php _e('Annually', 'utilitysign'); ?></option>
                        <option value="one_time" <?php selected($billing_cycle, 'one_time'); ?>><?php _e('One Time', 'utilitysign'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="product_setup_fee"><?php _e('Setup Fee', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="product_setup_fee" name="product_setup_fee" value="<?php echo esc_attr($setup_fee); ?>" step="0.01" min="0" />
                </td>
            </tr>
            <tr>
                <th><label for="product_trial_period"><?php _e('Trial Period (days)', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="product_trial_period" name="product_trial_period" value="<?php echo esc_attr($trial_period); ?>" min="0" />
                </td>
            </tr>
            <tr>
                <th><label for="product_cancellation_fee"><?php _e('Cancellation Fee', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="product_cancellation_fee" name="product_cancellation_fee" value="<?php echo esc_attr($cancellation_fee); ?>" step="0.01" min="0" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Product supplier meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_supplier_meta_box($post) {
        $supplier_id = get_post_meta($post->ID, '_product_supplier_id', true);
        $supplier_name = get_post_meta($post->ID, '_product_supplier_name', true);
        $supplier_contact = get_post_meta($post->ID, '_product_supplier_contact', true);
        $supplier_email = get_post_meta($post->ID, '_product_supplier_email', true);
        $supplier_phone = get_post_meta($post->ID, '_product_supplier_phone', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="product_supplier_id"><?php _e('Supplier ID', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="product_supplier_id" name="product_supplier_id" value="<?php echo esc_attr($supplier_id); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="product_supplier_name"><?php _e('Supplier Name', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="product_supplier_name" name="product_supplier_name" value="<?php echo esc_attr($supplier_name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="product_supplier_contact"><?php _e('Contact Person', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="product_supplier_contact" name="product_supplier_contact" value="<?php echo esc_attr($supplier_contact); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="product_supplier_email"><?php _e('Email', 'utilitysign'); ?></label></th>
                <td>
                    <input type="email" id="product_supplier_email" name="product_supplier_email" value="<?php echo esc_attr($supplier_email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="product_supplier_phone"><?php _e('Phone', 'utilitysign'); ?></label></th>
                <td>
                    <input type="tel" id="product_supplier_phone" name="product_supplier_phone" value="<?php echo esc_attr($supplier_phone); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Product variations meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_variations_meta_box($post) {
        $variations = get_post_meta($post->ID, '_product_variations', true);
        if (!is_array($variations)) {
            $variations = [];
        }

        ?>
        <div id="product-variations-container">
            <p class="description"><?php _e('Add different variations of this product (e.g., different contract lengths, power levels, data packages)', 'utilitysign'); ?></p>
            
            <div id="variations-list">
                <?php
                if (!empty($variations)) {
                    foreach ($variations as $index => $variation) {
                        $this->render_variation_row($index, $variation);
                    }
                }
                ?>
            </div>
            
            <button type="button" class="button add-variation" id="add-variation-btn">
                <?php _e('Add Variation', 'utilitysign'); ?>
            </button>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let variationIndex = <?php echo count($variations); ?>;
            
            $('#add-variation-btn').on('click', function() {
                const template = `
                    <div class="variation-row" data-index="${variationIndex}">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Variation Name', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="text" name="product_variations[${variationIndex}][name]" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Description', 'utilitysign'); ?></label></th>
                                <td>
                                    <textarea name="product_variations[${variationIndex}][description]" rows="2" cols="50"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Price Modifier', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_variations[${variationIndex}][price_modifier]" step="0.01" value="0" />
                                    <p class="description"><?php _e('Additional cost (positive) or discount (negative)', 'utilitysign'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('SKU', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="text" name="product_variations[${variationIndex}][sku]" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Is Default', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="checkbox" name="product_variations[${variationIndex}][is_default]" value="1" />
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-secondary remove-variation"><?php _e('Remove', 'utilitysign'); ?></button>
                        <hr />
                    </div>
                `;
                
                $('#variations-list').append(template);
                variationIndex++;
            });
            
            $(document).on('click', '.remove-variation', function() {
                $(this).closest('.variation-row').remove();
            });
        });
        </script>
        
        <style>
        .variation-row {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .variation-row hr {
            margin-top: 15px;
        }
        </style>
        <?php
    }

    /**
     * Product terms & conditions meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_terms_meta_box($post) {
        $terms_content = get_post_meta($post->ID, '_product_terms_content', true);
        $terms_version = get_post_meta($post->ID, '_product_terms_version', true);
        $terms_effective_date = get_post_meta($post->ID, '_product_terms_effective_date', true);
        $require_acceptance = get_post_meta($post->ID, '_product_require_acceptance', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="product_terms_content"><?php _e('Terms & Conditions', 'utilitysign'); ?></label></th>
                <td>
                    <?php
                    wp_editor($terms_content, 'product_terms_content', [
                        'textarea_name' => 'product_terms_content',
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny' => true,
                    ]);
                    ?>
                    <p class="description"><?php _e('Product-specific terms and conditions that customers must accept', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="product_terms_version"><?php _e('Terms Version', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="product_terms_version" name="product_terms_version" value="<?php echo esc_attr($terms_version ?: '1.0'); ?>" class="regular-text" />
                    <p class="description"><?php _e('Version number for tracking changes', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="product_terms_effective_date"><?php _e('Effective Date', 'utilitysign'); ?></label></th>
                <td>
                    <input type="date" id="product_terms_effective_date" name="product_terms_effective_date" value="<?php echo esc_attr($terms_effective_date); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="product_require_acceptance"><?php _e('Require Acceptance', 'utilitysign'); ?></label></th>
                <td>
                    <input type="checkbox" id="product_require_acceptance" name="product_require_acceptance" value="1" <?php checked($require_acceptance, '1'); ?> />
                    <label for="product_require_acceptance"><?php _e('Customer must explicitly accept these terms', 'utilitysign'); ?></label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Product advanced pricing meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function product_advanced_pricing_meta_box($post) {
        $pricing_models = get_post_meta($post->ID, '_product_pricing_models', true);
        if (!is_array($pricing_models)) {
            $pricing_models = [];
        }

        $volume_discounts = get_post_meta($post->ID, '_product_volume_discounts', true);
        if (!is_array($volume_discounts)) {
            $volume_discounts = [];
        }

        $tier_pricing = get_post_meta($post->ID, '_product_tier_pricing', true);
        if (!is_array($tier_pricing)) {
            $tier_pricing = [];
        }

        ?>
        <div id="advanced-pricing-container">
            <h4><?php _e('Pricing Models', 'utilitysign'); ?></h4>
            <p class="description"><?php _e('Configure different pricing models for this product', 'utilitysign'); ?></p>
            
            <div id="pricing-models-list">
                <?php
                if (!empty($pricing_models)) {
                    foreach ($pricing_models as $index => $model) {
                        $this->render_pricing_model_row($index, $model);
                    }
                }
                ?>
            </div>
            
            <button type="button" class="button add-pricing-model" id="add-pricing-model-btn">
                <?php _e('Add Pricing Model', 'utilitysign'); ?>
            </button>

            <h4><?php _e('Volume Discounts', 'utilitysign'); ?></h4>
            <p class="description"><?php _e('Set up volume-based discounts for bulk purchases', 'utilitysign'); ?></p>
            
            <div id="volume-discounts-list">
                <?php
                if (!empty($volume_discounts)) {
                    foreach ($volume_discounts as $index => $discount) {
                        $this->render_volume_discount_row($index, $discount);
                    }
                }
                ?>
            </div>
            
            <button type="button" class="button add-volume-discount" id="add-volume-discount-btn">
                <?php _e('Add Volume Discount', 'utilitysign'); ?>
            </button>

            <h4><?php _e('Tier Pricing', 'utilitysign'); ?></h4>
            <p class="description"><?php _e('Configure tiered pricing based on usage or quantity', 'utilitysign'); ?></p>
            
            <div id="tier-pricing-list">
                <?php
                if (!empty($tier_pricing)) {
                    foreach ($tier_pricing as $index => $tier) {
                        $this->render_tier_pricing_row($index, $tier);
                    }
                }
                ?>
            </div>
            
            <button type="button" class="button add-tier-pricing" id="add-tier-pricing-btn">
                <?php _e('Add Tier Pricing', 'utilitysign'); ?>
            </button>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            let pricingModelIndex = <?php echo count($pricing_models); ?>;
            let volumeDiscountIndex = <?php echo count($volume_discounts); ?>;
            let tierPricingIndex = <?php echo count($tier_pricing); ?>;
            
            // Pricing Models
            $('#add-pricing-model-btn').on('click', function() {
                const template = `
                    <div class="pricing-model-row" data-index="${pricingModelIndex}">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Model Name', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="text" name="product_pricing_models[${pricingModelIndex}][name]" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Model Type', 'utilitysign'); ?></label></th>
                                <td>
                                    <select name="product_pricing_models[${pricingModelIndex}][type]">
                                        <option value="fixed"><?php _e('Fixed Price', 'utilitysign'); ?></option>
                                        <option value="percentage"><?php _e('Percentage Discount', 'utilitysign'); ?></option>
                                        <option value="tiered"><?php _e('Tiered Pricing', 'utilitysign'); ?></option>
                                        <option value="subscription"><?php _e('Subscription', 'utilitysign'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Base Price', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_pricing_models[${pricingModelIndex}][base_price]" step="0.01" min="0" />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Minimum Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_pricing_models[${pricingModelIndex}][min_quantity]" min="1" value="1" />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Maximum Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_pricing_models[${pricingModelIndex}][max_quantity]" min="1" />
                                    <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Valid From', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="date" name="product_pricing_models[${pricingModelIndex}][valid_from]" />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Valid Until', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="date" name="product_pricing_models[${pricingModelIndex}][valid_until]" />
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-secondary remove-pricing-model"><?php _e('Remove', 'utilitysign'); ?></button>
                        <hr />
                    </div>
                `;
                
                $('#pricing-models-list').append(template);
                pricingModelIndex++;
            });
            
            // Volume Discounts
            $('#add-volume-discount-btn').on('click', function() {
                const template = `
                    <div class="volume-discount-row" data-index="${volumeDiscountIndex}">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Minimum Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_volume_discounts[${volumeDiscountIndex}][min_quantity]" min="1" required />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Maximum Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_volume_discounts[${volumeDiscountIndex}][max_quantity]" min="1" />
                                    <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Discount Type', 'utilitysign'); ?></label></th>
                                <td>
                                    <select name="product_volume_discounts[${volumeDiscountIndex}][discount_type]">
                                        <option value="percentage"><?php _e('Percentage', 'utilitysign'); ?></option>
                                        <option value="fixed"><?php _e('Fixed Amount', 'utilitysign'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Discount Value', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_volume_discounts[${volumeDiscountIndex}][discount_value]" step="0.01" min="0" required />
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-secondary remove-volume-discount"><?php _e('Remove', 'utilitysign'); ?></button>
                        <hr />
                    </div>
                `;
                
                $('#volume-discounts-list').append(template);
                volumeDiscountIndex++;
            });
            
            // Tier Pricing
            $('#add-tier-pricing-btn').on('click', function() {
                const template = `
                    <div class="tier-pricing-row" data-index="${tierPricingIndex}">
                        <table class="form-table">
                            <tr>
                                <th><label><?php _e('Tier Name', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="text" name="product_tier_pricing[${tierPricingIndex}][name]" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('From Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_tier_pricing[${tierPricingIndex}][from_quantity]" min="0" required />
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('To Quantity', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_tier_pricing[${tierPricingIndex}][to_quantity]" min="0" />
                                    <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Price Per Unit', 'utilitysign'); ?></label></th>
                                <td>
                                    <input type="number" name="product_tier_pricing[${tierPricingIndex}][price_per_unit]" step="0.01" min="0" required />
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button button-secondary remove-tier-pricing"><?php _e('Remove', 'utilitysign'); ?></button>
                        <hr />
                    </div>
                `;
                
                $('#tier-pricing-list').append(template);
                tierPricingIndex++;
            });
            
            // Remove buttons
            $(document).on('click', '.remove-pricing-model', function() {
                $(this).closest('.pricing-model-row').remove();
            });
            
            $(document).on('click', '.remove-volume-discount', function() {
                $(this).closest('.volume-discount-row').remove();
            });
            
            $(document).on('click', '.remove-tier-pricing', function() {
                $(this).closest('.tier-pricing-row').remove();
            });
        });
        </script>
        
        <style>
        .pricing-model-row, .volume-discount-row, .tier-pricing-row {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pricing-model-row hr, .volume-discount-row hr, .tier-pricing-row hr {
            margin-top: 15px;
        }
        </style>
        <?php
    }

    /**
     * Render a single variation row
     * 
     * @since 1.0.0
     * @param int $index
     * @param array $variation
     */
    private function render_variation_row($index, $variation) {
        ?>
        <div class="variation-row" data-index="<?php echo esc_attr($index); ?>">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Variation Name', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="text" name="product_variations[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($variation['name'] ?? ''); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Description', 'utilitysign'); ?></label></th>
                    <td>
                        <textarea name="product_variations[<?php echo esc_attr($index); ?>][description]" rows="2" cols="50"><?php echo esc_textarea($variation['description'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Price Modifier', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_variations[<?php echo esc_attr($index); ?>][price_modifier]" value="<?php echo esc_attr($variation['price_modifier'] ?? '0'); ?>" step="0.01" />
                        <p class="description"><?php _e('Additional cost (positive) or discount (negative)', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('SKU', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="text" name="product_variations[<?php echo esc_attr($index); ?>][sku]" value="<?php echo esc_attr($variation['sku'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Is Default', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="checkbox" name="product_variations[<?php echo esc_attr($index); ?>][is_default]" value="1" <?php checked($variation['is_default'] ?? false, '1'); ?> />
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-secondary remove-variation"><?php _e('Remove', 'utilitysign'); ?></button>
            <hr />
        </div>
        <?php
    }

    /**
     * Render a single pricing model row
     * 
     * @since 1.0.0
     * @param int $index
     * @param array $model
     */
    private function render_pricing_model_row($index, $model) {
        ?>
        <div class="pricing-model-row" data-index="<?php echo esc_attr($index); ?>">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Model Name', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="text" name="product_pricing_models[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($model['name'] ?? ''); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Model Type', 'utilitysign'); ?></label></th>
                    <td>
                        <select name="product_pricing_models[<?php echo esc_attr($index); ?>][type]">
                            <option value="fixed" <?php selected($model['type'] ?? '', 'fixed'); ?>><?php _e('Fixed Price', 'utilitysign'); ?></option>
                            <option value="percentage" <?php selected($model['type'] ?? '', 'percentage'); ?>><?php _e('Percentage Discount', 'utilitysign'); ?></option>
                            <option value="tiered" <?php selected($model['type'] ?? '', 'tiered'); ?>><?php _e('Tiered Pricing', 'utilitysign'); ?></option>
                            <option value="subscription" <?php selected($model['type'] ?? '', 'subscription'); ?>><?php _e('Subscription', 'utilitysign'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Base Price', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_pricing_models[<?php echo esc_attr($index); ?>][base_price]" value="<?php echo esc_attr($model['base_price'] ?? ''); ?>" step="0.01" min="0" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Minimum Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_pricing_models[<?php echo esc_attr($index); ?>][min_quantity]" value="<?php echo esc_attr($model['min_quantity'] ?? '1'); ?>" min="1" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Maximum Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_pricing_models[<?php echo esc_attr($index); ?>][max_quantity]" value="<?php echo esc_attr($model['max_quantity'] ?? ''); ?>" min="1" />
                        <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Valid From', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="date" name="product_pricing_models[<?php echo esc_attr($index); ?>][valid_from]" value="<?php echo esc_attr($model['valid_from'] ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Valid Until', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="date" name="product_pricing_models[<?php echo esc_attr($index); ?>][valid_until]" value="<?php echo esc_attr($model['valid_until'] ?? ''); ?>" />
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-secondary remove-pricing-model"><?php _e('Remove', 'utilitysign'); ?></button>
            <hr />
        </div>
        <?php
    }

    /**
     * Render a single volume discount row
     * 
     * @since 1.0.0
     * @param int $index
     * @param array $discount
     */
    private function render_volume_discount_row($index, $discount) {
        ?>
        <div class="volume-discount-row" data-index="<?php echo esc_attr($index); ?>">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Minimum Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_volume_discounts[<?php echo esc_attr($index); ?>][min_quantity]" value="<?php echo esc_attr($discount['min_quantity'] ?? ''); ?>" min="1" required />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Maximum Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_volume_discounts[<?php echo esc_attr($index); ?>][max_quantity]" value="<?php echo esc_attr($discount['max_quantity'] ?? ''); ?>" min="1" />
                        <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Discount Type', 'utilitysign'); ?></label></th>
                    <td>
                        <select name="product_volume_discounts[<?php echo esc_attr($index); ?>][discount_type]">
                            <option value="percentage" <?php selected($discount['discount_type'] ?? '', 'percentage'); ?>><?php _e('Percentage', 'utilitysign'); ?></option>
                            <option value="fixed" <?php selected($discount['discount_type'] ?? '', 'fixed'); ?>><?php _e('Fixed Amount', 'utilitysign'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Discount Value', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_volume_discounts[<?php echo esc_attr($index); ?>][discount_value]" value="<?php echo esc_attr($discount['discount_value'] ?? ''); ?>" step="0.01" min="0" required />
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-secondary remove-volume-discount"><?php _e('Remove', 'utilitysign'); ?></button>
            <hr />
        </div>
        <?php
    }

    /**
     * Render a single tier pricing row
     * 
     * @since 1.0.0
     * @param int $index
     * @param array $tier
     */
    private function render_tier_pricing_row($index, $tier) {
        ?>
        <div class="tier-pricing-row" data-index="<?php echo esc_attr($index); ?>">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Tier Name', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="text" name="product_tier_pricing[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($tier['name'] ?? ''); ?>" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('From Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_tier_pricing[<?php echo esc_attr($index); ?>][from_quantity]" value="<?php echo esc_attr($tier['from_quantity'] ?? ''); ?>" min="0" required />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('To Quantity', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_tier_pricing[<?php echo esc_attr($index); ?>][to_quantity]" value="<?php echo esc_attr($tier['to_quantity'] ?? ''); ?>" min="0" />
                        <p class="description"><?php _e('Leave empty for unlimited', 'utilitysign'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Price Per Unit', 'utilitysign'); ?></label></th>
                    <td>
                        <input type="number" name="product_tier_pricing[<?php echo esc_attr($index); ?>][price_per_unit]" value="<?php echo esc_attr($tier['price_per_unit'] ?? ''); ?>" step="0.01" min="0" required />
                    </td>
                </tr>
            </table>
            <button type="button" class="button button-secondary remove-tier-pricing"><?php _e('Remove', 'utilitysign'); ?></button>
            <hr />
        </div>
        <?php
    }

    /**
     * Save product meta data
     * 
     * @since 1.0.0
     * @param int $post_id
     */
    public function save_product_meta($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check if this is the correct post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Check nonce
        if (!isset($_POST['product_meta_nonce']) || !wp_verify_nonce($_POST['product_meta_nonce'], 'product_meta_nonce')) {
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save product details
        $fields = [
            'product_id',
            'product_description',
            'product_category',
            'product_status',
            'product_is_active',
            'product_sort_order',
            'product_base_price',
            'product_currency',
            'product_billing_cycle',
            'product_setup_fee',
            'product_trial_period',
            'product_cancellation_fee',
            'product_supplier_id',
            'product_supplier_name',
            'product_supplier_contact',
            'product_supplier_email',
            'product_supplier_phone',
            'product_terms_version',
            'product_terms_effective_date',
            'product_require_acceptance'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }

        // Save product variations
        if (isset($_POST['product_variations']) && is_array($_POST['product_variations'])) {
            $variations = [];
            foreach ($_POST['product_variations'] as $variation) {
                $variations[] = [
                    'name' => sanitize_text_field($variation['name'] ?? ''),
                    'description' => sanitize_textarea_field($variation['description'] ?? ''),
                    'price_modifier' => floatval($variation['price_modifier'] ?? 0),
                    'sku' => sanitize_text_field($variation['sku'] ?? ''),
                    'is_default' => isset($variation['is_default']) ? '1' : '0',
                ];
            }
            update_post_meta($post_id, '_product_variations', $variations);
        } else {
            delete_post_meta($post_id, '_product_variations');
        }

        // Save terms & conditions content
        if (isset($_POST['product_terms_content'])) {
            $terms_content = wp_kses_post($_POST['product_terms_content']);
            update_post_meta($post_id, '_product_terms_content', $terms_content);
        }

        // Set timestamps
        if (get_post_status($post_id) === 'publish') {
            $created_at = get_post_meta($post_id, '_product_created_at', true);
            if (empty($created_at)) {
                update_post_meta($post_id, '_product_created_at', current_time('mysql'));
            }
            update_post_meta($post_id, '_product_updated_at', current_time('mysql'));
        }
    }

    /**
     * Add custom columns to products list
     * 
     * @since 1.0.0
     * @param array $columns
     * @return array
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Add checkbox column
        $new_columns['cb'] = $columns['cb'];
        
        // Add title column
        $new_columns['title'] = $columns['title'];
        
        // Add custom columns
        $new_columns['product_id'] = __('Product ID', 'utilitysign');
        $new_columns['category'] = __('Category', 'utilitysign');
        $new_columns['status'] = __('Status', 'utilitysign');
        $new_columns['price'] = __('Price', 'utilitysign');
        $new_columns['supplier'] = __('Supplier', 'utilitysign');
        $new_columns['is_active'] = __('Active', 'utilitysign');
        
        // Add date column
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }

    /**
     * Display custom column content
     * 
     * @since 1.0.0
     * @param string $column
     * @param int $post_id
     */
    public function display_custom_columns($column, $post_id) {
        switch ($column) {
            case 'product_id':
                echo esc_html(get_post_meta($post_id, '_product_id', true));
                break;
            case 'category':
                $categories = get_the_terms($post_id, 'utilitysign_product_category');
                if ($categories && !is_wp_error($categories)) {
                    $category_names = array_map(function($cat) {
                        return $cat->name;
                    }, $categories);
                    echo esc_html(implode(', ', $category_names));
                } else {
                    echo '—';
                }
                break;
            case 'status':
                $status = get_post_meta($post_id, '_product_status', true);
                $status_class = $status === 'active' ? 'status-active' : 'status-inactive';
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                break;
            case 'price':
                $base_price = get_post_meta($post_id, '_product_base_price', true);
                $currency = get_post_meta($post_id, '_product_currency', true);
                if ($base_price) {
                    echo esc_html($base_price . ' ' . $currency);
                }
                break;
            case 'supplier':
                echo esc_html(get_post_meta($post_id, '_product_supplier_name', true));
                break;
            case 'is_active':
                $is_active = get_post_meta($post_id, '_product_is_active', true);
                echo $is_active ? '✓' : '✗';
                break;
        }
    }

    /**
     * Make columns sortable
     * 
     * @since 1.0.0
     * @param array $columns
     * @return array
     */
    public function make_columns_sortable($columns) {
        $columns['product_id'] = 'product_id';
        $columns['category'] = 'category';
        $columns['status'] = 'status';
        $columns['price'] = 'price';
        $columns['supplier'] = 'supplier';
        $columns['is_active'] = 'is_active';
        
        return $columns;
    }

    /**
     * Add category filter dropdown to products list
     * 
     * @since 1.0.0
     */
    public function add_category_filter() {
        global $typenow;
        
        if ($typenow === self::POST_TYPE) {
            $taxonomy = 'utilitysign_product_category';
            $tax = get_taxonomy($taxonomy);
            
            if ($tax) {
                $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
                wp_dropdown_categories([
                    'show_option_all' => sprintf(__('All %s', 'utilitysign'), $tax->label),
                    'taxonomy' => $taxonomy,
                    'name' => $taxonomy,
                    'orderby' => 'name',
                    'selected' => $selected,
                    'show_count' => true,
                    'hide_empty' => false,
                    'value_field' => 'slug',
                ]);
            }
        }
    }

    /**
     * Filter products by category
     * 
     * @since 1.0.0
     * @param WP_Query $query
     */
    public function filter_by_category($query) {
        global $pagenow;
        
        if ($pagenow === 'edit.php' && isset($_GET['utilitysign_product_category']) && $_GET['utilitysign_product_category'] !== '') {
            $query->set('utilitysign_product_category', $_GET['utilitysign_product_category']);
        }
    }

    /**
     * Get products by category
     * 
     * @since 1.0.0
     * @param string $category_slug
     * @param array $args
     * @return WP_Query
     */
    public function get_products_by_category($category_slug, $args = []) {
        $default_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'utilitysign_product_category',
                    'field' => 'slug',
                    'terms' => $category_slug,
                ],
            ],
        ];
        
        $args = wp_parse_args($args, $default_args);
        return new \WP_Query($args);
    }

    /**
     * Get all product categories
     * 
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_product_categories($args = []) {
        $default_args = [
            'taxonomy' => 'utilitysign_product_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args($args, $default_args);
        return get_terms($args);
    }

    /**
     * Get products by tag
     * 
     * @since 1.0.0
     * @param string $tag_slug
     * @param array $args
     * @return WP_Query
     */
    public function get_products_by_tag($tag_slug, $args = []) {
        $default_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'utilitysign_product_tag',
                    'field' => 'slug',
                    'terms' => $tag_slug,
                ],
            ],
        ];
        
        $args = wp_parse_args($args, $default_args);
        return new \WP_Query($args);
    }

    /**
     * Get all product tags
     * 
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_product_tags($args = []) {
        $default_args = [
            'taxonomy' => 'utilitysign_product_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args($args, $default_args);
        return get_terms($args);
    }

    /**
     * Get products with advanced filtering
     * 
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_products_with_filters($args = []) {
        $default_args = [
            'category' => '',
            'tag' => '',
            'supplier' => '',
            'price_min' => '',
            'price_max' => '',
            'featured' => '',
            'status' => 'publish',
            'posts_per_page' => 10,
            'paged' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $default_args);

        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => $args['status'],
            'posts_per_page' => $args['posts_per_page'],
            'paged' => $args['paged'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_query' => [],
            'tax_query' => []
        ];

        // Category filter
        if (!empty($args['category'])) {
            $query_args['tax_query'][] = [
                'taxonomy' => 'utilitysign_product_category',
                'field'    => 'slug',
                'terms'    => $args['category'],
            ];
        }

        // Tag filter
        if (!empty($args['tag'])) {
            $query_args['tax_query'][] = [
                'taxonomy' => 'utilitysign_product_tag',
                'field'    => 'slug',
                'terms'    => $args['tag'],
            ];
        }

        // Supplier filter
        if (!empty($args['supplier'])) {
            $query_args['meta_query'][] = [
                'key' => '_product_supplier_id',
                'value' => $args['supplier'],
                'compare' => '='
            ];
        }

        // Price range filter
        if (!empty($args['price_min']) || !empty($args['price_max'])) {
            $price_query = [
                'key' => '_product_base_price',
                'type' => 'NUMERIC'
            ];

            if (!empty($args['price_min']) && !empty($args['price_max'])) {
                $price_query['value'] = [$args['price_min'], $args['price_max']];
                $price_query['compare'] = 'BETWEEN';
            } elseif (!empty($args['price_min'])) {
                $price_query['value'] = $args['price_min'];
                $price_query['compare'] = '>=';
            } elseif (!empty($args['price_max'])) {
                $price_query['value'] = $args['price_max'];
                $price_query['compare'] = '<=';
            }

            $query_args['meta_query'][] = $price_query;
        }

        // Featured filter
        if ($args['featured'] !== '') {
            $query_args['meta_query'][] = [
                'key' => '_product_featured',
                'value' => $args['featured'] ? '1' : '0',
                'compare' => '='
            ];
        }

        // Set relation for multiple tax queries
        if (count($query_args['tax_query']) > 1) {
            $query_args['tax_query']['relation'] = 'AND';
        }

        $query = new \WP_Query($query_args);

        return [
            'products' => $query->posts,
            'total' => $query->found_posts,
            'max_pages' => $query->max_num_pages
        ];
    }

    /**
     * Get category hierarchy
     * 
     * @since 1.0.0
     * @param int $parent_id
     * @return array
     */
    public function get_category_hierarchy($parent_id = 0) {
        $categories = get_terms([
            'taxonomy' => 'utilitysign_product_category',
            'parent' => $parent_id,
            'hide_empty' => false,
        ]);

        $hierarchy = [];
        foreach ($categories as $category) {
            $hierarchy[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->count,
                'children' => $this->get_category_hierarchy($category->term_id)
            ];
        }

        return $hierarchy;
    }

    /**
     * Get products by multiple categories
     * 
     * @since 1.0.0
     * @param array $category_ids
     * @param string $relation
     * @return array
     */
    public function get_products_by_categories($category_ids, $relation = 'IN') {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'utilitysign_product_category',
                    'field'    => 'term_id',
                    'terms'    => $category_ids,
                    'operator' => $relation,
                ],
            ],
        ]);

        return $query->posts;
    }

    /**
     * Get category statistics
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_category_statistics() {
        $categories = get_terms([
            'taxonomy' => 'utilitysign_product_category',
            'hide_empty' => false,
        ]);

        $stats = [];
        foreach ($categories as $category) {
            $stats[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
                'parent' => $category->parent,
                'description' => $category->description
            ];
        }

        return $stats;
    }
}