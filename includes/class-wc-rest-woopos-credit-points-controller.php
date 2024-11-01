<?php
class WC_REST_WOOPOS_Credit_Points_Controller extends WC_REST_CRUD_Controller{
	protected $namespace = 'wc/v2';
	
	public function register_routes(){
		if( 'yes' === get_option( 'woopos_credit_points_store_credit_enable' ) ){
			register_rest_route( $this->namespace, '/woopos_storecredit', array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_credit' ),
				'permission_callback' => array( $this, 'check_permission_to_edit_users' ),
				'args' => $this->get_params_credit()
			) );
		}
		if( 'yes' === get_option( 'woopos_credit_points_reward_points_enable' ) ){
			register_rest_route( $this->namespace, '/woopos_points', array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_points' ),
				'permission_callback' => array( $this, 'check_permission_to_edit_users' ),
				'args' => $this->get_params_points()
			) );
		}
	}
	
	public function check_permission_to_edit_users(){
		return wc_rest_check_user_permissions( 'edit' );
	}
	
	public function get_params(){
		$params = array(
			'email' => array(
				'required'          => true,
				'description'       => __( 'Email of user to update.', 'woopos-credit-points' ),
				'type'              => 'string',
				'format'            => 'email',
				'validate_callback' => 'rest_validate_request_arg'
			)
		);
		return $params;
	}
	
	public function get_params_credit(){
		$params = $this->get_params();
		$params['store_credit'] = array(
			'description'       => __( 'Store credit.', 'woopos-credit-points' ),
			'type'              => 'number',
			'minimum'           => 0,
			'validate_callback' => 'rest_validate_request_arg'
		);
		return $params;
	}
	
	public function get_params_points(){
		$params = $this->get_params();
		$params['points_balance'] = array(
			'description'       => __( 'Points balance.', 'woopos-credit-points' ),
			'type'              => 'integer',
			'minimum'           => 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg'
		);
		$params['redeemable_points'] = array(
			'description'       => __( 'Redeemable Points.', 'woopos-credit-points' ),
			'type'              => 'integer',
			'minimum'           => 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg'
		);
		$params['redeemable_amount'] = array(
			'description'       => __( 'Redeemable Points Amount.', 'woopos-credit-points' ),
			'type'              => 'number',
			'minimum'           => 0,
			'validate_callback' => 'rest_validate_request_arg'
		);
		return $params;
	}
	
	public function update_credit($request){
		$response = array();
		try{
			$user_id = self::get_user_id_by_email( $request['email'] );
			if( ! isset( $request['store_credit'] ) ){
				throw new Exception("Store credit parameter is empty, cant update.");
			}
			WooPOS_Credit_Points::update_credit( $user_id, floatval( $request['store_credit'] ) );
			$response['result'] = "success";
			$response['message'] = "Updated store credit for user with email: '{$request['email']}' (user id: '{$user_id}'). Store Credit = {$request['store_credit']};";
		}catch(Exception $e){
			$response['result'] = "error";
			$response['message'] = $e->getMessage();
		}
		return $response;
	}
	
	public function update_points($request){
		$response = array();
		try{
			$user_id = self::get_user_id_by_email( $request['email'] );
			if( ! isset( $request['points_balance'] ) && ! isset( $request['redeemable_points'] ) && ! isset( $request['redeemable_amount'] ) ){
				throw new Exception("Not a single Points amount parameter is set, cant update.");
			}
			$updated_info = "";
			if( isset( $request['points_balance'] ) ){
				WooPOS_Credit_Points::update_points( $user_id, $request['points_balance'] );
				$updated_info .= "Points Balance = {$request['points_balance']};";
			}
			if( isset( $request['redeemable_points'] ) ){
				WooPOS_Credit_Points::update_points_redeemable( $user_id, $request['redeemable_points'] );
				$updated_info .= "Redeemable Points = {$request['redeemable_points']};";
			}
			if( isset( $request['redeemable_amount'] ) ){
				WooPOS_Credit_Points::update_points_redeemable_amount( $user_id, floatval( $request['redeemable_amount'] ) );
				$updated_info .= "Redeemable Points Amount = {$request['redeemable_amount']};";
			}
			$response['result'] = "success";
			$response['message'] = "Updated points for user with email: '{$request['email']}' (user id: '{$user_id}'). {$updated_info}";
		}catch(Exception $e){
			$response['result'] = "error";
			$response['message'] = $e->getMessage();
		}
		return $response;
	}
	
	public static function get_user_id_by_email($email){
		if( ! $user = get_user_by( 'email', $email ) ){
			throw new Exception("User with email '{$email}' not found");
		}
		return $user->ID;
	}
	
}