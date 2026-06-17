<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'AC_Serial_Numbers_Settings_Shortcodes' ) ) :

class AC_Serial_Numbers_Settings_Shortcodes extends WC_Settings_Page {

	private static $shortcode_catalog = null;

	public function __construct() {
		$this->id    = 'shortcodes';
		$this->label = __( 'Shortcodes', 'ac-serial-numbers' );

		add_filter( 'ac_serial_numbers_settings_tabs_array', array( $this, 'add_settings_page' ), 50 );
		add_action( 'ac_serial_numbers_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_update_options_' . $this->id, array( $this, 'save' ) );
	}

	public function output() {
		$catalog = self::get_catalog();
		?>
		<style>
			.ac-shortcodes-table { width:100%; border-collapse:collapse; margin-top:10px; }
			.ac-shortcodes-table th,
			.ac-shortcodes-table td { padding:12px 15px; text-align:left; border-bottom:1px solid #ddd; }
			.ac-shortcodes-table th { background:#f1f1f1; font-weight:600; }
			.ac-shortcodes-table code { font-size:13px; background:#f0f0f1; padding:3px 8px; border-radius:3px; }
			.ac-shortcodes-table .cat-header td { background:#f9f9f9; font-weight:700; padding:10px 15px; border-bottom:2px solid #c3c4c7; }
		</style>

		<h2><?php esc_html_e( 'LicenceBot Shortcodes', 'ac-serial-numbers' ); ?></h2>
		<p><?php esc_html_e( 'Enable or disable shortcodes below. Disabled shortcodes return empty output on the frontend.', 'ac-serial-numbers' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'ac-sc-save', 'ac_sc_nonce' ); ?>
			<table class="ac-shortcodes-table">
				<thead>
					<tr>
						<th style="width:32px;"><?php esc_html_e( 'On', 'ac-serial-numbers' ); ?></th>
						<th style="width:320px;"><?php esc_html_e( 'Shortcode', 'ac-serial-numbers' ); ?></th>
						<th><?php esc_html_e( 'Description', 'ac-serial-numbers' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catalog as $category => $shortcodes ) : ?>
						<tr class="cat-header"><td colspan="3"><?php echo esc_html( $category ); ?></td></tr>
						<?php foreach ( $shortcodes as $sc ) :
							$default = isset( $sc['default'] ) ? $sc['default'] : '1';
							$stored  = get_option( $sc['option'], $default );
							$is_yes_no = ! empty( $sc['yes_no'] );
							$checked = $is_yes_no ? ( $stored === 'yes' ) : ( $stored === '1' );
						?>
							<tr>
								<td>
									<input type="checkbox" name="ac_sc_status[<?php echo esc_attr( $sc['option'] ); ?>]"
										value="1" <?php checked( $checked ); ?> />
								</td>
								<td><code>[<?php echo esc_html( $sc['tag'] ); ?>]</code></td>
								<td><?php echo esc_html( $sc['description'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit">
				<button type="submit" class="button-primary woocommerce-save-button" name="save" value="Save changes">
					<?php esc_html_e( 'Save changes', 'ac-serial-numbers' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	public function save() {
		if ( empty( $_POST['ac_sc_nonce'] ) || ! wp_verify_nonce( $_POST['ac_sc_nonce'], 'ac-sc-save' ) ) {
			return;
		}
		$catalog = self::get_catalog();
		$posted = isset( $_POST['ac_sc_status'] ) && is_array( $_POST['ac_sc_status'] ) ? $_POST['ac_sc_status'] : array();

		foreach ( $catalog as $category => $shortcodes ) {
			foreach ( $shortcodes as $sc ) {
				$option  = $sc['option'];
				$checked = isset( $posted[ $option ] );
				if ( ! empty( $sc['yes_no'] ) ) {
					update_option( $option, $checked ? 'yes' : 'no' );
				} else {
					update_option( $option, $checked ? '1' : '0' );
				}
			}
		}
	}

	private static function get_catalog() {
		if ( self::$shortcode_catalog !== null ) {
			return self::$shortcode_catalog;
		}

		self::$shortcode_catalog = array(
			'Customer' => array(
				array(
					'tag'         => 'licencebot_contact_form',
					'option'      => 'licencebot_contact_form_enabled',
					'yes_no'      => true,
					'default'     => 'no',
					'description' => 'Contact/support form. Configure fields and styling in LicenceBot dashboard.',
				),
				array(
					'tag'         => 'licencebot_support_tickets',
					'option'      => 'licencebot_support_tickets_enabled',
					'yes_no'      => true,
					'default'     => 'no',
					'description' => 'Support ticket system with live chat interface.',
				),
			),
			'Storefront' => array(
				array(
					'tag'         => 'licencebot_sales_notification',
					'option'      => 'licencebot_sc_licencebot_sales_notification_enabled',
					'description' => 'Floating "X just bought ..." sales notification toaster.',
				),
				array(
					'tag'         => 'licencebot_sales_counter',
					'option'      => 'licencebot_sc_licencebot_sales_counter_enabled',
					'description' => 'Static "N sales today" counter badge.',
				),
				array(
					'tag'         => 'licencebot_visitor_alerts',
					'option'      => 'licencebot_sc_licencebot_visitor_alerts_enabled',
					'description' => '"N visitors viewing this page" live badge.',
				),
			),
			'Conversion' => array(
				array(
					'tag'         => 'licencebot_coupon_box',
					'option'      => 'licencebot_sc_licencebot_coupon_box_enabled',
					'description' => 'Email-gated coupon claim form.',
				),
				array(
					'tag'         => 'licencebot_newsletter_signup',
					'option'      => 'licencebot_sc_licencebot_newsletter_signup_enabled',
					'description' => 'Inline newsletter signup form.',
				),
				array(
					'tag'         => 'licencebot_sales_popup',
					'option'      => 'licencebot_sc_licencebot_sales_popup_enabled',
					'description' => 'Centered social-proof sales popup.',
				),
				array(
					'tag'         => 'licencebot_popup',
					'option'      => 'licencebot_sc_licencebot_popup_enabled',
					'description' => 'Generic configurable popup with time/exit trigger.',
				),
			),
			'Key Tools' => array(
				array(
					'tag'         => 'licencebot_get_cid',
					'option'      => 'licencebot_sc_licencebot_get_cid_enabled',
					'description' => 'Generate Confirmation ID (CID) from Installation ID (IID).',
				),
				array(
					'tag'         => 'licencebot_redeem',
					'option'      => 'licencebot_sc_licencebot_redeem_enabled',
					'description' => 'Redeem product keys via Microsoft.',
				),
				array(
					'tag'         => 'licencebot_check_key',
					'option'      => 'licencebot_sc_licencebot_check_key_enabled',
					'description' => 'Check Windows/Office product key status.',
				),
			),
		);

		return self::$shortcode_catalog;
	}
}

endif;

return new AC_Serial_Numbers_Settings_Shortcodes();
