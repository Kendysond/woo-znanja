<?php
// error_reporting(-1);
		// ini_set('display_errors', 1);
require_once('../../../wp-load.php');
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
require_once dirname( __FILE__ ) . '/vendor/autoload.php';
function _znanja_request($url, $method, $payload=array())
{
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
		'headers' => array(
		    $request->header()->fieldName() => $request->header()->fieldValue()
	));

	if($method == "GET")
	{
		$response = wp_remote_get( $url , $args );
	}

	if($method == "POST")
	{
		$response = wp_remote_post( $url , $args );
	}

	if($method == "PUT")
	{
		$args['method'] = 'PUT';
		$response = wp_remote_post( $url , $args );
	}

	$result = wp_remote_retrieve_body( $response );

	return json_decode($result);
}
$payload = [ 'fun' => 'cool'];
echo "<pre>";
$result = _znanja_request('https://api.znanja.com/api/hawk/v1/users', 'GET', $payload);
print_r($result);
die();

// $hawk = Hawk::generateHeader( $key, $secret,  'GET', 'https://api.znanja.com/api/hawk/v1/users');
$request= [];
$credentials= [];
$options= [];
$hawk = Hawk::generateClientHeader($request, $credentials, $options);
die($hawk);
// $hawk = '';
 $headers = ['Authorization: '.$hawk, 'Content-Type: application/json'];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://api.znanja.com/api/hawk/v1/users',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
        ));
    $resp = curl_exec($curl);
    curl_close($curl);
    // return $resp;

    print_r($resp);
    die();



$paystack_url = 'https://api.znanja.com/api/hawk/v1/users';

$headers = array(
	'Authorization' => $hawk
);

$args = array(

	'headers'	=> $headers,
	// 'timeout'	=> 60
);

$request = wp_remote_get( $paystack_url, $args );
print_r($request);
$result = wp_remote_retrieve_body( $request );
echo wp_remote_retrieve_response_code( $request ).'<br>';
	print_r($result);

	die();

if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

	$paystack_response = json_decode( wp_remote_retrieve_body( $request ) );
	print_r($paystack_response);
}else{
	print_r($request);
}

print_r('HMMMM');
// API Key Identifier
// public_api:membership:74825:8136
// API Key
// 1a59a258-d2d7-4cbf-97d9-ef07fe387864
// Algorithm
// sha256

