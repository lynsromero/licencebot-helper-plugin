<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_NEWSLETTER_ENABLED',    'licencebot_newsletter_enabled' );
define( 'AC_SERIAL_NEWSLETTER_CODE',       'licencebot_newsletter_html' );
define( 'AC_SERIAL_NEWSLETTER_WIDGET_ID',  'licencebot_newsletter_id' );
define( 'AC_SERIAL_NEWSLETTER_FETCHED_AT', 'licencebot_newsletter_fetched_at' );
define( 'AC_SERIAL_NEWSLETTER_API',        'helper-newsletter' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'newsletter', array(
		'title'            => __( 'Newsletter Subscription', 'ac-serial-numbers' ),
		'description'      => __( 'Display a newsletter subscription form on your store. Configure styling and fields from your LicenceBot Dashboard.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_NEWSLETTER_ENABLED,
		'code_option'      => AC_SERIAL_NEWSLETTER_CODE,
		'widget_id_option' => AC_SERIAL_NEWSLETTER_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_NEWSLETTER_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_NEWSLETTER_API,
	));
}, 5 );
