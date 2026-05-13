<?php
defined( 'ABSPATH' ) || exit();

class AC_Serial_Numbers_Admin_Stock_Manager_Screen {
	/**
	 * Render serial number page.
	 *
	 * @since 1.2.0
	 */
	public static function output() {
    global $wp;
    $uri = strpos($_SERVER['REQUEST_URI'], 'fix=yes') == 0 ? $_SERVER['REQUEST_URI'] . '&fix=yes' : $_SERVER['REQUEST_URI'];
    $count_incident = self::get_product_sync_status();

    $remote_products_transient_data = ac_fetch_products_data();
    $stocke_threshold = get_option( 'ac_serial_numbers_stock_threshold' );
    ?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Stock Manager', 'ac-serial-numbers' ); ?></h1>
      
      
      <?php if($count_incident > 0) { ?>
        <div>
          <p>There are total incidents of <span id="ac-serial-numbers-sync-product-count"><?php echo $count_incident; ?></span>
            <a href="<?php echo $uri; ?>">Fix Now</a>
          </p>
        </div>
      <?php } ?>
			<hr class="wp-header-end">
      <?php
      
      if(isset($wp->query_vars['acsn_error_code']) && $wp->query_vars['acsn_error']){

        $error_code = $wp->query_vars['acsn_error_code'];
        $error_message = $wp->query_vars['acsn_error'];
        ?>
        <div class="alert alert-danger">
          <p class="text-danger text-center mb-0"> Error code: <?php echo $error_code; ?> <?php echo $error_message; ?></p>
        </div>
        <?php
        
      }
      ?>
			<table id="ac_serial_numbers_stock_manager" class="table table-striped table-bordered" style="width:100%">
        <thead>
          <tr>
            <th scope="col">Product Name</th>
            <th scope="col">Remote Products</th>
            <th scope="col">Source</th>
            <th scope="col">Available</th>
            <th scope="col">Sold</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $args = array(
              'post_type'      => 'product',
              'posts_per_page' => -1,
              'order' => 'ASC',
            );
            $loop = new WP_Query( $args );
            while ( $loop->have_posts() ) : $loop->the_post();
              global $product;
              $id = $product->get_id();
              global $wpdb;
              $wpdb_prefix = $wpdb->prefix;
              $wpdb_tablename = $wpdb_prefix.'serial_numbers';
              $available = count( $wpdb->get_results("SELECT * FROM $wpdb_tablename WHERE product_id='$id' AND status='available'"));
              $sold =count( $wpdb->get_results("SELECT * FROM $wpdb_tablename WHERE product_id='$id' AND status='sold'"));
              
              $plink  = add_query_arg( [
                'product_id' => $id,
                'page'   => 'ac-serial-numbers'
              ], admin_url( 'admin.php' ) );

              $remote_products_data = get_post_meta($id, '_ac_remote_product', true);
              $remote_product_id = get_post_meta($id, '_ac_remote_product_id', true);
              $ids_to_match = [];
              if($remote_products_data){
                $ids_to_match = array_column(json_decode($remote_products_data), 'id');
              }
              
              if ( is_array( $remote_products_transient_data )){
                $matched_remote_product_data = array_filter($remote_products_transient_data, function ($product) use ($ids_to_match) {
                  return in_array($product['_id'], $ids_to_match);
                });

              }else{
                $matched_remote_product_data = [];
              }

              $matched_remote_product = $matched_remote_product_data;

              $wcsnsc_stock_status="text-dark";
              
              if($available==0){
                  $wcsnsc_stock_status="text-danger";
              }elseif($available < $stocke_threshold){
                  $wcsnsc_stock_status="text-warning";
              }

              $key_source_type = get_post_meta( $id, '_ac_serial_numbers_key_source', true );
              if ($key_source_type == 'reseller' && $remote_product_id && isset($matched_remote_product['availableKeys'])) {

                foreach ($matched_remote_product as $key => $value) {

                  if($value['availableKeys'] == 0){
                    $wcsnsc_stock_status = "text-danger";
                    break;
                  }elseif($value['availableKeys'] < $stocke_threshold){
                    $wcsnsc_stock_status = "text-warning";
                    break;
                  }else{
                    $wcsnsc_stock_status = "text-success";
                    break;
                  }
                  
                }
                
              }

              ?>
              <tr data-product_id="<?php echo esc_attr($id); ?>" 
                  data-remote_product_id="<?php echo esc_attr($remote_product_id); ?>"
                  data-remote_products_data="<?php echo esc_attr($remote_products_data); ?>">
                <td>
                  <div class="d-flex justify-content-between align-items-center" style="gap: 20px;">
                    <?php if( count($matched_remote_product) > 0 ) : ?>
                      
                      <a class="<?php echo $wcsnsc_stock_status; ?>" href="<?php echo $plink; ?>"><?php echo get_the_title(); ?></a>
                      
                      <!-- <span class="<?php echo $wcsnsc_stock_status; ?> text-nowrap"><?php echo isset($matched_remote_product['availableKeys']) ? 'Qty: ' .$matched_remote_product['availableKeys'] : ''; ?>/<?php 
                      echo isset($matched_remote_product['sellPrice']) ? '$' . $matched_remote_product['sellPrice'] : '';?></span> -->

                    <?php else : ?>
                      <a class="<?php echo $wcsnsc_stock_status; ?>" href="<?php echo $plink; ?>"><?php echo get_the_title(); ?></a>
                    <?php endif;?>
                  </div>

                </td>
                <td>
                <select name="productsLista" id=""
                      class="regular-text ac-serial-numbers-map-product" required="required"
                      placeholder="<?php _e( 'Select Product', 'ac-serial-numbers' ); ?>"
                      multiple="multiple">                      
                      <option value="">Select a Product</option>
                    </select>
                </td>
                <td class="text-center font-weight-bold">
                  <select name="keysource" class="keysource">
                      <option value="custom_source" <?php echo selected( 'custom_source', $key_source_type ); ?>>System</option>
                      <option value="reseller" <?php echo selected( 'reseller', $key_source_type ); ?>>Reseller</option>
                  </select>
                </td>
                <td class="text-center font-weight-bold"><?php echo $available; ?></td>
                <td class="text-center font-weight-bold"><?php echo $sold; ?></td>
              </tr>
              <?php   
            endwhile;
            wp_reset_query();
          ?>
        </tbody>
      </table>
		</div>
		<?php
	}

  public static function sync_product_meta_mapper() {
    global $wpdb;
    $wpdb_prefix = $wpdb->prefix;
    // get all post_meta like _ac_serial_numbers_key_source and it value is reseller
    $sql = "SELECT post_id, meta_value FROM {$wpdb_prefix}postmeta WHERE meta_key = '_ac_serial_numbers_key_source'";
    $results = $wpdb->get_results($sql);
    if (!empty($results)) {
      foreach ($results as $result) {
        $count_remote_products = count(json_decode(get_post_meta( $result->post_id, '_ac_remote_product', true )) ?? []);
        if($count_remote_products == 0){
          $old_product_id = get_post_meta( $result->post_id, '_ac_remote_product_id', true );
          if($old_product_id){
            $count_remote_products = 1;
          }
        }
        $isEnabled = get_post_meta( $result->post_id, '_is_serial_number', true );
        if(empty($result->meta_value)){
          update_post_meta( $result->post_id, '_ac_serial_numbers_key_source', 'custom_source' );          
        }else if ($result->meta_value == 'reseller' && $isEnabled == 'yes' && $count_remote_products == 0) {
          update_post_meta( $result->post_id, '_ac_serial_numbers_key_source', 'custom_source' );          
        }else if ($result->meta_value == 'reseller' && $isEnabled == 'no' && $count_remote_products == 0) {
          update_post_meta( $result->post_id, '_ac_serial_numbers_key_source', 'custom_source' );          
        }else if ($result->meta_value == 'custom_source' && $count_remote_products > 0) {
          update_post_meta( $result->post_id, '_ac_serial_numbers_key_source', 'reseller' );          
        }
        update_post_meta( $result->post_id, '_is_serial_number', 'yes' ); // run for all
      }
    }   

  }

  public static function get_product_sync_status() {
    global $wpdb;
    $wpdb_prefix = $wpdb->prefix;

    if (isset($_GET['fix']) && $_GET['fix'] == 'yes' ){
      self::sync_product_meta_mapper();
    }

    // get all post_meta like _ac_serial_numbers_key_source and it value is reseller
    $sql = "SELECT post_id, meta_value FROM {$wpdb_prefix}postmeta WHERE meta_key = '_ac_serial_numbers_key_source'";
    $results = $wpdb->get_results($sql);
    
    $incident_count = 0;
    if (!empty($results)) {
      foreach ($results as $result) {
        $count_remote_products = count(json_decode(get_post_meta( $result->post_id, '_ac_remote_product', true )) ?? []);
        // $dd = $count_remote_products == 0 ?  
        if($count_remote_products == 0){
          $old_product_id = get_post_meta( $result->post_id, '_ac_remote_product_id', true );
          if($old_product_id){
            $count_remote_products = 1;
          }
        }
        $isEnabled = get_post_meta( $result->post_id, '_is_serial_number', true );
        if(empty($result->meta_value)){
          $incident_count++; // do _ac_serial_numbers_key_source == 'custom_source'
        }else if ($result->meta_value == 'reseller' && $isEnabled == 'yes' && $count_remote_products == 0) {
          $incident_count++; // do _ac_serial_numbers_key_source == 'custom_source'
        }else if($result->meta_value == 'reseller' && $isEnabled == 'no' && $count_remote_products == 0){
          $incident_count++; // do _ac_serial_numbers_key_source == 'custom_source'
        }else if($result->meta_value == 'reseller' && $isEnabled == 'no' && $count_remote_products > 0){
          $incident_count++; // do _is_serial_number == 'yes'
        }else if($result->meta_value == 'custom_source' && $count_remote_products > 0){
          $incident_count++; // do _is_serial_number == 'yes'
        }

        // acsn_write_log($result->meta_value, $isEnabled, $count_remote_products);
      }
    } 
    
    // return $incident_count;
    if($incident_count > 0){
      // delete all post_meta which are not required
      $del_sql = "DELETE FROM {$wpdb_prefix}postmeta WHERE meta_key = '_serial_key_source' OR meta_key = 'ac_serial_numbers_key_source' OR meta_key = 'km_remote_product_id'";
      $wpdb->query($del_sql);
    }
    return $incident_count;


  }



}
