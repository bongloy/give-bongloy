=== Deprecation Notice ===

This library is deprecated and is here for reference only. Please use the official Give Stripe Add-On (https://givewp.com/documentation/add-ons/stripe-gateway/ instead.

In order to use Give with Bongloy replace `public static $apiBase = 'https://api.stripe.com';` with `public static $apiBase = 'https://api.bongloy.com';` in https://github.com/bongloy/give-bongloy/blob/master/Stripe/Stripe/Stripe.php and replace `wp_register_script( 'give-stripe-js', 'https://js.stripe.com/v3', array( 'jquery' ) );` with `wp_register_script( 'give-stripe-js', 'https://js.bongloy.com/v3', array( 'jquery' ) );` in https://github.com/bongloy/give-bongloy/blob/master/includes/give-stripe-scripts.php

=== Give - Stripe Gateway ===
Contributors: wordimpress
Tags: donations, donation, ecommerce, e-commerce, fundraising, fundraiser, stripe, gateway
Requires at least: 4.2
Tested up to: 4.7.2
Stable tag: 1.4.7
License: GPLv3
License URI: https://opensource.org/licenses/GPL-3.0

Stripe Gateway Add-on for Give

== Description ==

This plugin requires the Give plugin activated to function properly. When activated, it adds a payment gateway for stripe.com.

== Installation ==

= Minimum Requirements =

* WordPress 4.2 or greater
* PHP version 5.3 or greater
* MySQL version 5.0 or greater
* Some payment gateways require fsockopen support (for IPN access)

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of Give, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "Give" and click Search Plugins. Once you have found the plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our donation plugin and uploading it to your server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 1.4.7 =
* Fix: Properly include Stripe's autoloader to prevent update issues.

= 1.4.6 =
* New: The Give setting's Stripe API key field now displays as a password field type when the field contains an API key for added security.
* Fix: Stripe popup checkout now properly displays on mobile browsers.
* Fix: The Stripe charge ID is now stored as the Give transaction ID for every payment, not just preapprovals.
* Fix: Compatibility with Easy Digital Downloads plugin.

= 1.4.5 =
* Fix: Stripe popup checkout now properly triggers the popup event via js trigger.

= 1.4.4 =
* Fix: Incorrect path for Stripe's API autoloader causes 500 error when processing Stripe webhooks. https://github.com/WordImpress/Give-Stripe/issues/68

= 1.4.3 =
* Fix: Incompatibility with official WooCommerce Stripe extension. https://github.com/WordImpress/Give-Stripe/issues/65
* Fix: Investigate issues on mobile with the stripe checkout being blocked on mobile; https://github.com/WordImpress/Give-Stripe/issues/66

= 1.4.2 =
* New: The plugin now checks to see if Give is active and up to the minimum version required to run the plugin. https://github.com/WordImpress/Give-Stripe/issues/58
* Fix: Statement Descriptor defaults to organizations's site name - https://github.com/WordImpress/Give-Stripe/issues/56
* Fix: Bug when the disable Stripe JS option is turned on in wp-admin.

= 1.4.1 =
* Fix: Updated Stripe's API PHP SDK to the latest version to handle issues with TLS 1.2 errors and warnings: https://github.com/WordImpress/Give-Stripe/issues/52

= 1.4 =
* New: Support for Stripe's modal checkout - https://github.com/WordImpress/Give-Stripe/issues/10
* New: Support for Stripe's + Plaid ACH payment gateway - https://github.com/WordImpress/Give-Stripe/issues/21
* New: Updated to Stripe's latest PHP SDK - https://github.com/WordImpress/Give-Stripe/issues/24
* New: Refund Stripe payments directly in Give's donation details screen. https://github.com/WordImpress/Give-Stripe/issues/32
* New: Object oriented plugin architecture in place https://github.com/WordImpress/Give-Stripe/issues/24
* New: The plugin now passes additional metadata to Stripe when the customer is created such as "first name" "last name", as well as address if present. https://github.com/WordImpress/Give-Stripe/issues/29
* New: Links to the plugin's settings, priority support, and documentation are now present in the wp-admin plugin listing screen.
* New: Additional environmental checks are now in place for PHP version and Give core when the plugin is activated.
* New: A wp-admin notice now displays when the gateway is activated and no API keys are found for Stripe.

= 1.3.1 =
* Fix: Statement descriptor not properly being set for single time donations - https://github.com/WordImpress/Give-Stripe/issues/26

= 1.3 =
* New: Added the ability to disable "Billing Details" fieldset for Stripe to optimize donations forms with the least amount of fields possible - https://github.com/WordImpress/Give-Stripe/issues/11
* New: Stripe Preapproved Payments functionality - Admins are now notified when a new donation is made and it needs to be approved
* Fix: Payments fail if donation form has no title; now provides a fallback title "Untitled Donation Form" - https://github.com/WordImpress/Give-Stripe/issues/9
* Tweak: Register scripts prior to enqueuing
* Tweak: Removed "(MM/YY)" from the Expiration field label
* Tweak: Removed unused Recurring Donations functionality from Stripe Gateway Add-on in preparation for release of the actual Add-on

= 1.2 =
* Fix: Preapproved Stripe payments updated to properly show buttons within the Transactions' "Preapproval" column
* Fix: Increased statement_descriptor value limit from 15 to 22 characters

= 1.1 =
* New: Plugin activation banner with links to important links such as support, docs, and settings
* New: CC expiration field updated to be a singular field rather than two select fields
* Improved code organization and inline documentation
* Improved admin donation form validation
* Improved i18n (internationalization)
* Fix: Bug with Credit Cards with an expiration date more than 10 years
* Fix: Remove unsupported characters from statement_descriptor.
* Fix: Error refunding charges directly from within the transaction "Update Payment" modal

= 1.0 =
* Initial plugin release. Yippee!
