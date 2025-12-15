<?php
/**
 * Product Display Block
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Blocks;

use UtilitySign\Traits\Base;

/**
 * Product Display Block class
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ProductDisplayBlock {
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
    }

    /**
     * Register the block
     * 
     * @since 1.0.0
     */
    public function register_block() {
        register_block_type('utilitysign/product-display', [
            'attributes' => [
                'productId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'showPrice' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showImage' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showVariations' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showAddToCart' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'layout' => [
                    'type' => 'string',
                    'default' => 'grid'
                ],
                'columns' => [
                    'type' => 'number',
                    'default' => 3
                ],
                'supplierId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'category' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'tag' => [
                    'type' => 'string',
                    'default' => ''
                ],
                'limit' => [
                    'type' => 'number',
                    'default' => 12
                ],
                'orderBy' => [
                    'type' => 'string',
                    'default' => 'date'
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC'
                ]
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'utilitysign-product-display-block',
            'editor_style' => 'utilitysign-product-display-block-editor',
            'style' => 'utilitysign-product-display-block'
        ]);
    }

    /**
     * Enqueue block editor assets
     * 
     * @since 1.0.0
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'utilitysign-product-display-block',
            UTILITYSIGN_URL . 'assets/js/blocks/product-display.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            UTILITYSIGN_VERSION,
            true
        );

        wp_enqueue_style(
            'utilitysign-product-display-block-editor',
            UTILITYSIGN_URL . 'assets/css/blocks/product-display-editor.css',
            ['wp-edit-blocks'],
            UTILITYSIGN_VERSION
        );

        // Localize script with data
        wp_localize_script('utilitysign-product-display-block', 'utilitySignProductDisplay', [
            'products' => $this->get_products_for_editor(),
            'suppliers' => $this->get_suppliers_for_editor(),
            'categories' => $this->get_categories_for_editor(),
            'tags' => $this->get_tags_for_editor()
        ]);
    }

    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        if (has_block('utilitysign/product-display')) {
            wp_enqueue_style(
                'utilitysign-product-display-block',
                UTILITYSIGN_URL . 'assets/css/blocks/product-display.css',
                [],
                UTILITYSIGN_VERSION
            );

            wp_enqueue_script(
                'utilitysign-product-display-frontend',
                UTILITYSIGN_URL . 'assets/js/frontend/product-display.js',
                ['jquery'],
                UTILITYSIGN_VERSION,
                true
            );

            wp_localize_script('utilitysign-product-display-frontend', 'utilitySignProductDisplay', [
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
        $category = $attributes['category'] ?? '';
        $tag = $attributes['tag'] ?? '';
        $limit = $attributes['limit'] ?? 12;
        $order_by = $attributes['orderBy'] ?? 'date';
        $order = $attributes['order'] ?? 'DESC';
        $layout = $attributes['layout'] ?? 'grid';
        $columns = $attributes['columns'] ?? 3;

        // Build query args
        $query_args = [
            'post_type' => 'utilitysign_product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => $order_by,
            'order' => $order,
            'meta_query' => []
        ];

        // Filter by specific product
        if ($product_id > 0) {
            $query_args['p'] = $product_id;
        }

        // Filter by supplier
        if ($supplier_id > 0) {
            $query_args['meta_query'][] = [
                'key' => '_product_supplier_id',
                'value' => $supplier_id,
                'compare' => '='
            ];
        }

        // Filter by category
        if (!empty($category)) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'utilitysign_product_category',
                    'field' => 'slug',
                    'terms' => $category
                ]
            ];
        }

        // Filter by tag
        if (!empty($tag)) {
            if (!isset($query_args['tax_query'])) {
                $query_args['tax_query'] = [];
            }
            $query_args['tax_query'][] = [
                'taxonomy' => 'utilitysign_product_tag',
                'field' => 'slug',
                'terms' => $tag
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
                    'content' => get_the_content(),
                    'excerpt' => get_the_excerpt(),
                    'price' => get_post_meta($post_id, '_product_price', true),
                    'currency' => get_post_meta($post_id, '_product_currency', true),
                    'image_url' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'supplier_id' => get_post_meta($post_id, '_product_supplier_id', true),
                    'variations' => get_post_meta($post_id, '_product_variations', true),
                    'categories' => wp_get_post_terms($post_id, 'utilitysign_product_category', ['fields' => 'names']),
                    'tags' => wp_get_post_terms($post_id, 'utilitysign_product_tag', ['fields' => 'names'])
                ];
            }
            wp_reset_postdata();
        }

        // Render the block
        ob_start();
        ?>
        <div class="utilitysign-product-display" data-layout="<?php echo esc_attr($layout); ?>" data-columns="<?php echo esc_attr($columns); ?>">
            <?php if (empty($products)): ?>
                <p class="no-products"><?php _e('No products found.', 'utilitysign'); ?></p>
            <?php else: ?>
                <div class="products-container <?php echo esc_attr($layout); ?>-layout columns-<?php echo esc_attr($columns); ?>">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item" data-product-id="<?php echo esc_attr($product['id']); ?>">
                            <?php if ($attributes['showImage'] && $product['image_url']): ?>
                                <div class="product-image">
                                    <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['title']); ?>" />
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-content">
                                <h3 class="product-title"><?php echo esc_html($product['title']); ?></h3>
                                
                                <?php if ($attributes['showDescription'] && !empty($product['excerpt'])): ?>
                                    <div class="product-excerpt">
                                        <?php echo wp_kses_post($product['excerpt']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($attributes['showPrice'] && $product['price']): ?>
                                    <div class="product-price">
                                        <span class="price"><?php echo esc_html($product['price']); ?></span>
                                        <span class="currency"><?php echo esc_html($product['currency'] ?: 'NOK'); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($attributes['showVariations'] && !empty($product['variations'])): ?>
                                    <div class="product-variations">
                                        <select class="variation-select">
                                            <option value=""><?php _e('Select variation', 'utilitysign'); ?></option>
                                            <?php foreach ($product['variations'] as $variation): ?>
                                                <option value="<?php echo esc_attr($variation['name']); ?>" data-price-modifier="<?php echo esc_attr($variation['price_modifier']); ?>">
                                                    <?php echo esc_html($variation['name']); ?>
                                                    <?php if ($variation['price_modifier'] != 0): ?>
                                                        (<?php echo $variation['price_modifier'] > 0 ? '+' : ''; ?><?php echo esc_html($variation['price_modifier']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($attributes['showAddToCart']): ?>
                                    <div class="product-actions">
                                        <button class="add-to-cart-btn" data-product-id="<?php echo esc_attr($product['id']); ?>">
                                            <?php _e('Add to Cart', 'utilitysign'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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

    /**
     * Get categories for editor
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_categories_for_editor() {
        $categories = get_terms([
            'taxonomy' => 'utilitysign_product_category',
            'hide_empty' => false
        ]);

        $category_options = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_options[] = [
                    'value' => $category->slug,
                    'label' => $category->name
                ];
            }
        }

        return $category_options;
    }

    /**
     * Get tags for editor
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_tags_for_editor() {
        $tags = get_terms([
            'taxonomy' => 'utilitysign_product_tag',
            'hide_empty' => false
        ]);

        $tag_options = [];
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $tag_options[] = [
                    'value' => $tag->slug,
                    'label' => $tag->name
                ];
            }
        }

        return $tag_options;
    }
}