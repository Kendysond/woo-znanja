<?php error_reporting(0); ini_set('display_errors', 0); 

function kkd_znanja_product_tabs( $original_tabs) {

	$new_tab['znanja'] = array(
		'label'		=> __( 'Znanja Options', 'woocommerce' ),
		'target'	=> 'znanja_options',
		'class'		=> array( 'show_if_simple', 'show_if_variable'  ),
	);

	$insert_at_position = 1; 
	$tabs = array_slice( $original_tabs, 0, $insert_at_position, true );
	$tabs = array_merge( $tabs, $new_tab ); 
	$tabs = array_merge( $tabs, array_slice( $original_tabs, $insert_at_position, null, true ) ); 

	return $tabs;

}
add_filter( 'woocommerce_product_data_tabs', 'kkd_znanja_product_tabs' );


function kkd_znanja_options_product_tab_content() {

	global $post;
	
	?><div id='znanja_options' class='panel woocommerce_options_panel'><?php

		?><div class='options_group'><?php
			woocommerce_wp_text_input( array(
				'id'				=> '_course_group_id',
				'label'				=> __( 'Group ID', 'woocommerce' ),
				'desc_tip'			=> 'true',
				'description'		=> __( 'Group ID the Course is in', 'woocommerce' ),
				'type' 				=> 'number',
				
			) );

		?></div>

	</div><?php

}
add_action( 'woocommerce_product_data_panels', 'kkd_znanja_options_product_tab_content' );
function kkd_znanja_save_options_fields( $post_id ) {
	
	if ( isset( $_POST['_course_group_id'] ) ) :
		update_post_meta( $post_id, '_course_group_id', absint( $_POST['_course_group_id'] ) );
	endif;
	
}
add_action( 'woocommerce_process_product_meta_simple', 'kkd_znanja_save_options_fields'  );
add_action( 'woocommerce_process_product_meta_variable', 'kkd_znanja_save_options_fields'  );



function _znanja_request($url, $method, $payload=array('')){
	$api_key = get_option( 'kkd_znanja_api_key', 1 );
	$api_id = get_option( 'kkd_znanja_api_id', 1 );
	$credentials = new Dflydev\Hawk\Credentials\Credentials(
		$api_key,
		'sha256',
		$api_id
	);

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
		$args['method'] = 'GET';

		$response = wp_safe_remote_get($url , $args );
	}

	if($method == "POST")
	{
		$args['method'] = 'POST';
		$args['headers']['Content-Type'] = 'application/json';

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
	$response_code = wp_remote_retrieve_response_code( $response );
	$json = wp_remote_retrieve_body($response);

	
	$result  = array('code' => $response_code, 'object' => json_decode($json));
	return $result;
}

function znanja_woocommerce_payment_complete( $order_id ) {
    
    global $woocommerce;
	
    $order = new WC_Order($order_id);
    $items = $order->get_items(); 
   
    $customer = array();
    $customer['first_name'] =  get_post_meta($order_id,'_billing_first_name',true);
    $customer['last_name'] = get_post_meta($order_id,'_billing_last_name',true);
    $customer['email'] = get_post_meta($order_id,'_billing_email',true);
    $customer['is_active'] = true;
    
    $result = znanja_get_user_id($customer);
   	$order->update_meta_data('znanja_email', $customer['email']);
    $order->update_meta_data('znanja_password',$result['password']);
    $order->save();
    
    foreach ($items as $key => $item) {
    	$user_id = $result['user_id'];
    	$group_id = get_post_meta( $item['product_id'], '_course_group_id', true);
    	$payload  = array('group_id' => (int)$group_id,'user_id' => (int)$user_id, 'is_instructor' => false);
    	$response = znanja_add_to_group($payload);
    	if ($response['code'] === 201) {
    	
    	}
    }
}
add_action( 'woocommerce_payment_complete', 'znanja_woocommerce_payment_complete', 10, 1 );
add_action( 'woocommerce_order_status_completed', 'znanja_woocommerce_payment_complete', 10, 1);

function znanja_get_users($search = null){

	$url = 'https://api.znanja.com/api/hawk/v1/users';
	if ($search != null) {
		$url = 'https://api.znanja.com/api/hawk/v1/users/'.urlencode($search);
	}
	$result = _znanja_request($url, 'GET');
	return $result;
}

function znanja_get_groups(){
	$url = 'https://api.znanja.com/api/hawk/v1/groups';
	$result = _znanja_request($url, 'GET');
	return $result;
}

function  znanja_get_group_users($id){
	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$id.'/users';
 
	$result = _znanja_request($url, 'GET');
	return $result;
}

function  znanja_get_user($id){
	$url = 'https://api.znanja.com/api/hawk/v1/user/'.$id;
 
	$result = _znanja_request($url, 'GET');
	return $result;
}

function  znanja_get_user_id($customer){

	$response = znanja_get_user($customer['email']);
	$user_id = 0;
	$password = null;
	if ($response['code'] === 200) {
		$user_id = $response['object']->id;
	}elseif ($response['code'] === 404) {
		$response = znanja_create_user($customer);
		if ($response['code'] === 200) {
			$user_id = $response['object']->id;
			$password = $response['object']->password;
		}
	}
	$result = ['user_id' => $user_id, 'password' => $password];
	return $result;
}

function znanja_create_user($payload){
	$url = 'https://api.znanja.com/api/hawk/v1/user';

	$result = _znanja_request($url, 'PUT', $payload);
	//Send User ane mail 
	return $result;
}
function znanja_get_group_memberships($id){
	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$id.'/memberships';

	$result = _znanja_request($url, 'GET');
	return $result;
}

function znanja_get_membership($user_id,$organization_id){
	$url = 'https://api.znanja.com/api/hawk/v1/membership/'.$user_id.'/'.$organization_id;

	$result = _znanja_request($url, 'GET');
	return $result;
}

function znanja_add_to_group($payload){

	$url = 'https://api.znanja.com/api/hawk/v1/group/'.$payload['group_id'].'/membership/'.$payload['user_id'];
	$result = _znanja_request($url, 'POST', $payload);
	return $result;
}
class KKD_Znanja_Settings_Tab {

    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_settings_tab_demo', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_settings_tab_demo', __CLASS__ . '::update_settings' );
    }
    
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_demo'] = __( 'Znanja LMS', 'woocommerce-znanja-settings-tab' );
        return $settings_tabs;
    }

    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }

    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    public static function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Znanja LMS API Acccess settings', 'woocommerce-znanja-settings-tab' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_settings_tab_demo_section_title'
            ),
            'api_id' => array(
                'name' => __( 'API Key Identifier', 'woocommerce-znanja-settings-tab' ),
                'type' => 'text',
                'css'      => 'min-width:400px;',
                'id'   => 'kkd_znanja_api_id'
            ),
            'api_key' => array(
                'name' => __( 'API Key', 'woocommerce-znanja-settings-tab' ),
                'type' => 'text',
                'css'      => 'min-width:400px;',
                'id'   => 'kkd_znanja_api_key'
            ),
            'url' => array(
                'name' => __( 'Front End Znanja URL', 'woocommerce-znanja-settings-tab' ),
                'type' => 'text',
                'css'      => 'min-width:400px;',
                'id'   => 'kkd_znanja_url'
            ),
            'thank-you' => array(
                'name' => __( 'Thank you page description', 'woocommerce-znanja-settings-tab' ),
                'type' => 'textarea',
                'css'      => 'min-width:400px;',
                'id'   => 'kkd_znanja_thank_you'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_settings_tab_demo_section_end'
            )
        );

        return apply_filters( 'wc_settings_tab_demo_settings', $settings );
    }

}

KKD_Znanja_Settings_Tab::init();