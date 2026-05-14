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

	public function get_settings() {
		$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
		$token    = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		$enabled  = get_option( AC_SERIAL_CHAT_WIDGET_ENABLED, false );

		$endpoint_url = '';
		if ( $store_id && $token ) {
			$endpoint_url = add_query_arg(
				array(
					'store_id' => $store_id,
					't'        => $token,
				),
				AC_SERIAL_CHAT_WIDGET_API_BASE
			);
		}

		$settings = array(
			array(
				'title' => __( 'Floating Chat Widget', 'ac-serial-numbers' ),
				'type'  => 'title',
				'desc'  => __( 'Enable or disable the Floating Chat Widget on your store. The widget is configured from your LicenceBot Dashboard.', 'ac-serial-numbers' ),
				'id'    => 'chat_widget_section',
			),
			array(
				'title'   => __( 'Enable Chat Widget', 'ac-serial-numbers' ),
				'id'      => AC_SERIAL_CHAT_WIDGET_ENABLED,
				'desc'    => __( 'Display the Floating Chat Widget on your store.', 'ac-serial-numbers' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title' => __( 'API Endpoint', 'ac-serial-numbers' ),
				'id'    => 'ac_chat_widget_endpoint',
				'type'  => 'info',
				'text'  => $endpoint_url
					? '<code style="word-break: break-all;">' . esc_url( $endpoint_url ) . '</code>'
					: '<em>' . __( 'Connect to LicenceBot first to generate the endpoint URL.', 'ac-serial-numbers' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'chat_widget_section',
			),
		);

		return apply_filters( 'ac_serial_numbers_helper_plugin_settings', $settings );
	}

	public function output() {
		$GLOBALS['hide_save_button'] = false;

		$store_id      = get_option( AC_SERIAL_OPT_STORE_ID );
		$token         = get_option( AC_SERIAL_OPT_STORE_TOKEN );
		$is_registered = ! empty( $store_id ) && ! empty( $token );
		$enabled       = get_option( AC_SERIAL_CHAT_WIDGET_ENABLED, false );

		$endpoint_url = '';
		if ( $store_id && $token ) {
			$endpoint_url = add_query_arg(
				array(
					'store_id' => $store_id,
					't'        => $token,
				),
				AC_SERIAL_CHAT_WIDGET_API_BASE
			);
		}

		$cached_html = get_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY );

		WC_Admin_Settings::output_fields( $this->get_settings() );
		?>
		<div style="margin-top: 20px;">
			<h3><?php _e( 'Chat Widget Actions', 'ac-serial-numbers' ); ?></h3>
			<p><?php _e( 'Click the button below to fetch the chat widget code from LicenceBot and inject it into your store.', 'ac-serial-numbers' ); ?></p>

			<div id="ac-chat-widget-fetch-status" style="margin-bottom: 15px;"></div>

			<button
				id="ac-fetch-chat-widget"
				class="button button-primary"
				data-store-id="<?php echo esc_attr( $store_id ); ?>"
				data-token="<?php echo esc_attr( $token ); ?>"
				data-nonce="<?php echo wp_create_nonce( 'ac-serial-numbers-settings' ); ?>"
				<?php echo $is_registered ? '' : 'disabled'; ?>
			>
				<?php _e( 'Add Chat Widget', 'ac-serial-numbers' ); ?>
			</button>

			<?php if ( ! $is_registered ) : ?>
				<p style="color: #d63638; margin-top: 10px;">
					<?php _e( 'Connect to LicenceBot first (General tab) to enable this feature.', 'ac-serial-numbers' ); ?>
				</p>
			<?php endif; ?>

			<div id="ac-chat-widget-preview" style="margin-top: 20px; <?php echo $cached_html ? '' : 'display: none;'; ?>">
				<h4><?php _e( 'Current Widget Preview', 'ac-serial-numbers' ); ?></h4>
				<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
					<pre style="white-space: pre-wrap; word-break: break-all; margin: 0; font-size: 12px;" id="ac-chat-widget-embed-display"><?php echo esc_html( $cached_html ?: '' ); ?></pre>
				</div>
				<p style="margin-top: 10px; font-size: 13px; color: #666;">
					<?php _e( 'This is the embed code currently cached. It updates automatically every 5 minutes.', 'ac-serial-numbers' ); ?>
				</p>
			</div>

			<div id="ac-chat-widget-fetch-result" style="margin-top: 20px; display: none;">
				<h4><?php _e( 'Fetched Widget Code', 'ac-serial-numbers' ); ?></h4>
				<div style="background: #f0f9ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 4px;">
					<pre style="white-space: pre-wrap; word-break: break-all; margin: 0; font-size: 12px;" id="ac-chat-widget-embed-html"></pre>
				</div>
			</div>
		</div>
		<?php
	}

	public function save() {
		$settings = $this->get_settings();
		WC_Admin_Settings::save_fields( $settings );

		$enabled = isset( $_POST[ AC_SERIAL_CHAT_WIDGET_ENABLED ] );
		if ( ! $enabled ) {
			delete_transient( AC_SERIAL_CHAT_WIDGET_CACHE_KEY );
		}
	}
}

endif;

return new AC_Serial_Numbers_Settings_Helper_Plugin();
