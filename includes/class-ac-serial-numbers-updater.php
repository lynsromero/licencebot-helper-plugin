<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Updater {

	private static $api_url = 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/plugin-update-check';

	public static function init() {
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'add_check_link' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_check' ) );
	}

	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$current_version = AC_SERIAL_NUMBER_PLUGIN_VERSION;
		$remote = self::fetch_remote_info();

		if ( ! $remote ) {
			return $transient;
		}

		if ( version_compare( $remote->version, $current_version, '>' ) ) {
			$org_token = self::get_org_token();
			$download_url = add_query_arg(
				array(
					'org_token'   => $org_token,
					'plugin_slug' => 'ac-serial-numbers',
					'version'     => $remote->version,
				),
				'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/plugin-update-download'
			);

			$transient->response['ac-serial-numbers/ac-serial-numbers.php'] = (object) array(
				'new_version'  => $remote->version,
				'url'          => 'https://licencebot.com',
				'package'      => $download_url,
				'requires'     => isset( $remote->requires_wp ) ? $remote->requires_wp : '',
				'tested'       => isset( $remote->tested_up_to ) ? $remote->tested_up_to : '',
				'requires_php' => '7.4',
				'upgrade_notice' => isset( $remote->changelog ) ? wp_strip_all_tags( $remote->changelog ) : '',
			);
		}

		return $transient;
	}

	private static function fetch_remote_info() {
		$cached = get_site_transient( 'ac_remote_update_info' );
		if ( $cached !== false ) {
			return $cached;
		}

		$org_token = self::get_org_token();
		$url = add_query_arg( array( 'plugin_slug' => 'ac-serial-numbers' ), self::$api_url );
		if ( $org_token ) {
			$url = add_query_arg( array( 'org_token' => $org_token ), $url );
		}

		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'sslverify' => true,
		));

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['version'] ) ) {
			return null;
		}

		$info = (object) $data;
		set_site_transient( 'ac_remote_update_info', $info, 12 * HOUR_IN_SECONDS );
		return $info;
	}

	public static function force_check() {
		delete_site_transient( 'ac_remote_update_info' );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		self::fetch_remote_info();
	}

	public static function add_check_link( $meta, $file ) {
		if ( $file !== 'ac-serial-numbers/ac-serial-numbers.php' ) {
			return $meta;
		}

		$url = wp_nonce_url(
			add_query_arg( array( 'ac_check_update' => 1 ), admin_url( 'plugins.php' ) ),
			'ac_check_update'
		);

		$meta[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Check for updates', 'ac-serial-numbers' ) );
		return $meta;
	}

	public static function handle_manual_check() {
		if ( ! isset( $_GET['ac_check_update'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'ac_check_update' ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		self::force_check();

		$remote = self::fetch_remote_info();
		$current = AC_SERIAL_NUMBER_PLUGIN_VERSION;
		$has_update = $remote && version_compare( $remote->version, $current, '>' );

		$status = $has_update ? 'update_available' : 'no_update';

		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = admin_url( 'plugins.php' );
		}

		wp_redirect( add_query_arg( 'ac_update_check_result', $status, $referer ) );
		exit;
	}

	public static function display_manual_check_notice() {
		if ( ! isset( $_GET['ac_update_check_result'] ) ) {
			return;
		}

		$status = sanitize_key( $_GET['ac_update_check_result'] );

		if ( $status === 'update_available' ) {
			$message = __( 'A new version of LicenceBot Helper Plugin is available. Click "Update now" to install.', 'ac-serial-numbers' );
			$class = 'updated notice-success';
		} elseif ( $status === 'no_update' ) {
			$message = __( 'LicenceBot Helper Plugin is up to date.', 'ac-serial-numbers' );
			$class = 'updated notice-success';
		} else {
			return;
		}

		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	private static function get_org_token() {
		$token = defined( 'AC_SERIAL_ORG_TOKEN' ) ? AC_SERIAL_ORG_TOKEN : '';
		if ( strpos( $token, '%%' ) !== false || empty( $token ) || $token === 'null' ) {
			$token = get_option( AC_SERIAL_OPT_ORG_TOKEN_DYNAMIC, '' );
		}
		return $token;
	}
}

AC_Serial_Numbers_Updater::init();
add_action( 'admin_notices', array( 'AC_Serial_Numbers_Updater', 'display_manual_check_notice' ), 20 );
