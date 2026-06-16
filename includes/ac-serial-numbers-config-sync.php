<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'AC_Serial_Numbers_Config_Sync' ) ) :

class AC_Serial_Numbers_Config_Sync {

	const API_BASE     = 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1';
	const CONFIG_TRANSIENT = 'lb_config_cache';

	public static function fetch_config( $force = false ) {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		if ( empty( $store_id ) || empty( $token ) ) {
			return null;
		}

		if ( ! $force ) {
			$cached = get_transient( self::CONFIG_TRANSIENT );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$url = self::API_BASE . '/licencebot-helper?action=config'
			. '&store_id=' . rawurlencode( $store_id )
			. '&t=' . rawurlencode( $token )
			. '&_=' . time();

		$response = wp_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array( 'Cache-Control' => 'no-store' ),
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['features'] ) ) {
			return null;
		}

		self::update_local_options( $data );

		set_transient( self::CONFIG_TRANSIENT, $data, 30 );

		return $data;
	}

	public static function push_config( array $patch ) {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		if ( empty( $store_id ) || empty( $token ) ) {
			return new WP_Error( 'not_registered', 'Store not registered with LicenceBot.' );
		}

		$response = wp_remote_post( self::API_BASE . '/helper-update-config', array(
			'timeout' => 10,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'store_id'    => $store_id,
				'store_token' => $token,
				'patch'       => $patch,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 300 ) {
			$error_msg = isset( $body['error'] ) ? $body['error'] : 'Update failed (HTTP ' . $code . ')';
			return new WP_Error( 'api_error', $error_msg );
		}

		return $body;
	}

	private static function update_local_options( $data ) {
		$config = isset( $data['config'] ) ? $data['config'] : array();

		$features = AC_Serial_Numbers_Helper_Features::get_all();
		foreach ( $features as $slug => $feature ) {
			$config_key = self::slug_to_config_key( $slug );
			$enabled = ! empty( $config[ $config_key ] );
			update_option( $feature['enabled_option'], $enabled ? 'yes' : 'no' );
		}

		if ( isset( $config['contact_creates_tickets'] ) ) {
			update_option( 'licencebot_contact_creates_tickets', $config['contact_creates_tickets'] ? 'yes' : 'no' );
		}

		if ( isset( $config['chat_widget_id'] ) ) {
			$chat = AC_Serial_Numbers_Helper_Features::get( 'chat_widget' );
			if ( $chat && ! empty( $chat['widget_id_option'] ) ) {
				update_option( $chat['widget_id_option'], $config['chat_widget_id'] );
			}
		}
	}

	public static function slug_to_config_key( $slug ) {
		if ( $slug === 'contact_form' ) {
			return 'contact_page_form_enabled';
		}
		return $slug . '_enabled';
	}

	public static function config_key_to_slug( $config_key ) {
		if ( $config_key === 'contact_page_form_enabled' ) {
			return 'contact_form';
		}
		if ( substr( $config_key, -8 ) === '_enabled' ) {
			return substr( $config_key, 0, -8 );
		}
		return null;
	}
}

endif;
