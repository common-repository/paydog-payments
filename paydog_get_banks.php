<?php

add_action( 'rest_api_init', 'register_rest_endpoints');

/**
* Plugin options, we deal with it in Step 3 too
*/
function register_rest_endpoints(){
     register_rest_route( 'paydog/v1', 'bank', array(
        'methods' => 'GET',
        'callback' => 'getBanks',
     ) );
}

function getBanks( $data ) {
      $response = wp_remote_get( 'https://api.paydog.co.uk/api/bank',
                        array(
                             'method'      => 'GET',
                             'timeout'     => 10,
                             'redirection' => 5,
                             'blocking'    => true,
                             'headers'     => array(
                                 'Content-Type' => 'application/json; charset=utf-8'
                             )
                         ));

     if( !is_wp_error( $response ) ) {
         $banks = array_values(json_decode( $response['body'], true ));
         return $banks;
     }
     return;
}
?>