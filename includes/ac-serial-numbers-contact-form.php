<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_CONTACT_FORM_ENABLED',    'licencebot_contact_form_enabled' );
define( 'AC_SERIAL_CONTACT_FORM_CODE',       'licencebot_contact_form_html' );
define( 'AC_SERIAL_CONTACT_FORM_WIDGET_ID',  'licencebot_contact_form_id' );
define( 'AC_SERIAL_CONTACT_FORM_FETCHED_AT', 'licencebot_contact_form_fetched_at' );
define( 'AC_SERIAL_CONTACT_FORM_API',        'helper-contact-form' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'contact_form', array(
		'title'            => __( 'Inline Contact Form', 'ac-serial-numbers' ),
		'description'      => __( 'Display a contact/support form on your store. Configure fields and styling from your LicenceBot Dashboard.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_CONTACT_FORM_ENABLED,
		'code_option'      => AC_SERIAL_CONTACT_FORM_CODE,
		'widget_id_option' => AC_SERIAL_CONTACT_FORM_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_CONTACT_FORM_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_CONTACT_FORM_API,
	));
}, 5 );
