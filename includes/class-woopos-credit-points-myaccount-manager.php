<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPOS_Credit_Points_Myaccount_Manager
 */
class WooPOS_Credit_Points_Myaccount_Manager {
	
	public function __construct(){
		add_action( 'woocommerce_account_content', array( $this, 'output_credit_points_balance' ), 5 );
	}
	
	/**
	 * Show a notice about user points and credit balance
	 */
	public static function output_credit_points_balance(){
		if( 'yes' === get_option( 'woopos_credit_points_store_credit_enable' ) ){
			$message = '<div class="woocommerce-info woopos-credit-points-balance-notice">';
			$message .= WooPOS_Credit_Points::get_message('credit');
			$message .= '</div>';
			echo $message;
		}
		if( 'yes' === get_option( 'woopos_credit_points_reward_points_enable' ) ){
			$message = '<div class="woocommerce-info woopos-credit-points-balance-notice">';
			$message .= WooPOS_Credit_Points::get_message('points');
			$message .= '</div>';
			echo $message;
		}
	}
	
}

new WooPOS_Credit_Points_Myaccount_Manager();