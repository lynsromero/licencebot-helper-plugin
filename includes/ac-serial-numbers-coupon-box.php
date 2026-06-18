<?php
defined( 'ABSPATH' ) || exit();

define( 'AC_SERIAL_COUPON_BOX_ENABLED',    'licencebot_coupon_box_enabled' );
define( 'AC_SERIAL_COUPON_BOX_CODE',       'licencebot_coupon_box_html' );
define( 'AC_SERIAL_COUPON_BOX_WIDGET_ID',  'licencebot_coupon_box_id' );
define( 'AC_SERIAL_COUPON_BOX_FETCHED_AT', 'licencebot_coupon_box_fetched_at' );
define( 'AC_SERIAL_COUPON_BOX_API',        'helper-coupon-box' );

add_action( 'init', function() {
	AC_Serial_Numbers_Helper_Features::register( 'coupon_box', array(
		'title'            => __( 'Coupon Box', 'ac-serial-numbers' ),
		'description'      => __( 'Exit-intent / timed coupon popup on every page.', 'ac-serial-numbers' ),
		'enabled_option'   => AC_SERIAL_COUPON_BOX_ENABLED,
		'code_option'      => AC_SERIAL_COUPON_BOX_CODE,
		'widget_id_option' => AC_SERIAL_COUPON_BOX_WIDGET_ID,
		'fetched_at_option'=> AC_SERIAL_COUPON_BOX_FETCHED_AT,
		'api_endpoint'     => AC_SERIAL_COUPON_BOX_API,
	) );
}, 5 );
