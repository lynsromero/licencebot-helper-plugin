<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Cart_Recovery {

	private static $endpoint = 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/woo-cart-webhook';

	public static function init() {
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'track_cart' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'track_cart' ) );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'track_cart' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'capture_checkout_email' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'mark_cart_recovered' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'mark_cart_recovered' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'mark_cart_recovered' ) );
		add_action( 'woocommerce_after_checkout_form', array( __CLASS__, 'add_checkout_tracking_script' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_email_notice' ) );

		register_activation_hook( dirname( __FILE__ ) . '/../ac-serial-numbers.php', array( __CLASS__, 'schedule_cron' ) );
		register_deactivation_hook( dirname( __FILE__ ) . '/../ac-serial-numbers.php', array( __CLASS__, 'clear_cron' ) );

		add_action( 'ac_cart_recovery_check_abandoned', array( __CLASS__, 'check_abandoned_carts' ) );
		if ( ! wp_next_scheduled( 'ac_cart_recovery_check_abandoned' ) ) {
			wp_schedule_event( time(), 'hourly', 'ac_cart_recovery_check_abandoned' );
		}
	}

	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'ac_cart_recovery_check_abandoned' ) ) {
			wp_schedule_event( time(), 'hourly', 'ac_cart_recovery_check_abandoned' );
		}
	}

	public static function clear_cron() {
		$timestamp = wp_next_scheduled( 'ac_cart_recovery_check_abandoned' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ac_cart_recovery_check_abandoned' );
		}
	}

	private static function is_enabled() {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		if ( empty( $store_id ) ) {
			return false;
		}
		return get_option( AC_SERIAL_CART_RECOVERY_ENABLED, 'no' ) === 'yes';
	}

	private static function get_cart_items() {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return array();
		}

		$items = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$image_id = $product->get_image_id();
			$items[] = array(
				'product_id'   => $cart_item['product_id'],
				'variation_id' => $cart_item['variation_id'] ?? 0,
				'product_name' => $product->get_name(),
				'quantity'     => $cart_item['quantity'],
				'price'        => (string) wc_get_price_to_display( $product ),
				'line_total'   => (string) $cart_item['line_total'],
				'image_url'    => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
				'product_url'  => get_permalink( $cart_item['product_id'] ),
			);
		}
		return $items;
	}

	private static function get_customer_email() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return $user->user_email;
		}

		if ( ! empty( $_SESSION['ac_cart_recovery_email'] ) ) {
			$email = sanitize_email( $_SESSION['ac_cart_recovery_email'] );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				return $email;
			}
		}

		if ( WC()->customer && WC()->customer->get_email() ) {
			return WC()->customer->get_email();
		}

		return '';
	}

	private static function send_to_webhook( $payload ) {
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

	public static function track_cart() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$email = self::get_customer_email();
		if ( empty( $email ) ) {
			return;
		}

		$cart_items = self::get_cart_items();
		if ( empty( $cart_items ) ) {
			return;
		}

		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		$customer = WC()->customer;
		$first_name = $customer ? $customer->get_first_name() : '';
		$last_name  = $customer ? $customer->get_last_name() : '';
		$phone      = $customer ? $customer->get_billing_phone() : '';

		self::send_to_webhook( array(
			'store_id'   => $store_id,
			'store_url'  => home_url(),
			'email'      => $email,
			'first_name' => $first_name ?: '',
			'last_name'  => $last_name ?: '',
			'phone'      => $phone ?: '',
			'cart_items' => $cart_items,
			'cart_total' => (float) $cart->get_total( 'edit' ),
			'cart_url'   => wc_get_cart_url(),
			'currency'   => get_woocommerce_currency(),
			'event_type' => 'updated',
		));
	}

	public static function capture_checkout_email( $posted_data ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$data = array();
		if ( is_string( $posted_data ) ) {
			parse_str( $posted_data, $data );
		} elseif ( is_array( $posted_data ) ) {
			$data = $posted_data;
		} else {
			$data = $_POST;
		}

		if ( ! empty( $data['billing_email'] ) ) {
			$email = sanitize_email( $data['billing_email'] );
			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				if ( ! session_id() ) {
					session_start();
				}
				$_SESSION['ac_cart_recovery_email'] = $email;
				self::track_cart();
			}
		}
	}

	public static function mark_cart_recovered( $order_id ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( get_post_meta( $order_id, '_ac_cart_recovery_sent', true ) ) {
			return;
		}
		update_post_meta( $order_id, '_ac_cart_recovery_sent', '1' );

		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );

		self::send_to_webhook( array(
			'store_id'   => $store_id,
			'store_url'  => home_url(),
			'email'      => $order->get_billing_email(),
			'cart_items' => array(),
			'cart_total' => (float) $order->get_total(),
			'event_type' => 'recovered',
		));
	}

	public static function check_abandoned_carts() {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_sessions';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return;
		}

		$thirty_min_ago = time() - ( 30 * 60 );
		$twenty_four_h  = time() - ( 24 * 60 * 60 );

		$sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT session_key, session_value, session_expiry FROM $table WHERE session_expiry > %d AND session_expiry < %d",
			$twenty_four_h, $thirty_min_ago
		));

		foreach ( $sessions as $session ) {
			$data = maybe_unserialize( $session->session_value );

			if ( empty( $data['cart'] ) || empty( $data['customer']['email'] ) ) {
				continue;
			}

			$cart_contents = maybe_unserialize( $data['cart'] );
			if ( empty( $cart_contents ) ) {
				continue;
			}

			$cart_items = array();
			$cart_total = 0;

			foreach ( $cart_contents as $item ) {
				$product = wc_get_product( $item['product_id'] );
				if ( ! $product ) {
					continue;
				}

				$line_total = $product->get_price() * $item['quantity'];
				$cart_total += $line_total;

				$cart_items[] = array(
					'product_id'   => $item['product_id'],
					'product_name' => $product->get_name(),
					'quantity'     => $item['quantity'],
					'price'        => (string) $product->get_price(),
					'line_total'   => (string) $line_total,
					'image_url'    => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '',
				);
			}

			if ( empty( $cart_items ) ) {
				continue;
			}

			$store_id = get_option( AC_SERIAL_OPT_STORE_ID );

			self::send_to_webhook( array(
				'store_id'   => $store_id,
				'store_url'  => home_url(),
				'email'      => sanitize_email( $data['customer']['email'] ),
				'first_name' => $data['customer']['first_name'] ?? '',
				'last_name'  => $data['customer']['last_name'] ?? '',
				'cart_items' => $cart_items,
				'cart_total' => $cart_total,
				'cart_url'   => wc_get_cart_url(),
				'currency'   => get_woocommerce_currency(),
				'event_type' => 'abandoned',
			));
		}
	}

	public static function render_email_notice() {
		if ( ! self::is_enabled() ) {
			return;
		}
		?>
		<div class="woocommerce-info" style="margin-top:10px;">
			<span><?php esc_html_e( 'Your email will be stored to help recover your cart.', 'ac-serial-numbers' ); ?></span>
		</div>
		<?php
	}

	public static function add_checkout_tracking_script() {
		if ( ! self::is_enabled() ) {
			return;
		}
		?>
		<script>
		(function() {
			var emailInput = document.querySelector('#billing_email') || document.querySelector('input[name="billing_email"]');
			if (!emailInput) return;

			var debounceTimer;
			emailInput.addEventListener('input', function() {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function() {
					if (emailInput.value && emailInput.value.includes('@')) {
						jQuery('body').trigger('update_checkout');
					}
				}, 500);
			});
		})();
		</script>
		<?php
	}
}

AC_Serial_Numbers_Cart_Recovery::init();
