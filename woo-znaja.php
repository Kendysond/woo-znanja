<?php 
/**
 * Plugin Name: WooCommerce Znaja Integration
 * Plugin URI: https://waymakerlearning.com/
 * Description: Links Woocommerce Products to Znaja 
 * Version: 1.0.0
 * Author: Douglas Kendyson
 * Author URI: https://github.com/kendysond
 * Developer: Douglas Kendyson
 * Developer URI: https://github.com/kendysond
 * Text Domain: woo-znaja
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */



require_once dirname( __FILE__ ) . '/vendor/autoload.php';
require_once dirname( __FILE__ ) . '/functions.php';
// require_once  './woocommerce/woocommerce.php';

define( 'WC_KKD_ZNAJA_MAIN_FILE', __FILE__ );

define( 'WC_KKD_ZNAJA_VERSION', '1.0.0' );


function kkd_znanja_woocommerce_payment_complete( ) {
	global $woocommerce;
	$order_id = 94;

    $order = new WC_Order( $order_id ); //wc_get_order($order_id);
    $items = $order->get_items(); 
   
    $customer = array();
    $customer['first_name'] =  get_post_meta($order_id,'_billing_first_name',true);
    $customer['last_name'] = get_post_meta($order_id,'_billing_last_name',true);
    $customer['email'] = get_post_meta($order_id,'_billing_email',true);
    $customer['is_active'] = true;
    
    $result = znanja_get_user_id($customer);
   	
    foreach ($items as $key => $item) {
    	$user_id = $result['user_id'];
    	$group_id = get_post_meta( $item['product_id'], '_course_group_id', true);
    	$payload  = array('group_id' => (int)$group_id,'user_id' => (int)$user_id, 'is_instructor' => false);
    	$response = znanja_add_to_group($payload);
    	if ($response['code'] === 201) {
    	
    	}
    }
   
    die('End Stuff');
}
add_action( 'woocommerce_after_cart_table', 'kkd_znanja_woocommerce_payment_complete' );

// add_action('init', 'kkd_znanja_woocommerce_payment_complete', 1);
// kkd_znanja_woocommerce_payment_complete( 93 );