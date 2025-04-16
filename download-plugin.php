<?php
require_once('wp-load.php');

$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

if (!$email || !is_email($email)) {
    http_response_code(400);
    exit('❌ מייל לא חוקי');
}

global $wpdb;

// מחפשים רישיון פעיל לפי המייל והסטטוס
$results = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID
    FROM {$wpdb->prefix}posts p
    LEFT JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_license_key'
    LEFT JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_license_status'
    LEFT JOIN {$wpdb->prefix}users u ON p.post_author = u.ID
    WHERE (p.post_type = 'shop_order' OR p.post_type = 'shop_order_placehold')
      AND u.user_email = %s
      AND pm2.meta_value = 'active'
    LIMIT 1
", $email));


    var_dump($email);
var_dump($results);

if (empty($results)) {
    http_response_code(403);
    exit('❌ לא נמצא רישיון תקף עבור מייל זה');
}

// אם עברנו את הבדיקה – יוצרים ZIP
$plugin_dir = __DIR__ . '/wp-content/plugins/customqr_codegenerate';
$zip_file = __DIR__ . '/downloads/customqr_codegenerate_latest.zip';

$zip = new ZipArchive;
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($plugin_dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();

    // הורדה
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="customqr_codegenerate_latest.zip"');
    header('Content-Length: ' . filesize($zip_file));
    readfile($zip_file);
    unlink($zip_file); // אופציונלי
    exit;
} else {
    exit('❌ שגיאה ביצירת קובץ ההורדה.');
}
