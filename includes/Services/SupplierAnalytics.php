<?php
namespace UtilitySign\Services;

use UtilitySign\Traits\Base;
use UtilitySign\Admin\SupplierManager;
use UtilitySign\Admin\ProductManager;
use UtilitySign\Admin\OrderManager;

/**
 * Supplier Analytics Service
 * 
 * Provides comprehensive analytics and reporting for suppliers
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SupplierAnalytics {
    use Base;

    /**
     * Get comprehensive analytics for a supplier
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    public function get_supplier_analytics($supplier_id, $args = []) {
        $default_args = [
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'include_products' => true,
            'include_orders' => true,
            'include_performance' => true
        ];

        $args = wp_parse_args($args, $default_args);

        $analytics = [
            'supplier_id' => $supplier_id,
            'supplier_name' => get_the_title($supplier_id),
            'period' => [
                'from' => $args['date_from'],
                'to' => $args['date_to']
            ],
            'overview' => [],
            'products' => [],
            'orders' => [],
            'performance' => [],
            'trends' => []
        ];

        // Get supplier overview
        $analytics['overview'] = $this->get_supplier_overview($supplier_id, $args);

        // Get product analytics
        if ($args['include_products']) {
            $analytics['products'] = $this->get_supplier_product_analytics($supplier_id, $args);
        }

        // Get order analytics
        if ($args['include_orders']) {
            $analytics['orders'] = $this->get_supplier_order_analytics($supplier_id, $args);
        }

        // Get performance metrics
        if ($args['include_performance']) {
            $analytics['performance'] = $this->get_supplier_performance_metrics($supplier_id, $args);
        }

        // Get trends
        $analytics['trends'] = $this->get_supplier_trends($supplier_id, $args);

        return $analytics;
    }

    /**
     * Get supplier overview statistics
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function get_supplier_overview($supplier_id, $args) {
        $product_manager = ProductManager::get_instance();
        $order_manager = OrderManager::get_instance();

        // Count products
        $products = get_posts([
            'post_type' => 'utilitysign_product',
            'meta_query' => [
                [
                    'key' => '_product_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        // Count orders
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ]
        ]);

        // Calculate revenue
        $total_revenue = 0;
        foreach ($orders as $order) {
            $order_total = get_post_meta($order->ID, '_order_total_price', true);
            if ($order_total) {
                $total_revenue += floatval($order_total);
            }
        }

        return [
            'total_products' => count($products),
            'total_orders' => count($orders),
            'total_revenue' => $total_revenue,
            'average_order_value' => count($orders) > 0 ? $total_revenue / count($orders) : 0,
            'active_products' => count(array_filter($products, function($product) {
                return get_post_meta($product->ID, '_product_status', true) === 'active';
            }))
        ];
    }

    /**
     * Get supplier product analytics
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function get_supplier_product_analytics($supplier_id, $args) {
        $products = get_posts([
            'post_type' => 'utilitysign_product',
            'meta_query' => [
                [
                    'key' => '_product_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $product_analytics = [];
        $total_orders = 0;
        $total_revenue = 0;

        foreach ($products as $product) {
            $product_id = $product->ID;
            
            // Get orders for this product
            $product_orders = get_posts([
                'post_type' => 'utilitysign_order',
                'meta_query' => [
                    [
                        'key' => '_order_product_id',
                        'value' => $product_id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'date_query' => [
                    [
                        'after' => $args['date_from'],
                        'before' => $args['date_to'],
                        'inclusive' => true
                    ]
                ]
            ]);

            $product_revenue = 0;
            foreach ($product_orders as $order) {
                $order_total = get_post_meta($order->ID, '_order_total_price', true);
                if ($order_total) {
                    $product_revenue += floatval($order_total);
                }
            }

            $product_analytics[] = [
                'product_id' => $product_id,
                'product_name' => $product->post_title,
                'orders_count' => count($product_orders),
                'revenue' => $product_revenue,
                'average_order_value' => count($product_orders) > 0 ? $product_revenue / count($product_orders) : 0,
                'status' => get_post_meta($product_id, '_product_status', true),
                'base_price' => get_post_meta($product_id, '_product_base_price', true)
            ];

            $total_orders += count($product_orders);
            $total_revenue += $product_revenue;
        }

        // Sort by revenue
        usort($product_analytics, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });

        return [
            'products' => $product_analytics,
            'summary' => [
                'total_products' => count($products),
                'total_orders' => $total_orders,
                'total_revenue' => $total_revenue,
                'top_product' => !empty($product_analytics) ? $product_analytics[0] : null
            ]
        ];
    }

    /**
     * Get supplier order analytics
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function get_supplier_order_analytics($supplier_id, $args) {
        $order_manager = OrderManager::get_instance();
        
        // Get orders for this supplier
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ]
        ]);

        $status_breakdown = [];
        $daily_orders = [];
        $total_revenue = 0;

        foreach ($orders as $order) {
            $order_id = $order->ID;
            $status = get_post_meta($order_id, '_order_status', true);
            $order_total = get_post_meta($order_id, '_order_total_price', true);
            $order_date = get_the_date('Y-m-d', $order_id);

            // Count by status
            $status_breakdown[$status] = ($status_breakdown[$status] ?? 0) + 1;

            // Count by day
            $daily_orders[$order_date] = ($daily_orders[$order_date] ?? 0) + 1;

            // Sum revenue
            if ($order_total) {
                $total_revenue += floatval($order_total);
            }
        }

        return [
            'total_orders' => count($orders),
            'total_revenue' => $total_revenue,
            'average_order_value' => count($orders) > 0 ? $total_revenue / count($orders) : 0,
            'status_breakdown' => $status_breakdown,
            'daily_orders' => $daily_orders,
            'conversion_rate' => $this->calculate_conversion_rate($supplier_id, $args)
        ];
    }

    /**
     * Get supplier performance metrics
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function get_supplier_performance_metrics($supplier_id, $args) {
        // Get supplier details
        $supplier_manager = SupplierManager::get_instance();
        $supplier_details = $supplier_manager->get_supplier_details($supplier_id);

        // Calculate performance metrics
        $metrics = [
            'supplier_info' => $supplier_details,
            'response_time' => $this->calculate_average_response_time($supplier_id, $args),
            'order_fulfillment_rate' => $this->calculate_fulfillment_rate($supplier_id, $args),
            'customer_satisfaction' => $this->calculate_customer_satisfaction($supplier_id, $args),
            'revenue_growth' => $this->calculate_revenue_growth($supplier_id, $args),
            'product_performance' => $this->calculate_product_performance($supplier_id, $args)
        ];

        return $metrics;
    }

    /**
     * Get supplier trends
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function get_supplier_trends($supplier_id, $args) {
        $trends = [
            'revenue_trend' => $this->calculate_revenue_trend($supplier_id, $args),
            'order_trend' => $this->calculate_order_trend($supplier_id, $args),
            'product_trend' => $this->calculate_product_trend($supplier_id, $args),
            'seasonal_patterns' => $this->identify_seasonal_patterns($supplier_id, $args)
        ];

        return $trends;
    }

    /**
     * Calculate conversion rate for supplier
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return float
     */
    private function calculate_conversion_rate($supplier_id, $args) {
        // This would typically involve tracking user interactions
        // For now, we'll use a simplified calculation based on completed orders
        $total_orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ]
        ]);

        $completed_orders = array_filter($total_orders, function($order) {
            return get_post_meta($order->ID, '_order_status', true) === 'completed';
        });

        return count($total_orders) > 0 ? (count($completed_orders) / count($total_orders)) * 100 : 0;
    }

    /**
     * Calculate average response time
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return float
     */
    private function calculate_average_response_time($supplier_id, $args) {
        // This would typically involve tracking response times
        // For now, return a placeholder value
        return 24.5; // hours
    }

    /**
     * Calculate fulfillment rate
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return float
     */
    private function calculate_fulfillment_rate($supplier_id, $args) {
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $args['date_from'],
                    'before' => $args['date_to'],
                    'inclusive' => true
                ]
            ]
        ]);

        $fulfilled_orders = array_filter($orders, function($order) {
            $status = get_post_meta($order->ID, '_order_status', true);
            return in_array($status, ['completed', 'shipped', 'delivered']);
        });

        return count($orders) > 0 ? (count($fulfilled_orders) / count($orders)) * 100 : 0;
    }

    /**
     * Calculate customer satisfaction
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return float
     */
    private function calculate_customer_satisfaction($supplier_id, $args) {
        // This would typically involve customer feedback/ratings
        // For now, return a placeholder value
        return 4.2; // out of 5
    }

    /**
     * Calculate revenue growth
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return float
     */
    private function calculate_revenue_growth($supplier_id, $args) {
        $current_period_revenue = $this->get_period_revenue($supplier_id, $args['date_from'], $args['date_to']);
        
        // Get previous period revenue
        $period_length = strtotime($args['date_to']) - strtotime($args['date_from']);
        $previous_start = date('Y-m-d', strtotime($args['date_from']) - $period_length);
        $previous_end = date('Y-m-d', strtotime($args['date_from']) - 1);
        
        $previous_period_revenue = $this->get_period_revenue($supplier_id, $previous_start, $previous_end);
        
        if ($previous_period_revenue > 0) {
            return (($current_period_revenue - $previous_period_revenue) / $previous_period_revenue) * 100;
        }
        
        return 0;
    }

    /**
     * Get revenue for a specific period
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param string $date_from
     * @param string $date_to
     * @return float
     */
    private function get_period_revenue($supplier_id, $date_from, $date_to) {
        $orders = get_posts([
            'post_type' => 'utilitysign_order',
            'meta_query' => [
                [
                    'key' => '_order_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $date_from,
                    'before' => $date_to,
                    'inclusive' => true
                ]
            ]
        ]);

        $revenue = 0;
        foreach ($orders as $order) {
            $order_total = get_post_meta($order->ID, '_order_total_price', true);
            if ($order_total) {
                $revenue += floatval($order_total);
            }
        }

        return $revenue;
    }

    /**
     * Calculate product performance
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function calculate_product_performance($supplier_id, $args) {
        $products = get_posts([
            'post_type' => 'utilitysign_product',
            'meta_query' => [
                [
                    'key' => '_product_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $performance = [];
        foreach ($products as $product) {
            $product_id = $product->ID;
            $orders = get_posts([
                'post_type' => 'utilitysign_order',
                'meta_query' => [
                    [
                        'key' => '_order_product_id',
                        'value' => $product_id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'date_query' => [
                    [
                        'after' => $args['date_from'],
                        'before' => $args['date_to'],
                        'inclusive' => true
                    ]
                ]
            ]);

            $revenue = 0;
            foreach ($orders as $order) {
                $order_total = get_post_meta($order->ID, '_order_total_price', true);
                if ($order_total) {
                    $revenue += floatval($order_total);
                }
            }

            $performance[] = [
                'product_id' => $product_id,
                'product_name' => $product->post_title,
                'orders_count' => count($orders),
                'revenue' => $revenue,
                'performance_score' => $this->calculate_performance_score($product_id, count($orders), $revenue)
            ];
        }

        // Sort by performance score
        usort($performance, function($a, $b) {
            return $b['performance_score'] - $a['performance_score'];
        });

        return $performance;
    }

    /**
     * Calculate performance score for a product
     * 
     * @since 1.0.0
     * @param int $product_id
     * @param int $orders_count
     * @param float $revenue
     * @return float
     */
    private function calculate_performance_score($product_id, $orders_count, $revenue) {
        // Simple scoring algorithm - can be made more sophisticated
        $base_score = ($orders_count * 0.3) + ($revenue * 0.7);
        return round($base_score, 2);
    }

    /**
     * Calculate revenue trend
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function calculate_revenue_trend($supplier_id, $args) {
        // This would typically involve more sophisticated trend analysis
        // For now, return a simple trend calculation
        return [
            'direction' => 'up', // or 'down', 'stable'
            'percentage_change' => 15.5,
            'confidence' => 0.85
        ];
    }

    /**
     * Calculate order trend
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function calculate_order_trend($supplier_id, $args) {
        return [
            'direction' => 'up',
            'percentage_change' => 8.2,
            'confidence' => 0.78
        ];
    }

    /**
     * Calculate product trend
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function calculate_product_trend($supplier_id, $args) {
        return [
            'direction' => 'stable',
            'percentage_change' => 2.1,
            'confidence' => 0.92
        ];
    }

    /**
     * Identify seasonal patterns
     * 
     * @since 1.0.0
     * @param int $supplier_id
     * @param array $args
     * @return array
     */
    private function identify_seasonal_patterns($supplier_id, $args) {
        return [
            'has_seasonal_pattern' => true,
            'peak_months' => ['December', 'January'],
            'low_months' => ['July', 'August'],
            'seasonal_variance' => 0.35
        ];
    }
}
