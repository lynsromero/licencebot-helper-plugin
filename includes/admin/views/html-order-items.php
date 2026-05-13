<?php
/**
 * Order items HTML for meta box.
 *
 * @package WooCommerce\Admin
 */

use Automattic\WooCommerce\Enums\OrderStatus;

defined( 'ABSPATH' ) || exit;

global $wpdb;

$payment_gateway     = wc_get_payment_gateway_by_order( $order );
$line_items          = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );
$discounts           = $order->get_items( 'discount' );
$line_items_fee      = $order->get_items( 'fee' );
$line_items_shipping = $order->get_items( 'shipping' );
// var_dump($line_items);

if ( wc_tax_enabled() ) {
	$order_taxes      = $order->get_taxes();
	$tax_classes      = WC_Tax::get_tax_classes();
	$classes_options  = wc_get_product_tax_class_options();
	$show_tax_columns = count( $order_taxes ) === 1;
}
?>
<div class="woocommerce_order_items_wrapper wc-order-items-editable">
	<table cellpadding="0" cellspacing="0" class="woocommerce_order_items">
		<thead>
			<tr>
				<th class="item sortable" colspan="2" data-sort="string-ins"><?php esc_html_e( 'Item', 'woocommerce' ); ?></th>
				<?php do_action( 'woocommerce_admin_order_item_headers', $order ); ?>
				<th class="item_cost sortable" data-sort="float"><?php esc_html_e( 'Remote Products', 'woocommerce' ); ?></th>
				<th class="item_cost sortable" data-sort="float"><?php esc_html_e( 'Source', 'woocommerce' ); ?></th>
				<!-- <th class="item_cost sortable" data-sort="float"><?php esc_html_e( 'Cost', 'woocommerce' ); ?></th>
				<th class="quantity sortable" data-sort="int"><?php esc_html_e( 'Qty', 'woocommerce' ); ?></th>
				<th class="line_cost sortable" data-sort="float"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th> -->
				
				<th class="wc-order-edit-line-item" width="1%">&nbsp;</th>
			</tr>
		</thead>
		<tbody id="order_line_items">
			<?php
			foreach ( $line_items as $item_id => $item ) {
				// var_dump($item);
				do_action( 'woocommerce_before_order_item_' . $item->get_type() . '_html', $item_id, $item, $order );

				include __DIR__ . '/html-order-item.php';

				do_action( 'woocommerce_order_item_' . $item->get_type() . '_html', $item_id, $item, $order );
			}
			do_action( 'woocommerce_admin_order_items_after_line_items', $order->get_id() );
			?>
		</tbody>
	</table>
</div>

<div class="wc-order-data-row wc-order-bulk-actions wc-order-data-row-toggle">
	<p class="add-items">
		<button type="button" id="request-keys-items" class="button" data-order_id="<?php echo esc_attr($order->get_id()); ?>"><?php esc_html_e( 'Request Keys', 'woocommerce' ); ?></button>
	</p>
</div>
