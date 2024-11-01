<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooPOS_Credit_Points_API {
	
	public function __construct(){
		add_action( 'rest_api_init', array( $this, 'register_routes' ) , 15 );
		//add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_credit_and_points_to_wc_order_payload' ), 10, 3 );
	}
	
	public function register_routes(){
		global $wp_version;
		if ( version_compare( $wp_version, 4.4, '<' )) {
			return;
		}
		
		require_once( __DIR__ . '/class-wc-rest-woopos-credit-points-controller.php' );
		$api_classes = array( 'WC_REST_WOOPOS_Credit_Points_Controller' );
		foreach ( $api_classes as $api_class ) {
			$controller = new $api_class();
			$controller->register_routes();
		}
	}
	
	
	public function add_credit_and_points_to_wc_order_payload( $response, $object, $request ){
		if( 'yes' === get_option( 'woopos_credit_points_store_credit_enable' ) ){
			$credit_used = get_post_meta( $object->id, 'woopos_credit_used', true );
			$credit_used = $credit_used ? $credit_used : 0;
			$response->data['credit_used'] = $credit_used;
		}
		if( 'yes' === get_option( 'woopos_credit_points_reward_points_enable' ) ){
			$points_used = get_post_meta( $object->id, 'woopos_points_used', true );
			$points_used = $points_used ? $points_used : 0;
			$response->data['points_amount_used'] = $points_used;
		}
		return $response;
	}
}

new WooPOS_Credit_Points_API();