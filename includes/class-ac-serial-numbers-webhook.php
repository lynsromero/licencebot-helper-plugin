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
                    $auth_secret = get_option('_ac_serial_auth_secret');
                    if (empty($auth_secret)) {
                        $auth_secret = get_option('_ac_serial_numbers_webhook_secret');
                    }
                    $provided_key = $request->get_header('X-Webhook-Secret');
                    return $provided_key === $auth_secret;
                }
            ]);
            register_rest_route('ac-serial-numbers/v1', '/order/update/', [
                'methods'  => 'POST',
                'callback' => array($this, 'webhook_order_update_handler'),
                'permission_callback' => function ($request) {
                    $auth_secret = get_option('_ac_serial_auth_secret');
                    if (empty($auth_secret)) {
                        $auth_secret = get_option('_ac_serial_numbers_webhook_secret');
                    }
                    $provided_key = $request->get_header('X-Webhook-Secret');
                    return $provided_key === $auth_secret;
                }
            ]);
        });

	}

    public function webhook_order_update_handler(WP_REST_Request $request) {
        global $wpdb;
        $system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
        $system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
        $logger = wc_get_logger();
        $context = ['source' => 'licencebot-webhook-delivery'];
        $data = $request->get_json_params();
        if (empty($data)) {
            $logger->error('Webhook: Invalid Data', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }

        $order_id = $data['invoice_no'] ?? 0;

        if (!$order_id) {
            $logger->error('Webhook: Order ID is required', $context);
            return new WP_REST_Response(['message' => 'Order ID is required'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->error('Webhook: Order not found', $context);
            return new WP_REST_Response(['message' => 'Order not found'], 404);
        }

        $logger->info('Webhook: Received for order #' . $order_id . ' with ' . count($data['orderData'] ?? []) . ' product entries', $context);

        $total_inserted = 0;
        $total_skipped = 0;

        if (isset($data['orderData']) && is_array($data['orderData'])) {
            
            foreach ($data['orderData'] as $order_item ) {
                if (!is_array($order_item['serialKeys']) || count($order_item['serialKeys']) === 0) {
                    continue;
                }
				
				$keys = $order_item['serialKeys'];
				$cp_id = $order_item['cp_id'] ?? 0;
				
				foreach ($keys as $key) {
                    $serial_number = $key['serialNumber'] ?? '';
                    if (empty($serial_number)) {
                        $logger->warning('Webhook: Empty serial number for order #' . $order_id, $context);
                        continue;
                    }

                    $help_text = !empty($system_activation_guide) ? $system_activation_guide . ' | Support Email: ' . $system_support_email : ($key['activationGuide'] ?? '') . ' | Support Email: ' . $system_support_email;
                    $serial_key_value = $serial_number . ' | ' . $help_text;
                    $encrypted_key = ac_serial_numbers_encrypt_key($serial_key_value);
                    $product_id = !empty($cp_id) ? $cp_id : ($key['client_product_id'] ?? 0);

                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}serial_numbers WHERE product_id=%d AND serial_key=%s AND order_id=%d",
                        $product_id,
                        $encrypted_key,
                        $order_id
                    ));

                    if ($existing) {
                        $logger->info('Webhook: Duplicate key skipped for order #' . $order_id . ', product #' . $product_id, $context);
                        $total_skipped++;
                        continue;
                    }

                    $data_array = array(
                        'serial_key'       => $encrypted_key,
                        'product_id'       => $product_id,
                        'activation_limit' => $key['activation_limit'] ?? 1,
                        'activation_count' => 0,
                        'order_id'         => $order_id,
                        'vendor_id'        => $key['supplierId'] ?? '',
                        'status'           => 'sold',
                        'validity'         => null,
                        'order_date'       => current_time('mysql'),
                        'source'           => 'reseller',
                        'created_date'     => current_time('mysql'),
                    );

                    $inserted = $wpdb->insert(
                        $wpdb->prefix . 'serial_numbers',
                        $data_array
                    );

                    if ($inserted) {
                        $total_inserted++;
                    } else {
                        $logger->error('Webhook: Failed to insert serial key for order #' . $order_id . ': ' . $wpdb->last_error, $context);
                    }
				}
            }

            $logger->info('Webhook: Order #' . $order_id . ' — Inserted: ' . $total_inserted . ', Skipped (duplicate): ' . $total_skipped, $context);

            if ($total_inserted > 0) {
                if ($this->get_order_serial_count($order_id) > 0 && !$this->get_order_serial_status($order_id)) {
                    $order->update_status('completed', 'Licenses delivered via LicenceBot webhook (partial)');
                    $logger->info('Webhook: Order #' . $order_id . ' status updated to completed (partial delivery)', $context);
                    return new WP_REST_Response(['message' => 'Order status updated, partial delivery', 'status' => 'partial', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);
                } else if ($this->get_order_serial_count($order_id) > 0 && $this->get_order_serial_status($order_id)) {
                    $order->update_status('completed', 'Licenses delivered via LicenceBot webhook');
                    $logger->info('Webhook: Order #' . $order_id . ' status updated to completed (full delivery)', $context);
                    return new WP_REST_Response(['message' => 'Order status updated, full delivery', 'status' => 'complete', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);
                }
            }

            return new WP_REST_Response(['message' => 'Processed', 'status' => 'ok', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);

        } else {
            $logger->error('Webhook: Invalid Data — missing orderData', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }

    }

    public function webhook_handler(WP_REST_Request $request) {
        global $wpdb;
        $system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
	    $system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
        $logger = wc_get_logger();
	    $context = ['source' => 'licencebot-webhook-delivery'];
        $data = $request->get_json_params();
        if (empty($data)) {
            $logger->error('Webhook: Invalid Data', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
        }
        $order_id = $data['invoice_no'] ?? 0;

        if (!$order_id) {
            $logger->error('Webhook: Order ID is required', $context);
            return new WP_REST_Response(['message' => 'Order ID is required'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $logger->error('Webhook: Order not found', $context);
            return new WP_REST_Response(['message' => 'Order not found'], 404);
        }

        $logger->info('Webhook: Received for order #' . $order_id . ' with ' . count($data['orderData'] ?? []) . ' product entries', $context);

        $total_inserted = 0;
        $total_skipped = 0;
       
        if (isset($data['orderData']) && is_array($data['orderData'])) {
            foreach ($data['orderData'] as $order_item ) {
                if (!is_array($order_item['serialKeys']) || count($order_item['serialKeys']) === 0) {
                    continue;
                }
				
				$keys = $order_item['serialKeys'];
				$cp_id = $order_item['cp_id'] ?? 0;
				
				foreach ($keys as $key) {
                    $serial_number = $key['serialNumber'] ?? '';
                    if (empty($serial_number)) {
                        $logger->warning('Webhook: Empty serial number for order #' . $order_id, $context);
                        continue;
                    }

                    $help_text = !empty($system_activation_guide) ? $system_activation_guide . ' | Support Email: ' . $system_support_email : ($key['activationGuide'] ?? '') . ' | Support Email: ' . $system_support_email;
                    $serial_key_value = $serial_number . ' | ' . $help_text;
                    $encrypted_key = ac_serial_numbers_encrypt_key($serial_key_value);
                    $product_id = !empty($cp_id) ? $cp_id : ($key['client_product_id'] ?? 0);

                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}serial_numbers WHERE product_id=%d AND serial_key=%s AND order_id=%d",
                        $product_id,
                        $encrypted_key,
                        $order_id
                    ));

                    if ($existing) {
                        $logger->info('Webhook: Duplicate key skipped for order #' . $order_id . ', product #' . $product_id, $context);
                        $total_skipped++;
                        continue;
                    }

                    $data_array = array(
                        'serial_key'       => $encrypted_key,
                        'product_id'       => $product_id,
                        'activation_limit' => $key['activation_limit'] ?? 1,
                        'activation_count' => 0,
                        'order_id'         => $order_id,
                        'vendor_id'        => $key['supplierId'] ?? '',
                        'status'           => 'sold',
                        'validity'         => null,
                        'order_date'       => current_time('mysql'),
                        'source'           => 'reseller',
                        'created_date'     => current_time('mysql'),
                    );

                    $inserted = $wpdb->insert(
                        $wpdb->prefix . 'serial_numbers',
                        $data_array
                    );

                    if ($inserted) {
                        $total_inserted++;
                    } else {
                        $logger->error('Webhook: Failed to insert serial key for order #' . $order_id . ': ' . $wpdb->last_error, $context);
                    }
				}
            }

            $logger->info('Webhook: Order #' . $order_id . ' — Inserted: ' . $total_inserted . ', Skipped (duplicate): ' . $total_skipped, $context);

            if ($total_inserted > 0) {
                if ($this->get_order_serial_count($order_id) > 0 && !$this->get_order_serial_status($order_id)) {
                    $order->update_status('completed', 'Licenses delivered via LicenceBot webhook (partial)');
                    $logger->info('Webhook: Order #' . $order_id . ' status updated to completed (partial delivery)', $context);
                    $response = new WP_REST_Response(['message' => 'Order status updated, partial delivery', 'status' => 'partial', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);
                    $this->send_order_status(['_id' => $data['_id'] ?? '', 'order_id' => $order_id, 'message' => 'Order status updated, partial delivery', 'status' => 'partial']);
                    return $response;

                } else if ($this->get_order_serial_count($order_id) > 0 && $this->get_order_serial_status($order_id)) {
                    $order->update_status('completed', 'Licenses delivered via LicenceBot webhook');
                    $logger->info('Webhook: Order #' . $order_id . ' status updated to completed (full delivery)', $context);
                    $response = new WP_REST_Response(['message' => 'Order status updated, full delivery', 'status' => 'complete', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);
                    $this->send_order_status(['_id' => $data['_id'] ?? '', 'order_id' => $order_id, 'message' => 'Order status updated, full delivery', 'status' => 'complete']);
                    return $response;
                }
            }

            return new WP_REST_Response(['message' => 'Processed', 'status' => 'ok', 'inserted' => $total_inserted, 'skipped' => $total_skipped], 200);

        } else {
            $logger->error('Webhook: Invalid Data — missing orderData', $context);
            return new WP_REST_Response(['message' => 'Invalid Data'], 400);
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
        if ( self::api_url() ) {
            $response = wp_remote_post(
                self::api_url() . '/notify/order/status',
                array(
                    'headers' => ac_serial_numbers_get_api_headers(),
                    'body'    => json_encode( $data ),
                    'timeout' => 20,
                )
            );

            if ( is_wp_error( $response ) ) {
                error_log( "Error fetching product data: " . $response->get_error_message() );
                return;
            }
            return array(
                'code'    => wp_remote_retrieve_response_code( $response ),
                'message' => wp_remote_retrieve_response_message( $response ),
            );
        }
    }


}

AC_Serial_Numbers_Webhook::init();