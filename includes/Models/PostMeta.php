<?php
namespace UtilitySign\Models;

use UtilitySign\Core\Base;

/**
 * PostMeta model for managing post metadata
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class PostMeta extends Base {
    /**
     * Meta ID
     *
     * @var int
     */
    public $meta_id;

    /**
     * Post ID
     *
     * @var int
     */
    public $post_id;

    /**
     * Meta key
     *
     * @var string
     */
    public $meta_key;

    /**
     * Meta value
     *
     * @var string
     */
    public $meta_value;

    /**
     * Get meta by post ID and key
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @return PostMeta|null
     */
    public static function findByPostAndKey($post_id, $meta_key)
    {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $post_id,
            $meta_key
        ));
        
        if (!$result) {
            return null;
        }
        
        $meta = new self();
        $meta->meta_id = $result->meta_id;
        $meta->post_id = $result->post_id;
        $meta->meta_key = $result->meta_key;
        $meta->meta_value = $result->meta_value;
        
        return $meta;
    }

    /**
     * Get all meta for a post
     *
     * @param int $post_id Post ID
     * @return array Array of PostMeta objects
     */
    public static function getByPostId($post_id)
    {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d",
            $post_id
        ));
        
        $metas = [];
        foreach ($results as $result) {
            $meta = new self();
            $meta->meta_id = $result->meta_id;
            $meta->post_id = $result->post_id;
            $meta->meta_key = $result->meta_key;
            $meta->meta_value = $result->meta_value;
            $metas[] = $meta;
        }
        
        return $metas;
    }

    /**
     * Create or update meta
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @return bool|int Meta ID on success, false on failure
     */
    public static function update($post_id, $meta_key, $meta_value)
    {
        return update_post_meta($post_id, $meta_key, $meta_value);
    }

    /**
     * Add meta
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @return bool|int Meta ID on success, false on failure
     */
    public static function add($post_id, $meta_key, $meta_value)
    {
        return add_post_meta($post_id, $meta_key, $meta_value);
    }

    /**
     * Delete meta
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @return bool True on success, false on failure
     */
    public static function delete($post_id, $meta_key)
    {
        return delete_post_meta($post_id, $meta_key);
    }

    /**
     * Get meta value
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param bool $single Whether to return a single value
     * @return mixed Meta value
     */
    public static function get($post_id, $meta_key, $single = true)
    {
        return get_post_meta($post_id, $meta_key, $single);
    }

    /**
     * Check if meta exists
     *
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @return bool True if exists, false otherwise
     */
    public static function exists($post_id, $meta_key)
    {
        return metadata_exists('post', $post_id, $meta_key);
    }

    /**
     * Get all meta keys for a post
     *
     * @param int $post_id Post ID
     * @return array Array of meta keys
     */
    public static function getKeys($post_id)
    {
        global $wpdb;
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d",
            $post_id
        ));
        
        return $results;
    }

    /**
     * Get meta as array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'meta_id' => $this->meta_id,
            'post_id' => $this->post_id,
            'meta_key' => $this->meta_key,
            'meta_value' => $this->meta_value,
        ];
    }
}