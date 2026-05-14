<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_CHAT_WIDGET_ENABLED', '_ac_chat_widget_enabled' );
define( 'AC_SERIAL_CHAT_WIDGET_HTML', '_ac_chat_widget_html' );
define( 'AC_SERIAL_CHAT_WIDGET_CACHE_KEY', 'ac_chat_widget_html' );
define( 'AC_SERIAL_CHAT_WIDGET_CACHE_TTL', 5 * MINUTE_IN_SECONDS );
define( 'AC_SERIAL_CHAT_WIDGET_API_BASE', 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/helper-chat-widget' );

function ac_get_chat_widget_html() {
	if ( ! get_option( AC_SERIAL_CHAT_WIDGET_ENABLED, false ) ) {
		delete_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY );
		return '';
	}

	$cached = get_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY );
	if ( $cached !== false ) {
		return $cached;
	}

	$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
	$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
	if ( empty( $store_id ) || empty( $token ) ) {
		return '';
	}

	$url = add_query_arg(
		array(
			'store_id' => $store_id,
			't'        => $token,
		),
		AC_SERIAL_CHAT_WIDGET_API_BASE
	);

	$response = wp_remote_get( $url, array(
		'timeout'   => 15,
		'headers'   => array( 'Accept' => 'application/json' ),
		'sslverify' => true,
	) );

	if ( is_wp_error( $response ) ) {
		error_log( 'LicenceBot chat‑widget fetch error: ' . $response->get_error_message() );
		return '';
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code !== 200 ) {
		return '';
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( empty( $data['enabled'] ) || empty( $data['embed_html'] ) ) {
		return '';
	}

	set_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY, $data['embed_html'], AC_SERIAL_CHAT_WIDGET_CACHE_TTL );

	return $data['embed_html'];
}

function ac_render_chat_widget() {
	$html = ac_get_chat_widget_html();
	if ( $html ) {
		echo $html;
	}
}
add_action( 'wp_footer', 'ac_render_chat_widget' );

function ac_ajax_fetch_chat_widget() {
	check_ajax_referer( 'ac-serial-numbers-settings', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'msg' => __( 'Insufficient permissions.', 'ac-serial-numbers' ) ) );
	}

	$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
	$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
	if ( empty( $store_id ) || empty( $token ) ) {
		wp_send_json_error( array( 'msg' => __( 'Store not registered with LicenceBot.', 'ac-serial-numbers' ) ) );
	}

	$url = add_query_arg(
		array(
			'store_id' => $store_id,
			't'        => $token,
		),
		AC_SERIAL_CHAT_WIDGET_API_BASE
	);

	$response = wp_remote_get( $url, array(
		'timeout'   => 15,
		'headers'   => array( 'Accept' => 'application/json' ),
		'sslverify' => true,
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'msg' => $response->get_error_message() ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( $code !== 200 || empty( $data['enabled'] ) || empty( $data['embed_html'] ) ) {
		wp_send_json_error( array( 'msg' => __( 'Widget disabled or not configured on LicenceBot.', 'ac-serial-numbers' ) ) );
	}

	set_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY, $data['embed_html'], AC_SERIAL_CHAT_WIDGET_CACHE_TTL );
	update_option( AC_SERIAL_CHAT_WIDGET_ENABLED, true );

	$endpoint_url = add_query_arg(
		array(
			'store_id' => $store_id,
			't'        => $token,
		),
		AC_SERIAL_CHAT_WIDGET_API_BASE
	);

	wp_send_json_success( array(
		'embed_html'   => $data['embed_html'],
		'widget_id'    => isset( $data['widget_id'] ) ? $data['widget_id'] : '',
		'endpoint_url' => $endpoint_url,
	) );
}

add_action( 'wp_ajax_ac_fetch_chat_widget', 'ac_ajax_fetch_chat_widget' );

function ac_chat_widget_admin_scripts() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	if ( $screen->id !== 'ac-serial-numbers_page_ac-serial-numbers-settings' ) {
		return;
	}

	if ( ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'helper-plugin' ) {
		return;
	}

	wp_enqueue_script(
		'ac-chat-widget-admin',
		ac_serial_numbers()->plugin_url() . '/assets/js/ac-chat-widget-admin.js',
		array( 'jquery' ),
		AC_SERIAL_NUMBER_PLUGIN_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'ac_chat_widget_admin_scripts' );
