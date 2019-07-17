<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Job.Fixwrongcurrency API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Fixwrongcurrency_spec(&$spec) {
}

/**
 * Job.Fixwrongcurrency API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_Fixwrongcurrency($params) {

  // Find completed PayPal transactions which have the [total_is_in_wrong_currency] note on them.
  $completed_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
  if (!$completed_status > 0) {
    throw new Exception("Well that's odd, expected to find contribution status for Completed but failed.");
  }

  // Get PayPal payment processors.
  $result = civicrm_api3('PaymentProcessor', 'get', [
      'return' => ["id"],
      'payment_processor_type_id' => "PayPal_Standard",
    ]);
  if ($result['count'] == 0) {
    throw new \Exception("Failed to find paypal processors");
  }
  $paypal_processor_ids = implode(', ', array_keys($result['values']));

  $sql = "SELECT c.id contribution_id,
                 c.total_amount,
                 c.net_amount,
                 c.fee_amount,
                 n.id note_id,
                 n.note
        FROM civicrm_note n
        INNER JOIN civicrm_contribution c ON n.entity_id = c.id
        INNER JOIN civicrm_entity_financial_trxn eft ON eft.entity_table = 'civicrm_contribution'
                   AND eft.entity_id = c.id
        INNER JOIN civicrm_financial_trxn f ON f.id = eft.financial_trxn_id
                   AND f.payment_processor_id IN ($paypal_processor_ids)
        AND from_financial_account_id IS NULL
        WHERE n.entity_table = 'civicrm_contribution'
          AND note LIKE '%[total_is_in_wrong_currency]%'
          AND c.contribution_status_id = $completed_status;";

  $dao = CRM_Core_DAO::executeQuery($sql);
  $log = [];
  while ($dao->fetch()) {

    // Calculate correct total.
    $total = $dao->net_amount + $dao->fee_amount;
    $_ = "Contribution $dao->contribution_id total changing from $dao->total_amount (Net: $dao->net_amount + Fee: $dao->fee_amount) to $total";
    $log[] = $_;
    Civi::log()->info($_, []);

    // Correct total
    $result = civicrm_api3('Contribution', 'create', [
      'id'           => $dao->contribution_id,
      'total_amount' => $total,
      'net_amount'   => $dao->net_amount,
      'fee_amount'   => $dao->fee_amount,
    ]);

    $new_note = str_replace(' [total_is_in_wrong_currency]', " - actually got GBP$total gross total", $dao->note);
    $_ = "Note $dao->note_id total changing to '$new_note' from '$dao->note'";
    $log[] = $_;
    Civi::log()->info($_, []);
    civicrm_api3('Note', 'create', [ 'id' => $dao->note_id, 'note' => $new_note]);
  }

  return civicrm_api3_create_success($log, $params, 'Job', 'Fixwrongcurrency');
}
