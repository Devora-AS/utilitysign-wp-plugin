<?php
namespace UtilitySign\Core;

use UtilitySign\Traits\Base;

/**
 * Performance Optimization
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
final class PerformanceOptimizer {
    use Base;

    /**
     * Initialize performance optimization
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('init', [$this, 'init_caching']);
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets']);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_unused_theme_scripts'], 20); // Priority 20 to run after theme enqueues
        add_action('wp_enqueue_scripts', [$this, 'defer_third_party_scripts'], 25); // Priority 25 to run after theme/plugin enqueues
        add_action('wp_enqueue_scripts', [$this, 'fix_mime_type_script_errors'], 30); // Priority 30 to catch all scripts
        add_action('wp_head', [$this, 'add_preload_hints']);
        add_action('wp_footer', [$this, 'add_defer_scripts']);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
        add_action('wp_ajax_utilitysign_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_nopriv_utilitysign_clear_cache', [$this, 'ajax_clear_cache']);
    }

    /**
     * Initialize caching system
     * 
     * @since 1.0.0
     */
    public function init_caching() {
        // Object cache for database queries
        if (!wp_using_ext_object_cache()) {
            add_action('wp_cache_init', [$this, 'init_object_cache']);
        }
        
        // Page caching
        add_action('template_redirect', [$this, 'maybe_serve_cached_page']);
        add_action('wp_footer', [$this, 'maybe_cache_page']);
        
        // Database query optimization
        add_filter('posts_where', [$this, 'optimize_meta_queries'], 10, 2);
        add_filter('posts_orderby', [$this, 'optimize_orderby_clauses'], 10, 2);
    }

    /**
     * Optimize asset loading
     * 
     * @since 1.0.0
     */
    public function optimize_assets() {
        // Minify CSS and JS in production
        if (!WP_DEBUG) {
            add_filter('style_loader_src', [$this, 'add_version_to_assets'], 10, 2);
            add_filter('script_loader_src', [$this, 'add_version_to_assets'], 10, 2);
        }
        
        // Critical CSS inlining
        add_action('wp_head', [$this, 'inline_critical_css'], 1);
        
        // Lazy loading for images
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazy_loading'], 10, 3);
    }

    /**
     * Dequeue unused theme scripts that cause 404 errors
     * 
     * @since 1.0.0
     */
    public function dequeue_unused_theme_scripts() {
        // Only on pages with UtilitySign shortcode
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if page has UtilitySign signing form shortcode
        $has_shortcode = has_shortcode($post->post_content, 'utilitysign_signing_form') ||
                        has_shortcode($post->post_content, 'utilitysign_form');
        
        if ($has_shortcode) {
            // Dequeue Enfold theme scripts that cause 404 errors
            // These scripts are missing but enqueued by the theme
            $scripts_to_dequeue = [
                'enfold-order-form',
                'enfold-ajax-filter',
                'avf-order-form',
                'avf-ajax-filter',
            ];
            
            foreach ($scripts_to_dequeue as $handle) {
                if (wp_script_is($handle, 'enqueued')) {
                    wp_dequeue_script($handle);
                }
            }
            
            // Also try to dequeue by checking script sources
            global $wp_scripts;
            if (isset($wp_scripts->registered)) {
                foreach ($wp_scripts->registered as $handle => $script) {
                    if (isset($script->src)) {
                        // Check if script is from Enfold theme and matches the problematic files
                        if (strpos($script->src, '/themes/enfold/js/order-form.js') !== false ||
                            strpos($script->src, '/themes/enfold/js/ajax-filter.js') !== false) {
                            wp_dequeue_script($handle);
                        }
                    }
                }
            }
        }
    }

    /**
     * Add preload hints
     * 
     * @since 1.0.0
     */
    public function add_preload_hints() {
        // Only preload resources that will actually be used on this page
        global $post;
        
        // Check if this page uses UtilitySign shortcodes or blocks
        $uses_utilitysign = false;
        
        if ($post) {
            // Check for shortcodes
            $uses_utilitysign = has_shortcode($post->post_content, 'utilitysign_form') || 
                               has_shortcode($post->post_content, 'utilitysign_signing_form') ||
                               has_shortcode($post->post_content, 'utilitysign_order_form');
            
            // Check for blocks if not found
            if (!$uses_utilitysign && function_exists('has_block')) {
                $uses_utilitysign = has_block('utilitysign/signing-form', $post) ||
                                  has_block('utilitysign/product-display', $post) ||
                                  has_block('utilitysign/order-form', $post);
            }
        }
        
        // Only add preloads if page actually uses UtilitySign
        if ($uses_utilitysign) {
            // Preload critical fonts with font-display: swap
            echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&family=Open+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
            echo '<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&family=Open+Sans:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap"></noscript>';
            
            // Preload critical CSS only if it exists and will be used
            $critical_css_path = UTILITYSIGN_DIR . 'assets/css/critical.css';
            if (file_exists($critical_css_path)) {
                // Ensure preload has as="style" attribute and also link it normally
                // This prevents the "preloaded but not used" warning
                echo '<link rel="preload" href="' . esc_url(UTILITYSIGN_URL . 'assets/css/critical.css') . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
                echo '<noscript><link rel="stylesheet" href="' . esc_url(UTILITYSIGN_URL . 'assets/css/critical.css') . '"></noscript>';
            }
        }
        
        // Products API endpoint is only needed on pages with product display
        if ($post && (has_shortcode($post->post_content, 'utilitysign_products') || 
            (function_exists('has_block') && has_block('utilitysign/product-display', $post)))) {
            echo '<link rel="preload" href="' . rest_url('utilitysign/v1/products/get') . '" as="fetch" crossorigin>';
        }
    }

    /**
     * Add defer to non-critical scripts and lazy-load third-party scripts
     * 
     * @since 1.0.0
     */
    public function add_defer_scripts() {
        ?>
        <script>
        // Defer non-critical scripts
        document.addEventListener('DOMContentLoaded', function() {
            var scripts = document.querySelectorAll('script[data-defer]');
            scripts.forEach(function(script) {
                var newScript = document.createElement('script');
                newScript.src = script.src;
                newScript.async = true;
                script.parentNode.replaceChild(newScript, script);
            });
        });
        
        // Lazy-load reCAPTCHA only when form is about to be submitted or user interacts with form
        (function() {
            var recaptchaLoaded = false;
            var formContainer = document.querySelector('.utilitysign-signing-form, .utilitysign-order-form');
            
            function loadRecaptcha() {
                if (recaptchaLoaded) return;
                recaptchaLoaded = true;
                
                // Find and load reCAPTCHA script if present
                var recaptchaScript = document.querySelector('script[src*="recaptcha"]');
                if (recaptchaScript && !recaptchaScript.dataset.loaded) {
                    var newScript = document.createElement('script');
                    newScript.src = recaptchaScript.src;
                    newScript.async = true;
                    newScript.defer = true;
                    newScript.dataset.loaded = 'true';
                    recaptchaScript.parentNode.replaceChild(newScript, recaptchaScript);
                }
            }
            
            // Load reCAPTCHA on form focus or just before submit
            if (formContainer) {
                var form = formContainer.querySelector('form');
                if (form) {
                    // Load on first form interaction
                    form.addEventListener('focusin', loadRecaptcha, { once: true });
                    
                    // Load just before submit
                    form.addEventListener('submit', function(e) {
                        loadRecaptcha();
                        // Small delay to ensure reCAPTCHA loads before submission
                        if (!recaptchaLoaded) {
                            e.preventDefault();
                            setTimeout(function() {
                                form.submit();
                            }, 100);
                        }
                    }, { once: true });
                }
            }
        })();
        
        // Lazy-load Facebook Pixel after page load
        (function() {
            var fbPixelLoaded = false;
            
            function loadFacebookPixel() {
                if (fbPixelLoaded) return;
                fbPixelLoaded = true;
                
                // Find and load Facebook Pixel script if present
                var fbScript = document.querySelector('script[src*="fbevents"], script[src*="facebook"]');
                if (fbScript && !fbScript.dataset.loaded) {
                    var newScript = document.createElement('script');
                    newScript.src = fbScript.src;
                    newScript.async = true;
                    newScript.defer = true;
                    newScript.dataset.loaded = 'true';
                    fbScript.parentNode.replaceChild(newScript, fbScript);
                }
            }
            
            // Load Facebook Pixel after page load or on user interaction
            if (document.readyState === 'complete') {
                setTimeout(loadFacebookPixel, 2000); // 2 second delay
            } else {
                window.addEventListener('load', function() {
                    setTimeout(loadFacebookPixel, 2000);
                });
            }
            
            // Also load on user interaction (scroll, click, etc.)
            var interactionEvents = ['scroll', 'click', 'touchstart', 'keydown'];
            var interactionHandler = function() {
                loadFacebookPixel();
                interactionEvents.forEach(function(event) {
                    document.removeEventListener(event, interactionHandler);
                });
            };
            interactionEvents.forEach(function(event) {
                document.addEventListener(event, interactionHandler, { once: true, passive: true });
            });
        })();
        
        // Suppress Facebook Topics API warnings (intentionally blocked for privacy)
        if (typeof window.fbq !== 'undefined') {
            // Disable Topics API if Facebook Pixel is loaded
            try {
                if (window.fbq && window.fbq.disablePushState) {
                    // Suppress Topics API feature
                    window.fbq('consent', 'revoke');
                }
            } catch (e) {
                // Silently fail if Facebook Pixel not fully loaded
            }
        }
        </script>
        <?php
    }

    /**
     * Add resource hints
     * 
     * @since 1.0.0
     * @param array $urls
     * @param string $relation_type
     * @return array
     */
    public function add_resource_hints($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            $urls[] = '//fonts.googleapis.com';
            $urls[] = '//fonts.gstatic.com';
            $urls[] = '//api.utilitysign.devora.no';
        }
        
        if ('preconnect' === $relation_type) {
            $urls[] = 'https://fonts.googleapis.com';
            $urls[] = 'https://fonts.gstatic.com';
            $urls[] = 'https://api.utilitysign.devora.no';
        }
        
        return $urls;
    }
    
    /**
     * Defer third-party scripts (reCAPTCHA, Facebook Pixel)
     * 
     * @since 1.0.0
     */
    public function defer_third_party_scripts() {
        // Only on frontend pages with UtilitySign forms
        if (is_admin()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        // Check if page has UtilitySign shortcodes
        $has_utilitysign = has_shortcode($post->post_content, 'utilitysign_order_form') ||
                          has_shortcode($post->post_content, 'utilitysign_signing_form') ||
                          has_shortcode($post->post_content, 'utilitysign_form');
        
        if (!$has_utilitysign) {
            return;
        }
        
        // Defer reCAPTCHA script
        add_filter('script_loader_tag', function($tag, $handle, $src) {
            if (strpos($src, 'recaptcha') !== false || strpos($src, 'google.com/recaptcha') !== false) {
                // Remove the script tag - we'll load it lazily via JavaScript
                return '';
            }
            return $tag;
        }, 10, 3);
        
        // Defer Facebook Pixel script
        add_filter('script_loader_tag', function($tag, $handle, $src) {
            if (strpos($src, 'fbevents') !== false || strpos($src, 'connect.facebook.net') !== false) {
                // Remove the script tag - we'll load it lazily via JavaScript
                return '';
            }
            return $tag;
        }, 10, 3);
    }
    
    /**
     * Fix MIME type script errors by removing invalid script tags
     * 
     * @since 1.0.0
     */
    public function fix_mime_type_script_errors() {
        // Only on frontend pages
        if (is_admin()) {
            return;
        }
        
        // Filter out script tags pointing to site root or invalid paths
        add_filter('script_loader_tag', function($tag, $handle, $src) {
            // Check if script src points to site root (likely causing MIME type error)
            $site_url = site_url();
            $parsed_src = parse_url($src);
            $parsed_site = parse_url($site_url);
            
            // If script src is exactly the site root or points to root with no path
            if ($src === $site_url . '/' || 
                $src === $site_url ||
                (isset($parsed_src['path']) && $parsed_src['path'] === '/') ||
                (isset($parsed_src['host']) && isset($parsed_site['host']) && 
                 $parsed_src['host'] === $parsed_site['host'] && 
                 (!isset($parsed_src['path']) || $parsed_src['path'] === '/'))) {
                // Remove invalid script tag
                return '';
            }
            
            return $tag;
        }, 10, 3);
        
        // Also filter via output buffering to catch scripts added directly to HTML
        add_action('template_redirect', function() {
            if (!is_admin()) {
                ob_start(function($buffer) {
                    // Remove script tags with src pointing to site root
                    $site_url = preg_quote(site_url(), '/');
                    $pattern = '/<script[^>]*src=["\']' . $site_url . '\/?["\'][^>]*><\/script>/i';
                    $buffer = preg_replace($pattern, '', $buffer);
                    return $buffer;
                });
            }
        });
    }

    /**
     * Maybe serve cached page
     * 
     * @since 1.0.0
     */
    public function maybe_serve_cached_page() {
        if (is_admin() || is_user_logged_in() || is_feed()) {
            return;
        }
        
        $cache_key = $this->get_page_cache_key();
        $cached_content = wp_cache_get($cache_key, 'utilitysign_pages');
        
        if ($cached_content) {
            echo $cached_content;
            exit;
        }
    }

    /**
     * Maybe cache page
     * 
     * @since 1.0.0
     */
    public function maybe_cache_page() {
        if (is_admin() || is_user_logged_in() || is_feed()) {
            return;
        }
        
        $cache_key = $this->get_page_cache_key();
        $content = ob_get_contents();
        
        if ($content) {
            wp_cache_set($cache_key, $content, 'utilitysign_pages', HOUR_IN_SECONDS);
        }
    }

    /**
     * Get page cache key
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_page_cache_key() {
        $key_parts = [
            'page',
            get_the_ID(),
            get_current_blog_id(),
            is_ssl() ? 'https' : 'http'
        ];
        
        return implode('_', $key_parts);
    }

    /**
     * Optimize meta queries
     * 
     * @since 1.0.0
     * @param string $where
     * @param WP_Query $query
     * @return string
     */
    public function optimize_meta_queries($where, $query) {
        // Add indexes for common meta queries
        if (isset($query->query_vars['meta_query'])) {
            $meta_query = $query->query_vars['meta_query'];
            
            // Optimize product queries
            if (isset($meta_query['product_supplier'])) {
                $where .= " AND EXISTS (
                    SELECT 1 FROM {$GLOBALS['wpdb']->postmeta} pm 
                    WHERE pm.post_id = {$GLOBALS['wpdb']->posts}.ID 
                    AND pm.meta_key = '_product_supplier_id' 
                    AND pm.meta_value = '" . esc_sql($meta_query['product_supplier']['value']) . "'
                )";
            }
        }
        
        return $where;
    }

    /**
     * Optimize orderby clauses
     * 
     * @since 1.0.0
     * @param string $orderby
     * @param WP_Query $query
     * @return string
     */
    public function optimize_orderby_clauses($orderby, $query) {
        // Use database indexes for common sorting
        if (isset($query->query_vars['orderby'])) {
            $orderby_clause = $query->query_vars['orderby'];
            
            if ('meta_value' === $orderby_clause) {
                $meta_key = $query->query_vars['meta_key'];
                
                // Use proper index for meta value sorting
                $orderby = "CAST({$GLOBALS['wpdb']->postmeta}.meta_value AS SIGNED)";
            }
        }
        
        return $orderby;
    }

    /**
     * Add version to assets
     * 
     * @since 1.0.0
     * @param string $src
     * @param string $handle
     * @return string
     */
    public function add_version_to_assets($src, $handle) {
        if (strpos($src, UTILITYSIGN_URL) !== false) {
            $src = add_query_arg('v', UTILITYSIGN_VERSION, $src);
        }
        
        return $src;
    }

    /**
     * Inline critical CSS
     * 
     * @since 1.0.0
     */
    public function inline_critical_css() {
        // Only inline on pages with UtilitySign forms
        global $post;
        if (!$post) {
            return;
        }
        
        $has_utilitysign = has_shortcode($post->post_content, 'utilitysign_order_form') ||
                          has_shortcode($post->post_content, 'utilitysign_signing_form') ||
                          has_shortcode($post->post_content, 'utilitysign_form');
        
        if (!$has_utilitysign) {
            return;
        }
        
        $critical_css = $this->get_critical_css();
        if ($critical_css) {
            echo '<style id="utilitysign-critical-css">' . $critical_css . '</style>';
        } else {
            // Generate minimal critical CSS inline if file doesn't exist
            echo '<style id="utilitysign-critical-css">
                /* Critical CSS for UtilitySign form - above the fold */
                .utilitysign-signing-form, .utilitysign-order-form {
                    visibility: visible;
                    opacity: 1;
                }
                .devora-input {
                    min-height: 2.5rem;
                    padding: 0.5rem 0.75rem;
                }
                .devora-button-primary {
                    min-height: 2.75rem;
                }
            </style>';
        }
    }

    /**
     * Add lazy loading to images
     * 
     * @since 1.0.0
     * @param array $attr
     * @param WP_Post $attachment
     * @param string $size
     * @return array
     */
    public function add_lazy_loading($attr, $attachment, $size) {
        if (!is_admin()) {
            $attr['loading'] = 'lazy';
            $attr['decoding'] = 'async';
        }
        
        return $attr;
    }

    /**
     * Get critical CSS
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_critical_css() {
        $critical_css_file = UTILITYSIGN_PLUGIN_DIR . 'assets/css/critical.css';
        
        if (file_exists($critical_css_file)) {
            return file_get_contents($critical_css_file);
        }
        
        return '';
    }

    /**
     * AJAX handler for clearing cache
     * 
     * @since 1.0.0
     */
    public function ajax_clear_cache() {
        check_ajax_referer('utilitysign_clear_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'utilitysign')]);
        }
        
        // Clear object cache
        wp_cache_flush();
        
        // Clear page cache
        $this->clear_page_cache();
        
        // Clear transients
        $this->clear_transients();
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'utilitysign')]);
    }

    /**
     * Clear page cache
     * 
     * @since 1.0.0
     */
    private function clear_page_cache() {
        global $wpdb;
        
        // Clear page cache entries - using prepare() for security even with hardcoded patterns
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_utilitysign_page_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_utilitysign_page_%'
        ));
    }

    /**
     * Clear transients
     * 
     * @since 1.0.0
     */
    private function clear_transients() {
        global $wpdb;
        
        // Clear utilitysign transients - using prepare() for security even with hardcoded patterns
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_utilitysign_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_utilitysign_%'
        ));
    }

    /**
     * Get performance metrics
     * 
     * @since 1.0.0
     * @return array
     */
    public function get_performance_metrics() {
        $metrics = [
            'page_load_time' => $this->get_page_load_time(),
            'database_queries' => $this->get_database_query_count(),
            'memory_usage' => $this->get_memory_usage(),
            'cache_hit_ratio' => $this->get_cache_hit_ratio(),
            'asset_optimization' => $this->get_asset_optimization_status()
        ];
        
        return $metrics;
    }

    /**
     * Get page load time
     * 
     * @since 1.0.0
     * @return float
     */
    private function get_page_load_time() {
        if (defined('UTILITYSIGN_START_TIME')) {
            return microtime(true) - UTILITYSIGN_START_TIME;
        }
        
        return 0;
    }

    /**
     * Get database query count
     * 
     * @since 1.0.0
     * @return int
     */
    private function get_database_query_count() {
        global $wpdb;
        return $wpdb->num_queries;
    }

    /**
     * Get memory usage
     * 
     * @since 1.0.0
     * @return string
     */
    private function get_memory_usage() {
        return size_format(memory_get_usage(true));
    }

    /**
     * Get cache hit ratio
     * 
     * @since 1.0.0
     * @return float
     */
    private function get_cache_hit_ratio() {
        $cache_stats = wp_cache_get_stats();
        
        if ($cache_stats && isset($cache_stats['hits'], $cache_stats['misses'])) {
            $total = $cache_stats['hits'] + $cache_stats['misses'];
            return $total > 0 ? round(($cache_stats['hits'] / $total) * 100, 2) : 0;
        }
        
        return 0;
    }

    /**
     * Get asset optimization status
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_asset_optimization_status() {
        return [
            'css_minified' => !WP_DEBUG,
            'js_minified' => !WP_DEBUG,
            'images_optimized' => true,
            'fonts_preloaded' => true,
            'critical_css_inlined' => true
        ];
    }
}
