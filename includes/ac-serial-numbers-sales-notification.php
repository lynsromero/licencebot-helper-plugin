<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_SALES_NOTIFICATION_ENABLED',    'licencebot_sales_notification_enabled' );
define( 'AC_SERIAL_SALES_NOTIFICATION_CODE',       'licencebot_sales_notification_html' );
define( 'AC_SERIAL_SALES_NOTIFICATION_WIDGET_ID',  'licencebot_sales_notification_id' );
define( 'AC_SERIAL_SALES_NOTIFICATION_FETCHED_AT', 'licencebot_sales_notification_fetched_at' );
define( 'AC_SERIAL_SALES_NOTIFICATION_API',        'helper-sales-notification' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'sales_notification', array(
		'title'            => __( 'Sales Notification', 'ac-serial-numbers' ),
		'description'      => __( 'Floating sales notification toaster showing recent purchases.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_SALES_NOTIFICATION_ENABLED,
		'code_option'      => AC_SERIAL_SALES_NOTIFICATION_CODE,
		'widget_id_option' => AC_SERIAL_SALES_NOTIFICATION_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_SALES_NOTIFICATION_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_SALES_NOTIFICATION_API,
	) );
}, 5 );
