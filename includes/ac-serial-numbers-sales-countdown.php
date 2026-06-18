<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_SALES_COUNTDOWN_ENABLED',    'licencebot_sales_countdown_enabled' );
define( 'AC_SERIAL_SALES_COUNTDOWN_CODE',       'licencebot_sales_countdown_html' );
define( 'AC_SERIAL_SALES_COUNTDOWN_WIDGET_ID',  'licencebot_sales_countdown_id' );
define( 'AC_SERIAL_SALES_COUNTDOWN_FETCHED_AT', 'licencebot_sales_countdown_fetched_at' );
define( 'AC_SERIAL_SALES_COUNTDOWN_API',        'helper-sales-countdown' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'sales_countdown', array(
		'title'            => __( 'Sales Countdown Timer', 'ac-serial-numbers' ),
		'description'      => __( 'Countdown timer bar auto-shown on every page.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_SALES_COUNTDOWN_ENABLED,
		'code_option'      => AC_SERIAL_SALES_COUNTDOWN_CODE,
		'widget_id_option' => AC_SERIAL_SALES_COUNTDOWN_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_SALES_COUNTDOWN_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_SALES_COUNTDOWN_API,
	) );
}, 5 );
