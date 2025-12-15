<?php
namespace UtilitySign\Models;

use UtilitySign\Core\Base;

/**
 * Product model for managing utility products
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class Product extends Base {
    /**
     * Product ID
     *
     * @var int
     */
    public $ID;

    /**
     * Product title
     *
     * @var string
     */
    public $post_title;

    /**
     * Product content
     *
     * @var string
     */
    public $post_content;

    /**
     * Product excerpt
     *
     * @var string
     */
    public $post_excerpt;

    /**
     * Product status
     *
     * @var string
     */
    public $post_status;

    /**
     * Product creation date
     *
     * @var string
     */
    public $post_date;

    /**
     * Product modification date
     *
     * @var string
     */
    public $post_modified;

    /**
     * Get all products
     *
     * @param array $args Query arguments
     * @return array Array of Product objects
     */
    public static function getAll($args = [])
    {
        $defaults = [
            'post_type' => 'utilitysign_product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);
        
        $products = [];
        foreach ($posts as $post) {
            $product = new self();
            $product->ID = $post->ID;
            $product->post_title = $post->post_title;
            $product->post_content = $post->post_content;
            $product->post_excerpt = $post->post_excerpt;
            $product->post_status = $post->post_status;
            $product->post_date = $post->post_date;
            $product->post_modified = $post->post_modified;
            $products[] = $product;
        }
        
        return $products;
    }

    /**
     * Get product by ID
     *
     * @param int $id Product ID
     * @return Product|null
     */
    public static function find($id)
    {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== 'utilitysign_product') {
            return null;
        }
        
        $product = new self();
        $product->ID = $post->ID;
        $product->post_title = $post->post_title;
        $product->post_content = $post->post_content;
        $product->post_excerpt = $post->post_excerpt;
        $product->post_status = $post->post_status;
        $product->post_date = $post->post_date;
        $product->post_modified = $post->post_modified;
        
        return $product;
    }

    /**
     * Create a new product
     *
     * @param array $data Product data
     * @return Product|false
     */
    public static function create($data)
    {
        $post_data = [
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_status' => sanitize_text_field($data['status'] ?? 'publish'),
            'post_type' => 'utilitysign_product',
            'post_author' => get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }

        // Set meta fields
        if (isset($data['supplier'])) {
            update_post_meta($post_id, '_utilitysign_supplier', sanitize_text_field($data['supplier']));
        }
        if (isset($data['code'])) {
            update_post_meta($post_id, '_utilitysign_code', sanitize_text_field($data['code']));
        }
        if (isset($data['price'])) {
            update_post_meta($post_id, '_utilitysign_price', floatval($data['price']));
        }
        if (isset($data['currency'])) {
            update_post_meta($post_id, '_utilitysign_currency', sanitize_text_field($data['currency']));
        }
        if (isset($data['features']) && is_array($data['features'])) {
            update_post_meta($post_id, '_utilitysign_features', array_map('sanitize_text_field', $data['features']));
        }
        if (isset($data['requirements']) && is_array($data['requirements'])) {
            update_post_meta($post_id, '_utilitysign_requirements', array_map('sanitize_text_field', $data['requirements']));
        }

        return self::find($post_id);
    }

    /**
     * Update product
     *
     * @param array $data Product data
     * @return bool
     */
    public function update($data)
    {
        $post_data = [
            'ID' => $this->ID,
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($data['excerpt'] ?? ''),
            'post_status' => sanitize_text_field($data['status'] ?? $this->post_status),
        ];

        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return false;
        }

        // Update meta fields
        if (isset($data['supplier'])) {
            update_post_meta($this->ID, '_utilitysign_supplier', sanitize_text_field($data['supplier']));
        }
        if (isset($data['code'])) {
            update_post_meta($this->ID, '_utilitysign_code', sanitize_text_field($data['code']));
        }
        if (isset($data['price'])) {
            update_post_meta($this->ID, '_utilitysign_price', floatval($data['price']));
        }
        if (isset($data['currency'])) {
            update_post_meta($this->ID, '_utilitysign_currency', sanitize_text_field($data['currency']));
        }
        if (isset($data['features']) && is_array($data['features'])) {
            update_post_meta($this->ID, '_utilitysign_features', array_map('sanitize_text_field', $data['features']));
        }
        if (isset($data['requirements']) && is_array($data['requirements'])) {
            update_post_meta($this->ID, '_utilitysign_requirements', array_map('sanitize_text_field', $data['requirements']));
        }

        return true;
    }

    /**
     * Delete product
     *
     * @return bool
     */
    public function delete()
    {
        return wp_delete_post($this->ID, true) !== false;
    }

    /**
     * Get a specific meta value
     *
     * @param string $key Meta key
     * @return mixed Meta value
     */
    public function getMeta($key)
    {
        return get_post_meta($this->ID, $key, true);
    }

    /**
     * Set a specific meta value
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool|int Meta ID on success, false on failure
     */
    public function setMeta($key, $value)
    {
        return update_post_meta($this->ID, $key, $value);
    }

    /**
     * Get product supplier
     *
     * @return string|null
     */
    public function getSupplier()
    {
        return $this->getMeta('_utilitysign_supplier');
    }

    /**
     * Get product code
     *
     * @return string|null
     */
    public function getCode()
    {
        return $this->getMeta('_utilitysign_code');
    }

    /**
     * Get product price
     *
     * @return float|null
     */
    public function getPrice()
    {
        return floatval($this->getMeta('_utilitysign_price'));
    }

    /**
     * Get product currency
     *
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->getMeta('_utilitysign_currency') ?: 'NOK';
    }

    /**
     * Get product features
     *
     * @return array
     */
    public function getFeatures()
    {
        $features = $this->getMeta('_utilitysign_features');
        return is_array($features) ? $features : [];
    }

    /**
     * Get product requirements
     *
     * @return array
     */
    public function getRequirements()
    {
        $requirements = $this->getMeta('_utilitysign_requirements');
        return is_array($requirements) ? $requirements : [];
    }

    /**
     * Check if product is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->post_status === 'publish';
    }

    /**
     * Get formatted price
     *
     * @return string
     */
    public function getFormattedPrice()
    {
        $price = $this->getPrice();
        $currency = $this->getCurrency();
        
        if ($currency === 'NOK') {
            return number_format($price, 0, ',', ' ') . ' kr';
        }
        
        return $currency . ' ' . number_format($price, 2);
    }

    /**
     * Get product data as array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->ID,
            'title' => $this->post_title,
            'content' => $this->post_content,
            'excerpt' => $this->post_excerpt,
            'status' => $this->post_status,
            'supplier' => $this->getSupplier(),
            'code' => $this->getCode(),
            'price' => $this->getPrice(),
            'currency' => $this->getCurrency(),
            'features' => $this->getFeatures(),
            'requirements' => $this->getRequirements(),
            'formatted_price' => $this->getFormattedPrice(),
            'created_at' => $this->post_date,
            'updated_at' => $this->post_modified,
        ];
    }
}