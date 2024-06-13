<?php
error_reporting(0);
/*
Plugin Name: WooCommerce aamarPay A Bangladeshi Payment Gateway
Plugin URI: https://www.aamarpay.com/
Description: WooCommerce aamarPay Payment Gateway Module.
Version: 9.7.0
Author: Soft Tech Innovation Ltd.


    Copyright:   2009-2021 Soft Tech Innovation Limited.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_gateway_aamarpay_init', 0);
define('aamarpay_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_aamarpay_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_aamarpay extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/
		public function __construct(){

			$this->id 					= 'aamarpay'; // ID for WC to associate the gateway values
			$this->method_title 		= 'aamarPay'; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= 'aamarPay A Bangladeshi Payment Gateway'; // Gateway Description as seen in Admin Dashboad
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend

			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC

			// Special settigns if gateway is on Test Mode
			if ( $this->settings['test_mode'] == 'test' ) {
				$test_title 		= ' [TEST MODE]';
				$test_description 	= '<br/><br/><u>This Is Test. Any Order Placed will not Accepted';
				$key_URL				= 'https://sandbox.aamarpay.com/index.php';
				$key_secret			=  $this->settings['key_secret'];
			} else {
				$test_ttitle		= '';
				$test_description	= '';
				$key_URL				= 'https://secure.aamarpay.com/index.php';
				$key_secret			= $this->settings['key_secret'];
			} //END-{else}-test_mode=yes

			$this->title 			= $this->settings['title'].$test_title; // Title as displayed on Frontend
			$this->description 		= $this->settings['description'].$test_description; // Description as displayed on Frontend
			if ( $this->settings['show_logo'] != "no" ) { // Check if Show-Logo has been allowed
				$this->icon 		= aamarpay_IMG . 'logo_' . $this->settings['show_logo'] . '.png';
			}
			$this->merchant_id = $this->settings['merchant_id'];

            $this->key_secret 		= $key_secret;
			$this->liveurl 			= 'https://secure.aamarpay.com/index.php';

            $this->msg['message']	= '';
            $this->msg['class'] 	= '';

			add_action('init', array(&$this, 'check_aamarpay_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_aamarpay_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_aamarpay', array(&$this, 'receipt_page'));
		} //END-__construct

        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 			=> __('Enable/Disable:', 'woo_aamarpay'),
					'type' 			=> 'checkbox',
					'label' 			=> __('Enable aamarPay', 'woo_aamarpay'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),

				// Title as displayed on Frontend
      			'title' => array(
					'title' 			=> __('Title:', 'woo_aamarpay'),
					'type'			=> 'text',
					'default' 		=> __('Online Payments', 'woo_aamarpay'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'woo_aamarpay'),
					'desc_tip' 		=> true
				),
				// Description as displayed on Frontend
      			'description' => array(
					'title' 			=> __('Description:', 'woo_aamarpay'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Credit or Debit card or internet banking through aamarpay.', 'woo_aamarpay'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'woo_aamarpay'),
					'desc_tip' 		=> true
				),
				// aamarPay Merhcant ID
				'merchant_id' => array(
                    'title' => __('Merchant ID', 'Redwan'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at aamarPay of "email at support@aamarpay.com"')),
  				// LIVE Key-Secret

    			'key_secret' => array(
					'title' 			=> __('aamarPay Signature Key:', 'woo_aamarpay'),
					'type' 			=> 'text',
					'description' 	=> __('Given to Merchant by aamarPay'),
					'desc_tip' 		=> true
                ),
  				// Mode of Transaction
    //   			'test_server' => array(
				// 	'title' 			=> __('test_mode (true/false):', 'woo_aamarpay'),
				// 	'type' 			=> 'text',
				// 	'description' 	=> __('true/false'),
				// 	'desc_tip' 		=> true
    //             ),
    	            'test_server' => array(
					'title' 			=> __('Sandbox Mode:', 'woo_aamarpay'),
					'type' 			=> 'select',
					'label' 			=> __('Enable aamarpay TEST Transactions.', 'woo_aamarpay'),
					'options' 		=> array('ON'=>'ON','OFF'=>'OFF'),
					'default' 		=> 'OFF',
					'description' 	=> __('Sandbox Mode', 'woo_aamarpay'),
					'desc_tip' 		=> false
                ),
  				// Page for Redirecting after Transaction
      			'redirect_page' => array(
					'title' 			=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->aamarpay_get_pages('Select Page'),
					'description' 	=> __('URL of success page', 'woo_aamarpay'),
					'desc_tip' 		=> true
                ),
  				// Show Logo on Frontend
      			'show_logo' => array(
					'title' 			=> __('Show Logo:', 'woo_aamarpay'),
					'type' 			=> 'select',
					'label' 			=> __('Enable aamarpay TEST Transactions.', 'woo_aamarpay'),
					'options' 		=> array('no'=>'No Logo','icon-light'=>'Light - Icon','icon'=>'Dark'),
					'default' 		=> 'no',
					'description' 	=> __('<strong>aamarPay (Light)</strong> | Icon: <img src="'. aamarpay_IMG . 'logo_icon-light.png" height="24px" /> | Logo: <img src="'. aamarpay_IMG . 'logo-light.png" height="24px" /><br/>' . "\n"
										 .'<strong>aamarPay Dark&nbsp;&nbsp;</strong> | Icon: <img src="'. aamarpay_IMG . 'logo.png" height="24px" /> | Logo: <img src="'. aamarpay_IMG . 'logo.png" height="24px" /> | Logo (Full): <img src="'. aamarpay_IMG . 'logo.png" height="24px" />', 'woo_aamarpay'),
					'desc_tip' 		=> false
                )
			);

		} //END-init_form_fields

        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			echo '<h3>'.__('aamarPay', 'woo_aamarpay').'</h3>';
			echo '<p>'.__('Please make a note if you are using ', 'woo_aamarpay').'<strong>'.__('"aamarPay"', 'woo_aamarpay').'</strong>'.__(' or ', 'woo_aamarpay').'<strong>'.__('"aamarPay"', 'woo_aamarpay').'</strong>'.__(' as you main account.', 'woo_aamarpay').'</p>';
			echo '<p><small><strong>'.__('Confirm your Mode: Is it LIVE or TEST.').'</strong></small></p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields

        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'woo_aamarpay').'</strong><br/>' . __('The payment page will open soon.', 'woo_aamarpay').'</p>';
			echo $this->generate_aamarpay_form($order);
		} //END-receipt_page

        /**
         * Generate button link
         **/
		function generate_aamarpay_form($order_id){
			global $woocommerce;
			
			$order = new WC_Order( $order_id );
			// Redirect URL
			if ( $this->redirect_page_id == '' || $this->redirect_page == 0 ) {
				$redirect_url = get_site_url() . "/";
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}
			// Redirect URL : For WooCoomerce 2.0
			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$redirect_url = add_query_arg( 'wc-api', get_class( $this ), esc_url($this->get_return_url($order)) );
			}
			$items = $woocommerce->cart->get_cart();
			$prodcut_title="";
    			foreach($items as $item => $values) {
                $_product =  wc_get_product( $values['data']->get_id());
                $product_title .= $_product->get_title().",";

                }
			$cancel_url = get_site_url() . "/";
            $productinfo = $product_title;
			$randomNumber = time();
			$prefix = strtoupper(substr($this->merchant_id, 0, 3));
			$tran_id = $prefix . "" . $randomNumber . rand(10000, 99999);


			if($this->settings['test_server']=='ON'){
			    $paymentapiurl = "https://sandbox.aamarpay.com/jsonpost.php";
			    $verificaionapirl = "https://sandbox.aamarpay.com/api/v1/trxcheck/request.php";
			}else{
			    $paymentapiurl = "https://secure.aamarpay.com/jsonpost.php";
			    $verificaionapirl = "https://secure.aamarpay.com/api/v1/trxcheck/request.php";
			}
			$currency = get_option('woocommerce_currency');
			$store_id = $this->merchant_id;
			$curl = curl_init();
			$encrypt_amount = base64_encode($order -> order_total);
			curl_setopt_array($curl, array(
			  CURLOPT_URL => $paymentapiurl,
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => '',
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => 'POST',
			  CURLOPT_POSTFIELDS =>'{
				"store_id": "'.$this->merchant_id.'",
				"tran_id": "'.$tran_id.'",
				"success_url": "'.$redirect_url.'",
				"fail_url": "'.$redirect_url.'",
				"cancel_url": "'.$cancel_url.'",
				"amount": "'.$order -> order_total.'",
				"currency": "'.$currency.'",
				"signature_key": "'.$this->key_secret.'",
				"desc": "'.$productinfo.'",
				"cus_name": "'.$order -> billing_first_name .' '. $order -> billing_last_name.'",
				"cus_email": "'.$order -> billing_email.'",
				"cus_add1": "'.$order -> billing_address_1.'",
				"cus_add2": "'.$order -> billing_address_1.'",
				"cus_city": "'.$order -> billing_city.'",
				"cus_state": "'.$order -> shipping_state.'",
				"cus_postcode": "",
				"cus_country": "'.$order -> billing_country.'",
				"cus_phone": "'.$order -> billing_phone.'",
				"opt_a" : "'.$order_id.'",
				"opt_d" : "'.$encrypt_amount.'",
				"type": "json"
			}',
			  CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			  ),
			));

			$response = curl_exec($curl);

			curl_close($curl);

			$responseObj = json_decode($response);

			if(isset($responseObj->payment_url) && !empty($responseObj->payment_url)) {

			  $paymentUrl = $responseObj->payment_url;
			  return header('Location: '. $paymentUrl);
			  exit();

			}else{
				echo $response;
			}


		} //END-generate_aamarpay_form

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  $checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}
			

			return array(
				'result' => 'success',
				'redirect' => add_query_arg(
					'order',
					$order->id,
					add_query_arg(
						'key',
						$order->order_key,
						$checkout_payment_url
					)
				)
			);
		} //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_aamarpay_response(){
            global $woocommerce;
			
		
			if(isset($_POST['mer_txnid']) && isset($_POST['store_id']))
			{
				$order_id = $_REQUEST['mer_txnid'];

				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );


						$trans_authorised = false;
						$mertrxn_id = $_REQUEST['mer_txnid'];
						$storeid = $this->merchant_id;
						$signature_key = $this->key_secret;

						//transaction verification API calling
						if($this->settings['test_server']=='ON'){
						    $curl = curl_init();
                            curl_setopt_array($curl, array(
                              CURLOPT_URL => "https://sandbox.aamarpay.com/api/v1/trxcheck/request.php?request_id=$mertrxn_id&store_id=$storeid&signature_key=$signature_key&type=json",
                              CURLOPT_RETURNTRANSFER => true,
                              CURLOPT_ENCODING => '',
                              CURLOPT_MAXREDIRS => 10,
                              CURLOPT_TIMEOUT => 0,
                              CURLOPT_FOLLOWLOCATION => true,
                              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                              CURLOPT_CUSTOMREQUEST => 'GET',
                              CURLOPT_HTTPHEADER => array(
                                'Cookie: PHPSESSID=kgkkemq7cslcnjb4gqvll6pafi'
                              ),
                            ));
						}else{
						     $curl = curl_init();
                            curl_setopt_array($curl, array(
                              CURLOPT_URL => "https://secure.aamarpay.com/api/v1/trxcheck/request.php?request_id=$mertrxn_id&store_id=$storeid&signature_key=$signature_key&type=json",
                              CURLOPT_RETURNTRANSFER => true,
                              CURLOPT_ENCODING => '',
                              CURLOPT_MAXREDIRS => 10,
                              CURLOPT_TIMEOUT => 0,
                              CURLOPT_FOLLOWLOCATION => true,
                              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                              CURLOPT_CUSTOMREQUEST => 'GET',
                              CURLOPT_HTTPHEADER => array(
                                'Cookie: PHPSESSID=kgkkemq7cslcnjb4gqvll6pafi'
                              ),
                            ));
						}

                        $response = curl_exec($curl);
                        curl_close($curl);

                        //transaction Verification API end
                        $data = json_decode($response,true);
                        $pg_trxnid = $data['pg_txnid'];
                        $mid = $data['opt_a'];
                        $card_type = $data['payment_type'];
                        $card_number = $data['cardnumber'];
                        $risk_level =  $_REQUEST['pg_card_risklevel'];
                        $status = $data['pay_status'];
                        $error = $data['error_title'];
                        $status_code = $data['status_code'];
						$encrypt_data_amount = $data['opt_d'];
                        $amount_original = $data['amount_original'];
                        $decrypt_amount = base64_decode($encrypt_data_amount);
                        $formattedNumber = sprintf("%.2f", $amount_original);
                        $decrypt_amount = sprintf("%.2f", $decrypt_amount);

						if( $order->status !=='completed' ){
							
								$status = strtolower($status);
								if($status=="successful" && $risk_level==0 && $status_code=='2' && $decrypt_amount==$formattedNumber ){
									
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									
									$this->msg['class'] = 'woocommerce-message';
									
									if($order->status == 'processing'){
										$order->add_order_note('aamarPay ID: '.$pg_trxnid.' ('.$mid.')<br/>Card Type: '.$card_type.'('.$card_number.')<br/>Risk Level: '.$risk_level.'');
									}else{
										$order = wc_get_order($mid);
										$order->payment_complete();
										$order->add_order_note('aamarPay payment successful.<br/>aamarPay ID: '.$pg_trxnid.' ('.$mid.')<br/>Card Type: '.$card_type.'('.$card_number.')<br/>Risk Level: '.$risk_level.'');
										
										$order->update_status('processing');
										$woocommerce->cart->empty_cart();										
									}
								}else if($status=="successful" && $risk_level==1 && $status_code=='2'){
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. aamarPay will keep you posted regarding the status of your order through eMail. Please Co-Operate With EasyPayaWay.";
									$this->msg['class'] = 'woocommerce-info';
									$order->add_order_note('aamarPay payment On Hold<br/>aamarPay ID: '.$pg_trxnid.' ('.$mid.')<br/>Card Type: '.$card_type.'('.$card_number.')<br/>Risk Level: '.$risk_level.'');
									$order->update_status('on-hold');
									$woocommerce -> cart -> empty_cart();
								}else{

									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order->add_order_note('Transaction ERROR: '.$error.'<br/>aamarPay ID: '.$pg_trxnid.' ('.$mid.')<br/>Card Type: '.$card_type.'('.$card_number.')<br/>Risk Level: '.$risk_level.'');
								}
							header("Location: ".esc_url($this->get_return_url($order)));
							if($trans_authorised==false){
								$order->update_status('failed');
							}

							//removed for WooCommerce 2.0
							//add_action('the_content', array(&$this, 'aamarpay_showMessage'));
						}
					}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
					}
				}

				if ( $this->redirect_page_id == '' || $this->redirect_page_id == 0 ) {
					$redirect_url = esc_url($this->get_return_url($order));
				} else {
					$redirect_url = esc_url($this->get_return_url($order));
				}

				return array(
					'result' => 'success',
					'redirect' => $redirect_url
				);

			}

        } //END-check_aamarpay_response





        /**
         * Get Page list from WordPress
         **/
		function aamarpay_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';

                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} //END-aamarpay_get_pages

	} //END-class

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_aamarpay_gateway($methods) {
		$methods[] = 'WC_Gateway_aamarpay';
		return $methods;
	}//END-wc_add_gateway

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_aamarpay_gateway' );

} //END-init

/**
* 'Settings' link on plugin page
**/
add_filter( 'plugin_action_links', 'aamarpay_add_action_plugin', 10, 5 );
function aamarpay_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {

			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_aamarpay">' . __('Settings') . '</a>');

    			$actions = array_merge($settings, $actions);

		}

		return $actions;
}//END-settings_add_action_link
