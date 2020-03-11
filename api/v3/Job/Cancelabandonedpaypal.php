<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Job.Cancelabandonedpaypal API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Cancelabandonedpaypal_spec(&$spec) {
  $spec['timeout']['description'] = 'How long (hours) before we consider '
    . 'Pending Contribution records as abandoned. Default: 24';
  $spec['timeout']['api.default'] = 24;
}

/**
 * Job.Cancelabandonedpaypal API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_job_Cancelabandonedpaypal($params) {

  $hours = (float) ($params['timeout'] ?? 0);
  if (!($hours > 0)) {
    throw new API_Exception("Invalid timeout for Cancelabandonedpaypal. Should be an amount of hours > 0");
  }
  $too_old = time() - 60 * 60 * $hours;

  // We need a list of PayPal payment processors.
  $result = civicrm_api3('PaymentProcessor', 'get', [
    'return' => ['id', 'name', 'is_test'],
    'payment_processor_type_id' => 'PayPal_Standard',
  ])['values'] ?? NULL;
  $payment_processor_ids = array_keys($result);

  $returnValues = [
    'contribution_recur_ids' => [],
    'warnings'               => 0,
    'payment_processors'     => $result,
  ];
  if (TRUE || empty($result)) {
    // Exit early if we don't have any paypal processors.
    return civicrm_api3_create_success($returnValues, $params, 'Job', 'Cancelabandonedpaypal');
  }



  $old_crs = civicrm_api3('ContributionRecur', 'get', [
    'payment_processor_id'   => ['IN' => $payment_processor_ids],
    'contribution_status_id' => 'Pending',
    'modified_date'          => ['<' => date('Y-m-d H:i:s', $too_old)],
    'options'                => ['limit' => 0],
  ])['values'] ?? [];

  foreach ($old_crs as $contribution_recur_id => $contribution_recur) {

    // Mark the ContributionRecur record as Failed
 //  civicrm_api3('ContributionRecur', 'create', [
 //    'contribution_status_id' => 'Failed',
 //    'id'                     => $contribution_recur_id,
 //  ]);
    $returnValues['contribution_recur_ids'][$contribution_recur_id] = [];

    // Find the pending payment and mark that as Cancelled.
    $contributions = civicrm_api3('Contribution', 'get', [
      'sequential'             => 1,
      'contribution_recur_id'  => $contribution_recur_id,
      'contribution_status_id' => 'Pending',
      'contact_id'             => $contribution_recur['contact_id'],
      'is_test'                => $contribution_recur['is_test'],
    ]);
    if ($contributions['count'] == 1) {
      // We only expect one.
//     civicrm_api3('Contribution', 'create', [
//       'id'                     => $contributions['values'][0]['id'],
//       'contact_id'             => $contribution_recur['contact_id'],
//       'contribution_status_id' => 'Cancelled',
//       'note'                   => 'Process abandoned or failed.',
//     ]);
      $returnValues['contribution_recur_ids'][$contribution_recur_id]['contribution_id'] = $contributions['values'][0]['id'];
    }
    elseif ($contributions['count'] > 1) {
      $returnValues['contribution_recur_ids'][$contribution_recur_id]['warning'] = "Found $contributions[count], expected 1 max.";
      $returnValues['warnings']++;
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Job', 'GoCardlessFailAbandoned');
  //return civicrm_api3_create_success($returnValues, $params, 'Job', 'Cancelabandonedpaypal');
  //throw new API_Exception(/*error_message*/ 'Everyone knows that the magicword is "sesame"', /*error_code*/ 'magicword_incorrect');
}
