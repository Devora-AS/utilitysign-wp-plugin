<?php
/**
 * Supplier Selection Block
 * 
 * @package UtilitySign
 * @since 1.0.0
 */

namespace UtilitySign\Blocks;

use UtilitySign\Traits\Base;

/**
 * Supplier Selection Block class
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class SupplierSelectionBlock {
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
        register_block_type('utilitysign/supplier-selection', [
            'attributes' => [
                'supplierId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'showSupplierInfo' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showSupplierLogo' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showSupplierDescription' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showContactInfo' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'displayStyle' => [
                    'type' => 'string',
                    'default' => 'dropdown'
                ],
                'buttonText' => [
                    'type' => 'string',
                    'default' => __('Select Supplier', 'utilitysign')
                ],
                'placeholder' => [
                    'type' => 'string',
                    'default' => __('Choose a supplier', 'utilitysign')
                ]
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'utilitysign-supplier-selection-block',
            'editor_style' => 'utilitysign-supplier-selection-block-editor',
            'style' => 'utilitysign-supplier-selection-block'
        ]);
    }

    /**
     * Enqueue block editor assets
     * 
     * @since 1.0.0
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'utilitysign-supplier-selection-block',
            UTILITYSIGN_URL . 'assets/js/blocks/supplier-selection.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            UTILITYSIGN_VERSION,
            true
        );

        wp_enqueue_style(
            'utilitysign-supplier-selection-block-editor',
            UTILITYSIGN_URL . 'assets/css/blocks/supplier-selection-editor.css',
            ['wp-edit-blocks'],
            UTILITYSIGN_VERSION
        );

        // Localize script with data
        wp_localize_script('utilitysign-supplier-selection-block', 'utilitySignSupplierSelection', [
            'suppliers' => $this->get_suppliers_for_editor()
        ]);
    }

    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets() {
        if (has_block('utilitysign/supplier-selection')) {
            wp_enqueue_style(
                'utilitysign-supplier-selection-block',
                UTILITYSIGN_URL . 'assets/css/blocks/supplier-selection.css',
                [],
                UTILITYSIGN_VERSION
            );

            wp_enqueue_script(
                'utilitysign-supplier-selection-frontend',
                UTILITYSIGN_URL . 'assets/js/frontend/supplier-selection.js',
                ['jquery'],
                UTILITYSIGN_VERSION,
                true
            );

            wp_localize_script('utilitysign-supplier-selection-frontend', 'utilitySignSupplierSelection', [
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
        $supplier_id = $attributes['supplierId'] ?? 0;
        $show_supplier_info = $attributes['showSupplierInfo'] ?? true;
        $show_supplier_logo = $attributes['showSupplierLogo'] ?? true;
        $show_supplier_description = $attributes['showSupplierDescription'] ?? true;
        $show_contact_info = $attributes['showContactInfo'] ?? true;
        $display_style = $attributes['displayStyle'] ?? 'dropdown';
        $button_text = $attributes['buttonText'] ?? __('Select Supplier', 'utilitysign');
        $placeholder = $attributes['placeholder'] ?? __('Choose a supplier', 'utilitysign');

        $suppliers = $this->get_suppliers_for_form();

        ob_start();
        ?>
        <div class="utilitysign-supplier-selection" data-display-style="<?php echo esc_attr($display_style); ?>">
            <?php if ($display_style === 'dropdown'): ?>
                <div class="supplier-dropdown">
                    <label for="supplier-select"><?php _e('Select Supplier', 'utilitysign'); ?></label>
                    <select id="supplier-select" name="supplier_id">
                        <option value=""><?php echo esc_html($placeholder); ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo esc_attr($supplier['id']); ?>" <?php selected($supplier_id, $supplier['id']); ?>>
                                <?php echo esc_html($supplier['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($display_style === 'cards'): ?>
                <div class="supplier-cards">
                    <h3><?php _e('Choose a Supplier', 'utilitysign'); ?></h3>
                    <div class="supplier-grid">
                        <?php foreach ($suppliers as $supplier): ?>
                            <div class="supplier-card" data-supplier-id="<?php echo esc_attr($supplier['id']); ?>">
                                <?php if ($show_supplier_logo && !empty($supplier['logo_url'])): ?>
                                    <div class="supplier-logo">
                                        <img src="<?php echo esc_url($supplier['logo_url']); ?>" alt="<?php echo esc_attr($supplier['title']); ?>" />
                                    </div>
                                <?php endif; ?>
                                
                                <div class="supplier-info">
                                    <h4 class="supplier-name"><?php echo esc_html($supplier['title']); ?></h4>
                                    
                                    <?php if ($show_supplier_description && !empty($supplier['description'])): ?>
                                        <p class="supplier-description"><?php echo esc_html($supplier['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($show_contact_info): ?>
                                        <div class="supplier-contact">
                                            <?php if (!empty($supplier['email'])): ?>
                                                <p><strong><?php _e('Email:', 'utilitysign'); ?></strong> <?php echo esc_html($supplier['email']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($supplier['phone'])): ?>
                                                <p><strong><?php _e('Phone:', 'utilitysign'); ?></strong> <?php echo esc_html($supplier['phone']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($supplier['contact_person'])): ?>
                                                <p><strong><?php _e('Contact:', 'utilitysign'); ?></strong> <?php echo esc_html($supplier['contact_person']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="select-supplier-btn" data-supplier-id="<?php echo esc_attr($supplier['id']); ?>">
                                    <?php echo esc_html($button_text); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($display_style === 'list'): ?>
                <div class="supplier-list">
                    <h3><?php _e('Available Suppliers', 'utilitysign'); ?></h3>
                    <ul class="supplier-list-items">
                        <?php foreach ($suppliers as $supplier): ?>
                            <li class="supplier-list-item" data-supplier-id="<?php echo esc_attr($supplier['id']); ?>">
                                <div class="supplier-list-content">
                                    <?php if ($show_supplier_logo && !empty($supplier['logo_url'])): ?>
                                        <div class="supplier-list-logo">
                                            <img src="<?php echo esc_url($supplier['logo_url']); ?>" alt="<?php echo esc_attr($supplier['title']); ?>" />
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="supplier-list-info">
                                        <h4><?php echo esc_html($supplier['title']); ?></h4>
                                        
                                        <?php if ($show_supplier_description && !empty($supplier['description'])): ?>
                                            <p><?php echo esc_html($supplier['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($show_contact_info): ?>
                                            <div class="supplier-list-contact">
                                                <?php if (!empty($supplier['email'])): ?>
                                                    <span><?php echo esc_html($supplier['email']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($supplier['phone'])): ?>
                                                    <span><?php echo esc_html($supplier['phone']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="select-supplier-btn" data-supplier-id="<?php echo esc_attr($supplier['id']); ?>">
                                        <?php echo esc_html($button_text); ?>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="supplier-selection-messages"></div>
        </div>
        <?php
        return ob_get_clean();
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
                $post_id = get_the_ID();
                
                $suppliers[] = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'description' => get_the_content(),
                    'email' => get_post_meta($post_id, '_supplier_email', true),
                    'phone' => get_post_meta($post_id, '_supplier_phone', true),
                    'contact_person' => get_post_meta($post_id, '_supplier_contact_person', true),
                    'logo_id' => get_post_meta($post_id, '_supplier_logo_id', true),
                    'logo_url' => wp_get_attachment_url(get_post_meta($post_id, '_supplier_logo_id', true))
                ];
            }
            wp_reset_postdata();
        }

        return $suppliers;
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
