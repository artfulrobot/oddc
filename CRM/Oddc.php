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

    // test mode bool
    $params['test_mode'] = (!empty($input['test_mode'])) ? 1 : 0;

    // Things we trust.
    foreach (['return_url', 'campaign', 'source', 'project', 'legal_entity'] as $_) {
      $params[$_] = $input[$_];
    }

    // Copy other user data as is.
    foreach (['address', 'city', 'postal_code', 'country'] as $_) {
      $params[$_] = $input[$_];
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
      'contribution_status_id' => 'Pending',
      'invoice_id'             => $invoice_id,
      'note'                   => ($this->input['currency'] !== 'GBP')
                                  ? "Payment made as {$this->input['currency']}{$this->input['amount']}, taken in GBP [total_is_in_wrong_currency]"
                                  : NULL,
    ];
    if (isset($contrib_recur)) {
      $contrib_params['contribution_recur_id'] = $contrib_recur['id'];
    }
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
    $params = [
      'description'          => 'Donation', //xxx
      'session_token'        => $token,
      'success_redirect_url' => $this->getUrlWithParams(['gcrf' => 1]),
    ];
    $redirect_flow = $this->payment_processor->getRedirectFlow($params);

    if (!isset($_SESSION['odd_gcrf'])) {
      $_SESSION['odd_gcrf'] = [];
    }

    // Store all the [validated and parsed] input on normal Drupal session.
    $_SESSION['odd_gcrf'][$redirect_flow->id] = $this->input;
    $_SESSION['odd_gcrf'][$redirect_flow->id]['payment_processor_id'] = $this->payment_processor->getPaymentProcessor()['id'];
    $_SESSION['odd_gcrf'][$redirect_flow->id]['description'] = $params['description'];
    $_SESSION['odd_gcrf'][$redirect_flow->id]['session_token'] = $token;

    //CRM_Core_Error::debug_log_message(__FUNCTION__ . ": " . json_encode($redirect_flow) . "\n\n" . serialize($redirect_flow), FALSE, 'GoCardless', PEAR_LOG_INFO);
    return ['url' => $redirect_flow->redirect_url];
  }
  /**
   * Find or create CiviCRM contact.
   */
  public function getOrCreateContact() {

    // Find or create contact @todo use Björn's thing.
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
    $this->contact_id = $contact['id'];

    // Store address.
    // @todo
    // Store Gift Aid
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
}
