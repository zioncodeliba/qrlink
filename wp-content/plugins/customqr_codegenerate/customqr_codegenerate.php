<?php
/*
Plugin Name:  Custom QR Code Generator
Description:  Using this we can generate Custom QR code and store data in database and purchase product direct to scan the QR code
Version:      1.0
Author:       C&C team
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

*/
defined( 'ABSPATH' ) || exit;

ini_set('display_errors', 1);

// Include the qrlib file
include 'phpqrcode/qrlib.php';
include 'customqr_listdata.php';

// Create table when activate plugin
function customqr_create_db() {

	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$qrcode_details_table = $wpdb->prefix . 'qrcode_details';
    $qrcode_shortlinks_table = $wpdb->prefix . 'qrcode_shortlinks';

    $sql = "CREATE TABLE $qrcode_details_table (
                id INT(11) NOT NULL AUTO_INCREMENT,
                shortlink_id BIGINT(20) NOT NULL,
                qrcode_title varchar(100) NOT NULL,
                url_type varchar(20) NOT NULL,
                qrredirect_url text NOT NULL,
                product_discount boolean NOT NULL,
                discount_coupon varchar(50) NOT NULL,
                discount_type varchar(20) NOT NULL,
                discount_value varchar(20) NOT NULL,
                internalurl_type varchar(20) NOT NULL,
                internalurl_id INT(11) NOT NULL,
                qrcode_path text NOT NULL,
                conversions INT(11) DEFAULT 0 NOT NULL,
                created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                PRIMARY KEY (id)
            ) $charset_collate;
            CREATE TABLE $qrcode_shortlinks_table (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                short_key VARCHAR(20) NOT NULL,
                short_url TEXT NOT NULL,
                original_url TEXT NOT NULL,
                klicks_num INT(11) DEFAULT 0 NOT NULL,
                created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'customqr_create_db' );

//table delete when delete plugin
function delete_qrcodedata_table() {
    global $wpdb;
    $qrcode_details_table = $wpdb->prefix . 'qrcode_details';
    $qrcode_shortlinks_table = $wpdb->prefix . 'qrcode_shortlinks';
    $sql = "DROP TABLE IF EXISTS $qrcode_details_table
            DROP TABLE IF EXISTS $qrcode_shortlinks_table";
    $wpdb->query($sql);
}
register_uninstall_hook( __FILE__, 'delete_qrcodedata_table' );

function theme_options_panel(){
	global $qrcode_page;

	// add settings page
	$qrcode_page = add_menu_page(__('QR code', 'qrcode_example'), __('QR code', 'qrcode_example'), 'manage_options', 'qrcode-list', 'qrcode_list_init');
	add_action("load-$qrcode_page", "qrcode_screen_options");
    add_submenu_page( 'qrcode-list', 'Add new QR Code', 'Add new QR Code', 'manage_options', 'addqrcode-form', 'qrcode_func');
    
  }
add_action('admin_menu', 'theme_options_panel');
 

add_action('init', function() {
    // var_dump('init');
    // die;
    add_rewrite_rule(
        '^shortlink/([^/]+)/?$',
        'index.php?shortlink_key=$matches[1]',
        'top'
    );
    // flush_rewrite_rules();
});
add_filter('query_vars', function($vars) {
    $vars[] = 'shortlink_key';
    return $vars;
});

add_action('template_redirect', function() {
    $shortlink_key = get_query_var('shortlink_key');

    if ($shortlink_key) {
        // חפש את הלינק המקורי לפי המפתח
        $link_data = get_link_by_key($shortlink_key);
        if ($link_data) {
            $insert_klick = insert_klick($shortlink_key);

            // Display a success or error notice based on the result of insert_klick
            if ($insert_klick === true && !empty($link_data['product_id'])) {
                wc_add_notice(__('Your action was successful!', 'qrcode_example'), 'success');

                // הוספת מוצר לעגלה אם לא קיים
                if (!empty($link_data['product_id'])) {
                    $product_id = intval($link_data['product_id']);

                    // בדיקה אם המוצר כבר בעגלה
                    $cart_items = WC()->cart->get_cart();
                    $product_in_cart = false;

                    foreach ($cart_items as $cart_item) {
                        if ($cart_item['product_id'] == $product_id) {
                            $product_in_cart = true;
                            break;
                        }
                    }

                    // אם המוצר לא בעגלה, נוסיף אותו
                    if (!$product_in_cart) {
                        WC()->cart->add_to_cart($product_id);
                    }
                }

                // Return early if WooCommerce or sessions aren't available.
                if (!function_exists('WC') || !WC()->session) {
                    wc_add_notice(__("There was an error:  WooCommerce or sessions aren't available", 'qrcode_example'), 'error');
                    // return;
                }

                // הוספת קופון אם קיים
                if (!empty($link_data['coupon_name'])) {
                    $coupon_code = esc_attr($link_data['coupon_name']);
                    if (!WC()->cart->has_discount($coupon_code)) {
                        WC()->cart->add_discount($coupon_code);
                    }
                }

            } elseif($insert_klick !== true) {
                wc_add_notice(__('There was an error: ' . $insert_klick, 'qrcode_example'), 'error');
            }

            wp_redirect($link_data['original_url']);
            exit;

            // // הפניית המשתמש
            // wp_redirect("https://wordpress-761776-3484153.cloudwaysapps.com/");
            // exit;
        } else {
            wp_die('Link not found', '404', array('response' => 404));
        }
    }
});

function get_link_by_key($key) {
    global $wpdb;
    $shortlinks_table = $wpdb->prefix . 'qrcode_shortlinks'; // טבלת הקישורים
    $details_table = $wpdb->prefix . 'qrcode_details'; // טבלת הפרטים הנוספים

    // חיפוש הלינק, שם הקופון ו-ID של המוצר
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT s.original_url, d.discount_coupon AS coupon_name, d.internalurl_id AS product_id, d.internalurl_type AS product_type
         FROM $shortlinks_table AS s
         JOIN $details_table AS d ON s.id = d.shortlink_id
         WHERE s.short_key = %s",
        $key
    ), ARRAY_A);

    return $result ?: false;
}

//insert klick when user entered to url
function insert_klick($uniq_id) {
    if (!customqr_license_is_valid()) {
        echo '<div class="notice notice-error"><p><strong>QR Plugin:</strong> הרישיון שלך אינו פעיל. אנא הזן רישיון תקף בדף ההגדרות.</p></div>';
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'qrcode_shortlinks';
    // בדיקת אם הפרמטר ID קיים
    if (!empty($uniq_id)) {
        // עדכון מספר הקליקים בטבלה
        $sql = "UPDATE $table_name SET klicks_num = klicks_num + 1 WHERE short_key = %d";
        $result = $wpdb->query( $wpdb->prepare( $sql, $uniq_id ) );

        if ( $result !== false ) {
            return true; // העדכון הצליח
        } else {
            return "Error updating record: " . $wpdb->last_error; // תיאור השגיאה
        }
    } else {
        return "ID parameter is missing.";
    }
}

function shortcode_db_row_qrcode( $atts ) {
    $atts = shortcode_atts( array(
        'id' => false,
    ), $atts, 'qr_codedata' );
    $table = $wpdb->prefix . 'qrcode_details';
    $join_table = $wpdb->prefix . 'qrcode_shortlinks';
    global $wpdb;
    $sql = "SELECT q.*, s.id as s_id, s.short_key,s.short_url,s.original_url,s.klicks_num,s.created_date as s_created_date, s.updated_date as s_updated_date FROM {$table} AS q JOIN {$join_table} AS s ON q.shortlink_id = s.id where q.id = %d";
    $row = $wpdb->get_row( $wpdb->prepare($sql, $atts['id']), ARRAY_A );
    $result = '';

    if ( $row ) {
       //echo '<pre>'; print_r($row);
       foreach ($row as $key=>$value){
        if($key =="qrcode_path"){
             $result .= '<img src="'.plugins_url('images/'.$value,__FILE__ ) .'" height="100px" width="100px"/>';
             
        }
    }
     
        return $result;
    }

    return 'no such data found';
}
add_shortcode( 'qr_codedata', 'shortcode_db_row_qrcode' );


function qrcode_func()
{
    if (!customqr_license_is_valid()) {
        return '<div style="color:red;">⚠️ הפלאגין אינו פעיל. אנא עדכן רישיון תקף.</div>';
    }

    global $wpdb;
    $qrcode_details_table = $wpdb->prefix . 'qrcode_details';
    $qrcode_shortlinks_table = $wpdb->prefix . 'qrcode_shortlinks'; 

    $message = '';
    $notice = '';

    $inturlId = get_internal_url_id_from_post();
    $productId = $_POST['select_productdata'] ?? 0;

    if (should_handle_coupon()) {
        handle_coupon($_POST['discount_coupon'], $_POST['discount_value'], $_POST['discount_type']);
    }

    $externalurltext = get_redirect_url($inturlId);

    $short_key = substr(md5(uniqid()), 0, 6);
    $short_url = get_site_url() . "/shortlink/$short_key";
    $qrcodeImagepath = generate_qrcode($short_url);

    $default = build_default_item($inturlId, $qrcodeImagepath);

    if (is_post_back()) {
        $item = shortcode_atts($default, $_REQUEST);
        $item_valid = qrcode_example_validate_qrcode($item);

        if ($item_valid === true) {
            if ($item['id'] == 0) {
                $wpdb->insert($qrcode_shortlinks_table, [
                    'short_key' => $short_key,
                    'short_url' => $short_url,
                    'original_url' => $externalurltext
                ]);
                $shortlinks_id = $wpdb->insert_id;
                $item['shortlink_id'] = $shortlinks_id;

                $result = $wpdb->insert($qrcode_details_table, $item);
                $item['id'] = $wpdb->insert_id; 

                $message = $result ? __('Item was successfully saved', 'qrcode_example') : __('There was an error while saving item', 'qrcode_example');
            } else {
                $update_item = build_update_item($item);
                $result = $wpdb->update($qrcode_details_table, $update_item, ['id' => $item['id']]);
                if ($result || $result === 0) {
                    $result = $wpdb->update($qrcode_shortlinks_table, ['original_url' => $externalurltext], ['id' => $item['shortlink_id']]);
                    $message = ($result || $result === 0) ? __('Item was successfully updated', 'qrcode_example') : __('There was an error while updating item', 'qrcode_example');
                } else {
                    $notice = __('There was an error while updating item', 'qrcode_example');
                }
            }
        } else {
            $notice = $item_valid;
        }
    } else {
        $item = $default;
        if (isset($_REQUEST['id'])) {
            $item = load_item_for_editing($_REQUEST['id'], $qrcode_details_table, $qrcode_shortlinks_table) ?? $default;
            if ($item === $default) {
                $notice = __('Item not found', 'qrcode_example');
            }
        }
    }

    render_qrcode_form($item, $message, $notice);
}

function get_internal_url_id_from_post(): int {
    $type = $_POST['internalurl_type'] ?? '';
    if ($type === 'Page') {
        return (int) ($_POST['select_pagedata'] ?? 0);
    } elseif ($type === 'Post') {
        return (int) ($_POST['select_postdata'] ?? 0);
    } elseif ($type === 'Product') {
        return (int) ($_POST['select_productdata'] ?? 0);
    } else {
        return 0;
    }
}

function should_handle_coupon(): bool {
    return (
        ($_POST['internalurl_type'] ?? '') === 'Product'
        && ($_POST['product_discount'] ?? '') == 1
        && !empty($_POST['discount_coupon'])
    );
}

function handle_coupon(string $coupon_code, $amount, $discount_type): void {
    $coupon_code_t = sanitize_text_field($coupon_code);
    $amount = floatval($amount);
    $discount_type = sanitize_text_field($discount_type);

    $coupon = [
        'post_title' => $coupon_code_t,
        'post_content' => '',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_type' => 'shop_coupon'
    ];

    $existing_coupon_id = wc_get_coupon_id_by_code($coupon_code_t);
    if ($existing_coupon_id) {
        wp_update_post(array_merge(['ID' => $existing_coupon_id], $coupon));
        update_post_meta($existing_coupon_id, 'discount_type', $discount_type);
        update_post_meta($existing_coupon_id, 'coupon_amount', $amount);
    } else {
        $new_coupon_id = wp_insert_post($coupon);
        update_post_meta($new_coupon_id, 'discount_type', $discount_type);
        update_post_meta($new_coupon_id, 'coupon_amount', $amount);
    }
}

function get_redirect_url(int $inturlId): string {
    $cart = get_site_url() . '/cart/';
    $checkout = get_site_url() . '/checkout/';

    if (!empty($_POST['original_url'])) {
        $redirect = $_POST['qrredirect_url'] ?? '';
        if ($redirect === 'Cart') {
            return $cart;
        } elseif ($redirect === 'Checkout') {
            return $checkout;
        } else {
            return $_POST['original_url'];
        }
    }

    $type = $_POST['internalurl_type'] ?? '';
    if ($type === 'Page' || $type === 'Post') {
        return get_permalink($inturlId);
    }

    return ($_POST['qrredirect_url'] ?? '') === 'Cart' ? $cart : $checkout;
}

function generate_qrcode(string $short_url): string {
    $path = ABSPATH . 'wp-content/plugins/customqr_codegenerate/images/';
    $uniqueid = uniqid();
    $file = $path . $uniqueid . ".png";
    QRcode::png($short_url, $file, 'L', 10, 10);
    return $uniqueid . '.png';
}

function build_default_item(int $inturlId, string $qrcodeImagepath): array {
    return [
        'id' => 0,
        'shortlink_id' => '',
        'qrcode_title' => $_REQUEST['qrcode_title'] ?? '',
        'url_type' => $_REQUEST['url_type'] ?? '',
        'qrredirect_url' => $_REQUEST['qrredirect_url'] ?? '',
        'product_discount' => $_REQUEST['product_discount'] ?? '',
        'discount_coupon' => $_REQUEST['discount_coupon'] ?? '',
        'discount_type' => $_REQUEST['discount_type'] ?? '',
        'discount_value' => $_REQUEST['discount_value'] ?? '',
        'internalurl_type' => $_REQUEST['internalurl_type'] ?? '',
        'internalurl_id' => $inturlId,
        'qrcode_path' => $qrcodeImagepath
    ];
}

function is_post_back(): bool {
    return isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__));
}

function build_update_item(array $item): array {
    return [
        'shortlink_id' => $item['shortlink_id'],
        'qrcode_title' => $item['qrcode_title'],
        'url_type' => $item['url_type'],
        'qrredirect_url' => $item['qrredirect_url'],
        'product_discount' => $item['product_discount'],
        'discount_coupon' => $item['discount_coupon'],
        'discount_type' => $item['discount_type'],
        'discount_value' => $item['discount_value'],
        'internalurl_type' => $item['internalurl_type']
    ];
}

function load_item_for_editing($id, $details_table, $shortlinks_table): ?array {
    global $wpdb;
    $sql = "SELECT q.*, s.id as s_id, s.short_key,s.short_url,s.original_url,s.klicks_num,s.created_date as s_created_date, s.updated_date as s_updated_date 
            FROM {$details_table} AS q 
            JOIN {$shortlinks_table} AS s ON q.shortlink_id = s.id 
            WHERE q.id = %d";
    return $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);
}

function render_qrcode_form(array $item, string $message, string $notice): void {
    add_meta_box('qrcodes_form_meta_box', 'QR Code form', 'qrcode_example_qrcodes_form_meta_box_handler', 'qrcode', 'normal', 'default'); 
    ?>
    <div class="wrap">
        <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
        <h2><?php _e('QR Code', 'qrcode_example')?> <a class="add-new-h2"
                                    href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=qrcode-list');?>"><?php _e('back to list', 'qrcode_example')?></a>
        </h2>

        <?php if (!empty($notice)): ?>
        <div id="notice" class="error"><p><?php echo esc_html($notice) ?></p></div>
        <?php endif;?>
        <?php if (!empty($message)): ?>
        <div id="message" class="updated"><p><?php echo esc_html($message) ?></p></div>
        <?php endif;?>

        <form id="form" method="POST">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']) ?>"/>

            <div class="metabox-holder" id="poststuff">
                <div id="post-body">
                    <div id="post-body-content">
                        <?php do_meta_boxes('qrcode', 'normal', $item); ?>
                        <input type="submit" value="<?php _e('Save', 'qrcode_example') ?>" id="submit" class="button-primary" name="submit">
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
}



function qrcode_example_qrcodes_form_meta_box_handler($item){

    ?>
       <div class="wrap">
        <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post"> 
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
            <input type="hidden" name="shortlink_id" value="<?php echo $item['shortlink_id'] ?>"/>
            <?php /* NOTICE: here we storing id to determine will be item added or updated */ ?>
            <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>
                <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
                <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="qrcode_title"><?php _e('Title', 'qrcode_example')?></label>
                    </th>
                    <td>
                        <input id="qrcode_title" name="qrcode_title" type="text" style="width: 95%" value="<?php echo esc_attr($item['qrcode_title'])?>"
                            size="50" class="code" placeholder="<?php _e('Title', 'qrcode_example')?>">
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="url_type"><?php _e('Type', 'qrcode_example')?></label>
                    </th>
                    <td>
                        <input type="radio" id="exqrcode_types" name="url_type" value="External" <?php if($item['url_type']=="External"){ echo 'checked="checked"';}?>>External 
                        <input type="radio" id="inqrcode_types" name="url_type" value="Internal" <?php if($item['url_type']=="Internal"){ echo 'checked="checked"';}?>>Internal
                    </td>
                </tr>

                <tr id="urlExternal" class="form-field urlfield">
                    <th valign="top" scope="row">
                        <label for="original_url"><?php _e('External URL', 'qrcode_example')?></label>
                    </th>
                    <td>
                        <input type="text" id="original_url" name="original_url" style="width: 95%" placeholder="<?php _e('External URL', 'qrcode_example')?>" value="<?php echo esc_url($item['original_url'])?>" >
                    </td>
                </tr>
                
                <tr class="form-field urlfield" id="urlInternal" style="display:none">
                    <th valign="top" scope="row">
                        <label for="internalurl_type"><?php _e('Internal URL Type', 'qrcode_example')?></label>
                    </th>
                    <td>
                        <div class="dropdownfields">
                            <select name="internalurl_type" id="int_url">
                                <option selected="selected" disabled="disabled"><?php echo esc_attr( __( '- Select -' ) ); ?></option>
                                <option value="Page" <?php if($item['internalurl_type']=="Page"){ echo 'selected="selected"';}?>>Page</option>
                                <option value="Post"<?php if($item['internalurl_type']=="Post"){ echo 'selected="selected"';}?>>Post</option>
                                <option value="Product"<?php if($item['internalurl_type']=="Product"){ echo 'selected="selected"';}?>>Products</option>
                            </select>
                            <!-- All Page Data-->   
                            <select name="select_pagedata" id="all_pages" style="display:none" data-value = 'Page'> 
                                <option selected="selected" disabled="disabled" value=""><?php echo esc_attr( __( '- Select page -' ) ); ?></option> 
                                <?php
                                
                                    $selected_page = get_option( 'option_key' );
                                    $pages = get_pages(); 
                                    foreach ( $pages as $page ) {
                                        $option = '<option value="' . $page->ID . '" ';
                                        $option .= $item['internalurl_id'] == $page->ID ? 'selected="selected"' : '';
                                        $option .= '>';
                                        $option .= $page->post_title;
                                        $option .= '</option>';
                                        echo $option;
                                    }
                                ?>
                            </select>
                                
                            <!-- All Post Data-->
                            <select name="select_postdata" id="all_postdata" style="display:none" data-value = 'Post'>
                                <option selected="selected" disabled="disabled" value=""><?php echo esc_attr( __( '- Select post -' ) ); ?></option> 
                                <?php
                                global $post;
                                $args = array( 'numberposts' => -1);
                                $posts = get_posts($args);
                                foreach( $posts as $post ) : setup_postdata($post); ?>
                                    <option value="<?php echo $post->ID; ?>" <?php if($item['internalurl_id'] == $post->ID){ echo 'selected="selected"';}?>><?php the_title(); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <!-- All Product Data-->
                            <select name="select_productdata" id="all_productdata" style="display:none" data-value = 'Product'>
                                    <option selected="selected" disabled="disabled" value=""><?php echo esc_attr( __( '- Select product -' ) ); ?></option> 
                                    <?php
                                    global $productposts;
                                    $productargs = array( 'numberposts' => -1, 'post_type' => 'product',);
                                    $productposts = get_posts($productargs);
                                    foreach( $productposts as $productpost ) : setup_postdata($productpost);
                                                $productId[] = $productpost->ID;
                                    ?>
                                                <option value="<?php echo $productpost->ID; ?>" <?php if($item['internalurl_id'] == $productpost->ID){ echo 'selected="selected"';}?>><?php echo $productpost->post_title ?></option>
                                    <?php endforeach; ?>
                            </select>
                        </div>
                            <table id="products_fields" style="display:none;" data-value="Product">
                                    <tr data-value="Product">
                                        <td valign="top" scope="row">
                                            <label for="redirect_url"><?php _e('Redirect', 'qrcode_example')?></label>
                                        </td>
                                        <td>
                                            <input type="radio" id="redirect_url" name="qrredirect_url" value="Cart"<?php if($item['qrredirect_url']=="Cart"){ echo 'checked="checked"';}?>>Cart
                                            <input type="radio" id="redirect_url" name="qrredirect_url" value="Checkout" <?php if($item['qrredirect_url']=="Checkout"){ echo 'checked="checked"';}?>>Checkout
                                        </td>
                                    </tr>
                                    <tr data-value="Product">
                                        <td valign="top" scope="row">
                                            <label for="product_discount"><?php _e('Discount :', 'qrcode_example')?></label>
                                        </td>
                                        <td>
                                            <input type="checkbox" id="product_discount" name="product_discount" value="1" <?php if($item['product_discount']== 1){ echo 'checked="checked"';}?>>
                                        </td>
                                    </tr>  
                                    <tr id="discount_fields_coupon" style="display:none;">    
                                        <td valign="top" scope="row">
                                            <label for="discount_coupon"><?php _e('Discount Coupon', 'qrcode_example')?></label>
                                        </td>
                                        <td>
                                            <input type="text" id="discount_coupon" name="discount_coupon" value="<?php echo esc_attr($item['discount_coupon'])?>">
                                        </td>
                                    </tr>  
                                    <tr id="discount_fields_type" style="display:none;">    
                                        <td valign="top" scope="row">
                                            <label for="discount_type"><?php _e('Discount Type', 'qrcode_example')?></label>
                                        </td>
                                        <td>
                                            <input type="radio" id="discount_type" name="discount_type" value="fixed_cart" <?php if($item['discount_type']=="fixed_cart"){ echo 'checked="checked"';}?>>Fixed
                                            <input type="radio" id="discount_type" name="discount_type" value="percent" <?php if($item['discount_type']=="percent"){ echo 'checked="checked"';}?>>Percentage
                                        </td>
                                    </tr>
                                    <tr id="discount_fields_value" style="display:none;">
                                        <td valign="top" scope="row">
                                            <label for="dis_value"><?php _e('Discount Value', 'qrcode_example')?></label>
                                        </td>
                                        <td> 
                                            <input type="text" id="dis_value" name="discount_value" value="<?php echo esc_attr($item['discount_value'])?>"> 
                                        </td>
                                    </tr>
                            </table>  
                    </td>  
    
                </tr> 
                        
                </tbody>
            </table>
    
      </form> 
    </div>

<?php
  }
  function qrcode_example_validate_qrcode($item)
  {
      $messages = array();
  
      if (empty($item['qrcode_title'])) $messages[] = __('Title is required', 'qrcode_example');
     
      if (empty($messages)) return true;
      return implode('<br />', $messages);
  }
  function custom_cssjs() {
     wp_register_style('custom', plugins_url('css/custom.css',__FILE__ ));
     wp_enqueue_style('custom');
     wp_enqueue_script( 'jquery' );
     wp_register_script('custom', plugins_url('js/custom.js',__FILE__ ));
     wp_enqueue_script('custom');
}

add_action( 'admin_enqueue_scripts','custom_cssjs');

add_action('init', 'customqr_check_license');

function customqr_check_license() {
    if (!is_admin()) return;

    // update_option('customqr_license_key', '2B59F89BE29800CE27E6');
    $license_key = get_option('customqr_license_key', '');

    if (empty($license_key)) {
        customqr_show_license_notice('אין מפתח רישיון מוגדר');
        return;
    }

    $response = wp_remote_post('https://woocommerce-761776-5227801.cloudwaysapps.com/wp-json/customqr/license-check', [
        'timeout' => 10,
        'body' => [
            'license_key' => $license_key,
            'site_url'    => home_url()
        ]
    ]);

    if (is_wp_error($response)) {
        customqr_show_license_notice('שגיאה: לא ניתן להתחבר לשרת הרישוי.');
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($data['status']) || $data['status'] !== 'active') {
        customqr_show_license_notice('הרישיון אינו פעיל או לא תקף. הפלאגין מושבת.');
        add_action('admin_init', function () {
            // deactivate_plugins(plugin_basename(__FILE__));
        });
    }
}

function customqr_show_license_notice($msg) {
    add_action('admin_notices', function () use ($msg) {
        echo '<div class="notice notice-error"><p><strong>Custom QR Plugin:</strong> ' . esc_html($msg) . '</p></div>';
    });
}

// // הוספת תפריט בהגדרות
// add_action('admin_menu', 'customqr_license_menu');
// function customqr_license_menu() {
//     add_options_page(
//         'License Settings',
//         'License Key',
//         'manage_options',
//         'customqr-license',
//         'customqr_license_page_html'
//     );
// }

// יצירת עמוד HTML
function customqr_license_page_html() {
    if (!current_user_can('manage_options')) return;

    $license_key = get_option('customqr_license_key', '');
    $status = customqr_check_license_status($license_key);
    $domain_message = '';
    $current_domain = 'לא רשום';

    // טיפול בטופס שמירת רישיון
    if (isset($_POST['customqr_license_key'])) {
        check_admin_referer('customqr_license_save');
        $key = sanitize_text_field($_POST['customqr_license_key']);
        update_option('customqr_license_key', $key);
        $license_key = $key;
        $status = customqr_check_license_status($license_key);
        echo '<div class="updated"><p>מפתח הרישיון נשמר בהצלחה</p></div>';
    }

    // שליפת הדומיין מהשרת
    if (!empty($license_key)) {
        $response = wp_remote_post('https://woocommerce-761776-5227801.cloudwaysapps.com/wp-json/customqr/license-check', [
            'body' => [
                'license_key' => $license_key,
                'site_url' => home_url(),
            ]
        ]);
        if (!is_wp_error($response)) {
            $result = json_decode(wp_remote_retrieve_body($response), true);
            var_dump($result);
            var_dump("result");

            if (!empty($result['domain'])) {
                $current_domain = esc_html($result['domain']);
            }
        }
    }

    // טיפול בטופס שינוי דומיין
    if (isset($_POST['customqr_change_domain']) && !empty($_POST['customqr_new_domain'])) {
        $new_domain = sanitize_text_field($_POST['customqr_new_domain']);

        $response = wp_remote_post('https://woocommerce-761776-5227801.cloudwaysapps.com/wp-json/customqr/v1/change-domain', [
            'body' => [
                'license_key' => $license_key,
                'new_domain' => $new_domain,
            ]
        ]);

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (is_wp_error($response)) {
            $domain_message = '<div class="notice notice-error"><p>⚠️ שגיאה בחיבור לשרת הרישוי.</p></div>';
        } elseif (!empty($result['success'])) {
            $domain_message = '<div class="notice notice-success"><p>✅ הדומיין עודכן בהצלחה: <strong>' . esc_html($result['new_domain']) . '</strong></p></div>';
            $current_domain = esc_html($result['new_domain']);
        } else {
            $error_msg = $result['message'] ?? 'אירעה שגיאה לא ידועה';
            $domain_message = '<div class="notice notice-error"><p>❌ שגיאה: ' . esc_html($error_msg) . '</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1>הגדרות רישיון לפלאגין</h1>

        <h2 class="nav-tab-wrapper">
            <a href="#license-tab" class="nav-tab nav-tab-active">🔑 רישיון</a>
            <a href="#domain-tab" class="nav-tab">🌐 ניהול דומיין</a>
        </h2>

        <div id="license-tab-content" class="tab-content" style="display: block;">
            <form method="post">
                <?php wp_nonce_field('customqr_license_save'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="customqr_license_key">מפתח רישיון</label></th>
                        <td>
                            <input type="text" id="customqr_license_key" name="customqr_license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">סטטוס רישיון</th>
                        <td>
                            <strong><?php echo esc_html($status); ?></strong>
                        </td>
                    </tr>
                </table>
                <?php submit_button('שמור רישיון'); ?>
            </form>
        </div>

        <div id="domain-tab-content" class="tab-content" style="display: none;">
            <?php echo $domain_message; ?>
            <p><strong>🔒 הדומיין הנוכחי שרשום ברישיון:</strong> <?php echo $current_domain; ?></p>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="customqr_new_domain">דומיין חדש</label></th>
                        <td>
                            <input type="text" id="customqr_new_domain" name="customqr_new_domain" value="" class="regular-text" placeholder="example.com">
                            <p class="description">יש להזין את הדומיין החדש בלבד (ללא https:// או www)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('עדכן דומיין', 'primary', 'customqr_change_domain'); ?>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const contents = {
                'license-tab': document.getElementById('license-tab-content'),
                'domain-tab': document.getElementById('domain-tab-content')
            };

            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();

                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    tab.classList.add('nav-tab-active');

                    for (const id in contents) {
                        contents[id].style.display = 'none';
                    }

                    const target = tab.getAttribute('href').substring(1);
                    contents[target].style.display = 'block';
                });
            });
        })();
    </script>

    <style>
        .tab-content {
            margin-top: 20px;
        }
    </style>

    <?php
}


function customqr_check_license_status($license_key) {
    if (empty($license_key)) return 'לא הוזן מפתח';

    $response = wp_remote_post('https://woocommerce-761776-5227801.cloudwaysapps.com/wp-json/customqr/license-check', [
        'timeout' => 10,
        'body' => [
            'license_key' => $license_key,
            'site_url'    => home_url()
        ]
    ]);

    if (is_wp_error($response)) {
        return 'שגיאה בבדיקה';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($data['status'])) return 'לא תקף';

    switch ($data['status']) {
        case 'active':
            return '✅ פעיל (בתוקף עד ' . $data['expires'] . ')';
        case 'expired':
            return '⚠️ פג תוקף';
        case 'inactive':
        default:
            return '❌ לא פעיל';
    }
}


add_action('admin_menu', 'customqr_add_license_submenu');

function customqr_add_license_submenu() {
    add_submenu_page(
        'qrcode-list',                // parent_slug – התפריט הראשי של הפלאגין
        'License Settings',           // title בעמוד
        'License Settings',           // הכיתוב בתפריט הצד
        'manage_options',             // הרשאה
        'customqr-license',           // slug של עמוד ההגדרות
        'customqr_license_page_html'  // הפונקציה שמציגה את התוכן
    );
}

function customqr_license_is_valid() {
    // נעשה קאש לבדיקה – כדי לא לקרוא API בכל קריאה
    static $cached_result = null;
    if (!is_null($cached_result)) return $cached_result;

    $license_key = get_option('customqr_license_key', '');
    if (empty($license_key)) {
        $cached_result = false;
        return false;
    }

    $response = wp_remote_post('https://woocommerce-761776-5227801.cloudwaysapps.com/wp-json/customqr/license-check', [
        'timeout' => 10,
        'body' => [
            'license_key' => $license_key,
            'site_url' => home_url()
        ]
    ]);

    if (is_wp_error($response)) {
        $cached_result = false;
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $cached_result = isset($data['status']) && $data['status'] === 'active';
    return $cached_result;
}


add_action('admin_notices', function () {
    if (!customqr_license_is_valid()) {
        echo '<div class="notice notice-error"><p>🔒 <strong>QR Plugin:</strong> הרישיון שלך אינו פעיל. הפלאגין מוגבל. <a href="admin.php?page=customqr-license">לעמוד הרישוי »</a></p></div>';
    }
});


?>