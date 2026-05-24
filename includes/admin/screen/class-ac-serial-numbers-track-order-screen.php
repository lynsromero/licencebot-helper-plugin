<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Track_Order_Screen {

	public static function output() {
		require_once dirname( __DIR__ ) . '/tables/class-ac-serial-numbers-view-log-table.php';
		$list_table = new AC_Serial_Numbers_View_Log_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Track Order Status', 'ac-serial-numbers' ); ?></h1>
			<hr class="wp-header-end">
			<form method="get">
				<div class="serials-table">
					<?php $list_table->search_box( __( 'Search', 'ac-serial-numbers' ), 'view-log' ); ?>
					<input type="hidden" name="page" value="ac-serial-numbers-track-order"/>
					<?php $list_table->display() ?>
				</div>
			</form>
		</div>
		<?php
	}
}
