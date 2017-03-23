/**
 * Give - Stripe Popup Checkout JS
 */
var give_global_vars, give_stripe_ach_vars;
jQuery(document).ready(function ($) {

    var donate_button_val = $('.give-submit-button-wrap input[type="submit"]').val();

    $('form[id^=give-form]').on('submit', function (e) {

        //Form that has been submitted.
        var $form = $(this);
        var $form_submit = $(this).find('.give-submit-button-wrap input[type="submit"]');

        //Ensure the Plaid modal displays above.
        $form.append("<style>#plaid-link-iframe { z-index: 2147483541 !important; }</style>");
        //Disable submit.
        $form_submit.prop('disabled', true);


        //Check that Stripe is indeed the gateway chosen.
        var chosen_gateway = $form.find('input[name="give-gateway"]').val();
        if (chosen_gateway !== 'stripe_ach') {
            return;
        }

        e.preventDefault();

        var linkHandler = Plaid.create({
            env: give_stripe_ach_vars.plaid_endpoint,
            clientName: give_stripe_ach_vars.sitename,
            key: give_stripe_ach_vars.plaid_public_key,
            product: 'auth',
            selectAccount: true,
            onSuccess: function (public_token, metadata) {

                // insert the token into the form so it gets submitted to the server
                $form.append("<input type='hidden' name='give_stripe_ach_token' value='" + public_token + "' />");
                $form.append("<input type='hidden' name='give_stripe_ach_account_id' value='" + metadata.account_id + "' />");
                // submit
                $form.get(0).submit();

            },
            onExit: function () {

                //Remove loading animations
                $form.find('.give-loading-animation').hide();
                //Reenable submit button.
                $form_submit.prop('disabled', false);
                // button and add back text.
                $form_submit.val(donate_button_val);
            }
        });

        linkHandler.open();

    });


});