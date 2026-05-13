<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin_Actions {
	public static function init() {
		add_action( 'admin_post_ac_serial_numbers_edit_serial_number', array( __CLASS__, 'edit_serial_number' ) );
		add_action( 'admin_post_ac_serial_numbers_save_volume_license', array( __CLASS__, 'save_volume_license' ) );
	}

	public static function edit_serial_number() {
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'edit_serial_number' ) ) {
			wp_die( 'No, Cheating!' );
		}

		$id     = ! empty( $_POST['id'] ) ? intval( $_POST['id'] ) : null;
		$posted = array(
			'id'          => ! empty( $_POST['id'] ) ? intval( $_POST['id'] ) : '',
			'serial_key'  => ! empty( $_POST['serial_key'] ) ? sanitize_textarea_field( $_POST['serial_key'] ) : '',
			'product_id'  => ! empty( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : '',
			'order_id'    => ! empty( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : '',
			'expire_date' => ! empty( $_POST['expire_date'] ) ? sanitize_text_field( $_POST['expire_date'] ) : '',
			'status'      => ! empty( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'available',
		);

		if ( ! ac_serial_numbers_software_support_disabled() ) {
			$posted['activation_limit'] = ! empty( $_POST['activation_limit'] ) ? intval( $_POST['activation_limit'] ) : '';
			$posted['validity'] = ! empty( $_POST['validity'] ) ? intval( $_POST['validity'] ) : '';
		}

		$created = ac_serial_numbers_insert_serial_number($posted);

		$redirect_args = array(
			'page'   => 'ac-serial-numbers',
			'action' => empty( $id ) ? 'add' : 'edit',
		);

		if ( ! empty( $id ) ) {
			$redirect_args['id'] = $id;
		}

		if ( is_wp_error( $created ) ) {
			AC_Serial_Numbers_Admin_Notice::add_notice( $created->get_error_message(), [ 'type' => 'error' ] );
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit();
		}


		AC_Serial_Numbers_Admin_Notice::add_notice( __( 'Serial Number saved successfully', 'ac-serial-numbers' ), [ 'type' => 'success' ] );
		wp_safe_redirect( add_query_arg( array( 'page' => $redirect_args['page'] ), admin_url( 'admin.php' ) ) );
		exit();
	}
	
	public static function save_volume_license(){
	    if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'volume_serial_number' ) ) {
			wp_die( 'No, Cheating!' );
		}
		
        $_support_email = get_option('ac_serial_numbers_support_email');
        
        
                                 
		$unique_text=["for any issue: $_support_email","for any support: $_support_email","for any help: $_support_email",
		    "for any kind of issue: $_support_email","for any kind of help: $_support_email","for support: $_support_email",
		    "Support: $_support_email","Help: $_support_email","for guide: $_support_email","for issue: $_support_email",
			"for any issues: $_support_email","for any supports: $_support_email","for any helps: $_support_email",
		    "for any kind of issues: $_support_email","for any kind of helps: $_support_email","for supports: $_support_email",
		    "Supports: $_support_email","Helps: $_support_email","for guides: $_support_email","for issues: $_support_email"];
		
		$total_user  = ! empty( $_POST['total_user'] ) ? sanitize_textarea_field( $_POST['total_user'] ) : '';
		$serial_key  = ! empty( $_POST['serial_key'] ) ? sanitize_textarea_field( $_POST['serial_key'] ) : '';
		$activation_guide  = ! empty( $_POST['activation_guide'] ) ? sanitize_textarea_field( $_POST['activation_guide'] ) : '';
		$activation_guide  = ! empty( $activation_guide ) ? ' | '.$activation_guide : '';
		$serial_key = explode(",",$serial_key);
		
		if( $total_user > 20 ){
		    AC_Serial_Numbers_Admin_Notice::add_notice( __( 'You can add up to 20 serial numbers at once!', 'ac-serial-numbers' ), [ 'type' => 'error' ] );
    		wp_redirect('admin.php?page=ac-serial-numbers-add-volume-license');
    		exit();
		}
		
		
    	for ($x = 0; $x < $total_user; $x++) {
    	    foreach ($serial_key as $value) {
    	       $unique_serial_key = $value.$activation_guide.' | '.$unique_text[$x];
        	   $posted = array(
        			'serial_key'  => $unique_serial_key,
        			'product_id'  => ! empty( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : '',
        			'expire_date' => ! empty( $_POST['expire_date'] ) ? sanitize_text_field( $_POST['expire_date'] ) : '',
        			'activation_limit' => ! empty( $_POST['activation_limit'] ) ? intval( $_POST['activation_limit'] ) : '',
        			'validity'         => ! empty( $_POST['validity'] ) ? intval( $_POST['validity'] ) : '',
        		);
      		    $created = ac_serial_numbers_insert_serial_number($posted);
    	        
            }
        }
		
		if ( is_wp_error( $created ) ) {
			AC_Serial_Numbers_Admin_Notice::add_notice( $created->get_error_message(), [ 'type' => 'error' ] );
			wp_redirect('admin.php?page=ac-serial-numbers-add-volume-license');
			exit();
		}
		
		AC_Serial_Numbers_Admin_Notice::add_notice( __( 'Serial Number saved successfully', 'ac-serial-numbers' ), [ 'type' => 'success' ] );
		wp_redirect('admin.php?page=ac-serial-numbers');
		exit();
	}
}

AC_Serial_Numbers_Admin_Actions::init();
