<?php

class AC_Serial_Numbers_Cart_Tracking {

    private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		
		add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
			if (in_array($new_status, ['pending', 'processing', 'completed', 'refunded'])) {
				// Store the order ID in global or transient to process later
				global $orders_to_send;
				$orders_to_send[] = $order_id;
				$orders_to_send = array_unique($orders_to_send);
			}
		}, 10, 3);
		
		add_action('shutdown', function() {
			global $orders_to_send;
			if (empty($orders_to_send)) {
				return;
			}			
		
			if( is_array($orders_to_send) ) {
				foreach ($orders_to_send as $order_id) {
					$order = wc_get_order($order_id);
					$orders_data = ( new AC_Serial_Order_Data( $order ) );
					
					$response = $this::send_cart_data( $orders_data->to_array());

					if (is_wp_error($response)) {
						error_log("Error fetching product data: " . $response->get_error_message());
						return; // Stop on error
					}
				
					$http_code = wp_remote_retrieve_response_code($response);
					if ($http_code == 200) {
						$data = wp_remote_retrieve_body($response);
						$decoded_data = json_decode($data, true);

						acsn_write_log('orders_to_send', $decoded_data);
						return; // Stop on HTTP error
					}else if ($http_code == 409) {
						$data = wp_remote_retrieve_body($response);
						$decoded_data = json_decode($data, true);

						acsn_write_log('orders_to_send', $decoded_data);
						return; // Stop on HTTP error
					}else{
						error_log("HTTP Error: " . $http_code . " - " . wp_remote_retrieve_response_message($response));
						return; // Stop on HTTP error
					}
				}
			}
		});
	}

	public static function get_auth_token() {
		return get_option('ac_serial_numbers_api_key');
	}

	public static function api_url() {
        $api_url = get_option('ac_serial_numbers_api_endpoint');
        if($api_url){
            return apply_filters( 'ac_serial_number_store_api_url', $api_url . '/store' );
        }else{
            acsn_write_log("api_url", $api_url);
            return false;
        }
	}

	public static function send_cart_data( $cart_data, $path = '/incoming/orders' ) {
		if(self::api_url()){
			return wp_remote_post( 
				self::api_url() . $path,
				[
					'headers' => [
						'Content-Type' => 'application/json',
						'api-key' => self::get_auth_token(),
					],
					'body' => json_encode($cart_data),
					'timeout'   => 20,
				] 
			);
		}else{
			return [];
		}
	}
}

AC_Serial_Numbers_Cart_Tracking::instance();