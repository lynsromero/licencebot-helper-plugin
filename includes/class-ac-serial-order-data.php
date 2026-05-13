<?php
class AC_Serial_Order_Data {
    public array $cart = [];
	public $order = null;
	public ?int $customer_id = null;
	public ?string $email = null;
	public ?string $name = null;
	public ?string $phone = null;
	public ?string $type = null;

	public array $allowed_keys = array(
		'_is_serial_number',
		'_ac_serial_numbers_key_source',
		'_ac_remote_product_id',
		'_ac_remote_product',
		'total_sales',
		'_manage_stock',
		'_backorders',
		'_sold_individually',
		'_virtual',
		'_downloadable',
		'_download_limit',
		'_download_expiry',
		'_stock',
		'_stock_status',
		'_wc_average_rating',
		'_wc_review_count',
		'_product_version',
		'_regular_price',
		'_sale_price',
		'_price',
	);

    public function __construct( $order, $type = 'new' ) {
		$this->order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		$this->type = $type;
	}	

    public function to_array() {		
		$payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		$data = [
			'invoice_no'                   => $this->order->get_id(),
			'started_at'                   => strtotime( $this->order->get_date_created() ), // utc timestamp
			'total'                        => (float) $this->order->get_total(),
			'subtotal'                     => (float) $this->order->get_subtotal(), // Requires custom calculation, see below
			'total_tax'                    => (float) $this->order->get_total_tax(),
			'total_discount'               => (float) $this->order->get_discount_total(),
			'total_shipping'               => (float) $this->order->get_shipping_total(),
			'total_fee'                    => $this->get_order_fee_total(), // See helper function below
			'currency'                     => $this->order->get_currency(),
			'customer'					   => array(
				'customer_id'               => $this->order->get_customer_id(),
				'email'                     => $this->order->get_billing_email(),
				'first_name'                => $this->order->get_billing_first_name(),
				'last_name'                 => $this->order->get_billing_last_name(),
				'company'					=> $this->order->get_billing_company(),
				'city'						=> $this->order->get_billing_city(),
				'state'						=> $this->order->get_billing_state(),
				'country'					=> $this->order->get_billing_country(),
				'postcode'					=> $this->order->get_billing_postcode(),
				'phone'                     => $this->order->get_billing_phone(),
			),
			'order_note'                      => $this->order->get_customer_note(),
			'locale'                       => get_locale(), // Not saved in order usually
			// 'email_opt_out' => depends on custom implementation
			// 'client_session' => N/A
			'display_prices_including_tax' => wc_prices_include_tax(),
			'status'					   => $this->order->get_status(),
			'active_payment_method'		   => array_keys($payment_gateways),
			'payment_method'        	   => $this->order->get_payment_method(),
			'payment_method_title'  	   => $this->order->get_payment_method_title(),
			'items'						   => $this->get_items($this->order),
		];
		acsn_write_log('sending order data to key server', $data);
		return apply_filters( 'ac_serial_number_order_data', $data );
	}

	public function get_order_fee_total() {
		$total_fee = 0;
		foreach ( $this->order->get_fees() as $fee ) {
			$total_fee += $fee->get_total();
		}
		return $total_fee;
	}

	public function get_items($order) {
		$order_items = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			// $product = $item->get_product();
			
		
			$order_items[] = [
				'item_id'       => $item_id,
				'product_id'    => $item->get_product_id(),
				'variation_id'  => $item->get_variation_id(),
				'name'          => $item->get_name(),
				'quantity'      => $item->get_quantity(),
				'subtotal'      => $item->get_subtotal(),
				'total'         => $item->get_total(),
				'tax'           => $item->get_total_tax(),
				'meta_data'     => $this->get_order_item_custom_meta( $item ),
			];
		}

		return $order_items;
	}

	public function get_allowed_keys() {
		return apply_filters( 'ac_serial_order_data_allowed_meta_keys', $this->allowed_keys );
	}

	public function get_order_item_custom_meta( $item ) {

		$product_id = $item->get_product_id();
		if ( ! $product_id ) {
			return [];
		}
		$meta = get_post_meta( $product_id );

		$formatted_meta = [];
		foreach ( $meta as $key => $value ) {
			if (in_array($key, $this->get_allowed_keys())) {
				$formatted_meta[ $key ] = count( $value ) === 1 ? $value[0] : $value;
            }
		}

		$remote_product = get_post_meta($item->get_order_id(), "_ac_remote_product_" . $item->get_id(), true);
		$source = get_post_meta($item->get_order_id(), "_ac_serial_numbers_key_source_" . $item->get_id(), true);
		
		if($this->type == 'new'){
			if ( isset( $formatted_meta['_ac_remote_product_id'] ) && ! isset( $formatted_meta['_ac_remote_product'] ) ) {
				$data_arr = [
					[
						"id" => get_post_meta($product_id, '_ac_remote_product_id', true),
						"text" => $item->get_name()
					]
				];
				$formatted_meta['_ac_remote_product'] = json_encode($data_arr);
			}
			if( isset( $formatted_meta['_ac_remote_product_id'] ) ){
				update_post_meta($item->get_order_id(), "_ac_remote_product_id", $formatted_meta['_ac_remote_product_id'] );
			}
			if( isset( $formatted_meta['_ac_remote_product'] ) && !$remote_product ){
				update_post_meta($item->get_order_id(), "_ac_remote_product_" . $item->get_id(), $formatted_meta['_ac_remote_product'] );
			}
			if( isset( $formatted_meta['_ac_serial_numbers_key_source'] ) && !$source ){
				update_post_meta($item->get_order_id(), "_ac_serial_numbers_key_source_" . $item->get_id(), $formatted_meta['_ac_serial_numbers_key_source'] );
			}
		}else{
			$formatted_meta['_ac_remote_product'] = $remote_product;
			$formatted_meta['_ac_serial_numbers_key_source'] = $source;
		}

		return $formatted_meta;
		
	}

}