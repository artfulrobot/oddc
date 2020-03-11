<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Oddashboard.Getmailingstats API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_oddashboard_Getmailingstats_spec(&$spec) {
//  $spec['magicword']['api.required'] = 1;
  $spec['mailing_id'] = [
    'api.required' => 1,
  ];
}

/**
 * Oddashboard.Getmailingstats API
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
function civicrm_api3_oddashboard_Getmailingstats($params) {

  $mailing_ids = array_filter(array_map(function ($_) { return $_ > 0 ? (int) $_ : NULL; }, is_array($params['mailing_id']) ? $params['mailing_id'] : [$params['mailing_id']]));
  if (!$mailing_ids) {
    throw new API_Exception('No valid mailing_id values');
  }

  $mailings = civicrm_api3('Mailing', 'get', [
    'return'       => ["name"],
    'id'           => ['IN' => $mailing_ids],
    'options'      => ['limit' => 0],
  ])['values'] ?? [];

  $returnValues = array_fill_keys($mailing_ids, [
    'name'       => '(unknown mailing)',
    'delivered'  => NULL,
    'open_rate'  => NULL,
    'click_rate' => NULL,
  ]);

  foreach ($returnValues as $mailing_id => &$item) {
    if (!isset($mailings[$mailing_id])) {
      continue;
    }
    // This is a mailing and we have been able to load it's name.

    // Add the name of the mailing.
    $item['name'] = $mailings[$mailing_id]['name'];

    // Add in the stats.
    $stats = civicrm_api3('Mailing', 'stats', ['mailing_id' => $mailing_id])['values'][$mailing_id] ?? NULL;
    if (!$stats) {
      continue;
    }
    $item['delivered'] = (int) $stats['Delivered'];

    $unique_opens = CRM_Core_DAO::executeQuery("SELECT count(distinct q.contact_id)
          FROM civicrm_mailing_event_opened o
          INNER JOIN  civicrm_mailing_event_queue q ON o.event_queue_id = q.id
          INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
          WHERE j.mailing_id = $mailing_id;")->fetchValue();

    // Avoid divide by zero
    if ($item['delivered'] > 0) {
      $item['open_rate'] = number_format(100 * $unique_opens / $item['delivered'], 1) . '%';
    }
    $item['click_rate'] = number_format($stats['clickthrough_rate'], 1) . '%';
  }

  return civicrm_api3_create_success($returnValues, $params, 'Oddashboard', 'Getmailingstats');
}
