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

require_once get_stylesheet_directory() . '/inc/license-functions.php';
require_once get_stylesheet_directory() . '/inc/cron-functions.php';
require_once get_stylesheet_directory() . '/inc/renewal-functions.php';
require_once get_stylesheet_directory() . '/inc/dasboard-functions.php';

