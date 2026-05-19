<?php
defined( 'ABSPATH' ) || exit();

if ( ! defined( 'AC_SERIAL_OPT_AUTH_SECRET' ) ) {
	define( 'AC_SERIAL_OPT_AUTH_SECRET', '_ac_serial_auth_secret' );
}
if ( ! defined( 'AC_SERIAL_OPT_STORE_ID' ) ) {
	define( 'AC_SERIAL_OPT_STORE_ID', '_ac_serial_store_id' );
}
if ( ! defined( 'AC_SERIAL_OPT_REGISTERED_AT' ) ) {
	define( 'AC_SERIAL_OPT_REGISTERED_AT', '_ac_serial_registered_at' );
}
if ( ! defined( 'AC_SERIAL_OPT_LAST_ERROR' ) ) {
	define( 'AC_SERIAL_OPT_LAST_ERROR', '_ac_serial_last_error' );
}

if ( ! class_exists( 'AC_Serial_Numbers_Settings_General' ) ) :
	/**
	 * AC_Serial_Numbers_Settings_General
	 */
	class AC_Serial_Numbers_Settings_General extends WC_Settings_Page {
		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id    = 'general';
			$this->label = __( 'General', 'ac-serial-numbers' );

			add_filter( 'ac_serial_numbers_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
			add_action( 'ac_serial_numbers_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'ac_serial_numbers_settings_save_' . $this->id, array( $this, 'save' ) );
		}

		/**
		 * Get settings array
		 *
		 * @return array
		 */
		public function get_settings() {
			global $woocommerce, $wp_roles;

			// =========================================================================
			// LICENCEBOT AUTO-CONNECT STATUS CHECK
			// =========================================================================

			// Check if LicenceBot auto-connect is configured
			$is_licencebot_configured = (
				( defined( 'AC_SERIAL_ORG_TOKEN' ) &&
				  strpos( AC_SERIAL_ORG_TOKEN, '%%' ) === false &&
				  ! empty( AC_SERIAL_ORG_TOKEN ) ) ||
				! empty( get_option( '_ac_serial_org_token' ) )
			);

			// Check if connected to LicenceBot
			$is_licencebot_connected = ! empty( get_option( AC_SERIAL_OPT_STORE_ID ) );

			// Get connection status
			$store_id = get_option( AC_SERIAL_OPT_STORE_ID );
			$registered_at = get_option( AC_SERIAL_OPT_REGISTERED_AT );
			$last_error = get_option( AC_SERIAL_OPT_LAST_ERROR );
			$auth_secret = get_option( AC_SERIAL_OPT_AUTH_SECRET );
			$webhook_url = rest_url( 'ac-serial-numbers/v1/order/update/' );

			// Build connection status HTML
			$connection_status_html = '';
			if ( $is_licencebot_connected ) {
				$connection_status_html = '<div style="padding: 15px; background: #f0f9ff; border: 1px solid #b3d9ff; border-radius: 4px;">';
				$connection_status_html .= '<p style="margin: 0 0 10px 0;"><span style="color: #00a32a; font-weight: bold;">✅ Connected to LicenceBot</span></p>';
				if ( $store_id ) {
					$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;">Store ID: <code>' . esc_html( $store_id ) . '</code></p>';
				}
				if ( $registered_at ) {
					$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;">Connected on: ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $registered_at ) . '</p>';
				}
				if ( $auth_secret ) {
					$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;"><span style="color: #00a32a;">✅ Webhook Auth Secret:</span> Configured (delivery webhooks will be authenticated)</p>';
				} else {
					$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;"><span style="color: #d63638;">⚠️ Webhook Auth Secret:</span> Missing — delivery webhooks may fail. Re-connect or paste it below.</p>';
				}
				$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;">Webhook URL: <code>' . esc_html( $webhook_url ) . '</code></p>';

				$disconnect_url = wp_nonce_url(
					add_query_arg( 'ac_serial_disconnect', '1', admin_url() ),
					'ac_serial_disconnect'
				);
				$connection_status_html .= '<p style="margin: 10px 0 0 0;"><a href="' . esc_url( $disconnect_url ) . '" class="button" onclick="return confirm(\'Are you sure you want to disconnect? You will need to manually enter API credentials or re-download the plugin from LicenceBot.\');">Disconnect</a></p>';
				$connection_status_html .= '</div>';
			} elseif ( $is_licencebot_configured ) {
				// Configured but not connected yet
				$connection_status_html = '<div style="padding: 15px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 4px;">';
				$connection_status_html .= '<p style="margin: 0 0 10px 0;"><span style="color: #f57f17; font-weight: bold;">⚠️ LicenceBot Auto-Connect Available</span></p>';
				$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;">This plugin was downloaded from your LicenceBot Dashboard and can connect automatically.</p>';

				if ( $last_error ) {
					$connection_status_html .= '<p style="margin: 10px 0; font-size: 13px; color: #d63638;">Last Error: ' . esc_html( $last_error ) . '</p>';
				}

				// Add connect button
				$connect_url = wp_nonce_url(
					add_query_arg( 'ac_serial_reconnect', '1', admin_url() ),
					'ac_serial_reconnect'
				);
				$connection_status_html .= '<p style="margin: 10px 0 0 0;"><a href="' . esc_url( $connect_url ) . '" class="button button-primary">Connect to LicenceBot</a></p>';
				$connection_status_html .= '</div>';
			} else {
				// Manual mode
				$connection_status_html = '<div style="padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">';
				$connection_status_html .= '<p style="margin: 0 0 5px 0;"><span style="font-weight: bold;">📋 Manual Configuration Mode</span></p>';
				$connection_status_html .= '<p style="margin: 5px 0; font-size: 13px;">To enable auto-connect, download this plugin from your <a href="https://app.licencebot.com" target="_blank">LicenceBot Dashboard</a>.</p>';

				$connect_url = wp_nonce_url(
					add_query_arg( 'ac_serial_connect', '1', admin_url() ),
					'ac_serial_connect'
				);
				$connection_status_html .= '<p style="margin: 10px 0 0 0;"><a href="' . esc_url( $connect_url ) . '" class="button button-primary">Connect to LicenceBot</a></p>';

				$connection_status_html .= '</div>';
			}

			$settings = array(
				[
					'title' => __( 'Serial Number Settings.', 'ac-serial-numbers' ),
					'type'  => 'title',
					'desc'  => __( 'The following options affects how the serial numbers will work.', 'ac-serial-numbers' ),
					'id'    => 'section_serial_numbers'
				],
				[
					'title'   => __( 'Auto Complete Order', 'ac-serial-numbers' ),
					'id'      => 'ac_serial_numbers_autocomplete_order',
					'desc'    => __( 'This will automatically complete an order after successfull payment.', 'ac-serial-numbers' ),
					'type'    => 'checkbox',
					'default' => 'no',
				],
				[
					'title' => __( 'Reuse Serial Number', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_reuse_serial_number',
					'desc'  => __( 'This will recover failed, refunded serial number for selling again.', 'ac-serial-numbers' ),
					'type'  => 'checkbox',
				],
				[
					'title'           => __( 'Revoke statuses', 'ac-serial-numbers' ),
					'desc'            => __( 'Cancelled', 'ac-serial-numbers' ),
					'id'              => 'ac_serial_numbers_revoke_status_cancelled',
					'default'         => 'yes',
					'type'            => 'checkbox',
					'checkboxgroup'   => 'start',
					'show_if_checked' => 'option',
				],
				[
					'desc'          => __( 'Refunded', 'ac-serial-numbers' ),
					'id'            => 'ac_serial_numbers_revoke_status_refunded',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => '',

				],
				[
					'desc'          => __( 'Failed', 'ac-serial-numbers' ),
					'id'            => 'ac_serial_numbers_revoke_status_failed',
					'default'       => 'yes',
					'type'          => 'checkbox',
					'checkboxgroup' => 'end',

				],
				[
					'title'   => __( 'Hide Serial Number', 'ac-serial-numbers' ),
					'id'      => 'ac_serial_numbers_hide_serial_number',
					'desc'    => __( 'All serial numbers will be hidden and only displayed when the "Show" button is clicked.', 'ac-serial-numbers' ),
					'default' => 'yes',
					'type'    => 'checkbox',
				],
				[
					'title'   => __( 'Disable Software Support', 'ac-serial-numbers' ),
					'id'      => 'ac_serial_numbers_disable_software_support',
					'desc'    => __( 'This will disable Software Licensing support & API functionalities..', 'ac-serial-numbers' ),
					'default' => 'yes',
					'type'    => 'checkbox',
				],
				[
					'type' => 'sectionend',
					'id'   => 'section_serial_numbers'
				],
				[
					'title' => __( 'Stock notification.', 'ac-serial-numbers' ),
					'type'  => 'title',
					'desc'  => __( 'The following options affects how stock notification will work.', 'ac-serial-numbers' ),
					'id'    => 'stock_section'
				],
				[
					'title'             => __( 'Stock Notification Email', 'ac-serial-numbers' ),
					'id'                => 'ac_serial_numbers_enable_stock_notification',
					'desc'              => __( 'This will send you notification email when product stock is low.', 'ac-serial-numbers' ),
					'type'              => 'checkbox',
					'sanitize_callback' => 'intval',
				],
				array(
					'title'   => __( 'Stock Threshold', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_stock_threshold',
					'desc'    => __( 'When stock goes below the above number, it will send notification email.', 'ac-serial-numbers' ),
					'type'    => 'number',
					'default' => '5',
				),
				array(
					'title'   => __( 'Notification Recipient Email', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_notification_recipient',
					'desc'    => __( 'The email address to be used for sending the email notification.', 'ac-serial-numbers' ),
					'type'    => 'text',
					'default' => get_option( 'admin_email' ),
				),
				array(
					'title'   => __( 'Serial Number Support Email', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_support_email',
					'desc'    => __( 'The email address to be used for support email in serial number.', 'ac-serial-numbers' ),
					'type'    => 'text',
					'default' => get_option( 'admin_email' ),
				),
				array(
					'title'   => __( 'Activation Guideline', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_system_activation_guide',
					'desc'    => __( 'This text will be sent to your customer along with serial key as activation guide.', 'ac-serial-numbers' ),
					'type'    => 'textarea',
				),
				[
					'type' => 'sectionend',
					'id'   => 'stock_section'
				],

				// =========================================================================
				// LICENCEBOT CONNECTION STATUS SECTION
				// =========================================================================
				[
					'title' => __( 'LicenceBot Connection', 'ac-serial-numbers' ),
					'type'  => 'title',
					'desc'  => __( 'Connect your store with LicenceBot for automatic serial number delivery.', 'ac-serial-numbers' ),
					'id'    => 'licencebot_connection_section'
				],
				array(
					'title'   => __( 'Connection Status', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_licencebot_status',
					'type'    => 'info',
					'text' => $connection_status_html,
				),
				[
					'type' => 'sectionend',
					'id'   => 'licencebot_connection_section'
				],

				// =========================================================================
				// API SETTINGS SECTION
				// Note: When LicenceBot is connected, these are auto-configured
				// =========================================================================
				[
					'title' => __( 'API Settings', 'ac-serial-numbers' ),
					'type'  => 'title',
					'desc'  => $is_licencebot_connected ?
						__( '<strong style="color: #00a32a;">✅ Auto-configured by LicenceBot</strong>', 'ac-serial-numbers' ) :
						__( 'Enter your LicenceBot API credentials below, or download the plugin from your LicenceBot Dashboard for auto-connect.', 'ac-serial-numbers' ),
					'id'    => 'api_key_section'
				],
				array(
					'title'   => __( 'API Endpoint', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_api_endpoint',
					'desc'    => $is_licencebot_connected ?
						__( 'Auto-configured. Download a fresh plugin from LicenceBot to update.', 'ac-serial-numbers' ) :
						__( 'Get your endpoint from LicenceBot.', 'ac-serial-numbers' ),
					'type'    => 'url',
					'placeholder' => 'Paste your API Endpoint here',
					'custom_attributes' => $is_licencebot_connected ? array( 'disabled' => 'disabled' ) : array(),
				),
				array(
					'title'   => __( 'API Key', 'ac-serial-numbers' ),
					'id'    => 'ac_serial_numbers_api_key',
					'desc'    => $is_licencebot_connected ?
						__( 'Auto-configured. Download a fresh plugin from LicenceBot to update.', 'ac-serial-numbers' ) :
						__( 'Get your key from LicenceBot.', 'ac-serial-numbers' ),
					'type'    => 'text',
					'placeholder' => 'Paste your API key here',
					'custom_attributes' => $is_licencebot_connected ? array( 'disabled' => 'disabled' ) : array(),
				),
			array(
				'title'   => __( 'Auth Secret', 'ac-serial-numbers' ),
				'id'    => 'ac_serial_numbers_webhook_secret',
				'desc'    => $is_licencebot_connected ?
					__( 'Auto-shared with LicenceBot during registration.', 'ac-serial-numbers' ) :
					__( 'Copy this secret to your LicenceBot dashboard.', 'ac-serial-numbers' ),
				'type'    => 'info',
				'text' => get_option( '_ac_serial_numbers_webhook_secret', '' ),
			),
			array(
				'title'   => __( 'LicenceBot Webhook Auth Secret', 'ac-serial-numbers' ),
				'id'    => AC_SERIAL_OPT_AUTH_SECRET,
				'desc'    => __( 'Auto-filled during registration. Used to validate incoming delivery webhooks from LicenceBot (X-Webhook-Secret header). If empty, re-connect to LicenceBot or paste the auth_secret from LicenceBot Dashboard.', 'ac-serial-numbers' ),
				'type'    => 'text',
				'placeholder' => 'Auto-filled on registration',
				'custom_attributes' => $is_licencebot_connected && ! empty( $auth_secret ) ? array( 'readonly' => 'readonly' ) : array(),
			),
				[
					'type' => 'sectionend',
					'id'   => 'api_key_section'
				],
			);

			return apply_filters( 'ac_serial_numbers_general_settings_fields', $settings );
		}

		/**
		 * Save settings
		 */
		public function save() {
			$settings = $this->get_settings();
			AC_Serial_Numbers_Admin_Settings::save_fields( $settings );
		}
	}

endif;

return new AC_Serial_Numbers_Settings_General();
