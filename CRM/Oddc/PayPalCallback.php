<?php

class CRM_Oddc_PayPalCallback {
  /**
   * Handle incoming PayPal requests at civicrm/oddc/paypal-callback
   *
   * This is called after successful or failed authorisation.
   * @param array $params with keys:
   * - `success` 0 (cancel) 1 (success)
   *
   * // @todo is this an open redirect?
   *
   */
  public static function handleRequest() {
    $time = date('Y-m-d--H-i-s');
    // Drupal only
    file_put_contents("private://paypal-log-$time-request.log",serialize(['GET' => $_GET, 'POST' => $_POST]));

    if (!isset($_GET['success'])) {
      throw new \Exception("Missing 'success' param in " . __CLASS__);
    }
    // we need to get details.
    // we need to know the payment processor in use.
    //$payment_processor = Civi\Payment\System::singleton()->getByID($collected['payment_processor']);

    // Business logic goes here. - poss pass on to another custom function?

    CRM_Utils_System::redirect('https://artfulrobot.uk');
  }
}
