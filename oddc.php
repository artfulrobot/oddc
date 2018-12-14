<?php

require_once 'oddc.civix.php';
use CRM_Oddc_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function oddc_civicrm_config(&$config) {
  _oddc_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function oddc_civicrm_xmlMenu(&$files) {
  _oddc_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function oddc_civicrm_install() {
  _oddc_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function oddc_civicrm_postInstall() {
  _oddc_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function oddc_civicrm_uninstall() {
  _oddc_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function oddc_civicrm_enable() {
  _oddc_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function oddc_civicrm_disable() {
  _oddc_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function oddc_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _oddc_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function oddc_civicrm_managed(&$entities) {
  _oddc_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function oddc_civicrm_caseTypes(&$caseTypes) {
  _oddc_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function oddc_civicrm_angularModules(&$angularModules) {
  _oddc_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function oddc_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _oddc_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function oddc_civicrm_entityTypes(&$entityTypes) {
  _oddc_civix_civicrm_entityTypes($entityTypes);
}


/**
 *
 * We pass everything in $input on in the callbacks since we can't guarantee session storage.
 *
 * https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/#technical-variables
 *
 */
function oddc__get_paypal_url($input) {

  // Validate input ---------------------------------------------------------------------------------------------------

  // Leave $input as is, but clean it up into $params.
  $params = [];
  $is_recur = !empty($input['is_recur']);

  // Validate currency.
  if (!preg_match('/^(GBP|USD|EUR)$/', $input['currency'] ?? '')) {
    throw new \Exception("Invalid currency.");
  }
  $params['currency'] = $input['currency'];

  // Validate amount.
  if (!preg_match('/^\d{1,4}(?:\.\d\d)$/', $input['amount'] ?? '')) {
    throw new \Exception("Invalid amount");
  }
  $params['amount'] = $input['amount'];

  // Validate email.
  if (!preg_match('/[^@ <>"]+@[a-z0-9A-Z_-]+\.[a-z0-9A-Z_.-]+$/', $input['email'])) {
    throw new \Exception("Invalid Email");
  }
  $params['email'] = $input['email'];

  // Check we have names.
  foreach (['first_name', 'last_name'] as $_) {
    $v = trim($input[$_] ?? '');
    if (!$v) {
      throw new \Exception("Missing $_");
    }
    $params[$_] = $input[$_];
  }

  // @todo validate return urls.
  foreach (['success_url', 'cancel_url'] as $_) {
    $params[$_] = $input[$_];
  }

  // @todo set financial_type_id.
  $params['financial_type_id'] = 'Donation';

  $params['payment_processor_id'] = 8; //@todo

  // Find or create contact -------------------------------------------------------------------------------------------
  //
  // Find or create contact @todo use Björn's thing.
  $contact = civicrm_api3('Contact', 'get', ['sequential' => 1, 'email' => $params['email'], 'contact_type' => 'Individual']);
  if ($contact['count'] == 0) {
    $contact = civicrm_api3('Contact', 'create', [
      'first_name' => $params['first_name'],
      'last_name' => $params['last_name'],
    ]);
    civicrm_api3('Email', 'create', [
      'contact_id' => $contact['id'],
      'email' => $params['email'],
      'is_primary' => 1,
    ]);
  }

  // Create placeholder pending contribution records ------------------------------------------------------------------
  $invoice_id = md5(uniqid(rand())); // Used for both the contribution and the contributionRecur records.

  if ($is_recur) {
    // Recurring contribution. Create recur record.
    $contrib_recur = civicrm_api3('ContributionRecur', 'create', array(
      'amount'                 => $params['amount'],
      'contact_id'             => $contact['id'],
      'contribution_status_id' => "Pending",
      'currency'               => 'GBP', // We always collect in GBP.
      'financial_type_id'      => $params['financial_type_id'],
      'frequency_interval'     => 1,
      'frequency_unit'         => "day", //"month",
      'is_test'                => 1, // xxx look this up from pay processor.
      // 'payment_instrument_id'  => 'direct_debit_gc', xxx
      'payment_processor_id'   => $params['payment_processor_id'],
      'start_date'             => date('Y-m-d'),
      'invoice_id'             => $invoice_id,
      //'trxn_id'                => // ???
    ));
  }
  // Create incomplete contribution.
  $contrib_params = [
    'contact_id'             => $contact['id'],
    'financial_type_id'      => 'Donation', // @todo
    'total_amount'           => $params['amount'],
    'contribution_status_id' => 'Pending',
    'invoice_id'             => $invoice_id,
    'note'                   => ($params['currency'] !== 'GBP')
                                ? "Payment made as $params[currency]$params[amount], taken in GBP [total_is_in_wrong_currency]"
                                : NULL,
  ];
  if (isset($contrib_recur)) {
    $contrib_params['contribution_recur_id'] = $contrib_recur['id'];
  }
  $contribution = civicrm_api3('Contribution', 'create', $contrib_params);

  $payment_processor = CRM_Financial_BAO_PaymentProcessor::getPayment(8);

  // We can pass various parameters through as 'custom'. (max 256 chars) I think paypal sends these back at the end.
  $custom = ['module' => 'contribute', 'contactID' => $contact['id'], 'contributionID' => $contribution['id']];
  if (isset($contrib_recur)) {
    $custom['contributionRecurID'] = $contrib_recur['id'];
  }
  $config = CRM_Core_Config::singleton();

  // Will this work?
  $ipn_url = CRM_Utils_System::url('civicrm/payment/ipn/' . $payment_processor['id'], [], TRUE, NULL, FALSE);

  $paypalParams = array(
    'business'           => $payment_processor['user_name'],
    'notify_url'         => $ipn_url,
    'item_name'          => 'Donation', // xxx
    'quantity'           => 1,
    'undefined_quantity' => 0, // ???!
    'no_note'            => 1,
    'no_shipping'        => 1, // No shipping address.
    'rm'                 => 2, // POST the data back.
    'currency_code'      => $params['currency'],
    'invoice'            => $invoice_id,
    'lc'                 => substr($config->lcMessages, -2), // Locale
    'charset'            => 'UTF-8',
    'custom'             => json_encode($custom),
    'bn'                 => 'CiviCRM_SP',
  );

  //$query = [ 'success' => 1 ];
  //$paypalParams['return'] = CRM_Utils_System::url('civicrm/oddc/paypal-callback', $query, TRUE);
  $paypalParams['return'] = $params['success_url'];
  $paypalParams['cancel_return'] = $params['cancel_url'];
  //$paypalParams['cancel_return'] = CRM_Utils_System::url('civicrm/oddc/paypal-callback', $query, TRUE);
  //$paypalParams['cancel_return'] = CRM_Utils_System::url('civicrm/oddc/paypal-callback', $query, TRUE);

  // if recurring donations, add a few more items
  if ($is_recur) {
    $paypalParams += array(
      'cmd' => '_xclick-subscriptions',
      'a3' => $params['amount'],
      'p3' => 1, //$params['frequency_interval'], // e.g. 1 with t3='M' means every (1) month
      't3' => 'D', // ucfirst(substr($params['frequency_unit'], 0, 1)), /xxx set to M for month!
      'sra' => 1, // retry failed payments (up to two more tries).
      'src' => 1, // subscription recurs.
      // I think not sending 'srt' might mean indefinite recurring payments.
      // 'srt' => CRM_Utils_Array::value('installments', $params),
      'no_note' => 1,
      'modify' => 0,
    );
  }
  else {
    $paypalParams += array(
      'cmd' => '_xclick',
      'amount' => $params['amount'], // xxx
    );
  }

  $uri = '';
  foreach ($paypalParams as $key => $value) {
    if ($value === NULL) {
      continue;
    }

    $value = urlencode($value);
    if ($key == 'return' ||
      $key == 'cancel_return' ||
      $key == 'notify_url'
    ) {
      $value = str_replace('%2F', '/', $value); // ??? weird.
    }
    $uri .= "&{$key}={$value}";
  }

  $uri = substr($uri, 1);
  $url = $payment_processor['url_site'];
  $sub = $is_recur ? 'cgi-bin/webscr' : 'subscriptions';
  $paypalURL = "{$url}{$sub}?$uri";

  return $paypalURL;
}

