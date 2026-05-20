<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_HELPER_FEATURES_API_BASE', 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1' );

if ( ! class_exists( 'AC_Serial_Numbers_Helper_Features' ) ) :

class AC_Serial_Numbers_Helper_Features {

	private static $features = array();

	public static function register( $slug, $config ) {
		self::$features[ $slug ] = wp_parse_args( $config, array(
			'title'            => '',
			'description'      => '',
			'enabled_option'   => '',
			'code_option'      => '',
			'widget_id_option' => '',
			'fetched_at_option'=> '',
			'api_endpoint'     => '',
		));
	}

	public static function get_all() {
		return self::$features;
	}

	public static function get( $slug ) {
		return isset( self::$features[ $slug ] ) ? self::$features[ $slug ] : null;
	}

	public static function fetch_code( $slug ) {
		$feature = self::get( $slug );
		if ( ! $feature ) {
			return new WP_Error( 'feature_not_found', 'Feature not found.' );
		}

		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		if ( empty( $store_id ) || empty( $token ) ) {
			return new WP_Error( 'not_registered', 'Store not registered with LicenceBot.' );
		}

		$url = add_query_arg(
			array( 'store_id' => $store_id, 't' => $token ),
			AC_SERIAL_HELPER_FEATURES_API_BASE . '/' . $feature['api_endpoint']
		);

		$response = wp_remote_get( $url, array(
			'timeout'   => 15,
			'headers'   => array( 'Accept' => 'application/json' ),
			'sslverify' => true,
		));

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'http_error', sprintf( 'HTTP %d', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$enabled   = ! empty( $data['enabled'] );
		$embed_html = isset( $data['embed_html'] ) ? $data['embed_html'] : '';
		$widget_id  = isset( $data['widget_id'] ) ? $data['widget_id'] : '';

		if ( ! $enabled || empty( $embed_html ) ) {
			$reason = isset( $data['reason'] ) ? $data['reason'] : 'Not configured on LicenceBot Dashboard.';
			return new WP_Error( 'not_configured', $reason );
		}

		update_option( $feature['code_option'], $embed_html );
		if ( ! empty( $widget_id ) ) {
			update_option( $feature['widget_id_option'], $widget_id );
		}
		update_option( $feature['fetched_at_option'], current_time( 'timestamp' ) );

		delete_transient( 'lb_' . $slug . '_html' );

		return array(
			'success'    => true,
			'embed_html' => $embed_html,
			'widget_id'  => $widget_id,
			'endpoint'   => $url,
		);
	}

	public static function render_frontend() {
		if ( is_admin() ) {
			return;
		}

		foreach ( self::$features as $slug => $feature ) {
			$enabled = get_option( $feature['enabled_option'], 'no' );
			if ( $enabled !== 'yes' ) {
				continue;
			}

			$transient_key = 'lb_' . $slug . '_html';
			$html = get_transient( $transient_key );

			if ( $html === false ) {
				$html = get_option( $feature['code_option'], '' );
				if ( $html ) {
					set_transient( $transient_key, $html, 5 * MINUTE_IN_SECONDS );
				}
			}

			if ( $html ) {
				echo "\n" . $html . "\n";
			}
		}
	}

	public static function render_card( $slug ) {
		$feature = self::get( $slug );
		if ( ! $feature ) {
			return;
		}

		$store_id      = get_option( AC_SERIAL_OPT_STORE_ID );
		$token         = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		$is_registered = ! empty( $store_id ) && ! empty( $token );
		$enabled       = get_option( $feature['enabled_option'], 'no' ) === 'yes';
		$stored_code   = get_option( $feature['code_option'], '' );
		$fetched_at    = get_option( $feature['fetched_at_option'], 0 );

		$status_class = '';
		$status_text  = '';

		if ( ! $is_registered ) {
			$status_class = 'error';
			$status_text  = 'Not connected — connect to LicenceBot in the General tab first.';
		} elseif ( ! $enabled ) {
			$status_class = 'warning';
			$status_text  = 'Disabled locally — enable the toggle to activate.';
		} elseif ( empty( $stored_code ) ) {
			$status_class = 'info';
			$status_text  = 'Not fetched yet — click "Fetch Code" to retrieve from LicenceBot.';
		} else {
			$status_class = 'success';
			$status_text  = 'Connected';
			if ( $fetched_at ) {
				$status_text .= ' (updated ' . human_time_diff( $fetched_at, current_time( 'timestamp' ) ) . ' ago)';
			}
		}

		$endpoint_url = '';
		if ( $is_registered ) {
			$endpoint_url = add_query_arg(
				array( 'store_id' => $store_id, 't' => $token ),
				AC_SERIAL_HELPER_FEATURES_API_BASE . '/' . $feature['api_endpoint']
			);
		}
		?>
		<style>
			.ac-feature-status { display:inline-block; padding:4px 10px; border-radius:4px; font-size:13px; font-weight:500; }
			.ac-status-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
			.ac-status-warning  { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
			.ac-status-error    { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
			.ac-status-info     { background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb; }
			.ac-helper-feature-card { margin-bottom:24px; padding-bottom:24px; border-bottom:1px solid #eee; }
			.ac-helper-feature-card:last-child { border-bottom:none; }
		</style>
		<div class="ac-helper-feature-card" id="ac-feature-card-<?php echo esc_attr( $slug ); ?>">
			<h3><?php echo esc_html( $feature['title'] ); ?></h3>
			<p style="color:#666;"><?php echo esc_html( $feature['description'] ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( $feature['enabled_option'] ); ?>">
							Enable <?php echo esc_html( $feature['title'] ); ?>
						</label>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $feature['enabled_option'] ); ?>">
								<input
									type="checkbox"
									id="<?php echo esc_attr( $feature['enabled_option'] ); ?>"
									name="<?php echo esc_attr( $feature['enabled_option'] ); ?>"
									value="1"
									<?php checked( $enabled ); ?>
								/>
								<?php _e( 'Display on your store.', 'ac-serial-numbers' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Status</th>
					<td>
						<span class="ac-feature-status ac-status-<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th scope="row">API Endpoint</th>
					<td>
						<?php if ( $endpoint_url ) : ?>
							<code style="word-break:break-all;"><?php echo esc_url( $endpoint_url ); ?></code>
						<?php else : ?>
							<em>Connect to LicenceBot first.</em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label>Paste before closing <code>&lt;/body&gt;</code> tag</label>
					</th>
					<td>
						<textarea
							id="ac-<?php echo esc_attr( $slug ); ?>-code-display"
							class="large-text code"
							rows="4"
							readonly
							style="font-family:monospace;font-size:12px;"
						><?php echo esc_textarea( $stored_code ); ?></textarea>
						<p class="description">
							This code is fetched from LicenceBot and auto-updates daily.
						</p>
					</td>
				</tr>
			</table>

			<div id="ac-<?php echo esc_attr( $slug ); ?>-fetch-status" style="margin:10px 0;"></div>

			<button
				type="button"
				class="button button-primary ac-fetch-feature"
				data-feature="<?php echo esc_attr( $slug ); ?>"
				data-nonce="<?php echo wp_create_nonce( 'ac-serial-numbers-settings' ); ?>"
				<?php disabled( ! $is_registered ); ?>
			>
				<?php _e( 'Fetch Code', 'ac-serial-numbers' ); ?>
			</button>

			<?php if ( ! $is_registered ) : ?>
				<p style="color:#d63638;margin-top:10px;">
					<?php _e( 'Connect to LicenceBot first (General tab) to enable this feature.', 'ac-serial-numbers' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

endif;

add_action( 'wp_ajax_ac_fetch_helper_feature', function() {
	check_ajax_referer( 'ac-serial-numbers-settings', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'msg' => 'Insufficient permissions.' ) );
	}

	$slug = sanitize_text_field( $_POST['feature'] );
	$result = AC_Serial_Numbers_Helper_Features::fetch_code( $slug );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'msg' => $result->get_error_message() ) );
	}

	wp_send_json_success( array(
		'embed_html' => $result['embed_html'],
		'widget_id'  => $result['widget_id'],
		'endpoint'   => $result['endpoint'],
	));
});

add_action( 'wp_footer', function() {
	AC_Serial_Numbers_Helper_Features::render_frontend();
}, 100 );

add_action( 'admin_enqueue_scripts', function() {
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
		'ac-helper-features-admin',
		ac_serial_numbers()->plugin_url() . '/assets/js/ac-helper-features-admin.js',
		array( 'jquery' ),
		AC_SERIAL_NUMBER_PLUGIN_VERSION,
		true
	);

	wp_localize_script( 'ac-helper-features-admin', 'acHelperFeatures', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ac-serial-numbers-settings' ),
	));
});
