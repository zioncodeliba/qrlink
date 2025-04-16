<?php

function generate_license_key() {
    return strtoupper(bin2hex(random_bytes(10))); // מפתח רנדומלי
}

// הוספת עמודות לטבלת ההזמנות
function add_license_column_to_orders($columns) {
    error_log("Running add_license_column_to_orders : " . $columns);
    $columns['license_key'] = 'מפתח רישיון';
    $columns['license_expiration'] = 'תאריך תפוגה';
    return $columns;
}
add_filter('manage_woocommerce_page_wc-orders_columns', 'add_license_column_to_orders', 20);


function display_license_column_data($column, $post_id) {
    // global $wpdb;
    // error_log("🔍 Running display_license_column_data for Order ID: " . $post_id->id);

    if ($column === 'license_key') {
        
        $license_key = get_post_meta($post_id->get_id(), '_license_key', true);
        error_log("🔍 Running get_post_meta for  license_key: " . $license_key);
        
        // אם `get_post_meta` מחזיר ערך ריק, נשתמש בשאילתת DB ישירה
        // if (empty($license_key)) {
        //     $license_key = $wpdb->get_var($wpdb->prepare("
        //         SELECT meta_value FROM {$wpdb->prefix}postmeta 
        //         WHERE post_id = %d AND meta_key = '_license_key'
        //     ", $post_id->id));
        // }

        // error_log("🔍 License Key from DB for Order #{$post_id->id}: " . $license_key);
        echo $license_key ? esc_html($license_key) : '<span style="color: red;">לא הונפק</span>';
    }

    if ($column === 'license_expiration') {
        $expiration_date = get_post_meta($post_id->get_id(), '_license_expiration', true);
        
        // אם `get_post_meta` מחזיר ערך ריק, נשלוף ישירות מה-DB
        // if (empty($expiration_date)) {
        //     $expiration_date = $wpdb->get_var($wpdb->prepare("
        //         SELECT meta_value FROM {$wpdb->prefix}postmeta 
        //         WHERE post_id = %d AND meta_key = '_license_expiration'
        //     ", $post_id->id));
        // }

        // error_log("🔍 Expiration Date from DB for Order #{$post_id->id}: " . $expiration_date);
        echo $expiration_date ? esc_html($expiration_date) : '<span style="color: red;">לא נקבע</span>';
    }
}
add_action('manage_woocommerce_page_wc-orders_custom_column', 'display_license_column_data', 10, 2);

function display_license_info_in_order($order) {
    $license_key = get_post_meta($order->get_id(), '_license_key', true);
    $expiration_date = get_post_meta($order->get_id(), '_license_expiration', true);

    if ($license_key) {
        echo '<p><strong>מפתח רישיון:</strong> ' . esc_html($license_key) . '</p>';
        echo '<p><strong>תאריך תפוגה:</strong> ' . esc_html($expiration_date) . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'display_license_info_in_order');


function add_license_key_to_order($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        if ($product_id == 12 || $product_id == 15) {
            $is_renewal = ($product_id == 15);

            $new_license_key = strtoupper(bin2hex(random_bytes(10)));
            $expiration_date = date('Y-m-d', strtotime('+1 year'));

            update_post_meta($order_id, '_license_key', $new_license_key);
            update_post_meta($order_id, '_license_expiration', $expiration_date);

            $license_history = get_user_meta($user_id, '_license_history', true);
            if (!is_array($license_history)) {
                $license_history = array();
            }

            $license_entry = array(
                'license_key'      => $new_license_key,
                'expiration_date'  => $expiration_date,
                'order_id'         => $order_id,
                'date_generated'   => current_time('Y-m-d H:i:s'),
                'is_renewal'       => $is_renewal,
            );

            $license_history[] = $license_entry;
            update_user_meta($user_id, '_license_history', $license_history);
            update_user_meta($user_id, '_current_license', $license_entry);

            $email = $order->get_billing_email();
            $subject = ($is_renewal ? "חידוש רישיון" : "מפתח הרישיון שלך") . " - " . get_bloginfo('name');

            // כתובת הורדה
            $download_link = 'https://woocommerce-761776-5227801.cloudwaysapps.com/download-plugin.php?email=' . urlencode($email);

            // הודעה עם HTML
            $message = "<html><body>";
            $message .= "<p>שלום,</p>";
            $message .= "<p><strong>מפתח הרישיון שלך:</strong> {$new_license_key}<br>";
            $message .= "<strong>תוקף:</strong> {$expiration_date}</p>";

            if (!$is_renewal) {
                $message .= "<p>להורדת הפלאגין לחץ על הכפתור הבא:</p>";
                $message .= "<p><a href='{$download_link}' style='
                    display: inline-block;
                    background-color: #007cba;
                    color: #ffffff;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;'>📦 הורד את הפלאגין</a></p>";
            }

            $message .= "<p>תודה רבה,<br>" . get_bloginfo('name') . "</p>";
            $message .= "</body></html>";

            // כותרות ל־HTML
            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($email, $subject, $message, $headers);
        }
    }
}

// הפעלה על אירוע שבו ההזמנה מסומנת כ-Completed (יכול לעבוד גם עבור תשלום אוטומטי או ידני)
add_action('woocommerce_order_status_completed', 'add_license_key_to_order');



