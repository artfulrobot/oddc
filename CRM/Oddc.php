<?php

class CRM_Oddc {
  /** @var array Validated input */
  public $input;

  /** @var array Hard coded map of payment processor ids until a better method in place.
   * @xxx needs more setup.
   */
  public $payment_processor_map = [
    'GoCardless' => [ 'company' => 'GoCardless20181009', 'charity' => 'GoCardless20181009' ],
    'PayPal' =>     [ 'company' => 'paypal',             'charity' => 'paypal openTrust (GiftAid)' ],
  ];

  /** @var mixed Payment Processor */
  public $payment_processor;

  /** @var int Contact ID */
  public $contact_id;

  /**
   * Factory method.
   *
   * @param array $input
   */
  public static function factory() {
    $obj = new static();
    return $obj;
  }

  /**
   * Process
   */
  public function process($input) {
    try {
      $this->validate($input);
      return $this->routeToPaymentProcessor();
    }
    catch (CRM_Oddc_ValidationError $e) {
      return ['error' => $e->getMessage()];
    }
    catch (Exception $e) {
      return ['error' => 'Server problem: ' . $e->getMessage()];
    }
  }
  /**
   * Validate user input.
   */
  public function validate($input) {

    // Leave $input as is, but clean it up into $params.
    $params = ['financial_type_id' => 'Donation']; // xxx
    $params['is_recur'] = (!empty($input['is_recur'])) ? 1: 0;

    // Validate geo country name.
    if (!preg_match('/^[A-Z]{2,3}$/', $input['geo'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid country.");
    }
    $params['geo'] = $input['geo'];

    // Validate currency.
    if (!preg_match('/^(GBP|USD|EUR)$/', $input['currency'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid currency.");
    }
    $params['currency'] = $input['currency'];

    // Validate amount.
    if (!preg_match('/^\d{1,4}(?:\.\d\d)?$/', $input['amount'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid amount");
    }
    if ($input['amount'] < 1) {
      throw new CRM_Oddc_ValidationError("Minimum amount is 1.00");
    }
    $params['amount'] = $input['amount'];

    // Validate email.
    if (!preg_match('/[^@ <>"]+@[a-z0-9A-Z_-]+\.[a-z0-9A-Z_.-]+$/', $input['email'])) {
      throw new CRM_Oddc_ValidationError("Invalid Email");
    }
    $params['email'] = $input['email'];

    // Check we have names.
    foreach (['first_name', 'last_name'] as $_) {
      $v = trim($input[$_] ?? '');
      if (!$v) {
        throw new CRM_Oddc_ValidationError("Missing $_");
      }
      $params[$_] = $input[$_];
    }

    // Booleans
    foreach (['test_mode', 'giftaid', 'consent'] as $_) {
      $params[$_] = empty($input[$_]) ? 0 : 1;
    }

    // Things we trust.
    foreach (['return_url', 'campaign', 'project', 'legal_entity'] as $_) {
      $params[$_] = $input[$_];
    }

    // Source has come on query string, just check it does not have anything too weird in it.
    $params['source'] = preg_replace('/[^a-zA-Z0-9_,.!%£$()?@#-]+/', '-', $input['source']);

    // Copy other user data as is.
    foreach (['street_address', 'city', 'postal_code', 'country'] as $_) {
      $params[$_] = $input[$_] ?? '';
    }

    $this->input = $params;
  }
  /**
   * Business logic for choosing the payment processor to use.
   *
   * It's basically PayPal, unless:
   * - geo: UK
   * - regular
   *
   */
  public function routeToPaymentProcessor() {

    if ($this->input['geo'] === 'GB' && $this->input['is_recur']) {
      // GoCardless.
      $method = 'GoCardless';
    }
    else {
      // PayPal.
      $method = 'PayPal';
    }

    $params = [
      'name'      => $this->payment_processor_map[$method][$this->input['legal_entity']],
      'is_test'   => $this->input['test_mode'],
      'is_active' => 1,
    ];
    $processor = civicrm_api3('Payment Processor', 'getsingle', $params);
    $this->payment_processor = Civi\Payment\System::singleton()->getByProcessor($processor);

    $this->getOrCreateContact();

    $method = "process$method";
    return $this->$method();
  }
  /**
   * Get a redirect URL.
   *
   * @return string URL
   */
  protected function processPayPal() {

    // @todo set financial_type_id.
    $params['financial_type_id'] = 'Donation';

    // Used a couple of times below.
    $payment_processor_config = $this->payment_processor->getPaymentProcessor();

    // Create placeholder pending contribution records ------------------------------------------------------------------
    $invoice_id = md5(uniqid(rand())); // Used for both the contribution and the contributionRecur records.

    if ($this->input['is_recur']) {
      // Recurring contribution. Create recur record.
      $contrib_recur = civicrm_api3('ContributionRecur', 'create', array(
        'contact_id'             => $this->contact_id,
        'contribution_status_id' => "Pending",
        'currency'               => 'GBP', // We always collect in GBP as far as CiviCRM is concerned.
        'amount'                 => $this->input['amount'], // Nb. this is not correct if not actually paid in GBP but is required to match the numeric amount for PayPal webhooks.
        'financial_type_id'      => $this->input['financial_type_id'],
        'frequency_interval'     => 1,
        'frequency_unit'         => "day", //"month", // xxx
        'campaign_id'            => $this->input['campaign'],
        'is_test'                => $this->input['test_mode'],
        'payment_instrument_id'  => $this->payment_processor->getPaymentInstrumentID(),
        'payment_processor_id'   => $payment_processor_config['id'],
        'start_date'             => date('Y-m-d'),
        'invoice_id'             => $invoice_id,
        //'trxn_id'                => // ???
      ));
    }
    // Create incomplete contribution.
    $contrib_params = [
      'contact_id'             => $this->contact_id,
      'financial_type_id'      => $this->input['financial_type_id'],
      'total_amount'           => $this->input['amount'],
      'source'                 => $this->input['source'],
      'campaign_id'            => $this->input['campaign'],
      'contribution_status_id' => 'Pending',
      'invoice_id'             => $invoice_id,
      'note'                   => ($this->input['currency'] !== 'GBP')
                                  ? "Payment made as {$this->input['currency']}{$this->input['amount']}, taken in GBP [total_is_in_wrong_currency]"
                                  : NULL,
    ];
    if (isset($contrib_recur)) {
      $contrib_params['contribution_recur_id'] = $contrib_recur['id'];
    }
    $this->setGiftAidParam($contrib_params);

    $contribution = civicrm_api3('Contribution', 'create', $contrib_params);

    // We can pass various parameters through as 'custom'. (max 256 chars) I think paypal sends these back at the end.
    $custom = ['module' => 'contribute', 'contactID' => $this->contact_id, 'contributionID' => $contribution['id']];
    if (isset($contrib_recur)) {
      $custom['contributionRecurID'] = $contrib_recur['id'];
    }

    $ipn_url = CRM_Utils_System::url('civicrm/payment/ipn/' . $payment_processor_config['id'], [], TRUE, NULL, FALSE);
    $config = CRM_Core_Config::singleton();
    $paypalParams = [
      'business'           => $payment_processor_config['user_name'],
      'notify_url'         => $ipn_url,
      'item_name'          => 'Donation', // xxx
      'quantity'           => 1,
      'undefined_quantity' => 0, // Don't know what this is.
      'no_note'            => 1,
      'no_shipping'        => 1, // No shipping address.
      'rm'                 => 2, // POST the data back.
      'currency_code'      => $this->input['currency'],
      'invoice'            => $invoice_id,
      'lc'                 => substr($config->lcMessages, -2), // Locale
      'charset'            => 'UTF-8',
      'custom'             => json_encode($custom),
      'bn'                 => 'CiviCRM_SP',
      'return'             => $this->getUrlWithParams(['result' => 1]),
      'cancel_return'      => $this->input['return_url'],
    ];

    // if recurring donations, add a few more items
    if ($this->input['is_recur']) {
      $paypalParams += array(
        'cmd' => '_xclick-subscriptions',
        'a3'  => $this->input['amount'],
        'p3'  => 1, //$params['frequency_interval'], // e.g. 1 with t3='M' means every (1) month
        't3'  => 'D', // ucfirst(substr($params['frequency_unit'], 0, 1)), /xxx set to M for month!
        'sra' => 1, // retry failed payments (up to two more tries).
        'src' => 1, // subscription recurs.
        // I think not sending 'srt' might mean indefinite recurring payments.
        // 'srt' => CRM_Utils_Array::value('installments', $params),
        'no_note' => 1,
        'modify'  => 0,
      );
    }
    else {
      $paypalParams += [
        'cmd' => '_xclick',
        'amount' => $this->input['amount'],
      ];
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
    $url = $payment_processor_config['url_site'];
    $sub = $this->input['is_recur'] ? 'cgi-bin/webscr' : 'subscriptions';
    $paypalURL = "{$url}{$sub}?$uri";

    return ['url' => $paypalURL];
  }
  /**
   * Get a redirect URL.
   *
   * @return string URL
   */
  protected function processGoCardless() {

    $token = md5(uniqid(rand()));

    // We have to do the work of
    // $this->payment_processor->getRedirectParametersFromParams()
    // Turned out far less code to copy just the relevant bits here than try to reuse that.
    $payment_processor_config = $this->payment_processor->getPaymentProcessor();
    $params = [
      'session_token'        => $token,
      'description'          => 'Donation',
      'success_redirect_url' => $this->getUrlWithParams(['gcrf' => 1]),
      'prefilled_customer' => [
        'given_name'    => $this->input['first_name'],
        'family_name'   => $this->input['last_name'],
        'email'         => $this->input['email'],
        'address_line1' => $this->input['street_address'],
        'city'          => $this->input['city'],
        'postal_code'   => $this->input['postal_code'],
        'country_code'  => $this->input['country'],
      ],
    ];

    // Crete Redirect Flow.
    $redirect_flow = $this->payment_processor->getRedirectFlow($params);

    // Initialise session storage.
    if (!isset($_SESSION['odd_gcrf'])) {
      $_SESSION['odd_gcrf'] = [];
    }

    // Store all the [validated and parsed] input on normal Drupal session.
    $_SESSION['odd_gcrf'][$redirect_flow->id] = $this->input;
    $_SESSION['odd_gcrf'][$redirect_flow->id]['payment_processor_id'] = $this->payment_processor->getPaymentProcessor()['id'];
    $_SESSION['odd_gcrf'][$redirect_flow->id]['description'] = $params['description'];
    $_SESSION['odd_gcrf'][$redirect_flow->id]['session_token'] = $token;
    $_SESSION['odd_gcrf'][$redirect_flow->id]['contact_id'] = $this->contact_id;

    Civi::log()->info("Oddc::processGoCardless. Storing on SESSION:\n{session}",
      ['session' => json_encode($_SESSION['odd_gcrf'], JSON_PRETTY_PRINT)]);

    return ['url' => $redirect_flow->redirect_url];
  }
  /**
   * Create the subscription.
   *
   * @param array $input is query string data from GC.
   */
  public function completeRedirectFlow($input) {

    Civi::log()->info("Oddc::completeRedirectFlow. \nGET: {input}\n\nSESSION: {session}",
      ['input' => json_encode($input), 'session' => json_encode($_SESSION['odd_gcrf'], JSON_PRETTY_PRINT)]);

    // We are passed a redirect_flow_id; this must exist on our session.
    $redirect_flow_id = $input['redirect_flow_id'] ?? 'missing';
    if (empty($_SESSION['odd_gcrf'][$redirect_flow_id])) {
      throw new \Exception("Direct Debit could not be set up. This process requires cookies.");
    }
    $pre_data = $_SESSION['odd_gcrf'][$redirect_flow_id];

    // Unpack some of the stashed-in-session data for our convenience.
    $this->input = $input;
    $this->payment_processor = Civi\Payment\System::singleton()->getById($pre_data['payment_processor_id']);
    $this->contact_id = $pre_data['contact_id'];

    // Complete the redirect flow with GC.
    $params = [
      'redirect_flow_id' => $redirect_flow_id,
      'interval_unit'    => 'monthly', // xxx
    ] + $pre_data;
    $result = CRM_GoCardlessUtils::completeRedirectFlowWithGoCardless($params);
    $gc_api        = $result['gc_api'];
    $redirect_flow = $result['redirect_flow'];
    $subscription  = $result['subscription'];

    // Create a ContributionRecur record.
    $financial_type_id = 'Donation'; // xxx

    $contrib_recur = civicrm_api3('ContributionRecur', 'create', array(
      'amount'                 => $params['amount'],
      'contact_id'             => $this->contact_id,
      'contribution_status_id' => "Pending", // This is useful and should get changed to
                                             // In Progress after successful payment.
      'currency'               => 'GBP', // fixed.
      'financial_type_id'      => $financial_type_id,
      'frequency_interval'     => 1,
      'frequency_unit'         => "month", // xxx
      'campaign_id'            => $params['campaign'],
      'is_test'                => $this->payment_processor->isTestMode(),
      'payment_instrument_id'  => 'direct_debit_gc',
      'payment_processor_id'   => $params['payment_processor_id'],
      'start_date'             => $subscription->start_date,
      'trxn_id'                => $subscription->id,
    ));

    // Create a pending Contribution record.
    $contrib_recur = civicrm_api3('Contribution', 'create', array(
      'amount'                 => $params['amount'],
      'contact_id'             => $this->contact_id,
      'contribution_recur_id'  => $contrib_recur['id'],
      'contribution_status_id' => "Pending", // This is useful and should get changed to In Progress
      'currency'               => 'GBP', // fixed.
      'financial_type_id'      => $financial_type_id,
      'campaign_id'            => $params['campaign'],
      'source'                 => $params['source'],
      'is_test'                => $this->payment_processor->isTestMode(),
      'payment_instrument_id'  => 'direct_debit_gc',
      'payment_processor_id'   => $params['payment_processor_id'],
      'receive_date'           => $subscription->start_date,
      'total_amount'           => $params['amount'],
    ));
  }
  /**
   * Find or create CiviCRM contact.
   */
  public function getOrCreateContact() {

    if (civicrm_api3('Extension', 'getcount', ['key' => 'de.systopia.xcm', 'is_active' => 1]) == 1) {
      // Assume we have XCM
      $params = array_intersect_key($this->input, array_flip(['email', 'first_name', 'last_name', 'street_address', 'city', 'postal_code', 'country']));
      $contact = civicrm_api3('Contact', 'getorcreate', $params);
    }
    else {
      // Fallback.
      $contact = civicrm_api3('Contact', 'get', ['sequential' => 1, 'email' => $this->input['email'], 'contact_type' => 'Individual']);
      if ($contact['count'] != 1) {
        $contact = civicrm_api3('Contact', 'create', [
          'first_name' => $this->input['first_name'],
          'last_name' => $this->input['last_name'],
        ]);
        civicrm_api3('Email', 'create', [
          'contact_id' => $contact['id'],
          'email' => $this->input['email'],
          'is_primary' => 1,
        ]);
      }
    }
    $this->contact_id = $contact['id'];

    // Store Gift Aid
    if (!empty($this->input['giftaid'])
      && (civicrm_api3('Extension', 'getcount', ['key' => 'uk.co.compucorp.civicrm.giftaid', 'is_active' => 1]) == 1)) {
      // Got a Gift Aid declaration, and compucorp's gift aid extension is in use.
      //
      $addressDetails = _civigiftaid_civicrm_custom_get_address_and_postal_code($this->contact_id);

      require_once 'CRM/Civigiftaid/Utils/GiftAid.php';
      $params = array(
        'entity_id'             => $this->contact_id,
        'eligible_for_gift_aid' => 1,
        'start_date'            => date('Y-m-d'),
        'address'               => $addressDetails[0],
        'post_code'             => $addressDetails[1],
      );
      CRM_Civigiftaid_Utils_GiftAid::setDeclaration($params);
    }

    // Store consent.
    if (!empty($this->input['consent'])) {
      // Create an activity.
      $consent_activity = civicrm_api3('OptionValue', 'get', ['sequential' => 1, 'option_group_id' => "activity_type", 'name' => "marketing_consent"]);
      if (!empty($consent_activity['values'][0]['value'])) {
        civicrm_api3('Activity', 'create', [
          'source_contact_id'  => $this->contact_id,
          'activity_type_id'   => $consent_activity['values'][0]['value'],
          'target_id'          => $this->contact_id,
          'subject'            => 'Gave consent at time of making donation.',
          'details'            => '<p>Donation page at ' . htmlspecialchars($this->input['return_url']) . '</p>',
          'status_id'          => 'Completed',
          'activity_date_time' => date('Y-m-d H:i:s'),
        ]);
      }
    }
  }
  /**
   * Create a URL from the return_url appending the params.
   *
   * @param array $params
   * @return string
   */
  protected function getUrlWithParams($params) {
    $url = $this->input['return_url'];
    $join = strstr($url, '?') ? '&' : '?';
    return $url . $join . http_build_query($params);
  }
  /**
   * Lookup the custom field ID for Compucorp's Giftaid extension and set it as
   * a parameter for the Contribution.create call.
   *
   * @param &Array params for the Contribution.create API call.
   */
  protected function setGiftAidParam(&$params) {
    // Look up the custom_N name for the field.
    require_once 'CRM/Core/BAO/CustomField.php';
    $id = CRM_Core_BAO_CustomField::getCustomFieldID('Eligible_for_Gift_Aid', 'Gift_Aid');
    $params["custom_$id"] = $this->input['giftaid'];
  }
}
