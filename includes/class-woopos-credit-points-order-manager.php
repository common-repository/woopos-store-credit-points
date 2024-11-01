<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPOS_Credit_Points_Order_Manager
 */
class WooPOS_Credit_Points_Order_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'woocommerce_checkout_update_order_meta' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_remove_funds' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_remove_funds' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'maybe_remove_funds' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_remove_funds' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_restore_funds' ) );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'woocommerce_get_order_item_totals' ), 10, 2 );
		add_filter( version_compare( WC_VERSION, '3.0', '<' ) ? 'woocommerce_order_amount_total' : 'woocommerce_order_get_total', array( 'WooPOS_Credit_Points_Order_Manager', 'adjust_total_to_include_funds' ), 10, 2 );

		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'add_funds_used_after_order_total' ) );
		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'remove_funds_from_recalculation' ), 10, 2 );
	}

	/**
	 * Adjust total to exclude amount paid with funds when doing order save
	 * for recalculation. Note that because the recalculation doesn't use
	 * WC_Order::get_total we have to use another hook for this.
	 *
	 * @version 2.1.10
	 *
	 * @param bool     $and_taxes Whether taxes are included.
	 * @param WC_Order $order Order object.
	 */
	public function remove_funds_from_recalculation( $and_taxes, $order ) {
		$_credit_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_credit_used', true );
		$_points_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_points_used', true );

		// Calling `$order->get_total()` means firing again woocommerce_order_get_total
		// or woocommerce_order_amount_total hook. We need to remove the filter
		// temporarily.
		//
		// @see https://github.com/woocommerce/woocommerce-account-funds/issues/75.
		self::remove_order_total_filter_adjustment();

		$total = floatval( $order->get_total() ) - floatval( $_credit_used ) - floatval( $_points_used );
		$order->set_total( round( $total, wc_get_price_decimals() ) );

		self::add_order_total_filter_adjustment();
	}

	/**
	 * Try to remove user funds (if not already removed)
	 *
	 * @param  int $order_id
	 */
	public function maybe_remove_funds( $order_id ) {
		if ( null !== WC()->session ) {
			WC()->session->set( 'use-account-credit', false );
			WC()->session->set( 'used-account-credit', false );
			WC()->session->set( 'use-account-points', false );
			WC()->session->set( 'used-account-points', false );
		}

		$order       = wc_get_order( $order_id );
		$customer_id = $order->get_user_id();

		if ( $customer_id ){
			if( ! get_post_meta( $order_id, 'woopos_credit_removed', true ) && $credit = get_post_meta( $order_id, 'woopos_credit_used', true ) ) {
				WooPOS_Credit_Points::remove_credit( $customer_id, $credit );
				$order->add_order_note( sprintf( __( 'Removed %s Store Credit from user #%d', 'woopos-credit-points' ), wc_price( $credit ), $customer_id ) );
			    update_post_meta( $order_id, 'woopos_credit_removed', 1 );
		    }
			if( ! get_post_meta( $order_id, 'woopos_points_removed', true ) && $points_amount = get_post_meta( $order_id, 'woopos_points_used', true ) ) {
				WooPOS_Credit_Points::remove_points( $customer_id, $points_amount );
				$order->add_order_note( sprintf( __( 'Removed %s Points Amount from user #%d', 'woopos-credit-points' ), wc_price($points_amount), $customer_id ) );
			    update_post_meta( $order_id, 'woopos_points_removed', 1 );
		    }
		}
	}

	/**
	 * Remove user funds when an order is created
	 *
	 * @param  int $order_id
	 */
	public function woocommerce_checkout_update_order_meta( $order_id, $posted ) {
		if ( $posted['payment_method'] !== 'woopos_account_credit_points' ) {
			if ( WooPOS_Credit_Points_Cart_Manager::using('credit') ){
				$used_credit = WooPOS_Credit_Points_Cart_Manager::get_used_amount('credit');
				update_post_meta( $order_id, 'woopos_credit_used', $used_credit );
			    add_post_meta( $order_id, 'woopos_credit_removed', 0 );
            }
			if ( WooPOS_Credit_Points_Cart_Manager::using('points') ){
				$used_points = WooPOS_Credit_Points_Cart_Manager::get_used_amount('points');
				update_post_meta( $order_id, 'woopos_points_used', $used_points );
                add_post_meta( $order_id, 'woopos_points_removed', 0 );
            }
		}
	}

	/**
	 * Restore user funds when an order is cancelled
	 *
	 * @param  int $order_id
	 */
	public function maybe_restore_funds( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $credit = get_post_meta( $order_id, 'woopos_credit_used', true ) ) {
			WooPOS_Credit_Points::add_credit( $order->get_user_id(), $credit );
			$order->add_order_note( sprintf( __( 'Restored %s store credit to user #%d', 'woopos-credit-points' ), wc_price( $credit ), $order->get_user_id() ) );
		}
		if ( $points = get_post_meta( $order_id, 'woopos_points_used', true ) ) {
			WooPOS_Credit_Points::add_points( $order->get_user_id(), $points );
			$order->add_order_note( sprintf( __( 'Restored %s Points Amount to user #%d', 'woopos-credit-points' ), $points, $order->get_user_id() ) );
		}
	}

	/**
	 * Order total display
	 */
	public function woocommerce_get_order_item_totals( $rows, $order ) {
		if ( $_credit_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_credit_used', true ) ) {
			$rows['credit_used'] = array(
				'label' => __( 'Store Credit Used:', 'woopos-credit-points' ),
				'value'	=> wc_price( $_credit_used )
			);
		}
		if ( $_points_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_points_used', true ) ) {
			$rows['points_used'] = array(
				'label' => __( 'Points Used:', 'woopos-credit-points' ),
				'value'	=> wc_price( $_points_used )
			);
		}
		return $rows;
	}

	/**
	 * Adjust total to include amount paid with funds
	 *
	 * @version 2.1.3
	 *
	 * @param float    $total Order total.
	 * @param WC_Order $order Order object.
	 *
	 * @return float Order total.
	 */
	public static function adjust_total_to_include_funds( $total, $order ) {
		// Don't interfere with total while paying for order.
		if ( is_checkout() || ! empty( $wp->query_vars['order-pay'] ) ) {
			return $total;
		}
		$_credit_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_credit_used', true );
		$_points_used = get_post_meta( version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id(), 'woopos_points_used', true );

		// Calling `$order->get_total()` means firing again woocommerce_order_get_total
		// or woocommerce_order_amount_total hook. We need to remove the filter
		// temporarily.
		//
		// @see https://github.com/woocommerce/woocommerce-account-funds/issues/75.
		self::remove_order_total_filter_adjustment();

		$total = floatval( $order->get_total() ) + floatval( $_credit_used ) + floatval( $_points_used );

		self::add_order_total_filter_adjustment();

		return $total;
	}

	/**
	 * Add the filter to order total that will add amount of funds being used.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 */
	protected static function add_order_total_filter_adjustment() {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			add_filter( 'woocommerce_order_get_total', array( __CLASS__, 'adjust_total_to_include_funds' ), 10, 2 );
		} else {
			add_filter( 'woocommerce_order_amount_total', array( __CLASS__, 'adjust_total_to_include_funds' ), 10, 2 );
		}
	}

	/**
	 * Remove the filter to order total that will add amount of funds being used.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 */
	protected static function remove_order_total_filter_adjustment() {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			remove_filter( 'woocommerce_order_get_total', array( __CLASS__, 'adjust_total_to_include_funds' ), 10, 2 );
		} else {
			remove_filter( 'woocommerce_order_amount_total', array( __CLASS__, 'adjust_total_to_include_funds' ), 10, 2 );
		}
	}

	/**
	 * Add rows in edit order screen to display 'Funds Used' and 'Order Total
	 * after Funds Used'.
	 *
	 * @since 2.1.7
	 * @version 2.1.7
	 *
	 * @param int $order_id Order ID.
	 */
	public function add_funds_used_after_order_total( $order_id ) {
		$credit_used = floatval( get_post_meta( $order_id, 'woopos_credit_used', true ) );
		$points_used = floatval( get_post_meta( $order_id, 'woopos_points_used', true ) );
		if ( $credit_used <= 0 && $points_used <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if( $credit_used > 0 ){
?>
			<tr>
				<td class="label"><?php _e( 'Store Credit Used', 'woopos-credit-points' ); ?>:</td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price( $credit_used ); ?>
				</td>
			</tr>
			<?php
		}
		if( $points_used > 0 ){
			?>
			<tr>
				<td class="label"><?php _e( 'Points Used', 'woopos-credit-points' ); ?>:</td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price( $points_used ); ?>
				</td>
			</tr>
			<?php
		}
		?>
		<tr>
			<td class="label"><?php _e( 'Order Total after Credit and Points Used', 'woopos-credit-points' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">
				<?php
				self::remove_order_total_filter_adjustment();
				echo wc_price( $order->get_total() );
				self::add_order_total_filter_adjustment();
				?>
			</td>
		</tr>
		<?php
	}
}

new WooPOS_Credit_Points_Order_Manager();
