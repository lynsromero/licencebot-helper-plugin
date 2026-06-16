<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'AC_Serial_Numbers_Settings_Helper_Plugin' ) ) :

class AC_Serial_Numbers_Settings_Helper_Plugin extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'helper-plugin';
		$this->label = __( 'Helper Plugin', 'ac-serial-numbers' );

		add_filter( 'ac_serial_numbers_settings_tabs_array', array( $this, 'add_settings_page' ), 40 );
		add_action( 'ac_serial_numbers_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'ac_serial_numbers_settings_save_' . $this->id, array( $this, 'save' ) );
	}

	public function output() {
		$data = AC_Serial_Numbers_Config_Sync::fetch_config( true );
		if ( ! $data ) {
			$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
			$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
			if ( empty( $store_id ) || empty( $token ) ) {
				echo '<p>' . __( 'Not connected — connect to LicenceBot in the General tab first.', 'ac-serial-numbers' ) . '</p>';
			} else {
				echo '<div class="notice notice-error"><p>' . __( 'Could not reach LicenceBot. Check that your store is registered and try again.', 'ac-serial-numbers' ) . '</p></div>';
			}
			return;
		}

		$config   = isset( $data['config'] ) ? $data['config'] : array();
		$features = isset( $data['features'] ) ? $data['features'] : array();

		$section_map = array(
			'Support'   => array( 10, 39 ),
			'Commerce'  => array( 40, 49 ),
			'Analytics' => array( 50, 69 ),
		);

		$grouped = array();
		foreach ( $features as $f ) {
			$sort = isset( $f['sort'] ) ? (int) $f['sort'] : 99;
			$section = 'Other';
			foreach ( $section_map as $name => $range ) {
				if ( $sort >= $range[0] && $sort <= $range[1] ) {
					$section = $name;
					break;
				}
			}
			$grouped[ $section ][] = $f;
		}
		foreach ( $grouped as $section => $section_features ) :
			$section_label = $section;
			if ( $section === 'Support' ) {
				$section_label = __( 'Support & Communication', 'ac-serial-numbers' );
			} elseif ( $section === 'Commerce' ) {
				$section_label = __( 'Commerce & Conversion', 'ac-serial-numbers' );
			} elseif ( $section === 'Analytics' ) {
				$section_label = __( 'Analytics & Tracking', 'ac-serial-numbers' );
			}
		?>
		<div class="ac-feature-subsection" style="margin-bottom:30px;">
			<h4 style="margin:0 0 15px;font-size:14px;color:#23282d;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #ddd;padding-bottom:8px;">
				<?php echo esc_html( $section_label ); ?>
			</h4>
			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( $section_features as $f ) :
					$key      = $f['key'];
					$cfg_key  = AC_Serial_Numbers_Config_Sync::slug_to_config_key( $key );
					$checked  = array_key_exists( $cfg_key, $config ) ? (bool) $config[ $cfg_key ] : ( ! empty( $f['default_enabled'] ) );
				?>
					<tr>
						<th scope="row" style="width:250px;">
							<label for="feat_<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $f['label'] ); ?>
							</label>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox"
										id="feat_<?php echo esc_attr( $key ); ?>"
										name="features[<?php echo esc_attr( $key ); ?>]"
										value="1"
										<?php checked( $checked ); ?>>
									<?php _e( 'Enable', 'ac-serial-numbers' ); ?>
								</label>
								<?php if ( ! empty( $f['description'] ) ) : ?>
									<p class="description" style="margin-top:4px;"><?php echo esc_html( $f['description'] ); ?></p>
								<?php endif; ?>
								<?php if ( $key === 'contact_page_form' ) : ?>
									<p class="description" style="margin-top:4px;">
										<?php _e( 'Shortcode:', 'ac-serial-numbers' ); ?>
										<code>[licencebot_contact_form]</code>
									</p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endforeach; ?>

		<div class="ac-feature-subsection" style="margin-bottom:30px;">
			<h4 style="margin:0 0 15px;font-size:14px;color:#23282d;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #ddd;padding-bottom:8px;">
				<?php _e( 'Modifiers & Settings', 'ac-serial-numbers' ); ?>
			</h4>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row" style="width:250px;">
							<label for="contact_creates_tickets">
								<?php _e( 'Create Support Tickets', 'ac-serial-numbers' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input type="checkbox"
									id="contact_creates_tickets"
									name="contact_creates_tickets"
									value="1"
									<?php checked( ! empty( $config['contact_creates_tickets'] ) ); ?>>
								<?php _e( 'Convert contact-form submissions into LicenceBot tickets', 'ac-serial-numbers' ); ?>
							</label>
							<p class="description" style="margin-top:4px;">
								<?php _e( 'If OFF, submissions only email the store owner — no ticket is created.', 'ac-serial-numbers' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row" style="width:250px;">
							<label for="brand_color">
								<?php _e( 'Brand Color', 'ac-serial-numbers' ); ?>
							</label>
						</th>
						<td>
							<input type="text"
								id="brand_color"
								name="brand_color"
								value="<?php echo esc_attr( isset( $config['brand_color'] ) ? $config['brand_color'] : '#6366f1' ); ?>"
								class="regular-text"
								placeholder="#6366f1">
							<p class="description" style="margin-top:4px;">
								<?php _e( 'Primary color used by floating widgets and forms.', 'ac-serial-numbers' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function save() {
		$data = AC_Serial_Numbers_Config_Sync::fetch_config( true );
		if ( ! $data ) {
			AC_Serial_Numbers_Admin_Settings::add_error( __( 'Could not load feature list from LicenceBot. Toggle changes were not saved.', 'ac-serial-numbers' ) );
			return;
		}

		$patch = array();
		$features = isset( $data['features'] ) ? $data['features'] : array();

		foreach ( $features as $f ) {
			$key = $f['key'];
			$config_key = AC_Serial_Numbers_Config_Sync::slug_to_config_key( $key );
			$patch[ $config_key ] = ! empty( $_POST['features'][ $key ] );
		}

		if ( isset( $_POST['contact_creates_tickets'] ) ) {
			$patch['contact_creates_tickets'] = true;
		} else {
			$patch['contact_creates_tickets'] = false;
		}

		if ( isset( $_POST['brand_color'] ) ) {
			$color = sanitize_text_field( $_POST['brand_color'] );
			if ( preg_match( '/^#[a-fA-F0-9]{6}$/', $color ) ) {
				$patch['brand_color'] = $color;
			}
		}

		$result = AC_Serial_Numbers_Config_Sync::push_config( $patch );
		if ( is_wp_error( $result ) ) {
			AC_Serial_Numbers_Admin_Settings::add_error( sprintf(
				__( 'Failed to sync to LicenceBot: %s', 'ac-serial-numbers' ),
				$result->get_error_message()
			) );
			return;
		}

		delete_transient( AC_Serial_Numbers_Config_Sync::CONFIG_TRANSIENT );

		$registered = AC_Serial_Numbers_Helper_Features::get_all();
		foreach ( $registered as $slug => $config ) {
			$config_key = AC_Serial_Numbers_Config_Sync::slug_to_config_key( $slug );
			$enabled = ! empty( $patch[ $config_key ] );

			update_option( $config['enabled_option'], $enabled ? 'yes' : 'no' );

			if ( $enabled ) {
				$result_fetch = AC_Serial_Numbers_Helper_Features::fetch_code( $slug );
				if ( is_wp_error( $result_fetch ) ) {
					error_log( 'LicenceBot helper feature fetch failed [' . $slug . ']: ' . $result_fetch->get_error_message() );
				}
			} else {
				delete_option( $config['code_option'] );
				delete_transient( 'lb_' . $slug . '_html' );
			}
		}

		$chat_widget = AC_Serial_Numbers_Helper_Features::get( 'chat_widget' );
		if ( $chat_widget && ! empty( $result['config']['chat_widget_id'] ) ) {
			update_option( $chat_widget['widget_id_option'], $result['config']['chat_widget_id'] );
		}

		AC_Serial_Numbers_Admin_Settings::add_message( __( 'Saved. Changes will appear on the public site within seconds.', 'ac-serial-numbers' ) );
	}
}

endif;

return new AC_Serial_Numbers_Settings_Helper_Plugin();
