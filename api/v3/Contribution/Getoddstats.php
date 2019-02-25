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
        // period       campaign,  project    source_idx recur      amount, contribs
        // Couple of one-offs.
        [ '2017-01-01', 1,        'Project A', 0,       'one-off',  10,     1 ],
        [ '2018-01-01', 2,        'Project B', 1,       'one-off',  10,     1 ],
        // Some regulars
        [ '2017-02-01', 1,        'Project B', 1,       'first',    10,     1 ],
        [ '2017-03-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-04-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-05-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-06-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-07-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-08-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-09-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        [ '2017-10-01', 1,        'Project B', 1,       'repeat',   10,     1 ],
        // Another regular
        [ '2017-06-01', 2,        'Project A', 2,       'first',    30,     2 ],
        [ '2018-01-01', 2,        'Project A', 2,       'repeat',   30,     2 ],
        [ '2019-01-01', 2,        'Project A', 2,       'repeat',   30,     2 ],
      ],
      'sources' => [
        [ 'name' => 'None'],
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


  $sql_params = [];
  $sql = "SELECT
      $sql_display_format period,
      campaign_id,
      proj.`$field_name` project,
      source,
      IF(cc.contribution_recur_id IS NULL,
        'one-off',
        IF (EXISTS (
            SELECT id FROM civicrm_contribution cc_first
            WHERE cc.contribution_recur_id = cc_first.contribution_recur_id
              AND cc_first.id < cc.id
          ),
        'repeat',
        'first'
        )) recur,
      SUM(net_amount) amount,
      COUNT(*) contributions
    FROM civicrm_contribution cc
    LEFT JOIN `$table_name` proj ON proj.entity_id = cc.id
    WHERE is_test = 0 $date_from_sql $date_to_sql
    GROUP BY $sql_date_format DESC, campaign_id, proj.`$field_name`, source, recur
  ";
  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
  $campaign_ids = [];
  // Normalise the sources data.
  // Ensure the 0 index-ed item is for None; we know we'll need this.
  $unique_sources = ['(None)' => 0];

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
      $dao->recur,               // 4
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


  // Add Email stats.
  $mailing_ids = [];
  foreach ($unique_sources as $text => $count) {
    if (preg_match('/^mailing(\d+)$/', $text, $matches)) {
      $mailing_ids[$text] = $matches[1];
    }
  }

  // Load all mailings found in sources.
  $mailings = [];
  if ($mailing_ids) {
    $mailings = civicrm_api3('Mailing', 'get', [
      'return'       => ["name"],
      'id'           => ['IN' => array_values($mailing_ids)],
      'options'      => ['limit' => 0],
    ]);
    $mailings = $mailings['values'] ?? [];
  }

  // Create the source lookup data.
  $result['sources'] = [];
  foreach ($unique_sources as $name => $idx) {
    // IF $name is a mailing.
    if (isset($mailing_ids[$name]) && isset($mailings[$mailing_ids[$name]])) {
      // This is a mailing and we have been able to load it's name.
      $mailing_id = $mailing_ids[$name];
      $mailing = $mailings[$mailing_id];
      // Make the name a bit nicer.
      $result['sources'][$idx] = [
        'name' => $name . ': ' . $mailing['name'],
      ];
      // Add in the stats.
      $stats = civicrm_api3('Mailing', 'stats', ['mailing_id' => $mailing_id]);
      if (!empty($stats['values'][$mailing_id])) {
        $result['sources'][$idx] += $stats['values'][$mailing_id];
      }
    }
    else {
      // Not a mailing, just output the source name as is.
      $result['sources'][$idx] = [ 'name' => $name, ];
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
