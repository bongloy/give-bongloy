<?php
/**
 * Plugin Name: Give - Bongloy Gateway
 * Plugin URI:  https://givewp.com/addons/stripe-gateway/
 * Description: Adds the Bongloy.com payment gateway to the available Give payment methods.
 * Version:     1.4.7
 * Author:      WordImpress
 * Author URI:  https://wordimpress.com
 * Text Domain: give-stripe
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define constants.
 *
 * Required minimum versions, paths, urls, etc.
 */
if ( ! defined( 'GIVE_STRIPE_VERSION' ) ) {
	define( 'GIVE_STRIPE_VERSION', '1.4.7' );
}
if ( ! defined( 'GIVE_STRIPE_MIN_GIVE_VER' ) ) {
	define( 'GIVE_STRIPE_MIN_GIVE_VER', '1.8.3' );
}
if ( ! defined( 'GIVE_STRIPE_MIN_PHP_VER' ) ) {
	define( 'GIVE_STRIPE_MIN_PHP_VER', '5.3.0' );
}
if ( ! defined( 'GIVE_STRIPE_PLUGIN_FILE' ) ) {
	define( 'GIVE_STRIPE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'GIVE_STRIPE_PLUGIN_DIR' ) ) {
	define( 'GIVE_STRIPE_PLUGIN_DIR', dirname( GIVE_STRIPE_PLUGIN_FILE ) );
}
if ( ! defined( 'GIVE_STRIPE_PLUGIN_URL' ) ) {
	define( 'GIVE_STRIPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'GIVE_STRIPE_BASENAME' ) ) {
	define( 'GIVE_STRIPE_BASENAME', plugin_basename( __FILE__ ) );
}


if ( ! class_exists( 'Give_Stripe' ) ) :

	/**
	 * Class Give_Stripe.
	 */
	class Give_Stripe {

		/**
		 * @var Give_Stripe The reference the *Singleton* instance of this class.
		 */
		private static $instance;

		/**
		 * @var Give_Stripe_Upgrades.
		 */
		public $upgrades;

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Give_Stripe The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}

		/**
		 * Give_Stripe constructor.
		 *
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}


		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {

			// Don't hook anything else in the plugin if we're in an incompatible environment.
			if ( self::get_environment_warning() ) {
				return;
			}

			add_action( 'plugins_loaded', array( $this, 'licensing' ), 12 );
			add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );
			add_action( 'init', array( $this, 'register_post_statuses' ), 110 );
			add_filter( 'give_payment_statuses', array( $this, 'payment_status_labels' ) );

			load_plugin_textdomain( 'give-stripe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			$this->includes();

		}

		/**
		 * Allow this class and other classes to add notices.
		 *
		 * @param $slug
		 * @param $class
		 * @param $message
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * Display admin notices.
		 */
		public function admin_notices() {

			$allowed_tags = array(
				'a'      => array(
					'href'  => array(),
					'title' => array(),
				),
				'br'     => array(),
				'em'     => array(),
				'strong' => array(),
			);

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], $allowed_tags );
				echo '</p></div>';
			}

		}

		/**
		 * The primary sanity check, automatically disable the plugin on activation if it doesn't
		 * meet minimum requirements.
		 *
		 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
		 */
		public static function activation_check() {
			$environment_warning = self::get_environment_warning( true );
			if ( $environment_warning ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( $environment_warning );
			}
		}

		/**
		 * Check the server environment.
		 *
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation.
		 */
		public function check_environment() {

			$environment_warning = self::get_environment_warning();
			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}

			// Check for if give plugin activate or not.
			$is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;
			// Check to see if Give is activated, if it isn't deactivate and show a banner.
			if ( is_admin() && current_user_can( 'activate_plugins' ) && ! $is_give_active ) {

				$this->add_admin_notice( 'prompt_connect', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have the <a href="%s" target="_blank">Give</a> plugin installed and activated for Stripe to activate.', 'give-stripe' ), 'https://givewp.com' ) );

				// Don't let this plugin activate
				deactivate_plugins( plugin_basename( __FILE__ ) );

				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}

				return false;

			}

			// Check min Give version.
			if ( defined( 'GIVE_STRIPE_MIN_GIVE_VER' ) && version_compare( GIVE_VERSION, GIVE_STRIPE_MIN_GIVE_VER, '<' ) ) {

				$this->add_admin_notice( 'prompt_connect', 'error', sprintf( __( '<strong>Activation Error:</strong> You must have <a href="%1$s" target="_blank">Give</a> core version %2$s+ for the Give Stripe add-on to activate.', 'give-stripe' ), 'https://givewp.com', GIVE_STRIPE_MIN_GIVE_VER ) );

				// Don't let this plugin activate.
				deactivate_plugins( plugin_basename( __FILE__ ) );

				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}

				return false;

			}

			$give_stripe = new Give_Stripe_Gateway();
			$secret      = $give_stripe->get_secret_key();

			// Show prompt needing to connect API key if gateway is active.
			if ( empty( $secret ) && give_is_gateway_active( 'stripe' ) ) {
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'The Bongloy payment gateway is almost ready. To get started, <a href="%s">set your Bongloy account keys</a>.', 'give-stripe' ), admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways' ) ) );
			}

		}

		/**
		 * Environment warnings.
		 *
		 * Checks the environment for compatibility problems.
		 * Returns a string with the first incompatibility found or false if the environment has no problems.
		 *
		 * @param bool $during_activation
		 *
		 * @return bool|mixed|string
		 */
		public static function get_environment_warning( $during_activation = false ) {

			if ( version_compare( phpversion(), GIVE_STRIPE_MIN_PHP_VER, '<' ) ) {
				if ( $during_activation ) {
					$message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s. Please contact your web host to upgrade your server\'s PHP version.', 'give-stripe' );
				} else {
					$message = __( 'The plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'give-stripe' );
				}

				return sprintf( $message, GIVE_STRIPE_MIN_PHP_VER, phpversion() );
			}

			if ( ! function_exists( 'curl_init' ) ) {

				if ( $during_activation ) {
					return __( 'The plugin could not be activated. cURL is not installed. Please contact your web host to install cURL.', 'give-stripe' );
				}

				return __( 'The plugin has been deactivated. cURL is not installed. Please contact your web host to install cURL.', 'give-stripe' );
			}

			return false;
		}

		/**
		 * Give Stripe Includes.
		 */
		private function includes() {

			// Checks if Give is installed.
			if ( ! class_exists( 'Give' ) ) {
				return false;
			}

			require_once GIVE_STRIPE_PLUGIN_DIR . '/vendor/autoload.php';

			if ( is_admin() ) {
				include( GIVE_STRIPE_PLUGIN_DIR . '/includes/admin/give-stripe-activation.php' );
				include( GIVE_STRIPE_PLUGIN_DIR . '/includes/admin/give-stripe-upgrades.php' );
				include( GIVE_STRIPE_PLUGIN_DIR . '/includes/admin/give-stripe-admin.php' );
			}

			include( GIVE_STRIPE_PLUGIN_DIR . '/includes/give-stripe-helpers.php' );
			include( GIVE_STRIPE_PLUGIN_DIR . '/includes/give-stripe-scripts.php' );
			include( GIVE_STRIPE_PLUGIN_DIR . '/includes/give-stripe-gateway.php' );
			include( GIVE_STRIPE_PLUGIN_DIR . '/includes/give-stripe-preapproval.php' );
			include( GIVE_STRIPE_PLUGIN_DIR . '/includes/give-stripe-ach.php' );

		}

		/**
		 * Register the Stripe payment gateways.
		 *
		 * @access      public
		 * @since       1.0
		 *
		 * @param $gateways array
		 *
		 * @return array
		 */
		public function register_gateway( $gateways ) {

			// Format: ID => Name
			$gateways['stripe']     = array(
				'admin_label'    => esc_html__( 'Bongloy - Credit Card', 'give-stripe' ),
				'checkout_label' => esc_html__( 'Credit Card', 'give-stripe' ),
			);
			$gateways['stripe_ach'] = array(
				'admin_label'    => esc_html__( 'Bongloy - ACH', 'give-stripe' ),
				'checkout_label' => esc_html__( 'Bank Account', 'give-stripe' ),
			);

			return $gateways;
		}

		/**
		 * Plugin Licensing.
		 */
		public function licensing() {
			if ( class_exists( 'Give_License' ) ) {
				new Give_License( __FILE__, 'Stripe Gateway', GIVE_STRIPE_VERSION, 'WordImpress', 'stripe_license_key' );
			}
		}

		/**
		 * Register payment statuses for Preapproval.
		 *
		 * @since 1.0
		 * @return void
		 */
		public function register_post_statuses() {

			register_post_status( 'preapproval', array(
				'label'                     => esc_html__( 'Preapproved', 'give-stripe' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'give-stripe' ),
			) );
		}

		/**
		 * Register our new payment status labels for Give Stripe.
		 *
		 * @since 1.0
		 *
		 * @param $statuses
		 *
		 * @return mixed
		 */
		public function payment_status_labels( $statuses ) {
			$statuses['preapproval'] = esc_html__( 'Preapproved', 'give-stripe' );

			return $statuses;
		}

	}

	$GLOBALS['give_stripe'] = Give_Stripe::get_instance();
	register_activation_hook( __FILE__, array( 'Give_Stripe', 'activation_check' ) );

endif; // End if class_exists check.
