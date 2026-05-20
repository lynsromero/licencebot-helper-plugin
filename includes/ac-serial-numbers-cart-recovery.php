<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_CART_RECOVERY_ENABLED', 'licencebot_cart_recovery_enabled' );
define( 'AC_SERIAL_CART_RECOVERY_API', 'woo-cart-webhook' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'cart_recovery', array(
		'title'            => __( 'Cart Recovery', 'ac-serial-numbers' ),
		'description'      => __( 'Track abandoned carts and send recovery emails. Configure from your LicenceBot Dashboard.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_CART_RECOVERY_ENABLED,
		'code_option'      => '',
		'widget_id_option' => '',
		'fetched_at_option'=> '',
		'api_endpoint'     => AC_SERIAL_CART_RECOVERY_API,
	));
}, 5 );
