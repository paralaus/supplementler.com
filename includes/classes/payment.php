<?php
/*
  $Id: payment.php,v 1.37 2003/06/09 22:26:32 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  class payment {
    var $modules, $selected_module;

// class constructor
    function payment($module = '') {
      global $payment, $language, $PHP_SELF, $shipping;

      if (defined('MODULE_PAYMENT_INSTALLED') && tep_not_null(MODULE_PAYMENT_INSTALLED)) {
		  if($_SESSION['paypal_express_checkout']==true){
			$this->modules = explode(';', $_SESSION['paypal_express_payment_modules'] );
		}else{
			$this->modules = explode(';', MODULE_PAYMENT_INSTALLED);
			$this->modules = str_replace('paypalexpress.php', '', $this->modules);
		}
		
	      require(DIR_WS_CLASSES . 'ship2pay.php');
	      $my_ship2pay = new ship2pay;
	      $arrship=explode('_',$shipping['id']);
	      $ship2pay_mods = $my_ship2pay->get_pay_modules($arrship[0]);
	      if (tep_not_null($ship2pay_mods)){
	        $this->modules = explode(';', $ship2pay_mods);
	      }else{
	        $this->modules = explode(';', MODULE_PAYMENT_INSTALLED);
	      }

        $include_modules = array();

        if ( (tep_not_null($module)) && (in_array($module . '.' . substr($PHP_SELF, (strrpos($PHP_SELF, '.')+1)), $this->modules)) ) {
            
          $this->selected_module = $module;

          $include_modules[] = array('class' => $module, 'file' => $module . '.php');
        } else {
          reset($this->modules);
          while (list(, $value) = each($this->modules)) {
            $class = substr($value, 0, strrpos($value, '.'));
            $include_modules[] = array('class' => $class, 'file' => $value);
          }
        }

        for ($i=0, $n=sizeof($include_modules); $i<$n; $i++) {
          include(DIR_WS_LANGUAGES . $language . '/modules/payment/' . $include_modules[$i]['file']);
          include(DIR_WS_MODULES . 'payment/' . $include_modules[$i]['file']);

          $GLOBALS[$include_modules[$i]['class']] = new $include_modules[$i]['class'];
        }

// if there is only one payment method, select it as default because in
// checkout_confirmation.php the $payment variable is being assigned the
// $HTTP_POST_VARS['payment'] value which will be empty (no radio button selection possible)
        if ( (tep_count_payment_modules() == 1) && (!isset($GLOBALS[$payment]) || (isset($GLOBALS[$payment]) && !is_object($GLOBALS[$payment]))) ) {
          $payment = $include_modules[0]['class'];
        }

        if ( (tep_not_null($module)) && (in_array($module, $this->modules)) && (isset($GLOBALS[$module]->form_action_url)) ) {
          $this->form_action_url = $GLOBALS[$module]->form_action_url;
        }
      }
    }

// class methods
/* The following method is needed in the checkout_confirmation.php page
   due to a chicken and egg problem with the payment class and order class.
   The payment modules needs the order destination data for the dynamic status
   feature, and the order class needs the payment module title.
   The following method is a work-around to implementing the method in all
   payment modules available which would break the modules in the contributions
   section. This should be looked into again post 2.2.
*/   
    function update_status() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module])) {
          if (function_exists('method_exists')) {
            if (method_exists($GLOBALS[$this->selected_module], 'update_status')) {
              $GLOBALS[$this->selected_module]->update_status();
            }
          } else { // PHP3 compatibility
            @call_user_method('update_status', $GLOBALS[$this->selected_module]);
          }
        }
      }
    }

   // Start - CREDIT CLASS Gift Voucher Contribution
// function javascript_validation() {
  function javascript_validation($coversAll) {
	//added the $coversAll to be able to pass whether or not the voucher will cover the whole
	//price or not.  If it does, then let checkout proceed when just it is passed.
      $js = '';
      if (is_array($this->modules)) {
        if ($coversAll) {
          $addThis='if (document.checkout_payment.cot_gv.checked) {
            payment_value=\'cot_gv\';
          } else ';
        } else {
          $addThis='';
        }
// End - CREDIT CLASS Gift Voucher Contribution
        $js = '<script language="javascript"><!-- ' . "\n" .
              'function check_form() {' . "\n" .
              '  var error = 0;' . "\n" .
              '  var error_message = "' . JS_ERROR . '";' . "\n" .
// Start - CREDIT CLASS Gift Voucher Contribution
              '  var payment_value = null;' . "\n" .$addThis . 
// End - CREDIT CLASS Gift Voucher Contribution
              '  if (document.checkout_payment.payment.length) {' . "\n" .
              '    for (var i=0; i<document.checkout_payment.payment.length; i++) {' . "\n" .
              '      if (document.checkout_payment.payment[i].checked) {' . "\n" .
              '        payment_value = document.checkout_payment.payment[i].value;' . "\n" .
              '      }' . "\n" .
              '    }' . "\n" .
              '  } else if (document.checkout_payment.payment.checked) {' . "\n" .
              '    payment_value = document.checkout_payment.payment.value;' . "\n" .
              '  } else if (document.checkout_payment.payment.value) {' . "\n" .
              '    payment_value = document.checkout_payment.payment.value;' . "\n" .
              '  }' . "\n\n";

        reset($this->modules);
        while (list(, $value) = each($this->modules)) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            $js .= $GLOBALS[$class]->javascript_validation();
          }
        }

// Start - CREDIT CLASS Gift Voucher Contribution
//        $js .= "\n" . '  if (payment_value == null) {' . "\n" .
        $js .= "\n" . '  if (payment_value == null && submitter != 1) {' . "\n" . // CCGV Contribution
               '    error_message = error_message + "' . JS_ERROR_NO_PAYMENT_MODULE_SELECTED . '";' . "\n" .
               '    error = 1;' . "\n" .
               '  }' . "\n\n" .
//               '  if (error == 1) {' . "\n" .
               '  if (error == 1 && submitter != 1) {' . "\n" .
// End - CREDIT CLASS Gift Voucher Contribution
               '    alert(error_message);' . "\n" .
               '    return false;' . "\n" .
               '  } else {' . "\n" .
               ' if((document.getElementById(\'webpos_cc_number\').value!=\'\')&&(document.getElementById(\'payment111\').checked)){ return confirm("Kredi karti bilgilerinizi girdikten sonra ödeme seklinizi  Havale/EFT ile ödeme olarak degistirmek  istediginizden emin misiniz ?\n\nNot:  Havale/eft secenegini sectiginizde havalenizi/eft nizi gerçeklestirdiginizde paketiniz kargoya verilecektir."); }  ' . "\n" .
               ' if((document.getElementById(\'payment1\').value==\'\')&&(document.getElementById(\'payment111\').checked)){ alert("Herhangi bir banka seçmemissiniz, lütfen dropdown menüden Havale/EFT yapmak istediginiz bankayi seçiniz."); return false; }  ' . "\n" .
               ' return true;' . "\n" .
               '  }' . "\n" .
               '}' . "\n" .
               '//--></script>' . "\n";
      }

      return $js;
    }
	
    function selection() {
      $selection_array = array();
      if (is_array($this->modules)) {
        reset($this->modules);
        while (list(, $value) = each($this->modules)) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            $selection = $GLOBALS[$class]->selection();
            if (is_array($selection)) $selection_array[] = $selection;
          }
        }
      }

      return $selection_array;
    }

    // Start - CREDIT CLASS Gift Voucher Contribution
// check credit covers was setup to test whether credit covers is set in other parts of the code
  function check_credit_covers() {
  	global $credit_covers;

  	return $credit_covers;
  }
// End - CREDIT CLASS Gift Voucher Contribution

    function pre_confirmation_check() {
// Start - CREDIT CLASS Gift Voucher Contribution
      global $credit_covers, $payment_modules; 
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          if ($credit_covers) {
            $GLOBALS[$this->selected_module]->enabled = false;
            $GLOBALS[$this->selected_module] = NULL;
            $payment_modules = '';
          } else {
            $GLOBALS[$this->selected_module]->pre_confirmation_check();
          }
// End - CREDIT CLASS Gift Voucher Contribution
        }
      }
    }

    function confirmation() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          return $GLOBALS[$this->selected_module]->confirmation();
        }
      }
    }

    function process_button() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          return $GLOBALS[$this->selected_module]->process_button();
        }
      }
    }

    function before_process() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          return $GLOBALS[$this->selected_module]->before_process();
        }
      }
    }

    function after_process() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          return $GLOBALS[$this->selected_module]->after_process();
        }
      }
    }

    function get_error() {
      if (is_array($this->modules)) {
        if (is_object($GLOBALS[$this->selected_module]) && ($GLOBALS[$this->selected_module]->enabled) ) {
          return $GLOBALS[$this->selected_module]->get_error();
        }
      }
    }
  }
?>
