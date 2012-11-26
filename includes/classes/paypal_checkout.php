<?php

require_once(DIR_FS_INC . 'xtc_write_user_info.inc.php');

define('PROXY_HOST', '127.0.0.1');
define('PROXY_PORT', '808');
define('VERSION', '3.0');

class paypal_checkout {

	var $API_UserName,
		$API_Password,
	    $API_Signature,
	    $API_Endpoint,
	    $version,
	    $location_error,
	    $NOTIFY_URL,
	    $EXPRESS_CANCEL_URL,
	    $EXPRESS_RETURN_URL,
	    $CANCEL_URL,
	    $RETURN_URL,
	    $EXPRESS_URL,
	    $IPN_URL,
	    $ppAPIec,
	    $ppAPIdp,
	    $payPalURL;

	function paypal_checkout() {

		if(PAYPAL_MODE=='sandbox'){	
		$this->API_UserName 	= PAYPAL_API_SANDBOX_USER;
		$this->API_Password 	= PAYPAL_API_SANDBOX_PWD;
		$this->API_Signature	= PAYPAL_API_SANDBOX_SIGNATURE;			
		$this->API_Endpoint 	= 'https://api-3t.sandbox.paypal.com/nvp';
		$this->EXPRESS_URL		= 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=';
		$this->IPN_URL			= 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}elseif(PAYPAL_MODE=='live'){	
		$this->API_UserName 	= PAYPAL_API_USER;
		$this->API_Password 	= PAYPAL_API_PWD;
		$this->API_Signature	= PAYPAL_API_SIGNATURE;					
		$this->API_Endpoint 	= 'https://api-3t.paypal.com/nvp';
		$this->EXPRESS_URL		= 'https://www.paypal.com/webscr&cmd=_express-checkout&token=';
		$this->IPN_URL			= 'https://www.paypal.com/cgi-bin/webscr';
		}

		if(ENABLE_SSL == true){
		$this->NOTIFY_URL = HTTPS_SERVER.DIR_WS_CATALOG.'callback/paypal/ipn.php';
		
		$this->EXPRESS_CANCEL_URL = HTTPS_SERVER.DIR_WS_CATALOG.FILENAME_SHOPPING_CART.'?XTCsid='.xtc_session_id();
		$this->EXPRESS_RETURN_URL = HTTPS_SERVER.DIR_WS_CATALOG.FILENAME_PAYPAL_CHECKOUT.'?XTCsid='.xtc_session_id();
		$this->CANCEL_URL = HTTPS_SERVER.DIR_WS_CATALOG.FILENAME_CHECKOUT_PAYMENT.'?XTCsid='.xtc_session_id().'&error=true&error_message='.PAYPAL_ERROR;
		$this->RETURN_URL = HTTPS_SERVER.DIR_WS_CATALOG.FILENAME_CHECKOUT_PROCESS.'?XTCsid='.xtc_session_id();		
		}else{
		$this->NOTIFY_URL = HTTP_SERVER.DIR_WS_CATALOG.'callback/paypal/ipn.php';	
		
		$this->EXPRESS_CANCEL_URL = HTTP_SERVER.DIR_WS_CATALOG.FILENAME_SHOPPING_CART.'?XTCsid='.xtc_session_id();
		$this->EXPRESS_RETURN_URL = HTTP_SERVER.DIR_WS_CATALOG.FILENAME_PAYPAL_CHECKOUT.'?XTCsid='.xtc_session_id();
		$this->CANCEL_URL = HTTP_SERVER.DIR_WS_CATALOG.FILENAME_CHECKOUT_PAYMENT.'?XTCsid='.xtc_session_id().'&error=true&error_message='.PAYPAL_ERROR;
		$this->RETURN_URL = HTTP_SERVER.DIR_WS_CATALOG.FILENAME_CHECKOUT_PROCESS.'?XTCsid='.xtc_session_id();				
		}
		
		$this->version			= VERSION;
		$this->USE_PROXY		= FALSE;
		$this->payPalURL = '';
		
		$this->ppAPIec = $this->buildAPIKey(PAYPAL_API_KEY, 'ec');
		$this->ppAPIdp = $this->buildAPIKey(PAYPAL_API_KEY, 'dp');

	}


	function build_express_checkout_button($total, $currency){
		global $PHP_SELF;

		if(MODULE_PAYMENT_PAYPALEXPRESS_STATUS=='True'){
			if ($_SESSION['languages_id']=='2') { // de
			$source = 'https://www.paypal.com/de_DE/i/btn/btn_xpressCheckout.gif';
			} else {
			$source = 'https://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif';
			}		
			$button .= '<a class="paypal_checkout" href="'.xtc_href_link(basename($PHP_SELF), xtc_get_all_get_params(array ('action')).'action=paypal_express_checkout').'"><img src="'.$source.'"></a>';

			
			return $button;
		}
	}

	function paypal_auth_call($force_redirect=''){
		global $order;
		
		// Shipping:
		
		if (!isset ($_SESSION['sendto'])) {
			$_SESSION['sendto'] = $_SESSION['customer_default_address_id'];
		} else {
			// verify the selected shipping address
			$check_address_query = xtc_db_query("select count(*) as total from ".TABLE_ADDRESS_BOOK." where customers_id = '".(int) $_SESSION['customer_id']."' and address_book_id = '".(int) $_SESSION['sendto']."'");
			$check_address = xtc_db_fetch_array($check_address_query);
		
			if ($check_address['total'] != '1') {
				$_SESSION['sendto'] = $_SESSION['customer_default_address_id'];
				if (isset ($_SESSION['shipping']))
					unset ($_SESSION['shipping']);
			}
		}		

		// Shipping END
		
					unset($_SESSION['reshash'])	;
					unset($_SESSION['nvpReqArray'])	;

					if($force_redirect!='' && $force_redirect=='checkout_process'){
						$paymentAmount= round($order->info['total'],2);
						$currencyCodeType=$order->info['currency'];					
					}else{
						$paymentAmount= round($_SESSION['cart']->show_total(),2);
						$currencyCodeType=$_SESSION['currency'];
					}

				   if(PAYPAL_COUNTRY_MODE == 'de'){
				   $paymentType='Sale';
				   }else{
				   $paymentType=PAYPAL_EXPRESS_PAYMENTACTION;
				   }

		 			/* The returnURL is the location where buyers return when a
					payment has been succesfully authorized.
					The cancelURL is the location buyers are sent to when they hit the
					cancel button during authorization of payment during the PayPal flow
					*/

		 			if($force_redirect!='' && $force_redirect=='checkout_process'){
		 				$returnURL =urlencode($this->RETURN_URL);
		   				$cancelURL =urlencode($this->CANCEL_URL);	
						$add = '&ADDROVERRIDE=1';	
						
		 			}else{ 
		   				$returnURL =urlencode($this->EXPRESS_RETURN_URL);
		   				$cancelURL =urlencode($this->EXPRESS_CANCEL_URL);
		 			}
		 			/* Construct the parameter string that describes the PayPal payment
					the varialbes were set in the web form, and the resulting string
					is stored in $nvpstr
					*/
		 			
		 			$sh_name = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['firstname'].' '.$order->delivery['lastname']));
					$sh_street = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['street_address']));
					$sh_street_2 = '';
					$sh_city = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['city']));
					$sh_state = urlencode($order->delivery['state']);
					$sh_country = urlencode($order->delivery['country']['iso_code_2']);
					$sh_phonenum = urlencode($order->customer['telephone']);
					$sh_zip = urlencode($order->delivery['postcode']);
			
					if($_SESSION['paypal_express_new_customer']!='true'){
						$address = '&SHIPTONAME='.$sh_name.'&SHIPTOSTREET='.$sh_street.'&SHIPTOCITY='.$sh_city.'&SHIPTOCOUNTRY='.$sh_country.'&SHIPTOSTATE='.$sh_state.'&SHIPTOZIP='.$sh_zip.'&SHIPTOPHONENUM='.$sh_phonenum.''; 
					}
		 			
					
		   			$nvpstr="&Amt=".$paymentAmount."&PAYMENTACTION=".$paymentType."&ReturnUrl=".$returnURL."&CANCELURL=".$cancelURL . $address . $add."&CURRENCYCODE=".$currencyCodeType;
		   			
		 			/* Make the call to PayPal to set the Express Checkout token
					If the API call succeded, then redirect the buyer to PayPal
					to begin to authorize payment.  If an error occured, show the
					resulting errors
					*/
		   			$resArray=$this->hash_call("SetExpressCheckout",$nvpstr);
		   			$_SESSION['reshash']= $resArray;

		   			$ack = strtoupper($resArray["ACK"]);

		  			 if($ack=="SUCCESS"){
						// Redirect to paypal.com here
						$token = urldecode($resArray["TOKEN"]);
						$this->payPalURL = $this->EXPRESS_URL.''.$token;

						return $this->payPalURL;
				  	} else  {
				  		$this->build_error_message($_SESSION['reshash']);
				  		$this->payPalURL = $this->EXPRESS_CANCEL_URL;
				  		return $this->payPalURL;
					}

	}

	function paypal_get_customer_data(){

		   $nvpstr="&TOKEN=".$_SESSION['reshash']['TOKEN'];

		 /* Make the API call and store the results in an array.  If the
			call was a success, show the authorization details, and provide
			an action to complete the payment.  If failed, show the error
			*/

		   $resArray=$this->hash_call("GetExpressCheckoutDetails",$nvpstr);
		   $_SESSION['reshash'] = array_merge($_SESSION['reshash'], $resArray) ;
		   $ack = strtoupper($resArray["ACK"]);

		   if($ack=="SUCCESS"){

		   		$_SESSION['paypal_express_checkout'] = true;
		   		$_SESSION['paypal_express_payment_modules'] = 'paypalexpress.php';

				$this->check_customer();

			  } else  {
					$this->build_error_message($_SESSION['reshash']);
				  	$this->payPalURL = $this->EXPRESS_CANCEL_URL;
				  	return $this->payPalURL;
			  }
	}

	function check_customer(){

		if (!isset ($_SESSION['customer_id'])) {
			$check_customer_query = xtc_db_query("select * from ".TABLE_CUSTOMERS." where customers_email_address = '".xtc_db_input($_SESSION['reshash']['EMAIL'])."' and account_type = '0'");
			if (!xtc_db_num_rows($check_customer_query)) {
				$this->create_account();
			}else{
				$check_customer_query = xtc_db_query("select * from ".TABLE_CUSTOMERS." where customers_email_address = '".xtc_db_input($_SESSION['reshash']['EMAIL'])."' and account_type = '0'");
				$check_customer = xtc_db_fetch_array($check_customer_query);
				$this->login_customer($check_customer);
				if(PAYPAL_EXPRESS_ADDRESS_OVERRIDE == 'true' && $_SESSION['pp_allow_address_change']!='true'){
					$this->create_shipping_address($check_customer);
				}
			}
		}else{			
			if(PAYPAL_EXPRESS_ADDRESS_OVERRIDE == 'true' && $_SESSION['pp_allow_address_change']!='true'){
				$check_customer_query = xtc_db_query("select * from ".TABLE_CUSTOMERS." where customers_id = '".xtc_db_input($_SESSION['customer_id'])."' and account_type = '0'");
				$check_customer = xtc_db_fetch_array($check_customer_query);
				$this->create_shipping_address($check_customer);
			}
		}
	}

	function create_account(){

		//$gender = xtc_db_prepare_input($_POST['gender']);
		
		$firstname = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['FIRSTNAME']));
		$lastname = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['LASTNAME']));
		$email_address = xtc_db_prepare_input($_SESSION['reshash']['EMAIL']);
		$company = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['BUSINESS']));
		$street_address = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['SHIPTOSTREET'] . $_SESSION['reshash']['SHIPTOSTREET_2']));
		$postcode = xtc_db_prepare_input($_SESSION['reshash']['SHIPTOZIP']);
		$city = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['SHIPTOCITY']));
		$state = xtc_db_prepare_input($_SESSION['reshash']['SHIPTOSTATE']);
		$telephone = xtc_db_prepare_input($_SESSION['reshash']['PHONENUM']);

		$country_query = xtc_db_query("select * from ".TABLE_COUNTRIES." where countries_iso_code_2 = '".xtc_db_input($_SESSION['reshash']['SHIPTOCOUNTRYCODE'])."' ");
		$tmp_country = xtc_db_fetch_array($country_query);

		$country = xtc_db_prepare_input($tmp_country['countries_id']);

		$customers_status = DEFAULT_CUSTOMERS_STATUS_ID;

		$sql_data_array = array (
			'customers_status' => $customers_status,
			'customers_firstname' => $firstname,
			'customers_lastname' => $lastname,
			'customers_email_address' => $email_address,
			'customers_telephone' => $telephone,
			'customers_date_added' => 'now()',
			'customers_last_modified' => 'now()');

		xtc_db_perform(TABLE_CUSTOMERS, $sql_data_array);

		$_SESSION['paypal_express_new_customer'] = 'true';

		$_SESSION['customer_id'] = xtc_db_insert_id();
		$user_id = xtc_db_insert_id();
		xtc_write_user_info($user_id);
		$sql_data_array = array (
			'customers_id' => $_SESSION['customer_id'],
			'entry_firstname' => $firstname,
			'entry_lastname' => $lastname,
			'entry_street_address' => $street_address,
			'entry_postcode' => $postcode,
			'entry_city' => $city,
			'entry_country_id' => $country,
			'address_date_added' => 'now()',
			'address_last_modified' => 'now()'
		);

		if (ACCOUNT_COMPANY == 'true')
			$sql_data_array['entry_company'] = $company;
		if (ACCOUNT_SUBURB == 'true')
			$sql_data_array['entry_suburb'] = $suburb;
		if (ACCOUNT_STATE == 'true') {
				$sql_data_array['entry_zone_id'] = '0';
				$sql_data_array['entry_state'] = $state;
		}

		xtc_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

		$address_id = xtc_db_insert_id();

		xtc_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . $address_id . "' where customers_id = '" . (int) $_SESSION['customer_id'] . "'");

		xtc_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int) $_SESSION['customer_id'] . "', '0', now())");

		if (isset ($_SESSION['tracking']['refID'])) {
			$campaign_check_query_raw = "SELECT *
						                            FROM " . TABLE_CAMPAIGNS . "
						                            WHERE campaigns_refID = '" . $_SESSION[tracking][refID] . "'";
			$campaign_check_query = xtc_db_query($campaign_check_query_raw);
			if (xtc_db_num_rows($campaign_check_query) > 0) {
				$campaign = xtc_db_fetch_array($campaign_check_query);
				$refID = $campaign['campaigns_id'];
			} else {
				$refID = 0;
			}

			xtc_db_query("update " . TABLE_CUSTOMERS . " set
			                                 refferers_id = '" . $refID . "'
			                                 where customers_id = '" . (int) $_SESSION['customer_id'] . "'");

			$leads = $campaign['campaigns_leads'] + 1;
			xtc_db_query("update " . TABLE_CAMPAIGNS . " set
					                         campaigns_leads = '" . $leads . "'
			                                 where campaigns_id = '" . $refID . "'");
		}

		if (ACTIVATE_GIFT_SYSTEM == 'true') {
			// GV Code Start
			// ICW - CREDIT CLASS CODE BLOCK ADDED  ******************************************************* BEGIN
			if (NEW_SIGNUP_GIFT_VOUCHER_AMOUNT > 0) {
				$coupon_code = create_coupon_code();
				$insert_query = xtc_db_query("insert into " . TABLE_COUPONS . " (coupon_code, coupon_type, coupon_amount, date_created) values ('" . $coupon_code . "', 'G', '" . NEW_SIGNUP_GIFT_VOUCHER_AMOUNT . "', now())");
				$insert_id = xtc_db_insert_id($insert_query);
				$insert_query = xtc_db_query("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $insert_id . "', '0', 'Admin', '" . $email_address . "', now() )");

				$_SESSION['reshash']['SEND_GIFT'] = 'true';
				$_SESSION['reshash']['GIFT_AMMOUNT'] = $xtPrice->xtcFormat(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT, true);
				$_SESSION['reshash']['GIFT_CODE'] = $coupon_code;
				$_SESSION['reshash']['GIFT_LINK'] = xtc_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false);

			}
			if (NEW_SIGNUP_DISCOUNT_COUPON != '') {
				$coupon_code = NEW_SIGNUP_DISCOUNT_COUPON;
				$coupon_query = xtc_db_query("select * from " . TABLE_COUPONS . " where coupon_code = '" . $coupon_code . "'");
				$coupon = xtc_db_fetch_array($coupon_query);
				$coupon_id = $coupon['coupon_id'];
				$coupon_desc_query = xtc_db_query("select * from " . TABLE_COUPONS_DESCRIPTION . " where coupon_id = '" . $coupon_id . "' and language_id = '" . (int) $_SESSION['languages_id'] . "'");
				$coupon_desc = xtc_db_fetch_array($coupon_desc_query);
				$insert_query = xtc_db_query("insert into " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) values ('" . $coupon_id . "', '0', 'Admin', '" . $email_address . "', now() )");

				$_SESSION['reshash']['SEND_COUPON'] = 'true';
				$_SESSION['reshash']['COUPON_DESC'] = $coupon_desc['coupon_description'];
				$_SESSION['reshash']['COUPON_CODE'] = $coupon['coupon_code'];

			}
			// ICW - CREDIT CLASS CODE BLOCK ADDED  ******************************************************* END
			// GV Code End       // create templates
		}

		$_SESSION['ACCOUNT_PASSWORD'] = 'true';

		// Login Customer
		$check_customer_query = xtc_db_query("select * from ".TABLE_CUSTOMERS." where customers_email_address = '".xtc_db_input($email_address)."' and account_type = '0'");
		$check_customer = xtc_db_fetch_array($check_customer_query);
		$this->login_customer($check_customer);
		if(PAYPAL_EXPRESS_ADDRESS_OVERRIDE == 'true'){
			$this->create_shipping_address($check_customer);
		}

	}
	
	function login_customer($check_customer){
		global $econda;

			if (SESSION_RECREATE == 'True') {
				xtc_session_recreate();
			}

			$check_country_query = xtc_db_query("select entry_country_id, entry_zone_id from ".TABLE_ADDRESS_BOOK." where customers_id = '".(int) $check_customer['customers_id']."' and address_book_id = '".$check_customer['customers_default_address_id']."'");
			$check_country = xtc_db_fetch_array($check_country_query);

			$_SESSION['customer_gender'] = $check_customer['customers_gender'];
			$_SESSION['customer_first_name'] = $check_customer['customers_firstname'];
			$_SESSION['customer_last_name'] = $check_customer['customers_lastname'];
			$_SESSION['customer_id'] = $check_customer['customers_id'];
			$_SESSION['customer_vat_id'] = $check_customer['customers_vat_id'];
			$_SESSION['customer_default_address_id'] = $check_customer['customers_default_address_id'];
			$_SESSION['customer_country_id'] = $check_country['entry_country_id'];
			$_SESSION['customer_zone_id'] = $check_country['entry_zone_id'];
			$_SESSION['customer_email_address'] = $check_customer['customers_email_address'];

			$date_now = date('Ymd');

			xtc_db_query("update ".TABLE_CUSTOMERS_INFO." SET customers_info_date_of_last_logon = now(), customers_info_number_of_logons = customers_info_number_of_logons+1 WHERE customers_info_id = '".(int) $_SESSION['customer_id']."'");
			xtc_write_user_info((int) $_SESSION['customer_id']);
			// restore cart contents
			$_SESSION['cart']->restore_contents($check_customer['customers_status']);
			//$_SESSION['cart']->check_cart($check_customer['customers_status']);

			if (is_object($econda)) $econda->_loginUser();

	}

	function create_shipping_address($check_customer){
				
		//$gender = xtc_db_prepare_input($_POST['gender']);
		
		$pos = strrpos($_SESSION['reshash']['SHIPTONAME'], ' ');
		$lenght = strlen($_SESSION['reshash']['SHIPTONAME']);
		
		$firstname = iconv("UTF-8", $_SESSION['language_charset'], substr($_SESSION['reshash']['SHIPTONAME'], 0, $pos));
		$lastname = iconv("UTF-8", $_SESSION['language_charset'], substr($_SESSION['reshash']['SHIPTONAME'], ($pos+1), $lenght));
		
		$email_address = xtc_db_prepare_input($_SESSION['reshash']['EMAIL']);
		$company = xtc_db_prepare_input($_SESSION['reshash']['BUSINESS']);
		$street_address = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['SHIPTOSTREET'] . $_SESSION['reshash']['SHIPTOSTREET_2']));
		$postcode = xtc_db_prepare_input($_SESSION['reshash']['SHIPTOZIP']);
		$city = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $_SESSION['reshash']['SHIPTOCITY']));
		$state = xtc_db_prepare_input($_SESSION['reshash']['SHIPTOSTATE']);
		$telephone = xtc_db_prepare_input($_SESSION['reshash']['PHONENUM']);

		$country_query = xtc_db_query("select * from ".TABLE_COUNTRIES." where countries_iso_code_2 = '".xtc_db_input($_SESSION['reshash']['SHIPTOCOUNTRYCODE'])."' ");
		$tmp_country = xtc_db_fetch_array($country_query);

		$country = xtc_db_prepare_input($tmp_country['countries_id']);
	
		$sql_data_array = array (
			'customers_id' => $_SESSION['customer_id'],
			'entry_firstname' => $firstname,
			'entry_lastname' => $lastname,
			'entry_street_address' => $street_address,
			'entry_postcode' => $postcode,
			'entry_city' => $city,
			'entry_country_id' => $country,
			'address_date_added' => 'now()',
			'address_last_modified' => 'now()',
			'address_class' => 'paypal'
		);

		if (ACCOUNT_COMPANY == 'true')
			$sql_data_array['entry_company'] = $company;
		if (ACCOUNT_STATE == 'true') {
				$sql_data_array['entry_zone_id'] = '0';
				$sql_data_array['entry_state'] = $state;
		}

		$check_address_query = xtc_db_query("select address_book_id from ".TABLE_ADDRESS_BOOK." where customers_id = '".(int) $_SESSION['customer_id']."' and address_class = 'paypal'");
		$check_address = xtc_db_fetch_array($check_address_query);
		
		if ($check_address['address_book_id']!='') {
			xtc_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array, 'update', "address_book_id = '".(int) $check_address['address_book_id']."' and customers_id ='".(int) $_SESSION['customer_id']."'");
			$send_to = $check_address['address_book_id'];			

		}else{			
			xtc_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);	
			$send_to = xtc_db_insert_id();
		}
		
		$_SESSION['sendto'] = $send_to;
	}
	
	
	function complete_express_ceckout($tmp_id, $data='', $check=false){
		global $xtPrice,  $order;
		
		
		if($check==true){
				$order = new order($tmp_id);	
		}
		
		if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
			$customers_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		} else {
			$customers_ip = $_SERVER["REMOTE_ADDR"];
		}

		if ($_SESSION['customers_status']['customers_status_show_price_tax'] == 0 && $_SESSION['customers_status']['customers_status_add_tax_ot'] == 1) {
			$total = $order->info['total'] + $order->info['tax'];
		} else {
			$total = $order->info['total'];
		}
		
		if($check==true){
			$total = round($order->info['pp_total'], $xtPrice->get_decimal_places($_SESSION['currency']));	
		}
		
		
		$products_count = 0;
		for ($i = 0, $n = sizeof($order->products); $i < $n; $i ++) {
		
		$products_tax = 0;	
		$products_tax = $xtPrice->xtcGetTax($order->products[$i]['price'], $order->products[$i]['tax']);		
			
		if ($_SESSION['customers_status']['customers_status_show_price_tax'] == 0 && $_SESSION['customers_status']['customers_status_add_tax_ot'] == 1) {
			$products_price = $order->products[$i]['price'];
		} else {
			$products_price = $order->products[$i]['price'] - products_tax;
		}
		
			$tmp_products .= '&L_NAME'.$i.'='.$order->products[$i]['name'].'&L_NUMBER'.$i.'='.$order->products[$i]['model'].'&L_QTY'.$i.'='.$order->products[$i]['qty'].'&L_TAXAMT'.$i.'='.$products_tax.'&L_AMT'.$i.'='. $products_price;
 			$products_count ++;
		}
		
		$amount = round($total, $xtPrice->get_decimal_places($_SESSION['currency']));
		if($check==true){
			$shipping = $order->info['pp_shipping'];	
		}else{
			$shipping = $xtPrice->xtcFormat($order->info['shipping_cost'], false, 0, true);
		}
		$item_amt = $amount-$shipping;


		if($data['token']!=''){
			$tkn = $data['token'];
		}else{
			$tkn =  $_SESSION['nvpReqArray']['TOKEN'];
		}
		
		if($data['PayerID']!=''){
			$payer = $data['PayerID'];
		}else{
			$payer =  $_SESSION['reshash']['PAYERID'];
		}		
		
		$token =urlencode($tkn);
		$paymentAmount =urlencode ($total);
		
		if(PAYPAL_COUNTRY_MODE == 'de'){
			$paymentType='Sale';
		}else{
			$paymentType=PAYPAL_EXPRESS_PAYMENTACTION;
		}		
		
		$currCodeType = urlencode($_SESSION['currency']);
		$payerID = urlencode($payer);
		$serverName = urlencode($_SERVER['SERVER_NAME']);
		$notify_url  = urlencode($this->NOTIFY_URL);
		$inv_num = urlencode($tmp_id);
		$item_amt = urlencode($item_amt);
		$tax_amt = urlencode($order->info['tax']);
		$shipping_amt = urlencode($shipping);
		$button_source = urlencode($this->ppAPIec);
		
		$sh_name = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['firstname'].' '.$order->delivery['lastname']));
		$sh_street = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['street_address']));
		$sh_street_2 = '';
		$sh_city = urlencode(iconv($_SESSION['language_charset'], "UTF-8", $order->delivery['city']));
		$sh_state = urlencode($order->delivery['state']);
		if($check==true){
			$sh_country = urlencode($order->delivery['country_iso_2']);	
		}else{
			$sh_country = urlencode($order->delivery['country']['iso_code_2']);
		}
		
		$sh_phonenum = urlencode($order->customer['telephone']);
		$sh_zip = urlencode($order->delivery['postcode']);
			
		if($_SESSION['paypal_express_new_customer']!='true'){
			$adress = '&SHIPTONAME='.$sh_name.'&SHIPTOSTREET='.$sh_street.'&SHIPTOCITY='.$sh_city.'&SHIPTOCOUNTRY='.$sh_country.'&SHIPTOSTATE='.$sh_state.'&SHIPTOZIP='.$sh_zip.'&SHIPTOPHONENUM='.$sh_phonenum.''; 
		}
		
		$nvpstr='&TOKEN='.$token.'&PAYERID='.$payerID.'&PAYMENTACTION='.$paymentType.'&AMT='.$paymentAmount.'&CURRENCYCODE='.$currCodeType.'&IPADDRESS='.$customers_ip.'&NOTIFYURL='.$notify_url.'&INVNUM='.$inv_num.$adress.'&BUTTONSOURCE='.$button_source;

 		/* Make the call to PayPal to finalize payment
   		 If an error occured, show the resulting errors
    	*/
			$resArray=$this->hash_call("DoExpressCheckoutPayment",$nvpstr);
		  	$_SESSION['reshash'] = array_merge($_SESSION['reshash'], $resArray) ;
		   $ack = strtoupper($resArray["ACK"]);

		   if($ack!="SUCCESS"){
					$this->build_error_message($_SESSION['reshash']);
				  	$this->payPalURL = $this->EXPRESS_CANCEL_URL;
				  	return $this->payPalURL;
			  }
	}

function doDirectPayment($data, $tmp_id){
global $xtPrice, $order;

	if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
		$customers_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	} else {
		$customers_ip = $_SERVER["REMOTE_ADDR"];
	}

		if ($_SESSION['customers_status']['customers_status_show_price_tax'] == 0 && $_SESSION['customers_status']['customers_status_add_tax_ot'] == 1) {
			$total = $order->info['total'] + $order->info['tax'];
		} else {
			$total = $order->info['total'];
		}
		
		$products_count = 0;
		for ($i = 0, $n = sizeof($order->products); $i < $n; $i ++) {

		$products_tax = 0;	
		$products_tax = $xtPrice->xtcGetTax($order->products[$i]['price'], $order->products[$i]['tax']);					
			
		if ($_SESSION['customers_status']['customers_status_show_price_tax'] == 0 && $_SESSION['customers_status']['customers_status_add_tax_ot'] == 1) {
			$products_price = $order->products[$i]['price'];
		} else {
			$products_price = $order->products[$i]['price'] - products_tax;
		}
		
			$tmp_products .= '&L_NAME'.$i.'='.$order->products[$i]['name'].'&L_NUMBER'.$i.'='.$order->products[$i]['model'].'&L_QTY'.$i.'='.$order->products[$i]['qty'].'&L_TAXAMT'.$i.'='.$products_tax.'&L_AMT'.$i.'='. $products_price;
 			$products_count ++;
		}

		$amount = round($total, $xtPrice->get_decimal_places($_SESSION['currency']));
		$shipping = $xtPrice->xtcFormat($order->info['shipping_cost'], false, 0, true);
		$item_amt = $amount-$shipping;
		 		

$paymentType =urlencode('Sale');
$firstName =urlencode( $data['firstName']);
$lastName =urlencode( $data['lastName']);
$creditCardType =urlencode( $data['creditCardType']);
$creditCardNumber = urlencode($data['creditCardNumber']);
$expDateMonth =urlencode($data['expDateMonth']);
$ip_address = urlencode($customers_ip);
$notify_url  = urlencode($this->NOTIFY_URL);
$inv_num = urlencode($tmp_id);
$item_amt = urlencode($item_amt);
$shipping_amt = urlencode($shipping);
$tax_amt = urlencode($order->info['tax']);

// Month must be padded with leading zero
$padDateMonth = str_pad($expDateMonth, 2, '0', STR_PAD_LEFT);

$expDateYear =urlencode( $data['expDateYear']);
$cvv2Number = urlencode($data['cvv2Number']);
$address1 = urlencode($data['address1']);
$address2 = urlencode($data['address2']);
$city = urlencode($data['city']);
$state =urlencode( $data['state']);
$zip = urlencode($data['zip']);
$amount = urlencode($total);
$currencyCode=urlencode($_SESSION['currency']);
$paymentType=urlencode($paymentType);
$country_code = urlencode($data['country']);
$button_source = urlencode($this->ppAPIdp);
//////////

/* Construct the request string that will be sent to PayPal.
   The variable $nvpstr contains all the variables and is a
   name value pair string with & as a delimiter */
   
  $nvpstr="&PAYMENTACTION=$paymentType&AMT=$amount&CREDITCARDTYPE=$creditCardType&ACCT=$creditCardNumber&EXPDATE=".$padDateMonth.$expDateYear."&CVV2=$cvv2Number&FIRSTNAME=$firstName&LASTNAME=$lastName&STREET=$address1&CITY=$city&STATE=$state"."&ZIP=$zip&COUNTRYCODE=US&CURRENCYCODE=$currencyCode&BUTTONSOURCE=$button_source";


/* Make the API call to PayPal, using API signature.
   The API response is stored in an associative array called $resArray */
   $resArray=$this->hash_call("doDirectPayment",$nvpstr);
   //$_SESSION['reshash']=$resArray;

   $nvpstr_1='&TRANSACTIONID='.urlencode($resArray['TRANSACTIONID']);
   $resArray_1=$this->hash_call("gettransactionDetails",$nvpstr_1);
   
   $_SESSION['reshash'] = array_merge($resArray, $resArray_1) ;   
   
/* Display the API response back to the browser.
   If the response from PayPal was a success, display the response parameters'
   If the response was an error, display the errors received using APIError.php.
   */
$ack = strtoupper($resArray["ACK"]);

		   if($ack!="SUCCESS"){
					$this->build_error_message($_SESSION['reshash']=$resArray);
				  	$this->payPalURL = $this->EXPRESS_CANCEL_URL;
				  	return $this->payPalURL;
			  }

}


	/**
	  * hash_call: Function to perform the API call to PayPal using API signature
	  * @methodName is name of API  method.
	  * @nvpStr is nvp string.
	  * returns an associtive array containing the response from the server.
	*/

	function hash_call($methodName,$nvpStr,$pp_token='')
	{
		//declaring of global variables
		//global $API_Endpoint,$version,$API_UserName,$API_Password,$API_Signature,$nvp_Header;

		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->API_Endpoint.$pp_token);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);
	    //if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
	   //Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
		if($this->USE_PROXY)
		curl_setopt ($ch, CURLOPT_PROXY, PROXY_HOST.":".PROXY_PORT);


		//NVPRequest for submitting to server
		$nvpreq="METHOD=".urlencode($methodName)."&VERSION=".urlencode($this->version)."&PWD=".urlencode($this->API_Password)."&USER=".urlencode($this->API_UserName)."&SIGNATURE=".urlencode($this->API_Signature).$nvpStr;
//echo $nvpreq;
		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch,CURLOPT_POSTFIELDS,$nvpreq);

		//getting response from server
		$response = curl_exec($ch);

		//convrting NVPResponse to an Associative Array
		$nvpResArray=$this->deformatNVP($response);
		$nvpReqArray=$this->deformatNVP($nvpreq);
		
		
		$_SESSION['nvpReqArray']= $nvpReqArray;

		if (curl_errno($ch)) {
			// moving to display page to display curl errors
			  $_SESSION['curl_error_no']=curl_errno($ch) ;
			  $_SESSION['curl_error_msg']=curl_error($ch);
			  $this->build_error_message($_SESSION['reshash']);
			 // $this->payPalURL = $this->EXPRESS_CANCEL_URL;
			 // return $this->payPalURL;
		 } else {
			 //closing the curl
				curl_close($ch);
		  }

	return $nvpResArray;
	}

	/** This function will take NVPString and convert it to an Associative Array and it will decode the response.
	  * It is usefull to search for a particular key and displaying arrays.
	  * @nvpstr is NVPString.
	  * @nvpArray is Associative Array.
	  */

	function deformatNVP($nvpstr)
	{

		$intial=0;
	 	$nvpArray = array();


		while(strlen($nvpstr)){
					
			//postion of Key
			$keypos= strpos($nvpstr,'=');
			//position of value
			$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

			/*getting the Key and Value values and storing in a Associative Array*/
			$keyval=substr($nvpstr,$intial,$keypos);
			$valval=substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
			//decoding the respose
			$nvpArray[urldecode($keyval)] =urldecode( $valval);
			$nvpstr=substr($nvpstr,$valuepos+1,strlen($nvpstr));
	     }
		return $nvpArray;
	}

		
	function build_error_message($resArray=''){
		global $messageStack;

			if(isset($_SESSION['curl_error_no'])) {
				$errorCode= $_SESSION['curl_error_no'] ;
				$errorMessage=$_SESSION['curl_error_msg'] ;

				$error .=  'Error Number: '.  $errorCode . '<br />';
				$error .=  'Error Message: '.  $errorMessage . '<br />';

			} else {

				$error .=  'Ack: '.  $resArray['ACK'] . '<br />';
				$error .=  'Correlation ID: '.  $resArray['CORRELATIONID']  . '<br />';
				$error .=  'Version:'.  $resArray['VERSION'] . '<br />';

				$count=0;
				while (isset($resArray["L_SHORTMESSAGE".$count])) {
		  			$errorCode    = $resArray["L_ERRORCODE".$count];
		  			$shortMessage = $resArray["L_SHORTMESSAGE".$count];
		  			$longMessage  = $resArray["L_LONGMESSAGE".$count];
		  			$count=$count+1;

		 			$error .=  'Error Number:'.  $errorCode . '<br />';
		  			$error .=  'Error Short Message: '.   $shortMessage . '<br />';
		  			$error .=  'Error Long Message: '.  $longMessage . '<br />';
 				}//end while
			}// end else

		$_SESSION['reshash']['FORMATED_ERRORS'] = $error;

	}

	function write_status_history($o_id) {
		
		if (empty($o_id) ) return false;

		$ack = strtoupper($_SESSION['reshash']["ACK"]);
	    if($ack!="SUCCESS"){
			$o_status = PAYPAL_ORDER_STATUS_REJECTED_ID;
	    } else {
	    	$o_status = PAYPAL_ORDER_STATUS_SUCCESS_ID;
	    }
		/*
		while (list ($key, $value) = each($_SESSION['reshash'])) {
			
			$comment .= $key.'='.$value;

		}
		*/
		$order_history_data = array('orders_id' => $o_id,
		 						    'orders_status_id' => $o_status,
		 						    'date_added' => 'now()',
		 						    'customer_notified' => '0',
		 						    'comments' => $comment);
		xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY,$order_history_data);
		xtc_db_query("UPDATE " . TABLE_ORDERS . " SET orders_status = '" . $o_status . "', last_modified = now() WHERE orders_id = '" . xtc_db_prepare_input($o_id) . "'");		

		return true;
		
	}
	
	function callback_process($data) {
		global $_GET;
		$this->data = $data;

		//$this->_logTrans($data);
		
		require_once (DIR_WS_CLASSES . 'class.phpmailer.php');
			if (EMAIL_TRANSPORT == 'smtp')
				require_once (DIR_WS_CLASSES . 'class.smtp.php');
			require_once (DIR_FS_INC . 'xtc_Security.inc.php');

		if (isset ($this->data['invoice']) && is_numeric($this->data['invoice']) && ($this->data['invoice'] > 0)) {
			$order_query = xtc_db_query("SELECT	currency, currency_value
						  								 FROM " . TABLE_ORDERS . "
						  								 WHERE orders_id = '" . xtc_db_prepare_input($this->data['invoice']) . "'");

			if (xtc_db_num_rows($order_query) > 0) {
				$order = xtc_db_fetch_array($order_query);
				$total_query = xtc_db_query("SELECT value
																	 FROM " . TABLE_ORDERS_TOTAL . " 
																	 WHERE orders_id = '" . xtc_db_prepare_input($this->data['invoice']) . "' 
																	 AND class = 'ot_total' limit 1");

				
								$ipn_data = array();
				
				$ipn_data['reason_code'] = xtc_db_prepare_input($this->data['reason_code']);
				$ipn_data['xtc_order_id'] = xtc_db_prepare_input($this->data['invoice']);
				$ipn_data['payment_type'] = xtc_db_prepare_input($this->data['payment_type']);
				$ipn_data['payment_status'] = xtc_db_prepare_input($this->data['payment_status']);
				$ipn_data['pending_reason'] = xtc_db_prepare_input($this->data['pending_reason']);
				$ipn_data['invoice'] = xtc_db_prepare_input($this->data['invoice']);
				$ipn_data['mc_currency'] = xtc_db_prepare_input($this->data['mc_currency']);
				$ipn_data['first_name'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['first_name']));
				$ipn_data['last_name'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['last_name']));
				
				$ipn_data['address_name'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['address_name']));
				$ipn_data['address_street'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['address_street']));
				$ipn_data['address_city'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['address_city']));
				$ipn_data['address_state'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['address_state']));
				$ipn_data['address_zip'] = xtc_db_prepare_input($this->data['address_zip']);
				$ipn_data['address_country'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['address_country']));
				$ipn_data['address_status'] = xtc_db_prepare_input($this->data['address_status']);
				
				$ipn_data['payer_email'] = xtc_db_prepare_input($this->data['payer_email']);
				$ipn_data['payer_id'] = xtc_db_prepare_input($this->data['payer_id']);
				$ipn_data['payer_status'] = xtc_db_prepare_input($this->data['payer_status']);
				
				$ipn_data['payment_date'] = xtc_db_prepare_input($this->datetime_to_sql_format($this->data['payment_date']));
				$ipn_data['business'] = xtc_db_prepare_input(iconv("UTF-8", $_SESSION['language_charset'], $this->data['business']));
				$ipn_data['receiver_email'] = xtc_db_prepare_input($this->data['receiver_email']);
				$ipn_data['receiver_id'] = xtc_db_prepare_input($this->data['receiver_id']);
				
				$ipn_data['txn_id'] = xtc_db_prepare_input($this->data['txn_id']);
				$ipn_data['parent_txn_id'] = xtc_db_prepare_input($this->data['parent_txn_id']);
				
				$ipn_data['mc_gross'] = xtc_db_prepare_input($this->data['mc_gross']);
				$ipn_data['mc_fee'] = xtc_db_prepare_input($this->data['mc_fee']);
				
				$ipn_data['payment_gross'] = xtc_db_prepare_input($this->data['payment_gross']);
				$ipn_data['payment_fee'] = xtc_db_prepare_input($this->data['payment_fee']);


				$ipn_data['notify_version'] = xtc_db_prepare_input($this->data['notify_version']);
				$ipn_data['verify_sign'] = xtc_db_prepare_input($this->data['verify_sign']);
				$ipn_data['txn_type']= $this->ipn_determine_txn_type($this->data['txn_type']);

				$_transQuery = "SELECT paypal_ipn_id FROM paypal WHERE txn_id = '".$ipn_data['txn_id']."'";
				$_transQuery = xtc_db_query($_transQuery);
				$_transQuery = xtc_db_fetch_array($_transQuery);
				if ($_transQuery['paypal_ipn_id']!='') {
					$insert_id = $_transQuery['paypal_ipn_id'];
					// do not insert data in main table
//					xtc_db_perform('paypal',$ipn_data,'update','paypal_ipn_id='.$insert_id);	
					// only update status of main transaction
					
					xtc_db_query("update paypal set payment_status = '".$ipn_data['payment_status']."',pending_reason='". $ipn_data['pending_reason']."', last_modified = now() where paypal_ipn_id = '".$insert_id."'");
				} else {
					
					$ipn_data['date_added']='now()';
					$ipn_data['last_modified']='now()';
					xtc_db_perform('paypal',$ipn_data);	
					$insert_id = xtc_db_insert_id();
				}

				$paypal_order_history = array ('paypal_ipn_id' => $insert_id,
                                   'txn_id' => $ipn_data['txn_id'],
                                   'parent_txn_id' => $ipn_data['parent_txn_id'],
                                   'payment_status' => $ipn_data['payment_status'],
                                   'pending_reason' => $ipn_data['pending_reason'],
                                   'mc_amount' => $ipn_data['mc_gross'],
                                   'date_added' => 'now()'
                                  );
				xtc_db_perform('paypal_status_history',$paypal_order_history);	
				
				
				$total = xtc_db_fetch_array($total_query);
				$crlf = "\n";
				$comment_status = xtc_db_prepare_input($this->data['payment_status']) . ' ' . xtc_db_prepare_input($this->data['mc_gross']) . xtc_db_prepare_input($this->data['mc_currency']) . $crlf;
				$comment_status .= ' ' . xtc_db_prepare_input($this->data['first_name']) . ' ' . xtc_db_prepare_input($this->data['last_name']) . ' ' . xtc_db_prepare_input($this->data['payer_email']);

				if (isset ($this->data['payer_status'])) {
					$comment_status .= ' is ' . xtc_db_prepare_input($this->data['payer_status']);
				}
				
				$comment_status .= '.' . $crlf . $crlf . ' [';

				if (isset ($this->data['test_ipn']) && is_numeric($this->data['test_ipn']) && ($_POST['test_ipn'] > 0)) {
					$debug = '(Sandbox-Test Mode) ';
				}

				$comment_status .= $crlf . 'Fee=' . xtc_db_prepare_input($this->data['mc_fee']) . xtc_db_prepare_input($this->data['mc_currency']);

				if (isset ($this->data['pending_reason'])) {
					$comment_status .= $crlf . ' Pending Reason=' . xtc_db_prepare_input($this->data['pending_reason']);
				}

				if (isset ($this->data['reason_code'])) {
					$comment_status .= $crlf . ' Reason Code=' . xtc_db_prepare_input($this->data['reason_code']);
				}

				$comment_status .= $crlf . ' Payment=' . xtc_db_prepare_input($this->data['payment_type']);
				$comment_status .= $crlf . ' Date=' . xtc_db_prepare_input($this->data['payment_date']);

				if (isset ($this->data['parent_txn_id'])) {
					$comment_status .= $crlf . ' ParentID=' . xtc_db_prepare_input($this->data['parent_txn_id']);
				}

				$comment_status .= $crlf . ' ID=' . xtc_db_prepare_input($_POST['txn_id']);

				//Set status for default (Pending)
				$order_status_id = PAYPAL_ORDER_STATUS_PENDING_ID;

				$parameters = 'cmd=_notify-validate';

				foreach ($this->data as $key => $value) {
					$parameters .= '&' . $key . '=' . urlencode(stripslashes($value));
				}

				//$this->_logTransactions($parameters);

				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, $this->IPN_URL);
				
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				
				$result = curl_exec($ch);
				curl_close($ch);

				if ($result == 'VERIFIED' or $result == '1') {
					if ($this->data['payment_status'] == 'Completed') {

						if (PAYPAL_ORDER_STATUS_SUCCESS_ID > 0) {
							$order_status_id = PAYPAL_ORDER_STATUS_SUCCESS_ID;
													
							
						}
					}
					//Set status for Denied, Failed, Refunded or Reversed
					elseif (($this->data['payment_status'] == 'Denied') OR ($this->data['payment_status'] == 'Failed') OR ($this->data['payment_status'] == 'Refunded') OR ($this->data['payment_status'] == 'Reversed')) {
						$order_status_id = PAYPAL_ORDER_STATUS_REJECTED_ID;
					}
				} else {
					$debug .= '[INVALID VERIFIED FAILED] - ' . $result . "\n";
					$order_status_id = PAYPAL_ORDER_STATUS_REJECTED_ID;
					$error_reason = 'Received INVALID responce but invoice and Customer matched.';
				}

				$comment_status .= ']';

				xtc_db_query("UPDATE " . TABLE_ORDERS . " 
													  SET orders_status = '" . $order_status_id . "', last_modified = now() 
													  WHERE orders_id = '" . xtc_db_prepare_input($this->data['invoice']) . "'");

				$sql_data_array = array (
					'orders_id' => xtc_db_prepare_input($this->data['invoice']
				), 'orders_status_id' => $order_status_id, 'date_added' => 'now()', 'customer_notified' => '0', 'comments' => 'PayPal IPN ' . $comment_status . '');

				xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
								
			} else {
				$error_reason = 'No order found for invoice=' . xtc_db_prepare_input($this->data['invoice']) . ' with customer=' . (int) $this->data['custom'] . '.';
			}
		} else {
			$error_reason = 'No invoice id found on received data.';
		}

		if (xtc_not_null(EMAIL_SUPPORT_ADDRESS) && strlen($error_reason)) {
			$email_body = $error_reason . "\n\n";
			$email_body .= $_SERVER["REQUEST_METHOD"] . " - " . $_SERVER["REMOTE_ADDR"] . " - " . $_SERVER["HTTP_REFERER"] . " - " . $_SERVER["HTTP_ACCEPT"] . "\n\n";
			$email_body .= '$_POST:' . "\n\n";

			foreach ($this->data as $key => $value) {
				$email_body .= $key . '=' . $value . "\n";
			}

			$email_body .= "\n" . '$_GET:' . "\n\n";

			foreach ($_GET as $key => $value) {
				$email_body .= $key . '=' . $value . "\n";
			}

			

			xtc_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, EMAIL_SUPPORT_ADDRESS, EMAIL_SUPPORT_ADDRESS, '', EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, false, false, 'PayPal IPN Invalid Process', $email_body, $email_body);
		}

	}	

	function datetime_to_sql_format($paypalDateTime) {
		//Copyright (c) 2004 DevosC.com
		$months = array (
			'Jan' => '01',
			'Feb' => '02',
			'Mar' => '03',
			'Apr' => '04',
			'May' => '05',
			'Jun' => '06',
			'Jul' => '07',
			'Aug' => '08',
			'Sep' => '09',
			'Oct' => '10',
			'Nov' => '11',
			'Dec' => '12'
		);
		$hour = substr($paypalDateTime, 0, 2);
		$minute = substr($paypalDateTime, 3, 2);
		$second = substr($paypalDateTime, 6, 2);
		$month = $months[substr($paypalDateTime, 9, 3)];
		$day = (strlen($day = preg_replace("/,/", '', substr($paypalDateTime, 13, 2))) < 2) ? '0' . $day : $day;
		$year = substr($paypalDateTime, -8, 4);
		if (strlen($day) < 2)
			$day = '0' . $day;
		return ($year . "-" . $month . "-" . $day . " " . $hour . ":" . $minute . ":" . $second);
	}


	function logging_status($o_id) {
		$data = array_merge($_SESSION['reshash'],$_SESSION['nvpReqArray']);

 		$data_array = array (
 						   'xtc_order_id' => $o_id,
 						   'txn_type' => $data['TRANSACTIONTYPE'],
 						   'reason_code' => $data['REASONCODE'],
 						   'payment_type' => $data['PAYMENTTYPE'],
 						   'payment_status' => $data['PAYMENTSTATUS'],
 						   'pending_reason' => $data['PENDINGREASON'],
 						   'invoice' => $data['INVNUM'],
 						   'mc_currency' => $data['CURRENCYCODE'],
 						   'first_name' => iconv("UTF-8", $_SESSION['language_charset'], $data['FIRSTNAME']),
 						   'last_name' => iconv("UTF-8", $_SESSION['language_charset'], $data['LASTNAME']),
 						   'payer_business_name' => iconv("UTF-8", $_SESSION['language_charset'], $data['BUSINESS']),
 						   'address_name' => iconv("UTF-8", $_SESSION['language_charset'], $data['SHIPTONAME']),
 						   'address_street' => iconv("UTF-8", $_SESSION['language_charset'], $data['SHIPTOSTREET']),
 						   'address_city' => iconv("UTF-8", $_SESSION['language_charset'], $data['SHIPTOCITY']),
 						   'address_state' => iconv("UTF-8", $_SESSION['language_charset'], $data['SHIPTOSTATE']),
 						   'address_zip' => $data['SHIPTOZIP'],
 						   'address_country' => iconv("UTF-8", $_SESSION['language_charset'], $data['SHIPTOCOUNTRYNAME']),
 						   'address_status' => $data['ADDRESSSTATUS'],
 						   'payer_email' => $data['EMAIL'],
 						   'payer_id' => $data['PAYERID'],
 						   'payer_status' => $data['PAYERSTATUS'],
 						   'payment_date' => $data['TIMESTAMP'],
 						   'business' => '',
 						   'receiver_email' => '',
 						   'receiver_id' => '',
 						   'txn_id' => $data['TRANSACTIONID'],
 						   'parent_txn_id' => '',
 						   'num_cart_items' => '',
 						   'mc_gross' => $data['AMT'],
 						   'mc_fee' => $data['FEEAMT'],
 						   'mc_authorization' => $data['AMT'],
 						   'payment_gross' => '',
 						   'payment_fee' => '',
 						   'settle_amount' => $data['SETTLEAMT'],
 						   'settle_currency' => '',
 						   'exchange_rate' => $data['EXCHANGERATE'],
 						   'notify_version' => $data['VERSION'],
 						   'verify_sign' => '',
 						   'last_modified' => '',
 						   'date_added' => 'now()',
 						   'memo' => $data['DESC']);
		xtc_db_perform(TABLE_PAYPAL,$data_array);
		return true;
	}

	function buildAPIKey($key, $pay){
		$key_arr=explode(',',$key);
		$k='';
		for ($i=0; $i<count($key_arr);$i++) $k.=chr($key_arr[$i]);
			if($pay=='ec'){
		    return $k.'EC_AT_31';		
			}elseif($pay=='dp'){
			return $k.'DP_AT_31';	
			}
	}	
	
	  function ipn_determine_txn_type($txn_type = 'unknown') {

    if (substr($txn_type,0,8) == 'cleared-') return $txn_type;
    if ($this->data['txn_type'] == 'send_money') return $this->data['txn_type'];
    if ($this->data['txn_type'] == 'express_checkout' || $this->data['txn_type'] == 'cart') $txn_type = $this->data['txn_type'];
// if it's not unique or linked to a parent, then:
// 1. could be an e-check denied / cleared
// 2. could be an express-checkout "pending" transaction which has been Accepted in the merchant's PayPal console and needs activation in Zen Cart
    if ($this->data['payment_status']=='Completed' && $txn_type=='express_checkout' && $this->data['payment_type']=='echeck') {
      $txn_type = 'express-checkout-cleared';
      return $txn_type;
    }
    if ($this->data['payment_status']=='Completed' && $this->data['payment_type']=='echeck') {
      $txn_type = 'echeck-cleared';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Denied' || $this->data['payment_status']=='Failed') && $this->data['payment_type']=='echeck') {
      $txn_type = 'echeck-denied';
      return $txn_type;
    }
    if ($this->data['payment_status']=='Denied') {
      $txn_type = 'denied';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Pending') && $this->data['pending_reason']=='echeck') {
      $txn_type = 'pending-echeck';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Pending') && $this->data['pending_reason']=='address') {
      $txn_type = 'pending-address';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Pending') && $this->data['pending_reason']=='intl') {
      $txn_type = 'pending-intl';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Pending') && $this->data['pending_reason']=='multi-currency') {
      $txn_type = 'pending-multicurrency';
      return $txn_type;
    }
    if (($this->data['payment_status']=='Pending') && $this->data['pending_reason']=='multi-verify') {
      $txn_type = 'pending-verify';
      return $txn_type;
    }
    return $txn_type;
  }

		function _logTransactions($parameters) {

		$logFilePP = DIR_FS_CATALOG . 'includes/logs/payment.paypal_ipn.log';

		$line = 'PP TRANS|' . date("d.m.Y H:i", time()) . '|' . xtc_get_ip_address() . '|';

		foreach ($_POST as $key => $val)
			$line .= $key . ':' . $val . '|';

		error_log($line . "\n", 3, $logFilePP);

		}
		
		
		function _logTrans($data) {

			while (list ($key, $value) = each($data)) {
			$line .= $key . ':' . $val . '|';
			}
				
			xtc_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, EMAIL_SUPPORT_ADDRESS, EMAIL_SUPPORT_ADDRESS, '', EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, false, false, 'PayPal IPN Invalid Process', $line, $line);

		}		
	
}
?>