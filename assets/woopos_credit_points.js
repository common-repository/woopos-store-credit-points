jQuery(function() {
    $( '#order_review' ).on('change', 'input[name=payment_method]', function() {
    	if ( $('#payment_method_woopos_account_credit_points').length ) {
    		$('body').trigger( 'update_checkout' );
    	}
    });
});