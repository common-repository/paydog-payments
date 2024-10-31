jQuery(function($){
     var showBanks = function() {
             $('#paydog-product-checkout').toggle()
           }

    $( document ).ready(function() {
            $('#paydog-product-checkout-button').click(showBanks)

            var banks = $('#woocommerce_paydog_bank')

            if(banks.length !== 0){
                //$.getJSON( "https://uat-api.paydog.co.uk/api/bank", function( result ) {
          //      $.getJSON( "http://localhost:8200/api/bank", function( result ) {
                $.getJSON( "/wp-json/paydog/v1/bank", function( result ) {
                     $.each(result, function(item) {
                        var bank = result[item];
                        banks.append($("<option />").val(bank.id).text(bank.name))
                     })
                });
            }
    });
});

