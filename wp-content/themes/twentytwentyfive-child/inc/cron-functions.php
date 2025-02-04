<?php

// יצירת קרון ג'וב לבדיקה יומית של רישיונות שפג תוקפם
if (!wp_next_scheduled('check_expired_licenses')) {
    wp_schedule_event(time(), 'daily', 'check_expired_licenses');
}
add_action('check_expired_licenses', 'handle_expired_licenses');

function handle_expired_licenses() {
    global $wpdb;

    $today = date('Y-m-d');
    $licenses = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}postmeta 
        WHERE meta_key = '_license_expiry_date' 
        AND meta_value <= '$today'
    ");

    foreach ($licenses as $license) {
        $post_id = $license->post_id;
        update_post_meta($post_id, '_license_status', 'expired'); // סימון הרישיון כפג תוקף

        // שליחת התראה ללקוח
        $order = wc_get_order($post_id);
        $customer_email = $order->get_billing_email();
        wp_mail($customer_email, "הרישיון שלך פג תוקף", "הרישיון שלך לפלאגין פג תוקף. לחץ כאן כדי לחדש.");
    }
}


