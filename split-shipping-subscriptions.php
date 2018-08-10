<?php
/****
 * Plugin Name: PWDC Order Status
 * Plugin URI: https://phillipswdc.com
 * Description: This plugin adds the ability to split the shipping packages based on variable subscriptions along with additional added functionality
 * Version: .01
 * Author: Kevin Phillips
 * Author URI: https://phillipswdc.com/about
 * License: GNU
 *
 * Copyright 2018 Phillips Web Development Company, LLC email: projects@phillipswdc.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****/

//!!!IMPORTANT THIS CODE IS NOT PRODUCTION READY!!!//
//!!THIS CODE IS UGLY AND IS FOR TESTING ONLY!!//

/*
 * Add new inputs to each variation
 *
 * @param string $loop
 * @param array $variation_data
 * @return print HTML
 */
function add_to_variations_metabox( $loop, $variation_data, $variation ){

	$split_shipping_payments = get_post_meta( $variation->ID, '_split_shipping_payments', true );

	var_dump($split_shipping_payments);

	if($split_shipping_payments == '1'){
		$checked = 'checked';
	}else{
		$checked = '';
	}

	$custom_description = get_post_meta( $variation->ID, '_custom_description', true );

	?>

	<div class="variable_custom_field">
		<p class="form-row form-row-first">
			<label><?php echo __( 'Split shipping for monthly payments:', 'plugin_textdomain' ); ?></label>
			<input type="checkbox" size="5" name="variation_split_shipping_payments[<?php echo $loop; ?>]" value="<?php echo $split_shipping_payments; ?>" <?php echo $checked; ?>/>
			<script>
                jQuery(document).ready(function($){
                    var this_variation = $('input[name="variation_split_shipping_payments[<?php echo $loop; ?>]"]');

                    $(this_variation).on('change', function(){

                        if($(this).attr('checked')){
                            $(this).val('1');
                        }else{
                            $(this).val('0');
                        }

                    });
                });
			</script>
			<br />
			<br />
			<label><?php echo __( 'Custom Label:', 'plugin_textdomain' ); ?></label>
			<input type="text" size="5" name="variation_custom_description[<?php echo $loop; ?>]" value="<?php echo esc_attr( $custom_description ); ?>" />
		</p>
	</div>

	<?php

}
add_action( 'woocommerce_product_after_variable_attributes', 'add_to_variations_metabox', 10, 3 );

/*
 * Save extra meta info for variable products
 *
 * @param int $variation_id
 * @param int $i
 * return void
 */
function save_product_variation( $variation_id, $i ){

	if ( isset( $_POST['variation_split_shipping_payments'][$i] ) ) {
		// _charge_shipping_on_first
		// sanitize data in way that makes sense for your data type
		$split_shipping_payments = ( trim( $_POST['variation_split_shipping_payments'][$i]  ) === '' ) ? '' : sanitize_title( $_POST['variation_split_shipping_payments'][$i] );
		update_post_meta( $variation_id, '_split_shipping_payments', $split_shipping_payments );
	}


    // change description for variable product
	if ( isset( $_POST['variation_custom_description'][$i] ) ) {
		// sanitize data in way that makes sense for your data type
		$custom_data = $_POST['variation_custom_description'][$i];
		update_post_meta( $variation_id, '_custom_description', $custom_data );
	}

}
add_action( 'woocommerce_save_product_variation', 'save_product_variation', 20, 2 );

function wc_subscriptions_custom_price_string( $subscription_string, $product, $include ) {

	$split_shipping_payments = (boolean)get_post_meta( $product->variation_id, '_split_shipping_payments', true );
	$variation_description = get_post_meta( $product->variation_id, '_custom_description', true );
	$regular_price = get_post_meta( $product->variation_id, '_regular_price', true );


	if(! empty($variation_description)){
		if($product->is_on_sale()){
			$sale_price = $product->get_sale_price();
			$regular_price = '<s>'. $regular_price .'</s> ' . '$' . $sale_price;
		}
		$subscription_string = '$' . $regular_price . ' ' . $variation_description;
	}


	//echo '<pre>';
	//var_dump($include);
	//var_dump($single_payment_product);
	//var_dump($product->variation_id);
	//var_dump($subscription_string);
	//var_dump($product->id);
	//var_dump($product);
	//var_dump(get_post_meta( $product->variation_id, '_custom_description', true ));
	//echo '</pre>';

	//$newprice = 'test this out';
	//return $newprice;
	return $subscription_string;
}
add_filter( 'woocommerce_subscriptions_product_price_string', 'wc_subscriptions_custom_price_string', 10, 3 );
add_filter( 'woocommerce_subscription_price_string', 'wc_subscriptions_custom_price_string' );



function custom_split_shipping_packages_shipping_class( $packages ) {



	// Reset all packages
	$packages              = array();
	$regular_package_items = array();
	$split_package_items   = array();

	foreach ( WC()->cart->get_cart() as $item_key => $item ) {

		$_product = $item['data'];

		$single_payment_product = get_post_meta( $_product->variation_id, '_split_shipping_payments', true );


		if($single_payment_product){
			$subscription_length = get_post_meta( $_product->variation_id, '_subscription_length', true );
			$shipping_charge_divisor = $subscription_length + 1;
			$split_package_items[ $item_key ] = $item;
		}else {
			$regular_package_items[ $item_key ] = $item;
		}

	}

	// Create shipping packages
	if ( $regular_package_items ) {
		$packages[] = array(
			'contents'        => $regular_package_items,
			'recurring_cart_key' => false,
			'contents_cost'   => array_sum( wp_list_pluck( $regular_package_items, 'line_total' ) ),
			'applied_coupons' => WC()->cart->get_applied_coupons(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'    => array(
				'country'    => WC()->customer->get_shipping_country(),
				'state'      => WC()->customer->get_shipping_state(),
				'postcode'   => WC()->customer->get_shipping_postcode(),
				'city'       => WC()->customer->get_shipping_city(),
				'address'    => WC()->customer->get_shipping_address(),
				'address_2'  => WC()->customer->get_shipping_address_2()
			)
		);
	}

	if ( $split_package_items ) {
		$packages[] = array(
			'contents'        => $split_package_items,
			'recurring_cart_key' => true,
			'split-the-ship' => $shipping_charge_divisor,
			'contents_cost'   => array_sum( wp_list_pluck( $split_package_items, 'line_total' ) ),
			'applied_coupons' => WC()->cart->get_applied_coupons(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'    => array(
				'country'    => WC()->customer->get_shipping_country(),
				'state'      => WC()->customer->get_shipping_state(),
				'postcode'   => WC()->customer->get_shipping_postcode(),
				'city'       => WC()->customer->get_shipping_city(),
				'address'    => WC()->customer->get_shipping_address(),
				'address_2'  => WC()->customer->get_shipping_address_2()
			)
		);
	}
	/*
	echo '<pre>';
	var_dump($packages);
	echo '</pre>';
	*/

	return $packages;

}
add_filter( 'woocommerce_cart_shipping_packages', 'custom_split_shipping_packages_shipping_class' );

function apply_static_rate($rates, $package)
{
	//var_dump($package['split-the-ship']);

	if( isset( $package['split-the-ship'] ) ){
		foreach( $rates as $rate_id => $rate_obj ){
			$full_ship_cost = $rates[$rate_id]->cost;
			$rates[$rate_id]->cost = $full_ship_cost / $package['split-the-ship'];
		}

	}else{

	}
	/*
		echo '<pre>';
		var_dump($package['_remove_from_recurring_cart']);
		echo '</pre>';
		*/
	return $rates;
}
add_filter('woocommerce_package_rates', 'apply_static_rate', 10, 2);

/*
 * this currently is being used to remove the additional cost from the recurring total added from the non recurring package
 * this works well except for when there are changes made to the cart and AJAX updates the totals. When this happens the total is not calculated correctly and an odd amount is which appears to be a better way to fix this would be to remove the package that is NOT a recurring order from the calculation all together
 */
function custom_calculated_total( $total, $cart ){

	$packages = WC()->cart->get_shipping_packages();
	$cost = 0;
	if(count($packages) > 1){
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' )[0];
		$shipping_methods = WC()->session->get('shipping_for_package_0')['rates'];
		foreach ( $packages as $package ) {
			if($package["recurring_cart_key"] === false){
				// Loop through the array
				foreach ( $shipping_methods as $method_id => $shipping_rate ){
					$cost = $shipping_rate->cost;
					break;
				}
			}
		}
		return round( $total - $cost, $cart->dp );
	}

	/*echo '<pre>';
	var_dump($cost);
	var_dump($total);
	echo '</pre>';*/
return $total;

}

add_filter( 'woocommerce_calculated_total', 'custom_calculated_total', 10, 3 );
