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

			if ( $slug === 'contact_form' ) {
				$page_slug = get_option( 'licencebot_contact_page_slug', 'contact' );
				$page_slug = basename( untrailingslashit( $page_slug ) );
				$contact_page = get_page_by_path( $page_slug );
				if ( ! $contact_page || ! is_page( $contact_page->ID ) ) {
					continue;
				}
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
				$current_store_id = get_option( AC_SERIAL_OPT_STORE_ID );
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
		$requires_fetch = ! empty( $feature['code_option'] );

		$status_class = '';
		$status_text  = '';

		if ( ! $is_registered ) {
			$status_class = 'error';
			$status_text  = 'Not connected — connect to LicenceBot in the General tab first.';
		} elseif ( ! $enabled ) {
			$status_class = 'warning';
			$status_text  = 'Disabled locally — enable the toggle and click Save Changes.';
		} elseif ( $requires_fetch && empty( $stored_code ) ) {
			$status_class = 'info';
			$status_text  = 'Not fetched yet — enable and click Save Changes to fetch from LicenceBot.';
		} else {
			$status_class = 'success';
			$status_text  = 'Connected';
			if ( $requires_fetch && $fetched_at ) {
				$status_text .= ' (updated ' . human_time_diff( $fetched_at, current_time( 'timestamp' ) ) . ' ago)';
			}
		}

		$helper_text = '';
		if ( $slug === 'cart_recovery' ) {
			$helper_text = '<p style="color:#666;font-size:13px;margin-top:8px;">' . __( 'Tracks abandoned carts via a non-blocking background request — no impact on page load.', 'ac-serial-numbers' ) . '</p>';
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
			.ac-feature-subsection { margin-bottom:30px; }
			.ac-feature-subsection h4 { margin:0 0 15px; font-size:14px; color:#23282d; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #ddd; padding-bottom:8px; }
		</style>
		<div class="ac-helper-feature-card" id="ac-feature-card-<?php echo esc_attr( $slug ); ?>">
			<h3><?php echo esc_html( $feature['title'] ); ?></h3>
			<p style="color:#666;"><?php echo esc_html( $feature['description'] ); ?></p>
			<?php echo $helper_text; ?>

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
				<?php if ( $slug === 'contact_form' ) : ?>
				<tr>
					<th scope="row">
						<label for="licencebot_contact_page_slug">
							<?php _e( 'Contact Page URL', 'ac-serial-numbers' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="licencebot_contact_page_slug"
							name="licencebot_contact_page_slug"
							value="<?php echo esc_attr( get_option( 'licencebot_contact_page_slug', 'contact' ) ); ?>"
							placeholder="<?php esc_attr_e( 'contact-us or https://yoursite.com/contact-us/', 'ac-serial-numbers' ); ?>"
							style="width:100%;max-width:400px;"
						/>
						<p class="description" style="margin-top:4px;">
							<?php _e( 'Enter the full URL (e.g. https://yoursite.com/contact-us/) or just the slug (e.g. contact-us).', 'ac-serial-numbers' ); ?>
						</p>
					</td>
				</tr>
				<?php endif; ?>
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

	public static function render_grouped_cards() {
		$features = self::$features;
		$widgets = array();
		$tracking = array();

		foreach ( $features as $slug => $config ) {
			if ( $slug === 'cart_recovery' ) {
				$tracking[ $slug ] = $config;
			} else {
				$widgets[ $slug ] = $config;
			}
		}

		$widget_order = array( 'chat_widget', 'floating_contact', 'newsletter', 'newsletter_popup', 'contact_form' );
		$ordered = array();
		foreach ( $widget_order as $slug ) {
			if ( isset( $widgets[ $slug ] ) ) {
				$ordered[ $slug ] = $widgets[ $slug ];
			}
		}
		$widgets = $ordered;

		if ( ! empty( $widgets ) ) {
			echo '<div class="ac-feature-subsection"><h4>' . __( 'Storefront Widgets', 'ac-serial-numbers' ) . '</h4>';
			foreach ( $widgets as $slug => $config ) {
				self::render_card( $slug );
			}
			echo '</div>';
		}

		if ( ! empty( $tracking ) ) {
			echo '<div class="ac-feature-subsection"><h4>' . __( 'Tracking & Recovery', 'ac-serial-numbers' ) . '</h4>';
			foreach ( $tracking as $slug => $config ) {
				self::render_card( $slug );
			}
			echo '</div>';
		}
	}
}

endif;

add_action( 'wp_footer', function() {
	AC_Serial_Numbers_Helper_Features::render_frontend();
}, 100 );

add_action( 'wp_ajax_ac_check_for_updates', function() {
	check_ajax_referer( 'ac-serial-numbers-settings', 'security' );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'msg' => 'Insufficient permissions.' ) );
	}

	if ( ! class_exists( 'AC_Serial_Numbers_Updater' ) ) {
		wp_send_json_error( array( 'msg' => 'Updater not loaded.' ) );
	}

	AC_Serial_Numbers_Updater::force_check();

	$remote = get_site_transient( 'ac_remote_update_info' );
	$current = AC_SERIAL_NUMBER_PLUGIN_VERSION;
	$has_update = $remote && version_compare( $remote->version, $current, '>' );
	$available_version = $has_update ? $remote->version : '';
	$changelog = $has_update && ! empty( $remote->changelog ) ? $remote->changelog : '';

	ob_start();
	?>
	<div class="ac-update-section" id="ac-update-section">
		<h3><?php _e( 'LicenceBot Helper Plugin', 'ac-serial-numbers' ); ?></h3>
		<p>
			<?php printf( __( 'Installed version: %s', 'ac-serial-numbers' ), '<code>' . esc_html( $current ) . '</code>' ); ?>
			<?php if ( $has_update ) : ?>
				&nbsp;&nbsp;
				<?php printf( __( 'Available: %s', 'ac-serial-numbers' ), '<strong>' . esc_html( $available_version ) . '</strong>' ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $has_update ) : ?>
			<span class="ac-feature-status ac-status-warning">
				<?php _e( 'Update available', 'ac-serial-numbers' ); ?>
			</span>
			<div class="ac-update-buttons">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'update.php?action=upgrade-plugin&plugin=ac-serial-numbers/ac-serial-numbers.php' ), 'upgrade-plugin_ac-serial-numbers/ac-serial-numbers.php' ) ); ?>" class="button button-primary">
					<?php _e( 'Update Now', 'ac-serial-numbers' ); ?>
				</a>
				<button type="button" class="button" id="ac-check-updates">
					<?php _e( 'Check for Updates', 'ac-serial-numbers' ); ?>
				</button>
			</div>
		<?php else : ?>
			<span class="ac-feature-status ac-status-success">
				<?php _e( 'Up to date', 'ac-serial-numbers' ); ?>
			</span>
			<div class="ac-update-buttons">
				<button type="button" class="button" id="ac-check-updates">
					<?php _e( 'Check for Updates', 'ac-serial-numbers' ); ?>
				</button>
			</div>
		<?php endif; ?>
		<div id="ac-update-status"></div>
	</div>
	<?php if ( $changelog ) : ?>
		<h3 style="margin-top: 30px;"><?php _e( 'Changelog', 'ac-serial-numbers' ); ?></h3>
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; white-space: pre-wrap;"><?php echo esc_html( $changelog ); ?></div>
	<?php endif; ?>
	<?php
	$html = ob_get_clean();

	wp_send_json_success( array(
		'has_update' => $has_update,
		'version'    => $has_update ? $remote->version : $current,
		'html'       => $html,
	));
});

add_action( 'admin_enqueue_scripts', function() {
	$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
	$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

	if ( $page !== 'ac-serial-numbers-settings' || $tab !== 'updates' ) {
		return;
	}

	wp_enqueue_script(
		'ac-helper-updates-admin',
		ac_serial_numbers()->plugin_url() . '/assets/js/ac-helper-updates-admin.js',
		array( 'jquery' ),
		AC_SERIAL_NUMBER_PLUGIN_VERSION,
		true
	);

	wp_localize_script( 'ac-helper-updates-admin', 'acHelperUpdates', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ac-serial-numbers-settings' ),
	));
});
