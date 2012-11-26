<?php


class paypalexpress {
	var $code, $title, $description, $enabled;

	function paypalexpress() {
		global $order;

		$this->code = 'paypalexpress';
		$this->title = MODULE_PAYMENT_PAYPALEXPRESS_TEXT_TITLE;
		$this->description = MODULE_PAYMENT_PAYPALEXPRESS_TEXT_DESCRIPTION;
		$this->sort_order = MODULE_PAYMENT_PAYPALEXPRESS_SORT_ORDER;
		$this->enabled = ((MODULE_PAYMENT_PAYPALEXPRESS_STATUS == 'True') ? true : false);
		$this->info = MODULE_PAYMENT_PAYPALEXPRESS_TEXT_INFO;
		$this->order_status_success = PAYPAL_ORDER_STATUS_SUCCESS_ID;
		$this->order_status_rejected = PAYPAL_ORDER_STATUS_REJECTED_ID;
		$this->order_status_pending = PAYPAL_ORDER_STATUS_PENDING_ID;
		$this->order_status_tmp = PAYPAL_ORDER_STATUS_TMP_ID;
		$this->tmpOrders = true;
		$this->debug = true;
		$this->tmpStatus = PAYPAL_ORDER_STATUS_TMP_ID;;


		if (is_object($order))
			$this->update_status();
	}

	function update_status() {
		global $order;

		if (($this->enabled == true) && ((int) MODULE_PAYMENT_PAYPALEXPRESS_ZONE > 0)) {
			$check_flag = false;
			$check_query = tep_db_query("select zone_id from ".TABLE_ZONES_TO_GEO_ZONES." where geo_zone_id = '".MODULE_PAYMENT_PAYPALEXPRESS_ZONE."' and zone_country_id = '".$order->billing['country']['id']."' order by zone_id");
			while ($check = tep_db_fetch_array($check_query)) {
				if ($check['zone_id'] < 1) {
					$check_flag = true;
					break;
				}
				elseif ($check['zone_id'] == $order->billing['zone_id']) {
					$check_flag = true;
					break;
				}
			}

			if ($check_flag == false) {
				$this->enabled = false;
			}
		}
	}

	function javascript_validation() {
		return false;
	}

	function selection() {
		return array ('id' => $this->code, 'module' => $this->title, 'description' => $this->info);
	}

	function pre_confirmation_check() {
		return false;
	}

	function confirmation() {
		return false;
	}

	function process_button() {
		return false;
	}

	function before_process() {
		return false;
	}

	function payment_action(){
		global $order, $o_paypal, $tmp, $insert_id;
		$o_paypal->complete_express_ceckout($_SESSION['tmp_oID']);
		
		$tmp = false;
	}

	function after_process() {
		global $insert_id, $o_paypal;
		$o_paypal->write_status_history($insert_id);
		$o_paypal->logging_status($insert_id);
	}

	function admin_order($oID) {
		return false; 
	}

	function output_error() {
		return false;
	}

	function check() {
		if (!isset ($this->_check)) {
			$check_query = tep_db_query("select configuration_value from ".TABLE_CONFIGURATION." where configuration_key = 'MODULE_PAYMENT_PAYPALEXPRESS_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
	}


	function install() {
		tep_db_query("insert into ".TABLE_CONFIGURATION." ( configuration_title, configuration_key, configuration_value, configuration_description,  configuration_group_id, sort_order, set_function, date_added) values ('Enable PayPal Module','MODULE_PAYMENT_PAYPALEXPRESS_STATUS', 'True', 'Do you want to accept PayPal payments?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		 tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('E-Mail Address', 'MODULE_PAYMENT_PAYPALEXPRESS_ID', 'you@yourbusiness.com', 'The e-mail address to use for the PayPal service', '6', '4', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Currency', 'MODULE_PAYMENT_PAYPALEXPRESS_CURRENCY', 'Selected Currency', 'The currency to use for credit card transactions', '6', '6', 'tep_cfg_select_option(array(\'Selected Currency\',\'Only USD\',\'Only CAD\',\'Only EUR\',\'Only GBP\',\'Only JPY\'), ', now())");
		tep_db_query("insert into ".TABLE_CONFIGURATION." ( configuration_title, configuration_key, configuration_value, configuration_description,  configuration_group_id, sort_order, date_added) values ('Sort order of display.','MODULE_PAYMENT_PAYPALEXPRESS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");	
		tep_db_query("insert into ".TABLE_CONFIGURATION." (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone','MODULE_PAYMENT_PAYPALEXPRESS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYPALEXPRESS_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");		
	}

	function remove() {
		tep_db_query("delete from ".TABLE_CONFIGURATION." where configuration_key in ('".implode("', '", $this->keys())."')");
	}

	function keys() {
		return array ('MODULE_PAYMENT_PAYPALEXPRESS_STATUS',
							'MODULE_PAYMENT_PAYPALEXPRESS_ID', 
							'MODULE_PAYMENT_PAYPALEXPRESS_CURRENCY',
					  		'MODULE_PAYMENT_PAYPALEXPRESS_ZONE',
							'MODULE_PAYMENT_PAYPALEXPRESS_ORDER_STATUS_ID',						  
					  		'MODULE_PAYMENT_PAYPALEXPRESS_SORT_ORDER');
	}
}
?>