<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_NEWSLETTER_POPUP_ENABLED',    'licencebot_newsletter_popup_enabled' );
define( 'AC_SERIAL_NEWSLETTER_POPUP_CODE',       'licencebot_newsletter_popup_html' );
define( 'AC_SERIAL_NEWSLETTER_POPUP_WIDGET_ID',  'licencebot_newsletter_popup_id' );
define( 'AC_SERIAL_NEWSLETTER_POPUP_FETCHED_AT', 'licencebot_newsletter_popup_fetched_at' );
define( 'AC_SERIAL_NEWSLETTER_POPUP_API',        'helper-newsletter-popup' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'newsletter_popup', array(
		'title'            => __( 'Newsletter Popup', 'ac-serial-numbers' ),
		'description'      => __( 'Display a newsletter popup on your store with exit-intent, scroll, and time-delay triggers.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_NEWSLETTER_POPUP_ENABLED,
		'code_option'      => AC_SERIAL_NEWSLETTER_POPUP_CODE,
		'widget_id_option' => AC_SERIAL_NEWSLETTER_POPUP_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_NEWSLETTER_POPUP_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_NEWSLETTER_POPUP_API,
	) );
}, 5 );
