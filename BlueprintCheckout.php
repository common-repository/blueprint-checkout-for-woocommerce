<?php
/*
 * Plugin Name: Blueprint checkout for WooCommerce
 * Plugin URI: https://blueprint.store/woocommerce/blueprint-payments
 * Description: Replace WooCommerce checkout with Blueprint checkout
 * Author: Blueprint
 * Author URI: https://blueprint.store
 * Version: 1.0.1
 */

add_filter( 'woocommerce_payment_gateways', 'blueprint_payments_add_gateway_class' );

add_filter( 'woocommerce_checkout_fields' , 'blueprint_checkout_custom_fields' );


function blueprint_checkout_custom_fields( $fields ) {
    $fields['billing']['billing_first_name']['required'] = false;
    $fields['billing']['billing_last_name']['required'] = false;
    $fields['billing']['billing_company']['required'] = false;
    $fields['billing']['billing_address_1']['required'] = false;
    $fields['billing']['billing_address_2']['required'] = false;
    $fields['billing']['billing_city']['required'] = false;
    $fields['billing']['billing_postcode']['required'] = false;
    $fields['billing']['billing_country']['required'] = false;
    $fields['billing']['billing_state']['required'] = false;
    $fields['billing']['billing_email']['required'] = false;
    $fields['billing']['billing_phone']['required'] = false;

    $fields['shipping']['shipping_first_name']['required'] = false;
    $fields['shipping']['shipping_last_name']['required'] = false;
    $fields['shipping']['shipping_company']['required'] = false;
    $fields['shipping']['shipping_address_1']['required'] = false;
    $fields['shipping']['shipping_address_2']['required'] = false;
    $fields['shipping']['shipping_city']['required'] = false;
    $fields['shipping']['shipping_postcode']['required'] = false;
    $fields['shipping']['shipping_country']['required'] = false;
    $fields['shipping']['shipping_state']['required'] = false;

    return $fields;
}

function blueprint_payments_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Blueprint_Payments_Gateway';
    return $gateways;
}

add_action( 'plugins_loaded', 'blueprint_payments_init_gateway_class' );
function blueprint_payments_init_gateway_class() {
    class WC_Blueprint_Payments_Gateway extends WC_Payment_Gateway {
        public function __construct() {

            $this->id = 'blueprint_payments';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Blueprint checkout for WooCommerce';
            $this->method_description = 'Blueprint checkout for WooCommerce';
            $this->supports = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->publishable_key = $this->testmode === 'no' ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Blueprint checkout for WooCommerce',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Continue to payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via Blueprint.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Api Key',
                    'type'        => 'text'
                ),
                'publishable_key' => array(
                    'title'       => 'Live Api Key',
                    'type'        => 'text'
                ),
            );
        }

        public function payment_fields() {}

        public function payment_scripts() {
            if (!is_cart() && !is_checkout() && !isset( $_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled ) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if (empty( $this->publishable_key ) ) {
                return;
            }

            // do not work with card detaies without SSL unless your website is in a test mode
            if (!$this->testmode && ! is_ssl() ) {
               return;
            }
            $loadingGif = plugins_url( 'loading.gif', __FILE__ );

            wp_enqueue_script( 'blueprint_checkout_js', 'scripts/checkout.js' );
            wp_register_script( 'woocommerce_blueprint', plugins_url( 'scripts/checkout.js', __FILE__ ), array( 'jquery', 'blueprint_checkout_js' ) );
            wp_localize_script( 'woocommerce_blueprint', 'blueprint_params', array(
                'publishableKey' => $this->publishable_key,
                'loadingGif' => $loadingGif
            ) );

            wp_enqueue_script( 'woocommerce_blueprint' );
        }

        public function validate_fields() {}

        public function process_payment( $order_id ) {
            //global $woocommerce;
            //$order = wc_get_order($order_id);
            $args = array(
                'apiKey' => $this->publishable_key,
                'orderId' => $order_id
            );

            $url = 'https://'.(!$this->testmode ? 'prod' : 'dev').'.blueprint-api.com/checkout-creator/woo';

            $response = wp_remote_post($url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => json_encode($args),
                'cookies' => array()
            ));

            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);
                if (isset($body['status']) && $body['status'] == 'success') {
                    // Redirect to the checkout page
                    return array(
                        'result' => 'success',
                        'redirect' => $body['link']
                    );
                } else {
                    wc_add_notice('Please try again.', 'error');
                    return;
                }
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }

        public function webhook() {}
    }
}
