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
  $spec['date_from'] = ['description' => 'Optional earliest date'];
  $spec['date_to'] = ['description' => 'Optional latest date'];
  $spec['granularity'] = ['description' => 'Date granularity', 'options' => ['day', 'week', 'month', 'quarter', 'year'], 'default' => 'week'];
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
      SUM(net_amount) amount,
      COUNT(*) contributions
    FROM civicrm_contribution cc
    LEFT JOIN `$table_name` proj ON proj.entity_id = cc.id
    WHERE is_test = 0 $date_from_sql $date_to_sql
    GROUP BY $sql_date_format DESC, campaign_id, proj.`$field_name`, source
    ORDER BY $sql_date_format DESC
  ";
  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
  $campaign_ids = [];
  while ($dao->fetch()) {
    if ($dao->campaign_id) {
      $campaign_ids[$dao->campaign_id] = FALSE;
    }
    $result[] = [
      $dao->period,
      $dao->campaign_id,
      $dao->project,
      $dao->source,
      (double) $dao->amount,
      (int) $dao->contributions,
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

    // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
  return civicrm_api3_create_success($result, $params, 'Contribution', 'GetODDStats');
    //throw new API_Exception(/*errorMessage*/ 'Everyone knows that the magicword is "sesame"', /*errorCode*/ 1234);
}
