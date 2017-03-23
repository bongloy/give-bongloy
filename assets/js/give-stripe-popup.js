/**
 * Give - Stripe Popup Checkout JS
 */
var give_global_vars, give_stripe_vars;

/**
 * On document ready setup Stripe events.
 */
jQuery( document ).ready(function ($) {
	// Cache donation button title early to reset it if stripe checkout popup close.
	var donate_button_titles = [], stripe_handler = [];
	$( 'input[type="submit"].give-submit' ).each(function (index, $submit_btn) {
		$submit_btn = $( $submit_btn );
		var $form = $submit_btn.parents( 'form' ),
			form_id = $( 'input[name="give-form-id"]', $form ).val();

		donate_button_titles[ form_id ] = $submit_btn.val();
	});

	/**
	 * On form submit prevent submission for Stripe only.
	 */
	$( 'form[id^=give-form]' ).on('submit', function (e) {

		// Form that has been submitted.
		var $form = $( this );

		// Check that Stripe is indeed the gateway chosen.
		if ( ! give_is_stripe_gateway_selected( $form ) || ( typeof $form[0].checkValidity === "function" && $form[0].checkValidity() === false ) ) {
			return true;
		}

		e.preventDefault();

		return false;
	});

	/**
	 * When the submit button is clicked.
	 */
	$( 'body' ).on('click touchend', 'form[id^=give-form] input[name="give-purchase"].give-submit',function (e) {
		var $form = $( e.target ).parents( 'form' ),
			form_id = $( 'input[name="give-form-id"]', $form ).val(),
			donor_email = $form.find( 'input[name="give_email"]' ).val(),
			form_name = $form.find( 'input[name="give-form-title"]' ).val(),
			amount = give_stripe_format_currency( $form.find( '.give-final-total-amount' ).data( 'total' ) ),
			checkout_image = (give_stripe_vars.checkout_image.length > 0) ? give_stripe_vars.checkout_image : '',
			checkout_bitcoin = (give_stripe_vars.checkout_bitcoin.length > 0),
			checkout_alipay = (give_stripe_vars.checkout_alipay.length > 0),
			zipcode_option = (give_stripe_vars.zipcode_option.length > 0);

		// Check that Stripe is indeed the gateway chosen.
		if ( ! give_is_stripe_gateway_selected( $form ) || ( ( typeof $form[0].checkValidity === "function" && $form[0].checkValidity() === false ) ) ) {
			return;
		}

		// Set stripe handler for form.
		if ( 'undefined' != stripe_handler[form_id] ) {
			stripe_handler[form_id ] = StripeCheckout.configure({
				key: give_stripe_vars.publishable_key,
				image: checkout_image,
				bitcoin: checkout_bitcoin,
				alipay: checkout_alipay,
				locale: 'auto',
				token: function (token) {
					// Insert the token into the form so it gets submitted to the server.
					$form.append( "<input type='hidden' name='give_stripe_token' value='" + token.id + "' />" );
					// Submit form after charge token brought back from Stripe.
					$form.get( 0 ).submit();
				},
				closed: function () {
					// Remove loading animations.
					$form.find( '.give-loading-animation' ).hide();

					// Reenable submit button and add back text.
					$form.find( ':submit' ).prop( 'disabled', false ).val( donate_button_titles[form_id] );
				}
			});
		}

		// Open checkout
		stripe_handler[form_id].open({
			name: give_stripe_vars.sitename,
			description: form_name,
			amount: amount,
			zipCode: zipcode_option,
			email: donor_email,
			currency: give_stripe_vars.currency
		});
	});

	/**
	 * Format Stripe Currency
	 *
	 * @param price
	 * @returns {number}
	 */
	function give_stripe_format_currency(price) {
		return Math.abs( parseFloat( accounting.unformat( price, give_global_vars.decimal_separator ) ) ) * 100;
	}

	/**
	 * Check if stripe gateway selected or not
	 *
	 * @param $form
	 * @returns {boolean}
	 */
	function give_is_stripe_gateway_selected( $form ){
		return ( $( 'input[name="give-gateway"]', $form ).val() === 'stripe' )
	}
});
