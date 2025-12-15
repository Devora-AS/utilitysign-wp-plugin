<?php
namespace UtilitySign\REST;

use UtilitySign\Traits\Base;

/**
 * Products REST API Controller
 * 
 * @package UtilitySign
 * @since 1.0.0
 */
class ProductsController {
    use Base;

    /**
     * Initialize products controller
     * 
     * @since 1.0.0
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     * 
     * @since 1.0.0
     */
    public function register_routes() {
        register_rest_route('utilitysign/v1', '/products/get', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'supplier_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('utilitysign/v1', '/products/suppliers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_suppliers'],
            'permission_callback' => '__return_true',
        ]);

        // Get single product by ID (UUID from backend API)
        register_rest_route('utilitysign/v1', '/products/(?P<id>[a-f0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_by_id'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        // Validate UUID format
                        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get products
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_products($request) {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $search = $request->get_param('search');
            $supplier_id = $request->get_param('supplier_id');
            $status = $request->get_param('status');

            $args = [
                'post_type' => 'utilitysign_product',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'meta_query' => [],
            ];

            if ($search) {
                $args['s'] = $search;
            }

            if ($supplier_id) {
                $args['meta_query'][] = [
                    'key' => '_supplier_id',
                    'value' => $supplier_id,
                    'compare' => '='
                ];
            }

            if ($status) {
                $args['meta_query'][] = [
                    'key' => '_product_status',
                    'value' => $status,
                    'compare' => '='
                ];
            }

            $query = new \WP_Query($args);
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
                        'featured_image' => get_the_post_thumbnail_url($post_id, 'medium'),
                        'price' => get_post_meta($post_id, '_product_price', true),
                        'supplier_id' => get_post_meta($post_id, '_supplier_id', true),
                        'status' => get_post_meta($post_id, '_product_status', true) ?: 'active',
                        'created_at' => get_the_date('c'),
                        'updated_at' => get_the_modified_date('c'),
                    ];
                }
            }

            wp_reset_postdata();

            return new \WP_REST_Response([
                'success' => true,
                'data' => $products,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $query->found_posts,
                    'total_pages' => $query->max_num_pages,
                ]
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get suppliers
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_suppliers($request) {
        try {
            $args = [
                'post_type' => 'utilitysign_supplier',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ];

            $query = new \WP_Query($args);
            $suppliers = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    $suppliers[] = [
                        'id' => $post_id,
                        'name' => get_the_title(),
                        'description' => get_the_content(),
                        'logo' => get_post_meta($post_id, '_supplier_logo', true),
                        'contact_email' => get_post_meta($post_id, '_supplier_contact_email', true),
                        'contact_phone' => get_post_meta($post_id, '_supplier_contact_phone', true),
                        'website' => get_post_meta($post_id, '_supplier_website', true),
                        'status' => get_post_meta($post_id, '_supplier_status', true) ?: 'active',
                        'created_at' => get_the_date('c'),
                    ];
                }
            }

            wp_reset_postdata();

            return new \WP_REST_Response([
                'success' => true,
                'data' => $suppliers
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single product by ID (UUID from backend API)
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_product_by_id($request) {
        try {
            $product_id = $request->get_param('id');
            
            // Validate UUID format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $product_id)) {
                return new \WP_Error(
                    'rest_invalid_product_id',
                    __('Invalid product ID format. Expected UUID.', 'utilitysign'),
                    array('status' => 400)
                );
            }
            
            // Check if it's a backend API product (UUID format)
            // If it's a WordPress post ID (numeric), fetch from WordPress
            if (is_numeric($product_id)) {
                $product = get_post($product_id);
                
                if (!$product || $product->post_type !== 'utilitysign_product') {
                    return new \WP_Error(
                        'rest_product_not_found',
                        __('Product not found', 'utilitysign'),
                        array('status' => 404)
                    );
                }
                
                $response_data = array(
                    'success' => true,
                    'data' => array(
                        'id' => $product_id,
                        'name' => get_the_title($product_id),
                        'title' => get_the_title($product_id),
                        'description' => get_the_content(null, false, $product_id),
                        'supplier_id' => get_post_meta($product_id, '_supplier_id', true),
                    )
                );
                
                return rest_ensure_response($response_data);
            }
            
            // Backend API product - fetch from backend
            // Wrap ApiClient instantiation in try-catch to handle class loading errors
            try {
                $api_client = new \UtilitySign\Core\ApiClient();
            } catch (\Exception $e) {
                // #region agent log
                $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
                $log_entry = json_encode(array('id'=>'log_'.time().'_php1','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:283','message'=>'ApiClient instantiation failed','data'=>array('error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'))."\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                // #endregion
                error_log('UtilitySign ProductsController: Failed to instantiate ApiClient - ' . $e->getMessage());
                return new \WP_Error(
                    'rest_api_client_error',
                    __('Backend API client initialization failed', 'utilitysign'),
                    array('status' => 500)
                );
            }
            
            // DEBUG: Log the configuration status
            $config_status = $api_client->get_config_status();
            error_log('UtilitySign API Config Status: ' . json_encode($config_status));
            
            // #region agent log
            $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
            $log_entry = json_encode(array('id'=>'log_'.time().'_php2','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:293','message'=>'ApiClient config status','data'=>array('apiUrl'=>$config_status['api_url'],'hasPluginKey'=>$config_status['has_plugin_key'],'hasPluginSecret'=>$config_status['has_plugin_secret'],'isConfigured'=>$config_status['is_configured'],'productId'=>$product_id),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'B'))."\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            // #endregion
            
            if (!$api_client->is_configured()) {
                // #region agent log
                $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
                $log_entry = json_encode(array('id'=>'log_'.time().'_php3','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:296','message'=>'ApiClient not configured','data'=>array('hasKey'=>$config_status['has_plugin_key'],'hasSecret'=>$config_status['has_plugin_secret']),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'B'))."\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                // #endregion
                $missing = array();
                if (!$config_status['has_plugin_key']) {
                    $missing[] = 'API Key';
                }
                if (!$config_status['has_plugin_secret']) {
                    $missing[] = 'API Secret';
                }
                error_log('UtilitySign API NOT CONFIGURED - Missing: ' . implode(', ', $missing) . ' - has_key: ' . ($config_status['has_plugin_key'] ? 'YES' : 'NO') . ', has_secret: ' . ($config_status['has_plugin_secret'] ? 'YES' : 'NO'));
                return new \WP_Error(
                    'rest_api_not_configured',
                    sprintf(__('Backend API not configured. Missing: %s. Please configure the plugin in WordPress admin settings.', 'utilitysign'), implode(', ', $missing)),
                    array('status' => 500)
                );
            }
            
            // #region agent log
            $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
            $log_entry = json_encode(array('id'=>'log_'.time().'_php4','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:304','message'=>'Calling api_client->get_product','data'=>array('productId'=>$product_id,'endpoint'=>'/api/v1/wordpress/products/'.$product_id),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            // #endregion
            
            $product_data = $api_client->get_product($product_id);
            
            // #region agent log
            $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
            $log_entry = json_encode(array('id'=>'log_'.time().'_php5','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:305','message'=>'get_product response received','data'=>array('isWpError'=>is_wp_error($product_data),'errorCode'=>is_wp_error($product_data)?$product_data->get_error_code():null,'errorMessage'=>is_wp_error($product_data)?$product_data->get_error_message():null,'isArray'=>is_array($product_data),'hasData'=>!empty($product_data),'dataKeys'=>is_array($product_data)?array_keys($product_data):array()),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            // #endregion
            
            if (is_wp_error($product_data)) {
                $error_message = $product_data->get_error_message();
                $error_code = $product_data->get_error_code();
                $error_data = $product_data->get_error_data();
                
                // #region agent log
                $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
                $log_entry = json_encode(array('id'=>'log_'.time().'_php6','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:307','message'=>'get_product returned WP_Error','data'=>array('errorCode'=>$error_code,'errorMessage'=>$error_message,'errorStatus'=>isset($error_data['status'])?$error_data['status']:null),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'))."\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                // #endregion
                
                error_log('UtilitySign ProductsController: API error - ' . $error_message);
                
                // Preserve original error status if available
                $status = isset($error_data['status']) ? $error_data['status'] : 500;
                
                return new \WP_Error(
                    $error_code ?: 'rest_api_error',
                    $error_message,
                    array('status' => $status)
                );
            }
            
            if (!$product_data || !is_array($product_data)) {
                // #region agent log
                $log_file = '/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/.cursor/debug.log';
                $log_entry = json_encode(array('id'=>'log_'.time().'_php7','timestamp'=>round(microtime(true)*1000),'location'=>'ProductsController.php:323','message'=>'Invalid product data format','data'=>array('isArray'=>is_array($product_data),'isEmpty'=>empty($product_data),'type'=>gettype($product_data)),'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'D'))."\n";
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                // #endregion
                error_log('UtilitySign ProductsController: Invalid product data received from backend');
                return new \WP_Error(
                    'rest_invalid_response',
                    __('Invalid product data received from backend', 'utilitysign'),
                    array('status' => 500)
                );
            }
            
            // Extract product name from backend response
            $product_name = isset($product_data['name']) 
                ? $product_data['name'] 
                : (isset($product_data['title']) ? $product_data['title'] : '');
            
            $response_data = array(
                'success' => true,
                'data' => array(
                    'id' => $product_id,
                    'name' => $product_name,
                    'title' => $product_name,
                    'description' => isset($product_data['description']) ? $product_data['description'] : '',
                    'supplier_id' => isset($product_data['supplierId']) ? $product_data['supplierId'] : null,
                )
            );
            
            return rest_ensure_response($response_data);

        } catch (\Exception $e) {
            error_log('UtilitySign ProductsController: Unhandled exception - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new \WP_Error(
                'rest_internal_error',
                __('An internal error occurred while processing the request', 'utilitysign'),
                array('status' => 500)
            );
        } catch (\Error $e) {
            // Catch PHP 7+ fatal errors (TypeError, etc.)
            error_log('UtilitySign ProductsController: Fatal error - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new \WP_Error(
                'rest_fatal_error',
                __('A fatal error occurred while processing the request', 'utilitysign'),
                array('status' => 500)
            );
        }
    }

    /**
     * Check permissions for products endpoints
     * 
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_permissions($request) {
        // Allow public access to products data
        // This bypasses WordPress's default authentication requirements
        return true;
    }
}
