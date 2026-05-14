<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'AC_Serial_Numbers_Settings_Updates' ) ) :

class AC_Serial_Numbers_Settings_Updates extends WC_Settings_Page {
	public function __construct() {
		$this->id    = 'updates';
		$this->label = __( 'Updates', 'ac-serial-numbers' );

		add_filter( 'ac_serial_numbers_settings_tabs_array', array( $this, 'add_settings_page' ), 30 );
		add_action( 'ac_serial_numbers_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'ac_serial_numbers_settings_save_' . $this->id, array( $this, 'save' ) );
	}

	public function output() {
		$GLOBALS['hide_save_button'] = true;

		$plugin_name = __('LicenceBot Helper Plugin', 'ac-serial-numbers');
		$current_version = ac_serial_numbers()->get_version();
		$plugin_slug = 'ac-serial-numbers';
		$api_endpoint = get_option( 'ac_serial_numbers_api_endpoint', '' );

		$remote_version = '';
		$changelog = '';
		$update_available = false;
		$error = '';

		if ( ! empty( $api_endpoint ) ) {
			$check_url = rtrim( $api_endpoint, '/' ) . '/plugin/update-check?plugin_slug=' . $plugin_slug . '&installed_version=' . $current_version;
			$response = wp_remote_get( $check_url, array( 'timeout' => 10 ) );

			if ( is_wp_error( $response ) ) {
				$error = __( 'Could not check for updates. API unreachable.', 'ac-serial-numbers' );
			} else {
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( ! empty( $data['version'] ) && version_compare( $data['version'], $current_version, '>' ) ) {
					$remote_version = $data['version'];
					$changelog = isset( $data['changelog'] ) ? $data['changelog'] : '';
					$update_available = true;
				} elseif ( ! empty( $data['version'] ) ) {
					$remote_version = $data['version'];
				}
			}
		}
		?>
		<div class="wrap" style="max-width: 800px;">
			<h2><?php echo esc_html( $plugin_name ); ?></h2>

			<table class="widefat striped" style="margin-top: 15px;">
				<tbody>
					<tr>
						<td style="width: 200px;"><strong><?php _e( 'Installed Version', 'ac-serial-numbers' ); ?></strong></td>
						<td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> v<?php echo esc_html( $current_version ); ?></td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Remote Version', 'ac-serial-numbers' ); ?></strong></td>
						<td>
							<?php if ( ! empty( $remote_version ) ) : ?>
								v<?php echo esc_html( $remote_version ); ?>
							<?php else : ?>
								<em><?php _e( 'Unknown', 'ac-serial-numbers' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Status', 'ac-serial-numbers' ); ?></strong></td>
						<td>
							<?php if ( $update_available ) : ?>
								<span style="color: #d63638; font-weight: bold;"><?php _e( 'Update Available!', 'ac-serial-numbers' ); ?></span>
								<p style="margin-top: 5px;">
									<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-primary">
										<?php _e( 'Go to Plugins Screen to Update', 'ac-serial-numbers' ); ?>
									</a>
								</p>
							<?php elseif ( ! empty( $remote_version ) ) : ?>
								<span style="color: #46b450; font-weight: bold;"><?php _e( 'Up to date', 'ac-serial-numbers' ); ?></span>
							<?php else : ?>
								<span style="color: #72777c;"><?php _e( 'Update server not configured. Connect to LicenceBot to enable updates.', 'ac-serial-numbers' ); ?></span>
							<?php endif; ?>

							<?php if ( $error ) : ?>
								<p style="color: #d63638; margin-top: 5px;"><?php echo esc_html( $error ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'API Endpoint', 'ac-serial-numbers' ); ?></strong></td>
						<td><code><?php echo esc_html( $api_endpoint ?: __( 'Not configured', 'ac-serial-numbers' ) ); ?></code></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $changelog ) : ?>
				<h3 style="margin-top: 30px;"><?php _e( 'Changelog', 'ac-serial-numbers' ); ?></h3>
				<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; white-space: pre-wrap;"><?php echo esc_html( $changelog ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save() {
	}
}

endif;

return new AC_Serial_Numbers_Settings_Updates();
