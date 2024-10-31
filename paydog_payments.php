<?php
/*
 * Plugin Name: Paydog Payments
 * Plugin URI: https://developer.paydog.co.uk/application/a2-a1
 * Description: Take UK Bank Instant and Monthly Payments on your store.
 * Author: Paydog Ltd
 * Author URI: https://www.paydog.co.uk
 * Version: 1.0.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Robin Morris
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html

/*
Paydog Payments is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Paydog Payments is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Paydog Payments. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway

 * See https://rudrastyh.com/woocommerce/payment-gateway-plugin.html for basica
 */
add_filter( 'woocommerce_payment_gateways', 'paydog_add_gateway_class' );
function paydog_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_paydog_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'paydog_init_gateway_class' );

include( plugin_dir_path( __FILE__ ) . 'paydog_get_banks.php');

function paydog_init_gateway_class() {

	class WC_paydog_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
        public function __construct() {

            $this->id = 'paydog'; // payment gateway plugin ID
            $this->icon = 'https://www.paydog.co.uk/img/paydog_woocommerce.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Paydog Payments';
            $this->method_description = 'Take Instant Payments direct to your Bank Account using your Paydog Account. To take Monthly Payments put your Products in a category containing the name "Monthly" '; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->enabledProductCheckout = $this->get_option( 'enabledProductCheckout' );
            $this->access_token = $this->get_option( 'access_token' );
            $this->timeout = 180;

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // You can also register a webhook here  xxxxx/wc-api/paydog/
            add_action( 'woocommerce_api_paydog', array( $this, 'webhook' ) );

            // seems to miss this event unless paypay is registered!!
            add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_button_product' ), 1 );

         }

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Paydog Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Pay with your UK Bank Account - Powered by Paydog',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your UK Bank Account. You must have UK Mobile of Internet Banking.',
                ),
                'access_token' => array(
                    'title'       => 'Paydog Access Token',
                    'description'       => 'Past the Access_Token Created when you authorise this application to use your Paydog account at https://developer.paydog.co.uk/application/a2-a1',
                    'type'        => 'text'
                ),
//                 'enabledProductCheckout' => array(
//                     'title'       => 'Enable/Disable',
//                     'label'       => 'Enable Checkout on Product Pages',
//                     'type'        => 'checkbox',
//                     'description' => '',
//                     'default'     => 'yes'
//                 ),

            );
        }

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it

		 * https://woocommerce.wp-a2z.org/oik_api/wc_settings_apigenerate_multiselect_html/
		 */
		public function payment_fields() {
		   $this->showBanks();
		   $this->createRequest();
		}

	    public function getBanks( $data ) {
              $response = wp_remote_get( 'https://api.paydog.co.uk/api/bank',
                                array(
                                     'method'      => 'GET',
                                     'timeout'     => 10,
                                     'redirection' => 5,
                                     'blocking'    => true,
                                     'headers'     => array(
                                         'Authorization' => 'Bearer ' . $this->access_token,
                                         'Content-Type' => 'application/json; charset=utf-8'
                                     )
                                 ));

             if( !is_wp_error( $response ) ) {
                 $banks = array_values(json_decode( $response['body'], true ));
                 return $banks;
             }
             return;
		}

	    function showBanks() {
		     // from https://woocommerce.wp-a2z.org/oik_api/wc_settings_apigenerate_multiselect_html/
             $banks = $this->getBanks(null);

             if( $banks != null ) {

                 $options = [];
                 foreach ($banks as $bank){
                    $options[$bank['id']] = $bank['name'];
                 }

                 echo $this->generate_select_html('bank',
                        array(
                            'title' => '1. Please select your Bank',
                            'options' => $options
                        )
                    );
                // echo '<p>You will be taken via Paydog to your Mobile or Online Banking to approve this payment</p>';
             } else {
                 wc_add_notice(  'Unable to load Banks List.', 'error' );
                 return;
             }

		}

		function createRequestId() {
		       global $woocommerce;
               $order_id = $woocommerce->session->order_awaiting_payment;
               $args = array(
                        'method'      => 'GET',
                        'timeout'     => 45,
                        'redirection' => 5,
                        'blocking'    => true,
                        'headers'     => array(
                            'Authorization' => 'Bearer ' . $this->access_token,
                            'Content-Type' => 'application/json; charset=utf-8'
                        )
                    );

                /*
                 * Your API interaction could be built with wp_remote_post()
                 * From https://developer.wordpress.org/reference/functions/wp_remote_post/

                 * Also useful https://gist.github.com/igorbenic/9d555cd278c128deee0f43a56eba14da
                 */
                 $response = wp_remote_get( 'https://api.paydog.co.uk/api/requestId', $args );

                 if( !is_wp_error( $response ) ) {

                     $body = json_decode( $response['body'], true );
                     $requestId = $body['requestId'];
                     // it could be different depending on your payment processor
                     if ( $requestId != null ) {
                          return $requestId;
                     } else {
                        return;
                     }

                } else {
                    return;
                }
		}

		function createRequest() {
		     // from https://woocommerce.wp-a2z.org/oik_api/wc_settings_apigenerate_multiselect_html/
             $requestId = $this->createRequestId();
             if( $requestId != null ) {
                 $url = 'https://www.paydog.co.uk/r/' . $requestId .'/authorise';
                  echo '<div class="paydog" id="paydog_payment" style="z-index: 1200; position: relative; display: none;" >

                          <input type="hidden" class="input-hidden" name="paydog_request_id" id="paydog_request_id" value="' . $requestId . '">
                          <label>2. Mobile Banking Authorisation</label><br/>
                          <p>Please scan the QR code below to authorise this payment</p>
                          <img id="paydog_qr_code" class="paydog_qr_code" height="250" width="250" src="https://api.paydog.co.uk/paydog/api/qrcode?url=' . urlencode($url) . '" alt="qr code2">

                          <p class="paydog_qr_code_instructions">To continue:<br/>
                           1. Open the camera on your IOS/Android<br/>
                           2. Position you camera over the QR Code<br/>
                           3. Click accept to your launch browser<br/>
                           4. Complete the journey as instructed</p>

                          <span id="paydog_progress_bar" class="paydog_progress_bar"></span><br/>
                          <label>Prefer Online Banking?</label><br/>
                          <p>You will be taken to your online banking portal to verify this payment.<br/>
                          Depending on your bank you may need your card reader.</p>
                          <a id="paydog_link" href="' . $url . '">Pay using Online Banking</a></p>
                        </div>';
             } else {
                wc_add_notice(  'Unable to create request id.', 'error' );
                return;
             }

	 	}


		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
        public function payment_scripts() {

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->access_token ) ) {
                return;
            }

            wp_register_script( 'show_image', plugins_url( 'js/show_image.js', __FILE__ ), array( 'jquery') );
            wp_enqueue_script( 'show_image' );

        }

		/*
 		 * Fields validation, more in Step 5
		 */
		public function display_button_product() {
            error_log( var_export( 'display_button_product', 1 ) );
            if($this->enabledProductCheckout ==='no') {
                return;
            }
		    $is_product  = ( is_product() || wc_post_content_has_shortcode( 'product_page' ) );
            if(!$is_product) {
                return;
            }
            wp_enqueue_style( 'product_payment_buttons_css', plugins_url( 'css/product_payment_buttons.css', __FILE__ ), false, NULL, 'all' );

            wp_register_script( 'product_payment_buttons', plugins_url( 'js/product_payment_buttons.js', __FILE__ ), array( 'jquery') );
            wp_enqueue_script( 'product_payment_buttons' );
		    ?>
            <div class="paydog-button">

                <div id="paydog-product-checkout-button" class="paydog-product-checkout-button">
                    <img src="https://www.paydog.co.uk/img/paydog-white.svg" height='25' width='25'>
                    <span>Paydog Bank Payment</span>
                </div>
                <div id="paydog-product-checkout" class="paydog-bank">
                    <?php
                      echo $this->generate_select_html('bank',
                                            array(
                                                'title' => 'Please select your Bank',
                                                'options' => array()
                                            )
                                        );
                     ?>
                    <input id="paydog-product-pay-button" class="paydog-submit" type="submit" value="Pay">
                </div>
            </div>
            		<?php
        }

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'billing_last_name' ]) ) {
                wc_add_notice(  'Last name is required!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'billing_email' ]) ) {
                wc_add_notice(  'Email is required!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'woocommerce_paydog_bank' ]) ) {
                wc_add_notice(  'Bank is required!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'paydog_request_id' ]) ) {
                wc_add_notice(  'Paydog Request Id is required!', 'error' );
                return false;
            }
            return true;

		}

		/*
		 * Product is monthly if it has monthly in any of the category names
		 * Get the Sale price for monthly.is_producttodo might want this to be the non sale price???
		 */
		 function getMonthlyPrice( $product, $order ) {
		    if($this->isMonthly( $product )){
		        return  array(
                              'value' => wc_get_price_including_tax($product),
                              'currencyCode' => $order->get_currency()
                            );
		    }
		    return null;
		 }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		 function isMonthly( $product ) {
		    $categories = wc_get_product_category_list($product->get_id());
		    error_log( var_export( 'isMonthly:' . $categories) );
		    return strpos(strtolower($categories), 'monthly') !== false;
		 }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		 function getRequestPostData( $order_id ) {

            // we need it to get any order detailes
            $order = wc_get_order( $order_id );

            // convert tot he items to suit Paydog
            $all_items = array();
            
            // Products
            $tax = new WC_Tax();
            foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
                if ( 'fee' === $item['type'] ) {
                    $itemObject = array(
                        'price' => array(
                          'value' => $item['line_total'],
                          'currencyCode' => $order->get_currency()
                        ),
                        'quantity' => 1,
                        'description' => 'Fee',
                        'vatRate' => 0
                    );
                    $all_items[] = $itemObject;
                } else {
                    $product          = $order->get_product_from_item( $item );
                    $sku              = $product ? $product->get_sku() : '';
                    $product_tax_class = $product->get_tax_class();
                    $taxes =  $tax->get_rates($product_tax_class);
                    $rates = array_shift($taxes);
                    //Take only the item rate and round it.
                    $item_rate = $rates==null?0:round(array_shift($rates));

                    $itemObject = array(
                        'recurringPrice' => $this->getMonthlyPrice( $product, $order),
                        'price' => array(
                          'value' => wc_get_price_including_tax( $product ),
                          'currencyCode' => $order->get_currency()
                        ),
                        'quantity' => $item['qty'],
                        'description' => $sku . $item['name'],
                        'vatRate' => $item_rate
                    );
                    $all_items[] = $itemObject;
                }

            }

            /*
             * Array with parameters for API interaction
             */
            $args = array(
                    'method'      => 'POST',
                    'timeout'     => 45,
                    'redirection' => 5,
                    'blocking'    => true,
                    'headers'     => array(
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json; charset=utf-8'
                    ),
                    'body'        => json_encode(array(
                         'requestId' => $_POST[ 'paydog_request_id' ],
                         'reference' => 'Order ' . $order_id,
                         'attributes' => array(
                             'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                             'email' => $order->get_billing_email(),
                             'companyName' => $order->get_billing_company(),
                             'addressLine1' => $order->get_billing_address_1(),
                             'addressLine2' => $order->get_billing_address_2(),
                             'city' => $order->get_billing_city(),
                             'county' => $order->get_billing_state(),
                             'postcode' => $order->get_billing_postcode(),
                         ),
                         'webhook' => home_url( '/wc-api/paydog?orderId='. $order_id ),
                         'returnUrl' => $this->get_return_url( $order ),
                         'items' => $all_items,
                         'payout' => false
                    )),
                    'data-format' => 'body'
                );
            return $args;
		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		 function reset_cart( $order_id ) {

            global $woocommerce;
            //we received the payment
            $order = wc_get_order( $order_id );
            $order->payment_complete();
            $order->reduce_order_stock();

            //some notes to customer (replace true with false to make it private)
            //$order->add_order_note( 'Please pay for your order using our payment Provider Paydog here:'. $body['url'], true );

            //Empty cart
            $woocommerce->cart->empty_cart();
         }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
	    function poll_for_paid_status($requestId) {
            $polling_interval = 5;
            for ($i = 0; $i <= (($this->timeout)/$polling_interval); $i++) {

                $response = wp_remote_get( 'https://api.paydog.co.uk/api/request/' . $requestId,
                                                    array(
                                                         'method'      => 'GET',
                                                         'timeout'     => 10,
                                                         'redirection' => 5,
                                                         'blocking'    => true,
                                                         'headers'     => array(
                                                             'Authorization' => 'Bearer ' . $this->access_token,
                                                             'Content-Type' => 'application/json; charset=utf-8'
                                                         )
                                                     )
                );
                if( !is_wp_error( $response ) ) {
                      $body = json_decode( $response['body'], true );

                      // statuses for Paid
                      if ( $body['status'] === 'Paid' || $body['status'] === 'First Payment Made' ) {
                         return true;
                      }
                }
                sleep($polling_interval);
            }
            return false;
        }

		/*
		 * We're processing the payments here, everything about it is in Step 5

		 * might want to impriove save https://stackoverflow.com/questions/25626058/add-extra-meta-for-orders-in-woocommerce/48502896#48502896
		 */
	    function ensureRequest( $order_id ) {
             $order = wc_get_order( $order_id );
		     if($order->get_meta('paydog_request_id') === $_POST[ 'paydog_request_id' ])    // retry of the same order
		        return $order->get_meta('paydog_request_id');

             $response = wp_remote_post( 'https://api.paydog.co.uk/api/request', $this->getRequestPostData($order_id) );

             if( !is_wp_error( $response ) ) {
                  $body = json_decode( $response['body'], true );

                  // it could be different depending on your payment processor
                  $requestId = $body['requestId'];
                  if ( $requestId != null ) {
                     $order->update_meta_data('paydog_request_id', $requestId);
                     $order->save();
                     return $requestId;
                  } else {
                     wc_add_notice(  'Unable to create Request.', 'error' );
                     return null;
                  }
             } else {
                 wc_add_notice(  'Unable to connect to Paydog.', 'error' );
                 return null;
             }
         }



		/*
		 * We're processing the payments here, everything about it is in Step 5

		 * sort of handy https://stackoverflow.com/questions/34093683/what-is-the-success-url-for-woocommerce
		 */
		public function process_payment( $order_id ) {

            /*
             * Your API interaction could be built with wp_remote_post()
             * From https://developer.wordpress.org/reference/functions/wp_remote_post/

             * Also useful https://gist.github.com/igorbenic/9d555cd278c128deee0f43a56eba14da
             */
             $requestId = $this->ensureRequest($order_id);
             if($requestId !=null  ) {
                if($this->poll_for_paid_status($requestId)){
                    $this->reset_cart($order_id);
                    $order = wc_get_order( $order_id );
                    return array(
                         'result' => 'success',
                         'redirect' => $this->get_return_url( $order )
                    );
                } else {
                   wc_add_notice(  'Request Timed out. Please refresh and try again.', 'error' );
                   return;
                }
             } else {
                 return;
             }

	 	}


		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {

            $order = wc_get_order( $_GET['orderId'] );
            $order->payment_complete();
            $order->reduce_order_stock();

            update_option('webhook_debug', $_GET);

	 	}
 	}
}
