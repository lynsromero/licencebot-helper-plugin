<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_CRON {

	/**
	 * AC_Serial_Numbers_CRON constructor.
	 */
	public static function init() {
		add_action( 'ac_serial_numbers_hourly_event', array( __CLASS__, 'expire_outdated_serials' ) );
		add_action( 'ac_serial_numbers_daily_event', array( __CLASS__, 'send_stock_alert_email' ) );
		add_action( 'ac_serial_numbers_daily_event', array( __CLASS__, 'refresh_helper_features' ) );
	}

	/**
	 * Disable all expired serial numbers
	 *
	 * since 1.0.0
	 */
	public static function expire_outdated_serials() {
		global $wpdb;
		$wpdb->query( "update {$wpdb->prefix}serial_numbers set status='expired' where expire_date != '0000-00-00 00:00:00' AND expire_date < NOW()" );
		$wpdb->query( "update {$wpdb->prefix}serial_numbers set status='expired' where validity !='0' AND (order_date + INTERVAL validity DAY ) < NOW()" );
	}

	/**
	 * Send low stock email notification.
	 *
	 * @return bool
	 * @since 1.2.0
	 */
	public static function send_stock_alert_email() {
		if ( ! ac_serial_numbers_validate_boolean( get_option( 'ac_serial_numbers_enable_stock_notification' ) ) ) {
			return false;
		}

		$stock_threshold = get_option( 'ac_serial_numbers_stock_threshold', 5 );
		$to              = get_option( 'ac_serial_numbers_notification_recipient', get_option( 'admin_email' ) );
		if ( empty( $to ) ) {
			return false;
		}

		$low_stock_products = ac_serial_numbers_get_low_stock_products( true, $stock_threshold );
		if ( empty( $low_stock_products ) ) {
			return false;
		}

		$subject = __( 'Serial Numbers stock running low', 'ac-serial-numbers' );
		/** $woocommerce WooCommerce */
		global $woocommerce;
		$mailer = $woocommerce->mailer();

		ob_start();
		include dirname( __FILE__ ) . '/admin/views/email-notification-body.php';
		$message = ob_get_contents();
		ob_get_clean();

		$message = $mailer->wrap_message( $subject, $message );
		$headers = apply_filters( 'woocommerce_email_headers', '', 'rewards_message', 'null' );
		$mailer->send( $to, $subject, $message, $headers, array() );

		exit();
	}

	public static function refresh_helper_features() {
		if ( ! class_exists( 'AC_Serial_Numbers_Helper_Features' ) ) {
			return;
		}

		$features = AC_Serial_Numbers_Helper_Features::get_all();
		foreach ( $features as $slug => $config ) {
			$enabled = get_option( $config['enabled_option'], 'no' );
			if ( $enabled !== 'yes' ) {
				continue;
			}

			$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
			$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
			if ( empty( $store_id ) || empty( $token ) ) {
				continue;
			}

			$result = AC_Serial_Numbers_Helper_Features::fetch_code( $slug );
			if ( is_wp_error( $result ) ) {
				error_log( 'LicenceBot helper feature refresh failed [' . $slug . ']: ' . $result->get_error_message() );
			}
		}
	}
}

AC_Serial_Numbers_CRON::init();
