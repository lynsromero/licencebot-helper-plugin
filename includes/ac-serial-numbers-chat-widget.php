<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_CHAT_WIDGET_ENABLED',    'licencebot_chat_widget_enabled' );
define( 'AC_SERIAL_CHAT_WIDGET_CODE',       'licencebot_chat_widget_html' );
define( 'AC_SERIAL_CHAT_WIDGET_WIDGET_ID',  'licencebot_chat_widget_id' );
define( 'AC_SERIAL_CHAT_WIDGET_FETCHED_AT', 'licencebot_chat_widget_fetched_at' );
define( 'AC_SERIAL_CHAT_WIDGET_API',        'helper-chat-widget' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'chat_widget', array(
		'title'            => __( 'Floating Chat Widget', 'ac-serial-numbers' ),
		'description'      => __( 'Display a floating chat bubble on your store. Configure greeting, colors, and AI from your LicenceBot Dashboard.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_CHAT_WIDGET_ENABLED,
		'code_option'      => AC_SERIAL_CHAT_WIDGET_CODE,
		'widget_id_option' => AC_SERIAL_CHAT_WIDGET_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_CHAT_WIDGET_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_CHAT_WIDGET_API,
	));
}, 5 );
