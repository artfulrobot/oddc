<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Oddashboard.Updateconfig API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_oddashboard_Updateconfig_spec(&$spec) {
  //$spec['magicword']['api.required'] = 1;
}

/**
 * Oddashboard.Updateconfig API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_oddashboard_Updateconfig($params) {

  $config = json_decode(Civi::settings()->get('oddc_dashboards'), TRUE);
  //, json_encode(['mailingLists' => [5, 6, 7,8, 9, 10, 11, 12, 13, 14, 16, 17, 18 ]]));
  if (isset($params['mailingLists'])) {
    $selected = [];
    $dash = new CRM_Oddc_Page_EmailDashboard();
    $all_lists = $dash->getAllMailingLists();
    foreach ($params['mailingLists'] as $list_id) {
      $list_id = (int) $list_id;
      if ($list_id && isset($all_lists[$list_id])) {
        $selected []= $list_id;
      }
    }
    if (count($selected) != count($params['mailingLists'])) {
      throw new API_Exception('Invalid input for mailingLists');
    }
    // OK, save that now.
    $config['mailingLists'] = $selected;
    Civi::settings()->set('oddc_dashboards', json_encode($config));

    return civicrm_api3_create_success($config, $params, 'OdDashboard', 'UpdateConfig');
  }
  throw new API_Exception('Invalid updateconfig params');
}
