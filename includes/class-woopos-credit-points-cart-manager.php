<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPOS_Credit_Points_Cart_Manager
 */
class WooPOS_Credit_Points_Cart_Manager {
	
	
	public function __construct() {
		add_action( 'woocommerce_review_order_before_order_total', array( $this, 'display_used_credit_points' ) );
		add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'display_used_credit_points' ) );
		add_filter( 'woocommerce_cart_total', array( $this, 'display_total' ) );

		add_action( 'wp', array( $this, 'maybe_use_credit_points' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'output_use_credit_points_notice' ), 6 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'output_use_credit_points_notice' ), 6 );
		add_action( 'woocommerce_before_cart_table', array( $this, 'output_warning_message' ), 6 );

		add_filter( 'woocommerce_calculated_total', array( $this, 'calculated_total' ) );
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'enable_payment_method_for_zero_total'), 10, 3 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'maybe_select_payment_gateway' ), 20, 1 );

		add_filter( 'woocommerce_paypal_args', array( $this, 'filter_paypal_line_item_names' ), 10, 2 );
	}

	/**
	 * Can the user actually apply funds to this cart?
	 * @return bool
	 */
	public static function can_apply($type) {
		switch($type){
			case 'credit':
				$can_apply = 'yes' === get_option( 'woopos_credit_points_store_credit_enable' );
				if ( ! WooPOS_Credit_Points::get_credit( get_current_user_id(), false ) ) {
					$can_apply = false;
				}
				break;
			
			case 'points':
				$can_apply = 'yes' === get_option( 'woopos_credit_points_reward_points_enable' );
				break;
			
			default:
				$can_apply = false;
		}
		if ( self::cart_contains_subscription() || ! is_user_logged_in() ) {
			$can_apply = false;
		}
		if( ! WC()->session->get( 'use-account-' . $type ) && WC()->cart->total <= 0 ){
			$can_apply = false;
		}
		return $can_apply;
	}

	/**
	 * Using funds right now?
	 */
	public static function using($type) {
		switch($type){
			case 'credit':
				$using = ! is_null( WC()->session ) && WC()->session->get( 'use-account-credit' ) && self::can_apply('credit');
				break;
			
			case 'points':
				$using = ! is_null( WC()->session ) && WC()->session->get( 'use-account-points' ) && self::can_apply('points');
				break;
			
			default:
				$using = false;
		}
		return $using;
	}

	/**
	 * Amount of funds being applied
	 * @return float
	 */
	public static function get_used_amount($type) {
		switch($type){
			case 'credit':
				$amount = WC()->session->get( 'used-account-credit' );
				break;
			
			case 'points':
				$amount = WC()->session->get( 'used-account-points' );
				break;
		}
		$amount = $amount ? $amount : 0;
		return $amount;
	}

	/**
	 * Use funds
	 */
	public function maybe_use_credit_points() {
		$apply_first = is_null( WC()->session ) ? false : WC()->session->get( 'apply-first' );
		
		if ( ! empty( $_POST['wc_woopos_account_credit_apply'] ) && self::can_apply('credit') ) {
			WC()->session->set( 'use-account-credit', true );
			if( empty($apply_first) ){
				WC()->session->set( 'apply-first', 'credit' );
			}
		}
		if ( ! empty( $_GET['remove_woopos_account_credit'] )  ) {
			WC()->session->set( 'use-account-credit', false );
			WC()->session->set( 'used-account-credit', false );
			if( $apply_first == 'credit' ){
				if( self::using('points') ){
					WC()->session->set( 'apply-first', 'points' );
				}else{
					WC()->session->set( 'apply-first', false );
				}
			}
			wp_redirect( esc_url_raw( remove_query_arg( 'remove_woopos_account_credit' ) ) );
			exit;
		}
		
		if ( ! empty( $_POST['wc_woopos_account_points_apply'] ) && self::can_apply('points') ) {
			WC()->session->set( 'use-account-points', true );
			if( empty($apply_first) ){
				WC()->session->set( 'apply-first', 'points' );
			}
		}
		if ( ! empty( $_GET['remove_woopos_account_points'] )  ) {
			WC()->session->set( 'use-account-points', false );
			WC()->session->set( 'used-account-points', false );
			if( $apply_first == 'points' ){
				if( self::using('credit') ){
					WC()->session->set( 'apply-first', 'credit' );
				}else{
					WC()->session->set( 'apply-first', false );
				}
			}
			wp_redirect( esc_url_raw( remove_query_arg( 'remove_woopos_account_points' ) ) );
			exit;
		}
	}

	/**
	 * Show a notice to apply points towards your purchase
	 */
	public static function output_use_credit_points_notice(){
		if( !self::using('credit') && self::can_apply('credit') ){
			$message = '<div class="woocommerce-info woopos-credit-points-apply-notice">';
			$message .= '<form class="woopos-credit-points-apply" method="post">';
			if( WooPOS_Credit_Points::get_credit(null, false) > 0 ){
				$message .= '<input type="submit" class="button woopos-credit-points-apply-button" name="wc_woopos_account_credit_apply" value="' . __( 'Use Store Credit', 'woopos_credit_points_use_store_credit_button' ) . '" />';
			}
			$message .= WooPOS_Credit_Points::get_message('credit');
			$message .= '</form>';
			$message .= '</div>';
			echo $message;
		}
		if( !self::using('points') && self::can_apply('points') ){
			$message = '<div class="woocommerce-info woopos-credit-points-apply-notice">';
			$message .= '<form class="woopos-credit-points-apply" method="post">';
			if( WooPOS_Credit_Points::get_points(null, 'redeemable_amount', false) > 0 ){
				$message .= '<input type="submit" class="button woopos-credit-points-apply-button" name="wc_woopos_account_points_apply" value="' . __( 'Use Points', 'woopos_credit_points_use_points_button' ) . '" />';
			}
			$message .= WooPOS_Credit_Points::get_message('points');
			$message .= '</form>';
			$message .= '</div>';
			echo $message;
		}
	}
	
	public function output_warning_message(){
		$warning_message = WooPOS_Credit_Points::get_message('warning');
		if( !empty($warning_message) ){
			$message = '<div class="woocommerce-error woopos-credit-points-warning-message">';
			$message .= $warning_message;
			$message .= '</div>';
			echo $message;
		}
	}

	/**
	 * Subscription?
	 * @return bool
	 */
	public static function cart_contains_subscription() {
		return class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription();
	}

	/**
	 * Show amount of funds used
	 */
	public function display_used_credit_points() {
		if ( self::using('credit') ) {
			$credit_amount = self::get_used_amount('credit');
			if ( $credit_amount > 0 ) {
				?>
				<tr class="order-discount account-credit-discount">
					<th><?php _e( 'Store Credit', 'woopos_credit_points_store_credit' ); ?></th>
					<td>-<?php echo wc_price( $credit_amount ); ?> <a href="<?php echo esc_url( add_query_arg( 'remove_woopos_account_credit', true, get_permalink( is_cart() ? wc_get_page_id( 'cart' ) : wc_get_page_id( 'checkout' ) ) ) ); ?>"><?php _e( '[Remove]', 'woopos_store_credit_credit_remove' ); ?></a></td>
				</tr>
				<?php
			}
		}
		if ( self::using('points') ) {
			$points_amount = self::get_used_amount('points');
			if ( $points_amount > 0 ) {
				?>
				<tr class="order-discount account-points-discount">
					<th><?php _e( 'Points Amount', 'woopos_store_credit_points_amount' ); ?></th>
					<td>-<?php echo wc_price( $points_amount ); ?> <a href="<?php echo esc_url( add_query_arg( 'remove_woopos_account_points', true, get_permalink( is_cart() ? wc_get_page_id( 'cart' ) : wc_get_page_id( 'checkout' ) ) ) ); ?>"><?php _e( '[Remove]', 'woopos_store_credit_points_remove' ); ?></a></td>
				</tr>
				<?php
			}
		}
	}

	/**
	 * Calculated total
	 * @param  string $total
	 * @return string
	 */
	public static function display_total( $total ) {
		if ( self::using('credit') || self::using('points') ) {
			return wc_price( WC()->cart->total );
		}
		return $total;
	}

	/**
	 * Calculated total
	 * @param  float $total
	 * @return float
	 */
	public function calculated_total( $total ) {
		$apply_first = WC()->session->get( 'apply-first' );
		if( $apply_first == 'points' ){
			if ( self::using('points') ) {
				$points_amount = min( $total, WooPOS_Credit_Points::get_points( null, 'redeemable_amount', false ) );
				$total         = $total - $points_amount;
				WC()->session->set( 'used-account-points', $points_amount );
			}
			if ( self::using('credit') ) {
				$credit_amount = min( $total, WooPOS_Credit_Points::get_credit( null, false ) );
				$total         = $total - $credit_amount;
				WC()->session->set( 'used-account-credit', $credit_amount );
			}
		}else{
			if ( self::using('credit') ) {
				$credit_amount = min( $total, WooPOS_Credit_Points::get_credit( null, false ) );
				$total         = $total - $credit_amount;
				WC()->session->set( 'used-account-credit', $credit_amount );
			}
			if ( self::using('points') ) {
				$points_amount = min( $total, WooPOS_Credit_Points::get_points( null, 'redeemable_amount', false ) );
				$total         = $total - $points_amount;
				WC()->session->set( 'used-account-points', $points_amount );
			}
		}
		return $total;
	}
	
	public function enable_payment_method_for_zero_total( $needs_payment, $cart ){
		if( ! $needs_payment && $cart->total <= 0 && ( self::using('credit') || self::using('points') ) ){
			$needs_payment = true;
		}
		return $needs_payment;
	}
	public function maybe_select_payment_gateway( $available_gateways ){
       if( !WC()->cart || (WC()->cart->total <= 0 && ( self::using('credit') || self::using('points')  ) )){	    
			foreach($available_gateways as $key => $gateway){
				if( $key !== 'woopos_account_credit_points' ){
					unset($available_gateways[ $key ]);
				}
			}
		}
		return $available_gateways;
	}

	/**
	 * When AF is applied it causes order total mismatch with total from order
	 * items because AF is filtering the order total based on amount funds used.
	 *
	 * This filter adjust the line item name to indicate the amount is with tax
	 * and AF applied already.
	 *
	 * @since 2.0.11
	 * @version 2.1.8
	 *
	 * @param array    $paypal_args PayPal args.
	 * @param WC_Order $order       Order object.
	 *
	 * @return array PayPal args.
	 */
	public function filter_paypal_line_item_names( $paypal_args, $order ) {
		$credit_amount = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_credit_used', true );
		$points_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_points_used', true );
		if ( empty( $credit_amount ) && empty( $points_used ) ) {
			return $paypal_args;
		}

		$item_indexes = $this->get_paypal_line_item_indexes( $paypal_args );
		foreach ( $item_indexes as $index ) {
			$key = 'item_name_' . $index;
			$val = $paypal_args[ $key ];

			$paypal_args[ $key ] = sprintf(
				__( '%s (with tax, discount, store credit and points applied)', 'woopos-credit-points' ),
				$val
			);
		}

		return $paypal_args;

	}

	/**
	 * Get the item indexes from all paypal itmes.
	 *
	 * Only indexes with existing name, amount and quantity are added.
	 *
	 * @since 2.0.11
	 *
	 * @param array $paypal_args PayPal Args.
	 *
	 * @return array Item indexes.
	 */
	public function get_paypal_line_item_indexes( $paypal_args ) {
		$item_indexes = array();

		foreach ( $paypal_args as $key => $arg ) {
			if ( ! preg_match( '/item_name_/', $key ) ) {
				continue;
			}

			$index = str_replace( 'item_name_', '', $key );

			// Make sure the item name, amount and quantity values exist.
			if ( isset( $paypal_args[ 'amount_' . $index ] )
				&& isset( $paypal_args[ 'item_name_' . $index ] )
				&& isset( $paypal_args[ 'quantity_' . $index ] ) ) {
				$item_indexes[] = $index;
			}
		}

		return $item_indexes;
	}
}

new WooPOS_Credit_Points_Cart_Manager();
