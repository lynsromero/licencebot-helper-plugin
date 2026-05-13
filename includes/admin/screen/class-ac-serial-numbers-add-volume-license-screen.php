<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin_Add_Volume_License_Screen {
    
	/**
	 * Render serial number page.
	 *
	 * @since 1.2.0
	 */
	public static function output() {  
        	?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Add Volume License', 'ac-serial-numbers' ); ?></h1>
			<a href="<?php echo esc_url( 'admin.php?page=ac-serial-numbers' ); ?>" class="page-title-action">
				<?php _e( 'Back', 'ac-serial-numbers' ); ?>
            </a>
			<hr class="wp-header-end">
			<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>"style="max-width: 600px">
                <table class="form-table">
                      <tr>
                            <th scope="row">
                                <label for="total_user">
									<?php esc_html_e( 'How Many User', 'ac-serial-numbers' ); ?>
                                </label>
                            </th>
                            <td>
								<?php echo sprintf( '<input name="total_user" id="total_user" class="regular-text" type="number" value="%s"  autocomplete="off">',1 ); ?>
                                <p class="description"><?php esc_html_e( 'How many serial numbers do you want to add? You can add up to 20 serial numbers at once.', 'ac-serial-numbers' ); ?></p>
                            </td>
                        </tr>
                    <tr>
                        <th>
                            <label for="product_id">
								<?php esc_html_e( 'Product', 'ac-serial-numbers' ); ?>
                            </label>
                        </th>

                        <td>
                            <select name="product_id" id="product_id"
                                    class="regular-text ac-serial-numbers-select-product" required="required"
                                    placeholder="<?php _e( 'Select Product', 'ac-serial-numbers' ); ?>">
								<?php // echo sprintf( '<option value="%d" selected="selected">%s</option>', $item['product_id'], ac_serial_numbers_get_product_title( $item['product_id'] ) ); ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Select product to add serial number. NOTE: Free version does not support variation & subscription product.', 'ac-serial-numbers' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="serial_key">
								<?php esc_html_e( 'Serial Number', 'ac-serial-numbers' ); ?>
                            </label>
                        </th>

                        <td>
                            <textarea name="serial_key" id="serial_key" class="regular-text" required="required"
                                      placeholder="d555b5ae-d9a6-41cb-ae54-361427357382"></textarea>
                            <p class="description"><?php esc_html_e( 'Enter the serial number here. You can enter multiple serial numbers, in which case you have to enter "," (comma symbol) at the end of each serial number followed by another serial number.', 'ac-serial-numbers' ); ?></p>
                        </td>
                    </tr>
                     <tr>
                        <th scope="row">
                            <label for="serial_key">
								<?php esc_html_e( 'Activation Guide', 'ac-serial-numbers' ); ?>
                            </label>
                        </th>

                        <td>
                            <textarea name="activation_guide" id="serial_key" class="regular-text" 
                                      placeholder="eg. https://example.com/activation-guide "></textarea>
                            <p class="description"><?php esc_html_e( 'if you have activation guide then you can put here', 'ac-serial-numbers' ); ?></p>
                        </td>
                    </tr>

                        <tr>
                            <th scope="row">
                                <label for="activation_limit">
									<?php esc_html_e( 'Activation Limit', 'ac-serial-numbers' ); ?>
                                </label>
                            </th>
                            <td>
								<?php echo sprintf( '<input name="activation_limit" id="activation_limit" class="regular-text" type="number" value="%d" autocomplete="off">', 1 ); ?>
                                <p class="description"><?php esc_html_e( 'Maximum number of times the key can be used to activate the software. If the product is not software keep blank.', 'ac-serial-numbers' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="validity">
                                   
									<?php esc_html_e( 'Validity (days)', 'ac-serial-numbers' ); ?>
                                </label>
                            </th>
                            <td>
								<?php echo sprintf( '<input name="validity" id="validity" class="regular-text" type="number" value="%d">', ""); ?>
                                <p class="description"><?php esc_html_e( 'The number of days the key will be valid from the purchase date.', 'ac-serial-numbers' ); ?></p>
                            </td>
                        </tr>


                    <tr>
                        <th scope="row">
                            <label for="expire_date"><?php esc_html_e( 'Expires at', 'ac-serial-numbers' ); ?></label>
                        </th>
                        <td>
							<?php echo sprintf( '<input name="expire_date" id="expire_date" class="regular-text ac-serial-numbers-select-date" type="text" autocomplete="off" value="%s">', "" ); ?>
                            <p class="description"><?php esc_html_e( 'After this date the key will not be assigned with any order. Leave blank for no expire date.', 'ac-serial-numbers' ); ?></p>
                        </td>
                    </tr>


                    <tr>
                        <td></td>
                        <td>
                            <p class="submit">
                                <input type="hidden" name="action" value="ac_serial_numbers_save_volume_license">
								<?php wp_nonce_field( 'volume_serial_number'); ?>
								<?php submit_button( __( 'Add Serial Number', 'ac-serial-numbers' ) ); ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </form>
        
		</div>
		<?php
	}
	


}
