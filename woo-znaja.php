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

define( 'WC_KKD_ZNAJA_MAIN_FILE', __FILE__ );

define( 'WC_KKD_ZNAJA_VERSION', '1.0.0' );


add_filter( 'woocommerce_thankyou_order_received_text', 'wpb_thank_you', 10, 2 );
function wpb_thank_you( $thankyoutext, $order ) {
	$url = get_option( 'kkd_znanja_url', 1 );
	$email = get_post_meta( $order->id, 'znanja_email', true );
	    $password = get_post_meta( $order->id, 'znanja_password', true );
	    if ($password == null) {
	    	 $password .= "<i> Login with your existing password </i>";
	    }else{
	    	$password.= '<br> <i> Kindly ensure you change your password after logging in<i> ';
	    }

    // $thankyoutext .= '<br>';
	$thankyoutext .= '<h2 class="woocommerce-order-details__title">Learning Portal Credentials</h2>';
    if ($email != null) {
		$thankyoutext .= "<b>Portal</b>: <a href='".$url."' target='_blank'>".$url."</a><br>";
	    $thankyoutext .= "<b>Email</b>: ".$email."<br>";
	    $thankyoutext .= "<b>Password</b>: ".$password."<br><br>";
    }else{
	    $thankyoutext .= "<i> Login Credentials would be sent after your payment is confirmed. </i><br><br>";

    }
	return $thankyoutext;
}
add_action( 'woocommerce_email_before_order_table', 'kkd_znanja_add_credentials_to_email', 10, 2 ); 

function kkd_znanja_add_credentials_to_email( $order, $is_admin_email ) { 
	if (!$is_admin_email) {
		$thankyoutext = '';
	    $url = get_option( 'kkd_znanja_url', 1 );
		$email = get_post_meta( $order->id, 'znanja_email', true );
	    $password = get_post_meta( $order->id, 'znanja_password', true );
	    if ($password == null) {
	    	 $password .= "<i> Login with your existing password </i>";
	    }else{
	    	$password.= '<br> <i> Kindly ensure you change your password after logging in<i> ';
	    }

	    $thankyoutext .= '<h2 class="woocommerce-order-details__title">Learning Portal Credentials</h2>';
	    if ($email != null) {
			$thankyoutext .= "<b>Portal</b>: <a href='".$url."' target='_blank'>".$url."</a><br>";
		    $thankyoutext .= "<b>Email</b>: ".$email."<br>";
		    $thankyoutext .= "<b>Password</b>: ".$password."<br><br>";
	    }else{
		    $thankyoutext .= "<i> Login Credentials would be sent after your payment is confirmed. </i><br><br>";

	    }
		echo $thankyoutext;
	}
}
