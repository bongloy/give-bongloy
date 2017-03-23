<?php
/**
 * Give_Stripe_Preapproval
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Give_Stripe_ACH
 */
class Give_Stripe_Preapproval extends Give_Stripe_Gateway {

	/**
	 * Give_Stripe_Preapproval constructor.
	 */
	public function __construct() {

		//Check if preapproved is enabled.
		if ( ! parent::is_preapproved_enabled() ) {
			return false;
		}
		parent::__construct();

		add_action( 'admin_notices', array( $this, 'preapproval_messages' ) );
		add_action( 'give_charge_stripe_preapproval', array( $this, 'process_preapproved_charge' ) );
		add_action( 'give_cancel_stripe_preapproval', array( $this, 'process_preapproved_cancel' ) );
		add_filter( 'give_payments_table_column', array( $this, 'column_data' ), 9, 3 );
		add_filter( 'give_payments_table_columns', array( $this, 'payments_column' ) );
		add_filter( 'give_view_order_details_totals_before', array( $this, 'single_payment_buttons' ) );

	}


	/**
	 * PreApproval Admin Messages
	 *
	 * @since 1.1
	 * @return void
	 */
	function preapproval_messages() {

		//Must have.
		if ( ! isset( $_GET['give-message'] ) ) {
			return;
		}

		if ( 'preapproval-charged' == $_GET['give-message'] ) {
			add_settings_error( 'give-stripe-notices', 'give-stripe-preapproval-charged', esc_html__( 'The preapproved payment was successfully charged.', 'give-stripe' ), 'updated' );
		}
		if ( 'preapproval-failed' == $_GET['give-message'] ) {
			add_settings_error( 'give-stripe-notices', 'give-stripe-preapproval-charged', esc_html__( 'The preapproved payment failed to be charged. View order details for further details.', 'give-stripe' ), 'error' );
		}
		if ( 'preapproval-cancelled' == $_GET['give-message'] ) {
			add_settings_error( 'give-stripe-notices', 'give-stripe-preapproval-cancelled', esc_html__( 'The preapproved payment was successfully cancelled.', 'give-stripe' ), 'updated' );
		}

		settings_errors( 'give-stripe-notices' );
	}


	/**
	 * Trigger preapproved payment charge
	 *
	 * @since 1.0
	 * @return void
	 */
	function process_preapproved_charge() {

		//Security checks.
		if ( empty( $_GET['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['nonce'], 'give-stripe-process-preapproval' ) ) {
			return;
		}

		$payment_id = absint( $_GET['payment_id'] );

		$charge = $this->charge_preapproved( $payment_id );

		//Either redirect to single transaction or main listing page.
		$admin_url = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' );
		if ( isset( $_GET['id'] ) ) {
			$admin_url = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details&id=' . $_GET['id'] );
		}

		//Redirect.
		if ( $charge ) {
			wp_redirect( esc_url_raw( add_query_arg( array( 'give-message' => 'preapproval-charged' ), $admin_url ) ) );
			exit;
		} else {
			wp_redirect( esc_url_raw( add_query_arg( array( 'give-message' => 'preapproval-failed' ), $admin_url ) ) );
			exit;
		}

	}


	/**
	 * Cancel a preapproved payment.
	 *
	 * Sets the payment status to cancelled and adds a note then redirects the admin.
	 *
	 * @since 1.0
	 * @return void
	 */
	function process_preapproved_cancel() {

		//Security check.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'give-stripe-process-preapproval' ) ) {
			return;
		}

		$payment_id = absint( $_GET['payment_id'] );

		if ( empty( $payment_id ) ) {
			return;
		}

		if ( 'preapproval' !== get_post_status( $payment_id ) ) {
			return;
		}

		give_insert_payment_note( $payment_id, esc_html__( 'Preapproval cancelled', 'give-stripe' ) );
		give_update_payment_status( $payment_id, 'cancelled' );
		wp_redirect( esc_url_raw( add_query_arg( array( 'give-message' => 'preapproval-cancelled' ), admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) ) );
		exit;

	}


	/**
	 * Charge a preapproved payment.
	 *
	 * @since 1.0
	 *
	 * @param int $payment_id
	 *
	 * @return bool
	 */
	function charge_preapproved( $payment_id = 0 ) {

		//We need a payment id.
		if ( empty( $payment_id ) ) {
			return false;
		}

		//Sanity check: only preapproved payment statuses.
		if ( 'preapproval' !== get_post_status( $payment_id ) ) {
			return false;
		}

		$charge_id = give_get_payment_meta( $payment_id, '_give_payment_transaction_id' );

		//Verify we have the charge_id
		if ( empty( $charge_id ) ) {
			add_settings_error( 'give-notices', 'give-stripe-preapproval-charge-error', esc_attr__( 'Charge Error: No Stripe charge ID found for this transaction.', 'give-stripe' ), 'error' );
		}

		//Charge it
		try {

			\Stripe\Stripe::setApiKey( self::get_secret_key() );

			$charge = \Stripe\Charge::retrieve( $charge_id );
			$charge->capture();

		} catch ( \Stripe\Error\Base $e ) {

			add_settings_error( 'give-notices', 'give-stripe-preapproval-charge-error', esc_attr__( 'The Stripe Gateway returned an error while charging a preapproved donation.', 'give-stripe' ) . esc_attr__( 'Please check the payment gateway\'s error log for additional details.', 'give-stripe' ), 'error' );
			give_insert_payment_note( $payment_id, sprintf( __( 'An error occurred when charging this payment in Stripe. Please check the <a href="%s">payment gateway error logs</a> for additional details.', 'give-stripe' ), admin_url( 'edit.php?view=gateway_errors&post_type=give_forms&page=give-reports&tab=logs' ) ) );
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), esc_html__( 'The Stripe Gateway returned an error while charging a preapproved donation.', 'give-stripe' ) . '<br><br>' . sprintf( esc_attr__( 'Details: %s', 'give-stripe' ), $e->getMessage() ) );

		} catch ( Exception $e ) {

			add_settings_error( 'give-notices', 'give-stripe-preapproval-charge-error', esc_attr__( 'The Stripe Gateway returned an error while charging a preapproved donation.', 'give-stripe' ) . esc_attr__( 'Please check the payment gateway\'s error log for additional details.', 'give-stripe' ), 'error' );
			give_insert_payment_note( $payment_id, sprintf( __( 'An error occurred when charging this payment in Stripe. Please check the <a href="%s">payment gateway error logs</a> for additional details.', 'give-stripe' ), admin_url( 'edit.php?view=gateway_errors&post_type=give_forms&page=give-reports&tab=logs' ) ) );
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), esc_html__( 'The Stripe Gateway returned an error while charging a preapproved donation.', 'give-stripe' ) . '<br><br>' . sprintf( esc_attr__( 'Details: %s', 'give-stripe' ), $e->getMessage() ) );

		}

		//Charge succeeded.
		if ( ! empty( $charge ) ) {

			give_insert_payment_note( $payment_id, esc_html__( 'Stripe Charge ID: ', 'give-stripe' ) . $charge->id );
			give_update_payment_status( $payment_id, 'publish' );

			return true;

		} else {

			//Error :(
			give_insert_payment_note( $payment_id, sprintf( __( 'An error occurred when charging this payment in Stripe. Please check the <a href="%s">payment gateway error logs</a> for additional details.', 'give-stripe' ), admin_url( 'edit.php?view=gateway_errors&post_type=give_forms&page=give-reports&tab=logs' ) ) );
			give_record_gateway_error( esc_html__( 'Stripe Error', 'give-stripe' ), esc_html__( 'The Stripe Gateway returned an error while processing a charge for a preapproved payment.', 'give-stripe' ) );


			return false;
		}

	}


	/**
	 * Show the Process / Cancel buttons for preapproved payments.
	 *
	 * @param $value
	 * @param $payment_id
	 * @param $column_name
	 *
	 * @return string
	 */
	function column_data( $value, $payment_id, $column_name ) {

		$gateway = give_get_payment_gateway( $payment_id );

		if ( $column_name == 'preapproval' && $gateway == 'stripe' ) {

			$status             = get_post_status( $payment_id );
			$stripe_customer_id = get_post_meta( $payment_id, give_stripe_get_customer_key(), true );

			if ( give_is_payment_complete( $payment_id ) && $status == 'publish' ) {
				return esc_html__( 'Approved', 'give-stripe' );
			} elseif ( $status == 'cancelled' ) {
				return esc_html__( 'Cancelled', 'give-stripe' );
			}

			if ( ! $stripe_customer_id ) {
				return $value;
			}

			$nonce = wp_create_nonce( 'give-stripe-process-preapproval' );

			$preapproval_args = array(
				'payment_id'  => $payment_id,
				'nonce'       => $nonce,
				'give-action' => 'charge_stripe_preapproval'
			);
			$cancel_args      = array(
				'preapproval_key' => $stripe_customer_id,
				'payment_id'      => $payment_id,
				'nonce'           => $nonce,
				'give-action'     => 'cancel_stripe_preapproval'
			);


			if ( 'preapproval' === $status ) {
				$value = '<a href="' . esc_url( add_query_arg( $preapproval_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) ) . '" class="button-secondary button button-small give-tooltip" data-tooltip="' . esc_attr__( 'Process Payment', 'give-stripe' ) . '" style="float:left; margin: 0 3px 0 0;padding: 0 2px; text-align:center;" ><span class="dashicons dashicons-yes" style="vertical-align: middle; color:#03af00;"></span></a>&nbsp;';
				$value .= '<a href="' . esc_url( add_query_arg( $cancel_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) ) . '" class="button-secondary button button-small give-tooltip" data-tooltip="' . esc_attr__( 'Cancel Preapproval', 'give-stripe' ) . '" style="float:left; margin: 0; padding: 0 2px; text-align:center; color:#ff1800;"><span class="dashicons dashicons-no-alt" style="vertical-align: middle;"></span></a>';
			}


		} elseif ( $column_name == 'preapproval' ) {
			return esc_html__( 'n/a', 'give-stripe' );
		}

		return $value;
	}


	/**
	 * Add Approval / Decline buttons to single payment view.
	 *
	 * This will add buttons to the "Update Payment" metabox and returns the admin back to the transaction page once approved. Will only display on "preapproval" status transactions.
	 *
	 * @param $payment_id
	 */
	function single_payment_buttons( $payment_id ) {

		$stripe_customer_id = get_post_meta( $payment_id, give_stripe_get_customer_key(), true );
		$status             = get_post_status( $payment_id );
		$output             = '';

		//Sanity checks.
		if ( $status !== 'preapproval' ) {
			return false;
		}
		if ( ! $stripe_customer_id ) {
			return false;
		}

		$nonce = wp_create_nonce( 'give-stripe-process-preapproval' );

		$preapproval_args = array(
			'id'          => $payment_id,
			'payment_id'  => $payment_id,
			'nonce'       => $nonce,
			'give-action' => 'charge_stripe_preapproval'
		);
		$cancel_args      = array(
			'id'              => $payment_id,
			'preapproval_key' => $stripe_customer_id,
			'payment_id'      => $payment_id,
			'nonce'           => $nonce,
			'give-action'     => 'cancel_stripe_preapproval'
		);

		if ( 'preapproval' === $status ) {
			$output = '<div class="give-clearfix give-admin-box-inside" style="padding:10px; ">';
			$output .= '<label style="font-weight:bold;margin: 0 6px 0 0;">' . esc_html__( 'Approve / Deny:', 'give-stripe' ) . '</label>';
			$output .= '<a href="' . esc_url( add_query_arg( $preapproval_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details' ) ) ) . '" class="button-secondary button button-small give-tooltip" data-tooltip="' . esc_attr__( 'Process Payment', 'give-stripe' ) . '" style="display:inline-block; margin: 0 3px 0 0;padding: 0 2px; text-align:center;" ><span class="dashicons dashicons-yes" style="vertical-align: middle; color:#03af00;"></span></a>&nbsp;';
			$output .= '<a href="' . esc_url( add_query_arg( $cancel_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details' ) ) ) . '" class="button-secondary button button-small give-tooltip" data-tooltip="' . esc_attr__( 'Cancel Preapproval', 'give-stripe' ) . '" style="display:inline-block; margin: 0; padding: 0 2px; text-align:center; color:#ff1800;"><span class="dashicons dashicons-no-alt" style="vertical-align: middle;"></span></a>';
			$output .= '</div>';
		}

		echo apply_filters( 'give_stripe_preapproval_single_payment_links', $output );

	}

	/**
	 * Display the Preapproval column label.
	 *
	 * @since 1.0
	 * @return array
	 */
	function payments_column( $columns ) {

		if ( parent::is_preapproved_enabled() ) {
			$columns['preapproval'] = esc_html__( 'Preapproval', 'give-stripe' );
		}

		return $columns;
	}


	/**
	 * Send Preapproval Notice.
	 *
	 * Sends a notice to the donor with stripe instructions; can be customized per form.
	 *
	 * @TODO: Add a WYSIWYG editor for email content.
	 *
	 * @param int $payment_id
	 *
	 * @since       1.0
	 * @return bool
	 */
	function send_preapproval_admin_notice( $payment_id = 0 ) {

		//Must have a payment id
		if ( empty( $payment_id ) ) {
			return false;
		}

		$payment_data = give_get_payment_meta( $payment_id );

		//Email from name.
		$from_name = give_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$from_name = apply_filters( 'give_stripe_preapproval_notice_from_name', $from_name, $payment_id, $payment_data );

		//Email from email Address.
		$from_email = give_get_option( 'from_email', get_bloginfo( 'admin_email' ) );
		$from_email = apply_filters( 'give_stripe_preapproval_notice_from_email', $from_email, $payment_id, $payment_data );

		//To email: where it's headed.
		$admin_email = give_get_admin_notice_emails();

		//Subject.
		$admin_subject = esc_html__( 'A New Pending Donation is Awaiting Your Approval', 'give-stripe' );
		$admin_subject = apply_filters( 'give_stripe_preapproval_notice_subject', wp_strip_all_tags( $admin_subject ), $payment_id );
		$admin_subject = give_do_email_tags( $admin_subject, $payment_id );

		//Main email content.
		$order_url = '<a href="' . admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details&id=' . $payment_id ) . '">';;

		//Assemble the content.
		$admin_message = esc_html__( 'Good news!', 'give-stripe' ) . "\n\n";
		$admin_message .= sprintf( esc_html__( 'A new donation has been made on %s and it is awaiting your review. You have seven days after the donation has been made to approve or decline it.', 'give-stripe' ), '<strong>{sitename}</strong>' ) . "\n\n";
		$admin_message .= '<strong>' . esc_html__( 'Donor:', 'give-stripe' ) . '</strong> {fullname}' . "\n";
		$admin_message .= '<strong>' . esc_html__( 'Amount:', 'give-stripe' ) . '</strong> {price}' . "\n";
		$admin_message .= '<strong>' . esc_html__( 'Date:', 'give-stripe' ) . '</strong> {date}' . "\n\n";
		$admin_message .= $order_url . esc_html__( 'Click Here to View Donation Details &raquo;', 'give-stripe' ) . '</a>' . "\n\n";
		$admin_message = apply_filters( 'give_stripe_preapproval_notice_content', $admin_message );
		//Pass through do_email_tags.
		$admin_message = give_do_email_tags( $admin_message, $payment_id );

		//Get email ready to send
		$emails = Give()->emails;

		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'heading', apply_filters( 'give_stripe_preapproval_notice_h1', esc_html__( 'New Donation Awaiting Approval', 'give-stripe' ) ) );

		$admin_headers = apply_filters( 'give_stripe_preapproval_notice_headers', $emails->get_headers(), $payment_id, $payment_data );
		$emails->__set( 'headers', $admin_headers );

		//Send it!
		//Check for Give Core email class.
		$sent = Give()->emails->send( $admin_email, $admin_subject, $admin_message );

		//Record email as sent in log.
		if ( $sent ) {
			give_insert_payment_note( $payment_id, esc_html__( 'Preapproval donation admin email notice sent to: ', 'give-stripe' ) . implode( ',', $admin_email ) );

			return true;
		} else {
			return false;
		}


	}


	/**
	 * Sends the donor a preapproval notice.
	 *
	 * Sends a notice to the donor much like the
	 *
	 * @param int $payment_id
	 *
	 * @since       1.0
	 * @return void
	 */
	function send_preapproval_donor_notice( $payment_id = 0 ) {

		$payment_data = give_get_payment_meta( $payment_id );

		//Email from name and email
		$from_name  = give_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$from_name  = apply_filters( 'give_stripe_preapproval_donor_notice_from_name', $from_name, $payment_id, $payment_data );
		$from_email = give_get_option( 'from_email', get_bloginfo( 'admin_email' ) );
		$from_email = apply_filters( 'give_stripe_preapproval_donor_notice_from_address', $from_email, $payment_id, $payment_data );

		//To email. The donor.
		$donor_email = give_get_payment_user_email( $payment_id );

		//Subject.
		$donor_subject = esc_html__( 'Your Donation Has Been Preapproved', 'give-stripe' );
		$donor_subject = apply_filters( 'give_stripe_preapproval_donor_notice_subject', wp_strip_all_tags( $donor_subject ), $payment_id );
		$donor_subject = give_do_email_tags( $donor_subject, $payment_id );

		//Message.
		$donor_message = esc_html__( 'Dear', 'give-stripe' ) . " {name},\n\n";
		$donor_message .= esc_html__( 'Thank you for your donation. Your generosity is appreciated! Your card will be charged within seven days pending approval of the donation. You will receive an additional email receipt approved. Here are the details of your donation for your records:', 'give-stripe' ) . "\n\n";
		$donor_message .= '<strong>' . esc_html__( 'Status:', 'give-stripe' ) . '</strong> Awaiting approval' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Donor:', 'give-stripe' ) . '</strong> {fullname}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Donation:', 'give-stripe' ) . '</strong> {donation}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Donation Date:', 'give-stripe' ) . '</strong> {date}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Amount:', 'give-stripe' ) . '</strong> {price}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Payment Method:', 'give-stripe' ) . '</strong> {payment_method}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Payment ID:', 'give-stripe' ) . '</strong> {payment_id}' . "\n";
		$donor_message .= '<strong>' . esc_html__( 'Receipt ID:', 'give-stripe' ) . '</strong> {receipt_id}' . "\n\n";
		$donor_message .= '{receipt_link}' . "\n\n";
		$donor_message .= "\n\n";
		$donor_message .= esc_html__( 'Sincerely,', 'give-stripe' ) . "\n";
		$donor_message .= '{sitename}' . "\n";

		$donor_message = apply_filters( 'give_stripe_preapproval_donor_notice_content', $donor_message );

		$donor_message = give_do_email_tags( $donor_message, $payment_id );

		//Get email ready to send
		$emails = Give()->emails;
		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'heading', apply_filters( 'give_stripe_preapproval_donor_notice_h1', esc_html__( 'Donation Preapproved', 'give-stripe' ) ) );

		$donor_headers = apply_filters( 'give_stripe_preapproval_donor_notice_headers', $emails->get_headers(), $payment_id, $payment_data );
		$emails->__set( 'headers', $donor_headers );

		//Send it!
		//Check for Give Core email class.
		$sent = Give()->emails->send( $donor_email, $donor_subject, $donor_message );

		//Record email as sent in log.
		if ( $sent ) {
			give_insert_payment_note( $payment_id, esc_html__( 'Preapproved donation email notice sent to donor at: ', 'give-stripe' ) . $donor_email );

			return true;
		} else {
			return false;
		}

	}


}

new Give_Stripe_Preapproval();

