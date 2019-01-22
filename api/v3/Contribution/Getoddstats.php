<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Contribution.Getoddstats API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contribution_Getoddstats_spec(&$spec) {
  $spec['date_from'] = ['description' => 'Optional earliest date (not used by the report)'];
  $spec['date_to'] = ['description' => 'Optional latest date (not used by the report)'];
  $spec['granularity'] = ['description' => 'Date granularity (not used by the report - day used only)', 'options' => ['day', 'week', 'month', 'quarter', 'year'], 'default' => 'week'];
  $spec['first_only'] = ['description' => 'Only include the first contribution', 'options' => [0 => 'No', 1 => 'Yes'], 'default' => 0];
}

/**
 * Contribution.Getoddstats API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_Getoddstats($params) {
  $result = [ ];

  if (!empty($params['test'])) {
    $result = [
      'contributions' => [
        [ '2017-01-01', 1, 'Project A', 0, 1, 10, 1 ],
        [ '2017-02-01', 1, 'Project B', 1, 0, 10, 1 ],
        [ '2017-06-01', 1, 'Project A', 1, 0, 10, 1 ],
        [ '2018-01-01', 2, 'Project B', 2, 0, 10, 1 ],
        [ '2019-01-01', 2, 'Project A', 2, 1, 10, 1 ],
      ],
      'sources' => [
        [ 'name'=> 'Source A' ],
        [ 'name'=> 'Email A', 'opened_rate'=>"88.23%", 'clickthrough_rate'=> "2.56%", 'Delivered'=> "23414" ],
        [ 'name'=> 'Email B', 'opened_rate'=>"90.12%", 'clickthrough_rate'=> "10.56%", 'Delivered'=> "12323" ],
      ],
      'campaigns' => [
        1 => 'Campaign A',
        2 => 'Campaign B',
      ],
      'recur' => [
        [
          'period' => '2017-01-01',
          'create_date' => [0, 0],
          'start_date'  => [0, 0],
          'cancel_date' => [0, 0],
          'end_date'    => [0, 0],
        ]
      ]
    ];
    return civicrm_api3_create_success($result, $params, 'Contribution', 'GetODDStats');
  }
  switch ($params['granularity'] ?? '') {
  case 'year':
    $sql_date_format = 'DATE_FORMAT(receive_date, "%Y")';
    $sql_display_format = $sql_date_format;
    break;

  case 'quarter':
    $sql_date_format = 'CONCAT(YEAR(receive_date), QUARTER(receive_date))';
    $sql_display_format = 'CONCAT("Q", QUARTER(receive_date) , " ", YEAR(receive_date))';
    break;

  case 'week':
    $sql_date_format = 'DATE_FORMAT(receive_date, "%x-%v")';
    $sql_display_format = 'DATE_FORMAT(receive_date, "W%x %v")';
    break;

  case 'day':
    $sql_date_format = 'DATE_FORMAT(receive_date, "%Y-%m-%d")';
    #$sql_display_format = 'DATE_FORMAT(receive_date, "%e %M %Y")';
    $sql_display_format = $sql_date_format;
    break;

  case 'month':
  default:
    $sql_date_format = 'DATE_FORMAT(receive_date, "%Y%m")';
    $sql_display_format = 'DATE_FORMAT(receive_date, "%M %Y")';

  }

  require_once 'CRM/Core/BAO/CustomField.php';
  $id = CRM_Core_BAO_CustomField::getCustomFieldID('od_project', 'od_project_group');
  list($table_name, $field_name) = CRM_Core_BAO_CustomField::getTableColumnGroup($id);

  $date_from_sql = '';
  if (!empty($params['date_from'])) {
    $_ = strtotime($params['date_from']);
    if ($_) {
      $date_from_sql = 'AND receive_date >= "' . date('Y-m-d', $_) . '"';
    }
  }
  $date_to_sql = '';
  if (!empty($params['date_to'])) {
    $_ = strtotime($params['date_to']);
    if ($_) {
      $date_to_sql = 'AND receive_date <= "' . date('Y-m-d', $_) . '"';
    }
  }

  $first_contrib = '';
  if ($params['first_only'] ?? FALSE) {
    // We are only interested in the first contribution for any recurring contribution.
    $first_contrib = 'AND (cc.contribution_recur_id IS NULL
      OR NOT EXISTS (
        SELECT id FROM civicrm_contribution cc_first
        WHERE cc.contribution_recur_id = cc_first.contribution_recur_id
          AND cc_first.id < cc.id
      ))';
  }

  $sql_params = [];
  $sql = "SELECT
      $sql_display_format period,
      campaign_id,
      proj.`$field_name` project,
      source,
      cc.contribution_recur_id recur,
      SUM(net_amount) amount,
      COUNT(*) contributions
    FROM civicrm_contribution cc
    LEFT JOIN `$table_name` proj ON proj.entity_id = cc.id
    WHERE is_test = 0 $date_from_sql $date_to_sql $first_contrib
    GROUP BY $sql_date_format DESC, campaign_id, proj.`$field_name`, source
    ORDER BY $sql_date_format DESC
  ";
  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
  $campaign_ids = [];
  // Normalise the sources data.
  $unique_sources = [];

  while ($dao->fetch()) {
    if ($dao->campaign_id) {
      $campaign_ids[$dao->campaign_id] = FALSE;
    }
    $source = $dao->source ? $dao->source : '(None)';
    if (!isset($unique_sources[$source])) {
      $unique_sources[$source] = count($unique_sources);
    }
    $result[] = [
      $dao->period,              // 0
      $dao->campaign_id,         // 1
      $dao->project,             // 2
      $unique_sources[$source],  // 3
      $dao->recur ? 1 : 0,       // 4
      (double) $dao->amount,     // 5
      (int) $dao->contributions, // 6
    ];
  }
  $dao->free();
  $result = ['contributions' => $result];

  // Now provide a campaign lookup table.
  $campaigns = [];
  if ($campaign_ids) {
    $campaign_fetch = civicrm_api3('Campaign', 'get', [
      'id' => ['IN' => array_keys($campaign_ids)],
      'options' => ['limit' => 0],
      'return' => ['id', 'title'],
    ]);
    foreach ($campaign_fetch['values'] as $_) {
      $campaigns[$_['id']] = $_['title'];
    }
  }
  $result['campaigns'] = $campaigns;


  // Load all completed mailings.
  $mailings = civicrm_api3('Mailing', 'get', [
    'return'       => ["name"],
    'is_completed' => 1,
    'options'      => ['limit' => 0],
  ]);
  $mailing_stats = [];
  $a = ['sources' => $unique_sources];
  foreach ($mailings['values'] as $mailing) {
    $a['maling'] = $mailing;
    if (isset($unique_sources[$mailing['name']])) {
      // This mailing name was also used as a source.
      // Look up mailing stats for this mailing.
      $stats = civicrm_api3('Mailing', 'stats', ['mailing_id' => $mailing['id'], 'sequential' => 1]);
      $mailing_stats[$unique_sources[$mailing['name']]] = $stats['values'];
    }
  }
  // Create the source lookup data.
  $result['sources'] = [];
  foreach ($unique_sources as $name => $idx) {
    $result['sources'][$idx] = [
      'name' => $name,
    ];
    if (isset($mailing_stats[$idx])) {
      $result['sources'][$idx] += $mailing_stats[$idx];
    }
  }

  // Get joiners and leavers from the contrib recur.
  $sql = "SELECT
      $sql_display_format period,
      SUM(amount) amount,
      COUNT(*) people
    FROM civicrm_contribution_recur cr
    WHERE is_test = 0 $date_from_sql $date_to_sql
      AND $sql_display_format IS NOT NULL AND receive_date IS NOT NULL
    GROUP BY $sql_date_format DESC
    ORDER BY $sql_date_format DESC
  ";
  $result['recur'] = [];
  foreach (['create_date', 'start_date', 'cancel_date', 'end_date'] as $field) {
    $sql1 = str_replace('receive_date', $field, $sql);
    $dao = CRM_Core_DAO::executeQuery($sql1, $sql_params);
    while ($dao->fetch()) {
      if (!isset($result['recur'][$dao->period])) {
        $result['recur'][$dao->period] = [
          'period' => $dao->period,
          'create_date' => [0, 0],
          'start_date'  => [0, 0],
          'cancel_date' => [0, 0],
          'end_date'    => [0, 0],
        ];
      }
      $result['recur'][$dao->period][$field] = [(double)$dao->amount, (int)$dao->people];
    }
  }
  $result['recur'] = array_values($result['recur']);


  return civicrm_api3_create_success($result, $params, 'Contribution', 'GetODDStats');
  //throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
}
