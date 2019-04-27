<?php
use CRM_Oddc_ExtensionUtil as E;

class CRM_Oddc_Page_EmailDashboard extends CRM_Core_Page {

  /** caches **/
  protected $all_lists;
  protected $selected_list_ids;

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('EmailDashboard'));

    // Example: Assign a variable for use in a template
    //$this->assign('currentTime', date('Y-m-d H:i:s'));


    $this->assign('currentTotalUniqueSubscribers', $this->getCurrentTotalUniqueSubscribers());
    $this->assign('selectedListCounts', $this->getSelectedListCounts());
    $this->assign('allLists', $this->getAllMailingLists());

    parent::run();
  }
  /**
   * Count unique non-deleted contacts in selected mailing lists.
   *
   * @return int
   */
  public function getCurrentTotalUniqueSubscribers() {
    $selected_list_ids = implode(',', $this->getSelectedLists());
    if (!$selected_list_ids) {
      return 0;
    }

    $sql = "SELECT COUNT(distinct gc.contact_id) FROM civicrm_group_contact gc
      WHERE gc.group_id IN ($selected_list_ids) AND status = 'Added' AND gc.contact_id NOT IN (
        SELECT id FROM civicrm_contact WHERE is_deleted = 1);";

    $unique_contacts = (int) CRM_Core_DAO::executeQuery($sql)->fetchValue();
    return number_format($unique_contacts);
  }

  /**
   * Get count of members in each of the selected mailing lists.
   *
   * @return array of group IDs => [ 'title' => ..., 'count' => ...]
   */
  public function getSelectedListCounts() {

    $selected_lists = [];
    $groups = $this->getAllMailingLists();

    foreach ($this->getSelectedLists() as $group_id) {
      $selected_lists[$group_id] = [
        'title' => $groups[$group_id]['title'],
        'count' => CRM_Contact_BAO_Group::memberCount($group_id),
      ];
    }

    return $selected_lists;
  }


  /**
   * Get array of integer group IDs.
   *
   * @return array of ints.
   */
  public function getSelectedLists() {
    if (!isset($this->selected_list_ids)) {
      $this->selected_list_ids = array_filter(array_map(function ($x) { return ($x > 0) ? (int) $x : 0; }, $this->getConfig('mailingLists', [])));
    }
    return $this->selected_list_ids;
  }
  /**
   * Get all active CiviCRM mailing lists
   *
   * @return array of group IDs => ['title' => 'foo']
   */
  public function getAllMailingLists() {
    if (!isset($this->all_lists)) {

      $selected = $this->getSelectedLists();

      $result = civicrm_api3('Group', 'get', [
        'return'     => ["title"],
        'group_type' => "Mailing List",
        'is_active'  => 1,
        'is_hidden'  => 0,
      ]);
      $this->all_lists = [];
      foreach ($result['values'] as $id => $_) {
        $this->all_lists[$id] = $_ + ['selected' => in_array($id, $selected)];
      }
    }
    return $this->all_lists;
  }

  /**
   * Get config.
   *
   * @param NULL|string sub-setting to return.
   */
  public function getConfig($key=NULL, $default=NULL) {

    $settings = [];

    //$json_encoded = Civi::settings()->set('oddc_dashboards', json_encode(['mailingLists' => [5, 6, 7,8, 9, 10, 11, 12, 13, 14, 16, 17, 18 ]]));

    $json_encoded = Civi::settings()->get('oddc_dashboards');
    if ($json_encoded) {
      $settings = json_decode($json_encoded, TRUE);
    }

    if ($key) {
      return $settings[$key] ?? $default;
    }

    return $settings;
  }

}
