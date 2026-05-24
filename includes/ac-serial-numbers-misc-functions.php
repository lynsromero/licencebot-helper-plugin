<?php
defined( 'ABSPATH' ) || exit();
/**
 * Get product title.
 *
 * @since 1.2.0
 *
 * @param $product
 *
 * @return string
 */
function ac_serial_numbers_get_product_title( $product ) {
	if ( ! empty( $product ) ) {
		$product = wc_get_product( $product );
	}
	if ( $product && ! empty( $product->get_id() ) ) {
		return sprintf(
			'(#%1$s) %2$s',
			$product->get_id(),
			html_entity_decode( $product->get_formatted_name() )
		);
	}

	return '';
}


/**
 * Get Low stock products.
 *
 * @since 1.0.0
 *
 * @param int $stock
 *
 * @return array
 */
// function ac_serial_numbers_get_low_stock_products5( $force = false, $stock = 10 ) {
// 	$transient = md5( 'wcsn_low_stock_products' . $stock );
// 	if ( $force || false == $low_stocks = get_transient( $transient ) ) {
// 		global $wpdb;
// 		$product_ids   = $wpdb->get_results( "select post_id, 0 as count from $wpdb->postmeta where meta_key='_is_serial_number' AND meta_value='yes'" );
// 		/*
// 		$serial_counts = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, count(id) as count FROM {$wpdb->prefix}serial_numbers where status='available' AND product_id IN (select post_id from $wpdb->postmeta where meta_key='_is_serial_number' AND meta_value='yes')
// 																group by product_id having count < %d order by count asc", $stock ) );
// 		*/
// 		// skip private products for serial number notification count
// 		$serial_counts = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, count(id) as count FROM {$wpdb->prefix}serial_numbers where status='available' AND product_id IN (select meta.post_id from $wpdb->postmeta AS meta LEFT JOIN $wpdb->posts AS posts on posts.ID=meta.post_id where posts.post_status='publish' AND meta.meta_key='_is_serial_number' AND meta.meta_value='yes')
// 																group by product_id having count < %d order by count asc", $stock ) );
																

// 		$serial_counts = wp_list_pluck( $serial_counts, 'count', 'product_id' );


// 		$low_stocks = $serial_counts;
// 		set_transient( $transient, $low_stocks, time() + 60 * 20 );
// 	}

// 	return $low_stocks;
// }

function ac_serial_numbers_get_low_stock_products( $force = false, $stock = 10 ) {
	$transient = md5( 'wcsn_low_stock_products' . $stock );
	$low_stocks = get_transient( $transient );
	if ( $force || false == $low_stocks ) {
		global $wpdb;
		
		$serial_counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.post_id AS product_id, 
			   COALESCE(COUNT(sn.id), 0) AS count
			FROM (
				SELECT DISTINCT meta.post_id 
				FROM {$wpdb->postmeta} AS meta
				INNER JOIN {$wpdb->posts} AS posts 
				ON posts.ID = meta.post_id 
				WHERE posts.post_status = 'publish' 
				AND meta.meta_key = '_is_serial_number' 
				AND meta.meta_value = 'yes'
			) AS p
			LEFT JOIN {$wpdb->prefix}serial_numbers AS sn
			ON p.post_id = sn.product_id AND sn.status = 'available'
			GROUP BY p.post_id
			HAVING count < %d
			ORDER BY count ASC",
			$stock ) );
			
		$serial_counts = wp_list_pluck( $serial_counts, 'count', 'product_id' );
		$low_stocks = $serial_counts;
		set_transient( $transient, $low_stocks, time() + 60 * 20 );
	}
	return $low_stocks;
}

/**
 * Get order table.
 *
 * @since 1.2.0
 *
 * @param bool $return
 *
 * @param      $order
 *
 * @return false|string|void
 */
function ac_serial_numbers_get_order_table( $order, $return = false ) {
	$order_id = $order->get_id();
	if ( 'completed' !== $order->get_status( 'edit' ) ) {
		return;
	}

	//no serial numbers ordered so bail @since 1.2.1
	$total_ordered_serial_numbers = ac_serial_numbers_order_has_serial_numbers( $order );

	if ( empty( $total_ordered_serial_numbers ) ) {
		return;
	}

	$serial_numbers = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( 'order_id', intval( $order_id ) )->get();

	echo sprintf( '<h2 class="woocommerce-order-downloads__title">%s</h2>', apply_filters( 'ac_serial_numbers_order_table_heading', esc_html__( "Serial Numbers", 'ac-serial-numbers' ) ) );
	if ( empty( $serial_numbers ) ) {
		echo sprintf( '<p>%s</p>', apply_filters( 'ac_serial_numbers_pending_notice', __( 'Order waiting for assigning serial numbers.', 'ac-serial-numbers' ) ) );

		return;
	}

	ob_start();
	$columns  = ac_serial_numbers_get_order_table_columns();
	$order_key = $order->get_order_key();
	$nonce     = wp_create_nonce( 'ac_serial_numbers_view_license' );
	?>
	<table
		class="woocommerce-table woocommerce-table--order-details shop_table order_details ac-serial-numbers-order-items"
		style="width: 100%; margin-bottom: 40px;"
		cellspacing="0" cellpadding="6" border="1">
		<thead>
		<tr>
			<?php foreach ( $columns as $key => $label ) {
				echo sprintf( '<th class="td %s" scope="col" style="text-align:left;">%s</th>', sanitize_html_class( $key ), $label );
			} ?>
		</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $serial_numbers as $serial_number ) {
			echo '<tr>';
			foreach ( $columns as $key => $label ) {
				echo '<td class="td" style="text-align:left;">';
				switch ( $key ) {
					case 'product':
						echo sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $serial_number->product_id ) ), get_the_title( $serial_number->product_id ) );
						break;
					case 'serial_key':
						$btn_serial_id   = absint( $serial_number->id );
						$btn_product_id  = absint( $serial_number->product_id );
						$btn_product_title = esc_attr( get_the_title( $serial_number->product_id ) );
						printf(
							'<button class="ac-sn-see-license button" data-serial-id="%d" data-order-id="%d" data-product-id="%d" data-product-title="%s" data-order-key="%s" data-nonce="%s">%s</button>',
							$btn_serial_id,
							$order_id,
							$btn_product_id,
							$btn_product_title,
							esc_attr( $order_key ),
							esc_attr( $nonce ),
							esc_html__( 'See Your License Key', 'ac-serial-numbers' )
						);
						break;
					case 'activation_email':
						echo $order->get_billing_email();
						break;
					case 'activation_limit':
						if ( empty( $serial_number->activation_limit ) ) {
							echo __( 'Unlimited', 'ac-serial-numbers' );
						} else {
							echo $serial_number->activation_limit;
						}
						break;
					case 'expire_date':
						if ( empty( $serial_number->validity ) ) {
							echo __( 'Lifetime', 'ac-serial-numbers' );
						} else {
							echo date( 'Y-m-d', strtotime( $serial_number->order_date . ' + ' . $serial_number->validity . ' Day ' ) );
						}
						break;

					default:
						do_action( 'ac_serial_numbers_order_table_cell_content', $key, $serial_number, $order_id );
				}
				echo '</td>';
			}
			echo '</tr>';
		} ?>

		</tbody>
	</table>
	<?php
	$output = ob_get_contents();
	ob_get_clean();
	if ( $return ) {
		return $output;
	}

	echo $output;
}
