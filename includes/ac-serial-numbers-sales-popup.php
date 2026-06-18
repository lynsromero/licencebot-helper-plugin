<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_SALES_POPUP_ENABLED',    'licencebot_sales_popup_enabled' );
define( 'AC_SERIAL_SALES_POPUP_CODE',       'licencebot_sales_popup_html' );
define( 'AC_SERIAL_SALES_POPUP_WIDGET_ID',  'licencebot_sales_popup_id' );
define( 'AC_SERIAL_SALES_POPUP_FETCHED_AT', 'licencebot_sales_popup_fetched_at' );
define( 'AC_SERIAL_SALES_POPUP_API',        'helper-sales-popup' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'sales_popup', array(
		'title'            => __( 'Sales Popup', 'ac-serial-numbers' ),
		'description'      => __( 'Social-proof sales popup auto-shown on every page.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_SALES_POPUP_ENABLED,
		'code_option'      => AC_SERIAL_SALES_POPUP_CODE,
		'widget_id_option' => AC_SERIAL_SALES_POPUP_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_SALES_POPUP_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_SALES_POPUP_API,
	) );
}, 5 );
