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
        SELECT p.ID, p.post_date, pm1.meta_value AS license_key, pm2.meta_value AS license_status,
               pm3.meta_value AS expiry_date, pm4.meta_value AS total_paid, o.total as order_total,
               c.display_name, c.user_email
        FROM wp_posts p
        JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_key'
        JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_status'
        JOIN wp_postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_license_expiry_date'
        JOIN wp_postmeta pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_total_paid'
        JOIN wp_users c ON p.post_author = c.ID
        JOIN (SELECT post_id, meta_value as total FROM wp_postmeta WHERE meta_key = '_order_total') o 
        ON p.ID = o.post_id
        WHERE p.post_type = 'shop_order'
    ");

    echo "<div class='wrap'>";
    echo "<h1>ניהול רישיונות</h1>";
    echo "<table class='wp-list-table widefat fixed striped'>";
    echo "<thead><tr>
            <th>מזהה</th>
            <th>שם הלקוח</th>
            <th>אימייל</th>
            <th>מפתח רישיון</th>
            <th>סטטוס</th>
            <th>תאריך תפוגה</th>
            <th>תאריך רכישה</th>
            <th>סכום רכישה</th>
            <th>סה״כ תשלומים</th>
            <th>פעולות</th>
          </tr></thead><tbody>";

    foreach ($licenses as $license) {
        echo "<tr>
                <td>{$license->ID}</td>
                <td>{$license->display_name}</td>
                <td>{$license->user_email}</td>
                <td>{$license->license_key}</td>
                <td>{$license->license_status}</td>
                <td>{$license->expiry_date}</td>
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
