<?php
/**
 * Plugin Name: LicenceBot Helper Plugin
 * Plugin URI:  https://licencebot.com
 * Description: Auto-connects your store with LicenceBot for chat, cart recovery, serial number delivery, and more.
 * Version:     3.2.7
 * Author:      Tic Limited
 * Author URI:  https://tic.com.bd
 * License:     GPLv2+
 * Text Domain: licencebot-helper
 * Domain Path: /i18n/languages/
 * Tested up to: 6.8.0
 * WC requires at least: 6.0.0
 * WC tested up to: 10.7.0
 * Requires Plugins: woocommerce
 */

/**
 * Copyright (c) 2016 ticlimited (email : support@tic.com.bd)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// don't call the file directly
defined('ABSPATH') || exit();

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

define('AC_SERIAL_NUMBER_REMOTE_TRANSIENT', 'ac_product_data_transient');

/**
 * LicenceBot Auto-Connect Configuration
 * NOTE: AC_SERIAL_ORG_TOKEN gets REPLACED with actual value
 * when user downloads this plugin from LicenceBot Dashboard.
 * The placeholder value means auto-connect is disabled (manual entry still works).
 * When installed from WordPress.org, the token is set dynamically via
 * handle_licencebot_setup_token() and stored in wp_option _ac_serial_org_token.
 */
define('AC_SERIAL_ORG_TOKEN', '%%REPLACED_AT_DOWNLOAD%%');
define('AC_SERIAL_REGISTRATION_URL', 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/register-helper-store');
define('AC_SERIAL_HELPER_SCRIPT_URL', 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/licencebot-helper');

// Auto-connect status options (stored in wp_options)
define('AC_SERIAL_OPT_STORE_ID', '_ac_serial_store_id');
define('AC_SERIAL_OPT_STORE_TOKEN', '_ac_serial_store_token');
define('AC_SERIAL_OPT_REGISTERED_AT', '_ac_serial_registered_at');
define('AC_SERIAL_OPT_LAST_ERROR', '_ac_serial_last_error');
define('AC_SERIAL_OPT_AUTH_SECRET', '_ac_serial_auth_secret');

// Dynamic org_token (set via WP.org install flow)
define('AC_SERIAL_OPT_ORG_TOKEN_DYNAMIC', '_ac_serial_org_token');
define('AC_SERIAL_COMPLETE_SETUP_URL', 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/complete-helper-setup');

/**
 * AC_Serial_Numbers class.
 *
 * @class AC_Serial_Numbers contains everything for the plugin.
 */
class AC_Serial_Numbers
{
	/**
	 * AC_Serial_Numbers version.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $version = '3.1.7';

	/**
	 * This plugin's instance
	 *
	 * @var AC_Serial_Numbers The one true AC_Serial_Numbers
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * Main AC_Serial_Numbers Instance
	 *
	 * Insures that only one instance of AC_Serial_Numbers exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @return AC_Serial_Numbers The one true AC_Serial_Numbers
	 * @since 1.0.0
	 * @static var array $instance
	 */
	public static function init()
	{
		if (!isset(self::$instance) && !(self::$instance instanceof AC_Serial_Numbers)) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Return plugin version.
	 *
	 * @return string
	 * @since 1.2.0
	 * @access public
	 **/
	public function get_version()
	{
		return $this->version;
	}

	/**
	 * Plugin URL getter.
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function plugin_url()
	{
		return untrailingslashit(plugins_url('/', __FILE__));
	}

	/**
	 * Plugin path getter.
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function plugin_path()
	{
		return untrailingslashit(plugin_dir_path(__FILE__));
	}

	/**
	 * Plugin base path name getter.
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function plugin_basename()
	{
		return plugin_basename(__FILE__);
	}

	/**
	 * Initialize plugin for localization
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function localization_setup()
	{
		load_plugin_textdomain('ac-serial-numbers', false, plugin_basename(dirname(__FILE__)) . '/i18n/languages');
	}

	/**
	 * Determines if the pro version active.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public function is_pro_active()
	{
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');

		return is_plugin_active('ac-serial-numbers-pro/ac-serial-numbers-pro.php') == true;
	}

	/**
	 * Determines if the wc active.
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public function is_wc_active()
	{
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');

		return is_plugin_active('woocommerce/woocommerce.php') == true;
	}

	/**
	 * WooCommerce plugin dependency notice
	 * @since 1.2.0
	 */
	public function wc_missing_notice()
	{
		if (!$this->is_wc_active()) {
			$message = sprintf(
				__('<strong>TIC Serial Numbers</strong> requires <strong>WooCommerce</strong> installed and activated. Please Install %s WooCommerce. %s', 'ac-serial-numbers'),
				'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">',
				'</a>'
			);
			echo sprintf('<div class="notice notice-error"><p>%s</p></div>', $message);
		}
	}

	/**
	 * Define constant if not already defined
	 *
	 * @param string $name
	 * @param string|bool $value
	 *
	 * @return void
	 * @since 1.2.0
	 *
	 */
	private function define($name, $value)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @access protected
	 * @return void
	 */

	public function __clone()
	{
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'ac-serial-numbers'), '1.0.0');
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @access protected
	 * @return void
	 */

	public function __wakeup()
	{
		_doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'ac-serial-numbers'), '1.0.0');
	}

	/**
	 * AC_Serial_Numbers constructor.
	 */
	private function __construct()
	{
		$this->define_constants();
		register_activation_hook(__FILE__, array($this, 'activate_plugin'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

		add_action('woocommerce_loaded', array($this, 'init_plugin'));
		add_action('admin_notices', array($this, 'wc_missing_notice'));
		add_filter('site_transient_update_plugins', array($this, 'remove_update_notification'));
		add_action('plugins_loaded', array($this, 'update_check'));

		// =========================================================================
		// LICENCEBOT AUTO-CONNECT HOOKS
		// =========================================================================

		// Enqueue LicenceBot helper script on frontend pages
		add_action('wp_enqueue_scripts', array($this, 'enqueue_licencebot_helper'));

		// Handle admin actions (re-connect, disconnect, setup token)
		add_action('admin_init', array($this, 'handle_admin_actions'));

		// Handle WP.org install setup token exchange
		add_action('admin_init', array($this, 'handle_licencebot_setup_token'), 5);

		// LicenceBot connection notices (handled separately to avoid conflicts)
		// We'll use a priority of 15 to run after wc_missing_notice
		add_action('admin_notices', array($this, 'admin_notices'), 15);
	}

	public function remove_update_notification($value)
	{
		unset($value->response[plugin_basename(__FILE__)]);
		return $value;
	}

	/**
	 * Define all constants
	 * @return void
	 * @since 1.2.0
	 */
	public function define_constants()
	{
		$this->define('AC_SERIAL_NUMBER_PLUGIN_VERSION', $this->version);
		$this->define('AC_SERIAL_NUMBER_PLUGIN_FILE', __FILE__);
		$this->define('AC_SERIAL_NUMBER_PLUGIN_DIR', dirname(__FILE__));
		$this->define('AC_SERIAL_NUMBER_PLUGIN_INC_DIR', dirname(__FILE__) . '/includes');
	}

	/**
	 * Activate plugin.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function activate_plugin()
	{
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-installer.php';
		AC_Serial_Numbers_Installer::install();
		$this->webhook_secret();

		// NEW: Auto-register with LicenceBot if configured
		// If org_token is still placeholder, this does nothing (manual mode)
		$this->register_with_licencebot();
	}

	public function webhook_secret()
	{
		$data = get_option('_ac_serial_numbers_webhook_secret');
		if ($data)
			return $data;
		$secret_key = wp_generate_password(32, false); // Generate a 32-character secret
		update_option('_ac_serial_numbers_webhook_secret', $secret_key);
	}

	/**
	 * =========================================================================
	 * LICENCEBOT AUTO-CONNECT METHODS
	 * =========================================================================
	 */

	/**
	 * Check if LicenceBot auto-connect is configured
	 * Returns false if org_token is still placeholder (manual entry mode)
	 *
	 * @return bool
	 * @since 2.0.7
	 */
	private function is_auto_connect_enabled()
	{
		// Check baked-in constant (from Download ZIP)
		if (strpos(AC_SERIAL_ORG_TOKEN, '%%') === false && !empty(AC_SERIAL_ORG_TOKEN) && AC_SERIAL_ORG_TOKEN !== 'null') {
			return true;
		}
		// Check dynamic option (from WP.org install flow)
		$dynamic_token = get_option(AC_SERIAL_OPT_ORG_TOKEN_DYNAMIC);
		if (!empty($dynamic_token)) {
			return true;
		}
		return false;
	}

	/**
	 * Get the effective org_token from constant or dynamic option
	 *
	 * @return string|null
	 * @since 2.0.7
	 */
	private function get_org_token()
	{
		if (strpos(AC_SERIAL_ORG_TOKEN, '%%') === false && !empty(AC_SERIAL_ORG_TOKEN)) {
			return AC_SERIAL_ORG_TOKEN;
		}
		$dynamic_token = get_option(AC_SERIAL_OPT_ORG_TOKEN_DYNAMIC);
		if (!empty($dynamic_token)) {
			return $dynamic_token;
		}
		return null;
	}

	/**
	 * Check if already registered with LicenceBot
	 *
	 * @return bool
	 * @since 2.0.7
	 */
	private function is_registered()
	{
		return !empty(get_option(AC_SERIAL_OPT_STORE_ID));
	}

	/**
	 * Get webhook endpoint URL for LicenceBot
	 *
	 * @return string
	 * @since 2.0.7
	 */
	private function get_webhook_url()
	{
		return rest_url('ac-serial-numbers/v1/order/update/');
	}

	/**
	 * Get store name from WordPress
	 *
	 * @return string
	 * @since 2.0.7
	 */
	private function get_store_name()
	{
		$name = get_bloginfo('name');
		if (empty($name)) {
			$name = parse_url(home_url(), PHP_URL_HOST);
		}
		return $name;
	}

	/**
	 * Get LicenceBot connection status message
	 * Used for admin UI display
	 *
	 * @return array
	 * @since 2.0.7
	 */
	public function get_licencebot_connection_status()
	{
		$status = array(
			'connected' => false,
			'store_id' => '',
			'registered_at' => '',
			'last_error' => '',
			'auto_connect_enabled' => $this->is_auto_connect_enabled(),
		);

		$store_id = get_option(AC_SERIAL_OPT_STORE_ID);
		if ($store_id) {
			$status['connected'] = true;
			$status['store_id'] = $store_id;

			$registered_at = get_option(AC_SERIAL_OPT_REGISTERED_AT);
			if ($registered_at) {
				$status['registered_at'] = date_i18n(
					get_option('date_format') . ' ' . get_option('time_format'),
					$registered_at
				);
			}
		}

		$last_error = get_option(AC_SERIAL_OPT_LAST_ERROR);
		if ($last_error) {
			$status['last_error'] = $last_error;
		}

		return $status;
	}

	/**
	 * =========================================================================
	 * LICENCEBOT REGISTRATION METHOD
	 * =========================================================================
	 */

	/**
	 * Register this store with LicenceBot
	 * Called on plugin activation and via admin retry button
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 * @since 2.0.7
	 */
	public function register_with_licencebot()
	{
		// Skip if auto-connect not configured (org_token still placeholder)
		// This means manual entry mode - do nothing
		if (!$this->is_auto_connect_enabled()) {
			return true;
		}

		// Skip if already registered (unless forced)
		if ($this->is_registered() && !defined('AC_SERIAL_FORCE_REGISTER')) {
			return true;
		}

		// Clear any previous errors
		delete_option(AC_SERIAL_OPT_LAST_ERROR);

		// Get/generate webhook secret (existing method)
		$webhook_secret = $this->webhook_secret();
		$webhook_url = $this->get_webhook_url();

		// Build registration payload
		$payload = array(
			'org_token' => $this->get_org_token(),
			'store_url' => home_url(),
			'store_name' => $this->get_store_name(),
			'plugin_version' => $this->version,
			'plugin_source' => 'licencebot-helper',
			'webhook_secret' => $webhook_secret,
			'webhook_url' => $webhook_url,
		);

		// Optional: Generate WooCommerce REST API keys like licencebot-helper does
		// Uncomment if needed for your integration
		// list($ck, $cs) = $this->ensure_woocommerce_keys();
		// $payload['consumer_key'] = $ck;
		// $payload['consumer_secret'] = $cs;

		// Send registration request
		$response = wp_remote_post(AC_SERIAL_REGISTRATION_URL, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode($payload),
			'timeout' => 30,
			'data_format' => 'body',
		));

		// Handle network errors
		if (is_wp_error($response)) {
			$error_msg = 'Network Error: ' . $response->get_error_message();
			update_option(AC_SERIAL_OPT_LAST_ERROR, $error_msg);
			error_log('LicenceBot Registration Failed: ' . $error_msg);
			return new WP_Error('network_error', $error_msg);
		}

		// Parse response
		$response_code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		// Handle non-200 responses
		if ($response_code < 200 || $response_code >= 300) {
			$error_msg = isset($body['error']) ? $body['error'] :
				'HTTP Error ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
			update_option(AC_SERIAL_OPT_LAST_ERROR, $error_msg);
			error_log('LicenceBot Registration Failed (HTTP ' . $response_code . '): ' . $error_msg);
			return new WP_Error('http_error_' . $response_code, $error_msg);
		}

		// Check success flag
		if (!isset($body['success']) || !$body['success']) {
			$error_msg = isset($body['error']) ? $body['error'] : 'Registration failed: Unknown error';
			update_option(AC_SERIAL_OPT_LAST_ERROR, $error_msg);
			error_log('LicenceBot Registration Failed: ' . $error_msg);
			return new WP_Error('registration_failed', $error_msg);
		}

		// ========== SUCCESS! Save credentials ==========

		// Save store identification
		if (!empty($body['store_id'])) {
			update_option(AC_SERIAL_OPT_STORE_ID, $body['store_id']);
		}
		if (!empty($body['store_token'])) {
			update_option(AC_SERIAL_OPT_STORE_TOKEN, $body['store_token']);
		}

		// Save API credentials for Serial Numbers plugin (THESE ARE KEY!)
		// Only update if received - don't overwrite manual settings if not provided
		if (!empty($body['api_endpoint'])) {
			update_option('ac_serial_numbers_api_endpoint', $body['api_endpoint']);
		}
		if (!empty($body['api_key'])) {
			update_option('ac_serial_numbers_api_key', $body['api_key']);
		}

		// Save webhook auth secret from LicenceBot (used for X-Webhook-Secret validation)
		if (!empty($body['license_auth_secret'])) {
			update_option(AC_SERIAL_OPT_AUTH_SECRET, $body['license_auth_secret']);
		}

		// Save registration timestamp
		update_option(AC_SERIAL_OPT_REGISTERED_AT, current_time('timestamp'));

		// Clear any cached product data
		delete_transient(AC_SERIAL_NUMBER_REMOTE_TRANSIENT);

		// Success!
		return true;
	}

	/**
	 * =========================================================================
	 * LICENCEBOT WORDPRESS.ORG SETUP TOKEN EXCHANGE
	 * =========================================================================
	 */

	/**
	 * Handle setup token from WordPress.org install flow
	 * LicenceBot redirects user to:
	 *   /wp-admin/admin.php?page=licencebot-helper&lb_action=connect&lb_token=xxx
	 * This method exchanges the token for org credentials via
	 * POST /functions/v1/complete-helper-setup
	 *
	 * @since 2.0.7
	 */
	public function handle_licencebot_setup_token()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Only process if our params are present
		if (!isset($_GET['lb_action']) || $_GET['lb_action'] !== 'connect') {
			return;
		}
		if (empty($_GET['lb_token'])) {
			return;
		}

		$token = sanitize_text_field($_GET['lb_token']);

		// Exchange token for org credentials
		$response = wp_remote_post(AC_SERIAL_COMPLETE_SETUP_URL, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode(array(
				'token' => $token,
			)),
			'timeout' => 30,
		));

		// Handle network errors
		if (is_wp_error($response)) {
			$redirect_url = add_query_arg(
				array('ac_serial_error' => '1'),
				admin_url('admin.php?page=ac-serial-numbers-settings')
			);
			wp_redirect($redirect_url);
			exit;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		// Handle error responses
		if ($response_code < 200 || $response_code >= 300 || empty($body['success'])) {
			$error_msg = isset($body['error']) ? $body['error'] : 'Setup failed. Please try again from LicenceBot dashboard.';
			update_option(AC_SERIAL_OPT_LAST_ERROR, $error_msg);

			$redirect_url = add_query_arg(
				array('ac_serial_error' => '1'),
				admin_url('admin.php?page=ac-serial-numbers-settings')
			);
			wp_redirect($redirect_url);
			exit;
		}

		// ========== SUCCESS! Save org_token + credentials ==========

		// Save the org_token dynamically
		if (!empty($body['org_token'])) {
			update_option(AC_SERIAL_OPT_ORG_TOKEN_DYNAMIC, $body['org_token']);
		}

		// Save API credentials if provided
		if (!empty($body['api_endpoint'])) {
			update_option('ac_serial_numbers_api_endpoint', $body['api_endpoint']);
		}
		if (!empty($body['api_key'])) {
			update_option('ac_serial_numbers_api_key', $body['api_key']);
		}

		// Store pre-existing store info if returned
		if (!empty($body['store_id'])) {
			update_option(AC_SERIAL_OPT_STORE_ID, $body['store_id']);
		}
		if (!empty($body['store_token'])) {
			update_option(AC_SERIAL_OPT_STORE_TOKEN, $body['store_token']);
		}

		// Save webhook auth secret from LicenceBot
		if (!empty($body['license_auth_secret'])) {
			update_option(AC_SERIAL_OPT_AUTH_SECRET, $body['license_auth_secret']);
		}

		// Now run the full registration
		$result = $this->register_with_licencebot();

		// Redirect with status
		$redirect_url = add_query_arg(
			!is_wp_error($result) ? array('ac_serial_connected' => '1') : array('ac_serial_error' => '1'),
			admin_url('admin.php?page=ac-serial-numbers-settings')
		);
		wp_redirect($redirect_url);
		exit;
	}

	/**
	 * =========================================================================
	 * LICENCEBOT FRONTEND SCRIPT
	 * =========================================================================
	 */

	/**
	 * Enqueue LicenceBot helper script on frontend pages
	 * Enables: Live chat, cart abandonment tracking, live traffic, etc.
	 * Controlled from LicenceBot dashboard
	 *
	 * @since 2.0.7
	 */
	public function enqueue_licencebot_helper()
	{
		// Don't enqueue on admin pages
		if (is_admin()) {
			return;
		}

		// Check if registered with LicenceBot
		$store_id = get_option(AC_SERIAL_OPT_STORE_ID);
		$store_token = get_option(AC_SERIAL_OPT_STORE_TOKEN);

		if (empty($store_id)) {
			return;
		}

		// Build script URL
		$script_url = AC_SERIAL_HELPER_SCRIPT_URL . '?store_id=' . rawurlencode($store_id);
		if (!empty($store_token)) {
			$script_url .= '&t=' . rawurlencode($store_token);
		}

		// Enqueue the script
		// true = load in footer (before </body>)
		// Change to false to load in header (<head>)
		wp_enqueue_script(
			'licencebot-helper',
			$script_url,
			array(),
			null,
			true
		);
	}

	/**
	 * =========================================================================
	 * LICENCEBOT ADMIN ACTIONS & NOTICES
	 * =========================================================================
	 */

	/**
	 * Handle admin actions like "Re-connect to LicenceBot"
	 *
	 * @since 2.0.7
	 */
	public function handle_admin_actions()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Handle connect action (from manual mode button)
		if (isset($_GET['ac_serial_connect']) && check_admin_referer('ac_serial_connect')) {
			// Check org token validity first
			$org_token = $this->get_org_token();
			if (empty($org_token) || $org_token === 'null') {
				$msg = __('LicenceBot org token is missing. Please download the plugin from the LicenceBot Dashboard or enter the token manually.', 'ac-serial-numbers');
				update_option(AC_SERIAL_OPT_LAST_ERROR, $msg);
				$redirect_url = add_query_arg(
					array('ac_serial_error' => '1'),
					admin_url('admin.php?page=ac-serial-numbers-settings')
				);
				wp_redirect($redirect_url);
				exit;
			}

			// Ensure webhook secret exists
			$this->webhook_secret();

			// Clear previous errors
			delete_option(AC_SERIAL_OPT_LAST_ERROR);

			// Attempt registration with LicenceBot
			$result = $this->register_with_licencebot();

			// Redirect with status
			$redirect_url = add_query_arg(
				!is_wp_error($result) ? array('ac_serial_connected' => '1') : array('ac_serial_error' => '1'),
				admin_url('admin.php?page=ac-serial-numbers-settings')
			);

			wp_redirect($redirect_url);
			exit;
		}

		// Handle re-connect action
		if (isset($_GET['ac_serial_reconnect']) && check_admin_referer('ac_serial_reconnect')) {
			// Clear stored values
			delete_option(AC_SERIAL_OPT_STORE_ID);
			delete_option(AC_SERIAL_OPT_STORE_TOKEN);
			delete_option(AC_SERIAL_OPT_REGISTERED_AT);
			delete_option(AC_SERIAL_OPT_LAST_ERROR);

			// Force re-registration
			if (!defined('AC_SERIAL_FORCE_REGISTER')) {
				define('AC_SERIAL_FORCE_REGISTER', true);
			}

			$result = $this->register_with_licencebot();

			// Redirect with status
			$redirect_url = add_query_arg(
				!is_wp_error($result) ? array('ac_serial_connected' => '1') : array('ac_serial_error' => '1'),
				admin_url('admin.php?page=ac-serial-numbers-settings')
			);

			wp_redirect($redirect_url);
			exit;
		}

		// Handle disconnect action
		if (isset($_GET['ac_serial_disconnect']) && check_admin_referer('ac_serial_disconnect')) {
			// Clear LicenceBot connection values
			delete_option(AC_SERIAL_OPT_STORE_ID);
			delete_option(AC_SERIAL_OPT_STORE_TOKEN);
			delete_option(AC_SERIAL_OPT_REGISTERED_AT);
			delete_option(AC_SERIAL_OPT_LAST_ERROR);

			// Note: We do NOT delete ac_serial_numbers_api_endpoint and ac_serial_numbers_api_key
			// This allows manual entry to still work as fallback

			// Redirect
			$redirect_url = add_query_arg(
				array('ac_serial_disconnected' => '1'),
				admin_url('admin.php?page=ac-serial-numbers-settings')
			);

			wp_redirect($redirect_url);
			exit;
		}
	}

	/**
	 * Display admin notices for LicenceBot connection status
	 *
	 * @since 2.0.7
	 */
	public function admin_notices()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Only show on relevant admin pages
		$screen = get_current_screen();
		if (!$screen || (strpos($screen->id, 'ac-serial-numbers') === false && $screen->id !== 'plugins')) {
			return;
		}

		// Success notice (after connection)
		if (isset($_GET['ac_serial_connected'])) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>✅ Connected to LicenceBot Successfully!</strong></p>';
			$store_id = get_option(AC_SERIAL_OPT_STORE_ID);
			if ($store_id) {
				echo '<p>Store ID: <code>' . esc_html($store_id) . '</code></p>';
			}
			echo '<p>API Endpoint and API Key have been auto-configured.</p>';
			echo '</div>';
		}

		// Disconnected notice
		if (isset($_GET['ac_serial_disconnected'])) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>⚠️ Disconnected from LicenceBot</strong></p>';
			echo '<p>You can still manually enter API Endpoint and API Key in the settings below.</p>';
			echo '</div>';
		}

		// Error notice
		if (isset($_GET['ac_serial_error']) || get_option(AC_SERIAL_OPT_LAST_ERROR)) {
			$last_error = get_option(AC_SERIAL_OPT_LAST_ERROR);
			echo '<div class="notice notice-error">';
			echo '<p><strong>⚠️ Failed to Connect to LicenceBot</strong></p>';
			if ($last_error) {
				echo '<p>Error: ' . esc_html($last_error) . '</p>';
			}

			// Only show retry button if auto-connect is configured
			if ($this->is_auto_connect_enabled()) {
				$retry_url = wp_nonce_url(
					add_query_arg('ac_serial_reconnect', '1', admin_url()),
					'ac_serial_reconnect'
				);
				echo '<p><a href="' . esc_url($retry_url) . '" class="button button-primary">Retry Connection</a></p>';
			} else {
				echo '<p><em>Auto-connect is not configured. Download the plugin from your LicenceBot Dashboard or manually enter API credentials below.</em></p>';
			}

			echo '</div>';
		}

		// Notice when auto-connect is available but not yet connected
		if (
			$this->is_auto_connect_enabled() &&
			!$this->is_registered() &&
			!get_option(AC_SERIAL_OPT_LAST_ERROR)
		) {
			echo '<div class="notice notice-info">';
			echo '<p><strong>🔌 LicenceBot Auto-Connect is Available</strong></p>';
			echo '<p>This plugin can automatically connect to your LicenceBot account.</p>';

			$connect_url = wp_nonce_url(
				add_query_arg('ac_serial_reconnect', '1', admin_url()),
				'ac_serial_reconnect'
			);
			echo '<p><a href="' . esc_url($connect_url) . '" class="button button-primary">Connect Now</a></p>';
			echo '</div>';
		}
	}

	/**
	 * =========================================================================
	 * END OF LICENCEBOT AUTO-CONNECT METHODS
	 * =========================================================================
	 */

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function deactivate_plugin()
	{

	}

	/**
	 * Load the plugin when WooCommerce loaded.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function init_plugin()
	{
		$this->includes();
		$this->init_hooks();
	}


	/**
	 * Include required core files used in admin and on the frontend.
	 * @since 1.2.0
	 */
	public function includes()
	{
		require_once dirname(__FILE__) . '/includes/ac-serial-numbers-functions.php';
		require_once dirname(__FILE__) . '/includes/ac-serial-numbers-misc-functions.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-query.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-installer.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-order-handler.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-encryption.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-ajax.php';
		// require_once dirname( __FILE__ ) . '/includes/class-ac-serial-numbers-api.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-cron.php';
		// require_once dirname( __FILE__ ) . '/includes/class-ac-serial-numbers-compat.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-numbers-webhook.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-order-data.php';
		require_once dirname(__FILE__) . '/includes/class-ac-serial-order-tracking.php';

		if (is_admin()) {
			require_once dirname(__FILE__) . '/includes/admin/class-ac-serial-numbers-admin.php';
		}

		require_once dirname(__FILE__) . '/includes/ac-serial-numbers-helper-features.php';
		require_once dirname(__FILE__) . '/includes/ac-serial-numbers-chat-widget.php';
		do_action('ac_serial_numbers__loaded');
		// add_action('rest_api_init', array($this, 'products_ids_route'));
		// add_action('rest_api_init', array($this, 'products_updated_route'));
	}

	public $namespace = 'wc/v1';

	public function products_ids_route()
	{
		register_rest_route($this->namespace, '/products/ids/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'products_ids_callback'),
			'permission_callback' => array($this, 'get_items_permissions_check'),
		));
	}

	/**
	 * Products updated route definition.
	 */
	public function products_updated_route()
	{
		register_rest_route($this->namespace, '/products/updated/', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array($this, 'products_updated_callback'),
			'permission_callback' => array($this, 'get_items_permissions_check'),
			'args' => array(
				'context' => array(
					'description' => 'Scope under which the request is made; determines fields present in response.',
					'type' => 'string',
					'enum' => array('view', 'edit'),
					'default' => 'view',
					'required' => false,
				),
				'page' => array(
					'description' => 'Current page of the collection.',
					'type' => 'integer',
					'default' => 1,
					'minimum' => 1,
					'required' => false,
				),
				'per_page' => array(
					'description' => 'Maximum number of items to be returned in result set.',
					'type' => 'integer',
					'default' => 10,
					'minimum' => 1,
					'maximum' => 100,
					'required' => false,
				),
				'after' => array(
					'description' => 'Limit response to resources published after a given ISO8601 compliant date.',
					'type' => 'string',
					'format' => 'date-time',
					'required' => false,
				),
				'before' => array(
					'description' => 'Limit response to resources published before a given ISO8601 compliant date.',
					'type' => 'string',
					'format' => 'date-time',
					'required' => false,
				),
				'orderby' => array(
					'description' => 'Sort collection by object attribute.',
					'type' => 'string',
					'default' => 'date',
					'enum' => array('date', 'id', 'title', 'slug', 'modified'),
					'required' => false,
				),
			)
		));
	}

	public $post_type = 'product';

	public function products_ids_callback()
	{
		/**
		 * Get products.
		 */
		$products = new WP_Query(array(
			'post_type' => $this->post_type,
			'posts_per_page' => -1,
			'post_status' => 'any',
			'fields' => 'ids',
		));

		/*
		 * No products.
		 */
		if (!$products->have_posts()) {
			return false;
		}

		/**
		 * Prepare response.
		 */
		$data = array(
			'count' => $products->post_count,
			'ids' => $products->posts,
		);

		/**
		 * Response.
		 */
		$response = rest_ensure_response($data);
		$response->set_status(200);

		return $response;
	}


	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks()
	{
		add_action('plugins_loaded', array($this, 'localization_setup'));
		//add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), - 1 );
	}


	/**
	 * When WP has loaded all plugins, trigger the `ac_serial_numbers__loaded` hook.
	 *
	 * This ensures `ac_serial_numbers__loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded()
	{
		do_action('ac_serial_numbers__loaded');
	}

	public function update_check()
	{
		require_once 'vendor/plugin-update-checker/plugin-update-checker.php';
		$update_url = 'https://yiczembsfiqqviqxxdxl.supabase.co/functions/v1/plugin-update-check';
		$checker = PucFactory::buildUpdateChecker(
			$update_url,
			__FILE__,
			'ac-serial-numbers'
		);
		$checker->addQueryArgFilter(function ($query) {
			$query['plugin_slug'] = 'ac-serial-numbers';
			return $query;
		});
	}
}


/**
 * The main function responsible for returning the one true WC Serial Numbers
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @return AC_Serial_Numbers
 * @since 1.2.0
 */
function ac_serial_numbers()
{
	return AC_Serial_Numbers::init();
}

//lets go.
ac_serial_numbers();

function acsn_write_log(...$data)
{
	if (true === WP_DEBUG) {

		// Get GMT+6 time
		$datetime = new DateTime("now", new DateTimeZone("Asia/Dhaka"));
		$formatted_time = $datetime->format("[d-M-Y H:i:s]");

		$backtrace = debug_backtrace();

		$backtrace = array_shift($backtrace);

		$output['backtrace'] = $backtrace['file'] . ':' . $backtrace['line'];
		foreach ($data as $key => $value) {
			$output['data' . ($key + 1)] = $value;
		}
		error_log($formatted_time . " " . print_r($output, true));
	}
}

function ac_fetch_products_data()
{
	global $wp;
	$transient_name = AC_SERIAL_NUMBER_REMOTE_TRANSIENT; // Name of your transient
	$transient_timeout = 1 * DAY_IN_SECONDS; // 1 day timeout

	// Check if the transient already exists and is not expired.
	$product_data = get_transient($transient_name);
	if ($product_data === false) {
		$url = get_option('ac_serial_numbers_api_endpoint');
		$api_key = get_option('ac_serial_numbers_api_key');

		if (empty($url) || empty($api_key)) {
			error_log("API Endpoint or Key is missing. Check your settings.");
			$wp->query_vars['acsn_error'] = 'api_key_or_endpoint_missing';
			$wp->query_vars['acsn_error_code'] = 101;
			return false;
		}

		$response = wp_remote_get( $url . '/product/stocks-all', array(
			'headers' => ac_serial_numbers_get_api_headers(),
		) );
		if (is_wp_error($response)) {
			error_log("Error fetching data from API: " . $response->get_error_message());
			$wp->query_vars['acsn_error'] = $response->get_error_message();
			$wp->query_vars['acsn_error_code'] = 102;
			return false;
		}

		$data = wp_remote_retrieve_body($response);
		$decoded_data = json_decode($data, true);
		if ($decoded_data === null) {
			error_log("Error decoding JSON: " . json_last_error_msg());

			$wp->query_vars['acsn_error'] = 'json_decode_error';
			$wp->query_vars['acsn_error_code'] = 104;
			return false;
		}

		if (!empty($decoded_data) && isset($decoded_data['success']) && $decoded_data['success'] == true) { // Check if data is not empty
			set_transient($transient_name, $decoded_data['products'], $transient_timeout);
			return $decoded_data['products'];
		} else {
			error_log("No product data was retrieved from the API.");
			$wp->query_vars['acsn_error'] = isset($decoded_data['message']) ? $decoded_data['message'] : 'No product data was retrieved from the API.';
			$wp->query_vars['acsn_error_code'] = 105;

		}
	}
	return $product_data;
}