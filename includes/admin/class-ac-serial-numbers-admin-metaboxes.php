<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin_MetaBoxes {

	/**
	 * AC_Serial_Numbers_Admin_MetaBoxes constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ) );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_write_panel' ) );
		add_filter( 'woocommerce_process_product_meta', array( __CLASS__, 'product_save_data' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variable_product_content' ), 10, 3 );
		//add_action( 'woocommerce_after_order_itemmeta', array( $this, 'order_itemmeta' ), 10, 3 );
	}

	/**
	 * Register metaboxes.
	 *
	 * @since 1.2.5
	 */
	public static function register_metaboxes() {
		add_meta_box( 'order-lines-serial-numbers', __( 'Order Lines', 'ac-serial-numbers' ), array( __CLASS__, 'order_lines_metabox' ), 'shop_order', 'advanced', 'high' );
		add_meta_box( 'order-serial-numbers', __( 'Serial Numbers', 'ac-serial-numbers' ), array( __CLASS__, 'order_metabox' ), 'shop_order', 'advanced', 'high' );
	}

	/**
	 * product
	 * since 1.0.0
	 */
	public static function product_data_tab( $tabs ) {
		$tabs['ac_serial_numbers'] = array(
			'label'    => __( 'Serial Numbers', 'ac-serial-numbers' ),
			'target'   => 'ac_serial_numbers_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 11
		);

		return $tabs;
	}

	/**
	 * since 1.0.0
	 */
	public static function product_write_panel() {
		global $post, $woocommerce;
		?>
		<div id="ac_serial_numbers_data" class="panel woocommerce_options_panel show_if_simple"
			 style="padding-bottom: 50px;display: none;">
			<?php
			woocommerce_wp_checkbox(
				array(
					'id'            => '_is_serial_number',
					'label'         => __( 'Sell Serial Numbers', 'ac-serial-numbers' ),
					'description'   => __( 'Enable this if you are selling serial numbers for this product.', 'ac-serial-numbers' ),
					'value'         => get_post_meta( $post->ID, '_is_serial_number', true ),
					'wrapper_class' => 'options_group',
					'desc_tip'      => true,
				)
			);
			$remote_title = get_post_meta( $post->ID, '_ac_remote_product_title', true );
			woocommerce_wp_hidden_input(
				array(
					'id'            => '_ac_remote_product_title',
					'value'			=> $remote_title,
				)
			);

			$option_data = array();
			$remote_id = get_post_meta( $post->ID, '_ac_remote_product_id', true );
			if( $remote_id ) {
				$option_data[ $remote_id ] = $remote_title;
			}else{
				$option_data[''] = __('Select a product', 'ac-serial-numbers');
			}

			woocommerce_wp_select(
				array(
					'id'            => '_ac_remote_product_id',
					'label'         => __( 'Assign Product', 'ac-serial-numbers' ),
					'description'   => __( 'Assign remote product with this product.', 'ac-serial-numbers' ),
					'placeholder'   => __( 'Select a product', 'ac-serial-numbers' ),
					'wrapper_class' => 'options_group',
					'desc_tip'      => true,
					'options'		=> $option_data,
					'value'			=> get_post_meta( $post->ID, '_ac_remote_product_id', true ),
				),					
			);

			$delivery_quantity = (int) get_post_meta( $post->ID, '_delivery_quantity', true );
			woocommerce_wp_text_input( apply_filters( 'ac_serial_numbers_delivery_quantity_field_args', array(
				'id'                => '_delivery_quantity',
				'label'             => __( 'Delivery quantity', 'ac-serial-numbers' ),
				'description'       => __( 'The number of serial key will be automatically generated and delivered per item.', 'ac-serial-numbers' ),
				'value'             => empty( $delivery_quantity ) ? 1 : $delivery_quantity,
				'type'              => 'number',
				'wrapper_class'     => 'options_group',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'disabled' => 'disabled'
				),
			) ) );

			$source  = get_post_meta( $post->ID, '_ac_serial_numbers_key_source', true );
			$sources = ac_serial_numbers_get_key_sources();
			woocommerce_wp_radio( array(
				'id'            => "_ac_serial_numbers_key_source",
				'name'          => "_ac_serial_numbers_key_source",
				'class'         => "serial_key_source",
				'label'         => __( 'Serial Key Source', 'ac-serial-numbers' ),
				'value'         => empty( $source ) ? 'custom_source' : $source,
				'wrapper_class' => 'options_group',
				'options'       => $sources,
			) );

			foreach ( array_keys( $sources ) as $key_source ) {
				do_action( 'ac_serial_numbers_source_settings_' . $key_source, $post->ID );
				do_action( 'ac_serial_numbers_source_settings', $key_source, $post->ID );
			}


			do_action( 'ac_serial_numbers_simple_product_metabox', $post );

			if ( 'system' == $source ) {
				echo sprintf(
					'<p class="form-field options_group"><label>%s</label><span class="description">%d %s</span></p>',
					__( 'Available', 'ac-serial-numbers' ),
					ac_serial_numbers_get_stock_quantity($post->ID),
					__( 'Serial Number available for sale', 'ac-serial-numbers' )
				);
			}
			
			?>
		</div>
		<?php
	}

	/**
	 * Show promo box.
	 *
	 * @since 1.2.0
	 *
	 * @param $variation_data
	 * @param $variation
	 *
	 * @param $loop
	 */
	public static function variable_product_content( $loop, $variation_data, $variation ) {
		if ( ! ac_serial_numbers()->is_pro_active() ) {
			echo sprintf( '<p class="ac-serial-numbers-upgrade-box">%s <a href="%s" target="_blank" class="button">%s</a></p>', __( 'WooCommerce Serial Number Free version does not support product variation.', 'ac-serial-numbers' ), 'https://tic.com.bd/plugins/woocommerce-serial-numbers-pro/?utm_source=product_page_license_area&utm_medium=link&utm_campaign=ac-serial-numbers&utm_content=Upgrade%20to%20Pro', __( 'Upgrade to Pro', 'ac-serial-numbers' ) );
		}

	}

	/**
	 * since 1.0.0
	 */
	public static function product_save_data() {
		global $post;
		$status = isset( $_POST['_is_serial_number'] ) ? 'yes' : 'no';
		$source = isset( $_POST['_ac_serial_numbers_key_source'] ) ? sanitize_text_field( $_POST['_ac_serial_numbers_key_source'] ) : 'custom_source';
		update_post_meta( $post->ID, '_is_serial_number', $status );
		update_post_meta( $post->ID, '_ac_serial_numbers_key_source', $source );

		if(isset($_POST['_ac_remote_product_id']) && !empty($_POST['_ac_remote_product_id'])){
			update_post_meta( $post->ID, '_ac_remote_product_id', sanitize_text_field( $_POST['_ac_remote_product_id'] ) );
		}
		if(isset($_POST['_ac_remote_product_title']) && !empty($_POST['_ac_remote_product_title'])){
			update_post_meta( $post->ID, '_ac_remote_product_title', sanitize_text_field( $_POST['_ac_remote_product_title'] ) );
		}
		do_action( 'wcsn_save_simple_product_meta', $post );
	}


	/**
	 *
	 * @since 1.1.6
	 *
	 * @param $o_item
	 * @param $product
	 *
	 * @param $o_item_id
	 *
	 * @return bool|string
	 */
	public function order_itemmeta( $o_item_id, $o_item, $product ) {
		global $post;
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return false;
		}

		$order = wc_get_order( $post->ID );

		// bail for no order
		if ( ! $order ) {
			return false;
		}

		if ( 'completed' !== $order->get_status( 'edit' ) ) {
			return '';
		}

		//if this is not product then no need to process
		if ( empty( $product ) ) {
			return false;
		}

		$is_serial_product = 'yes' == get_post_meta( $product->get_id(), '_is_serial_number', true );

		if ( ! $is_serial_product ) {
			return false;
		}

		$items = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( [
			'order_id'   => $post->ID,
			'product_id' => $product->get_id(),
		] )->get();

		if ( empty( $items ) && $order ) {
			echo sprintf( '<div class="wcsn-missing-serial-number">%s</div>', __( 'Order missing serial numbers for this item.', 'ac-serial-numbers' ) );

			return true;
		}

		$url = admin_url( 'admin.php?page=ac-serial-numbers' );
		echo sprintf( '<br/><a href="%s">%s&rarr;</a>', add_query_arg( [
			'order_id'   => $post->ID,
			'product_id' => $product->get_id()
		], $url ), __( 'Serial Numbers', 'ac-serial-numbers' ) );

		$url = admin_url( 'admin.php?page=ac-serial-numbers' );

		$li = '';

		foreach ( $items as $item ) {
			$li .= sprintf( '<li><a href="%s">&rarr;</a>&nbsp;%s</li>', add_query_arg( [
				'action' => 'edit',
				'id'     => $item->id
			], $url ), ac_serial_numbers_decrypt_key( $item->serial_key ) );
		}

		echo sprintf( '<ul>%s</ul>', $li );
	}

	/**
	 * Render order metabox.
	 *
	 * The metabox shows all ordered serial numbers.
	 *
	 * @since 1.2.6
	 *
	 * @param $post
	 *
	 * @return bool
	 */
	public static function order_metabox( $post ) {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return false;
		}
		$order = wc_get_order( $post->ID );

		// bail for no order
		if ( ! $order ) {
			return false;
		}

		if ( 'completed' !== $order->get_status( 'edit' ) ) {
			echo sprintf( '<p>%s</p>', __( 'Order status is not completed.', 'ac-serial-numbers' ) );

			return false;
		}

		$total_ordered_serial_numbers = ac_serial_numbers_order_has_serial_numbers( $order );
		if ( empty( $total_ordered_serial_numbers ) ) {
			echo sprintf( '<p>%s</p>', __( 'No serial numbers associated with the order.', 'ac-serial-numbers' ) );

			return false;
		}

		$serial_numbers = AC_Serial_Numbers_Query::init()->from( 'serial_numbers' )->where( 'order_id', intval( $order->get_id() ) )->get();

		if ( empty( $serial_numbers ) ) {
			echo sprintf( '<p>%s</p>', apply_filters( 'ac_serial_numbers_pending_notice', __( 'Order waiting for assigning serial numbers.', 'ac-serial-numbers' ) ) );

			return false;
		}

		do_action( 'ac_serial_numbers_order_table_top', $order, $serial_numbers );
		$columns = ac_serial_numbers_get_order_table_columns();

		?>
		<table class="widefat striped">
			<tbody>
			<tr>
				<?php foreach ( $columns as $key => $label ) {
					echo sprintf( '<th class="td %s" scope="col" style="text-align:left;">%s</th>', sanitize_html_class( $key ), $label );
				} ?>

				<th>
					<?php _e( 'Actions', 'ac-serial-numbers' ); ?>
				</th>
			</tr>

			<?php foreach ( $serial_numbers as $serial_number ): ?>
				<tr>
					<?php foreach ( $columns as $key => $column ): ?>
						<td class="td" style="text-align:left;">
							<?php
							switch ( $key ) {
								case 'product':
									echo sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $serial_number->product_id ) ), get_the_title( $serial_number->product_id ) );
									break;
								case 'serial_key':
									echo ac_serial_numbers_decrypt_key( $serial_number->serial_key );
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
									do_action( 'ac_serial_numbers_order_table_cell_content', $key, $serial_number, $order->get_id() );
							}
							?>

						</td>
					<?php endforeach; ?>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ac-serial-numbers&action=edit&id=' . $serial_number->id ) ) ?>"><?php _e( 'Edit', 'ac-serial-numbers' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		do_action( 'ac_serial_numbers_order_table_bottom', $order, $serial_numbers );

		return true;
	}


	public static function order_lines_metabox($post) {
		global $post, $thepostid, $theorder;

		OrderUtil::init_theorder_object( $post );
		if ( ! is_int( $thepostid ) && ( $post instanceof WP_Post ) ) {
			$thepostid = $post->ID;
		}

		$order = $theorder;
		$data  = ( $post instanceof WP_Post ) ? get_post_meta( $post->ID ) : array();

		include __DIR__ . '/views/html-order-items.php';
	}

}

new AC_Serial_Numbers_Admin_MetaBoxes();
