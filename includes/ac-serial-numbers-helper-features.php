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
			$status_text  = 'Disabled locally — enable the toggle and click Save Changes.';
		} elseif ( empty( $stored_code ) ) {
			$status_class = 'info';
			$status_text  = 'Not fetched yet — enable and click Save Changes to fetch from LicenceBot.';
		} else {
			$status_class = 'success';
			$status_text  = 'Connected';
			if ( $fetched_at ) {
				$status_text .= ' (updated ' . human_time_diff( $fetched_at, current_time( 'timestamp' ) ) . ' ago)';
			}
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
			</table>

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

add_action( 'wp_footer', function() {
	AC_Serial_Numbers_Helper_Features::render_frontend();
}, 100 );
