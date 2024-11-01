<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPOS_Credit_Points_Admin
 */
class WooPOS_Credit_Points_Admin {

	/** @var Settings Tab ID */
	private $settings_tab_id = 'woopos_credit_points';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Users
		add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
		add_action( 'manage_users_custom_column', array( $this, 'manage_users_custom_column' ), 10, 3 );

		// Settings
		add_action( 'woocommerce_settings_tabs_array', array( $this, 'add_woocommerce_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_' . $this->settings_tab_id, array( $this, 'woocommerce_settings_tab_action' ), 10 );
		add_action( 'woocommerce_update_options_' . $this->settings_tab_id, array( $this, 'woocommerce_settings_save' ), 10 );
	}

	/**
	 * Add column
	 * @param  array $columns
	 * @return array
	 */
	public function manage_users_columns( $columns ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$columns['woopos_account_credit'] = __( 'Store Credit', 'woopos-credit-points' );
			$columns['woopos_account_points'] = __( 'Points Balance', 'woopos-credit-points' );
		}
		return $columns;
	}

	/**
	 * Column value
	 * @param  string $value
	 * @param  string $column_name
	 * @param  int $user_id
	 * @return string
	 */
	public function manage_users_custom_column( $value, $column_name, $user_id ) {
		if ( $column_name === 'woopos_account_credit' ) {
        	$credit = get_user_meta( $user_id, 'woopos_account_credit', true );
        	$credit = $credit ? $credit : 0;
        	$value = wc_price( $credit );
   		}
		if ( $column_name === 'woopos_account_points' ) {
        	$points = get_user_meta( $user_id, 'woopos_account_points', true );
        	$points = $points ? $points : 0;

			$points_redeemable = get_user_meta( $user_id, 'woopos_account_points_redeemable', true );
        	$points_redeemable = $points_redeemable ? $points_redeemable : 0;

			$points_redeemable_amount = get_user_meta( $user_id, 'woopos_account_points_redeemable_amount', true );
        	$points_redeemable_amount = $points_redeemable_amount ? $points_redeemable_amount : 0;
			$points_redeemable_amount = wc_price($points_redeemable_amount);

        	$value = "{$points} / {$points_redeemable} / {$points_redeemable_amount}";
   		}
    	return $value;
	}

	/**
	 * Returns settings array.
	 * @return array settings
	 */
	public function get_settings() {
		$legend_keys = array(
			'{store_credit_balance}' => 'will be replaced with Store Credit balance',
			'{points_balance}'       => 'will be replaced with Points balance',
			'{redeemable_points}'    => 'will be replaced with usable Points balance',
			'{redeemable_amount}'    => 'will be replaced with usable Points currency value'
		);
		$legend = '<ul>';
		foreach($legend_keys as $key => $desc){
			$legend .= '<li>';
			$legend .= "<strong><code>{$key}</code></strong> {$desc}";
			$legend .= '</li>';
		}
		$legend .= '</ul>';

		$settings = array(
			array(
				'name' => __( 'WooPOS Credit & Points', 'woopos-credit-points' ),
				'type' => 'title',
				'desc' => $legend,
				'id' => 'woopos_credit_points_title'
			),
			array(
				'name'     => __( 'Enable WooPOS Store Credit', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_store_credit_enable',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;'
			),
			array(
				'name'     => __( 'Store Credit Message', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_store_credit_message',
				'type'     => 'textarea',
				'css'      => 'width: 100%;',
				'default'  => __( 'You have {store_credit_balance} store credit on your account.', 'woopos-credit-points' )
			),
			array(
				'name'     => __( 'Enable WooPOS Reward Points', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_reward_points_enable',
				'type'     => 'checkbox',
				'css'      => 'min-width:300px;'
			),
			array(
				'name'     => __( 'Message when Redeemable Amount = 0', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_reward_points_zero_message',
				'type'     => 'textarea',
				'css'      => 'width: 100%;',
				'default'  => __( 'You will earn one point on every dollar you spend. 20 points = 1$', 'woopos-credit-points' )
			),
			array(
				'name'     => __( 'Message when Redeemable Amount > 0', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_reward_points_not_zero_message',
				'type'     => 'textarea',
				'css'      => 'width: 100%;',
				'default'  => __( 'You have {points_balance} points. You can redeem {redeemable_points} for a {redeemable_amount} discount for this order!', 'woopos-credit-points' )
			),
			array(
				'name'     => __( 'Warning Message', 'woopos-credit-points' ),
				'id'       => 'woopos_credit_points_warning_message',
				'type'     => 'textarea',
				'css'      => 'width: 100%;',
				'default'  => __( '', 'woopos-credit-points' )
			),
			array(
				'type' => 'sectionend',
				'id' => 'woopos_credit_points_title'
			)
		);

		return apply_filters( 'woopos_credit_points_settings', $settings );
	}

	/**
	 * Add settings tab to woocommerce
	 */
	public function add_woocommerce_settings_tab( $settings_tabs ) {
		$settings_tabs[ $this->settings_tab_id ] = __( 'WooPOS Credit & Points', 'woopos-credit-points' );
		return $settings_tabs;
	}

	/**
	 * Do this when viewing our custom settings tab(s). One function for all tabs.
	 */
	public function woocommerce_settings_tab_action() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Save settings in a single field in the database for each tab's fields (one field per tab).
	 */
	public function woocommerce_settings_save() {
		woocommerce_update_options( $this->get_settings() );
	}
}

new WooPOS_Credit_Points_Admin();
