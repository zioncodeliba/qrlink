<?php
// Loading WP_List_Table class file
// We need to load it as it's not automatically loaded by WordPress
if (!class_exists('WP_List_Table')) {
  require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
// Extending class
class Qrcode_List_Table extends WP_List_Table
{

    // define $table_data property
    private $table_data;

    // Get table data
    private function get_table_data( $search = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qrcode_details';
        $join_table = $wpdb->prefix . 'qrcode_shortlinks';
        $sql = "";

        if ( !empty($search) ) {
            $sql = "SELECT q.*, s.id as s_id, s.short_key,s.short_url,s.original_url,s.klicks_num,s.created_date as s_created_date, s.updated_date as s_updated_date FROM {$table} AS q JOIN {$join_table} AS s ON q.shortlink_id = s.id WHERE q.qrcode_title Like '%{$search}%' OR q.url_type Like '%{$search}%' OR q.discount_type Like '%{$search}%' order by q.updated_date desc";
            // $sql = "SELECT * from {$table} WHERE qrcode_title Like '%{$search}%' OR url_type Like '%{$search}%' OR discount_type Like '%{$search}%' order by updated_date desc";
            return $wpdb->get_results(
                $sql,
                ARRAY_A
            );
        } else {
            $sql = "SELECT q.*, s.id as s_id, s.short_key,s.short_url,s.original_url,s.klicks_num,s.created_date as s_created_date, s.updated_date as s_updated_date FROM {$table} AS q JOIN {$join_table} AS s ON q.shortlink_id = s.id order by q.updated_date desc";
            // $sql = "SELECT * from {$table} order by updated_date desc";
            return $wpdb->get_results(
                $sql,
                ARRAY_A
            );
        }
    }
    protected function get_table_classes() {
        return ['widefat', 'striped', 'custom-qrcode-table'];
    }
    
    // Define table columns
    function get_columns()
    {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'qrcode_title'          => __('Title', 'qrcode_example'),
            'qrcode_path'   => __('QR Code', 'qrcode_example'),
            'url'   => __('🌍 Url', 'qrcode_example'),
            'url_type'   => __('Type URL', 'qrcode_example'),            
            'discount_type'         => __('Discount Type', 'qrcode_example'),
            'conversions'   => __('Number Conversions', 'qrcode_example'),
            'klicks_num'   => __('Number Of Clicks', 'qrcode_example'),
            'created_date'   => __('Date Created', 'qrcode_example'),
            'updated_date'   => __('Date Updated', 'qrcode_example')
        );
        return $columns;
    }

    // Bind table with columns, data and all
    function prepare_items()
    {
        //data
        if ( isset($_POST['s']) ) {
            $this->table_data = $this->get_table_data($_POST['s']);
        } else {
            $this->table_data = $this->get_table_data();
        }
        // var_dump($this->table_data);
        // die;

        $columns = $this->get_columns();
        $hidden = ( is_array(get_user_meta( get_current_user_id(), 'managetoplevel_page_qrcode_list_tablecolumnshidden', true)) ) ? get_user_meta( get_current_user_id(), 'managetoplevel_page_qrcode_list_tablecolumnshidden', true) : array();
        $primary  = 'qrcode_title';
        $sortable = $this->get_sortable_columns();
        //$this->_column_headers = array($columns, $hidden, $sortable);
        
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        $this->process_bulk_action();

        if((!empty($_GET['orderby']))){
            usort($this->table_data, array(&$this, 'usort_reorder'));
        }

        /* pagination */
        $per_page = $this->get_items_per_page('elements_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        $this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
                'total_items' => $total_items, // total number of items
                'per_page'    => $per_page, // items to show on a page
                'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
        ));
        
        $this->items = $this->table_data;

    }

    // set value for each column
    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'cb':
            case 'qrcode_title':
            case 'qrcode_path': 
            case 'discount_type':
            case 'internalurl_type':    
            //case 'order':
            default:
                return $item[$column_name];
        }
    }

    // Add a checkbox in the first column
    function column_cb($item)
    {
        return sprintf(
                '<input type="checkbox" name="id[]" value="%s" />',
                $item['id']
        );
    }
    function column_qrcode_path($item)
    {
        return sprintf(
            '<img src="'.plugins_url('images/'.$item['qrcode_path'],__FILE__ ) .'" height="100px" width="100px"/></br><a href="'.plugins_url('images/'.$item['qrcode_path'],__FILE__ ) .'" download="'.$item['qrcode_path'].'"><button type="button">Download</button></a>',
            $item['qrcode_path']
        );
    }
 
    function column_url($item)
    {
        $short_url = esc_url($item['short_url']);
        $internalpageUrl = esc_url(get_permalink($internalpageId));
        $internalpageTitle = esc_html(get_the_title($internalpageId));

        return sprintf('🔗 <a href="%s" target="_blank">%s</a>',$short_url,$short_url);

    }

    function column_discount_type($item)
    {
          
        $disType = ''; 
        if(!empty($item['discount_value'])){
            $disValue = $item['discount_value'];
        }else{
            $disValue = '';
        }

       
         if($item['discount_type'] == 'fixed_cart'){
             $disType = 'Fixed'. '<br>'. $disValue . ' '.get_option('woocommerce_currency');
         }else if($item['discount_type'] == 'percent'){
             $disType = 'Percentage'. '<br>'. $disValue . ' % ';
         }

         
         return $disType;
       
    }
    // function column_shortcode($item)
    // {
    //     $rowId = $item['id'];
    //     return sprintf(
    //         '[qr_codedata id="'.$rowId.'"]',$item['internalurl_id']
    //     );
    // }

    // Define sortable column
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'qrcode_title'  => array('qrcode_title', false),
            'qrcode_path' => array('qrcode_path', false),
            'url' => array('url', false),
            'url_type' => array('url_type', false),
            'discount_type' => array('discount_type', false),
            'conversions' => array('conversions', false),
            'klicks_num' => array('klicks_num', false),
            'created_date' => array('created_date', false),
            'updated_date' => array('updated_date', false)
        );
        return $sortable_columns;
    }

    // Sorting function
    function usort_reorder($a, $b)
    {
        // If no sort, default to user_login
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';

        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';

        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);

        $return = ($order === 'asc') ? $result : -$result;

        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    // Adding action links to column
    function column_qrcode_title($item)
    {
        $actions = array(
                'edit' => sprintf('<a href="?page=addqrcode-form&id=%s">%s</a>', $item['id'], __('Edit', 'qrcode_example')),
                'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'qrcode_example')),   
        );

        return sprintf('%s %s', $item['qrcode_title'], $this->row_actions($actions));
    }

    // To show bulk action dropdown
    function get_bulk_actions()
    {
            $actions = array(
                    'delete'    => __('Delete', 'qrcode_example'),    
            );
            return $actions;
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qrcode_details'; // do not forget about tables prefix

        // if ('delete' === $this->current_action()) {
        //     $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
        //     if (is_array($ids)) $ids = implode(',', $ids);

        //     if (!empty($ids)) {
        //         $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
        //     }
        // }
        if ('delete' === $this->current_action()) {
            global $wpdb;
        
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);
        
            if (!empty($ids)) {
                $qrcode_paths = $wpdb->get_col("SELECT qrcode_path FROM $table_name WHERE id IN($ids)");
                $shortlink_ids = $wpdb->get_col("SELECT shortlink_id FROM $table_name WHERE id IN($ids)");
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
        
                if (!empty($shortlink_ids)) {
                    $shortlinks_table = $wpdb->prefix . 'qrcode_shortlinks';
                    $shortlink_ids_str = implode(',', array_map('intval', $shortlink_ids)); 
                    $wpdb->query("DELETE FROM $shortlinks_table WHERE id IN ($shortlink_ids_str)");
                }
            
                $path = ABSPATH.'wp-content/plugins/customqr_codegenerate/images/';
                // 🔹 מחיקת קובצי ה-QR Code מהשרת
                foreach ($qrcode_paths as $image) {
                    $full_path = $path.$image;
                    // var_dump($full_path);
                    $file_exists = file_exists($full_path);
                    // var_dump($file_exists);
                // die;
                     // התאמת הנתיב לתיקיית התמונות שלך
                    if (file_exists($full_path)) {
                        unlink($full_path); // מחיקת הקובץ מהשרת
                    }
                }
            }
        }
        
    }

}
// add screen options
function qrcode_screen_options() {
 
	global $qrcode_page;
    global $table;
 
	$screen = get_current_screen();
 
	// get out of here if we are not on our settings page
	if(!is_object($screen) || $screen->id != $qrcode_page)
		return;
 
	$args = array(
		'label' => __('Elements per page', 'qrcode_example'),
		'default' => 2,
		'option' => 'elements_per_page'
	);
	add_screen_option( 'per_page', $args );

    $table = new Qrcode_List_Table();

}

add_filter('set-screen-option', 'test_table_set_option', 10, 3);
function test_table_set_option($status, $option, $value) {
  return $value;
}


// Plugin menu callback function
function qrcode_list_init()
{
    //include 'qr_process.php';
     global $wpdb;

      // Creating an instance
      $table = new Qrcode_List_Table();

       // Prepare table
      $table->prepare_items();
      $message = '';
    //   var_dump($table->current_action());
      if ('delete' === $table->current_action()) {
          $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'qrcode_example'), $_REQUEST['id']) . '</p></div>';
          echo '<script>window.location.href="' . esc_url(admin_url('admin.php?page=qrcode-list')) . '";</script>';
          exit;
      }

   ?>

      <div class="wrap">

            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1 class="wp-heading-inline"><?php _e('QR code List', 'qrcode_example')?> </h1>
                    <a class="page-title-action" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=addqrcode-form');?>"><?php _e('Add new', 'qrcode_example')?></a>

            <?php echo $message; ?>  
            <form method="GET"> 
                <?php   
                    
                    // Search form
                    //$table->search_box('search', 'search_id'); ?>
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
                    <?php // Display table
                         $table->display();
                    ?>
            </form>
    </div>
<?php } ?>