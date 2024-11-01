<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPOS_Credit_Points_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class WooPOS_Credit_Points_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'woopos_account_credit_points';
		$this->method_title       = __( 'Store Credit and Points', 'woopos-credit-points' );
		$this->method_description = __( 'This gateway takes full payment using a logged in user\'s account credit or points.', 'woocommerce-account-credit-points' );
		$this->supports           = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_date_changes'
		);
		$this->credit_enabled = 'yes' === get_option( 'woopos_credit_points_store_credit_enable' );
		$this->points_enabled = 'yes' === get_option( 'woopos_credit_points_reward_points_enable' );
		$this->enabled = $this->is_available();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title        = $this->get_option( 'title' );

		$description = '';
		if( WooPOS_Credit_Points_Cart_Manager::using('credit') || WooPOS_Credit_Points_Cart_Manager::using('points') ){
			if( $this->credit_enabled ){
				$description .= sprintf( __( "Store Credit applied: %s.", 'woocommerce-account-credit-points'), wc_price( WooPOS_Credit_Points_Cart_Manager::get_used_amount('credit') ) );
			}
			if( !empty($description) ) $description .= "<br>";
			if( $this->points_enabled ){
				$description .= sprintf( __( "Points applied: %s.", 'woocommerce-account-credit-points'), wc_price( WooPOS_Credit_Points_Cart_Manager::get_used_amount('points') ) );
			}
		}else{
			if( $this->credit_enabled ){
				$description .= sprintf( __( "Available Store Credit: %s.", 'woocommerce-account-credit-points'), WooPOS_Credit_Points::get_credit() );
			}
			if( !empty($description) ) $description .= "<br>";
			if( $this->points_enabled ){
				$description .= sprintf( __( "Available Points: %s.", 'woocommerce-account-credit-points'), WooPOS_Credit_Points::get_points(null, 'redeemable_amount') );
			}
		}

		$this->description = $description;

		// Subscriptons
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_filter( 'woocommerce_my_subscriptions_recurring_payment_method', array( $this, 'subscription_payment_method_name' ), 10, 3 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if the gateway is available for use
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = $this->credit_enabled || $this->points_enabled;

		// Check cart when it's front-end request.
		//$is_frontend_request = (
		//	(
		//		! is_admin()
		//		||
		//		( defined( 'DOING_AJAX' ) && DOING_AJAX )
		//	)
		//	&&
		//	(
		//		! defined( 'DOING_CRON' )
		//		||
		//		( defined( 'DOING_CRON' ) && ! DOING_CRON )
		//	)
		//);
		//if ( $is_frontend_request ) {
		//	if (  WooPOS_Credit_Points_Cart_Manager::using('credit') || WooPOS_Credit_Points_Cart_Manager::using('points') ) {
		//		//$is_available = false;
		//	}
		//}

		return $is_available;
	}

	/**
	 * Settings
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woothemes' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable', 'woothemes' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'woothemes' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'     => __( 'Store Credit and Points', 'woopos-credit-points' )
			)
		);
	}

	/**
	 * Process a payment
	 */
	public function process_payment( $order_id ) {
		$order  = wc_get_order( $order_id );

		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'Payment error:', 'woopos-credit-points' ) . ' ' . __( 'You must be logged in to use this payment method', 'woopos-credit-points' ), 'error' );
			return;
		}

		$total = $order->get_total();
		if( $this->credit_enabled ){
			if( WooPOS_Credit_Points_Cart_Manager::using('credit') ){
				$credit_amount =  WooPOS_Credit_Points_Cart_Manager::get_used_amount('credit');
			}else{
				$credit_amount =  min( $total, WooPOS_Credit_Points::get_credit( $order->get_user_id(), false ) );
				$total         = $total - $credit_amount;
			}
		}
		if( $this->points_enabled ){
			if( WooPOS_Credit_Points_Cart_Manager::using('points') ){
				$points_amount = WooPOS_Credit_Points_Cart_Manager::get_used_amount('points');
			}else{
				$points_amount = min( $total, WooPOS_Credit_Points::get_points( $order->get_user_id(), 'redeemable_amount', false ) );
				$total         = $total - $points_amount;
			}
		}

		if ( $total > 0 ) {
			wc_add_notice( __( 'Payment error:', 'woopos-credit-points' ) . ' ' . __( 'Insufficient account balance', 'woopos-credit-points' ), 'error' );
			return;
		}

		// deduct amount from account funds
		if(  ! get_post_meta( $order_id, 'woopos_credit_removed', true ) && $this->credit_enabled && $credit_amount!=0){
			WooPOS_Credit_Points::remove_credit( $order->get_user_id(), $credit_amount );
			update_post_meta( $order_id, 'woopos_credit_used', $credit_amount );
	        update_post_meta( $order_id, 'woopos_credit_removed', 1 );	
        }
		if(  ! get_post_meta( $order_id, 'woopos_points_removed', true ) && $this->points_enabled && $points_amount!=0 ){
			WooPOS_Credit_Points::remove_points( $order->get_user_id(), $points_amount );
			update_post_meta( $order_id, 'woopos_points_used', $points_amount );
		    update_post_meta( $order_id, 'woopos_points_removed', 1 );
		}
		$order->set_total( 0 );

		// Payment complete
		$order->payment_complete();

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'    => 'success',
			'redirect'  => $this->get_return_url( $order )
		);
	}

	/**
	 * Process scheduled subscription payment.
	 *
	 * @since 1.0.0
	 * @version 2.1.7
	 *
	 * @param float    $amount Renewal order amount.
	 * @param WC_Order $order  Renewal order.
	 *
	 * @return bool|WP_Error
	 */
	public function scheduled_subscription_payment( $amount, $order ) {
		$ret = true;

		// The WC_Subscriptions_Manager will generates order for the renewal.
		// However, the total will not be cleared and replaced with amount of
		// funds used. The set_renewal_order_meta will fix that.
		add_action( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'set_renewal_order_meta' ), 10, 2 );

		try {
			$user_id = $order->get_user_id();
			if ( ! $user_id ) {
				throw new Exception( __( 'Customer not found.', 'woocommerce-account-funds' ) );
			}

			$total = $amount;
			if( $this->credit_enabled ){
				if( WooPOS_Credit_Points_Cart_Manager::using('credit') ){
					$credit_amount =  WooPOS_Credit_Points_Cart_Manager::get_used_amount('credit');
				}else{
					$credit_amount =  min( $total, WooPOS_Credit_Points::get_credit( $order->get_user_id(), false ) );
					$total         = $total - $credit_amount;
				}
			}
			if( $this->points_enabled ){
				if( WooPOS_Credit_Points_Cart_Manager::using('points') ){
					$points_amount = WooPOS_Credit_Points_Cart_Manager::get_used_amount('points');
				}else{
					$points_amount = min( $total, WooPOS_Credit_Points::get_points( $order->get_user_id(), 'redeemable_amount', false ) );
					$total         = $total - $points_amount;
				}
			}

			if ( $total > 0 ) {
				$available_funds = 0 + ( $this->credit_enabled ? $credit_amount : 0 ) + ( $this->points_enabled ? $points_amount : 0 );
				throw new Exception( sprintf( __( 'Insufficient funds (amount to pay = %s; available funds = %s).', 'woocommerce-account-funds' ), wc_price( $amount ), wc_price( $available_funds ) ) );
			}

			if(  ! get_post_meta( $order_id, 'woopos_credit_removed', true ) && $this->credit_enabled && $credit_amount!=0){
			    WooPOS_Credit_Points::remove_credit( $order->get_user_id(), $credit_amount );
			    update_post_meta( $order_id, 'woopos_credit_used', $credit_amount );
	            update_post_meta( $order_id, 'woopos_credit_removed', 1 );	
            }
		    if(  ! get_post_meta( $order_id, 'woopos_points_removed', true ) && $this->points_enabled && $points_amount!=0 ){
			    WooPOS_Credit_Points::remove_points( $order->get_user_id(), $points_amount );
			    update_post_meta( $order_id, 'woopos_points_used', $points_amount );
		        update_post_meta( $order_id, 'woopos_points_removed', 1 );
		    }

			$this->complete_payment_for_subscriptions_on_order( $order );

			$note = '';
			if( $this->credit_enabled ){
				$note .= sprintf( __( '%s Credit applied.', 'woocommerce-account-funds' ), $credit_amount );
			}
			if( !empty($note) ) $note .= " ";
			if( $this->points_enabled ){
				$note .= sprintf( __( '%s Points Amount applied.', 'woocommerce-account-funds' ), $points_amount );
			}
			$order->add_order_note( $note );

		} catch ( Exception $e ) {

			$order->add_order_note( $e->getMessage() );
			$this->payment_failed_for_subscriptions_on_order( $order );

			$ret = new WP_Error( 'accountfunds', $e->getMessage() );
		}

		remove_action( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'set_renewal_order_meta' ), 10, 2 );

		return $ret;
	}

	/**
	 * Complete subscriptions payments in a given order.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 */
	protected function complete_payment_for_subscriptions_on_order( $order ) {
		foreach ( $this->get_subscriptions_for_order( $order ) as $subscription ) {
			$subscription->payment_complete();
		}
		do_action( 'processed_subscription_payments_for_order', $order );
	}

	/**
	 * Failed payment for subscriptions in a given order.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 */
	protected function payment_failed_for_subscriptions_on_order( $order ) {
		foreach ( $this->get_subscriptions_for_order( $order ) as $subscription ) {
			$subscription->payment_failed();
		}
		do_action( 'processed_subscription_payment_failure_for_order', $order );
	}

	/**
	 * Get subscriptions from a given order.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 *
	 * @return array List of subscriptions.
	 */
	protected function get_subscriptions_for_order( $order ) {
		return wcs_get_subscriptions_for_order(
			$order,
			array(
				'order_type' => array( 'parent', 'renewal' ),
			)
		);
	}

	/**
	 * Set renewal order meta.
	 *
	 * Set the total to zero as it will be replaced by `_funds_used`.
	 *
	 * @param WC_Order $renewal_order Order from renewal payment
	 *
	 * @return void
	 */
	public function set_renewal_order_meta( $renewal_order ) {
		// Use total from post meta directly to avoid filter in total amount.
		// The _order_total meta is already calculated for total subscription
		// to pay of given order.
		$renewal_order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $renewal_order->id : $renewal_order->get_id();

		$total = get_post_meta( $renewal_order_id, '_order_total', true );
		if( $this->credit_enabled ){
			$credit_amount = min( $total, WooPOS_Credit_Points::get_credit( $order->get_user_id(), false ) );
			$total         = $total - $credit_amount;
			update_post_meta( $renewal_order_id, 'woopos_credit_used', $credit_amount );
		}
		if( $this->points_enabled ){
			$points_amount = min( $total, WooPOS_Credit_Points::get_points( $order->get_user_id(), 'redeemable_amount', false ) );
			$total         = $total - $points_amount;
			update_post_meta( $renewal_order_id, 'woopos_points_used', $points_amount );
		}

		$renewal_order->set_total( 0 );
		$renewal_order->add_order_note( __( 'Account Funds subscription payment completed', 'woocommerce-account-funds' ) );
	}

	/**
	 * Payment method name
	 */
	public function subscription_payment_method_name( $payment_method_to_display, $subscription_details, $order ) {
		$customer_user = version_compare( WC_VERSION, '3.0', '<' ) ? $order->customer_user : $order->get_customer_id();
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		if ( $this->id !== get_post_meta( $order_id, '_recurring_payment_method', true ) || ! $customer_user ) {
			return $payment_method_to_display;
		}
		return sprintf( __( 'Via %s', 'woocommerce-account-funds' ), $this->method_title );
	}
}
