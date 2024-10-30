jQuery(function($){
    var checkout_form = $( 'form.woocommerce-checkout' );
    var checkout_button = $( '#place_order' );
    $('.woocommerce-form-coupon-toggle').remove();
    checkout_form.children().hide();
    checkout_form.append(`<div style="text-align: center">
            <span>We are just transferring you...</span>
            <img src="${blueprint_params.loadingGif}" style="display: block; margin: 0 auto;">
        </div>
    `);
    checkout_button.click();
});
