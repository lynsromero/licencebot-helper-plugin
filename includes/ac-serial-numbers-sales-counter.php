<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_SALES_COUNTER_ENABLED',    'licencebot_sales_counter_enabled' );
define( 'AC_SERIAL_SALES_COUNTER_CODE',       'licencebot_sales_counter_html' );
define( 'AC_SERIAL_SALES_COUNTER_WIDGET_ID',  'licencebot_sales_counter_id' );
define( 'AC_SERIAL_SALES_COUNTER_FETCHED_AT', 'licencebot_sales_counter_fetched_at' );
define( 'AC_SERIAL_SALES_COUNTER_API',        'helper-sales-counter' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'sales_counter', array(
		'title'            => __( 'Sales Counter', 'ac-serial-numbers' ),
		'description'      => __( 'Live sales counter auto-injected on product pages.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_SALES_COUNTER_ENABLED,
		'code_option'      => AC_SERIAL_SALES_COUNTER_CODE,
		'widget_id_option' => AC_SERIAL_SALES_COUNTER_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_SALES_COUNTER_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_SALES_COUNTER_API,
	) );
}, 5 );
