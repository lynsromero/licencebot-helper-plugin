<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'AC_Serial_Numbers_Settings_Shortcodes' ) ) :

class AC_Serial_Numbers_Settings_Shortcodes extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'shortcodes';
		$this->label = __( 'Shortcodes', 'ac-serial-numbers' );

		add_filter( 'ac_serial_numbers_settings_tabs_array', array( $this, 'add_settings_page' ), 50 );
		add_action( 'ac_serial_numbers_settings_' . $this->id, array( $this, 'output' ) );
	}

	public function output() {
		?>
		<style>
			.ac-shortcodes-table { width:100%; border-collapse:collapse; }
			.ac-shortcodes-table th,
			.ac-shortcodes-table td { padding:12px 15px; text-align:left; border-bottom:1px solid #ddd; }
			.ac-shortcodes-table th { background:#f1f1f1; font-weight:600; }
			.ac-shortcodes-table code { font-size:13px; background:#f0f0f1; padding:3px 8px; border-radius:3px; }
		</style>

		<h2><?php esc_html_e( 'LicenceBot Shortcodes', 'ac-serial-numbers' ); ?></h2>
		<p><?php esc_html_e( 'Use the shortcodes below to place LicenceBot features on any page or post.', 'ac-serial-numbers' ); ?></p>

		<table class="ac-shortcodes-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Shortcode', 'ac-serial-numbers' ); ?></th>
					<th><?php esc_html_e( 'Description', 'ac-serial-numbers' ); ?></th>
					<th><?php esc_html_e( 'Usage', 'ac-serial-numbers' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[licencebot_contact_form]</code></td>
					<td><?php esc_html_e( 'Displays the LicenceBot contact/support form. Configure fields and styling from your LicenceBot Dashboard.', 'ac-serial-numbers' ); ?></td>
					<td><code>[licencebot_contact_form]</code></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}

endif;

return new AC_Serial_Numbers_Settings_Shortcodes();
