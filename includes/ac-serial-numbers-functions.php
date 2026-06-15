<?php
defined( 'ABSPATH' ) || exit();
/**
 * Get serial number user role.
 *
 * @return mixed|void
 * @since 1.2.0
 */
function ac_serial_numbers_get_user_role() {
	return apply_filters( 'ac_serial_numbers_user_role', 'manage_woocommerce' );
}

/**
 * Build standard API headers with store identity for all LicenceBot API calls.
 *
 * Includes api-key, cb-platform (site URL), x-store-id, and x-store-token
 * so helper-api can resolve the correct store for passthrough requests.
 *
 * @param array $extra Additional headers to merge in.
 * @return array
 * @since 3.2.0
 */
function ac_serial_numbers_get_api_headers( $extra = array() ) {
	$headers = array(
		'Content-Type' => 'application/json',
		'api-key'      => get_option( 'ac_serial_numbers_api_key' ),
		'cb-platform'  => home_url(),
	);

	$store_id    = get_option( '_ac_serial_store_id' );
	$store_token = get_option( '_ac_serial_store_token' );

	if ( $store_id ) {
		$headers['x-store-id'] = $store_id;
	}
	if ( $store_token ) {
		$headers['x-store-token'] = $store_token;
	}

	return array_merge( $headers, $extra );
}

/**
 * Sanitize boolean
 *
 * @param $string
 *
 * @return mixed
 * @since 1.2.0
 */
function ac_serial_numbers_validate_boolean( $string ) {
	return filter_var( $string, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
}

/**
 * Check if product enabled for selling serial numbers.
 *
 * @param $product_id
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_product_serial_enabled( $product_id ) {
	return 'yes' == get_post_meta( $product_id, '_is_serial_number', true );
}

/**
 * Check if product source type.
 *
 * @param $product_id
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_product_serial_source_type( $product_id ) {
	return 'reseller' == get_post_meta( $product_id, '_ac_serial_numbers_key_source', true ) ? true : false;
}

/**
 * Get refund statuses.
 *
 * @return array|bool|mixed
 * @since 1.2.0
 */
function ac_serial_numbers_get_revoke_statuses() {
	$refund_statuses = wp_cache_get( 'ac_serial_numbers_get_revoke_statuses' );
	if ( $refund_statuses == false ) {
		$refund_statuses = [];
		if ( 'yes' == get_option( 'ac_serial_numbers_revoke_status_refunded' ) ) {
			$refund_statuses[] = 'refunded';
		}
		if ( 'yes' == get_option( 'ac_serial_numbers_revoke_status_cancelled' ) ) {
			$refund_statuses[] = 'cancelled';
		}
		if ( 'yes' == get_option( 'ac_serial_numbers_revoke_status_failed' ) ) {
			$refund_statuses[] = 'failed';
		}
	}

	return $refund_statuses;
}

/**
 * Check if software disabled.
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_software_support_disabled() {
	return 'yes' == get_option( 'ac_serial_numbers_disable_software_support' );
}

/**
 * Check if serial number is reusing.
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_reuse_serial_numbers() {
	return 'yes' == get_option( 'ac_serial_numbers_reuse_serial_number' );
}

/**
 * Encrypt serial number.
 *
 * @param $key
 *
 * @return false|string
 * @since 1.2.0
 */
function ac_serial_numbers_encrypt_key( $key ) {
	return AC_Serial_Numbers_Encryption::maybeEncrypt( $key );
}

/**
 * Decrypt number.
 *
 * @param $key
 *
 * @return false|string
 * @since 1.2.0
 */
function ac_serial_numbers_decrypt_key( $key ) {
	return AC_Serial_Numbers_Encryption::maybeDecrypt( $key );
}

/**
 * Get serial number's statuses.
 *
 * since 1.2.0
 * @return array
 */
function ac_serial_numbers_get_serial_number_statuses() {
	$statuses = array(
		'available' => __( 'Available', 'ac-serial-numbers' ),
		'sold'      => __( 'Sold', 'ac-serial-numbers' ),
		'refunded'  => __( 'Refunded', 'ac-serial-numbers' ),
		'cancelled' => __( 'Cancelled', 'ac-serial-numbers' ),
		'expired'   => __( 'Expired', 'ac-serial-numbers' ),
		'failed'    => __( 'Failed', 'ac-serial-numbers' ),
		'inactive'  => __( 'Inactive', 'ac-serial-numbers' ),
	);

	return apply_filters( 'ac_serial_numbers_serial_number_statuses', $statuses );
}

/**
 * Get key sources.
 *
 * @return mixed|void
 * @since 1.2.0
 */
function ac_serial_numbers_get_key_sources() {
	$sources = array(
		'custom_source' => __( 'System/Custom Source', 'ac-serial-numbers' ),
		'reseller' => __( 'TIC Reseller Panel', 'ac-serial-numbers' ),
	);

	return apply_filters( 'ac_serial_numbers_key_sources', $sources );
}


/**
 * Check if order contains serial numbers.
 *
 * @param $order
 *
 * @return bool|int
 * @since 1.2.0
 */
function ac_serial_numbers_order_has_serial_numbers( $order ) {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}

	$order_id = $order->get_id();

	// bail for no order
	if ( ! $order_id ) {
		return false;
	}

	$quantity = 0;
	$items    = $order->get_items();

	foreach ( $items as $item ) {
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		if ( ! ac_serial_numbers_product_serial_enabled( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() ) ) {
			continue;
		}

		$line_quantity     = $item->get_quantity();
		$per_item_quantity = (int) get_post_meta( $product_id, '_delivery_quantity', true );
		$needed_quantity   = $line_quantity * ( empty( $per_item_quantity ) ? 1 : absint( $per_item_quantity ) );
		$quantity          += $needed_quantity;
	}

	return $quantity;
}

/**
 * Connect serial numbers with order.
 *
 * @param $order_id
 *
 * @return bool|int
 * @since 1.2.0
 */
function ac_serial_numbers_order_connect_serial_numbers( $order_id ) {
	global $wpdb;
	// $system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
	// $system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
	$order    = wc_get_order( $order_id );
	$order_id = $order->get_id();

	// bail for no order
	if ( ! $order_id ) {
		return false;
	}
	$items = $order->get_items();

	$total_added = 0;

	foreach ( $items as $item ) {
		/* @var $item WC_Order_Item_Product */
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

		$quantity = $item->get_quantity();

		// If product add as license 
		if ( ! ac_serial_numbers_product_serial_enabled( $product_id ) ) {
			continue;
		}

		$per_product_delivery_qty       = absint( apply_filters( 'ac_serial_numbers_per_product_delivery_qty', 1, $product_id ) ); 
		$per_product_total_delivery_qty = $quantity * $per_product_delivery_qty;
		$delivered_qty                  = AC_Serial_Numbers_Query::init()->table( 'serial_numbers' )->where( 'order_id', $order_id )->where( 'product_id', $product_id )->count();

		if ( $delivered_qty >= $per_product_total_delivery_qty ) {
			continue;
		}
		$total_delivery_qty = $per_product_total_delivery_qty - $delivered_qty;

		// the source will be use for fetch serial numbers from remote
		$source = ac_serial_numbers_product_serial_source_type( $product_id ) ? 'reseller' : 'custom_source';
		do_action( 'ac_serial_numbers_pre_order_item_connect_serial_numbers', $product_id, $total_delivery_qty, $source, $order_id );

		if ( 'custom_source' === $source ) {
			$serials = AC_Serial_Numbers_Query::init()->table( 'serial_numbers' )
												->where( 'product_id', $product_id )
												->where( 'status', 'available' )
												->where( 'source', $source )
												->limit( $total_delivery_qty )
												->column( 0 );
			foreach ( $serials as $serial_id ) {
				$updated     = $wpdb->update(
					$wpdb->prefix . 'serial_numbers',
					array(
						'order_id'   => $order_id,
						'status'     => 'sold',
						'order_date' => current_time( 'mysql' ),
					),
					array(
						'id' => $serial_id
					) );
				$total_added += $updated ? 1 : 0;
			}
		} elseif ( 'reseller' === $source ) {
			$system_activation_guide = get_option('ac_serial_numbers_system_activation_guide') ?? false;
			$system_support_email = get_option('ac_serial_numbers_support_email') ?? false;
			$logger = wc_get_logger();
			$logger_context = ['source' => 'licencebot-reseller-delivery'];

			$serial_numbers = ac_serial_numbers_get_serial_numbers( $item, $order, $order_id );
			
			if ( is_array( $serial_numbers ) && isset( $serial_numbers['data'] ) ) {
				$data = $serial_numbers['data'];
				if ( isset( $data['serialKeys'] ) && is_array( $data['serialKeys'] ) && count( $data['serialKeys'] ) > 0 ) {
					$keys = $data['serialKeys'];
					foreach ( $keys as $key ) {
						$serial_number = $key['serialNumber'] ?? '';
						if ( empty( $serial_number ) ) {
							continue;
						}
						$help_text = !empty( $system_activation_guide ) ? $system_activation_guide . ' | Support Email: ' . $system_support_email : ( $key['activationGuide'] ?? '' ) . ' | Support Email: ' . $system_support_email;
						$serial_key_value = $serial_number . ' | ' . $help_text;
						$encrypted_key = ac_serial_numbers_encrypt_key( $serial_key_value );

						$existing = $wpdb->get_var( $wpdb->prepare(
							"SELECT id FROM {$wpdb->prefix}serial_numbers WHERE product_id=%d AND serial_key=%s AND order_id=%d",
							$product_id,
							$encrypted_key,
							$order_id
						) );

						if ( $existing ) {
							continue;
						}

						$result = $wpdb->insert(
							$wpdb->prefix . 'serial_numbers',
							array(
								'serial_key'       => $encrypted_key,
								'product_id'       => $product_id,
								'activation_limit' => $key['activation_limit'] ?? 1,
								'activation_count' => 0,
								'order_id'         => $order_id,
								'vendor_id'        => $key['supplierId'] ?? '',
								'status'           => 'sold',
								'validity'         => null,
								'order_date'       => current_time( 'mysql' ),
								'source'           => 'reseller',
								'created_date'     => current_time( 'mysql' ),
							)
						);
						$total_added += $result ? 1 : 0;
					}
					$logger->info( 'Reseller delivery: Order #' . $order_id . ' — Inserted ' . count( $keys ) . ' keys for product #' . $product_id, $logger_context );
				} elseif ( isset( $data['reason'] ) ) {
					$logger->warning( 'Reseller delivery: Order #' . $order_id . ' — API returned reason: ' . $data['reason'], $logger_context );
				}
			} else {
				$logger->warning( 'Reseller delivery: Order #' . $order_id . ' — Invalid API response for product #' . $product_id, $logger_context );
			}
		}
	}
	do_action( 'ac_serial_numbers_order_connect_serial_numbers', $order_id, $total_added );
	return $total_added;
}

function ac_serial_numbers_get_serial_numbers( $order_item, $order, $order_id ) {
	$product_id = $order_item->get_variation_id() ? $order_item->get_variation_id() : $order_item->get_product_id();
	$original_id = get_post_meta($product_id, '_ac_remote_product_id', true);

	$data = [
		"invoice_no" => $order_id,
		"customer" => [
			"first_name" => $order->get_billing_first_name(),
			"last_name" => $order->get_billing_last_name(),
			"email" => $order->get_billing_email(),
			"phone" => $order->get_billing_phone(),
			"address" => $order->get_billing_address_1() . ', ' . $order->get_billing_address_2(),
			"city" => $order->get_billing_city(),
			"state" => $order->get_billing_state(),
			"zip" => $order->get_billing_postcode(),
			"country" => $order->get_billing_country(),
		],
		"product" => [
			"op_id" => $original_id,
			"cp_id" => $product_id,
			"title" => $order_item->get_name(),
			"quantity" => $order_item->get_quantity()
		],
	];

	$url = 	get_option('ac_serial_numbers_api_endpoint');
	$api_key = 	get_option('ac_serial_numbers_api_key');

	$response = wp_remote_post( $url . '/shop/new-order', array(
		'headers' => ac_serial_numbers_get_api_headers(),
		'body'    => json_encode( $data ),
	) );

	if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }
	$logger = wc_get_logger();
	$context = ['source' => 'wc-serial-numbers'];
	$body = wp_remote_retrieve_body($response);
	$logger->debug(print_r($body, true), $context);
	return json_decode($body, true);
}

/**
 * Disconnect serial numbers from order.
 *
 * @param $order_id
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_order_disconnect_serial_numbers( $order_id ) {
	$order    = wc_get_order( $order_id );
	$order_id = $order->get_id();

	// bail for no order
	if ( ! $order_id ) {
		return false;
	}

	if ( ! ac_serial_numbers_order_has_serial_numbers( $order ) ) {
		return false;
	}

	$reuse_serial_number = ac_serial_numbers_reuse_serial_numbers();
	$data                = array(
		'status' => $order->get_status( 'edit' ) == 'completed' ? 'cancelled' : $order->get_status( 'edit' ),
	);
	if ( $reuse_serial_number ) {
		$data['status']     = 'available';
		$data['order_id']   = '';
		$data['order_date'] = '';
	}
	if ( $reuse_serial_number ) {
		global $wpdb;
		AC_Serial_Numbers_Query::init()->table( 'serial_numbers' )->whereRaw( $wpdb->prepare( "serial_id IN (SELECT id from {$wpdb->prefix}serial_numbers WHERE order_id=%d)", $order_id ) )->delete();
	}
	do_action( 'ac_serial_numbers_pre_order_disconnect_serial_numbers', $order_id );

	$total_disconnected = AC_Serial_Numbers_Query::init()->table( 'serial_numbers' )->where( 'order_id', $order_id )->update( $data );

	do_action( 'ac_serial_numbers_order_disconnect_serial_numbers', $order_id, $total_disconnected );

	return $total_disconnected;
}


/**
 * Insert serial number.
 *
 * @param $args
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_insert_serial_number( $args ) {
	global $wpdb;
	$update = false;
	$order  = false;
	$args   = apply_filters( 'ac_serial_numbers_insert_serial_number_args', $args );
	$id     = ! empty( $args['id'] ) ? absint( $args['id'] ) : 0;
	if ( isset( $args['id'] ) && ! empty( trim( $args['id'] ) ) ) {
		$id          = (int) $args['id'];
		$update      = true;
		$item_before = ac_serial_numbers_get_serial_number( $id );
		if ( is_null( $item_before ) ) {
			return new \WP_Error( 'invalid_action', __( 'Could not find the item to  update', 'ac-serial-numbers' ) );
		}

		$args = array_merge( get_object_vars( $item_before ), $args );
	}

	$args              = array_map( 'trim', $args );
	$default_vendor    = get_user_by( 'email', get_option( 'admin_email' ) );
	$default_vendor_id = isset( $default_vendor->ID ) ? $default_vendor->ID : 0;
	$serial_key        = isset( $args['serial_key'] ) ? sanitize_textarea_field( $args['serial_key'] ) : '';
	$product_id        = isset( $args['product_id'] ) ? intval( $args['product_id'] ) : null;
	$activation_limit  = ! empty( $args['activation_limit'] ) ? intval( $args['activation_limit'] ) : 0;
	$order_id          = ! empty( $args['order_id'] ) ? intval( $args['order_id'] ) : 0;
	$order_date        = isset( $args['order_date'] ) && ! empty( $order_id ) ? sanitize_text_field( $args['order_date'] ) : null;
	$vendor_id         = ! empty( $args['vendor_id'] ) ? intval( $args['vendor_id'] ) : $default_vendor_id;
	$status            = empty( $args['status'] ) ? 'available' : sanitize_text_field( $args['status'] );
	$source            = empty( $args['source'] ) ? 'custom_source' : sanitize_text_field( $args['source'] );
	$validity          = ! empty( $args['validity'] ) ? intval( $args['validity'] ) : null;
	$expire_date       = isset( $args['expire_date'] ) ? sanitize_text_field( $args['expire_date'] ) : '';
	$created_date      = isset( $args['created_date'] ) ? sanitize_text_field( $args['created_date'] ) : current_time( 'mysql' );


	//is set product id?
	if ( empty( $product_id ) ) {
		return new \WP_Error( 'empty_content', __( 'You must select a product to add serial number.', 'ac-serial-numbers' ) );
	}

	//product exist?
	if ( empty( get_post( $product_id ) ) ) {
		return new \WP_Error( 'invalid_content', __( 'Invalid product selected.', 'ac-serial-numbers' ) );
	}
	//is set serial key?
	if ( empty( $serial_key ) ) {
		return new \WP_Error( 'empty_content', __( 'The Serial Key is empty. Please enter a serial key and try again', 'ac-serial-numbers' ) );
	}

	//is duplicate
	if ( ! apply_filters( 'ac_serial_numbers_allow_duplicate_serial_number', false ) ) {
		$exist_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}serial_numbers WHERE product_id=%d AND serial_key=%s", $product_id, apply_filters( 'ac_serial_numbers_maybe_encrypt', $serial_key ) ) );
		if ( ! empty( $exist_id ) && $exist_id != $id ) {
			return new \WP_Error( 'duplicate_key', __( 'Duplicate key is not allowed', 'ac-serial-numbers' ) );
		}
	}

	//updating ordered item
	if ( ! empty( $order_id ) ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( empty( $order ) ) {
			return new \WP_Error( 'invalid_order_id', __( 'Associated order is not valid.', 'ac-serial-numbers' ) );
		}
	}

	if ( ! array_key_exists( $status, ac_serial_numbers_get_serial_number_statuses() ) ) {
		return new \WP_Error( 'invalid_status', __( 'Unknown serial number status.', 'ac-serial-numbers' ) );
	}

	if ( $status == 'sold' && empty( $order ) ) {
		return new \WP_Error( 'invalid_status', __( 'Sold item must have a associated valid order.', 'ac-serial-numbers' ) );
	}

	if ( $order && $status == 'sold' ) {
		$items = $order->get_items();
		//error_log( print_r( $items, true ) );
		$valid_product = false;
		foreach ( $items as $item_id => $item ) {
//			if ( $item->get_id() === $product_id ) {
//				$valid_product = true;
//				break;
//			}
			$product        = $item->get_product();
			if ( $product->get_id() === $product_id ) {
				$valid_product = true;
				break;
			}
		}

		if ( ! $valid_product ) {
			return new \WP_Error( 'invalid_status', __( 'Order does not contains the product.', 'ac-serial-numbers' ) );
		}
	}

	//serial key set
	$serial_key = apply_filters( 'ac_serial_numbers_maybe_encrypt', sanitize_textarea_field( $serial_key ), $args );

	if ( $order && ( empty( $order_date ) || $order_date == '0000-00-00 00:00:00' ) && $order->get_date_completed() ) {
		$order_date = $order->get_date_completed()->format( 'Y-m-d H:i:s' );
	} elseif ( $order && ( empty( $order_date ) || $order_date == '0000-00-00 00:00:00' ) && ! $order->get_date_completed() ) {
		$order_date = current_time( 'mysql' );
	} elseif ( $order && ( ! empty( $order_date ) || $order_date == '0000-00-00 00:00:00' ) && $order->get_date_completed() ) {
		$order_date = date( 'Y-m-d H:i:s', strtotime( $order_date ) );
	} else {
		$order_date = null;
	}

	$data  = compact( 'id', 'serial_key', 'product_id', 'activation_limit', 'order_id', 'vendor_id', 'status', 'validity', 'expire_date', 'source', 'created_date', 'order_date' );
	$where = array( 'id' => $id );
	$data  = wp_unslash( $data );
	if ( $update ) {
		do_action( 'ac_serial_numbers_pre_update_serial_number', $id, $data );
		if ( false === $wpdb->update( "{$wpdb->prefix}serial_numbers", $data, $where ) ) {
			return new \WP_Error( 'db_update_error', __( 'Could not update serial number in the database', 'ac-serial-numbers' ), $wpdb->last_error );
		}
		do_action( 'ac_serial_numbers_update_serial_number', $id, $data, $item_before );
	} else {
		do_action( 'ac_serial_numbers_pre_insert_serial_number', $id, $data );
		if ( false === $wpdb->insert( "{$wpdb->prefix}serial_numbers", $data ) ) {

			return new \WP_Error( 'db_insert_error', __( 'Could not insert serial number into the database', 'ac-serial-numbers' ), $wpdb->last_error );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'ac_serial_numbers_insert_serial_number', $id, $data );
	}

	update_post_meta( $data['product_id'], '_is_serial_number', 'yes' );

	return $id;
}

/**
 * @param $args
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_update_serial_number( $args ) {
	$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
	if ( empty( $id ) ) {
		return new \WP_Error( 'no-id-found', __( 'No serial number ID found for updating', 'ac-serial-numbers' ) );
	}

	return ac_serial_numbers_insert_serial_number( $args );
}

/**
 * Update status.
 *
 * @param $id
 * @param $status
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_update_serial_number_status( $id, $status ) {
	return ac_serial_numbers_update_serial_number( [ 'id' => intval( $id ), 'status' => $status ] );
}


/**
 * Delete serial number.
 *
 * @param $id
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_delete_serial_number( $id ) {
	global $wpdb;
	$id = absint( $id );

	$item = ac_serial_numbers_get_serial_number( $id );
	if ( is_null( $item ) ) {
		return false;
	}
	do_action( 'ac_serial_numbers_pre_delete_serial_number', $id, $item );
	if ( false == $wpdb->delete( "{$wpdb->prefix}serial_numbers", array( 'id' => $id ), array( '%d' ) ) ) {
		return false;
	}
	do_action( 'ac_serial_numbers_delete_serial_number', $id, $item );

	return true;
}

/**
 * @param $id
 *
 * @return mixed
 * @since 1.2.0
 */
function ac_serial_numbers_get_serial_number( $id ) {
	return AC_Serial_Numbers_Query::init()->table( 'serial_numbers' )->find( intval( $id ) );
}

/**
 * Get activation
 *
 * @param $args
 *
 * @since 1.2.0
 */
function ac_serial_numbers_get_activation( $activation_id ) {
	return AC_Serial_Numbers_Query::init()->from( 'serial_numbers_activations' )->find( intval( $activation_id ) );
}

/**
 * @param $args
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_insert_activation( $args ) {
	global $wpdb;
	$update = false;
	$args   = apply_filters( 'ac_serial_numbers_insert_activation_args', $args );
	$id     = ! empty( $args['id'] ) ? absint( $args['id'] ) : 0;
	if ( isset( $args['id'] ) && ! empty( trim( $args['id'] ) ) ) {
		$id          = (int) $args['id'];
		$update      = true;
		$item_before = ac_serial_numbers_get_activation( $id );
		if ( is_null( $item_before ) ) {
			return new \WP_Error( 'invalid_action', __( 'Could not find the item to update', 'ac-serial-numbers' ) );
		}
		$args = array_merge( get_object_vars( $item_before ), $args );
	}

	$data = [
		'serial_id'       => isset( $args['serial_id'] ) ? absint( $args['serial_id'] ) : '',
		'instance'        => isset( $args['instance'] ) ? sanitize_text_field( $args['instance'] ) : '',
		'active'          => isset( $args['active'] ) ? intval( $args['active'] ) : '0',
		'platform'        => isset( $args['platform'] ) ? sanitize_text_field( $args['platform'] ) : '',
		'activation_time' => isset( $args['activation_time'] ) ? sanitize_text_field( $args['activation_time'] ) : current_time( 'mysql' ),
	];

	if ( empty( $data['serial_id'] ) ) {
		return new \WP_Error( 'empty_content', __( 'Serial ID is required.', 'ac-serial-numbers' ) );
	}

	$where = array( 'id' => $id );
	$data  = wp_unslash( $data );
	if ( $update ) {
		do_action( 'ac_serial_numbers_pre_update_activation', $id, $data );
		if ( false === $wpdb->update( "{$wpdb->prefix}serial_numbers_activations", $data, $where ) ) {
			return new \WP_Error( 'db_update_error', __( 'Could not update activation in the database', 'ac-serial-numbers' ), $wpdb->last_error );
		}
		do_action( 'ac_serial_numbers_update_activation', $id, $data, $item_before );
	} else {
		do_action( 'ac_serial_numbers_pre_insert_activation', $id, $data );
		if ( false === $wpdb->insert( "{$wpdb->prefix}serial_numbers_activations", $data ) ) {

			return new \WP_Error( 'db_insert_error', __( 'Could not insert activation into the database', 'ac-serial-numbers' ), $wpdb->last_error );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'ac_serial_numbers_insert_activation', $id, $data );
	}

	return $id;
}

/**
 * @param $args
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_update_activation( $args ) {
	$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
	if ( empty( $id ) ) {
		return new \WP_Error( 'no-id-found', __( 'No Activation ID found for updating', 'ac-serial-numbers' ) );
	}

	return ac_serial_numbers_insert_activation( $args );
}

/**
 * @param $id
 *
 * @return bool
 * @since 1.2.0
 */
function ac_serial_numbers_delete_activation( $id ) {
	global $wpdb;
	$id = absint( $id );

	$item = ac_serial_numbers_get_activation( $id );
	if ( is_null( $item ) ) {
		return false;
	}
	do_action( 'ac_serial_numbers_pre_delete_activation', $id, $item );
	if ( false == $wpdb->delete( "{$wpdb->prefix}serial_numbers_activations", array( 'id' => $id ), array( '%d' ) ) ) {
		return false;
	}
	do_action( 'ac_serial_numbers_delete_activation', $id, $item );

	return true;
}

function ac_serial_numbers_update_activation_count( $id ) {
	$activation = AC_Serial_Numbers_Query::init()->from( 'serial_numbers_activations' )->find( $id );
	if ( empty( $activation ) ) {
		return false;
	}
	global $wpdb;
	$activation_count = $wpdb->get_var( $wpdb->prepare( "SELECT count(id) from {$wpdb->prefix}serial_numbers_activations WHERE serial_id=%d AND active='1'", $activation->serial_id ) );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}serial_numbers SET activation_count = %d WHERE id=%d", intval( $activation_count ), intval( $activation->serial_id ) ) );

	return $activation_count;
}

add_action( 'ac_serial_numbers_update_activation', 'ac_serial_numbers_update_activation_count' );
add_action( 'ac_serial_numbers_delete_activation', 'ac_serial_numbers_update_activation_count' );
add_action( 'ac_serial_numbers_insert_activation', 'ac_serial_numbers_update_activation_count' );

/**
 * @param $id
 * @param int $status
 *
 * @return int|WP_Error
 * @since 1.2.0
 */
function ac_serial_numbers_update_activation_status( $id, $status = 1 ) {
	if ( empty( $id ) ) {
		return new \WP_Error( 'no-id-found', __( 'No Activation ID found for updating', 'ac-serial-numbers' ) );
	}

	return ac_serial_numbers_insert_activation( array( [ 'id' => $id, 'active' => intval( $status ) ] ) );
}

/**
 * Serial number order table get columns.
 *
 * @return mixed|void
 * @since 1.2.0
 */
function ac_serial_numbers_get_order_table_columns() {
	$columns = array(
		'product'          => __( 'Product', 'ac-serial-numbers' ),
		'serial_key'       => __( 'Serial Number', 'ac-serial-numbers' ),
		'activation_email' => __( 'Email', 'ac-serial-numbers' ),
		'activation_limit' => __( 'Activation Limit', 'ac-serial-numbers' ),
		'expire_date'      => __( 'Expires', 'ac-serial-numbers' ),
	);

	return apply_filters( 'ac_serial_numbers_order_table_columns', $columns );
}

/**
 * Get product stock
 *
 * @param $product_id
 *
 * @return int
 * @since 1.2.0
 */
function ac_serial_numbers_get_stock_quantity( $product_id ) {
	return AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( [
		'product_id' => $product_id,
		'status'     => 'available'
	] )->count();
}

/**
 * @param $value
 * @param $product
 *
 * @return int
 * @since 1.2.0
 */
function ac_serial_numbers_find_stock_quantity( $value, $product ) {
	if ( ac_serial_numbers_product_serial_enabled( $product->get_id() ) ) {
		return ac_serial_numbers_get_stock_quantity( $product->get_id() );
	}

	return $value;
}

/**
 * Fetch license status counts from LicenceBot API.
 *
 * @param string|null $product_id Optional product UUID filter.
 * @param string|null $store_id Optional store UUID filter.
 * @param bool $force_refresh Force bypass cache.
 *
 * @return array|false Response data or false on failure.
 * @since 3.1.2
 */
function ac_serial_numbers_get_license_counts( $product_id = null, $store_id = null, $force_refresh = false ) {
	$url     = get_option( 'ac_serial_numbers_api_endpoint' );
	$api_key = get_option( 'ac_serial_numbers_api_key' );

	if ( empty( $url ) || empty( $api_key ) ) {
		return false;
	}

	$cache_key = 'acsn_license_counts_' . md5( $product_id . '_' . $store_id );

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$endpoint = rtrim( $url, '/' ) . '/product/stocks-status';
	$params   = [];
	if ( $product_id ) {
		$params['product_id'] = $product_id;
	}
	if ( $store_id ) {
		$params['store_id'] = $store_id;
	}
	if ( ! empty( $params ) ) {
		$endpoint .= '?' . http_build_query( $params );
	}

	$response = wp_remote_get( $endpoint, array(
		'headers' => ac_serial_numbers_get_api_headers(),
		'timeout' => 20,
	) );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code < 200 || $response_code >= 300 ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! isset( $body['success'] ) || ! $body['success'] ) {
		return false;
	}

	set_transient( $cache_key, $body, 5 * MINUTE_IN_SECONDS );

	return $body;
}

/**
 * Clear all license counts transients.
 *
 * @since 3.1.2
 */
function ac_serial_numbers_clear_license_counts_cache() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_acsn_license_counts_%'
	) );
}

/**
 * Sync remote licenses from LicenceBot into local DB.
 * Fetches all serial numbers for a given order and inserts missing ones.
 *
 * @param int $order_id WooCommerce order ID.
 *
 * @return int Number of serials synced.
 * @since 3.1.2
 */
function ac_serial_numbers_sync_order_serials( $order_id ) {
	global $wpdb;
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return 0;
	}

	$system_activation_guide = get_option( 'ac_serial_numbers_system_activation_guide' ) ?? false;
	$system_support_email    = get_option( 'ac_serial_numbers_support_email' ) ?? false;

	$items       = $order->get_items();
	$total_added = 0;

	foreach ( $items as $item ) {
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

		if ( ! ac_serial_numbers_product_serial_enabled( $product_id ) ) {
			continue;
		}

		$source = ac_serial_numbers_product_serial_source_type( $product_id ) ? 'reseller' : 'custom_source';
		if ( 'reseller' !== $source ) {
			continue;
		}

		$quantity                  = $item->get_quantity();
		$per_item_quantity         = absint( apply_filters( 'ac_serial_numbers_per_product_delivery_qty', 1, $product_id ) );
		$per_product_total_qty     = $quantity * $per_item_quantity;
		$already_connected         = AC_Serial_Numbers_Query::init()
			->table( 'serial_numbers' )
			->where( 'order_id', $order_id )
			->where( 'product_id', $product_id )
			->count();

		if ( $already_connected >= $per_product_total_qty ) {
			continue;
		}

		$remote_ids = [];

		$order_remote = get_post_meta( $order_id, '_ac_remote_product_' . $item->get_id(), true );
		if ( ! empty( $order_remote ) ) {
			$decoded = json_decode( $order_remote, true );
			if ( is_array( $decoded ) ) {
				$remote_ids = array_column( $decoded, 'id' );
			}
		}

		if ( empty( $remote_ids ) ) {
			$product_remote = get_post_meta( $product_id, '_ac_remote_product_id', true );
			if ( ! empty( $product_remote ) ) {
				$remote_ids = [ $product_remote ];
			}
		}

		if ( empty( $remote_ids ) ) {
			continue;
		}

		$url     = get_option( 'ac_serial_numbers_api_endpoint' );
		$api_key = get_option( 'ac_serial_numbers_api_key' );

		if ( empty( $url ) || empty( $api_key ) ) {
			continue;
		}

		foreach ( $remote_ids as $remote_product_id ) {
			$api_response = wp_remote_post( rtrim( $url, '/' ) . '/shop/new-order', [
				'headers' => ac_serial_numbers_get_api_headers(),
				'body'    => wp_json_encode( [
					'invoice_no' => $order_id,
					'customer'   => [
						'first_name' => $order->get_billing_first_name(),
						'last_name'  => $order->get_billing_last_name(),
						'email'      => $order->get_billing_email(),
						'phone'      => $order->get_billing_phone(),
						'address'    => $order->get_billing_address_1() . ', ' . $order->get_billing_address_2(),
						'city'       => $order->get_billing_city(),
						'state'      => $order->get_billing_state(),
						'zip'        => $order->get_billing_postcode(),
						'country'    => $order->get_billing_country(),
					],
					'product'  => [
						'op_id'    => $remote_product_id,
						'cp_id'    => $product_id,
						'title'    => $item->get_name(),
						'quantity' => $quantity,
					],
				] ),
				'timeout' => 30,
			] );

			if ( is_wp_error( $api_response ) ) {
				continue;
			}

			$data = json_decode( wp_remote_retrieve_body( $api_response ), true );
			$keys = isset( $data['data']['serialKeys'] ) && is_array( $data['data']['serialKeys'] )
				? $data['data']['serialKeys']
				: [];

			if ( empty( $keys ) ) {
				continue;
			}

			foreach ( $keys as $key ) {
				$help_text = ! empty( $system_activation_guide )
					? $system_activation_guide . ' | Support Email: ' . $system_support_email
					: ( isset( $key['activationGuide'] ) ? $key['activationGuide'] : '' ) . ' | Support Email: ' . $system_support_email;

				$existing = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}serial_numbers WHERE product_id=%d AND serial_key=%s AND order_id=%d",
					$product_id,
					ac_serial_numbers_encrypt_key( $key['serialNumber'] . ' | ' . $help_text ),
					$order_id
				) );

				if ( $existing ) {
					continue;
				}

				$inserted = $wpdb->insert(
					$wpdb->prefix . 'serial_numbers',
					[
						'serial_key'       => ac_serial_numbers_encrypt_key( $key['serialNumber'] . ' | ' . $help_text ),
						'product_id'       => $product_id,
						'activation_limit' => $key['activation_limit'] ?? 1,
						'activation_count' => 0,
						'order_id'         => $order_id,
						'vendor_id'        => $key['supplierId'] ?? '',
						'status'           => 'sold',
						'validity'         => null,
						'order_date'       => current_time( 'mysql' ),
						'source'           => 'reseller',
						'created_date'     => current_time( 'mysql' ),
					]
				);

				if ( $inserted ) {
					$total_added++;
				}
			}
		}
	}

	if ( $total_added > 0 ) {
		ac_serial_numbers_clear_license_counts_cache();
	}

	return $total_added;
}

add_filter( 'woocommerce_product_get_stock_quantity', 'ac_serial_numbers_find_stock_quantity', 10, 2 );

/**
 * Control software related columns
 *
 * @param $columns
 *
 * @return mixed
 * @since 1.2.0
 */
function ac_serial_numbers_control_order_table_columns( $columns ) {
	if ( ac_serial_numbers_software_support_disabled() ) {
		$software_columns = [ 'activation_email', 'activation_limit', 'expire_date' ];
		foreach ( $columns as $key => $label ) {
			if ( in_array( $key, $software_columns ) ) {
				unset( $columns[ $key ] );
			}
		}
	}

	return $columns;
}

add_filter( 'ac_serial_numbers_order_table_columns', 'ac_serial_numbers_control_order_table_columns', 99 );

/**
 * Sync a product mapping to LicenceBot.
 *
 * Sends the WooCommerce product → LicenceBot product mapping to the
 * LicenceBot API so that order delivery can resolve the correct license keys.
 *
 * @param int    $woo_product_id     WooCommerce product ID (remote_product_id).
 * @param string $lb_product_id      LicenceBot product UUID (license_product_id).
 * @param string $woo_product_name   WooCommerce product name (optional).
 * @param float  $woo_product_price  WooCommerce product price (optional).
 * @param string $action             'create' or 'delete'.
 *
 * @return bool|WP_Error True on success, WP_Error on failure, false if API not configured.
 * @since 3.2.0
 */
function ac_serial_numbers_sync_mapping_to_licencebot( $woo_product_id, $lb_product_id, $woo_product_name = '', $woo_product_price = 0, $action = 'create' ) {
	$url         = get_option( 'ac_serial_numbers_api_endpoint' );
	$api_key     = get_option( 'ac_serial_numbers_api_key' );
	$auth_secret = get_option( '_ac_serial_auth_secret' );

	if ( empty( $url ) || empty( $api_key ) ) {
		return false;
	}

	$logger = wc_get_logger();
	$context = array( 'source' => 'licencebot-mapping-sync' );

	if ( $action === 'delete' ) {
		$body = array(
			'remote_product_id'  => (int) $woo_product_id,
			'license_product_id' => $lb_product_id,
			'is_active'          => false,
		);
	} else {
		$body = array(
			'remote_product_id'  => (int) $woo_product_id,
			'license_product_id' => $lb_product_id,
		);
		if ( ! empty( $woo_product_name ) ) {
			$body['remote_product_name'] = $woo_product_name;
		}
		if ( $woo_product_price > 0 ) {
			$body['remote_product_price'] = (float) $woo_product_price;
		}
	}

	$headers = ac_serial_numbers_get_api_headers();

	$response = wp_remote_post( rtrim( $url, '/' ) . '/license-api/map', array(
		'headers' => $headers,
		'body'    => wp_json_encode( $body ),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		$logger->warning( 'Mapping sync failed (network): Woo product #' . $woo_product_id . ' → LB ' . $lb_product_id . ' — ' . $response->get_error_message(), $context );
		return $response;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$result        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $response_code < 200 || $response_code >= 300 ) {
		$error_msg = isset( $result['error'] ) ? $result['error'] : 'HTTP ' . $response_code;
		$logger->warning( 'Mapping sync HTTP error: Woo product #' . $woo_product_id . ' → LB ' . $lb_product_id . ' — ' . $error_msg, $context );
		return new WP_Error( 'mapping_sync_error', $error_msg );
	}

	if ( ! empty( $result['success'] ) ) {
		$logger->info( 'Mapping synced: Woo product #' . $woo_product_id . ' → LB ' . $lb_product_id . ' (' . $action . ')', $context );
		return true;
	}

	$logger->warning( 'Mapping sync unexpected response: Woo product #' . $woo_product_id . ' → LB ' . $lb_product_id . ' — ' . wp_json_encode( $result ), $context );
	return new WP_Error( 'mapping_sync_error', 'Unexpected API response' );
}
