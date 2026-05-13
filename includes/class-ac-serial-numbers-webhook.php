<?php

class AC_Serial_Numbers_Webhook {

    private static $instance;

    public static function init() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AC_Serial_Numbers_Webhook ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    public function __construct() {
        add_action('rest_api_init', function () {
            register_rest_route('ac-serial-numbers/v1', '/webhook/', [
                'methods'  => 'POST',
                'callback' => array($this, 'webhook_handler'),
                'permission_callback' => function ($request) {
                    $secret_key = get_option('_ac_serial_numbers_webhook_secret'); // Retrieve stored key
                    $provided_key = $request->get_header('X-Webhook-Secret'); // Get key from request
                    return $provided_key === $secret_key; // Validate key
                }
            ]);
            register_rest_route('ac-serial-numbers/v1', '/order/update/', [
                'methods'  => 'POST',
                'callback' => array($this, 'webhook_order_update_handler'),
                'permission_callback' => function ($request) {
                    $secret_key = get_option('_ac_serial_numbers_webhook_secret'); // Retrieve stored key
                    $provided_key = $request->get_header('X-Webhook-Secret'); // Get key from request
                    return $provided_key === $secret_key; // Validate key
                }
            ]);
        });

	}

    public function webhook_order_update_handler(WP_REST_Request $request) {
        global $wpdb;
        $system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
        $system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
        $logger = wc_get_logger();
        $context = ['source' => 'wcsn-webhook-order-update'];
        $data = $request->get_json_params();
        if (empty($data)) {
            $logger->error('Invalid Data', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }

        $order_id = $data['invoice_no'] ?? 0;

        if (!$order_id) {
            $logger->error('Order ID is required', $context);
            $logger->error(print_r($data, true), $context);
            return new WP_REST_Response(['message' => 'Order ID is required'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->error('Order not found', $context);
            $logger->error(print_r($data, true), $context);
            return new WP_REST_Response(['message' => 'Order not found'], 404);
        }

        acsn_write_log($data);

        if (isset($data['orderData']) && is_array($data['orderData'])) {
            
            foreach ($data['orderData'] as $order_item ) { // $data['orderData'] is an array of order items
                if(is_array($order_item['serialKeys']) && count($order_item['serialKeys']) > 0){
					
					$keys = isset($order_item['serialKeys']) ? $order_item['serialKeys'] : [];
					
					foreach ($keys as $key) {
                        $help_text = !empty($system_activation_guide) ? $system_activation_guide . ' | Support Email: ' . $system_support_email : $key['activationGuide'] . ' | Support Email: ' . $system_support_email;
                        $data_array = array(
                            'serial_key' => $key['serialNumber'] . ' | ' . $help_text ?? '',
                            'product_id' => isset($order_item['cp_id']) ? $order_item['cp_id'] : $key['client_product_id'],
                            'activation_limit' => $key['activation_limit'] ?? 1,
                            'activation_count' => 0,
                            'order_id'   => $order_id,
                            'vendor_id' => $key['supplierId'] ?? '',
                            'status' => 'sold',
                            'validity' => null,						
                            'order_date' => isset($key['createdAt']) ? $key['createdAt'] :( isset($key['updatedAt']) ? $key['updatedAt'] : date('Y-m-d H:i:s')),
                            'source' => 'reseller',
                            'created_date' => isset($key['createdAt']) ? $key['createdAt'] :( isset($key['updatedAt']) ? $key['updatedAt'] : date('Y-m-d H:i:s')),
                        );
                        $logger->info(print_r($data_array, true), $context);
						$wpdb->insert(
							$wpdb->prefix . 'serial_numbers',
							$data_array
						);
					}
				}
            }

            // Update order status to "completed"
            if ($this->get_order_serial_count($order_id) > 0 && !$this->get_order_serial_status($order_id)){
                $order->update_status('completed', 'Order updated via TIC KeyManager Webhook');
                
                $logger->info('Order status updated to partially completed', $context);
                $logger->info(print_r($data, true), $context);
                
                return new WP_REST_Response(['message' => 'Order status updated to partially completed', 'status' => 'partial' ], 200);
            } else if ($this->get_order_serial_count($order_id) > 0 && $this->get_order_serial_status($order_id)){
                $order->update_status('completed', 'Order updated via TIC KeyManager Webhook');

                $logger->info('Order status updated to completed', $context);
                $logger->info(print_r($data, true), $context);

                return new WP_REST_Response(['message' => 'Order status updated to completed', 'status' => 'complete' ], 200);
            }

        }else{
            $logger->error('Invalid Data', $context);
            $logger->error(print_r($data, true), $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }

    }

    public function webhook_handler(WP_REST_Request $request) {
        global $wpdb;
        $system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
	    $system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
        $logger = wc_get_logger();
	    $context = ['source' => 'wcsn-webhook'];
        $data = $request->get_json_params();
        if (empty($data)) {
            $logger->error('Invalid Data', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }
        $order_id = $data['invoice_no'] ?? 0;

        if (!$order_id) {
            $logger->error('Order ID is required', $context);
            $logger->error(print_r($data, true), $context);
            return new WP_REST_Response(['message' => 'Order ID is required'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->error('Order not found', $context);
            $logger->error(print_r($data, true), $context);
            return new WP_REST_Response(['message' => 'Order not found'], 404);
        }
       
        if (isset($data['orderData']) && is_array($data['orderData'])) {
            foreach ($data['orderData'] as $order_item ) { // $data['orderData'] is an array of order items
                if(is_array($order_item['serialKeys']) && count($order_item['serialKeys']) > 0){
					
					$keys = isset($order_item['serialKeys']) ? $order_item['serialKeys'] : [];
					
					foreach ($keys as $key) {
                        $help_text = !empty($system_activation_guide) ? $system_activation_guide . ' | Support Email: ' . $system_support_email : $key['activationGuide'] . ' | Support Email: ' . $system_support_email;
                        $data_array = array(
                            'serial_key' => $key['serialNumber'] . ' | ' . $help_text ?? '',
                            'product_id' => isset($order_item['cp_id']) ? $order_item['cp_id'] : $key['client_product_id'],
                            'activation_limit' => $key['activation_limit'] ?? 1,
                            'activation_count' => 0,
                            'order_id'   => $order_id,
                            'vendor_id' => $key['supplierId'] ?? '',
                            'status' => 'sold',
                            'validity' => null,						
                            'order_date' => isset($key['createdAt']) ? $key['createdAt'] :( isset($key['updatedAt']) ? $key['updatedAt'] : date('Y-m-d H:i:s')),
                            'source' => 'reseller',
                            'created_date' => isset($key['createdAt']) ? $key['createdAt'] :( isset($key['updatedAt']) ? $key['updatedAt'] : date('Y-m-d H:i:s')),
                        );
                        $logger->info(print_r($data_array, true), $context);
						$wpdb->insert(
							$wpdb->prefix . 'serial_numbers',
							$data_array
						);
					}
				}
            }
            // Update order status to "completed"
            if ($this->get_order_serial_count($order_id) > 0 && !$this->get_order_serial_status($order_id)){
                $order->update_status('completed', 'Order updated via TIC KeyManager Webhook');
                
                $logger->info('Order status updated to partially completed', $context);
                $logger->info(print_r($data, true), $context);
                
                new WP_REST_Response(['message' => 'Order status updated to partially completed', 'status' => 'partial' ], 200);
                $response = $this->send_order_status( ['_id' => $data['_id'], 'order_id' => $order_id, 'message' => 'Order status updated to partially completed', 'status' => 'partial']);
                $logger->info(print_r($response, true), $context);
                return;

            } else if ($this->get_order_serial_count($order_id) > 0 && $this->get_order_serial_status($order_id)){
                $order->update_status('completed', 'Order updated via TIC KeyManager Webhook');

                $logger->info('Order status updated to completed', $context);
                $logger->info(print_r($data, true), $context);

                new WP_REST_Response(['message' => 'Order status updated to completed', 'status' => 'complete' ], 200);
                $response = $this->send_order_status( ['_id' => $data['_id'], 'order_id' => $order_id, 'message' => 'Order status updated to completed', 'status' => 'complete'] );
                $logger->info(print_r($response, true), $context);
                return;
            }

        }else{
            $logger->error('Invalid Data', $context);
            $logger->error(print_r($data, true), $context);
            new WP_REST_Response(['message' => 'Invalid Data'], 400);
            $response = $this->send_order_status( ['message' => 'Invalid Data'] );
            $logger->info(print_r($response, true), $context);
            return;
        }
    }

    public static function get_order_serial_status( $order_id ) {
		$total_ordered = ac_serial_numbers_order_has_serial_numbers( $order_id );
		if ( empty( $total_ordered ) ) {
			return false;
		} else {
			$total_connected = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( 'order_id', intval( $order_id ) )->count();
			if ( $total_ordered == $total_connected ) {
				return true;
			} else {
				return false;
			}
		}
	}

    public static function get_order_serial_count( $order_id ) {
		$total_ordered = ac_serial_numbers_order_has_serial_numbers( $order_id );
		if ( empty( $total_ordered ) ) {
			return false;
		} else {
			$total_connected = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( 'order_id', intval( $order_id ) )->count();
			return $total_connected;
		}
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

    public function send_order_status( $data ) {
        if(self::api_url()){
            $response = wp_remote_post( 
                self::api_url() . '/notify/order/status',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'api-key' => self::get_auth_token(),
                    ],
                    'body' => json_encode($data),
                    'timeout'   => 20,
                ] 
            );
    
            if (is_wp_error($response)) {
                error_log("Error fetching product data: " . $response->get_error_message());
                return; // Stop on error
            }
            return [
                'code' => wp_remote_retrieve_response_code($response),
                'message' => wp_remote_retrieve_response_message($response),
            ];
        }
	}


}

AC_Serial_Numbers_Webhook::init();