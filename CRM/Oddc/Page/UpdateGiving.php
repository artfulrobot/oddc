<?php
use CRM_Oddc_ExtensionUtil as E;

class CRM_Oddc_Page_UpdateGiving extends CRM_Core_Page {

  const UPGRADE_DONATE_URL = '/node/33';

  public function run() {
    // Drupal global:
    global $user;

    //
    // Get the contact id and checksum
    //
    if (!empty($_POST)) {
      // This is an ajax request.
      if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
        // Not an xhr request.
        CRM_Utils_System::civiExit(401);
      }
      $contact_id = (int) ($_POST['cid'] ?? 0);
      $checksum = $_POST['cs'] ?? '';
      $is_ajax = TRUE;
    }
    else {
      $contact_id = (int) ($_GET['cid'] ?? 0);
      $checksum = $_GET['cs'] ?? '';
      $is_ajax = FALSE;
    }

    //
    // Authenticate request.
    //
    if (!$user->uid) {
      // Not logged in as a Drupal user so we need to validate the contact hash.
      if (!$contact_id || !CRM_Contact_BAO_Contact_Utils::validChecksum($contact_id, $checksum)) {
        // Link may have expired.
        Civi::log()->warning("UpdateGiving: invalid link. User sent to donate page.", ['contact_id' => $contact_id, 'checksum' => $checksum]);
        // Send people to the normal donate page.
        CRM_Utils_System::redirect(static::UPGRADE_DONATE_URL);
      }
    }

    if ($is_ajax) {
      $this->processAjax($contact_id, $_POST);
    }
    else {
      $this->processFirstPageLoad($contact_id, $checksum);
    }
    parent::run();
  }

  /**
   * Main page.
   */
  public function processFirstPageLoad($contact_id, $checksum) {

    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Update your giving'));
    $giving = CRM_Oddc::getCurrentRegularGiving($contact_id);
    if (!$giving) {
      // Hmmm. they don't have a regular gift, send them to donate page.
      // (they should probably not have received this mailing, so call this a warning)
      Civi::log()->warning("UpdateGiving: Not currently giving. User sent to donate page.", ['contact_id' => $contact_id]);
      $this->redirectToDonate($contact_id, $checksum);
    }
    if (count($giving) > 1) {
      // Multiple.
      Civi::log()->warning("UpdateGiving: Multiple current recurring payments. User sent to donate page.", ['contact_id' => $contact_id]);
      $this->redirectToDonate($contact_id, $checksum);
    }
    else {
      // Single, normal giving.
      if ($giving[0]['processor'] !== 'GoCardless') {
        Civi::log()->info("UpdateGiving: Non GoCardless supporter sent to donate page.", ['contact_id' => $contact_id]);
        $this->redirectToDonate($contact_id, $checksum);
      }
    }

    // OK, we can process this upgrade directly.
    Civi::log()->info("UpdateGiving: GoCardless supporter, will offer upgrade.", ['contact_id' => $contact_id]);

    // Look up the contact
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id'     => $contact_id,
      'return' => ['first_name', 'display_name'],
    ]);
    $this->assign('contact', $contact);
    $this->assign('is_test', $giving['is_test']);

    if ($contact['first_name']) {
      $this->assign('who', $contact['first_name']);
    }
    else {
      $this->assign('who', $contact['display_name']);
    }

    // Let's be cheeky and suggest an increase based on how much they're giving.
    $giving = $giving[0];
    if ($giving['amount'] < 6) {
      $this->assign('suggestion', number_format($giving['amount'] * 2), 2);
    }
    elseif ($giving['amount'] < 10) {
      $this->assign('suggestion', $giving['amount'] + 5);
    }
    elseif ($giving['amount'] < 30) {
      $this->assign('suggestion', $giving['amount'] + 10);
    }
    else {
      $this->assign('suggestion', $giving['amount'] + 20);
    }

    $this->assign('giving', $giving);
    $this->assign('contact_id', $contact_id);
    // Just make sure the checksum doesn't contain anything it should not.
    $this->assign('checksum', preg_replace('/[^0-9a-f_]+$/', '', $checksum));
  }

  /**
   * Handle ajax requests.
   *
   * This either returns an object like:
   * { error: 'message', success: FALSE }
   * { success: TRUE }
   */
  public function processAjax($contact_id, $post_data) {
    $giving = CRM_Oddc::getCurrentRegularGiving($contact_id);
    $response = [];

    if (count($giving) !== 1) {
      // Something's up - this should never happen.
      Civi::log()->warning("UpdateGiving: Weird, contact had one regular payment when they began but now have a different number",
        ['contact_id' => $contact_id, 'count' => count($giving)]);
      $response['error'] = 'Sorry, something unexpected has gone on here and we have not been able to update your giving.';
      $response['success'] = FALSE;
      CRM_Core_Page_AJAX::returnJsonResponse($response);
    }

    $giving = $giving[0];
    if ($giving['processor'] !== 'GoCardless') {
      Civi::log()->warning("UpdateGiving: Weird, contact had one regular GoCardless payment when they began but now it's not GC!",
        ['contact_id' => $contact_id, 'giving' => $giving]);
      $response['error'] = 'Sorry, something unexpected has gone on here and we have not been able to update your giving.';
      $response['success'] = FALSE;
      CRM_Core_Page_AJAX::returnJsonResponse($response);
    }

    // OK, looking good.

    // Get the processor for this ContributionRecur.
    $processor = Civi\Payment\System::singleton()->getById($giving['payment_processor_id']);
    $message = '';
    try {
      $processor->changeSubscriptionAmount($message, [
        'id'     => $giving['contribution_recur_id'],
        'amount' => $post_data['amount'],
      ]);

      $response['success'] = TRUE;
      Civi::log()->info("UpdateGiving: SUCCESS updating subscription",[
        'contact_id'            => $contact_id,
        'contribution_recur_id' => $giving['contribution_recur_id'],
        'amount'                => $post_data['amount'],
      ]);

      // Now Update CiviCRM's record.
      civicrm_api3('ContributionRecur', 'create', [
        'id'     => $giving['contribution_recur_id'],
        'amount' => $post_data['amount'],
      ]);

      CRM_Core_Page_AJAX::returnJsonResponse($response);
    }
    catch (PaymentProcessorException $e) {
      Civi::log()->error("UpdateGiving: FAILED trying to change subscription",[
        'message'               => $e->getMessage(),
        'contact_id'            => $contact_id,
        'contribution_recur_id' => $giving['contribution_recur_id'],
        'amount'                => $post_data['amount'],
      ]);
    }
    catch (Exception $e) {
      Civi::log()->error("UpdateGiving: FAILED trying to change subscription - general exception",[
        'message'               => $e->getMessage(),
        'type'                  => get_class($e),
        'contact_id'            => $contact_id,
        'contribution_recur_id' => $giving['contribution_recur_id'],
        'amount'                => $post_data['amount'],
      ]);
    }
    $response['error'] = 'Sorry, something went wrong while requesting the change in your direct debit. We will check out what happened and get back to you ASAP.';
    $response['success'] = FALSE;
    CRM_Core_Page_AJAX::returnJsonResponse($response);


  }

  /**
   * Redirect to donate page.
   */
  public function redirectToDonate($contact_id, $checksum) {
    $params = [
      'cid' => $contact_id,
      'cs' => $checksum,
    ];
    CRM_Utils_System::redirect(static::UPGRADE_DONATE_URL . '?' . http_build_query($params));
  }
}
