<?php
/**
 * Test suite for Phase 1C Enhanced Integration features
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

class TestPhase1CFeatures extends WP_UnitTestCase {
    
    private $product_manager;
    private $order_manager;
    private $supplier_manager;
    private $pricing_calculator;
    private $supplier_analytics;
    private $performance_optimizer;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->product_manager = \UtilitySign\Admin\ProductManager::get_instance();
        $this->order_manager = \UtilitySign\Admin\OrderManager::get_instance();
        $this->supplier_manager = \UtilitySign\Admin\SupplierManager::get_instance();
        $this->pricing_calculator = \UtilitySign\Services\PricingCalculator::get_instance();
        $this->supplier_analytics = \UtilitySign\Services\SupplierAnalytics::get_instance();
        $this->performance_optimizer = \UtilitySign\Core\PerformanceOptimizer::get_instance();
    }
    
    public function tearDown(): void {
        parent::tearDown();
    }
    
    /**
     * Test product categories and tags functionality
     */
    public function test_product_categories_and_tags() {
        // Test category creation
        $category_id = wp_insert_term('Test Category', 'utilitysign_product_category');
        $this->assertNotWPError($category_id);
        
        // Test tag creation
        $tag_id = wp_insert_term('Test Tag', 'utilitysign_product_tag');
        $this->assertNotWPError($tag_id);
        
        // Test product with categories and tags
        $product_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Test Product with Categories'
        ]);
        
        wp_set_post_terms($product_id, [$category_id['term_id']], 'utilitysign_product_category');
        wp_set_post_terms($product_id, [$tag_id['term_id']], 'utilitysign_product_tag');
        
        $categories = wp_get_post_terms($product_id, 'utilitysign_product_category');
        $tags = wp_get_post_terms($product_id, 'utilitysign_product_tag');
        
        $this->assertCount(1, $categories);
        $this->assertCount(1, $tags);
        $this->assertEquals('Test Category', $categories[0]->name);
        $this->assertEquals('Test Tag', $tags[0]->name);
    }
    
    /**
     * Test advanced pricing models
     */
    public function test_advanced_pricing_models() {
        $product_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Test Product with Advanced Pricing'
        ]);
        
        // Set base price
        update_post_meta($product_id, '_product_base_price', '100.00');
        
        // Set advanced pricing models
        $advanced_pricing = [
            [
                'type' => 'volume_discount',
                'min_quantity' => 10,
                'discount_percentage' => 10
            ],
            [
                'type' => 'tier_pricing',
                'tiers' => [
                    ['min_quantity' => 1, 'price' => 100],
                    ['min_quantity' => 10, 'price' => 90],
                    ['min_quantity' => 50, 'price' => 80]
                ]
            ]
        ];
        
        update_post_meta($product_id, '_product_advanced_pricing', $advanced_pricing);
        
        // Test volume discount pricing
        $price_5 = $this->pricing_calculator->calculate_price($product_id, 5);
        $this->assertEquals(500, $price_5); // 5 * 100 = 500
        
        $price_10 = $this->pricing_calculator->calculate_price($product_id, 10);
        $this->assertEquals(900, $price_10); // 10 * 90 = 900 (volume discount)
        
        $price_50 = $this->pricing_calculator->calculate_price($product_id, 50);
        $this->assertEquals(4000, $price_50); // 50 * 80 = 4000 (tier pricing)
    }
    
    /**
     * Test supplier management functionality
     */
    public function test_supplier_management() {
        $supplier_id = $this->factory->post->create([
            'post_type' => 'utilitysign_supplier',
            'post_title' => 'Test Supplier'
        ]);
        
        // Set supplier details
        update_post_meta($supplier_id, '_supplier_contact_person', 'John Doe');
        update_post_meta($supplier_id, '_supplier_email', 'john@example.com');
        update_post_meta($supplier_id, '_supplier_phone', '+1234567890');
        update_post_meta($supplier_id, '_supplier_address', '123 Test Street');
        
        // Set supplier branding
        update_post_meta($supplier_id, '_supplier_primary_color', '#FF0000');
        update_post_meta($supplier_id, '_supplier_secondary_color', '#00FF00');
        
        // Test supplier details retrieval
        $details = $this->supplier_manager->get_supplier_details($supplier_id);
        $this->assertEquals('John Doe', $details['contact_person']);
        $this->assertEquals('john@example.com', $details['email']);
        
        $branding = $this->supplier_manager->get_supplier_branding($supplier_id);
        $this->assertEquals('#FF0000', $branding['primary_color']);
        $this->assertEquals('#00FF00', $branding['secondary_color']);
    }
    
    /**
     * Test order analytics functionality
     */
    public function test_order_analytics() {
        // Create test orders
        $order1_id = $this->factory->post->create([
            'post_type' => 'utilitysign_order',
            'post_title' => 'Test Order 1',
            'post_date' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ]);
        
        $order2_id = $this->factory->post->create([
            'post_type' => 'utilitysign_order',
            'post_title' => 'Test Order 2',
            'post_date' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]);
        
        // Set order meta
        update_post_meta($order1_id, '_order_status', 'completed');
        update_post_meta($order1_id, '_order_total_price', '150.00');
        update_post_meta($order1_id, '_order_supplier_id', '1');
        
        update_post_meta($order2_id, '_order_status', 'pending');
        update_post_meta($order2_id, '_order_total_price', '200.00');
        update_post_meta($order2_id, '_order_supplier_id', '1');
        
        // Test analytics
        $analytics = $this->order_manager->get_order_analytics([
            'date_from' => date('Y-m-d', strtotime('-10 days')),
            'date_to' => date('Y-m-d'),
            'supplier_id' => '1'
        ]);
        
        $this->assertEquals(2, $analytics['total_orders']);
        $this->assertEquals(350, $analytics['total_revenue']);
        $this->assertArrayHasKey('status_breakdown', $analytics);
        $this->assertArrayHasKey('daily_orders', $analytics);
    }
    
    /**
     * Test Gutenberg blocks registration
     */
    public function test_gutenberg_blocks() {
        // Test that blocks are registered
        $registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        
        $this->assertArrayHasKey('utilitysign/product-display', $registered_blocks);
        $this->assertArrayHasKey('utilitysign/order-form', $registered_blocks);
        $this->assertArrayHasKey('utilitysign/supplier-selection', $registered_blocks);
        
        // Test block attributes
        $product_display_block = $registered_blocks['utilitysign/product-display'];
        $this->assertArrayHasKey('productId', $product_display_block->attributes);
        $this->assertArrayHasKey('displayVariations', $product_display_block->attributes);
        $this->assertArrayHasKey('displayPricing', $product_display_block->attributes);
    }
    
    /**
     * Test performance optimization
     */
    public function test_performance_optimization() {
        // Test caching functionality
        $product_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Test Product for Caching'
        ]);
        
        // Test cache set and get
        $cache_key = 'utilitysign_product_details_' . $product_id;
        $test_data = ['name' => 'Test Product', 'price' => 100];
        
        set_transient($cache_key, $test_data, HOUR_IN_SECONDS);
        $cached_data = get_transient($cache_key);
        
        $this->assertEquals($test_data, $cached_data);
        
        // Test cache clearing
        delete_transient($cache_key);
        $cached_data_after_clear = get_transient($cache_key);
        $this->assertFalse($cached_data_after_clear);
    }
    
    /**
     * Test REST API endpoints
     */
    public function test_rest_api_endpoints() {
        // Test pricing calculation endpoint
        $request = new \WP_REST_Request('POST', '/utilitysign/v1/pricing/calculate');
        $request->set_param('product_id', 1);
        $request->set_param('quantity', 5);
        
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        
        // Test supplier analytics endpoint
        $request = new \WP_REST_Request('GET', '/utilitysign/v1/supplier-analytics/1/overview');
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
    }
    
    /**
     * Test product filtering functionality
     */
    public function test_product_filtering() {
        // Create test products with different categories
        $category1_id = wp_insert_term('Category 1', 'utilitysign_product_category');
        $category2_id = wp_insert_term('Category 2', 'utilitysign_product_category');
        
        $product1_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Product 1'
        ]);
        
        $product2_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Product 2'
        ]);
        
        wp_set_post_terms($product1_id, [$category1_id['term_id']], 'utilitysign_product_category');
        wp_set_post_terms($product2_id, [$category2_id['term_id']], 'utilitysign_product_category');
        
        // Test filtering by category
        $filtered_products = $this->product_manager->get_products_with_filters([
            'category' => 'category-1'
        ]);
        
        $this->assertCount(1, $filtered_products['products']);
        $this->assertEquals($product1_id, $filtered_products['products'][0]->ID);
    }
    
    /**
     * Test supplier analytics service
     */
    public function test_supplier_analytics_service() {
        $supplier_id = $this->factory->post->create([
            'post_type' => 'utilitysign_supplier',
            'post_title' => 'Test Supplier for Analytics'
        ]);
        
        // Test supplier overview
        $overview = $this->supplier_analytics->get_supplier_overview($supplier_id);
        
        $this->assertArrayHasKey('total_orders', $overview);
        $this->assertArrayHasKey('total_revenue', $overview);
        $this->assertArrayHasKey('average_order_value', $overview);
        $this->assertArrayHasKey('status_breakdown', $overview);
        
        // Test product performance
        $performance = $this->supplier_analytics->get_supplier_product_performance($supplier_id);
        $this->assertIsArray($performance);
        
        // Test revenue over time
        $revenue_data = $this->supplier_analytics->get_supplier_revenue_over_time($supplier_id);
        $this->assertIsArray($revenue_data);
    }
    
    /**
     * Test database optimization
     */
    public function test_database_optimization() {
        // Test that optimization can be called without errors
        $this->performance_optimizer->perform_database_optimization();
        
        // Test that scheduled optimization is set up
        $this->assertTrue(wp_next_scheduled('utilitysign_daily_db_optimization') !== false);
    }
    
    /**
     * Test order status tracking
     */
    public function test_order_status_tracking() {
        $order_id = $this->factory->post->create([
            'post_type' => 'utilitysign_order',
            'post_title' => 'Test Order for Status Tracking'
        ]);
        
        // Test status update
        $this->order_manager->update_order_status($order_id, 'processing');
        
        $status = get_post_meta($order_id, '_order_status', true);
        $this->assertEquals('processing', $status);
        
        // Test status history
        $history = $this->order_manager->get_order_status_history($order_id);
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $this->assertEquals('processing', $history[0]['status']);
    }
    
    /**
     * Test order export functionality
     */
    public function test_order_export() {
        // Create test orders
        $order_id = $this->factory->post->create([
            'post_type' => 'utilitysign_order',
            'post_title' => 'Test Order for Export'
        ]);
        
        update_post_meta($order_id, '_order_status', 'completed');
        update_post_meta($order_id, '_order_total_price', '250.00');
        
        // Test CSV export
        $csv_data = $this->order_manager->export_orders_csv([
            'date_from' => date('Y-m-d', strtotime('-10 days')),
            'date_to' => date('Y-m-d')
        ]);
        
        $this->assertIsString($csv_data);
        $this->assertStringContainsString('Test Order for Export', $csv_data);
        $this->assertStringContainsString('completed', $csv_data);
        $this->assertStringContainsString('250.00', $csv_data);
    }
    
    /**
     * Test frontend asset optimization
     */
    public function test_frontend_asset_optimization() {
        // Test that assets are enqueued correctly
        $this->performance_optimizer->optimize_frontend_assets();
        
        // Test that scripts are registered
        $this->assertTrue(wp_script_is('utilitysign-product-display-block-editor', 'registered'));
        $this->assertTrue(wp_script_is('utilitysign-order-form-block-editor', 'registered'));
        $this->assertTrue(wp_script_is('utilitysign-supplier-selection-block-editor', 'registered'));
    }
    
    /**
     * Test integration between components
     */
    public function test_component_integration() {
        // Create a complete workflow: supplier -> product -> order -> analytics
        
        // 1. Create supplier
        $supplier_id = $this->factory->post->create([
            'post_type' => 'utilitysign_supplier',
            'post_title' => 'Integration Test Supplier'
        ]);
        
        // 2. Create product
        $product_id = $this->factory->post->create([
            'post_type' => 'utilitysign_product',
            'post_title' => 'Integration Test Product'
        ]);
        
        update_post_meta($product_id, '_product_supplier_id', $supplier_id);
        update_post_meta($product_id, '_product_base_price', '100.00');
        
        // 3. Create order
        $order_id = $this->factory->post->create([
            'post_type' => 'utilitysign_order',
            'post_title' => 'Integration Test Order'
        ]);
        
        update_post_meta($order_id, '_order_supplier_id', $supplier_id);
        update_post_meta($order_id, '_order_product_id', $product_id);
        update_post_meta($order_id, '_order_status', 'completed');
        update_post_meta($order_id, '_order_total_price', '100.00');
        
        // 4. Test pricing calculation
        $price = $this->pricing_calculator->calculate_price($product_id, 1);
        $this->assertEquals(100, $price);
        
        // 5. Test analytics
        $overview = $this->supplier_analytics->get_supplier_overview($supplier_id);
        $this->assertEquals(1, $overview['total_orders']);
        $this->assertEquals(100, $overview['total_revenue']);
        
        // 6. Test performance optimization
        $this->performance_optimizer->clear_product_cache($product_id);
        $this->assertFalse(get_transient('utilitysign_product_details_' . $product_id));
    }
}
