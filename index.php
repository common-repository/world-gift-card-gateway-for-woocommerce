<?php
/*
   Plugin Name: World Gift Card Gateway For WooCommerce
   Description: Extends WooCommerce to Process Payments with World Gift Card API. 
   Version: 1.0.0
   Author: World Gift Card 
   Author URI: https://Worldgiftcard.com
   License: Under GPL2
   WC requires at least: 3.0.0
*/

add_action('plugins_loaded', 'wgc_payment_gateway_init', 0);

function wgc_payment_gateway_init() {
    class WC_Gateway_WGC extends WC_Payment_Gateway {
	
		public function __construct(){

			$this->id               = 'worldgift';
			$this->has_fields       = true;
			$this->method_title     = __('World Gift Card', 'wgc');
			$this->method_description = 'Pay securely using your World Gift Cards through the WGC API.';
			$this->init_form_fields();
			$this->init_settings();
			$this->enabled          = $this->settings['enabled'];
			$this->title            = $this->settings['title'];
			$this->description      = $this->settings['description'];
			$this->merchant_key     = $this->settings['merchant_key'];   

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
		function init_form_fields()
		{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'wgc' ),
				'type' => 'checkbox',
				'label' => __( 'Enable World Gift Card', 'wgc' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', '' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wgc' ),
				'default' => __( 'World Gift Card', 'wgc' ),
				'desc_tip'      => true,
			),
			'merchant_key' => array(
					'title'        => __('Merchant Key', 'wgc'),
					'type'         => 'password',
					'description'  => __('This is your API Merchant ID, contact World Gift Card at 888-745-4112 if you are unsure of your ID.')
			),
			'login_key' => array(
					'title'        => __('Login Key', 'wgc'),
					'type'         => 'password',
					'description'  => __('This is your API Login ID, contact World Gift Card at 888-745-4112 if you are unsure of your ID.')
			),
			'pass_key' => array(
					'title'        => __('Pass Key', 'wgc'),
					'type'         => 'password',
					'description'  => __('This is your API Password Key, contact World Gift Card at 888-745-4112 if you are unsure of your ID.')
			)
		);
		}

		function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$order_data = $order->get_data();
			
			$wgc_mnum=sanitize_text_field($this->get_option('merchant_key'));
			$wgc_login=sanitize_text_field($this->get_option('login_key'));
			$wgc_pass=sanitize_text_field($this->get_option('pass_key'));
			$wgc_amt=$order->get_total();
			$wgc_card=sanitize_text_field($_POST['worldgift-card-number']);
			
			$url = 'http://www.wgchost.com/w3/service.asmx';
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
			  <soap:Body>
				<Sale xmlns="http://wgchost.com/w3/">
					<mnum>'.$wgc_mnum.'</mnum>
					<id>'.$wgc_card.'</id>
					<cn>1234</cn>
					<amount>'.$wgc_amt.'</amount>
					<login>'.$wgc_login.'</login>
					<pass>'.$wgc_pass.'</pass>
				</Sale>
			  </soap:Body>
			</soap:Envelope>';
			$response = wp_remote_post( 
				$url, 
				array(
					'method' => 'POST',
					'timeout' => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers' => array(
						'Content-Type' => 'text/xml'
					),
					'body' => $xml,
					'sslverify' => false
				)
			);
			$body = wp_remote_retrieve_body( $response );

			$API_search = '<SaleResult xsi:type="xsd:string">';
			$API_return = substr($body, strpos($body, $API_search) + strlen($API_search));
			$API_final = substr($API_return, 0, strpos($API_return, '<'));
			
			if ( strlen($API_final) == 0 || !is_numeric($API_final) ) {
				wc_add_notice( __('Payment error:', 'woothemes') ." ".$API_final, 'error' );
				return;
			}			
			
			$order->payment_complete();
			$woocommerce->cart->empty_cart();

			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}
		
		public function payment_fields() {
		?>
		<fieldset id="worldgift-card-number" class="worldgift-card-number">
			<p class="form-row form-row-first">
				<label for="worldgift-card-number"><?php _e("Gift Card number", 'woothemes') ?> <span class="required">*</span></label>
			
			<input type="text" class="input-text" class="input-text wc-credit-card-form-card-number" placeholder="Card Number" name="worldgift-card-number" maxlength="16" autocomplete="off" />
			</p>
			</fieldset>
		<?php
		}
	}
}

function add_WGC_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_WGC'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_WGC_gateway_class' );