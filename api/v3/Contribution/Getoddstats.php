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
  $spec['date_from']   = ['description' => 'Optional earliest date (not used by the report)'];                                                                                          
  $spec['date_to']     = ['description' => 'Optional latest date (not used by the report)'];                                                                                            
  $spec['granularity'] = ['description' => 'Date granularity (not used by the report - day used only)', 'options' => ['day', 'week', 'month', 'quarter', 'year'], 'default' => 'week']; 
}

/**
 * Contribution.Getoddstats API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_Getoddstats($params) {
  $result = [ ];
  $t = microtime(TRUE);

  if (!empty($params['test'])) {
    $result = [
      'contributions' => [
        // 0            1          2          3          4         5     6       7
        // period       campaign,  project    source_idx recur     nid   amount, contribs
        // Couple of one-offs.
        [ '2017-01-01', 1,        'Project A', 0,       'one-off', 1234, 10,     1 ],
        [ '2018-01-01', 2,        'Project B', 1,       'one-off', 1234, 10,     1 ],
        // Some regulars
        [ '2017-02-01', 1,        'Project B', 1,       'first',   1234, 10,     1 ],
        [ '2017-03-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-04-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-05-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-06-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-07-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-08-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-09-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        [ '2017-10-01', 1,        'Project B', 1,       'repeat',  1234, 10,     1 ],
        // Another regular
        [ '2017-06-01', 2,        'Project A', 2,       'first',   4567, 30,     2 ],
        [ '2018-01-01', 2,        'Project A', 2,       'repeat',  4567, 30,     2 ],
        [ '2019-01-01', 2,        'Project A', 2,       'repeat',  4567, 30,     2 ],
      ],
      'sources' => [
        [ 'name' => 'None'],
        [ 'name'=> 'Source A' ],
        [ 'name'=> 'Email A', 'opened_rate'=>"88.23%", 'clickthrough_rate'=> "2.56%", 'Delivered'=> "23414" ],
        [ 'name'=> 'Email B', 'opened_rate'=>"90.12%", 'clickthrough_rate'=> "10.56%", 'Delivered'=> "12323" ],
      ],
      'donation_pages' => [
        1234 => 'Test donation page',
        4567 => 'Other donation page',
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
  list($table_name, $project_field_name) = CRM_Core_BAO_CustomField::getTableColumnGroup($id);
  $id = CRM_Core_BAO_CustomField::getCustomFieldID('donation_page_nid', 'od_project_group');
  list($table_name, $donation_page_nid_field_name) = CRM_Core_BAO_CustomField::getTableColumnGroup($id);

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
  $completed_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');

  $sql = "SELECT
      $sql_display_format period,
      campaign_id,
      proj.`$project_field_name` project,
      proj.`$donation_page_nid_field_name` donation_page_nid,
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
    WHERE is_test = 0
          AND cc.contribution_status_id = $completed_status
          $date_from_sql
          $date_to_sql
    GROUP BY $sql_date_format DESC, campaign_id, proj.`$project_field_name`, donation_page_nid, source, recur
  ";
  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
  $campaign_ids = [];
  // Normalise the sources data.
  // Ensure the 0 index-ed item is for None; we know we'll need this.
  $unique_sources = ['(None)' => 0];

  $unique_pages = [];
  while ($dao->fetch()) {
    if ($dao->campaign_id) {
      $campaign_ids[$dao->campaign_id] = FALSE;
    }
    $source = $dao->source ? $dao->source : '(None)';
    if (!isset($unique_sources[$source])) {
      $unique_sources[$source] = count($unique_sources);
    }

    $nid = $dao->donation_page_nid ?? 0;
    $unique_pages[$nid] = 1;

    $result[] = [
      $dao->period,              // 0
      $dao->campaign_id,         // 1
      $dao->project,             // 2
      $unique_sources[$source],  // 3
      $dao->recur,               // 4
      $nid,                      // 5
      (double) $dao->amount,     // 6
      (int) $dao->contributions, // 7
    ];
  }
  $dao->free();
  $result = ['contributions' => $result];
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load main data SQL');
  $t = microtime(TRUE);

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
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load campaigns');
  $t = microtime(TRUE);

  // Add internal name of donation pages (Drupal query.)
  $result['donation_pages'] = [];
  if ($unique_pages) {
    $_ = db_query(
      'SELECT entity_id nid, field_internal_name_value title FROM field_data_field_internal_name WHERE entity_id IN (:ids)',
      [':ids' => array_keys($unique_pages)])
      ->fetchAllKeyed();
    foreach ($_ as $nid => $title) {
      $result['donation_pages']["nid$nid"] = $title;
    }
  }
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load donation pages');
  $t = microtime(TRUE);


  // Add Email stats.
  $mailing_ids = [];
  foreach ($unique_sources as $text => $count) {
    if (preg_match('/^mailing(\d+)$/', $text, $matches)) {
      $mailing_ids[$text] = $matches[1];
    }
  }

  /*
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
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load mailings');
  $t = microtime(TRUE);
   */

  // Create the source lookup data.
  $result['sources'] = [];
  foreach ($unique_sources as $name => $idx) {
    // IF $name is a mailing. REMOVED {{{
    if (FALSE && isset($mailing_ids[$name]) && isset($mailings[$mailing_ids[$name]])) {
      // This is a mailing and we have been able to load it's name.
      $mailing_id = (int) $mailing_ids[$name];
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

      $result['sources'][$idx]['unique_opens'] =
        CRM_Core_DAO::executeQuery("SELECT count(distinct q.contact_id)
          FROM civicrm_mailing_event_opened o
          INNER JOIN  civicrm_mailing_event_queue q ON o.event_queue_id = q.id
          INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
          WHERE j.mailing_id = $mailing_id;")->fetchValue();

      // Replace CiviCRM's opened_rate which is not unique opens and therefore pretty useless.
      $result['sources'][$idx]['opened_rate'] = number_format(100 * $result['sources'][$idx]['unique_opens'] / $result['sources'][$idx]['Delivered'], 2) . '%';

    } // }}}
    else {
      // Not a mailing, just output the source name as is.
      $result['sources'][$idx] = [ 'name' => $name, ];
    }
  }
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load source metadata');
  $t = microtime(TRUE);

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
  Civi::log()->info('Took ' . number_format(microtime(TRUE) - $t, 2) . 's to load joiners/leavers');


  return civicrm_api3_create_success($result, $params, 'Contribution', 'GetODDStats');
  //throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
}
