<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class AC_Serial_Numbers_View_Log_List_Table extends \WP_List_Table {

	public $per_page = 20;

	public $total_count;

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'View Log', 'ac-serial-numbers' ),
			'plural'   => __( 'View Logs', 'ac-serial-numbers' ),
			'ajax'     => false,
		) );
	}

	protected function get_table_classes() {
		return array( 'widefat', 'striped', $this->_args['plural'] );
	}

	function prepare_items() {
		$per_page              = $this->per_page;
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$data = $this->get_results();

		$total_items = $this->total_count;

		$this->items = $data;

		$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>"/>
			<?php submit_button( $text, 'button', false, false, array( 'ID' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	function get_columns() {
		$columns = array(
			'id'            => __( 'ID', 'ac-serial-numbers' ),
			'product_title' => __( 'Product Title', 'ac-serial-numbers' ),
			'serial_key'    => __( 'Licence View', 'ac-serial-numbers' ),
			'ip_address'    => __( 'IP', 'ac-serial-numbers' ),
			'viewed_at'     => __( 'Time', 'ac-serial-numbers' ),
		);

		return apply_filters( 'ac_serial_numbers_view_log_columns', $columns );
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'id'            => array( 'id', false ),
			'product_title' => array( 'product_title', false ),
			'ip_address'    => array( 'ip_address', false ),
			'viewed_at'     => array( 'viewed_at', false ),
		);

		return apply_filters( 'ac_serial_numbers_view_log_sortable_columns', $sortable_columns );
	}

	protected function get_primary_column_name() {
		return 'id';
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return $item->id;
			case 'product_title':
				return esc_html( $item->product_title );
			case 'serial_key':
				return '<code>' . esc_html( $item->serial_key ) . '</code>';
			case 'ip_address':
				return esc_html( $item->ip_address );
			case 'viewed_at':
				return $item->viewed_at;
			default:
				return '&mdash;';
		}
	}

	public function get_results() {
		$per_page = $this->per_page;
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'id';
		$order    = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		$args = array(
			'per_page' => $per_page,
			'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
			'orderby'  => $orderby,
			'order'    => $order,
			'search'   => $search,
		);

		$this->total_count = AC_Serial_Numbers_View_Log::count_total( $search );

		return AC_Serial_Numbers_View_Log::get_results( $args );
	}
}
