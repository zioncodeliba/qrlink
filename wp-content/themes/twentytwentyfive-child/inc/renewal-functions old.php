<?php

function display_license_renewal_status() {
    if (!is_user_logged_in()) {
        return '<p style="color: red;">עליך להתחבר כדי לבדוק את מצב הרישיון שלך.</p>';
    }

    $user_id = get_current_user_id();
    global $wpdb;

    // שליפת כל ההזמנות של המשתמש עם רישיונות
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT p.ID 
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending','draft')
        AND p.post_author = %d
        AND pm.meta_key = '_license_key'
        ORDER BY p.post_date DESC
    ", $user_id));

    if (!$orders) {
        return '<p style="color: red;">לא נמצאו הזמנות עם רישיון.</p>';
    }

    ob_start(); // התחלת באפר ליצירת תוכן HTML
    ?>
    
    <style>
        .license-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 16px;
            text-align: left;
        }
        .license-table th, .license-table td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        .license-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        .valid-license {
            color: green;
        }
        .expired-license {
            color: red;
        }
    </style>

    <h3>📜 רישיונות פעילים</h3>
    <table class="license-table">
        <thead>
            <tr>
                <th>מפתח רישיון</th>
                <th>תוקף עד</th>
                <th>סטטוס</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $current_date = date('Y-m-d');
            $has_valid_license = false;

            foreach ($orders as $order) {
                $license_key = get_post_meta($order->ID, '_license_key', true);
                $expiration_date = get_post_meta($order->ID, '_license_expiration', true);

                if (!$license_key || !$expiration_date) continue; // דלג אם אין נתונים

                $status = ($current_date <= $expiration_date) ? '<span class="valid-license">✅ בתוקף</span>' : '<span class="expired-license">❌ פג תוקף</span>';
                // $renew_license_button = ($current_date > $expiration_date) ? "<a href='https://woocommerce-761776-5227801.cloudwaysapps.com/checkout/?add-to-cart=12&renew_license=$license_key' class='button'>חידוש רישיון</a>" : "";
                $renew_license_button = ($current_date > $expiration_date) ? '<a href="' . esc_url(site_url('/checkout/?add-to-cart=12')) . '" class="button">🔄 חדש רישיון עכשיו</a>';
                
                $status_new = $renew_license_button == "" ? $status : $status."<br>".$renew_license_button;
                if ($current_date <= $expiration_date) {
                    $has_valid_license = true;
                }

                echo "<tr>
                        <td>{$license_key}</td>
                        <td>{$expiration_date}</td>
                        <td>{$status_new}</td>
                    </tr>";
            }
            ?>
        </tbody>
    </table>

    <?php if (!$has_valid_license) : ?>
        <p style="color: red; font-weight: bold;">⚠️ אין לך רישיונות פעילים כרגע. אנא חידש את הרישיון.</p>
    <?php endif; ?>


    <?php
    return ob_get_clean(); // החזרת התוכן שנשמר בבאפר
}

// עדכון ה-Shortcode
add_shortcode('license_status', 'display_license_renewal_status');


function send_license_expiration_notifications() {
    global $wpdb;

    $current_date = date('Y-m-d');
    $warning_date = date('Y-m-d', strtotime('+7 days')); // הודעה 7 ימים לפני פקיעה

    // שליפת רישיונות שפג תוקפם או עומדים לפוג
    $expiring_licenses = $wpdb->get_results($wpdb->prepare("
        SELECT pm.post_id, pm.meta_value as expiration_date, p.post_author 
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_license_expiration'
        AND (pm.meta_value <= %s OR pm.meta_value = %s)
    ", $current_date, $warning_date));

    foreach ($expiring_licenses as $license) {
        $user_info = get_userdata($license->post_author);
        $email = $user_info->user_email;
        $license_key = get_post_meta($license->post_id, '_license_key', true);

        if ($license->expiration_date == $warning_date) {
            $subject = "🔔 תזכורת: הרישיון שלך עומד לפוג";
            $message = "שלום {$user_info->display_name},\n\n
            הרישיון שלך עומד לפוג ב-{$license->expiration_date}.\n
            מספר הרישיון שלך: {$license_key}\n
            נא לחדש את הרישיון בהקדם כדי להמשיך להשתמש בשירותים שלנו.\n
            קישור לחידוש הרישיון: " . site_url('/renew-license/');
        } else {
            $subject = "⚠️ שים לב: הרישיון שלך פג!";
            $message = "שלום {$user_info->display_name},\n\n
            הרישיון שלך פג ב-{$license->expiration_date}.\n
            מספר הרישיון שלך: {$license_key}\n
            כדי להמשיך להשתמש בשירותים שלנו, עליך לחדש את הרישיון.\n
            קישור לחידוש הרישיון: " . site_url('/renew-license/');
        }

        // שליחת אימייל
        wp_mail($email, $subject, $message);
    }
}
add_action('check_expiring_licenses', 'send_license_expiration_notifications');


