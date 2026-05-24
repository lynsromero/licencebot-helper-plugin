<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_AJAX {

	/**
	 * AC_Serial_Numbers_AJAX constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ac_serial_numbers_search_products', array( $this, 'search_products' ) );
		add_action( 'wp_ajax_ac_serial_numbers_decrypt_key', array( $this, 'decrypt_key' ) );
		add_action( 'wp_ajax_ac_serial_numbers_refresh_counts', array( $this, 'refresh_counts' ) );
		add_action( 'wp_ajax_ac_serial_numbers_sync_order', array( $this, 'sync_order' ) );
		add_action( 'wp_ajax_ac_serial_numbers_view_license', array( $this, 'view_license_key' ) );
		add_action( 'wp_ajax_nopriv_ac_serial_numbers_view_license', array( $this, 'view_license_key' ) );
	}

	/**
	 * Search products.
	 *
	 * @since 1.1.6
	 */
	public function search_products() {
		$this->verify_nonce( 'ac_serial_numbers_admin_js_nonce', 'nonce' );
		$this->check_permission();
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( $_REQUEST['search'] ) : '';
		$page   = isset( $_REQUEST['page'] ) ? absint( $_REQUEST['page'] ) : 1;
		$types  = apply_filters( 'ac_serial_numbers_product_types', array( 'product' ) );
		global $wpdb;
		$query = AC_Serial_Numbers_Query::init()->table( 'posts' )
		                                ->where( 'post_status', 'publish' )
		                                ->whereRaw( 'post_type IN ("' . implode( '","', $types ) . '")' )
		                                ->whereRaw( "ID NOT IN  (SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type='product_variation') " )
		                                ->search( sanitize_text_field( $search ), array( 'post_title' ) )
		                                ->page( $page );
		
		$more  = false;
		if ( $query->count() > ( 20 * $page ) ) {
			$more = true;
		}
		$product_ids = $query->column( 0 );
		$results     = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$text = sprintf(
				'(#%1$s) %2$s',
				$product->get_id(),
				strip_tags( $product->get_formatted_name() )
			);

			$results[] = array(
				'id'   => $product->get_id(),
				'text' => $text
			);
		}
		wp_send_json(
			array(
				'page'       => $page,
				'results'    => $results,
				'pagination' => array(
					'more' => $more
				)
			)
		);
	}

	/**
	 * Decrypt key
	 * @since 1.2.0
	 */
	public function decrypt_key() {
		$this->verify_nonce( 'ac_serial_numbers_decrypt_key', 'nonce' );
		$this->check_permission();
		$serial_id = isset( $_REQUEST['serial_id'] ) ? sanitize_text_field( $_REQUEST['serial_id'] ) : '';
		if ( empty( $serial_id ) ) {
			$this->send_error( [ 'message' => __( 'Could not detect the serial number to decrypt', 'ac-serial-numbers' ) ] );
		}

		$serial_number = ac_serial_numbers_get_serial_number( $serial_id );
		if ( empty( $serial_number ) ) {
			$this->send_error( [ 'message' => __( 'Could not find the serial number to decrypt', 'ac-serial-numbers' ) ] );
		}

		$this->send_success( [ 'key' => ac_serial_numbers_decrypt_key( $serial_number->serial_key ) ] );

	}

	/**
	 * Refresh license counts from LicenceBot API.
	 *
	 * @since 3.1.2
	 */
	public function refresh_counts() {
		$this->verify_nonce( 'ac_serial_numbers_admin_js_nonce', 'nonce' );
		$this->check_permission();

		$data = ac_serial_numbers_get_license_counts( null, null, true );

		if ( false === $data ) {
			$this->send_error( [ 'message' => __( 'Failed to fetch license counts from LicenceBot.', 'ac-serial-numbers' ) ] );
		}

		$this->send_success( [
			'totals'   => $data['totals'],
			'products' => $data['products'] ?? [],
		] );
	}

	/**
	 * Sync serial numbers for a specific order from LicenceBot.
	 *
	 * @since 3.1.2
	 */
	public function sync_order() {
		$this->verify_nonce( 'ac_serial_numbers_admin_js_nonce', 'nonce' );
		$this->check_permission();

		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;

		if ( empty( $order_id ) ) {
			$this->send_error( [ 'message' => __( 'Invalid order ID.', 'ac-serial-numbers' ) ] );
		}

		$synced = ac_serial_numbers_sync_order_serials( $order_id );

		$this->send_success( [
			'message'      => sprintf( __( 'Synced %d serial number(s).', 'ac-serial-numbers' ), $synced ),
			'synced_count' => $synced,
		] );
	}

	/**
	 * View license key from frontend (order-received / view-order page).
	 * Logs the view with IP, time, and product info.
	 *
	 * @since 3.5.2
	 */
	public function view_license_key() {
		$this->verify_nonce( 'ac_serial_numbers_view_license', 'nonce' );

		$serial_id   = isset( $_REQUEST['serial_id'] ) ? absint( $_REQUEST['serial_id'] ) : 0;
		$order_id    = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		$product_id  = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		$product_title = isset( $_REQUEST['product_title'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['product_title'] ) ) : '';
		$order_key   = isset( $_REQUEST['order_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_key'] ) ) : '';

		if ( empty( $serial_id ) || empty( $order_id ) ) {
			$this->send_error( array( 'message' => __( 'Invalid request.', 'ac-serial-numbers' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->send_error( array( 'message' => __( 'Order not found.', 'ac-serial-numbers' ) ) );
		}

		if ( $order->get_order_key() !== $order_key ) {
			$this->send_error( array( 'message' => __( 'Invalid order key.', 'ac-serial-numbers' ) ) );
		}

		if ( 'completed' !== $order->get_status( 'edit' ) ) {
			$this->send_success( array( 'status' => 'processing' ) );
		}

		$serial_number = ac_serial_numbers_get_serial_number( $serial_id );
		if ( empty( $serial_number ) ) {
			$this->send_error( array( 'message' => __( 'Serial number not found.', 'ac-serial-numbers' ) ) );
		}

		if ( (int) $serial_number->order_id !== (int) $order_id ) {
			$this->send_error( array( 'message' => __( 'Serial number does not belong to this order.', 'ac-serial-numbers' ) ) );
		}

		$decrypted_key = ac_serial_numbers_decrypt_key( $serial_number->serial_key );

		if ( empty( $product_title ) ) {
			$product_title = get_the_title( $serial_number->product_id );
		}

		AC_Serial_Numbers_View_Log::insert( array(
			'serial_id'     => $serial_id,
			'order_id'      => $order_id,
			'product_id'    => $product_id,
			'product_title' => $product_title,
			'serial_key'    => $decrypted_key,
			'ip_address'    => self::get_client_ip(),
			'viewed_at'     => current_time( 'mysql' ),
		) );

		$this->send_success( array( 'key' => $decrypted_key ) );
	}

	private static function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}
		return sanitize_text_field( $ip );
	}

	/**
	 * Check permission
	 *
	 * since 1.0.0
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			self::send_error( __( 'Error: You are not allowed to do this.', 'ac-serial-numbers' ) );
		}
	}

	/**
	 * Verify nonce request
	 * since 1.0.0
	 *
	 * @param $action
	 */
	public function verify_nonce( $action, $field = '_wpnonce' ) {
		if ( ! isset( $_REQUEST[ $field ] ) || ! wp_verify_nonce( $_REQUEST[ $field ], $action ) ) {
			self::send_error( __( 'Error: Nonce verification failed', 'ac-serial-numbers' ) );
		}
	}

	/**
	 * Wrapper function for sending success response
	 * since 1.0.0
	 *
	 * @param null $data
	 */
	public function send_success( $data = null ) {
		wp_send_json_success( $data );
	}

	/**
	 * Wrapper function for sending error
	 * since 1.0.0
	 *
	 * @param null $data
	 */
	public function send_error( $data = null ) {
		wp_send_json_error( $data );
	}
}

new AC_Serial_Numbers_AJAX();
