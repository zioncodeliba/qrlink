<?php
// טוען את קובץ ה-CSS של עיצוב האב
function my_child_theme_enqueue_styles() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

// סקריפט JS לעמוד ניהול רישיון
function enqueue_license_admin_script($hook) {
    if ($hook !== 'woocommerce_page_license-management') {
        return;
    }

    wp_enqueue_script(
        'license-admin-js',
        get_stylesheet_directory_uri() . '/admin-ajax.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('license-admin-js', 'licenseAjax', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
}
add_action('admin_enqueue_scripts', 'enqueue_license_admin_script');

// קבצים נוספים
require_once get_stylesheet_directory() . '/inc/license-functions.php';
require_once get_stylesheet_directory() . '/inc/cron-functions.php';
require_once get_stylesheet_directory() . '/inc/renewal-functions.php';
require_once get_stylesheet_directory() . '/inc/dasboard-functions.php';


// 🟢 רישום כל ה-APIים
add_action('rest_api_init', function () {
    register_rest_route('customqr', '/license-check', [
        'methods'  => 'POST',
        'callback' => 'customqr_license_check_handler',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('customqr/v1', '/change-domain', [
        'methods'  => 'POST',
        'callback' => 'customqr_change_license_domain',
        'permission_callback' => '__return_true',
    ]);
});


// 🟩 בדיקת רישיון ודומיין
function customqr_license_check_handler(WP_REST_Request $request) {
    global $wpdb;

    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = esc_url_raw($request->get_param('site_url'));
    $parsed_domain = parse_url($site_url, PHP_URL_HOST);
    $parsed_domain = preg_replace('/^www\./', '', strtolower($parsed_domain));

    if (empty($license_key)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Missing parameters - license_key'], 400);
    }
    // if (empty($parsed_domain)) {
    //     return new WP_REST_Response(['status' => 'error', 'message' => 'Missing parameters - parsed_domain'], 400);
    // }

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, 
               pm1.meta_value as status, 
               pm2.meta_value as expiry,
               pm4.meta_value as domain
        FROM {$wpdb->prefix}posts p
        LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_status'
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_expiration'
        LEFT JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_license_key'
        LEFT JOIN {$wpdb->prefix}postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_license_domain'
        WHERE pm3.meta_value = %s
        LIMIT 1
    ", $license_key));

    if (empty($results)) {
        return new WP_REST_Response(['status' => 'invalid'], 200);
    }

    $license = $results[0];

    if ($license->status !== 'active') {
        return new WP_REST_Response(['status' => 'inactive'], 200);
    }

    $today = date('Y-m-d');
    if (!empty($license->expiry) && $license->expiry < $today) {
        return new WP_REST_Response(['status' => 'expired'], 200);
    }

    // רישום דומיין אוטומטי אם לא קיים
    $stored_domain = preg_replace('/^www\./', '', strtolower($license->domain));
    if (empty($stored_domain)) {
        update_post_meta($license->ID, '_license_domain', $parsed_domain);
        $stored_domain = $parsed_domain;
    }

    // בדיקת התאמה
    if (!empty($stored_domain) && $stored_domain !== $parsed_domain) {
        return new WP_REST_Response([
            'status' => 'domain_mismatch',
            'expected' => $stored_domain,
            'actual' => $parsed_domain
        ], 200);
    }

    return new WP_REST_Response([
        'status' => 'active',
        'expires' => $license->expiry,
        'site_verified' => true,
        'domain' => $stored_domain
    ], 200);
}


// 🟦 עדכון דומיין ידני
function customqr_change_license_domain(WP_REST_Request $request) {
    global $wpdb;

    $license_key = sanitize_text_field($request->get_param('license_key'));
    $new_domain = parse_url($request->get_param('new_domain'), PHP_URL_HOST);
    $new_domain = preg_replace('/^www\./', '', strtolower($new_domain));

    if (empty($license_key) || empty($new_domain)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Missing parameters'], 400);
    }

    $result = $wpdb->get_row($wpdb->prepare("
        SELECT p.ID, pm1.meta_value as status
        FROM {$wpdb->prefix}posts p
        LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_status'
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_key'
        WHERE pm2.meta_value = %s
        LIMIT 1
    ", $license_key));

    if (!$result) {
        return new WP_REST_Response(['success' => false, 'message' => 'License not found'], 404);
    }

    if ($result->status !== 'active') {
        return new WP_REST_Response(['success' => false, 'message' => 'License is not active'], 403);
    }

    update_post_meta($result->ID, '_license_domain', $new_domain);

    return new WP_REST_Response(['success' => true, 'new_domain' => $new_domain], 200);
}
