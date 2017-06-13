<?php error_reporting(-1);
		ini_set('display_errors', 1);
require_once('../../../wp-load.php');
		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
/**
 * Plugin Name: WooCommerce Znaja Integration
 * Plugin URI: https://waymakerlearning.com/
 * Description: Links Woocommerce Products to Znaja 
 * Version: 1.0.0
 * Author: Douglas Kendyson
 * Author URI: https://github.com/kendysond
 * Developer: Douglas Kendyson
 * Developer URI: https://github.com/kendysond
 * Text Domain: woocommerce-extension
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

function register_znaja_course_product_type() {

	class WC_Product_Znaja_Course extends WC_Product_Simple {

		public function __construct( $product ) {

			$this->product_type = 'znaja_course';

			parent::__construct( $product );

		}

	}

}

// add_action( 'init', 'register_znaja_course_product_type' );

function add_znaja_course_product( $types ){

	$types[ 'znaja_course' ] = __( 'Znaja Course' );

	return $types;

}
// add_filter( 'product_type_selector', 'add_znaja_course_product' );

function znaja_course_custom_js() {

	if ( 'product' != get_post_type() ) :
		return;
	endif;

	?><script type='text/javascript'>
		jQuery( document ).ready( function() {
			jQuery( '.options_group.pricing' ).addClass( 'show_if_znaja_course' ).show();
		});
	</script><?php
}
// add_action( 'admin_footer', 'znaja_course_custom_js' );

function _znanja_request($url, $method, $payload=array()){
	$api_key = '6ff0dbd4-bff8-4181-a55f-1551c77ce57a';
	$api_id = 'public_api:membership:74825:8136';
	$credentials = new Dflydev\Hawk\Credentials\Credentials(
		$api_key,
		'sha256',
		$api_id
	);

	// Create a Hawk client
	$client = Dflydev\Hawk\Client\ClientBuilder::create()->build();

	$pay = array(
		'payload' => json_encode($payload),
		'content_type' => 'application/json'
	);

	$request = $client->createRequest(
		$credentials,
		$url,
		$method,
		$pay
	);

	$args = array(
		'body' => json_encode($payload),
		'url' => $url,
		'headers' => array(
		    $request->header()->fieldName() => $request->header()->fieldValue()
	));
	print_r($args);
	if($method == "GET")
	{
		$response = wp_remote_get( $url , $args );
	}

	if($method == "POST")
	{
		// $args['method'] = 'POST';
		$response = wp_remote_post( $url , $args );
	}

	if($method == "PUT")
	{
		$args['method'] = 'PUT';
		$response = wp_remote_post( $url , $args );
	}
	if($method == "DELETE")
	{
		$args['method'] = 'DELETE';
		$response = wp_remote_post( $url , $args );
	}
	print_r($response);
	$result = wp_remote_retrieve_body( $response );

	$response_code = wp_remote_retrieve_response_code( $response );
	// echo $response_code;
	$result  = array('code' => $response_code, 'json' => json_decode($result));
	return $result;
}


// $payload = [ 'fun' => 'cool'];
echo "<pre>";

function znanja_get_users(){
	$url = 'https://api.znanja.com/api/hawk/v1/users';
	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}

function znanja_get_groups(){
	$url = 'https://api.znanja.com/api/hawk/v1/groups';
	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}

function  znanja_get_group_users($id){
	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$id.'/users';
 
	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}

function  znanja_get_user($id){
	$url = 'https://api.znanja.com/api/hawk/v1/user/'.$id;
 
	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}

$payload  = array(

	'first_name' => 'Kendyson',
	'last_name' => 'Douglas',
	'email' => 'kendyson@kendyson.com',
	'is_active' => true,

);
function znaja_create_user($payload){
	$url = 'https://api.znanja.com/api/hawk/v1/user';

	$result = _znanja_request($url, 'PUT', $payload);
	return $result;
}
function znaja_get_group_memberships($id){
	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$id.'/memberships';

	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}

function znaja_get_membership($user_id,$organization_id){
	$url = 'https://api.znanja.com/api/hawk/v1/membership/'.$user_id.'/'.$organization_id;

	$result = _znanja_request($url, 'GET', $payload);
	return $result;
}
// $result = znaja_get_membership(75957,8136);

// print_r($result);
function znaja_add_to_group($payload){

	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$payload['group_id'].'/membership/'.$payload['user_id'];
	$payload  = array(

		'is_instructor' => false,
	);
	$result = _znanja_request($url, 'POST', $payload);
	return $result;
}

$payload  = array(

	'group_id' => 2649,
	'user_id' => 75957,
	'is_instructor' => false,

);
$result = znaja_add_to_group($payload);
$payload  = array(

	'group_id' => 2649,
	'user_id' => 75957,
	

);
// function znaja_delete_from_group($payload){

// 	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$payload['group_id'].'/membership/'.$payload['user_id'];
// 	$payload  = array();
// 	$result = _znanja_request($url, 'DELETE', $payload);
// 	return $result;
// }
// $result = znaja_delete_from_group($payload);
// $result =znanja_get_group_users('2649');
print_r($result);
echo "FUN";
die();
