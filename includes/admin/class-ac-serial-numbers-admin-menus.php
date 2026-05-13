<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin_Menus {

	/**
	 * AC_Serial_Numbers_Admin_Menus constructor.
	 */
	public function __construct() {
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'register_pages' ) );
		// add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	// public function load_scripts() {
	// 	wp_enqueue_script('ac-serial-numbers-admin', ac_serial_numbers()->plugin_url() . 'assets/js/admin.js', array('jquery'), ac_serial_numbers()->get_version(), true);
	// }
	/**
	 * Save screen options
	 *
	 * @param $status
	 * @param $option
	 * @param $value
	 *
	 * @return mixed
	 * @since 1.2.0
	 */
	public function save_screen_options( $status, $option, $value ) {
		if ( 'serials_per_page' == $option ) {
			return $value;
		}
	}

	/**
	 * Register pages.
	 * @since 1.2.0
	 */
	public function register_pages() {
		$role               = ac_serial_numbers_get_user_role();
		$serial_number_page = add_menu_page(
			__( 'Serial Keys', 'ac-serial-numbers' ),
			__( 'Serial Keys', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers',
			array( 'AC_Serial_Numbers_Admin_Screen', 'output' ),
			'dashicons-lock',
			'55.9'
		);

		add_submenu_page(
			'ac-serial-numbers',
			__( 'Serial Keys', 'ac-serial-numbers' ),
			__( 'Serial Keys', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers',
			array( 'AC_Serial_Numbers_Admin_Screen', 'output' )
		);
		
		add_submenu_page(
			'ac-serial-numbers',
			__( 'Stock Manager', 'ac-serial-numbers' ),
			__( 'Stock Manager', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers-stock-manager',
			array( 'AC_Serial_Numbers_Admin_Stock_Manager_Screen', 'output' )
		);
		
		add_submenu_page(
			'ac-serial-numbers',
			__( 'Add Volume License', 'ac-serial-numbers' ),
			__( 'Add Volume License', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers-add-volume-license',
			array( 'AC_Serial_Numbers_Admin_Add_Volume_License_Screen', 'output' )
		);

		add_submenu_page(
			'ac-serial-numbers',
			__( 'Activations', 'ac-serial-numbers' ),
			__( 'Activations', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers-activations',
			array( 'AC_Serial_Numbers_Admin_Activations_Screen', 'output' )
		);

		add_submenu_page(
			'ac-serial-numbers',
			__( 'Settings', 'ac-serial-numbers' ),
			__( 'Settings', 'ac-serial-numbers' ),
			$role,
			'ac-serial-numbers-settings',
			array( 'AC_Serial_Numbers_Admin_Settings', 'output' )
		);

		add_action( 'load-' . $serial_number_page, array( $this, 'load_serial_numbers_page' ) );
	}

	public function load_serial_numbers_page() {
		$args = array(
			'label'   => __( 'Serials per page', 'ac-serial-numbers' ),
			'default' => 20,
			'option'  => 'serials_per_page'
		);
		add_screen_option( 'per_page', $args );
		$status = "<ul>";
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Available', 'ac-serial-numbers' ), __( 'Serial Keys are valid and available for sell', 'ac-serial-numbers' ) );
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Sold', 'ac-serial-numbers' ), __( 'Serial Keys are sold and active', 'ac-serial-numbers' ) );
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Refunded', 'ac-serial-numbers' ), __( 'Serial Keys are sold then refunded', 'ac-serial-numbers' ) );
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Cancelled', 'ac-serial-numbers' ), __( 'Serial Keys are sold then cancelled', 'ac-serial-numbers' ) );
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Expired', 'ac-serial-numbers' ), __( 'Serial Keys are sold then expired', 'ac-serial-numbers' ) );
		$status .= sprintf( '<li><strong>%s</strong>: %s</li>', __( 'Inactive', 'ac-serial-numbers' ), __( 'Serial Keys are are npt available for sell ', 'ac-serial-numbers' ) );
		$status .= "</ul>";

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'status',
				'title'   => __( 'Statuses','ac-serial-numbers' ),
				'content' => $status,
			)
		);
	}

}

new AC_Serial_Numbers_Admin_Menus();
