<?php

namespace UtilitySign\Admin;

use UtilitySign\Traits\Base;

/**
 * Order Manager for UtilitySign Plugin
 * Handles custom post type for orders and admin interface
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class OrderManager {
    use Base;

    /**
     * Custom post type name
     */
    const POST_TYPE = 'utilitysign_order';

    /**
     * Initialize the order manager
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'display_custom_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'make_columns_sortable']);
    }

    /**
     * Register custom post type for orders
     * 
     * @since 1.0.0
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Orders', 'utilitysign'),
            'singular_name' => __('Order', 'utilitysign'),
            'menu_name' => __('Orders', 'utilitysign'),
            'add_new' => __('Add New Order', 'utilitysign'),
            'add_new_item' => __('Add New Order', 'utilitysign'),
            'edit_item' => __('Edit Order', 'utilitysign'),
            'new_item' => __('New Order', 'utilitysign'),
            'view_item' => __('View Order', 'utilitysign'),
            'search_items' => __('Search Orders', 'utilitysign'),
            'not_found' => __('No orders found', 'utilitysign'),
            'not_found_in_trash' => __('No orders found in trash', 'utilitysign'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 10,
            'menu_icon' => 'dashicons-cart',
            'supports' => ['title'],
            'show_in_rest' => true,
            'rest_base' => 'orders',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for order data
     * 
     * @since 1.0.0
     */
    public function add_meta_boxes() {
        add_meta_box(
            'order_details',
            __('Order Details', 'utilitysign'),
            [$this, 'order_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'order_customer',
            __('Customer Information', 'utilitysign'),
            [$this, 'order_customer_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'order_status',
            __('Order Status', 'utilitysign'),
            [$this, 'order_status_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Order details meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function order_details_meta_box($post) {
        $product_id = get_post_meta($post->ID, '_order_product_id', true);
        $product_title = get_post_meta($post->ID, '_order_product_title', true);
        $selected_variation = get_post_meta($post->ID, '_order_selected_variation', true);
        $total_price = get_post_meta($post->ID, '_order_total_price', true);
        $currency = get_post_meta($post->ID, '_order_currency', true);
        $billing_cycle = get_post_meta($post->ID, '_order_billing_cycle', true);
        $custom_fields = get_post_meta($post->ID, '_order_custom_fields', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Product', 'utilitysign'); ?></label></th>
                <td>
                    <strong><?php echo esc_html($product_title); ?></strong>
                    <?php if ($product_id): ?>
                        <br />
                        <a href="<?php echo get_edit_post_link($product_id); ?>" target="_blank">
                            <?php _e('View Product', 'utilitysign'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($selected_variation): ?>
            <tr>
                <th><label><?php _e('Selected Variation', 'utilitysign'); ?></label></th>
                <td><?php echo esc_html($selected_variation); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label><?php _e('Total Price', 'utilitysign'); ?></label></th>
                <td>
                    <strong><?php echo esc_html($total_price . ' ' . $currency); ?></strong>
                    <br />
                    <span class="description"><?php echo esc_html(ucfirst($billing_cycle)); ?></span>
                </td>
            </tr>
            <?php if (is_array($custom_fields) && !empty($custom_fields)): ?>
            <tr>
                <th><label><?php _e('Additional Information', 'utilitysign'); ?></label></th>
                <td>
                    <table class="widefat">
                        <?php foreach ($custom_fields as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong></td>
                            <td><?php echo esc_html($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Order customer meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function order_customer_meta_box($post) {
        $customer_name = get_post_meta($post->ID, '_order_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_order_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_order_customer_phone', true);
        $terms_accepted = get_post_meta($post->ID, '_order_terms_accepted', true);
        $created_at = get_post_meta($post->ID, '_order_created_at', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Customer Name', 'utilitysign'); ?></label></th>
                <td><?php echo esc_html($customer_name); ?></td>
            </tr>
            <tr>
                <th><label><?php _e('Email', 'utilitysign'); ?></label></th>
                <td>
                    <a href="mailto:<?php echo esc_attr($customer_email); ?>">
                        <?php echo esc_html($customer_email); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Phone', 'utilitysign'); ?></label></th>
                <td>
                    <a href="tel:<?php echo esc_attr($customer_phone); ?>">
                        <?php echo esc_html($customer_phone); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Terms Accepted', 'utilitysign'); ?></label></th>
                <td><?php echo $terms_accepted === '1' ? '✓ ' . __('Yes', 'utilitysign') : '✗ ' . __('No', 'utilitysign'); ?></td>
            </tr>
            <?php if ($created_at): ?>
            <tr>
                <th><label><?php _e('Order Date', 'utilitysign'); ?></label></th>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_at))); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Order status meta box
     * 
     * @since 1.0.0
     * @param WP_Post $post
     */
    public function order_status_meta_box($post) {
        wp_nonce_field('order_status_nonce', 'order_status_nonce');
        
        $status = get_post_meta($post->ID, '_order_status', true);

        ?>
        <div class="order-status-box">
            <label for="order_status"><strong><?php _e('Status', 'utilitysign'); ?></strong></label>
            <select id="order_status" name="order_status" class="widefat" style="margin-top: 10px;">
                <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'utilitysign'); ?></option>
                <option value="processing" <?php selected($status, 'processing'); ?>><?php _e('Processing', 'utilitysign'); ?></option>
                <option value="awaiting_signature" <?php selected($status, 'awaiting_signature'); ?>><?php _e('Awaiting Signature', 'utilitysign'); ?></option>
                <option value="signed" <?php selected($status, 'signed'); ?>><?php _e('Signed', 'utilitysign'); ?></option>
                <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed', 'utilitysign'); ?></option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'utilitysign'); ?></option>
                <option value="failed" <?php selected($status, 'failed'); ?>><?php _e('Failed', 'utilitysign'); ?></option>
            </select>

            <div style="margin-top: 15px;">
                <button type="submit" class="button button-primary button-large" style="width: 100%;">
                    <?php _e('Update Status', 'utilitysign'); ?>
                </button>
            </div>
        </div>

        <style>
        .order-status-box {
            padding: 10px;
        }
        </style>
        <?php
    }

    /**
     * Add custom columns to orders list
     * 
     * @since 1.0.0
     * @param array $columns
     * @return array
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['customer'] = __('Customer', 'utilitysign');
        $new_columns['product'] = __('Product', 'utilitysign');
        $new_columns['total'] = __('Total', 'utilitysign');
        $new_columns['status'] = __('Status', 'utilitysign');
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
            case 'customer':
                $customer_name = get_post_meta($post_id, '_order_customer_name', true);
                $customer_email = get_post_meta($post_id, '_order_customer_email', true);
                echo '<strong>' . esc_html($customer_name) . '</strong><br />';
                echo '<a href="mailto:' . esc_attr($customer_email) . '">' . esc_html($customer_email) . '</a>';
                break;
            
            case 'product':
                $product_title = get_post_meta($post_id, '_order_product_title', true);
                $selected_variation = get_post_meta($post_id, '_order_selected_variation', true);
                echo esc_html($product_title);
                if ($selected_variation) {
                    echo '<br /><span class="description">' . esc_html($selected_variation) . '</span>';
                }
                break;
            
            case 'total':
                $total_price = get_post_meta($post_id, '_order_total_price', true);
                $currency = get_post_meta($post_id, '_order_currency', true);
                echo '<strong>' . esc_html($total_price . ' ' . $currency) . '</strong>';
                break;
            
            case 'status':
                $status = get_post_meta($post_id, '_order_status', true);
                $status_labels = [
                    'pending' => __('Pending', 'utilitysign'),
                    'processing' => __('Processing', 'utilitysign'),
                    'awaiting_signature' => __('Awaiting Signature', 'utilitysign'),
                    'signed' => __('Signed', 'utilitysign'),
                    'completed' => __('Completed', 'utilitysign'),
                    'cancelled' => __('Cancelled', 'utilitysign'),
                    'failed' => __('Failed', 'utilitysign'),
                ];
                $status_class = 'status-' . $status;
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_labels[$status] ?? ucfirst($status)) . '</span>';
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
        $columns['customer'] = 'customer';
        $columns['product'] = 'product';
        $columns['total'] = 'total';
        $columns['status'] = 'status';
        
        return $columns;
    }

    /**
     * Get order analytics data
     * 
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_order_analytics($args = []) {
        $default_args = [
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'supplier_id' => null,
            'status' => null
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [],
            'date_query' => [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ]
        ];
        
        if ($args['supplier_id']) {
            $query_args['meta_query'][] = [
                'key' => '_order_supplier_id',
                'value' => $args['supplier_id'],
                'compare' => '='
            ];
        }
        
        if ($args['status']) {
            $query_args['meta_query'][] = [
                'key' => '_order_status',
                'value' => $args['status'],
                'compare' => '='
            ];
        }
        
        $query = new \WP_Query($query_args);
        
        $analytics = [
            'total_orders' => $query->found_posts,
            'total_revenue' => 0,
            'status_breakdown' => [],
            'daily_orders' => [],
            'top_products' => [],
            'conversion_rate' => 0
        ];
        
        if ($query->have_posts()) {
            $revenue = 0;
            $status_counts = [];
            $product_counts = [];
            $daily_counts = [];
            
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Calculate revenue
                $order_total = get_post_meta($post_id, '_order_total_price', true);
                if ($order_total) {
                    $revenue += floatval($order_total);
                }
                
                // Count by status
                $status = get_post_meta($post_id, '_order_status', true);
                $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
                
                // Count by product
                $product_id = get_post_meta($post_id, '_order_product_id', true);
                if ($product_id) {
                    $product_counts[$product_id] = ($product_counts[$product_id] ?? 0) + 1;
                }
                
                // Count by day
                $order_date = get_the_date('Y-m-d');
                $daily_counts[$order_date] = ($daily_counts[$order_date] ?? 0) + 1;
            }
            
            wp_reset_postdata();
            
            $analytics['total_revenue'] = $revenue;
            $analytics['status_breakdown'] = $status_counts;
            $analytics['daily_orders'] = $daily_counts;
            
            // Get top products
            arsort($product_counts);
            $analytics['top_products'] = array_slice($product_counts, 0, 5, true);
            
            // Calculate conversion rate (completed orders / total orders)
            $completed_orders = $status_counts['completed'] ?? 0;
            $analytics['conversion_rate'] = $analytics['total_orders'] > 0 ? 
                round(($completed_orders / $analytics['total_orders']) * 100, 2) : 0;
        }
        
        return $analytics;
    }

    /**
     * Get orders by date range
     * 
     * @since 1.0.0
     * @param string $date_from
     * @param string $date_to
     * @param array $args
     * @return WP_Query
     */
    public function get_orders_by_date_range($date_from, $date_to, $args = []) {
        $default_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'after' => $date_from,
                    'before' => $date_to,
                    'inclusive' => true
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $default_args);
        return new \WP_Query($args);
    }

    /**
     * Get orders by status
     * 
     * @since 1.0.0
     * @param string $status
     * @param array $args
     * @return WP_Query
     */
    public function get_orders_by_status($status, $args = []) {
        $default_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_order_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $default_args);
        return new \WP_Query($args);
    }

    /**
     * Get orders by supplier
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return WP_Query
     */
    public function get_orders_by_supplier($supplier_id, $args = []) {
        $default_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $default_args);
        return new \WP_Query($args);
    }

    /**
     * Update order status
     * 
     * @since 1.0.0
     * @param int $order_id
     * @param string $status
     * @return bool
     */
    public function update_order_status($order_id, $status) {
        $valid_statuses = [
            'pending',
            'processing',
            'awaiting_signature',
            'signed',
            'completed',
            'cancelled',
            'failed'
        ];
        
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        $updated = update_post_meta($order_id, '_order_status', $status);
        
        if ($updated) {
            // Update status change timestamp
            update_post_meta($order_id, '_order_status_updated_at', current_time('mysql'));
            
            // Log status change
            $this->log_status_change($order_id, $status);
        }
        
        return $updated;
    }

    /**
     * Log order status change
     * 
     * @since 1.0.0
     * @param int $order_id
     * @param string $new_status
     */
    private function log_status_change($order_id, $new_status) {
        $status_log = get_post_meta($order_id, '_order_status_log', true);
        if (!is_array($status_log)) {
            $status_log = [];
        }
        
        $status_log[] = [
            'status' => $new_status,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ];
        
        update_post_meta($order_id, '_order_status_log', $status_log);
    }

    /**
     * Get order status history
     * 
     * @since 1.0.0
     * @param int $order_id
     * @return array
     */
    public function get_order_status_history($order_id) {
        return get_post_meta($order_id, '_order_status_log', true) ?: [];
    }

    /**
     * Export orders to CSV
     * 
     * @since 1.0.0
     * @param array $args
     * @return string
     */
    public function export_orders_csv($args = []) {
        $default_args = [
            'date_from' => null,
            'date_to' => null,
            'status' => null,
            'supplier_id' => null
        ];
        
        $args = wp_parse_args($args, $default_args);
        
        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => []
        ];
        
        if ($args['date_from'] && $args['date_to']) {
            $query_args['date_query'] = [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ];
        }
        
        if ($args['status']) {
            $query_args['meta_query'][] = [
                'key' => '_order_status',
                'value' => $args['status'],
                'compare' => '='
            ];
        }
        
        if ($args['supplier_id']) {
            $query_args['meta_query'][] = [
                'key' => '_order_supplier_id',
                'value' => $args['supplier_id'],
                'compare' => '='
            ];
        }
        
        $query = new \WP_Query($query_args);
        
        $csv_data = [];
        $csv_data[] = [
            'Order ID',
            'Customer Name',
            'Customer Email',
            'Product',
            'Total Price',
            'Currency',
            'Status',
            'Order Date',
            'Supplier'
        ];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $csv_data[] = [
                    get_the_title(),
                    get_post_meta($post_id, '_order_customer_name', true),
                    get_post_meta($post_id, '_order_customer_email', true),
                    get_post_meta($post_id, '_order_product_title', true),
                    get_post_meta($post_id, '_order_total_price', true),
                    get_post_meta($post_id, '_order_currency', true),
                    get_post_meta($post_id, '_order_status', true),
                    get_the_date('Y-m-d H:i:s'),
                    get_post_meta($post_id, '_order_supplier_name', true)
                ];
            }
            wp_reset_postdata();
        }
        
        // Generate CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        return $csv_content;
    }
}

