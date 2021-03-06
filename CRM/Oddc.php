<?php

class CRM_Oddc {
  const API_CUSTOM_FIELD_CAMPAIGN_FUNDING_TARGET = 'custom_67';
  const API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD = 'custom_68';
  /** @var array Validated input */
  public $input;

  /** @var array Hard coded map of payment processor ids until a better method in place.  */
  public $payment_processor_map = [
    'GoCardless' => [ 'company' => 'openDemocracy GoCardless Direct Debit', 'charity' => 'openTrust GoCardless Direct Debit' ],
    'PayPal'     => [ 'company' => 'openDemocracy PayPal'                 , 'charity' => 'openTrust PayPal' ]                 ,
  ];
  /** @var array Hard coded map of financial types.  */
  public $financial_type_map = [
    'charity' => 'Donation - openTrust',
    'company' => 'Donation - openDemocracy',
  ];

  /** @var CRM_Core_Payment */
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
   * Process Signup request.
   *
   * Expects:
   * - first_name
   * - last_name
   * - email
   * - mailing_list (which is the ID to a mailing group)
   * - nid
   * - source
   * And these (added from node data by oddd__add_page_details):
   * - legal_entity
   * - project
   * - campaign
   * - include_address
   * - mailing_list
   * - test_mode
   * - is_upgrade
   * - return_url (the URL to the signup page)
   * - donation_page_nid
   * - thanks_message_template_id
   * - tweet
   * - facebook
   *
   */
  public function processRequestSignup($input) {
    try {
      $this->validateSignup($input);
      if (!empty($input['signature'])) {
        return $this->doSignup();
      }
      else {
        return ['signature' => $this->generateSignature()];
      }
    }
    catch (CRM_Oddc_ValidationError $e) {
      return ['error' => $e->getMessage(), 'user_error' => $e->getMessage()];
    }
    catch (Exception $e) {
      return ['error' => 'Server problem: ' . $e->getMessage()];
    }
  }
  /**
   * Validate user input. Valid input is stored on $this->input
   *
   * @param array $input
   * @throws CRM_Oddc_ValidationError
   */
  public function validateSignup($input) {
    $this->input = [];
    $this->extractValidEmail($input);
    $this->extractValidSource($input);
    $this->extractValidNames($input);
    $this->extractTrustedData($input);
    if (empty($input['mailing_list'])) {
      throw new CRM_Oddc_ValidationError("Sorry this form is not configured correctly. err5");
    }
    $this->input['mailing_list_id'] = $input['mailing_list'];
    $this->input['mailing_list_name'] = civicrm_api3('group', 'get', ['id' => $input['mailing_list'], 'return' => 'title', 'sequential' => 1])['values'][0]['title'] ?? '';;

    if (empty($this->input['mailing_list_name'])) {
      watchdog('odd', 'Failed to load mailing list for @id', ['@id' => $input['mailing_list']], WATCHDOG_ERROR);
      throw new CRM_Oddc_ValidationError("Sorry this form is not configured correctly. err6");
    }

    if (array_key_exists('signature', $input)) {
      // Signature must be valid.
      $hash = $this->generateSignature();
      if (hash_equals($hash, $input['signature'])) {
        $this->input['signature'] = $input['signature'];
      }
      else {
        throw new CRM_Oddc_ValidationError("Corrupted request, please try again. err7");
      }
    }
  }
  /**
   * Generate signature.
   */
  public function generateSignature() {
    ksort($this->input);
    $hash = sha1(serialize($this->input) . 'let us hope this is secret');
    return $hash;
  }
  /**
   * Called once data has been validated and stored in $this->input.
   *
   * This is only called when consent is given.
   * It's used by the embedded form, not the self hosted form.
   *
   * - create/find contact
   * - create consent activity
   * - add to the mailing_list_id specified in input.
   *
   * @return array
   *    { success: 1, contact_id: (int) }
   */
  public function doSignup() {

    // Store name, email.
    $this->getOrCreateContact();

    $this->drySignup(
      (int) $this->contact_id,
      (int) $this->input['mailing_list_id'],
      'Gave consent on signup page',
      'node/' . $this->input['donation_page_nid'] . ' ' . $this->input['source'],
      '<p>Donation page at '
      . htmlspecialchars($this->input['return_url'] ?? '')
      . '</p>'
      . '<p>Group: #' . $this->input['mailing_list_id'] . ' '
      . htmlspecialchars($this->input['mailing_list_name']) . '</p>'
      . '<p>We asked: ' . htmlspecialchars($this->input['consent_invite']) . '</p>'
    );

    return ['success' => 1, 'contact_id' => $this->contact_id];
  }
  /**
   * Stores valid data on $this->input.
   *
   * @throws CRM_Oddc_ValidationError
   */
  public function extractValidSource($input) {
    $this->input['source'] = static::filterSource($input['source'] ?? '');
  }
  /**
   * Stores valid data on $this->input.
   *
   * @throws CRM_Oddc_ValidationError
   */
  public function extractTrustedData($input) {
    foreach (['return_url', 'campaign', 'project', 'legal_entity', 'mailing_list', 'donation_page_nid'] as $_) {
      $this->input[$_] = $input[$_];
    }
  }
  /**
   * Stores valid data on $this->input.
   *
   * @throws CRM_Oddc_ValidationError
   */
  public function extractValidNames($input) {
    // Check we have names.
    foreach (['first_name', 'last_name'] as $_) {
      $v = trim($input[$_] ?? '');
      if (!$v) {
        throw new CRM_Oddc_ValidationError("Missing $_");
      }
      $this->input[$_] = $input[$_];
    }
  }
  /**
   * Stores valid data on $this->input.
   *
   * @throws CRM_Oddc_ValidationError
   */
  public function extractValidEmail($input) {
    // Validate email.
    if (!preg_match('/[^@ <>"]+@[a-z0-9A-Z_-]+\.[a-z0-9A-Z_.-]+$/', $input['email'])) {
      throw new CRM_Oddc_ValidationError("The email address is invalid");
    }
    $this->input['email'] = $input['email'];
  }
  /**
   * Process Donation request.
   */
  public function processRequestDonation($input) {
    try {
      $this->validateDonation($input);
      return $this->routeToPaymentProcessor();
    }
    catch (CRM_Oddc_ValidationError $e) {
      return ['error' => $e->getMessage(), 'user_error' => $e->getMessage()];
    }
    catch (Exception $e) {
      return ['error' => 'Server problem: ' . $e->getMessage()];
    }
  }
  /**
   * Validate user input. Valid input is stored on $this->input
   *
   * @throws CRM_Oddc_ValidationError
   */
  public function validateDonation($input) {

    $this->input = [];
    $this->extractValidEmail($input);
    $this->extractValidSource($input);
    $this->extractValidNames($input);
    $this->extractTrustedData($input);

    $this->input['is_recur'] = (!empty($input['is_recur'])) ? 1: 0;

    // Validate geo country name.
    if (!preg_match('/^[A-Z]{2,3}$/', $input['geo'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid country.");
    }
    $this->input['geo'] = $input['geo'];

    // Validate currency.
    if (!preg_match('/^(GBP|USD|EUR)$/', $input['currency'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid currency.");
    }
    $this->input['currency'] = $input['currency'];

    // Validate amount.
    if (!preg_match('/^\d{1,4}(?:\.\d\d)?$/', $input['amount'] ?? '')) {
      throw new CRM_Oddc_ValidationError("Invalid amount");
    }
    if ($input['amount'] < 1) {
      throw new CRM_Oddc_ValidationError("Minimum amount is 1.00");
    }
    $this->input['amount'] = $input['amount'];

    // Booleans
    foreach (['test_mode', 'giftaid', 'consent', 'include_address'] as $_) {
      $this->input[$_] = empty($input[$_]) ? 0 : 1;
    }

    // Set financial type based on legal entity.
    $this->input['financial_type_id'] = $this->financial_type_map[$input['legal_entity']];

    // Copy other user data as is.
    foreach (['street_address', 'city', 'postal_code', 'country'] as $_) {
      $this->input[$_] = $input[$_] ?? '';
    }

    // This is set by oddd__add_page_details so it's safe.
    $this->input['consent_invite'] = $input['consent_invite'];
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
    $this->processCommonDonationContactData();

    $method = "process$method";
    return $this->$method();
  }
  /**
   * Get a redirect URL.
   *
   * @return string URL
   */
  protected function processPayPal() {

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
        'frequency_unit'         => "month", // Use "day" for testing.
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
      'payment_instrument_id'  => $this->payment_processor->getPaymentInstrumentID(),
      'total_amount'           => $this->input['amount'],
      'source'                 => $this->input['source'],
      'campaign_id'            => $this->input['campaign'],
      'contribution_status_id' => 'Pending',
      'is_test'                => $this->input['test_mode'],
      'invoice_id'             => $invoice_id,
      'note'                   => ($this->input['currency'] !== 'GBP')
                                  ? "Payment made as {$this->input['currency']}{$this->input['amount']}, taken in GBP [total_is_in_wrong_currency]"
                                  : NULL,
    ];
    if (isset($contrib_recur)) {
      $contrib_params['contribution_recur_id'] = $contrib_recur['id'];
    }
    $this->setGiftAidParam($contrib_params);
    $this->setOdProjectParam($contrib_params);
    $this->setDonationPageNidParam($contrib_params);

    Civi::log()->info('Contribution.create call with params:',
      ['params_used' => json_encode($contrib_params, JSON_PRETTY_PRINT),
       'input_data'=> json_encode($this->input, JSON_PRETTY_PRINT)]);
    $contribution = civicrm_api3('Contribution', 'create', $contrib_params);
    // Store the Contribution ID in session data so that the thank you page can send an email.
    // The contribution ID is passed in as contributionID on the success return URL.
    // By checking that the received contributionID is on this session var we can be sure that
    // we are not about to start thanking a hacker who knows a contributionID.
    if (!isset($_SESSION['oddc_paypal_contribs'])) {
      $_SESSION['oddc_paypal_contribs'] = [];
    }
    // Store the input on the session - this helps us email the right email later.
    $_SESSION['oddc_paypal_contribs'][$contribution['id']] = $this->input;

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
      'item_name'          => $this->input['financial_type_id'],
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
      'return'             => $this->getUrlWithParams(['result' => 1, 'contributionID' => $contribution['id']]),
      'cancel_return'      => $this->input['return_url'],
    ];
    Civi::log()->info("Oddc: Creating a paypal callback URL with params:", $paypalParams);

    // if recurring donations, add a few more items
    if ($this->input['is_recur']) {
      $paypalParams += array(
        'cmd' => '_xclick-subscriptions',
        'a3'  => $this->input['amount'],
        'p3'  => 1, //$params['frequency_interval'], // e.g. 1 with t3='M' means every (1) month
        't3'  => 'M',// set to D for testing (daily)
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
    // $payment_processor_config = $this->payment_processor->getPaymentProcessor();

    $params = [
      'session_token'        => $token,
      'description'          => $this->input['financial_type_id'],
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

    // Create Redirect Flow.
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
      ['session' => json_encode($_SESSION['odd_gcrf'][$redirect_flow->id], JSON_PRETTY_PRINT)]);

    return ['url' => $redirect_flow->redirect_url];
  }
  /**
   * Create the subscription.
   *
   * Called from oddc__complete_redirect_url() which is called by Drupal's
   * preprocess node hook when gcrf is present on GET data.
   *
   * @param array $input is query string data from GC, plus the form config.
   */
  public function completeGoCardlessRedirectFlow($input) {

    Civi::log()->info("Oddc::completeRedirectFlow. \nGET: {input}\n\nSESSION: {session}",
      ['input' => json_encode($input), 'session' => json_encode($_SESSION['odd_gcrf'], JSON_PRETTY_PRINT)]);

    // We are passed a redirect_flow_id; this must exist on our session.
    $redirect_flow_id = $input['redirect_flow_id'] ?? 'missing';
    if (empty($_SESSION['odd_gcrf'][$redirect_flow_id])) {
      throw new \Exception("Direct Debit could not be set up. This process requires cookies.");
    }
    $pre_data = $_SESSION['odd_gcrf'][$redirect_flow_id];

    // Unpack some of the stashed-in-session data for our convenience.
    $this->input = $input + $pre_data;
    $this->payment_processor = Civi\Payment\System::singleton()->getById($pre_data['payment_processor_id']);
    $this->contact_id = $pre_data['contact_id'];

    // Complete the redirect flow with GC.
    $params = [
      'redirect_flow_id' => $redirect_flow_id,
      'interval_unit'    => 'monthly',
    ] + $pre_data;
    $result = CRM_GoCardlessUtils::completeRedirectFlowWithGoCardless($params);
    $subscription  = $result['subscription'];

    // Create a ContributionRecur record.
    // Set financial type based on legal entity.
    $financial_type_id = $this->financial_type_map[$input['legal_entity']];

    $contrib_recur = civicrm_api3('ContributionRecur', 'create', array(
      'amount'                 => $params['amount'],
      'contact_id'             => $this->contact_id,
      // 2019-11-15 We now set this to In Progress to copy designed bahaviour.
      // Old notes:
      // Was set to Pending, until first payment came in.
      // This is useful and should get changed to
      // In Progress after successful payment.
      'contribution_status_id' => "In Progress",
      'currency'               => 'GBP', // fixed.
      'financial_type_id'      => $financial_type_id,
      'frequency_interval'     => 1,
      'frequency_unit'         => "month",
      'campaign_id'            => $params['campaign'],
      'is_test'                => $this->payment_processor->getPaymentProcessor()['is_test'],
      'payment_instrument_id'  => 'direct_debit_gc',
      'payment_processor_id'   => $params['payment_processor_id'],
      'start_date'             => $subscription->start_date,
      'trxn_id'                => $subscription->id,
      'processor_id'           => $subscription->id,
    ));

    // Create a pending Contribution record.
    $contrib_params = [
      'amount'                 => $params['amount'],
      'contact_id'             => $this->contact_id,
      'contribution_recur_id'  => $contrib_recur['id'],
      'contribution_status_id' => "Pending", // This is useful and should get changed to In Progress
      'currency'               => 'GBP', // fixed.
      'financial_type_id'      => $financial_type_id,
      'campaign_id'            => $params['campaign'],
      'source'                 => $params['source'],
      'is_test'                => $this->payment_processor->getPaymentProcessor()['is_test'],
      'payment_instrument_id'  => 'direct_debit_gc',
      'payment_processor_id'   => $params['payment_processor_id'],
      'receive_date'           => $subscription->start_date,
      'total_amount'           => $params['amount'],
    ];
    $this->setOdProjectParam($contrib_params);
    $this->setGiftAidParam($contrib_params);
    $this->setDonationPageNidParam($contrib_params);
    $contrib = civicrm_api3('Contribution', 'create', $contrib_params);

    // Now we send thank you.
    // Add in contributionID, contactID to input.
    $this->input['contributionID'] = $contrib['id'];
    $this->input['contactID'] = $this->contact_id;
    $this->sendThankYouEmail($this->input);

    $this->upgradesActions($this->input);

    $this->extractDataForAnalyticsToSession($this->input);
  }
  /**
   * Find or create CiviCRM contact.
   */
  public function getOrCreateContact() {

    if (civicrm_api3('Extension', 'getcount', ['key' => 'de.systopia.xcm', 'is_active' => 1]) == 1) {
      // Assume we have XCM
      $required_params = ['email', 'first_name', 'last_name' ];
      if ($this->input['include_address']) {
        $required_params = array_merge($required_params, ['street_address', 'city', 'postal_code', 'country']);
      }
      $params = array_intersect_key($this->input, array_flip($required_params));
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
  }
  /**
   * Store data common to PayPal and GoCardless donation requests.
   * on the self-hosted donation pages.
   */
  public function processCommonDonationContactData() {

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
    if ($this->input['mailing_list']) {

      $mailing_list_name = civicrm_api3('group', 'get', ['id' => $this->input['mailing_list'], 'return' => 'title', 'sequential' => 1]);
      $mailing_list_name = $mailing_list_name['values'][0]['title'] ?? '';

      if (!$mailing_list_name) {
        watchdog('odd', 'Failed to load mailing list for @id', ['@id' => $this->input['mailing_list']], WATCHDOG_ERROR);
      }

      if ($mailing_list_name && !empty($this->input['consent'])) {
        // Create an activity.
        $consent_activity_type_id = civicrm_api3('OptionValue', 'get', ['sequential' => 1, 'option_group_id' => "activity_type", 'name' => "marketing_consent"])['values'][0]['value'] ?? 0;

        civicrm_api3('Activity', 'create', [
          'source_contact_id'  => $this->contact_id,
          'activity_type_id'   => $consent_activity_type_id,
          'target_id'          => $this->contact_id,
          'subject'            => 'Gave consent at time of making donation.',
          'details'            => '<p>Donation page at '
          . htmlspecialchars($this->input['return_url'])
          . '</p>'
          . '<p>Group: #' . $this->input['mailing_list'] . ' '
          . htmlspecialchars($mailing_list_name) . '</p>'
          . '<p>We asked: ' . htmlspecialchars($this->input['consent_invite']) . '</p>',
          'status_id'          => 'Completed',
          'activity_date_time' => date('Y-m-d H:i:s'),
        ]);
        }

        // Add the contact to the list.
        $contact_ids = [$this->contact_id];
        CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $this->input['mailing_list']);
      }
    }
  /**
   * PayPal user clicked Return To Merchant after making payment.
   */
  public function completePayPal($input) {

    Civi::log()->info(
      "Oddc::completePayPal. \nGET: {input}\n\nSESSION: {session}",
      [
        'input'   => json_encode($input, JSON_PRETTY_PRINT),
        'session' => json_encode($_SESSION['oddc_paypal_contribs'] ?? NULL, JSON_PRETTY_PRINT)
      ]
    );

    // Unpack the 'custom' field
    $custom = json_decode($input['custom'] ?? '', TRUE);
    if (!$custom) {
      Civi::log()->error("Oddc::completePayPal. No 'custom' data could be extracted. Will not send email.", []);
      return;
    }
    // Move contents into our input.
    $input += $custom;

    // We require that the contribution ID matches one on session.
    if (empty($input['contributionID']) || empty($_SESSION['oddc_paypal_contribs'][$input['contributionID']])) {
      Civi::log()->error("Oddc::completePayPal. Input contributionID not found on session. Will not send email.", []);
      return;
    }

    // Load the email from the session data.
    $input['email'] = $_SESSION['oddc_paypal_contribs'][$input['contributionID']]['email'] ?? '';

    $this->sendThankYouEmail($input);

    $this->upgradesActions($input);

    $this->extractDataForAnalyticsToSession($input);

    // Clean up session data a bit as it is no longer needed.
    unset($_SESSION['oddc_paypal_contribs'][$input['contributionID']]);

  }
  /**
   * Send thank you email.
   *
   * Minimum input keys are:
   * - contactID
   * - contributionID
   * - thanks_message_template_id
   * - email - email address to send to.
   *
   * @param array $input
   */
  public function sendThankYouEmail($input) {

    Civi::log()->info("Oddc::sendThankYouEmail. input is", ['input' => json_encode($input, JSON_PRETTY_PRINT)]);
    // Check for an optional configured thank you message.
    if (!(((int) ($input['thanks_message_template_id'] ?? 0))>0)) {
      Civi::log()->info("Oddc::sendThankYouEmail. No thanks_message_template_id '$input[thanks_message_template_id]'. Will not send email.", []);
      return;
    }

    if (!(CRM_Utils_Rule::positiveInteger($input['contactID'] ?? 0))) {
      Civi::log()->info("Oddc::sendThankYouEmail. No contactID '$input[contactID]'. Will not send email.", []);
      return;
    }

    if (!(CRM_Utils_Rule::email($input['email'] ?? ''))) {
      Civi::log()->info("Oddc::sendThankYouEmail. Missing/invalid email '$input[email]'. Will not send email.", []);
      return;
    }

    // Prepare extra vars for the smarty template.
    $template_params = [];

    // Load the contribution.
    require_once 'CRM/Core/BAO/CustomField.php';
    $gift_aid_custom_field = 'custom_' . CRM_Core_BAO_CustomField::getCustomFieldID('Eligible_for_Gift_Aid', 'Gift_Aid');
    $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $input['contributionID'], 'return' => ['total_amount', 'contribution_recur_id', 'note', 'financial_type_id', $gift_aid_custom_field]]);

    // Calculate description.
    $note = civicrm_api3('Note', 'get', [
      'sequential' => 1,
      'entity_table' => "civicrm_contribution",
      'entity_id' => $input['contributionID'],
    ]);
    Civi::log()->info("Note:", $note);
		if (preg_match('/^Payment made as ([A-Z]{3})(\d[.0-9]*), taken in GBP/', ($note['values'][0]['note'] ?? ''), $matches)) {
      // Was made in foreign currency.
      $template_params['donation_description'] = $matches[1] . number_format($matches[2], 2) . ' (taken in GBP)';
		}
    else {
      // GPB
      $template_params['donation_description'] = "£" . number_format($contribution['total_amount'], 2);
    }

    if (($contribution['contribution_recur_id'] ?? 0) > 0) {
      // Recurring. At the moment it means monthly.
      $template_params['donation_description'] .= " monthly donation";
    }
    else {
      // One off.
      $template_params['donation_description'] .= " donation";
    }

    // Legal entity. Default to openDemocracy
    $template_params['legal_entity'] = 'openDemocracy';
    $financial_type_name = civicrm_api3('FinancialType', 'getvalue', ['return' => "name", 'id' => $contribution['financial_type_id']]);
    if ($financial_type_name === 'Donation - openTrust') {
      $template_params['legal_entity'] = 'openTrust';
    }

    // Gift Aid
    $template_params['gift_aid'] = '';
    if (($contribution[$gift_aid_custom_field] ?? '') == 1) {
      $template_params['gift_aid'] = 'Gift Aid will be claimed on this contribution.';
    }

    $from = CRM_Core_BAO_Domain::getNameAndEmail();
    $params = [
      'id'              => (int) $input['thanks_message_template_id'],
      'contact_id'      => (int) $input['contactID'],
      'template_params' => $template_params,
      'from'            => $from[1],
      'to_email'        => $input['email'],
      'to_name'         => civicrm_api3('Contact', 'getvalue', ['return' => 'display_name', 'id' => $input['contactID']]),
    ];
    Civi::log()->info("Oddc::sendThankYouEmail. Sending:", $params);

    $result = civicrm_api3('MessageTemplate', 'send', $params);
    Civi::log()->info("Oddc::sendThankYouEmail. Sent email.", ['result' => $result]);
    return $result;
  }
  /**
   * Send email to notify staff.
   *
   * N.b. to address is hard-coded to finance@opendemocracy.net
   *
   * Minimum input keys are:
   * - contact_id
   * - od_message: explanation of what to do
   * - subject: explanation of what to do
   *
   * @param array $input
   */
  public static function sendStaffNotificationEmail($input) {
    if (!($input['contact_id'] ?? 0 > 0)) {
      Civi::log()->info("Oddc::sendStaffNotificationEmail. No contactID '$input[contact_id]'. Will not send email.", []);
      return;
    }

    // Prepare extra vars for the smarty template.
    $template_params = [];
    $template_params['who'] = civicrm_api3('Contact', 'getvalue', ['return' => 'display_name', 'id' => $input['contact_id']]);
    $template_params['contact_record_link'] = CRM_Utils_System::url('civicrm/contact/view', ['reset' => 1, 'cid' => $input['contact_id']], TRUE);
    $template_params['od_message'] = $input['od_message'] ?? '(Missing! This is a bug!)';
    $template_params['od_subject'] = $input['od_subject'] ?? '(Missing! This is a bug!)';

    $from = CRM_Core_BAO_Domain::getNameAndEmail();
    $params = [
      'id'              => 85,
      'template_params' => $template_params,
      'from'            => $from[1],
      'to_email'        => 'finance@opendemocracy.net',
      'to_name'         => 'oD staff',
    ];
    Civi::log()->info("Oddc::sendStaffNotificationEmail. Sending:", $params);

    $result = civicrm_api3('MessageTemplate', 'send', $params);
    Civi::log()->info("Oddc::sendStaffNotificationEmail. Sent email.", ['result' => $result]);
    return $result;
  }
  /**
   * On completion of setup of new regular giving,
   *
   * @param array $input keys that are relied upon:
   * - contactID
   * - contributionID
   * - is_upgrade bool
   */
  public function upgradesActions($input) {

    if (empty($input['is_upgrade'])) {
      // This is not an upgrades page.
      return;
    }

    // Load contribution, check it's a regular.
    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'id'     => $input['contributionID'],
      'return' => ['total_amount', 'contribution_recur_id', 'note', 'financial_type_id']]);
    if (!($contribution['contribution_recur_id'] ?? 0)) {
      // This is a one off.
      return;
    }

    // Is recurring.
    // This is an upgrade, therefore we should record an activity.
    $activity_params = [
      'target_id'         => [$input['contactID']],
      'activity_type_id'  => 'Update Recurring Contribution',
      'source_contact_id' => $input['contactID'],
      'subject'           => 'New donation to replace existing regular giving',
      'status_id'         => 'Scheduled',
    ];
    // Fetch description of their current giving.
    $activity_params['details'] =
      "<p>New giving: $contribution[total_amount] monthly.</p>"
      . "<p>All giving: "
      . htmlspecialchars(CRM_Oddc::getCurrentRegularGivingDescription($input['contactID']))
      . '</p>'
      . "<p><strong>Note: all previous regular giving arrangements need to be cancelled, leaving only this latest one in place</strong></p>";

    // Create the activity
    civicrm_api3('Activity', 'create', $activity_params);

    $params = [
      'contact_id'          => $input['contactID'],
      'od_subject'          => $activity_params['subject'],
      'od_message'          => $activity_params['details'],
    ];
    CRM_Oddc::sendStaffNotificationEmail($params);
  }
  /**
   * Extract amount for Google Analytics.
   *
   * Saves to $_SESSION['oddc_analytics_data']
   *
   * @param array $input keys that are relied upon:
   * - contactID
   * - contributionID
   */
  public function extractDataForAnalyticsToSession($input) {

    // Load contribution
    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'id'     => $input['contributionID'],
      'return' => ['total_amount', 'contribution_recur_id', 'note', 'financial_type_id']]);

    $_SESSION['oddc_analytics_data'] = [
      'id' => $input['contributionID'] ,
      'currency' => $contribution['currency'],
      'amount' => $contribution['total_amount'],
    ];

    if (!($contribution['contribution_recur_id'] ?? 0)) {
      // One off
    }
    else {
      // Regular - https://trello.com/c/d4SRFTVY/103-add-google-analytics-e-commerce-tracking-code-send-purchase-events-on-sale#comment-5e176a1f472de116e80a469e
      $_SESSION['oddc_analytics_data']['amount'] = 300;
    }
    Civi::log()->debug("Oddc::extractDataForAnalyticsToSession", ['input' => $input, 'contribution' => $contribution, 'output(session)' => $_SESSION['oddc_analytics_data']]);
  }
  /**
   * Update total received on campaign targets.
   */
  public function updateCampaignTargetStats() {
    // Load campaigns.
    $campaigns = civicrm_api3('Campaign', 'get', ['return' =>  ["id", self::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD]]);
    // Load stats.
    $totals_raised = CRM_Core_DAO::executeQuery('SELECT campaign_id, SUM(net_amount) raised FROM civicrm_contribution WHERE campaign_id IS NOT NULL AND contribution_status_id=1 AND is_test = 0 GROUP BY campaign_id')
      ->fetchMap('campaign_id', 'raised');
    foreach ($campaigns['values'] as $campaign_id => $details) {
      $raised = (int) round($totals_raised[$campaign_id] ?? 0);
      if (!isset($details[self::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD])
        || $details[self::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD] != $raised) {
        // Need to update total.
        civicrm_api3('Campaign', 'create', ["id" => $campaign_id, self::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD => $raised]);
        Civi::log()->info("Updated campaign $campaign_id with total " . self::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD . " = " . $raised);
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
    $params["custom_$id"] = $this->input['giftaid'] ?? FALSE;
  }
  /**
   * Lookup the custom field ID for project and set it as
   * a parameter for the Contribution.create call.
   *
   * @param &Array params for the Contribution.create API call.
   */
  protected function setOdProjectParam(&$params) {
    // Look up the custom_N name for the field.
    require_once 'CRM/Core/BAO/CustomField.php';
    $id = CRM_Core_BAO_CustomField::getCustomFieldID('od_project', 'od_project_group');
    $params["custom_$id"] = $this->input['project'];
  }
  /**
   * Lookup the custom field ID for Donation Page Node ID and set it as
   * a parameter for the Contribution.create call.
   *
   * @param &Array params for the Contribution.create API call.
   */
  protected function setDonationPageNidParam(&$params) {
    // Look up the custom_N name for the field.
    require_once 'CRM/Core/BAO/CustomField.php';
    $id = CRM_Core_BAO_CustomField::getCustomFieldID('donation_page_nid', 'od_project_group');
    $params["custom_$id"] = $this->input['donation_page_nid'];
  }
  /**
   * @return array of arrays like:
   * - contribution_recur_id
   * - entity (string openDemocracy or openTrust)
   * - amount (like 1.23)
   * - processor (like "GoCardless")
   * - currency (e.g. GBP)
   * - currencySymbol (e.g. £|$|€|CAD)
   * - description string.
   *
   */
  public static function getCurrentRegularGiving($contact_id) {
    $contact_id = (int) $contact_id;
    if ($contact_id <1) {
      throw new InvalidArgumentException("Invalid contact_id");
    }
    // Find active recurring payments.
    $results = civicrm_api3('ContributionRecur', 'get', [
      'contact_id'             => $contact_id,
      'contribution_status_id' => ['IN' => ['In Progress', 'Pending']],
      'return' => [
        'id', 'amount', 'currency', 'frequency_unit',
        'payment_processor_id.id',
        'payment_processor_id.name',
        'contribution_status_id',
        'payment_processor_id.payment_processor_type_id.name',
        'is_test',
      'financial_type_id.name', 'frequency_interval'],
    ]);

    $regulars = [];
    $pending_status = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'Pending');
    if ($results['values'] ?? NULL) {
      foreach($results['values'] as $recur) {
        if ($pending_status == $recur['contribution_status_id']
          && 'PayPal_Standard' === $recur['payment_processor_id.payment_processor_type_id.name']) {
          // Do not count PayPal 'pending' ones - they are probably abandoned.
          continue;
        }

        $_ = [
          'contribution_recur_id'  => $recur['id'],
          'contribution_status'    => ($pending_status == $recur['contribution_status_id']) ? 'pending' : 'live',
          'payment_processor_id'   => $recur['payment_processor_id.id'],
          'entity'                 => preg_replace('/^(openTrust|openDemocracy).*$/', '$1', $recur['payment_processor_id.name'] ?? 'openDemocracy'),
          'amount'                 => $recur['amount'],
          'is_test'                => $recur['is_test'] ?? 0,
          'processor'              => $recur['payment_processor_id.payment_processor_type_id.name'],
          'currency'               => $recur['currency'],
          'currencySymbol'         => ['GBP' => '£', 'USD' => '$', 'EUR' => '€'][$recur['currency']] ?? $recur['currency'],
        ];

        if ($recur['frequency_interval'] > 1) {
          //$_['description'] = "$_[currencySymbol]$_[amount] every $recur[frequency_interval] $recur[frequency_unit]s to $_[entity]";
          $_['description'] = "$_[currencySymbol]$_[amount] every $recur[frequency_interval] $recur[frequency_unit]s to openDemocracy";
        }
        else {
          $_['description'] = "$_[currencySymbol]$_[amount] a $recur[frequency_unit] to openDemocracy";
        }
				if ($_['contribution_status'] === 'pending') {
					$_['description'] .= " (not started yet)";
        }
        $regulars[] = $_;
      }
    }

    return $regulars;
  }
  /**
   * Description logic for regular giving.
   *
   * @param int $contact_id
   *
   * @return string
   *
   */
  public static function getCurrentRegularGivingDescription($contact_id) {
    $giving = CRM_Oddc::factory()->getCurrentRegularGiving($contact_id);
    switch (count($giving)) {
    case 0:
      $description = 'You are not currently giving regularly to openDemocracy.';
      break;

    case 1:
      // Good.
      $description = 'You are currently giving ' . $giving[0]['description'] . '.';
      break;

    default:
      $description = 'You currently have multiple regular donations set up: '
        . implode(' and ', array_column($giving, 'description')) . '.';
    }
    return $description;
  }
  /**
   * This is for the purpose of centralising the regex.
   *
   * @param string $source
   * @return string filtered source.
   */
  public static function filterSource($source) {
    return preg_replace('/[^a-zA-Z0-9_,.!%£$()?@#| \/:;-]+/', '-', $source ?? '');
  }
  /**
   * @param Array
   *    Array of integer nids.
   * @param NULL|string
   *
   * @return Array
   *    Keyed by 'A' and 'B' with array values which have keys: nid, count, sum
   */
  public static function getAbStats($nids, $startTimestamp=NULL) {

    $andIsAfterStartTime = $startTimestamp ? "AND c.receive_date >= %3" : '';

    // We count completed contributions, plus those pending ones where the
    // recur record is In Progress - that should include the initial GoCardless
    // contributions.
    $pendingContribStatus = 2;
    $inProgressContribRecurStatus = 5;
    $completedContribStatus = 1;
    $gcPaymentProcessorIds = '9, 17';

    $sql = "SELECT donation_page_nid_69 nid, COUNT(*) `count`, SUM(total_amount) `sum`
      FROM civicrm_value_od_project_30 winner
      INNER JOIN civicrm_contribution c ON winner.entity_id = c.id
      LEFT JOIN civicrm_contribution_recur cr ON c.contribution_recur_id = c.id
      WHERE winner.donation_page_nid_69 IN (%1, %2)
            AND c.is_test = 0
            $andIsAfterStartTime
            AND (c.contribution_status_id = $completedContribStatus OR (
              c.contribution_status_id = $pendingContribStatus
              AND cr.contribution_status_id = $inProgressContribRecurStatus
              AND cr.payment_processor_id IN ($gcPaymentProcessorIds)
            ))
      GROUP BY winner.donation_page_nid_69";
    $params = [
      1 => [$nids[0], 'Integer'],
      2 => [$nids[1], 'Integer'],
    ];
    if ($startTimestamp) {
      $params[3] = [date('YmdHis', strtotime($startTimestamp)), 'Timestamp'];
    }
    $results = [
      'updated' => date('Y-m-d H:i:s'),
      'A' => ['nid' => $nids[0], 'count' => 0, 'sum' => 0],
      'B' => ['nid' => $nids[1], 'count' => 0, 'sum' => 0],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while ($dao->fetch()) {
      if ($dao->nid == $nids[0]) {
        $results['A'] = $dao->toArray();
      }
      elseif ($dao->nid == $nids[1]) {
        $results['B'] = $dao->toArray();
      }
      else {
        throw new \Exception("Unexpected nid value '$dao->nid' is neither of " . json_encode($nids));
      }
    }
    return $results;

  }
  /**
   * This is DRY code trying to centralise the logic of signing someone up.
   *
   * - Adds a consent activity
   * - Adds them to $groupID (unless it's null)
   * - Adds them to the general group.
   * - Clears their do not email and bulk opt-out flags.
   */
  public static function drySignup(
    int $contactID,
    ?int $groupID,
    string $consentSubject, string $consentDetails, string $consentLocation, ?string $when=NULL
  ) {

    // Look them up and clear is_opt_out and do_not_email
    $dao = new CRM_Contact_BAO_Contact();
    $dao->id = $contactID;
    if (!$dao->find(1)) {
      // This should not happen, coding error if it does.
      throw new \RuntimeException("Could not find contact $contactID, passed into drySignup for group $groupID");
    }
    $needsSaving = FALSE;
    if ($dao->is_opt_out) {
      $dao->is_opt_out = 0;
      $needsSaving = TRUE;
    }
    if ($dao->do_not_email) {
      $dao->do_not_email = 0;
      $needsSaving = TRUE;
    }
    if ($needsSaving) {
      $dao->save();
    }

    civicrm_api3('Activity', 'create', [
      'source_contact_id'  => $contactID,
      'target_id'          => $contactID,
      'activity_type_id'  => "marketing_consent",
      'subject'            => $consentSubject,
      'location'           => $consentLocation,
      'details'            => $consentDetails,
      'status_id'          => 'Completed',
      'activity_date_time' => $when ? $when : date('Y-m-d H:i:s'),
    ]);

    // Add the contact to the list.
    $contactIDs = [$contactID];
    if ($groupID) {
      CRM_Contact_BAO_GroupContact::addContactsToGroup($contactIDs, $groupID);
    }

    // Always add them to the general list, too.
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contactIDs, oD_GENERAL_EMAIL_GROUP_ID);

  }
}
