jQuery(function() {
    jQuery( '#order_review' ).on('change', 'input[name=payment_method]', function() {
    	if ( jQuery('#payment_method_account_credit_points').length ) {
    		jQuery('body').trigger( 'update_checkout' );
    	}
    });
});