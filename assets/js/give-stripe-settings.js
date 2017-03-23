/**
 * Give - Stripe Gateway Add-on ADMIN JS
 */
jQuery.noConflict();
(function ($) {

    //On DOM Ready
    $(function () {

        var modal_options = $('#stripe_checkout_enabled');
        modal_options.on('change', function () {
            if (modal_options.prop('checked')) {
                $('.stripe-checkout-field').css('display', 'table-row');
            } else {
                $('.stripe-checkout-field').css('display', 'none');
            }
        }).change();


    });

})(jQuery);