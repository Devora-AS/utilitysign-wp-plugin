<?php
echo "Testing UtilitySign plugin with basic WordPress functions...\n";

// Mock WordPress functions
function add_shortcode($tag, $callback) {
    global $shortcodes;
    $shortcodes[$tag] = $callback;
    echo "Shortcode registered: $tag\n";
}

function shortcode_exists($tag) {
    global $shortcodes;
    return isset($shortcodes[$tag]);
}

function apply_filters($hook, $value, ...$args) {
    return $value;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    global $filters;
    $filters[$hook][] = $callback;
    echo "Filter added: $hook\n";
}

function wp_script_is($handle, $list = "enqueued") {
    return false;
}

function wp_localize_script($handle, $object_name, $l10n) {
    // Mock - do nothing
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

function sanitize_text_field($str) {
    return trim($str);
}

function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function esc_url_raw($url) {
    return esc_url($url);
}

function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

function esc_textarea($text) {
    return esc_html($text);
}

function __($text, $domain = 'default') {
    return $text;
}

function _e($text, $domain = 'default') {
    echo $text;
}

function _x($text, $context, $domain = 'default') {
    return $text;
}

function _ex($text, $context, $domain = 'default') {
    echo $text;
}

function _n($single, $plural, $number, $domain = 'default') {
    return ($number == 1) ? $single : $plural;
}

function _nx($single, $plural, $number, $context, $domain = 'default') {
    return ($number == 1) ? $single : $plural;
}

function load_plugin_textdomain($domain, $deprecated, $plugin_rel_path) {
    // Mock - do nothing
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    global $actions;
    $actions[$hook][] = $callback;
    echo "Action added: $hook\n";
}

function register_activation_hook($file, $callback) {
    // Mock - do nothing
}

function register_deactivation_hook($file, $callback) {
    // Mock - do nothing
}

function wp_parse_args($args, $defaults = []) {
    if (is_object($args)) {
        $args = get_object_vars($args);
    }
    return array_merge($defaults, $args);
}

function get_option($option, $default = false) {
    global $wp_options;
    return isset($wp_options[$option]) ? $wp_options[$option] : $default;
}

function update_option($option, $value) {
    global $wp_options;
    $wp_options[$option] = $value;
    return true;
}

function do_action($hook, ...$args) {
    global $actions;
    if (isset($actions[$hook])) {
        foreach ($actions[$hook] as $callback) {
            call_user_func_array($callback, $args);
        }
    }
}

function is_admin() {
    return false; // Mock - not admin
}

function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
    return 'mock_password_' . $length;
}

function wp_create_nonce($action = -1) {
    return 'mock_nonce_' . $action;
}

function wp_verify_nonce($nonce, $action = -1) {
    return strpos($nonce, 'mock_nonce_') === 0;
}

function wp_die($message = '', $title = '', $args = []) {
    echo "WP Die: $message";
    exit;
}

function wp_redirect($location, $status = 302) {
    echo "Redirect to: $location";
    exit;
}

function wp_safe_redirect($location, $status = 302) {
    return wp_redirect($location, $status);
}

function wp_get_current_user() {
    return (object) ['ID' => 1, 'user_login' => 'admin'];
}

function current_user_can($capability) {
    return true;
}

function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
    echo "Script enqueued: $handle\n";
}

function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
    echo "Style enqueued: $handle\n";
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false) {
    echo "Script registered: $handle\n";
}

function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all') {
    echo "Style registered: $handle\n";
}

function wp_create_user($username, $password, $email = '') {
    return 1;
}

function wp_insert_user($userdata) {
    return 1;
}

function wp_update_user($userdata) {
    return 1;
}

function wp_delete_user($id, $reassign = null) {
    return true;
}

function wp_get_user_by($field, $value) {
    return (object) ['ID' => 1, 'user_login' => 'admin'];
}

function wp_set_current_user($id, $name = '') {
    return true;
}

function wp_logout() {
    return true;
}

function wp_login($username, $password, $remember = false) {
    return true;
}

function wp_authenticate($username, $password) {
    return (object) ['ID' => 1, 'user_login' => 'admin'];
}

function wp_check_password($password, $hash, $user_id = '') {
    return true;
}

function wp_hash_password($password) {
    return 'hashed_' . $password;
}

function wp_set_password($password, $user_id) {
    return true;
}

function wp_rand($min = 0, $max = 0) {
    return rand($min, $max);
}

function wp_parse_url($url, $component = -1) {
    return parse_url($url, $component);
}

function wp_remote_get($url, $args = []) {
    return ['body' => 'mock response', 'response' => ['code' => 200]];
}

function wp_remote_post($url, $args = []) {
    return ['body' => 'mock response', 'response' => ['code' => 200]];
}

function wp_remote_retrieve_body($response) {
    return 'mock response body';
}

function wp_remote_retrieve_response_code($response) {
    return 200;
}

function is_wp_error($thing) {
    return false;
}

function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
    echo "Mail sent to: $to\n";
    return true;
}

function wp_json_encode($data, $options = 0, $depth = 512) {
    return json_encode($data, $options, $depth);
}

function wp_json_decode($json, $assoc = false, $depth = 512, $options = 0) {
    return json_decode($json, $assoc, $depth, $options);
}

function wp_send_json($response, $status_code = null) {
    echo json_encode($response);
    exit;
}

function wp_send_json_success($data = null, $status_code = null) {
    wp_send_json(['success' => true, 'data' => $data], $status_code);
}

function wp_send_json_error($data = null, $status_code = null) {
    wp_send_json(['success' => false, 'data' => $data], $status_code);
}

// Initialize global variables
$shortcodes = [];
$filters = [];
$actions = [];
$wp_options = [];

echo "Loading plugin...\n";

// Load the plugin
require_once 'utilitysign.php';

echo "Action added: plugins_loaded\n";
echo "Triggering plugins_loaded action...\n";

// Trigger the plugins_loaded action
do_action('plugins_loaded');

echo "Plugin loaded successfully!\n";
echo "Test completed.\n";
?>
