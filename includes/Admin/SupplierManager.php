<?php
/**
 * Supplier Management
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;

/**
 * Supplier Manager class
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SupplierManager {
    use Base;

    /**
     * Post type name
     * 
     * @since 1.0.0
     * @var string
     */
    const POST_TYPE = 'utilitysign_supplier';

    /**
     * Initialize the supplier manager
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_supplier_meta']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'display_custom_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_columns_sortable']);
    }

    /**
     * Register supplier post type
     * 
     * @since 1.0.0
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Suppliers', 'utilitysign'),
            'singular_name' => __('Supplier', 'utilitysign'),
            'menu_name' => __('Suppliers', 'utilitysign'),
            'add_new' => __('Add New Supplier', 'utilitysign'),
            'add_new_item' => __('Add New Supplier', 'utilitysign'),
            'edit_item' => __('Edit Supplier', 'utilitysign'),
            'new_item' => __('New Supplier', 'utilitysign'),
            'view_item' => __('View Supplier', 'utilitysign'),
            'search_items' => __('Search Suppliers', 'utilitysign'),
            'not_found' => __('No suppliers found', 'utilitysign'),
            'not_found_in_trash' => __('No suppliers found in trash', 'utilitysign'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Hidden from admin menu per plan - managed in SaaS admin
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-businessman',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes
     * 
     * @since 1.0.0
     */
    public function add_meta_boxes() {
        add_meta_box(
            'supplier_details',
            __('Supplier Details', 'utilitysign'),
            [$this, 'supplier_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'supplier_branding',
            __('Branding & Customization', 'utilitysign'),
            [$this, 'supplier_branding_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'supplier_settings',
            __('Supplier Settings', 'utilitysign'),
            [$this, 'supplier_settings_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'supplier_analytics',
            __('Analytics & Reporting', 'utilitysign'),
            [$this, 'supplier_analytics_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Supplier details meta box
     * 
     * @since 1.0.0
     * @param \WP_Post $post
     */
    public function supplier_details_meta_box($post) {
        wp_nonce_field('supplier_meta_box', 'supplier_meta_box_nonce');

        $contact_email = get_post_meta($post->ID, '_supplier_contact_email', true);
        $contact_phone = get_post_meta($post->ID, '_supplier_contact_phone', true);
        $website = get_post_meta($post->ID, '_supplier_website', true);
        $address = get_post_meta($post->ID, '_supplier_address', true);
        $city = get_post_meta($post->ID, '_supplier_city', true);
        $postal_code = get_post_meta($post->ID, '_supplier_postal_code', true);
        $country = get_post_meta($post->ID, '_supplier_country', true);
        $tax_id = get_post_meta($post->ID, '_supplier_tax_id', true);
        $registration_number = get_post_meta($post->ID, '_supplier_registration_number', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="supplier_contact_email"><?php _e('Contact Email', 'utilitysign'); ?></label></th>
                <td>
                    <input type="email" id="supplier_contact_email" name="supplier_contact_email" value="<?php echo esc_attr($contact_email); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_contact_phone"><?php _e('Contact Phone', 'utilitysign'); ?></label></th>
                <td>
                    <input type="tel" id="supplier_contact_phone" name="supplier_contact_phone" value="<?php echo esc_attr($contact_phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_website"><?php _e('Website', 'utilitysign'); ?></label></th>
                <td>
                    <input type="url" id="supplier_website" name="supplier_website" value="<?php echo esc_attr($website); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_address"><?php _e('Address', 'utilitysign'); ?></label></th>
                <td>
                    <textarea id="supplier_address" name="supplier_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_city"><?php _e('City', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="supplier_city" name="supplier_city" value="<?php echo esc_attr($city); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_postal_code"><?php _e('Postal Code', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="supplier_postal_code" name="supplier_postal_code" value="<?php echo esc_attr($postal_code); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_country"><?php _e('Country', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="supplier_country" name="supplier_country" value="<?php echo esc_attr($country); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_tax_id"><?php _e('Tax ID', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="supplier_tax_id" name="supplier_tax_id" value="<?php echo esc_attr($tax_id); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="supplier_registration_number"><?php _e('Registration Number', 'utilitysign'); ?></label></th>
                <td>
                    <input type="text" id="supplier_registration_number" name="supplier_registration_number" value="<?php echo esc_attr($registration_number); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Supplier branding meta box
     * 
     * @since 1.0.0
     * @param \WP_Post $post
     */
    public function supplier_branding_meta_box($post) {
        $primary_color = get_post_meta($post->ID, '_supplier_primary_color', true);
        $secondary_color = get_post_meta($post->ID, '_supplier_secondary_color', true);
        $logo_url = get_post_meta($post->ID, '_supplier_logo_url', true);
        $favicon_url = get_post_meta($post->ID, '_supplier_favicon_url', true);
        $custom_css = get_post_meta($post->ID, '_supplier_custom_css', true);
        $custom_js = get_post_meta($post->ID, '_supplier_custom_js', true);
        $footer_text = get_post_meta($post->ID, '_supplier_footer_text', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="supplier_primary_color"><?php _e('Primary Color', 'utilitysign'); ?></label></th>
                <td>
                    <input type="color" id="supplier_primary_color" name="supplier_primary_color" value="<?php echo esc_attr($primary_color); ?>" />
                    <p class="description"><?php _e('Main brand color for this supplier', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_secondary_color"><?php _e('Secondary Color', 'utilitysign'); ?></label></th>
                <td>
                    <input type="color" id="supplier_secondary_color" name="supplier_secondary_color" value="<?php echo esc_attr($secondary_color); ?>" />
                    <p class="description"><?php _e('Secondary brand color for this supplier', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_logo_url"><?php _e('Logo URL', 'utilitysign'); ?></label></th>
                <td>
                    <input type="url" id="supplier_logo_url" name="supplier_logo_url" value="<?php echo esc_attr($logo_url); ?>" class="regular-text" />
                    <button type="button" class="button" id="upload-logo-btn"><?php _e('Upload Logo', 'utilitysign'); ?></button>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_favicon_url"><?php _e('Favicon URL', 'utilitysign'); ?></label></th>
                <td>
                    <input type="url" id="supplier_favicon_url" name="supplier_favicon_url" value="<?php echo esc_attr($favicon_url); ?>" class="regular-text" />
                    <button type="button" class="button" id="upload-favicon-btn"><?php _e('Upload Favicon', 'utilitysign'); ?></button>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_custom_css"><?php _e('Custom CSS', 'utilitysign'); ?></label></th>
                <td>
                    <textarea id="supplier_custom_css" name="supplier_custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                    <p class="description"><?php _e('Custom CSS for this supplier\'s pages', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_custom_js"><?php _e('Custom JavaScript', 'utilitysign'); ?></label></th>
                <td>
                    <textarea id="supplier_custom_js" name="supplier_custom_js" rows="10" class="large-text code"><?php echo esc_textarea($custom_js); ?></textarea>
                    <p class="description"><?php _e('Custom JavaScript for this supplier\'s pages', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_footer_text"><?php _e('Footer Text', 'utilitysign'); ?></label></th>
                <td>
                    <textarea id="supplier_footer_text" name="supplier_footer_text" rows="3" class="large-text"><?php echo esc_textarea($footer_text); ?></textarea>
                    <p class="description"><?php _e('Custom footer text for this supplier', 'utilitysign'); ?></p>
                </td>
            </tr>
        </table>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Media uploader for logo
            $('#upload-logo-btn').on('click', function(e) {
                e.preventDefault();
                var mediaUploader = wp.media({
                    title: '<?php _e('Choose Logo', 'utilitysign'); ?>',
                    button: {
                        text: '<?php _e('Choose Logo', 'utilitysign'); ?>'
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#supplier_logo_url').val(attachment.url);
                });

                mediaUploader.open();
            });

            // Media uploader for favicon
            $('#upload-favicon-btn').on('click', function(e) {
                e.preventDefault();
                var mediaUploader = wp.media({
                    title: '<?php _e('Choose Favicon', 'utilitysign'); ?>',
                    button: {
                        text: '<?php _e('Choose Favicon', 'utilitysign'); ?>'
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#supplier_favicon_url').val(attachment.url);
                });

                mediaUploader.open();
            });
        });
        </script>
        <?php
    }

    /**
     * Supplier settings meta box
     * 
     * @since 1.0.0
     * @param \WP_Post $post
     */
    public function supplier_settings_meta_box($post) {
        $api_endpoint = get_post_meta($post->ID, '_supplier_api_endpoint', true);
        $api_key = get_post_meta($post->ID, '_supplier_api_key', true);
        $webhook_url = get_post_meta($post->ID, '_supplier_webhook_url', true);
        $auto_approve_orders = get_post_meta($post->ID, '_supplier_auto_approve_orders', true);
        $email_notifications = get_post_meta($post->ID, '_supplier_email_notifications', true);
        $max_orders_per_day = get_post_meta($post->ID, '_supplier_max_orders_per_day', true);
        $commission_rate = get_post_meta($post->ID, '_supplier_commission_rate', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="supplier_api_endpoint"><?php _e('API Endpoint', 'utilitysign'); ?></label></th>
                <td>
                    <input type="url" id="supplier_api_endpoint" name="supplier_api_endpoint" value="<?php echo esc_attr($api_endpoint); ?>" class="regular-text" />
                    <p class="description"><?php _e('External API endpoint for this supplier', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_api_key"><?php _e('API Key', 'utilitysign'); ?></label></th>
                <td>
                    <input type="password" id="supplier_api_key" name="supplier_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                    <p class="description"><?php _e('API key for external integration', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_webhook_url"><?php _e('Webhook URL', 'utilitysign'); ?></label></th>
                <td>
                    <input type="url" id="supplier_webhook_url" name="supplier_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text" />
                    <p class="description"><?php _e('Webhook URL for order notifications', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_auto_approve_orders"><?php _e('Auto Approve Orders', 'utilitysign'); ?></label></th>
                <td>
                    <input type="checkbox" id="supplier_auto_approve_orders" name="supplier_auto_approve_orders" value="1" <?php checked($auto_approve_orders, '1'); ?> />
                    <label for="supplier_auto_approve_orders"><?php _e('Automatically approve orders from this supplier', 'utilitysign'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_email_notifications"><?php _e('Email Notifications', 'utilitysign'); ?></label></th>
                <td>
                    <input type="checkbox" id="supplier_email_notifications" name="supplier_email_notifications" value="1" <?php checked($email_notifications, '1'); ?> />
                    <label for="supplier_email_notifications"><?php _e('Send email notifications for orders', 'utilitysign'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_max_orders_per_day"><?php _e('Max Orders Per Day', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="supplier_max_orders_per_day" name="supplier_max_orders_per_day" value="<?php echo esc_attr($max_orders_per_day); ?>" min="0" class="small-text" />
                    <p class="description"><?php _e('Maximum number of orders per day (0 for unlimited)', 'utilitysign'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="supplier_commission_rate"><?php _e('Commission Rate (%)', 'utilitysign'); ?></label></th>
                <td>
                    <input type="number" id="supplier_commission_rate" name="supplier_commission_rate" value="<?php echo esc_attr($commission_rate); ?>" step="0.01" min="0" max="100" class="small-text" />
                    <p class="description"><?php _e('Commission rate for this supplier (0-100%)', 'utilitysign'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Supplier analytics meta box
     * 
     * @since 1.0.0
     * @param \WP_Post $post
     */
    public function supplier_analytics_meta_box($post) {
        $analytics = $this->get_supplier_analytics($post->ID);
        ?>
        <div class="supplier-analytics">
            <h4><?php _e('Order Statistics', 'utilitysign'); ?></h4>
            <p><strong><?php _e('Total Orders:', 'utilitysign'); ?></strong> <?php echo esc_html($analytics['total_orders']); ?></p>
            <p><strong><?php _e('Total Revenue:', 'utilitysign'); ?></strong> <?php echo esc_html(number_format($analytics['total_revenue'], 2)); ?> NOK</p>
            <p><strong><?php _e('Average Order Value:', 'utilitysign'); ?></strong> <?php echo esc_html(number_format($analytics['average_order_value'], 2)); ?> NOK</p>
            <p><strong><?php _e('Conversion Rate:', 'utilitysign'); ?></strong> <?php echo esc_html($analytics['conversion_rate']); ?>%</p>
            
            <h4><?php _e('Recent Activity', 'utilitysign'); ?></h4>
            <p><strong><?php _e('Last Order:', 'utilitysign'); ?></strong> <?php echo esc_html($analytics['last_order_date'] ? date('Y-m-d H:i', strtotime($analytics['last_order_date'])) : __('Never', 'utilitysign')); ?></p>
            <p><strong><?php _e('Orders This Month:', 'utilitysign'); ?></strong> <?php echo esc_html($analytics['orders_this_month']); ?></p>
        </div>
        <?php
    }

    /**
     * Add custom columns
     * 
     * @since 1.0.0
     * @param array $columns
     * @return array
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['contact_email'] = __('Contact Email', 'utilitysign');
        $new_columns['total_orders'] = __('Total Orders', 'utilitysign');
        $new_columns['total_revenue'] = __('Total Revenue', 'utilitysign');
        $new_columns['status'] = __('Status', 'utilitysign');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    /**
     * Display custom columns
     * 
     * @since 1.0.0
     * @param string $column
     * @param int $post_id
     */
    public function display_custom_columns($column, $post_id) {
        switch ($column) {
            case 'contact_email':
                $email = get_post_meta($post_id, '_supplier_contact_email', true);
                echo esc_html($email);
                break;

            case 'total_orders':
                $analytics = $this->get_supplier_analytics($post_id);
                echo esc_html($analytics['total_orders']);
                break;

            case 'total_revenue':
                $analytics = $this->get_supplier_analytics($post_id);
                echo esc_html(number_format($analytics['total_revenue'], 2)) . ' NOK';
                break;

            case 'status':
                $auto_approve = get_post_meta($post_id, '_supplier_auto_approve_orders', true);
                if ($auto_approve) {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;" title="' . esc_attr__('Auto Approve Enabled', 'utilitysign') . '"></span>';
                } else {
                    echo '<span class="dashicons dashicons-dismiss" style="color: red;" title="' . esc_attr__('Manual Approval Required', 'utilitysign') . '"></span>';
                }
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
        $columns['contact_email'] = 'contact_email';
        $columns['total_orders'] = 'total_orders';
        $columns['total_revenue'] = 'total_revenue';
        return $columns;
    }

    /**
     * Save supplier meta data
     * 
     * @since 1.0.0
     * @param int $post_id
     */
    public function save_supplier_meta($post_id) {
        if (!isset($_POST['supplier_meta_box_nonce']) || !wp_verify_nonce($_POST['supplier_meta_box_nonce'], 'supplier_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save supplier details
        $fields = [
            'supplier_contact_email',
            'supplier_contact_phone',
            'supplier_website',
            'supplier_address',
            'supplier_city',
            'supplier_postal_code',
            'supplier_country',
            'supplier_tax_id',
            'supplier_registration_number',
            'supplier_primary_color',
            'supplier_secondary_color',
            'supplier_logo_url',
            'supplier_favicon_url',
            'supplier_custom_css',
            'supplier_custom_js',
            'supplier_footer_text',
            'supplier_api_endpoint',
            'supplier_api_key',
            'supplier_webhook_url',
            'supplier_max_orders_per_day',
            'supplier_commission_rate'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if ($field === 'supplier_address' || $field === 'supplier_custom_css' || $field === 'supplier_custom_js' || $field === 'supplier_footer_text') {
                    $value = sanitize_textarea_field($_POST[$field]);
                }
                update_post_meta($post_id, '_' . $field, $value);
            }
        }

        // Save checkboxes
        $checkbox_fields = [
            'supplier_auto_approve_orders',
            'supplier_email_notifications'
        ];

        foreach ($checkbox_fields as $field) {
            $value = isset($_POST[$field]) ? '1' : '0';
            update_post_meta($post_id, '_' . $field, $value);
        }
    }

    /**
     * Get supplier analytics
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @return array
     */
    public function get_supplier_analytics($supplier_id) {
        $query_args = [
            'post_type' => 'utilitysign_order',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($query_args);
        $total_orders = $query->found_posts;
        $total_revenue = 0;
        $last_order_date = null;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $order_total = get_post_meta($post_id, '_order_total_price', true);
                if ($order_total) {
                    $total_revenue += floatval($order_total);
                }
                if (!$last_order_date) {
                    $last_order_date = get_the_date('Y-m-d H:i:s');
                }
            }
            wp_reset_postdata();
        }

        // Get orders this month
        $month_query_args = [
            'post_type' => 'utilitysign_order',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'after' => date('Y-m-01'),
                    'before' => date('Y-m-t'),
                    'inclusive' => true
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ]
        ];

        $month_query = new \WP_Query($month_query_args);
        $orders_this_month = $month_query->found_posts;

        return [
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'average_order_value' => $total_orders > 0 ? $total_revenue / $total_orders : 0,
            'conversion_rate' => 0, // This would need to be calculated based on your business logic
            'last_order_date' => $last_order_date,
            'orders_this_month' => $orders_this_month
        ];
    }

    /**
     * Get all suppliers
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_all_suppliers() {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
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
                    'title' => get_the_title(),
                    'contact_email' => get_post_meta(get_the_ID(), '_supplier_contact_email', true),
                    'logo_url' => get_post_meta(get_the_ID(), '_supplier_logo_url', true),
                    'primary_color' => get_post_meta(get_the_ID(), '_supplier_primary_color', true),
                    'secondary_color' => get_post_meta(get_the_ID(), '_supplier_secondary_color', true)
                ];
            }
            wp_reset_postdata();
        }

        return $suppliers;
    }

    /**
     * Get supplier by ID
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @return array|null
     */
    public function get_supplier($supplier_id) {
        $supplier = get_post($supplier_id);
        if (!$supplier || $supplier->post_type !== self::POST_TYPE) {
            return null;
        }

        return [
            'id' => $supplier->ID,
            'title' => $supplier->post_title,
            'content' => $supplier->post_content,
            'contact_email' => get_post_meta($supplier_id, '_supplier_contact_email', true),
            'contact_phone' => get_post_meta($supplier_id, '_supplier_contact_phone', true),
            'website' => get_post_meta($supplier_id, '_supplier_website', true),
            'address' => get_post_meta($supplier_id, '_supplier_address', true),
            'city' => get_post_meta($supplier_id, '_supplier_city', true),
            'postal_code' => get_post_meta($supplier_id, '_supplier_postal_code', true),
            'country' => get_post_meta($supplier_id, '_supplier_country', true),
            'tax_id' => get_post_meta($supplier_id, '_supplier_tax_id', true),
            'registration_number' => get_post_meta($supplier_id, '_supplier_registration_number', true),
            'primary_color' => get_post_meta($supplier_id, '_supplier_primary_color', true),
            'secondary_color' => get_post_meta($supplier_id, '_supplier_secondary_color', true),
            'logo_url' => get_post_meta($supplier_id, '_supplier_logo_url', true),
            'favicon_url' => get_post_meta($supplier_id, '_supplier_favicon_url', true),
            'custom_css' => get_post_meta($supplier_id, '_supplier_custom_css', true),
            'custom_js' => get_post_meta($supplier_id, '_supplier_custom_js', true),
            'footer_text' => get_post_meta($supplier_id, '_supplier_footer_text', true),
            'api_endpoint' => get_post_meta($supplier_id, '_supplier_api_endpoint', true),
            'api_key' => get_post_meta($supplier_id, '_supplier_api_key', true),
            'webhook_url' => get_post_meta($supplier_id, '_supplier_webhook_url', true),
            'auto_approve_orders' => get_post_meta($supplier_id, '_supplier_auto_approve_orders', true),
            'email_notifications' => get_post_meta($supplier_id, '_supplier_email_notifications', true),
            'max_orders_per_day' => get_post_meta($supplier_id, '_supplier_max_orders_per_day', true),
            'commission_rate' => get_post_meta($supplier_id, '_supplier_commission_rate', true)
        ];
    }
}