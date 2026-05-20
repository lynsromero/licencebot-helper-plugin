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

		$current_version = AC_SERIAL_NUMBER_PLUGIN_VERSION;
		$remote = get_site_transient( 'ac_remote_update_info' );
		$has_update = $remote && version_compare( $remote->version, $current_version, '>' );
		$available_version = $has_update ? $remote->version : '';
		$changelog = $has_update && ! empty( $remote->changelog ) ? $remote->changelog : '';
		?>
		<style>
			.ac-update-section { margin-bottom:24px; padding:20px; background:#fff; border:1px solid #ddd; border-radius:4px; }
			.ac-update-section h3 { margin-top:0; }
			.ac-update-buttons { margin-top:15px; }
			.ac-update-buttons .button { margin-right:8px; }
			#ac-update-status { margin-top:10px; }
		</style>
		<div class="wrap" style="max-width: 800px;">
			<h2><?php _e( 'Plugin Updates', 'ac-serial-numbers' ); ?></h2>

			<div class="ac-update-section" id="ac-update-section">
				<h3><?php _e( 'LicenceBot Helper Plugin', 'ac-serial-numbers' ); ?></h3>
				<p>
					<?php printf( __( 'Installed version: %s', 'ac-serial-numbers' ), '<code>' . esc_html( $current_version ) . '</code>' ); ?>
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
		</div>
		<?php
	}

	public function save() {
	}
}

endif;

return new AC_Serial_Numbers_Settings_Updates();
