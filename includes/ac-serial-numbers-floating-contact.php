<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_FLOATING_CONTACT_ENABLED',    'licencebot_floating_contact_enabled' );
define( 'AC_SERIAL_FLOATING_CONTACT_CODE',       'licencebot_floating_contact_html' );
define( 'AC_SERIAL_FLOATING_CONTACT_WIDGET_ID',  'licencebot_floating_contact_id' );
define( 'AC_SERIAL_FLOATING_CONTACT_FETCHED_AT', 'licencebot_floating_contact_fetched_at' );
define( 'AC_SERIAL_FLOATING_CONTACT_API',        'helper-floating-contact' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'floating_contact', array(
		'title'            => __( 'Floating Contact Form', 'ac-serial-numbers' ),
		'description'      => __( 'Display a floating contact bubble on your store. Visitors can open a slide-up form to send support messages.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_FLOATING_CONTACT_ENABLED,
		'code_option'      => AC_SERIAL_FLOATING_CONTACT_CODE,
		'widget_id_option' => AC_SERIAL_FLOATING_CONTACT_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_FLOATING_CONTACT_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_FLOATING_CONTACT_API,
	) );
}, 5 );
