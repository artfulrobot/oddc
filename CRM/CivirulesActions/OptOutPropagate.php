<?php
use Civi\Klaviyo\Glue;

class CRM_CivirulesActions_OptOutPropagate extends CRM_Civirules_Action {
  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactID = (int) $triggerData->getContactId();
    if (!$contactID) {
      Civi::log()->warn("OptOutPropagate action called without contactID");
      // Huh?
      return;
    }

    try {
      $glue = Glue::singleton();
      $groups = $glue->getGroupsToPushToLists();
      if (!$groups) {
        Civi::log()->warn("OptOutPropagate action called on Contact $contactID but we have no to-sync groups.");
        // Nothing to do.
        return;
      }
    }
    catch (\Exception $e) {
      Civi::log()->error($e->getMessage(), ['exception' => $e]);
      return;
    }

    $groupIDs = implode(', ', array_keys($groups));
    $groupsToRemove = CRM_Core_DAO::executeQuery("SELECT group_id FROM civicrm_group_contact WHERE contact_id = $contactID AND status = 'Added' AND group_id IN ($groupIDs);")
      ->fetchMap('group_id', 'group_id');

    if ($groupsToRemove) {
      Civi::log()->notice("OptOutPropagate: Contact $contactID has had is_opt_out or do_not_email set; removing them from groups $groupIDs");
      // This must be a variable since the param is pass by reference.
      $contactIDs = [$contactID];
      foreach ($groupsToRemove as $groupID) {
        CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contactIDs, $groupID, 'OptOut' /* method string */, 'Removed');
      }
    }
    else {
      Civi::log()->notice("OptOutPropagate: Contact $contactID no action required.");
    }
  }

}
