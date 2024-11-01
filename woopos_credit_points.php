<?php
/**
 * Plugin Name: WooPOS Store Credit & Points
 * Description: Store Credit and Points for both WooCommerce online store and WooPOS physical stores
 * Author: woopos
 * Author URI: https://woopos.com
 * Version: 1.3
 * License: GPL2 or later
 */

if( !defined( 'ABSPATH' ) ) exit;

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

class WooPOS_Credit_Points{

	public function __construct(){
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'admin_init' ) );
		add_action( 'plugins_loaded', array( $this, 'gateway_init' ), 0 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		add_action( 'woocommerce_email', array( $this, 'unhook_emails_for_api' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woopos-credit-points', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Load classes
	 */
	public function init(){
		//self::add_credit(get_current_user_id(), 45);
		//self::add_points(get_current_user_id(), 5*20);
		include_once( 'includes/class-woopos-credit-points-myaccount-manager.php' );
		include_once( 'includes/class-woopos-credit-points-cart-manager.php' );
		include_once( 'includes/class-woopos-credit-points-order-manager.php' );
		include_once( 'includes/class-woopos-credit-points-api.php' );
	}

	/**
	 * Load admin
	 */
	public function admin_init() {
		if ( is_admin() ) {
			include_once( 'includes/class-woopos-credit-points-admin.php' );
		}
	}

	/**
	 * Init Gateway
	 */
	public function gateway_init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		include_once( 'includes/class-woopos-credit-points-gateway.php' );
	}

	/**
	 * Register the gateway for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WooPOS_Credit_Points_Gateway';
		return $methods;
	}

	/**
	 * Add scripts to checkout process
	 */
	public function checkout_scripts() {
		wp_enqueue_script( 'woopos_credit_points', plugins_url( 'assets/js/woopos_credit_points.js', __FILE__ ), array( 'jquery' ), true );
	}
	
	/*
	 Unhook all woocommerce emails if current request is WC API route
	 */
	public function unhook_emails_for_api( $email_class  ){
		$is_api = $this->is_request_to_rest_api();
		if( $is_api ){
			/**
			 * Hooks for sending emails during store events
			 **/
			remove_action( 'woocommerce_low_stock_notification', array( $email_class, 'low_stock' ) );
			remove_action( 'woocommerce_no_stock_notification', array( $email_class, 'no_stock' ) );
			remove_action( 'woocommerce_product_on_backorder_notification', array( $email_class, 'backorder' ) );
			// New order emails
			remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			// Processing order emails
			remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			// Completed order emails
			remove_action( 'woocommerce_order_status_completed_notification', array( $email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger' ) );
			// Note emails
			remove_action( 'woocommerce_new_customer_note_notification', array( $email_class->emails['WC_Email_Customer_Note'], 'trigger' ) );
		}
	}
	
	/*
	 This function is a copy from class "WC_REST_Authentication"
	 we need this copy cuz its protected function in above class
	 */
	public function is_request_to_rest_api() {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $rest_prefix = trailingslashit( rest_get_url_prefix() );

        // Check if our endpoint.
        $woocommerce = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix . 'wc/' ) );

        // Allow third party plugins use our authentication methods.
        $third_party = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix . 'wc-' ) );

        return apply_filters( 'woocommerce_rest_is_request_to_rest_api', $woocommerce || $third_party );
    }

	/**
	 * Activation
	 */
	public function activate() {
		add_option( 'woopos_credit_points_store_credit_enable', 'yes' );
		add_option( 'woopos_credit_points_store_credit_message', __( 'You have {store_credit_balance} store credit on your account.', 'woopos-credit-points' ) );
		add_option( 'woopos_credit_points_reward_points_enable', 'yes' );
		add_option( 'woopos_credit_points_reward_points_zero_message', __( 'Earn points on every dollar you spend. Reward points details here.', 'woopos-credit-points' ) );
		add_option( 'woopos_credit_points_reward_points_not_zero_message', __( 'You have {points_balance} points. You can redeem {redeemable_points} for a {redeemable_amount} discount for this order!', 'woopos-credit-points' ) );
	}

	public static function get_message($message_type, $user_id = null){
		$user_id = $user_id ? $user_id : get_current_user_id();
		$message = "";

		switch($message_type){
			case 'credit':
				$message_code = 'store_credit_message';
				break;

			case 'points':
				$available_points = self::get_points($user_id, 'redeemable_amount', false);
				$message_code = empty($available_points) ? 'reward_points_zero_message' : 'reward_points_not_zero_message';
				break;
			
			case 'warning':
				$message_code = 'warning_message';
				break;
		}
		if( !empty($message_code) ){
			$message = get_option( "woopos_credit_points_{$message_code}" );
			$message = self::parse_message($message, $user_id);
		}

		return $message;
	}
	public static function parse_message($message, $user_id){
		$message = str_replace('{store_credit_balance}', self::get_credit($user_id), $message);
		$message = str_replace('{points_balance}', self::get_points($user_id), $message);
		$message = str_replace('{redeemable_points}', self::get_points($user_id, 'redeemable'), $message);
		$message = str_replace('{redeemable_amount}', self::get_points($user_id, 'redeemable_amount'), $message);
		return $message;
	}

	public static function get_credit( $user_id = null, $formatted = true ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if ( $user_id ) {
			$credit = max( 0, get_user_meta( $user_id, 'woopos_account_credit', true ) );
		} else {
			$credit = 0;
		}
		return $formatted ? wc_price( $credit ) : $credit;
	}
	public static function add_credit( $customer_id, $amount ) {
		$credit = get_user_meta( $customer_id, 'woopos_account_credit', true );
		$credit = $credit ? $credit : 0;
		$credit += floatval( $amount );
		update_user_meta( $customer_id, 'woopos_account_credit', $credit );
	}
	public static function remove_credit( $customer_id, $amount ) {
		$credit = get_user_meta( $customer_id, 'woopos_account_credit', true );
		$credit = $credit ? $credit : 0;
		$credit = $credit - floatval( $amount );
		update_user_meta( $customer_id, 'woopos_account_credit', max( 0, $credit ) );
	}
	public static function update_credit( $customer_id, $amount ) {
		update_user_meta( $customer_id, 'woopos_account_credit', $amount );
	}


	public static function get_points($user_id = null, $type = 'points', $formatted = true){
		$user_id = $user_id ? $user_id : get_current_user_id();
		if( ! $user_id ){
			return 0;
		}

		switch($type){
			case 'points':
				$points = max( 0, get_user_meta( $user_id, 'woopos_account_points', true ) );
				break;

			case 'redeemable':
				$points = max( 0, get_user_meta( $user_id, 'woopos_account_points_redeemable', true ) );
				break;

			case 'redeemable_amount':
				$points = max( 0, get_user_meta( $user_id, 'woopos_account_points_redeemable_amount', true ) );
				$points = $formatted ? wc_price( $points ) : $points;
				break;
		}

		return $points;
	}

	public static function add_points( $customer_id, $amount ) {
		$points_redeemable_amount = get_user_meta( $customer_id, 'woopos_account_points_redeemable_amount', true );
		$points_redeemable_amount = $points_redeemable_amount ? $points_redeemable_amount : 0;
		$points_redeemable_amount += floatval( $amount );
		update_user_meta( $customer_id, 'woopos_account_points_redeemable_amount', $points_redeemable_amount );
        if($points_redeemable_amount-floatval($amount)>0){
            $points_redeemable = get_user_meta( $customer_id, 'woopos_account_points_redeemable', true );
            $points_redeemable = $points_redeemable ? $points_redeemable : 0;
            $points_redeemable_new = $points_redeemable*$points_redeemable_amount/($points_redeemable_amount-floatval($amount));
            update_user_meta( $customer_id, 'woopos_account_points_redeemable', $points_redeemable_new );
            $points = get_user_meta( $customer_id, 'woopos_account_points', true);
            $points =$points + ($points_redeemable_new-$points_redeemable);
            update_user_meta($customer_id, 'woopos_account_points', $points);
        }
    }

	public static function remove_points( $customer_id, $amount ) {
		$points_redeemable_amount = get_user_meta( $customer_id, 'woopos_account_points_redeemable_amount', true );
		$points_redeemable_amount = $points_redeemable_amount ? $points_redeemable_amount : 0;
		$points_redeemable_amount = $points_redeemable_amount - floatval( $amount );
		update_user_meta( $customer_id, 'woopos_account_points_redeemable_amount', max( 0, $points_redeemable_amount ) );
        if($points_redeemable_amount+floatval($amount)>0){
            $points_redeemable = get_user_meta( $customer_id, 'woopos_account_points_redeemable', true );
            $points_redeemable = $points_redeemable ? $points_redeemable : 0;
            $points_redeemable_new = $points_redeemable*$points_redeemable_amount/($points_redeemable_amount+floatval($amount));
            update_user_meta( $customer_id, 'woopos_account_points_redeemable', $points_redeemable_new );
            $points = get_user_meta( $customer_id, 'woopos_account_points', true);
            $points =$points -($points_redeemable-$points_redeemable_new);
            update_user_meta( $customer_id, 'woopos_account_points', $points);
        }
    }

	public static function update_points( $customer_id, $amount ) {
		update_user_meta( $customer_id, 'woopos_account_points', $amount );
	}
	public static function update_points_redeemable( $customer_id, $amount ) {
		update_user_meta( $customer_id, 'woopos_account_points_redeemable', $amount );
	}
	public static function update_points_redeemable_amount( $customer_id, $amount ) {
		update_user_meta( $customer_id, 'woopos_account_points_redeemable_amount', $amount );
	}
}

new WooPOS_Credit_Points();