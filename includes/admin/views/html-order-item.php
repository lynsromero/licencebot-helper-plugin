<?php
/**
 * Shows an order item
 *
 * @package WooCommerce\Admin
 * @var WC_Order_Item $item The item being displayed
 * @var int $item_id The id of the item being displayed
 */

defined( 'ABSPATH' ) || exit;

$product      = $item->get_product();
$product_link = $product ? admin_url( 'post.php?post=' . $item->get_product_id() . '&action=edit' ) : '';
$thumbnail    = $product ? apply_filters( 'woocommerce_admin_order_item_thumbnail', $product->get_image( 'thumbnail', array( 'title' => '' ), false ), $item_id, $item ) : '';
$row_class    = apply_filters( 'woocommerce_admin_html_order_item_class', ! empty( $class ) ? $class : '', $item, $order );
$remote_products_data = empty(get_post_meta($item->get_order_id(), '_ac_remote_product_'. $item->get_id(), true)) 
                        ? get_post_meta($item->get_product_id(), '_ac_remote_product', true) 
                        : get_post_meta($item->get_order_id(), '_ac_remote_product_'. $item->get_id(), true);
$key_source_type = empty(get_post_meta( $item->get_order_id(), '_ac_serial_numbers_key_source_'. $item->get_id(), true )) 
                    ? get_post_meta( $item->get_product_id(), '_ac_serial_numbers_key_source', true )
                    : get_post_meta( $item->get_order_id(), '_ac_serial_numbers_key_source_'. $item->get_id(), true );
?>
<tr class="item <?php echo esc_attr( $row_class ); ?>" data-order_item_id="<?php echo esc_attr( $item_id ); ?>"
	data-order_id="<?php echo esc_attr($item->get_order_id()); ?>"
	data-remote_products_data="<?php echo esc_attr($remote_products_data); ?>">
	<td class="thumb">
		<?php echo '<div class="wc-order-item-thumbnail">' . wp_kses_post( $thumbnail ) . '</div>'; ?>
	</td>
	<td class="name" data-sort-value="<?php echo esc_attr( $item->get_name() ); ?>">
		<?php
		echo $product_link ? '<a href="' . esc_url( $product_link ) . '" class="wc-order-item-name">' . wp_kses_post( $item->get_name() ) . '</a>' : '<div class="wc-order-item-name">' . wp_kses_post( $item->get_name() ) . '</div>';

		if ( $product && $product->get_sku() ) {
			echo '<div class="wc-order-item-sku"><strong>' . esc_html__( 'SKU:', 'woocommerce' ) . '</strong> ' . esc_html( $product->get_sku() ) . '</div>';
		}

		if ( $item->get_variation_id() ) {
			echo '<div class="wc-order-item-variation"><strong>' . esc_html__( 'Variation ID:', 'woocommerce' ) . '</strong> ';
			if ( 'product_variation' === get_post_type( $item->get_variation_id() ) ) {
				echo esc_html( $item->get_variation_id() );
			} else {
				/* translators: %s: variation id */
				printf( esc_html__( '%s (No longer exists)', 'woocommerce' ), esc_html( $item->get_variation_id() ) );
			}
			echo '</div>';
		}
		?>
		<input type="hidden" class="order_item_id" name="order_item_id[]" value="<?php echo esc_attr( $item_id ); ?>" />
		<input type="hidden" name="order_item_tax_class[<?php echo absint( $item_id ); ?>]" value="<?php echo esc_attr( $item->get_tax_class() ); ?>" />

		<?php do_action( 'woocommerce_before_order_itemmeta', $item_id, $item, $product ); ?>
		<?php require __DIR__ . '/html-order-item-meta.php'; ?>
		<?php do_action( 'woocommerce_after_order_itemmeta', $item_id, $item, $product ); ?>
	</td>

	<?php do_action( 'woocommerce_admin_order_item_values', $product, $item, absint( $item_id ) ); ?>

	<td>
		<select name="productsLista" id=""
			class="regular-text ac-serial-numbers-map-product-shop-order" required="required"
			placeholder="<?php _e( 'Select Product', 'ac-serial-numbers' ); ?>"
			multiple="multiple">                      
			<option value="">Select a Product</option>
		</select>
	</td>
	<td class="text-center font-weight-bold">
		<select name="keysource" class="keysource_order_page">
			<option value="custom_source" <?php echo selected( 'custom_source', $key_source_type ); ?>>System</option>
			<option value="reseller" <?php echo selected( 'reseller', $key_source_type ); ?>>Reseller</option>
		</select>
	</td>

	<td class="wc-order-edit-line-item" width="1%">
		<div class="wc-order-edit-line-item-actions">
			<?php if ( $order->is_editable() ) : ?>
				<a class="edit-order-item tips" href="#" data-tip="<?php esc_attr_e( 'Edit item', 'woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Edit item', 'woocommerce' ); ?>"></a><a class="delete-order-item tips" href="#" data-tip="<?php esc_attr_e( 'Delete item', 'woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Delete item', 'woocommerce' ); ?>"></a>
			<?php endif; ?>
		</div>
	</td>
</tr>
