<?php
use CRM_Oddc_ExtensionUtil as E;

class CRM_Oddc_Page_RevenueDashboard extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('RevenueDashboard'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));

    $stats = civicrm_api3('Contribution', 'getoddstats', ['stats_set' => 2])['values'];

    $this->assign('stats', json_encode($stats));

    parent::run();
  }



}
