<?php
// טוען את קובץ ה-CSS של עיצוב האב
function my_child_theme_enqueue_styles() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

// // לדוגמה, כלול את כל הקבצים מהתיקייה inc
// foreach (glob(get_stylesheet_directory() . '/inc/*.php') as $file) {
//     require_once $file;
// }


function enqueue_license_admin_script($hook) {
    // בדוק שזה באמת עמוד ניהול הרישיונות
    if ($hook !== 'woocommerce_page_license-management') {
        return;
    }

    wp_enqueue_script(
        'license-admin-js',
        get_stylesheet_directory_uri() . '/admin-ajax.js',
        array('jquery'),
        null,
        true
    );

    wp_localize_script('license-admin-js', 'licenseAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_license_admin_script');



require_once get_stylesheet_directory() . '/inc/license-functions.php';
require_once get_stylesheet_directory() . '/inc/cron-functions.php';
require_once get_stylesheet_directory() . '/inc/renewal-functions.php';
require_once get_stylesheet_directory() . '/inc/dasboard-functions.php';

add_action('rest_api_init', function () {
    register_rest_route('customqr', '/license-check', array(
        'methods'  => 'POST',
        'callback' => 'customqr_license_check_handler',
        'permission_callback' => '__return_true', // פתוח לקריאה מהפלאגין
    ));
});

function customqr_license_check_handler(WP_REST_Request $request) {
    $license_key = sanitize_text_field($request->get_param('license_key'));
    $site_url = esc_url_raw($request->get_param('site_url'));

    if (empty($license_key) || empty($site_url)) {
        return new WP_REST_Response(['status' => 'error', 'message' => 'Missing parameters'], 400);
    }

    global $wpdb;

    // חפש את ההזמנה שמכילה את מפתח הרישיון הזה
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, pm1.meta_value as status, pm2.meta_value as expiry
        FROM {$wpdb->prefix}posts p
        LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_status'
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_expiration'
        LEFT JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_license_key'
        WHERE pm3.meta_value = %s
        LIMIT 1
    ", $license_key));

    if (empty($results)) {
        return new WP_REST_Response(['status' => 'invalid'], 200);
    }

    $license = $results[0];

    // בדוק אם סטטוס פעיל
    if ($license->status !== 'active') {
        return $results;

        // return new WP_REST_Response(['status' => 'inactive'], 200);
    }

    // בדוק אם פג תוקף
    $today = date('Y-m-d');
    if (!empty($license->expiry) && $license->expiry < $today) {
        return new WP_REST_Response(['status' => 'expired'], 200);
    }

    // אם הכל תקין
    return new WP_REST_Response([
        'status' => 'active',
        'expires' => $license->expiry,
        'site_verified' => true, // אפשר בהמשך לקשר לרשימת דומיינים מותרים
    ], 200);
}
