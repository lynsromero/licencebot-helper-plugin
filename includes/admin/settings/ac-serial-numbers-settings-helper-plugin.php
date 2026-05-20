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
		$features = AC_Serial_Numbers_Helper_Features::get_all();

		if ( empty( $features ) ) {
			echo '<p>No helper features are registered.</p>';
			return;
		}

		AC_Serial_Numbers_Helper_Features::render_grouped_cards();
	}

	public function save() {
		$features = AC_Serial_Numbers_Helper_Features::get_all();

		foreach ( $features as $slug => $config ) {
			$enabled = isset( $_POST[ $config['enabled_option'] ] );
			update_option( $config['enabled_option'], $enabled ? 'yes' : 'no' );

			if ( $enabled ) {
				$result = AC_Serial_Numbers_Helper_Features::fetch_code( $slug );
				if ( is_wp_error( $result ) ) {
					error_log( 'LicenceBot helper feature fetch failed [' . $slug . ']: ' . $result->get_error_message() );
				}
			} else {
				delete_option( $config['code_option'] );
				delete_transient( 'lb_' . $slug . '_html' );
			}
		}
	}
}

endif;

return new AC_Serial_Numbers_Settings_Helper_Plugin();
