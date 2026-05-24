<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_View_Log {

	public static function insert( $data ) {
		global $wpdb;

		$defaults = array(
			'serial_id'     => 0,
			'order_id'      => 0,
			'product_id'    => 0,
			'product_title' => '',
			'serial_key'    => '',
			'ip_address'    => '',
			'viewed_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'serial_view_log',
			array(
				'serial_id'     => absint( $data['serial_id'] ),
				'order_id'      => absint( $data['order_id'] ),
				'product_id'    => absint( $data['product_id'] ),
				'product_title' => sanitize_text_field( $data['product_title'] ),
				'serial_key'    => sanitize_textarea_field( $data['serial_key'] ),
				'ip_address'    => sanitize_text_field( $data['ip_address'] ),
				'viewed_at'     => $data['viewed_at'],
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	public static function get_results( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'DESC',
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->prefix . 'serial_view_log';
		$where = '1=1';

		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= $wpdb->prepare(
				' AND ( product_title LIKE %s OR serial_key LIKE %s OR ip_address LIKE %s )',
				$search,
				$search,
				$search
			);
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'id DESC';
		}

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d",
				$args['per_page'],
				$offset
			)
		);
	}

	public static function count_total( $search = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'serial_view_log';
		$where = '1=1';

		if ( ! empty( $search ) ) {
			$search = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare(
				' AND ( product_title LIKE %s OR serial_key LIKE %s OR ip_address LIKE %s )',
				$search,
				$search,
				$search
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}
}
