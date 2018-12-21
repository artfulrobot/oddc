<?php

require_once 'oddc.civix.php';
use CRM_Oddc_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function oddc_civicrm_config(&$config) {
  _oddc_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function oddc_civicrm_xmlMenu(&$files) {
  _oddc_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function oddc_civicrm_install() {
  _oddc_civix_civicrm_install();

  // Ensure we have the marketing_consent activity type.
  $consent_activity = civicrm_api3('OptionValue', 'get', [
    'sequential' => 1,
    'option_group_id' => "activity_type",
    'name' => "marketing_consent",
  ]);
  if ($consent_activity['count'] == 0) {
    $consent_activity = civicrm_api3('OptionValue', 'create', [
      'option_group_id' => "activity_type",
      'name'            => "marketing_consent",
      'label'           => "Consent",
      'description'     => "Records a log of this contact having given marketing consent to help comply with the GDPR.",
    ]);
  }
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function oddc_civicrm_postInstall() {
  _oddc_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function oddc_civicrm_uninstall() {
  _oddc_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function oddc_civicrm_enable() {
  _oddc_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function oddc_civicrm_disable() {
  _oddc_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function oddc_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _oddc_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function oddc_civicrm_managed(&$entities) {
  _oddc_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function oddc_civicrm_caseTypes(&$caseTypes) {
  _oddc_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function oddc_civicrm_angularModules(&$angularModules) {
  _oddc_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function oddc_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _oddc_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function oddc_civicrm_entityTypes(&$entityTypes) {
  _oddc_civix_civicrm_entityTypes($entityTypes);
}


/**
 *
 * We pass everything in $input on in the callbacks since we can't guarantee session storage.
 *
 * https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/#technical-variables
 *
 */
function oddc__get_redirect_url($input) {
  return CRM_Oddc::factory()->process($input);
}
/**
 * User has set up mandate at GC, complete this process and set up subscription.
 *
 */
function oddc__complete_redirect_url($input) {
  return CRM_Oddc::factory()->completeRedirectFlow($input);
}
