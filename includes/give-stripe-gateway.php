<?php
/**
 * Give Stripe Gateway
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Give_Stripe_Gateway.
 */
class Give_Stripe_Gateway {

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Give_Stripe_Gateway constructor.
	 */
	public function __construct() {

		add_action( 'give_gateway_stripe', array( $this, 'process_payment' ) );
		add_action( 'init', array( $this, 'stripe_event_listener' ) );
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			if ( give_is_test_mode() ) {
				self::$secret_key = trim( give_get_option( 'test_secret_key' ) );
			} else {
				self::$secret_key = trim( give_get_option( 'live_secret_key' ) );
			}
		}

		return self::$secret_key;
	}

	/**
	 * Is Preapproved Enabled.
	 *
	 * @since 1.4
	 * @return bool
	 */
	function is_preapproved_enabled() {
		$preapproval_enabled = give_get_option( 'stripe_preapprove_only' );
		if ( $preapproval_enabled == 'on' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Is ACH Enabled.
	 *
	 * @since 1.4
	 * @return bool
	 */
	function is_ach_enabled() {
		return give_is_gateway_active( 'stripe_ach' );
	}

	/**
	 * Is Stripe Popup Enabled.
	 *
	 * @since 1.4
	 * @return bool
	 */
	function is_stripe_popup_enabled() {
		$popup_enabled = give_get_option( 'stripe_checkout_enabled' );
		if ( $popup_enabled == 'on' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *
	 * Process stripe checkout submission.
	 *
	 * @access      public
	 *
	 * @param $donation_data
	 *
	 * @return      void
	 */
	function process_payment( $donation_data ) {

		$payment_id = false;

		// Make sure we don't have any left over errors present.
		give_clear_errors();

		// Set the API key prior to any API calls.
		\Stripe\Stripe::setApiKey( self::get_secret_key() );

		// Security: Check for the Stripe token.
		$card_data = $this->check_for_token( $_POST, $donation_data );

		/**
		 * Sanity Checks:
		 * a) We need card data.
		 * b) Strip checkout is not enabled.
		 */
		if ( ! $card_data && ! $this->is_stripe_popup_enabled() ) {

			// No Stripe token.
			give_set_error( 'no_token', esc_html__( 'The Stripe token is missing. Please contact support.', 'give-stripe' ) );
			give_record_gateway_error( esc_html__( 'Missing Stripe Token', 'give-stripe' ), esc_html__( 'A Stripe token failed to be generated. Please check Stripe logs for more information.', 'give-stripe' ) );

			return;

		}

		// Any errors?
		$errors = give_get_errors();
		$charge = false;

		// No errors, proceed.
		if ( ! $errors ) {

			try {

				// Setup the payment details
				$payment_data = array(
					'price'           => $donation_data['price'],
					'give_form_title' => $donation_data['post_data']['give-form-title'],
					'give_form_id'    => intval( $donation_data['post_data']['give-form-id'] ),
					'date'            => $donation_data['date'],
					'user_email'      => $donation_data['user_email'],
					'purchase_key'    => $donation_data['purchase_key'],
					'currency'        => give_get_currency(),
					'user_info'       => $donation_data['user_info'],
					'status'          => 'pending',
					'gateway'         => 'stripe'
				);

				//Get an existing Stripe customer.
				$stripe_customer = $this->get_or_create_stripe_customer( $donation_data );

				$stripe_customer_id = is_array( $stripe_customer ) ? $stripe_customer['id'] : $stripe_customer->id;

				//We have a Stripe customer, charge them.
				if ( $stripe_customer_id ) {

					$card_id = $this->get_customer_card( $stripe_customer, $card_data );

					//Process charge w/ support for preapproval.
					$charge = $this->process_charge( $donation_data, $card_id, $stripe_customer_id );

					// Record the pending payment in Give.
					$payment_id = give_insert_payment( $payment_data );

				} else {

					//No customer, failed.
					give_record_gateway_error( esc_html__( 'Stripe Customer Creation Failed', 'give-stripe' ), sprintf( esc_html__( 'Customer creation failed while processing the donation. Details: %s', 'give-stripe' ), json_encode( $payment_data ) ) );
					give_set_error( 'stripe_error', esc_html__( 'The Stripe Gateway returned an error while processing the donation.', 'give-stripe' ) );
					give_send_back_to_checkout( '?payment-mode=stripe' );

				}

				//Verify the Stripe payment.
				$this->verify_payment( $payment_id, $stripe_customer_id, $charge );

			} catch ( \Stripe\Error\Base $e ) {

				/**
				 * All the Stripe error classes inherit from `Stripe\Error\Base`, and this first catch block match all the exception sub-classes such as `\Stripe\Error\Card`, `\Stripe\Error\API`, etc.
				 *
				 * @see http://stackoverflow.com/questions/17750143/catching-stripe-errors-with-try-catch-php-method Explanation of the above found in this answer
				 */
				$this->log_error( $e );

			} catch ( Exception $e ) {

				//Something went wrong outside of Stripe.
				give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe Gateway returned an error while processing a donation. Details: %s', 'give-stripe' ), $e->getMessage() ) );
				give_set_error( 'stripe_error', esc_html__( 'An error occurred while processing the donation. Please try again.', 'give-stripe' ) );
				give_send_back_to_checkout( '?payment-mode=stripe' );

			}
		} else {
			give_send_back_to_checkout( '?payment-mode=stripe' );
		}
	}

	/**
	 * Process One Time Charge.
	 *
	 * @param $donation_data
	 * @param $card_id
	 * @param $stripe_customer_id
	 *
	 * @return bool|\Stripe\Charge
	 */
	function process_charge( $donation_data, $card_id, $stripe_customer_id ) {

		$purchase_summary = give_get_purchase_summary( $donation_data, false );

		//Process the charge.
		$amount = $this->format_amount( $donation_data['price'] );

		$charge_args = array(
			'amount'               => $amount,
			'currency'             => give_get_currency(),
			'customer'             => $stripe_customer_id,
			'source'               => $card_id,
			'description'          => html_entity_decode( $purchase_summary, ENT_COMPAT, 'UTF-8' ),
			'statement_descriptor' => give_get_stripe_statement_descriptor( $donation_data ),
			'metadata'             => array(
				'email' => $donation_data['user_info']['email']
			),
		);

		//If preapproval enabled, only capture the charge
		//@see: https://stripe.com/docs/api#create_charge-capture
		if ( $this->is_preapproved_enabled() ) {
			$charge_args['capture'] = false;
		}

		try {

			$charge = \Stripe\Charge::create( apply_filters( 'give_stripe_create_charge_args', $charge_args, $donation_data ) );

		} catch ( \Stripe\Error\Base $e ) {

			$this->log_error( $e );

		} catch ( Exception $e ) {

			give_send_back_to_checkout( '?payment-mode=stripe' );

			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe Gateway returned an error while processing a donation. Details: %s', 'give-stripe' ), $e->getMessage() ) );
		}


		//Return charge if set.
		if ( isset( $charge ) ) {
			return $charge;
		} else {
			return false;
		}

	}


	/**
	 * Get Customer's card.
	 *
	 * @param $stripe_customer \Stripe\Customer
	 * @param $card_token
	 *
	 * @return bool
	 */
	public function get_customer_card( $stripe_customer, $card_token ) {

		$card_id = false;

		//Check if this card exists
		try {

			$payment_card = \Stripe\Token::retrieve( $card_token );

			$all_sources = $stripe_customer->sources->all();

			foreach ( $all_sources->data as $existing_card ) {

				//Check for a between the card the donor made the donation with and their Stripe customer sources.
				//a) cards fingerprints matches
				//b) cards expiration year matches
				//c) cards last 4 digits match
				if ( $existing_card->fingerprint === $payment_card->card->fingerprint
				     && $existing_card->exp_year === $payment_card->card->exp_year
				     && $existing_card->last4 === $payment_card->card->last4
				) {
					$card_id = $existing_card->id;
				}

			}

			//Create the card if none found above.
			if ( empty( $card_id ) ) {
				$card    = $stripe_customer->sources->create( array( 'source' => $card_token ) );
				$card_id = $card->id;
			}


		} catch ( \Stripe\Error\Base $e ) {

			$this->log_error( $e );

		} catch ( Exception $e ) {

			//Something went wrong outside of Stripe.
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe Gateway returned an error while processing a donation. Details: %s', 'give-stripe' ), $e->getMessage() ) );
			give_set_error( 'stripe_error', esc_html__( 'An error occurred while processing the donation. Please try again.', 'give-stripe' ) );
			give_send_back_to_checkout( '?payment-mode=stripe' );

		}

		//Check that there's a card.
		if ( ! $card_id ) {

			give_set_error( 'stripe_error', esc_html__( 'An error occurred while processing the donation. Please try again.', 'give-stripe' ) );
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), esc_html__( 'An error occurred retrieving or creating the ', 'give-stripe' ) );
			give_send_back_to_checkout( '?payment-mode=stripe' );

			return false;

		} else {

			//Return the $card_id.
			return $card_id;
		}

	}

	/**
	 * Verify Payment.
	 *
	 * @param $payment_id
	 * @param $stripe_customer_id
	 * @param $charge
	 */
	function verify_payment( $payment_id, $stripe_customer_id, $charge ) {

		//Sanity checks: verify all vars exist
		if ( $payment_id && ( ! empty( $stripe_customer_id ) || ! empty( $charge ) ) ) {

			//Preapproved payment? These don't get published, rather set to 'preapproval' status.
			if ( $this->is_preapproved_enabled() ) {

				give_update_payment_status( $payment_id, 'preapproval' );
				add_post_meta( $payment_id, give_stripe_get_customer_key(), $stripe_customer_id );

				$preapproval = new Give_Stripe_Preapproval();
				$preapproval->send_preapproval_admin_notice( $payment_id );
				$preapproval->send_preapproval_donor_notice( $payment_id );

			} else {

				//@TODO use Stripe's API here to retrieve the invoice then confirm it has been paid.
				//Regular payment, publish it.
				give_update_payment_status( $payment_id, 'publish' );
			}


			// Add note for the charge.
			// Save Stripe's charge ID to the transaction.
			if ( ! empty( $charge ) ) {
				give_insert_payment_note( $payment_id, 'Stripe Charge ID: ' . $charge->id );
				give_set_payment_transaction_id( $payment_id, $charge->id );
			}

			// Add note for customer ID.
			if ( ! empty( $stripe_customer_id ) ) {
				give_insert_payment_note( $payment_id, 'Stripe Customer ID: ' . $stripe_customer_id );
			}

			// Save Stripe customer id.
			$this->save_stripe_customer_id( $stripe_customer_id, $payment_id );

			// Send them to success page.
			give_send_to_success_page();

		} else {

			give_set_error( 'payment_not_recorded', esc_html__( 'Your donation could not be recorded, please contact the site administrator.', 'give-stripe' ) );

			// If errors are present, send the user back to the purchase page so they can be corrected.
			give_send_back_to_checkout( '?payment-mode=stripe' );

		}

	}


	/**
	 * Save Stripe Customer ID.
	 *
	 * @since 1.4
	 *
	 * @param $stripe_customer_id
	 * @param $payment_id
	 */
	function save_stripe_customer_id( $stripe_customer_id, $payment_id ) {

		// Update customer meta
		if ( class_exists( 'Give_DB_Customer_Meta' ) ) {

			$customer_id = give_get_payment_customer_id( $payment_id );

			// Get the Give Customer.
			$customer = new Give_Customer( $customer_id );

			// Update customer meta.
			$customer->update_meta( give_stripe_get_customer_key(), $stripe_customer_id );

		} elseif ( is_user_logged_in() ) {

			// Support saving to legacy method of user method.
			update_user_meta( get_current_user_id(), give_stripe_get_customer_key(), $stripe_customer_id );

		}


	}

	/**
	 * Check for the Stripe Token.
	 *
	 * @since 1.4
	 *
	 * @param $posted
	 * @param $donation_data
	 *
	 * @return array
	 */
	public function check_for_token( $posted, $donation_data ) {

		$card_data = false;

		$stripe_js_fallback = give_get_option( 'stripe_js_fallback' );

		if ( ! isset( $posted['give_stripe_token'] ) ) {

			// check for fallback mode.
			if ( ! empty( $stripe_js_fallback ) ) {

				$card_data = $this->process_post_data( $donation_data );

				try {

					$card_data = \Stripe\Token::create( array( 'card' => $card_data ) );
					$card_data = $card_data->id;

				} catch ( \Stripe\Error\Base $e ) {
					$this->log_error( $e );

				} catch ( Exception $e ) {

					give_send_back_to_checkout( '?payment-mode=stripe&form_id=' . $donation_data['post_data']['give-form-id'] );
					give_set_error( 'stripe_error', esc_html__( 'An occurred while processing the donation with the gateway. Please try your donation again.', 'give-stripe' ) );
					give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe Gateway returned an error while creating the customer payment token. Details: %s', 'give-stripe' ), $e->getMessage() ) );
				}

			} elseif ( ! $this->is_stripe_popup_enabled() ) {

				//No Stripe token and fallback mode is disabled.
				give_set_error( 'no_token', esc_html__( 'Missing Stripe token. Please contact support.', 'give-stripe' ) );
				give_record_gateway_error( esc_html__( 'Missing Stripe Token', 'give-stripe' ), esc_html__( 'A Stripe token failed to be generated. Please check Stripe logs for more information.', 'give-stripe' ) );

			}

		} else {

			$card_data = $_POST['give_stripe_token'];

		}

		return $card_data;

	}

	/**
	 * Get the Stripe customer object. If not found, create the customer with Stripe's API.
	 * Save the customer ID appropriately in the database.
	 *
	 * @param $donation_data
	 *
	 * @return bool|\Stripe\Customer
	 */
	public function get_or_create_stripe_customer( $donation_data ) {

		$cu = false;

		// No customer ID found, look up based on the email.
		$stripe_customer_id = give_get_stripe_customer_id( $donation_data['user_email'] );

		//There is a customer ID. Check if it is active still in Stripe.
		if ( ! empty( $stripe_customer_id ) ) {

			try {

				// Retrieve the customer to ensure the customer has not been deleted.
				$cu = \Stripe\Customer::retrieve( $stripe_customer_id );

				if ( isset( $cu->deleted ) && $cu->deleted ) {

					// This customer was deleted.
					$cu = false;

				}

			} catch ( \Stripe\Error\Base $e ) {
				// No customer found.
				$this->log_error( $e );

			} catch ( Exception $e ) {

				$cu = false;

			}

		}

		//Create the Stripe customer if not present from checks above.
		if ( empty( $cu ) ) {
			$cu = $this->create_stripe_customer( $donation_data );
		}

		return $cu;

	}

	/**
	 * Create a Customer in Stripe.
	 *
	 * @param $donation_data
	 *
	 * @return bool|\Stripe\Customer
	 */
	function create_stripe_customer( $donation_data ) {

		try {

			$metadata = array(
				'first_name' => $donation_data['user_info']['first_name'],
				'last_name'  => $donation_data['user_info']['last_name'],
				'created_by' => $donation_data['post_data']['give-form-title']
			);

			//Add address to customer metadata if present.
			if ( isset( $donation_data['user_info']['address'] ) && ! empty( $donation_data['user_info']['address'] ) ) {
				$metadata['address_line1']   = isset( $donation_data['user_info']['address']['line1'] ) ? $donation_data['user_info']['address']['line1'] : '';
				$metadata['address_line2']   = isset( $donation_data['user_info']['address']['line2'] ) ? $donation_data['user_info']['address']['line2'] : '';
				$metadata['address_city']    = isset( $donation_data['user_info']['address']['city'] ) ? $donation_data['user_info']['address']['city'] : '';
				$metadata['address_state']   = isset( $donation_data['user_info']['address']['state'] ) ? $donation_data['user_info']['address']['state'] : '';
				$metadata['address_country'] = isset( $donation_data['user_info']['address']['country'] ) ? $donation_data['user_info']['address']['country'] : '';
				$metadata['address_zip']     = isset( $donation_data['user_info']['address']['zip'] ) ? $donation_data['user_info']['address']['zip'] : '';
			}

			$args = array(
				'description' => esc_html__( 'Customer for ', 'give-stripe' ) . $donation_data['user_email'],
				'email'       => $donation_data['user_email'],
				'metadata'    => apply_filters( 'give_stripe_customer_metadata', $metadata, $donation_data )
			);

			// Create a customer first so we can retrieve them later for future payments.
			$cu = \Stripe\Customer::create( $args );

		} catch ( \Stripe\Error\Base $e ) {

			$this->log_error( $e );

		} catch ( Exception $e ) {

			give_send_back_to_checkout( '?payment-mode=stripe&form_id=' . $donation_data['post_data']['give-form-id'] );
			give_set_error( 'stripe_error', esc_html__( 'An occurred while processing the donation with the gateway. Please try your donation again.', 'give-stripe' ) );
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe Gateway returned an error while creating the customer. Details: %s', 'give-stripe' ), $e->getMessage() ) );
		}

		if ( ! empty( $cu ) ) {
			//Return obj.
			return $cu;
		} else {
			return false;
		}

	}


	/**
	 * Log a Stripe Error.
	 *
	 * Logs in the Give db the error and also displays the error message to the donor.
	 *
	 * @param        $exception \Stripe\Error\Base|\Stripe\Error\Card
	 * @param string $payment_mode
	 *
	 * @return bool
	 */
	public function log_error( $exception, $payment_mode = 'stripe' ) {

		$body = $exception->getJsonBody();
		$err  = $body['error'];

		$log_message = esc_html__( 'The Stripe payment gateway returned an error while processing the donation.', 'give-stripe' ) . '<br><br>';

		// Bad Request of some sort.
		if ( isset( $err['message'] ) ) {
			$log_message .= sprintf( esc_html__( 'Message: %s', 'give-stripe' ), $err['message'] ) . '<br><br>';
			if ( isset( $err['code'] ) ) {
				$log_message .= sprintf( esc_html__( 'Code: %s', 'give-stripe' ), $err['code'] );
			}

			give_set_error( 'stripe_request_error', $err['message'] );
		} else {
			give_set_error( 'stripe_request_error', esc_html__( 'The Stripe API request was invalid, please try again.', 'give-stripe' ) );
		}

		//Log it with DB
		give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), $log_message );
		give_send_back_to_checkout( '?payment-mode=' . $payment_mode );


		return false;

	}

	/**
	 * Process the POST Data for the Credit Card Form, if a token was not supplied.
	 *
	 * @since  1.0
	 *
	 * @param array $donation_data
	 *
	 * @return array The credit card data from the $_POST
	 */
	public function process_post_data( $donation_data ) {

		if ( ! isset( $_POST['card_name'] ) || strlen( trim( $_POST['card_name'] ) ) == 0 ) {
			give_set_error( 'no_card_name', esc_html__( 'Please enter a name for the credit card.', 'give-stripe' ) );
		}

		if ( ! isset( $_POST['card_number'] ) || strlen( trim( $_POST['card_number'] ) ) == 0 ) {
			give_set_error( 'no_card_number', esc_html__( 'Please enter a credit card number.', 'give-stripe' ) );
		}

		if ( ! isset( $_POST['card_cvc'] ) || strlen( trim( $_POST['card_cvc'] ) ) == 0 ) {
			give_set_error( 'no_card_cvc', esc_html__( 'Please enter a CVC/CVV for the credit card.', 'give-stripe' ) );
		}

		if ( ! isset( $_POST['card_exp_month'] ) || strlen( trim( $_POST['card_exp_month'] ) ) == 0 ) {
			give_set_error( 'no_card_exp_month', esc_html__( 'Please enter an expiration month.', 'give-stripe' ) );
		}

		if ( ! isset( $_POST['card_exp_year'] ) || strlen( trim( $_POST['card_exp_year'] ) ) == 0 ) {
			give_set_error( 'no_card_exp_year', esc_html__( 'Please enter an expiration year.', 'give-stripe' ) );
		}

		$card_data = array(
			'number'          => $donation_data['card_info']['card_number'],
			'name'            => $donation_data['card_info']['card_name'],
			'exp_month'       => $donation_data['card_info']['card_exp_month'],
			'exp_year'        => $donation_data['card_info']['card_exp_year'],
			'cvc'             => $donation_data['card_info']['card_cvc'],
			'address_line1'   => $donation_data['card_info']['card_address'],
			'address_line2'   => $donation_data['card_info']['card_address_2'],
			'address_city'    => $donation_data['card_info']['card_city'],
			'address_zip'     => $donation_data['card_info']['card_zip'],
			'address_state'   => $donation_data['card_info']['card_state'],
			'address_country' => $donation_data['card_info']['card_country']
		);

		return $card_data;
	}

	/**
	 * Format currency for Stripe.
	 *
	 * @see https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
	 *
	 * @param $amount
	 *
	 * @return mixed
	 */
	public function format_amount( $amount ) {
		//Get the donation amount.
		if ( give_stripe_is_zero_decimal_currency() ) {
			return $amount;
		} else {
			return $amount * 100;
		}
	}

	/**
	 * Listen for Stripe events.
	 *
	 * @access      public
	 * @since       1.0
	 * @return      void
	 */
	public function stripe_event_listener() {

		if ( isset( $_GET['give-listener'] ) && $_GET['give-listener'] == 'stripe' ) {

			//Get the autoloader.
			require_once GIVE_STRIPE_PLUGIN_DIR . '/vendor/autoload.php';

			//Get the secret key.
			$secret_key = give_is_test_mode() ? trim( give_get_option( 'test_secret_key' ) ) : trim( give_get_option( 'live_secret_key' ) );

			//Sanity check for required $secret_key.
			if ( empty( $secret_key ) ) {
				status_header( 500 );
				//Something went wrong outside of Stripe.
				give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'The Stripe API secret key is missing. Error occurred when processing a Stripe webhook event.', 'give-stripe' ) ) );
				die( '-1' ); // Failed
			}


			\Stripe\Stripe::setApiKey( $secret_key );

			// retrieve the request's body and parse it as JSON.
			// perhaps use wp_remote_retrieve_body() here?
			$body       = @file_get_contents( 'php://input' );
			$event_json = json_decode( $body );

			//We have an event ID.
			if ( isset( $event_json->id ) ) {

				status_header( 200 );

				try {

					$event = \Stripe\Event::retrieve( $event_json->id );

				} catch ( Exception $e ) {

					die( 'Invalid event ID' );

				}

				switch ( $event->type ) :

					case 'charge.refunded' :

						global $wpdb;

						$charge = $event->data->object;

						if ( $charge->refunded ) {

							$payment_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_give_payment_transaction_id' AND meta_value = %s LIMIT 1", $charge->id ) );

							if ( $payment_id ) {

								give_update_payment_status( $payment_id, 'refunded' );
								give_insert_payment_note( $payment_id, esc_html__( 'Charge refunded in Stripe.', 'give-stripe' ) );

							}

						}

						break;

				endswitch;

				do_action( 'give_stripe_event_' . $event->type, $event );

				die( '1' ); // Completed successfully

			} else {
				status_header( 500 );
				// Something went wrong outside of Stripe.
				give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), sprintf( esc_html__( 'An error occurred while processing a webhook.', 'give-stripe' ) ) );
				die( '-1' ); // Failed
			}
		}
	}

}

return new Give_Stripe_Gateway();