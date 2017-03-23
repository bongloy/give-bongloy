<?php
/**
 * Stripe Helper Functions
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe uses it's own credit card form because the card details are tokenized.
 *
 * We don't want the name attributes to be present on the fields in order to prevent them from getting posted to the
 * server.
 *
 * @access      public
 * @since       1.0
 *
 * @param      $form_id
 * @param bool $echo
 *
 * @return string $form
 */
function give_stripe_credit_card_form( $form_id, $echo = true ) {

	$billing_fields_enabled = give_get_option( 'stripe_collect_billing' );

	//No CC fields for popup.
	$stripe_gateway = new Give_Stripe_Gateway();
	if ( $stripe_gateway->is_stripe_popup_enabled() ) {

		//Remove Address Fields if user has option enabled.
		if ( $billing_fields_enabled ) {
			do_action( 'give_after_cc_fields' );
		}

		return false;
	}

	$fallback_option    = give_get_option( 'stripe_js_fallback' );
	$stripe_js_fallback = ! empty( $fallback_option );

	ob_start();

	do_action( 'give_before_cc_fields', $form_id ); ?>

    <fieldset id="give_cc_fields" class="give-do-validate">

        <legend><?php esc_html_e( 'Credit Card Info', 'give-stripe' ); ?></legend>

		<?php if ( is_ssl() ) : ?>
            <div id="give_secure_site_wrapper">
                <span class="give-icon padlock"></span>
                <span><?php esc_html_e( 'This is a secure SSL encrypted payment.', 'give-stripe' ); ?></span>
            </div>
		<?php endif; ?>

        <p id="give-card-number-wrap" class="form-row form-row-two-thirds form-row-responsive">
            <label for="card_number" class="give-label">
				<?php esc_html_e( 'Card Number', 'give-stripe' ); ?>
                <span class="give-required-indicator">*</span>
                <span class="give-tooltip give-icon give-icon-question"
                      data-tooltip="<?php esc_attr_e( 'The (typically) 16 digits on the front of your credit card.', 'give-stripe' ); ?>"></span>
                <span class="card-type"></span>
            </label>
            <input type="tel" autocomplete="off" <?php echo $stripe_js_fallback ? 'name="card_number"' : ''; ?>
                   id="card_number" class="card-number give-input required"
                   placeholder="<?php esc_attr_e( 'Card number', 'give-stripe' ); ?>"/>
        </p>

        <p id="give-card-cvc-wrap" class="form-row form-row-one-third form-row-responsive">
            <label for="card_cvc" class="give-label">
				<?php esc_html_e( 'CVC', 'give-stripe' ); ?>
                <span class="give-required-indicator">*</span>
                <span class="give-tooltip give-icon give-icon-question"
                      data-tooltip="<?php esc_attr_e( 'The 3 digit (back) or 4 digit (front) value on your card.', 'give-stripe' ); ?>"></span>
            </label>
            <input type="tel" size="4" autocomplete="off"
			       <?php echo $stripe_js_fallback ? 'name="card_cvc" ' : ''; ?>id="card_cvc"
                   class="card-cvc give-input required"
                   placeholder="<?php esc_attr_e( 'Security code', 'give-stripe' ); ?>"/>
        </p>

        <p id="give-card-name-wrap" class="form-row form-row-two-thirds form-row-responsive">
            <label for="card_name" class="give-label">
				<?php esc_html_e( 'Name on the Card', 'give-stripe' ); ?>
                <span class="give-required-indicator">*</span>
                <span class="give-tooltip give-icon give-icon-question"
                      data-tooltip="<?php esc_attr_e( 'The name printed on the front of your credit card.', 'give-stripe' ); ?>"></span>
            </label>

            <input type="text" autocomplete="off"
			       <?php echo $stripe_js_fallback ? 'name="card_name" ' : ''; ?>id="card_name"
                   class="card-name give-input required"
                   placeholder="<?php esc_attr_e( 'Card name', 'give-stripe' ); ?>"/>
        </p>

		<?php do_action( 'give_before_cc_expiration' ); ?>

        <p class="card-expiration form-row form-row-one-third form-row-responsive">
            <label for="card_expiry" class="give-label">
				<?php esc_html_e( 'Expiration', 'give-stripe' ); ?>
                <span class="give-required-indicator">*</span>
                <span class="give-tooltip give-icon give-icon-question"
                      data-tooltip="<?php esc_attr_e( 'The date your credit card expires, typically on the front of the card.', 'give-stripe' ); ?>"></span>
            </label>

            <input type="hidden" id="card_exp_month"
			       <?php echo $stripe_js_fallback ? 'name="card_exp_month" ' : ''; ?>class="card-expiry-month"/>
            <input type="hidden" id="card_exp_year"
			       <?php echo $stripe_js_fallback ? 'name="card_exp_year" ' : ''; ?>class="card-expiry-year"/>

            <input type="tel" autocomplete="off"
			       <?php echo $stripe_js_fallback ? 'name="card_expiry" ' : ''; ?>id="card_expiry"
                   class="card-expiry give-input required"
                   placeholder="<?php esc_attr_e( 'MM/YY', 'give-stripe' ); ?>"/>
        </p>

		<?php do_action( 'give_after_cc_expiration', $form_id ); ?>

    </fieldset>
	<?php

	//Remove Address Fields if user has option enabled
	if ( ! $billing_fields_enabled ) {
		remove_action( 'give_after_cc_fields', 'give_default_cc_address_fields' );
	}

	do_action( 'give_after_cc_fields', $form_id );

	$form = ob_get_clean();

	if ( false !== $echo ) {
		echo $form;
	}

	return $form;
}

add_action( 'give_stripe_cc_form', 'give_stripe_credit_card_form' );

/**
 * Add an errors div
 *
 * @access      public
 * @since       1.0
 * @return      void
 */
function give_stripe_add_stripe_errors() {
	echo '<div id="give-stripe-payment-errors"></div>';
}

add_action( 'give_after_cc_fields', 'give_stripe_add_stripe_errors', 999 );


/**
 * Get the meta key for storing Stripe customer IDs in.
 *
 * @access      public
 * @since       1.0
 * @return      string $key
 */
function give_stripe_get_customer_key() {

	$key = '_give_stripe_customer_id';
	if ( give_is_test_mode() ) {
		$key .= '_test';
	}

	return $key;
}

/**
 * Determines if the shop is using a zero-decimal currency.
 *
 * @access      public
 * @since       1.0
 * @return      bool
 */
function give_stripe_is_zero_decimal_currency() {

	$ret      = false;
	$currency = give_get_currency();

	switch ( $currency ) {

		case 'BIF' :
		case 'CLP' :
		case 'DJF' :
		case 'GNF' :
		case 'JPY' :
		case 'KMF' :
		case 'KRW' :
		case 'MGA' :
		case 'PYG' :
		case 'RWF' :
		case 'VND' :
		case 'VUV' :
		case 'XAF' :
		case 'XOF' :
		case 'XPF' :

			$ret = true;
			break;

	}

	return $ret;
}


/**
 * Use give_get_payment_transaction_id() first.
 *
 * Given a Payment ID, extract the transaction ID from Stripe and update the payment meta.
 *
 * @param  string $payment_id Payment ID
 *
 * @return string                   Transaction ID
 */
function give_stripe_get_payment_txn_id_fallback( $payment_id ) {

	$notes          = give_get_payment_notes( $payment_id );
	$transaction_id = '';

	foreach ( $notes as $note ) {
		if ( preg_match( '/^Stripe Charge ID: ([^\s]+)/', $note->comment_content, $match ) ) {
			$transaction_id = $match[1];
			update_post_meta($payment_id, '_give_payment_transaction_id', $transaction_id);
			continue;
		}
	}

	return apply_filters( 'give_stripe_get_payment_txn_id_fallback', $transaction_id, $payment_id );
}

add_filter( 'give_get_payment_transaction_id-stripe', 'give_stripe_get_payment_txn_id_fallback', 10, 1 );
add_filter( 'give_get_payment_transaction_id-stripe_ach', 'give_stripe_get_payment_txn_id_fallback', 10, 1 );


/**
 * Display the payment status filters.
 *
 * @since 1.0
 * @return array
 */
function give_stripe_payment_status_filters( $views ) {

	$payment_count        = wp_count_posts( 'give_payment' );
	$preapproval_count    = '&nbsp;<span class="count">(' . $payment_count->preapproval . ')</span>';
	$current              = isset( $_GET['status'] ) ? $_GET['status'] : '';
	$views['preapproval'] = sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'status', 'preapproval', admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) ), $current === 'preapproval' ? ' class="current"' : '', esc_attr__( 'Preapproval Pending', 'give-stripe' ) . $preapproval_count );

	return $views;
}

add_filter( 'give_payments_table_views', 'give_stripe_payment_status_filters' );


/**
 * Get Statement Descriptor.
 *
 * Create the Statement Description.
 *
 * @see         : https://stripe.com/docs/api/php#create_charge-statement_descriptor
 *
 * @since       1.3
 *
 * @param $donation_data
 *
 * @return mixed|void
 */
function give_get_stripe_statement_descriptor( $donation_data ) {

	$form_title = isset( $donation_data['post_data']['give-form-title'] ) ? $donation_data['post_data']['give-form-title'] : '';

	//Check for additional data
	if ( empty( $form_title ) ) {
		$form_title = isset( $donation_data['form_title'] ) ? $donation_data['form_title'] : esc_html__( 'Untitled donation form', 'give-stripe' );
	}

	//Assemble the statement descriptor: "Sitename - Form Title".
	$unsupported_characters = array( '<', '>', '"', '\'' );
	$statement_descriptor   = get_bloginfo( 'sitename' ) . ' - ' . $form_title;
	$statement_descriptor   = mb_substr( $statement_descriptor, 0, 22 );
	$statement_descriptor   = str_replace( $unsupported_characters, '', $statement_descriptor );

	return apply_filters( 'give_stripe_statement_descriptor', $statement_descriptor, $donation_data );

}


/**
 * Look up the stripe customer id in user meta, and look to recurring if not found yet.
 *
 * @since  1.4
 *
 * @param  int $user_id_or_email The user ID or email to look up.
 *
 * @return string       Stripe customer ID.
 */
function give_get_stripe_customer_id( $user_id_or_email ) {

	$user_id            = 0;
	$stripe_customer_id = '';

	//First check the customer meta of purchase email.
	if ( class_exists( 'Give_DB_Customer_Meta' ) && is_email( $user_id_or_email ) ) {
		$customer           = new Give_Customer( $user_id_or_email );
		$stripe_customer_id = $customer->get_meta( give_stripe_get_customer_key() );
	}

	//If not found via email, check user_id.
	if ( class_exists( 'Give_DB_Customer_Meta' ) && empty( $stripe_customer_id ) ) {
		$customer           = new Give_Customer( $user_id, true );
		$stripe_customer_id = $customer->get_meta( give_stripe_get_customer_key() );
	}

	//Get user ID from customer.
	if ( is_email( $user_id_or_email ) && empty( $stripe_customer_id ) ) {

		$customer = new Give_Customer( $user_id_or_email );
		//Pull user ID from customer object.
		if ( $customer->id > 0 && ! empty( $customer->user_id ) ) {
			$user_id = $customer->user_id;
		}

	} else {
		//This is a user ID passed.
		$user_id = $user_id_or_email;
	}

	//If no Stripe customer ID found in customer meta move to wp user meta.
	if ( empty( $stripe_customer_id ) && ! empty( $user_id ) ) {

		$stripe_customer_id = get_user_meta( $user_id, give_stripe_get_customer_key(), true );

	} elseif ( empty( $stripe_customer_id ) && class_exists( 'Give_Recurring_Subscriber' ) ) {

		//Not found in customer meta or user meta, check Recurring data.
		$by_user_id = is_int( $user_id_or_email ) ? true : false;
		$subscriber = new Give_Recurring_Subscriber( $user_id_or_email, $by_user_id );

		if ( $subscriber->id > 0 ) {

			$verified = false;

			if ( ( $by_user_id && $user_id_or_email == $subscriber->user_id ) ) {
				// If the user ID given, matches that of the subscriber.
				$verified = true;
			} else {
				// If the email used is the same as the primary email.
				if ( $subscriber->email == $user_id_or_email ) {
					$verified = true;
				}

				// If the email is in the Give's Additional emails.
				if ( property_exists( $subscriber, 'emails' ) && in_array( $user_id_or_email, $subscriber->emails ) ) {
					$verified = true;
				}
			}

			if ( $verified ) {
				$stripe_customer_id = $subscriber->get_recurring_customer_id( 'stripe' );
			}

		}

		if ( ! empty( $stripe_customer_id ) ) {
			update_user_meta( $subscriber->user_id, give_stripe_get_customer_key(), $stripe_customer_id );
		}

	}

	return $stripe_customer_id;

}

/**
 * Process refund in Stripe.
 *
 * @access      public
 * @since       1.4
 *
 * @param $payment_id
 * @param $new_status
 * @param $old_status
 *
 * @return      void
 */
function give_stripe_process_refund( $payment_id, $new_status, $old_status ) {

	//Only move forward if refund requested.
	if ( empty( $_POST['give_refund_in_stripe'] ) ) {
		return;
	}

	//Verify statuses.
	$should_process_refund = 'publish' != $old_status ? false : true;
	$should_process_refund = apply_filters( 'give_stripe_should_process_refund', $should_process_refund, $payment_id, $new_status, $old_status );

	if ( false === $should_process_refund ) {
		return;
	}

	if ( 'refunded' != $new_status ) {
		return;
	}

	$charge_id = give_get_payment_transaction_id( $payment_id );

	// If no charge ID, look in the payment notes.
	if ( empty( $charge_id ) || $charge_id == $payment_id ) {
		$charge_id = give_stripe_get_payment_txn_id_fallback( $payment_id );
	}

	// Bail if no charge ID was found.
	if ( empty( $charge_id ) ) {
		return;
	}

	$secret_key = give_is_test_mode() ? trim( give_get_option( 'test_secret_key' ) ) : trim( give_get_option( 'live_secret_key' ) );

	\Stripe\Stripe::setApiKey( $secret_key );

	try {

		$refund = \Stripe\Refund::create( array(
			'charge' => $charge_id
		) );

		if ( isset( $refund->id ) ) {
			give_insert_payment_note( $payment_id, sprintf( esc_html__( 'Charge refunded in Stripe: %s', 'give-stripe' ), $refund->id ) );
		}

	} catch ( \Stripe\Error\Base $e ) {
		// Refund issue occurred.
		$log_message = esc_html__( 'The Stripe payment gateway returned an error while refunding a donation.', 'give-stripe' ) . '<br><br>';
		$log_message .= sprintf( esc_html__( 'Message: %s', 'give-stripe' ), $e->getMessage() ) . '<br><br>';
		$log_message .= sprintf( esc_html__( 'Code: %s', 'give-stripe' ), $e->getCode() );

		//Log it with DB
		give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), $log_message );

	} catch ( Exception $e ) {

		// some sort of other error
		$body = $e->getJsonBody();
		$err  = $body['error'];

		if ( isset( $err['message'] ) ) {
			$error = $err['message'];
		} else {
			$error = esc_html__( 'Something went wrong while refunding the charge in Stripe.', 'give-stripe' );
		}

		wp_die( $error, esc_html__( 'Error', 'give-stripe' ), array( 'response' => 400 ) );

	}

	do_action( 'give_stripe_donation_refunded', $payment_id );

}

add_action( 'give_update_payment_status', 'give_stripe_process_refund', 200, 3 );
