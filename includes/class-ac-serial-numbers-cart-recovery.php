<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Cart_Recovery {

	private static $endpoint = 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/woo-cart-webhook';

	public static function init() {
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'track_cart' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'mark_cart_recovered' ) );
	}

	public static function track_cart() {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		if ( empty( $store_id ) ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$email = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$email = $user->user_email;
		}

		$items = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$items[] = array(
				'product_id' => $cart_item['product_id'],
				'name'       => $product->get_name(),
				'quantity'   => $cart_item['quantity'],
				'price'      => $product->get_price(),
			);
		}

		$payload = array(
			'store_id'   => $store_id,
			'email'      => $email,
			'cart_items' => $items,
			'cart_total' => $cart->get_total( 'edit' ),
			'cart_url'   => wc_get_cart_url(),
			'event_type' => 'updated',
		);

		wp_remote_post( self::$endpoint, array(
			'method'      => 'POST',
			'timeout'     => 1,
			'blocking'    => false,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
		));
	}

	public static function mark_cart_recovered( $order_id ) {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		if ( empty( $store_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$payload = array(
			'store_id'   => $store_id,
			'email'      => $order->get_billing_email(),
			'cart_items' => array(),
			'cart_total' => 0,
			'cart_url'   => '',
			'event_type' => 'recovered',
		);

		wp_remote_post( self::$endpoint, array(
			'method'      => 'POST',
			'timeout'     => 1,
			'blocking'    => false,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload ),
		));
	}
}

AC_Serial_Numbers_Cart_Recovery::init();
