<?php
/**
 * Give Stripe Scripts
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Frontend javascript
 *
 * @since  1.0
 *
 * @return void
 */
function give_stripe_frontend_scripts() {

	//Which mode are we in? Get the corresponding key.
	if ( give_is_test_mode() ) {
		$test_pub_key    = give_get_option( 'test_publishable_key' );
		$publishable_key = isset( $test_pub_key ) ? trim( $test_pub_key ) : '';
	} else {
		$live_pub_key    = give_get_option( 'live_publishable_key' );
		$publishable_key = isset( $live_pub_key ) ? trim( $live_pub_key ) : '';
	}

	//set vars for AJAX.
	$stripe_vars = array(
		'currency'         => give_get_currency(),
		'sitename'         => give_get_option( 'stripe_checkout_name' ),
		'publishable_key'  => $publishable_key,
		'checkout_image'   => give_get_option( 'stripe_checkout_image' ),
		'checkout_alipay'  => give_get_option( 'stripe_checkout_alipay' ),
		'checkout_bitcoin' => give_get_option( 'stripe_checkout_bitcoin' ),
		'zipcode_option'   => true,
		'give_version'     => get_option( 'give_version' ),
	);

	// Is Stripe's checkout enabled?
	$stripe_checkout = give_get_option( 'stripe_checkout_enabled' );
	if ( ! empty( $stripe_checkout ) ) {

		//Stripe checkout js.
		wp_register_script( 'give-stripe-checkout-js', 'https://checkout.stripe.com/checkout.js', array( 'jquery' ) );
		wp_enqueue_script( 'give-stripe-checkout-js' );

		$deps = array(
			'jquery',
			'give-stripe-checkout-js',
		);

		// If debug is enabled there are different script dependencies
		// because we concat all scripts into one.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG == true ) {
			$deps[] = 'give-checkout-global';
		} else {
			$deps[] = 'give';
		}

		//Give Stripe Checkout JS.
		wp_register_script( 'give-stripe-popup-js', GIVE_STRIPE_PLUGIN_URL . 'assets/js/give-stripe-popup.js', $deps, GIVE_STRIPE_VERSION );
		wp_enqueue_script( 'give-stripe-popup-js' );
		wp_localize_script( 'give-stripe-popup-js', 'give_stripe_vars', $stripe_vars );

		return;
	}

	// in fallback mode?
	$fallback = give_get_option( 'stripe_js_fallback' );
	if ( ! empty( $fallback ) ) {
		return;
	}

	$publishable_key = null;

	// Load Stripe on-page checkout scripts.
	if ( apply_filters( 'give_stripe_js_loading_conditions', give_is_gateway_active( 'stripe' ) ) ) {

		wp_register_script( 'give-stripe-js', 'https://js.stripe.com/v2/', array( 'jquery' ) );
		wp_enqueue_script( 'give-stripe-js' );

		wp_register_script( 'give-stripe-onpage-js', GIVE_STRIPE_PLUGIN_URL . 'assets/js/give-stripe.js', array(
			'jquery',
			'give-stripe-js'
		), GIVE_STRIPE_VERSION );
		wp_enqueue_script( 'give-stripe-onpage-js' );
		wp_localize_script( 'give-stripe-onpage-js', 'give_stripe_vars', $stripe_vars );

	}

}

add_action( 'wp_enqueue_scripts', 'give_stripe_frontend_scripts' );

/**
 * Load Admin javascript
 *
 * @since  1.0
 *
 * @param  $hook
 *
 * @return void
 */
function give_stripe_admin_js( $hook ) {

	if ( isset( $_GET['page'] ) && $_GET['page'] == 'give-settings' ) {

		wp_register_script( 'give-stripe-settings-js', GIVE_STRIPE_PLUGIN_URL . 'assets/js/give-stripe-settings.js', 'jquery', GIVE_STRIPE_VERSION );
		wp_enqueue_script( 'give-stripe-settings-js' );

		wp_register_style( 'give-stripe-settings-css', GIVE_STRIPE_PLUGIN_URL . 'assets/css/give-stripe-settings.css', false, GIVE_STRIPE_VERSION );
		wp_enqueue_style( 'give-stripe-settings-css' );

	}


}

add_action( 'admin_enqueue_scripts', 'give_stripe_admin_js', 100 );

/**
 * Load Transaction-specific admin javascript.
 *
 * Allows the user to refund non-recurring purchases.
 *
 * @since  1.0
 *
 * @param int $payment_id
 */
function give_stripe_admin_payment_js( $payment_id = 0 ) {

	if (
		'stripe' !== give_get_payment_gateway( $payment_id )
		&& 'stripe_ach' !== give_get_payment_gateway( $payment_id )
	) {
		return;
	}
	?>
    <script type="text/javascript">
		jQuery(document).ready(function ($) {

			$('select[name=give-payment-status]').change(function () {

				if ('refunded' == $(this).val()) {

					$(this).parent().parent().append('<p class="give-stripe-refund"><input type="checkbox" id="give_refund_in_stripe" name="give_refund_in_stripe" value="1"/><label for="give_refund_in_stripe"><?php esc_html_e( 'Refund Charge in Stripe?', 'give-stripe' ); ?></label></p>');

				} else {

					$('.give-stripe-refund').remove();

				}

			});
		});
    </script>
	<?php

}

add_action( 'give_view_order_details_before', 'give_stripe_admin_payment_js', 100 );


/**
 * WooCommerce checkout compatibility.
 *
 * This prevents Give from outputting scripts on Woo's checkout page.
 *
 * @since 1.4.3
 *
 * @param $ret
 *
 * @return bool
 */
function give_stripe_woo_script_compatibility( $ret ) {

	if (
		function_exists( 'is_checkout' )
		&& is_checkout()
	) {
		return false;
	}


	return $ret;

}

add_filter( 'give_stripe_js_loading_conditions', 'give_stripe_woo_script_compatibility', 10, 1 );


/**
 * EDD checkout compatibility.
 *
 * This prevents Give from outputting scripts on EDD's checkout page.
 *
 * @since 1.4.6
 *
 * @param $ret
 *
 * @return bool
 */
function give_stripe_edd_script_compatibility( $ret ) {

    if (
		function_exists( 'edd_is_checkout' )
		&& edd_is_checkout()
	) {
		return false;
	}


	return $ret;

}

add_filter( 'give_stripe_js_loading_conditions', 'give_stripe_edd_script_compatibility', 10, 1 );
