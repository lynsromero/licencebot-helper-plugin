<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin {

	/**
	 * AC_Serial_Numbers_Admin constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'includes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_style' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_serial_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'add_order_serial_column_content' ), 20, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_order_serial_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'add_order_serial_column_content' ), 20, 2 );
		
		// ajax call to update product key source
		add_action( 'wp_ajax_ac_serial_numbers_update_product_key_source', array( $this, 'update_product_key_source' ) );
		add_action( 'wp_ajax_ac_serial_numbers_update_product_key_source_from_order_page', array( $this, 'update_product_key_source_from_order_page' ) );
		add_action( 'wp_ajax_ac_serial_numbers_update_product_mapping', array( $this, 'update_product_mapping' ) );
		add_action( 'wp_ajax_ac_serial_numbers_update_product_mapping_from_shop_order', array( $this, 'update_product_mapping_shop_order' ) );
		add_action( 'wp_ajax_ac_serial_numbers_clear_transient_data', array( $this, 'ac_serial_numbers_clear_transient_data' ) );
		add_action( 'wp_ajax_ac_serial_numbers_request_new_keys', array( $this, 'ac_serial_numbers_request_new_keys' ) );
	}

	public function ac_serial_numbers_request_new_keys(){
		// var_dump($_POST);

		if(isset($_POST['order_id'])){
			$order = wc_get_order($_POST['order_id']);
			$orders_data = ( new AC_Serial_Order_Data( $order, 'request-new' ) );
			$response = AC_Serial_Numbers_Cart_Tracking::send_cart_data( $orders_data->to_array(), '/updating/orders');
			$result = wp_remote_retrieve_body($response);
			$decoded_data = json_decode($result, true);
			if ($decoded_data === null) {
				error_log("Error decoding JSON: " . json_last_error_msg());
				return false;
			}

			if (!empty($decoded_data) && isset($decoded_data['success']) && $decoded_data['success'] == true) { // Check if data is not empty
				update_post_meta($_POST['order_id'], '_ac_shop_order_reload', 3);
				echo json_encode($decoded_data);
				die();
			} else {
				echo "failed";
				die();			
			}
		}
		die();
	}

	public function update_product_key_source() {
		$product_id = isset($_POST['product_id']) ? $_POST['product_id'] : '';
		$key_source = isset($_POST['key_source']) ? $_POST['key_source'] : '';

		if(!empty($key_source) && !empty($product_id)){
			update_post_meta( $product_id, '_is_serial_number', 'yes' );
			update_post_meta( $product_id, '_ac_serial_numbers_key_source', $key_source );
			wp_send_json_success(['message' => 'Item updated successfully!']);
		}else{
			wp_send_json_error(['message' => 'Invalid request']);
		}
		die(1);
	}
	public function update_product_key_source_from_order_page() {
		$order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';
		$order_item_id = isset($_POST['order_item_id']) ? $_POST['order_item_id'] : '';
		$key_source = isset($_POST['key_source']) ? $_POST['key_source'] : '';

		if(!empty($key_source) && !empty($order_id)){
			// update_post_meta( $order_id, '_is_serial_number', 'yes' );
			update_post_meta( $order_id, '_ac_serial_numbers_key_source_' . $order_item_id, $key_source );
			wp_send_json_success(['message' => 'Item updated successfully!']);
		}else{
			wp_send_json_error(['message' => 'Invalid request']);
		}
		die(1);
	}
	
	public function update_product_mapping() {
		if( isset($_POST['local_product_id']) && isset($_POST['type']) ){

			$post_id = intval($_POST['local_product_id']);
			$remote_product_id = isset($_POST['remote_product_id']) ? $_POST['remote_product_id'] : '';
			$remote_product_title = isset($_POST['remote_product_title']) ? $_POST['remote_product_title'] : '';

			$type = $_POST['type'];

			$selected_items = get_post_meta($post_id, '_ac_remote_product', true);
			$selected_items = !empty($selected_items) ? json_decode($selected_items, true) : [];

			if (!is_array($selected_items)) {
				$selected_items = [];
			}

			switch ($type){
				case 'update':
					if (!empty($post_id) && !empty($remote_product_id) && !empty($remote_product_title) && !in_array($remote_product_id, $selected_items)){
						$selected_items[] = ["id" => $remote_product_id, "text" => $remote_product_title];
						update_post_meta( $post_id, '_ac_remote_product', wp_json_encode(array_values($selected_items)) );
						update_post_meta( $post_id, '_delivery_quantity', count($selected_items) );
						wp_send_json_success(['message' => 'Item updated successfully!', 'updated_items' => $selected_items]);
					}else{
						wp_send_json_error(['message' => 'Invalid request']);
					}
					break;
				case 'clear':
					if (!empty($post_id) && !empty($remote_product_id)){
						$selected_items = array_filter($selected_items, function ($item) use ($remote_product_id) {
							return $item['id'] !== $remote_product_id;
						});
						update_post_meta( $post_id, '_ac_remote_product', wp_json_encode(array_values($selected_items)) );
						update_post_meta( $post_id, '_delivery_quantity', count($selected_items) );
						wp_send_json_success(['message' => 'Item updated successfully!', 'updated_items' => $selected_items]);

					}else{
						wp_send_json_error(['message' => 'Invalid request']);
					}
					break;
			}
		}
		die(1);
	}
	public function update_product_mapping_shop_order() {
		if( isset($_POST['order_id']) && isset($_POST['type']) ){
			$order_id = intval($_POST['order_id']);
			$order_item_id = intval($_POST['order_item_id']);

			$type = $_POST['type'];
			$data = isset($_POST['data']) ? $_POST['data'] : [];

			switch ($type){
				case 'update':
					if (!empty($order_id)){
						update_post_meta( $order_id, '_ac_remote_product_' . $order_item_id, wp_json_encode(array_values($data)) );
						update_post_meta( $order_id, '_delivery_quantity', count($data) );
						wp_send_json_success(['message' => 'Item updated successfully!', 'updated_items' => $data]);
					}else{
						wp_send_json_error(['message' => 'Invalid request']);
					}
					break;
				case 'clear':
					if (!empty($order_id)){
						
						update_post_meta( $order_id, '_ac_remote_product_' . $order_item_id, wp_json_encode(array_values($data)) );
						update_post_meta( $order_id, '_delivery_quantity', count($data) );
						wp_send_json_success(['message' => 'Item updated successfully!', 'updated_items' => $data]);

					}else{
						wp_send_json_error(['message' => 'Invalid request']);
					}
					break;
			}
		}
		die(1);
	}

	function ac_serial_numbers_clear_transient_data() {
		echo AC_Serial_Numbers_Query::init()->clear_transients([AC_SERIAL_NUMBER_REMOTE_TRANSIENT]);
		die();
	}

	/**
	 * Include any classes we need within admin.
	 */
	public static function includes() {
		require_once dirname( __FILE__ ) . '/class-ac-serial-numbers-admin-metaboxes.php';
		require_once dirname( __FILE__ ) . '/class-ac-serial-numbers-admin-settings.php';
		require_once dirname( __FILE__ ) . '/class-ac-serial-numbers-admin-menus.php';
		require_once dirname( __FILE__ ) . '/class-ac-serial-numbers-admin-notice.php';
		require_once dirname( __FILE__ ) . '/class-ac-serial-numbers-admin-actions.php';
		require_once dirname( __FILE__ ) . '/screen/class-ac-serial-numbers-activations-screen.php';
		require_once dirname( __FILE__ ) . '/screen/class-ac-serial-numbers-serial-numbers-screen.php';
		require_once dirname( __FILE__ ) . '/screen/class-ac-serial-numbers-add-volume-license-screen.php';
		require_once dirname( __FILE__ ) . '/screen/class-ac-serial-numbers-stock-manager-screen.php';
	}

	/**
	 * Enqueue admin related assets
	 *
	 * @param $hook
	 *
	 * @since 1.2.0
	 */
	public function admin_scripts( $hook ) {
		if ( ! ac_serial_numbers()->is_wc_active() ) {
			return;
		}

		$css_url = ac_serial_numbers()->plugin_url() . '/assets/css';
		$js_url  = ac_serial_numbers()->plugin_url() . '/assets/js';
		$version = ac_serial_numbers()->get_version();


		wp_enqueue_style( 'ac-serial-numbers-admin', $css_url . '/ac-serial-numbers-admin.css', array( 'woocommerce_admin_styles', 'jquery-ui-style' ), $version );
		wp_enqueue_style( 'jquery-ui-style' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'ac-serial-numbers-admin', $js_url . '/ac-serial-numbers-admin.js', [ 'jquery', 'wp-util', 'select2', ], $version, true );
		
		//datatable
		if (strpos($_SERVER['REQUEST_URI'], 'page=ac-serial-numbers-stock-manager') !== false) {
    		wp_enqueue_style( 'wcsnsc-bootstrap', "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.css");
            wp_enqueue_style( 'wcsnsc-dataTables', "https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css");
            
            wp_enqueue_script( 'wcsnsc-jquery' , 'https://code.jquery.com/jquery-3.5.1.js',[], $version, true);
            wp_enqueue_script( 'wcsnsc-dataTablesjs' , 'https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js',[], $version, true);
            wp_enqueue_script( 'wcsnsc-bootstrap4js' , 'https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js',[], $version, true);
	        wp_enqueue_script( 'wcsnsc-script' , $js_url.'/datatableinit.js',[], $version, true );
		}
	    //datatable
		$products = ac_fetch_products_data(); // Assuming this returns your product array
		$formatted_products = [];
		// Format products for Select2 (id and text)
		if (is_array($products)){
			$formatted_products = array_map(function($product) {
				$price = isset($product['sellPrice']) ? $product['sellPrice'] : '';
				$qty = isset($product['availableKeys']) ? $product['availableKeys'] : '';
				return array(
					'id'   => $product['_id'],
					'text' => $product['productName'] . ' ($' . $price . ', qty: ' . $qty . ')',
				);
			}, $products);
		}

		wp_localize_script( 'ac-serial-numbers-admin', 'ac_serial_numbers_admin_i10n', array(
			'i18n'    => array(
				'search_product' => __( 'Search product by name', 'ac-serial-numbers' ),
				'search_order'   => __( 'Search order', 'ac-serial-numbers' ),
				'show'           => __( 'Show', 'ac-serial-numbers' ),
				'hide'           => __( 'Hide', 'ac-serial-numbers' ),
			),
			'remote_data'	 => $formatted_products,
			'nonce'   => wp_create_nonce( 'ac_serial_numbers_admin_js_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );
	}

	/**
	 * @param $columns
	 *
	 * @return array|string[]
	 * @since 1.2.0
	 */
	public static function add_order_serial_column( $columns ) {
		$postition = 3;
		$new       = array_slice( $columns, 0, $postition, true ) + array( 'order_serials' => '<span class="dashicons dashicons-lock"></span>' ) + array_slice( $columns, $postition, count( $columns ) - $postition, true );;

		return $new;
	}

	/**
	 * @param $column
	 * @param $order_or_id
	 *
	 * @since 1.2.0
	 */
	public static function add_order_serial_column_content( $column, $order_or_id ) {
		if ( $column == 'order_serials' ) {
			$order_id = is_object( $order_or_id ) ? $order_or_id->get_id() : intval( $order_or_id );
			$total_ordered = ac_serial_numbers_order_has_serial_numbers( $order_id );
			if ( empty( $total_ordered ) ) {
				echo '&mdash;';
			} else {
				$total_connected = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( 'order_id', intval( $order_id ) )->count();
				if ( $total_ordered == $total_connected ) {
					$style = "color:green";
					$title = __( 'Order assigned all serial numbers.', 'ac-serial-numbers' );
				} else if ( ! empty( $total_connected ) && $total_ordered !== $total_connected ) {
					$style = "color:#f39c12";
					$title = sprintf( __( 'Order partially missing serial numbers(%d)', 'ac-serial-numbers' ), $total_ordered );
				} else {
					$style = "color:red";
					$title = sprintf( __( 'Order missing serial numbers(%d)', 'ac-serial-numbers' ), $total_ordered );
				}
				$url = add_query_arg( [ 'order_id' => $order_id ], admin_url( 'admin.php?page=ac-serial-numbers' ) );
				echo sprintf( '<a href="%s" title="%s"><span class="dashicons dashicons-lock" style="%s"></span></a>', $url, $title, $style );

			}
		}
	}

	/**
	 * Print style
	 *
	 * @since 1.0.0
	 */
	public static function print_style() {
		ob_start();
		?>
		<style>
			#woocommerce-product-data ul.wc-tabs li.ac_serial_numbers_options a:before {
				font-family: 'dashicons';
				content: "\f112";
			}

			._ac_serial_numbers_key_source_field label {
				margin: 0 !important;
				width: 100% !important;
			}

			.ac-serial-numbers-upgrade-box {
				background: #f1f1f1;
				padding: 10px;
				border-left: 2px solid #007cba;
			}

			.ac-serial-numbers-variation-settings .ac-serial-numbers-settings-title {
				border-bottom: 1px solid #eee;
				padding-left: 0 !important;
				font-weight: 600;
				font-size: 1em;
				padding-bottom: 5px;
			}

			.ac-serial-numbers-variation-settings label, .ac-serial-numbers-variation-settings legend {
				margin-bottom: 5px !important;
				display: inline-block;
			}

			.ac-serial-numbers-variation-settings .wc-radios li {
				padding-bottom: 0 !important;

			}

			.ac-serial-numbers-variation-settings .woocommerce-help-tip {
				margin-top: -5px;
			}

			.ac-serial-numbers-variation-settings .short {
				min-width: 200px;
			}
		</style>
		<?php
		$style = ob_get_contents();
		ob_get_clean();
		echo $style;
	}
}

new AC_Serial_Numbers_Admin();
