<?php
/*
Plugin Name: Finnish Business ID validation and meta to API v2
Plugin URI:
Description: Validoi Y-tunnuskenttään ( billing_y_tunnus ) syötetyn tiedon ja lisää tilauksen metaan tallennetun Y-tunnustiedon ( _billing_y_tunnus ) v2 APIin ( y_tunnus )
Author: Mikko Mörö - sivuseppa.fi
Version: 0.2

Using "validate_finnish_business_id.php" by Lauri Eskola: https://gist.github.com/lauriii/3cb3b32ad86c271ebc681012855ce529
 
*/

defined('ABSPATH') OR exit('No direct script access allowed');

/**
 * Process the checkout and validate the company id from the billing_y_tunnus field
 */
add_action('woocommerce_checkout_process', 'my_custom_checkout_field_process');

function my_custom_checkout_field_process() {

  // Check if set correctly, if its not set correctly add an error.
  
  if ( $_POST['billing_y_tunnus'] ){

      $company_id = sanitize_text_field( $_POST['billing_y_tunnus'] );

      function validate_company_id( $company_id ) {

          // Some old company id's have only 6 digits. They should be prefixed with 0.
          if (preg_match("/^[0-9]{6}\\-[0-9]{1}/", $company_id)) {
            $company_id = '0' . $company_id;
          }
        
          // Ensure that the company ID is entered in correct format.
          if (!preg_match("/^[0-9]{7}\\-[0-9]{1}/", $company_id)) {
            return FALSE;
          }
        
          list($id, $checksum) = explode('-', $company_id);
          $checksum = (int) $checksum;
        
          $total_count = 0;
          $multipliers = [7, 9, 10, 5, 8, 4, 2];
          foreach ($multipliers as $key => $multiplier) {
            $total_count = $total_count + $multiplier * $id[$key];
          }
        
          $remainder = $total_count % 11;
        
          // Remainder 1 is not valid.
          if ($remainder === 1) {
            return FALSE;
          }
        
          // Remainder 0 leads into checksum 0.
          if ($remainder === 0) {
            return $checksum === $remainder;
          }
        
          // If remainder is not 0, the checksum should be remainder deducted from 11.
          return $checksum === 11 - $remainder;
        }
  
      $validate_id = validate_company_id( $company_id );
  
      if ( ! $validate_id ){
        wc_add_notice( __( 'Tarkista, että syöttämäsi Y-tunnus ( ' . $company_id . ' ) on oikein, kiitos.' ), 'error' );
    }
  }
}

/*
 * Legacy API
 * Add a billing_y_tunnus field to the Order API response.
*/

add_filter( 'woocommerce_api_order_response', 'prefix_wc_api_order_response', 10, 1 );

function prefix_wc_api_order_response( $order_data ) {
	// Get the value
	$referanse_meta_field = ( $value = get_post_meta($order_data['id'], '_billing_y_tunnus', true) ) ? $value : '';
 
	$order_data['billing_address']['y_tunnus'] = $referanse_meta_field;
 
	return $order_data;
}