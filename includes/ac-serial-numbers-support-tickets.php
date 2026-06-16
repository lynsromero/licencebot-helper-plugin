<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_SUPPORT_TICKETS_ENABLED',     'licencebot_support_tickets_enabled' );
define( 'AC_SERIAL_SUPPORT_TICKETS_CODE',        'licencebot_support_tickets_html' );
define( 'AC_SERIAL_SUPPORT_TICKETS_WIDGET_ID',   'licencebot_support_tickets_id' );
define( 'AC_SERIAL_SUPPORT_TICKETS_FETCHED_AT',  'licencebot_support_tickets_fetched_at' );
define( 'AC_SERIAL_SUPPORT_TICKETS_API',         'helper-support-tickets' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'support_tickets', array(
		'title'            => __( 'Support Tickets', 'ac-serial-numbers' ),
		'description'      => __( 'Customer support form with live chat.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_SUPPORT_TICKETS_ENABLED,
		'code_option'      => AC_SERIAL_SUPPORT_TICKETS_CODE,
		'widget_id_option' => AC_SERIAL_SUPPORT_TICKETS_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_SUPPORT_TICKETS_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_SUPPORT_TICKETS_API,
	) );
}, 5 );

add_shortcode( 'licencebot_support_tickets', function( $atts ) {
	$feature = AC_Serial_Numbers_Helper_Features::get( 'support_tickets' );
	if ( ! $feature ) {
		return '';
	}

	if ( ! is_user_logged_in() ) {
		return '<p>' . sprintf(
			__( 'Please <a href="%s">log in</a> to access your support tickets.', 'ac-serial-numbers' ),
			esc_url( wp_login_url( get_permalink() ) )
		) . '</p>';
	}

	$enabled = get_option( $feature['enabled_option'], 'no' );
	if ( $enabled !== 'yes' ) {
		return '';
	}

	$transient_key = 'lb_support_tickets_html';
	$html = get_transient( $transient_key );
	if ( $html === false ) {
		$html = get_option( $feature['code_option'], '' );
		if ( $html ) {
			set_transient( $transient_key, $html, 5 * MINUTE_IN_SECONDS );
		}
	}

	if ( ! $html ) {
		return '';
	}

	$current_store_id = get_option( AC_SERIAL_OPT_STORE_ID );
	$current_user = wp_get_current_user();

	if ( $current_store_id ) {
		$html = preg_replace(
			"/(['\"]data-store-id['\"]\s*,\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_store_id ) . '${2}',
			$html
		);
		$html = preg_replace(
			"/(data-store-id\s*=\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_store_id ) . '${2}',
			$html
		);
	}

	if ( $current_user && $current_user->exists() ) {
		$html = preg_replace(
			"/(['\"]data-user-email['\"]\s*,\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_user->user_email ) . '${2}',
			$html
		);
		$html = preg_replace(
			"/(data-user-email\s*=\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_user->user_email ) . '${2}',
			$html
		);
		$html = preg_replace(
			"/(['\"]data-user-name['\"]\s*,\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_user->display_name ) . '${2}',
			$html
		);
		$html = preg_replace(
			"/(data-user-name\s*=\s*['\"])[^'\"]*(['\"])/",
			'${1}' . esc_js( $current_user->display_name ) . '${2}',
			$html
		);
	}

	return $html;
} );
