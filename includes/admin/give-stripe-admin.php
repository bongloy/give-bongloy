<?php
/**
 * Stripe Admin Functions
 *
 * @package     Give
 * @copyright   Copyright (c) 2015, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the gateway settings
 *
 * @access      public
 * @since       1.0
 *
 * @param $settings
 *
 * @return array
 */
function give_stripe_add_settings( $settings ) {

	$stripe_settings = array(
		array(
			'name' => __( 'Bongloy Settings', 'give-stripe' ),
			'desc' => '<hr>',
			'id'   => 'give_title_stripe',
			'type' => 'give_title'
		),
		array(
			'name' => __( 'Live Secret Key', 'give-stripe' ),
			'desc' => __( 'Enter your live secret key, found in your Bongloy Account Settings.', 'give-stripe' ),
			'id'   => 'live_secret_key',
			'type' => 'api_key',
		),
		array(
			'name' => __( 'Live Publishable Key', 'give-stripe' ),
			'desc' => __( 'Enter your live publishable key, found in your Bongloy Account Settings.', 'give-stripe' ),
			'id'   => 'live_publishable_key',
			'type' => 'text'
		),
		array(
			'name' => __( 'Test Secret Key', 'give-stripe' ),
			'desc' => __( 'Enter your test secret key, found in your Bongloy Account Settings.', 'give-stripe' ),
			'id'   => 'test_secret_key',
			'type' => 'api_key'
		),
		array(
			'name' => __( 'Test Publishable Key', 'give-stripe' ),
			'desc' => __( 'Enter your test publishable key, found in your Bongloy Account Settings.', 'give-stripe' ),
			'id'   => 'test_publishable_key',
			'type' => 'text',
		),
		array(
			'name' => __( 'Collect Billing Details', 'give-stripe' ),
			'desc' => __( 'This option will enable the billing details section for Bongloy which requires the donor\'s address to complete the donation. These fields are not required by Bongloy to process the transaction, but you may have the need to collect the data.', 'give-stripe' ),
			'id'   => 'stripe_collect_billing',
			'type' => 'checkbox'
		),
		array(
			'name' => __( 'Preapprove Only?', 'give-stripe' ),
			'desc' => __( 'Check this if you would like to preapprove payments but <strong>not charge until up to seven days</strong> after the donation has been made.', 'give-stripe' ),
			'id'   => 'stripe_preapprove_only',
			'type' => 'checkbox'
		),
		array(
			'name' => __( 'Bongloy JS Incompatibility', 'give-stripe' ),
			'desc' => __( 'If your site has problems with processing cards using Bongloy JS, check this option to use a fallback method of processing.', 'give-stripe' ),
			'id'   => 'stripe_js_fallback',
			'type' => 'checkbox'
		),
		array(
			'name' => __( 'Enable Bongloy Checkout', 'give-stripe' ),
			'desc' => sprintf( __( 'This option will enable <a href="%s" target="_blank">Bongloy\'s modal checkout</a> where the donor will complete the donation rather than the default credit card fields on page.', 'give-stripe' ), 'https://www.bongloy.com/documentation#bongloy_checkout' ),
			'id'   => 'stripe_checkout_enabled',
			'type' => 'checkbox',
		),
		array(
			'name'        => __( 'Checkout Heading', 'give-stripe' ),
			'desc'        => __( 'This is the main heading within the modal checkout. Typically, this is the name of your organization, cause, or website.', 'give-stripe' ),
			'id'          => 'stripe_checkout_name',
			'row_classes' => 'stripe-checkout-field',
			'default'     => get_bloginfo( 'name' ),
			'type'        => 'text'
		),
		array(
			'name'        => __( 'Accept Alipay', 'give-stripe' ),
			'desc'        => __( 'Enable the ability to accept payments via Alipay, China\'s most popular payment method. Only Bongloy users in the United States can accept Alipay for USD payments.', 'give-stripe' ),
			'id'          => 'stripe_checkout_alipay',
			'row_classes' => 'stripe-checkout-field',
			'type'        => 'checkbox'
		),
		array(
			'name'        => __( 'Accept Bitcoin', 'give-stripe' ),
			'desc'        => __( 'Enable the ability to accept Bitcoin, a digital cryptocurrency, alongside other types of payment. A USD-denominated bank account is required before you can accept Bitcoin payments.', 'give-stripe' ),
			'id'          => 'stripe_checkout_bitcoin',
			'row_classes' => 'stripe-checkout-field',
			'type'        => 'checkbox'
		),
		array(
			'name'        => __( 'Bongloy Checkout Image', 'give-stripe' ),
			'desc'        => __( 'This image appears in when the Bongloy checkout modal window opens and provides better brand recognition that leads to increased conversion rates. The recommended minimum size is a square image at 128x128px. The supported image types are: .gif, .jpeg, and .png.', 'give-stripe' ),
			'id'          => 'stripe_checkout_image',
			'row_classes' => 'stripe-checkout-field',
			'type'        => 'file',
			// Optional:
			'options'     => array(
				'url' => false, // Hide the text input for the url
			),
			'text'        => array(
				'add_upload_file_text' => __( 'Add or Upload Image', 'give-stripe' )
				// Change upload button text. Default: "Add or Upload File"
			),
		),
		array(
			'name' => __( 'Bongloy - ACH Settings', 'give-stripe' ),
			'desc' => '<hr>',
			'id'   => 'give_title_stripe_ach',
			'type' => 'give_title'
		),
		array(
			'name' => __( 'Plaid Client ID', 'give-stripe' ),
			'desc' => __( 'Enter your Plaid Client ID, found in your Plaid account dashboard.', 'give-stripe' ),
			'id'   => 'plaid_client_id',
			'type' => 'text'
		),
		array(
			'name' => __( 'Plaid Public Key', 'give-stripe' ),
			'desc' => __( 'Enter your Plaid public key, found in your Plaid account dashboard.', 'give-stripe' ),
			'id'   => 'plaid_public_key',
			'type' => 'text'
		),
		array(
			'name' => __( 'Plaid Secret Key', 'give-stripe' ),
			'desc' => __( 'Enter your Plaid secret key, found in your Plaid account dashboard.', 'give-stripe' ),
			'id'   => 'plaid_secret_key',
			'type' => 'api_key'
		),
	);

	return array_merge( $settings, $stripe_settings );

}

add_filter( 'give_settings_gateways', 'give_stripe_add_settings' );


/**
 * Given a transaction ID, generate a link to the Stripe transaction ID details
 *
 * @since  1.0
 *
 * @param  string $transaction_id The Transaction ID
 * @param  int    $payment_id The payment ID for this transaction
 *
 * @return string                 A link to the Transaction details
 */
function give_stripe_link_transaction_id( $transaction_id, $payment_id ) {

	$test = give_get_payment_meta( $payment_id, '_give_payment_mode' ) === 'test' ? 'test/' : '';
	$url  = '<a href="https://www.bongloy.com/charges' . $test . 'payments/' . $transaction_id . '" target="_blank">' . $transaction_id . '</a>';

	return apply_filters( 'give_stripe_link_donation_details_transaction_id', $url );

}

add_filter( 'give_payment_details_transaction_id-stripe', 'give_stripe_link_transaction_id', 10, 2 );
add_filter( 'give_payment_details_transaction_id-stripe_ach', 'give_stripe_link_transaction_id', 10, 2 );
