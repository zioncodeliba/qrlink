<?php
// הוספת עמוד ניהול רישיונות לתפריט האדמין
function add_license_management_page() {
    add_submenu_page(
        'woocommerce',
        'ניהול רישיונות',
        'ניהול רישיונות',
        'manage_woocommerce',
        'license-management',
        'render_license_management_page'
    );
}
add_action('admin_menu', 'add_license_management_page');

// פונקציה שמציגה את עמוד הניהול
function render_license_management_page() {
    global $wpdb;

    $licenses = $wpdb->get_results("
        SELECT p.ID, p.post_status, p.post_date, 
               pm1.meta_value AS license_key, 
               pm2.meta_value AS license_status,
               pm3.meta_value AS expiry_date, 
               pm4.meta_value AS total_paid, 
               o.meta_value as order_total, 
               c.display_name, c.user_email
        FROM {$wpdb->prefix}posts p
        LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_key'
        LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_status'
        LEFT JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_license_expiration'
        LEFT JOIN {$wpdb->prefix}postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_total_paid'
        LEFT JOIN {$wpdb->prefix}postmeta o ON p.ID = o.post_id AND o.meta_key = '_order_total'
        LEFT JOIN {$wpdb->prefix}users c ON p.post_author = c.ID
        WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
    ");

    echo "<div class='wrap'>";
    echo "<h1>ניהול רישיונות</h1>";
    echo "<table class='wp-list-table widefat striped'>";
    echo "<thead><tr>
            <th>מזהה</th>
            <th>שם הלקוח</th>
            <th>אימייל</th>
            <th>סטטוס הזמנה</th>
            <th>מפתח רישיון</th>
            <th>סטטוס רישיון</th>
            <th>תאריך תפוגה</th>
            <th>תאריך רכישה</th>
            <th>סכום רכישה</th>
            <th>סה״כ תשלומים</th>
            <th>פעולות</th>
          </tr></thead><tbody>";

    foreach ($licenses as $license) {
        echo "<tr id='row-{$license->ID}'>
                <td>{$license->ID}</td>
                <td>{$license->display_name}</td>
                <td>{$license->user_email}</td>
                <td>{$license->post_status}</td>
                <td>{$license->license_key}</td>
                <td>{$license->license_status}</td>
                <td>
                    <input type='date' id='expiry-date-{$license->ID}' value='{$license->expiry_date}'>
                    <button class='button update-expiry' data-id='{$license->ID}'>שמור</button>
                </td>
                <td>{$license->post_date}</td>
                <td>{$license->order_total} ₪</td>
                <td>{$license->total_paid} ₪</td>
                <td>
                    <a href='?page=license-management&action=activate&license_id={$license->ID}' class='button'>הפעל</a>
                    <a href='?page=license-management&action=deactivate&license_id={$license->ID}' class='button'>השבת</a>
                    <a href='?page=license-management&action=send_renewal&license_id={$license->ID}' class='button'>שלח חידוש</a>
                </td>
              </tr>";
    }

    echo "</tbody></table></div>";
var_dump(plugin_basename());
var_dump(plugin_dir_url( __FILE__ ));
var_dump(plugin_basename( plugin_dir_url( __FILE__ )));
    // הוספת קובץ JavaScript של AJAX
    echo "<script src='admin-ajax.js'></script>";
}




// שינוי סטטוס רישיון
function update_license_status() {
    if (isset($_GET['action']) && isset($_GET['license_id'])) {
        $license_id = intval($_GET['license_id']);
        if ($_GET['action'] == 'activate') {
            update_post_meta($license_id, '_license_status', 'active');
        } elseif ($_GET['action'] == 'deactivate') {
            update_post_meta($license_id, '_license_status', 'inactive');
        } elseif ($_GET['action'] == 'send_renewal') {
            $order = wc_get_order($license_id);
            $customer_email = $order->get_billing_email();
            wp_mail($customer_email, "חידוש רישיון", "הרישיון שלך פג תוקף, לחץ כאן כדי לחדש.");
        }
    }
}
add_action('admin_init', 'update_license_status');

function update_license_expiry_date() {
    if (isset($_POST['update_expiry']) && isset($_POST['license_id']) && isset($_POST['expiry_date'])) {
        $license_id = intval($_POST['license_id']);
        $new_expiry_date = sanitize_text_field($_POST['expiry_date']);

        if (!empty($new_expiry_date)) {
            update_post_meta($license_id, '_license_expiration', $new_expiry_date);
            echo "<div class='updated'><p>תאריך התפוגה עודכן בהצלחה!</p></div>";
        } else {
            echo "<div class='error'><p>שגיאה: אנא הזן תאריך תקין.</p></div>";
        }
    }
}
add_action('admin_init', 'update_license_expiry_date');

function update_license_expiry_ajax() {
    // בדיקת הרשאות
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('אין לך הרשאות לעדכן');
    }

    // קבלת הנתונים מהבקשה
    $license_id = intval($_POST['license_id']);
    $new_expiry_date = sanitize_text_field($_POST['expiry_date']);

    if (!empty($license_id) && !empty($new_expiry_date)) {
        update_post_meta($license_id, '_license_expiration', $new_expiry_date);
        wp_send_json_success('תאריך התפוגה עודכן בהצלחה!');
    } else {
        wp_send_json_error('שגיאה: נא להזין תאריך תקין');
    }
}
add_action('wp_ajax_update_license_expiry', 'update_license_expiry_ajax');
