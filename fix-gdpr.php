#!/usr/bin/php
<?php
exit; // Already performed.
if (php_sapi_name() !== 'cli') {
  // Fail with 404 if not called from CLI.
  if (isset($_SERVER['HTTP_PROTOCOL'])) {
    header("$_SERVER[HTTP_PROTOCOL] 404 Not Found");
  }
  exit;
}
// Note: options MUST be given before positional parameters.

function die_with_help($error=NULL) {
  global $argv;
  if ($error !== NULL) {
    fwrite(STDERR, "$error\n");
  }
  echo <<<TXT
Usage: $argv[0] [-h]

TXT;
  exit(1);
}

$optind = null;
// getopt format:
//
// - single letter on its own is a boolean
// - follow with : for a required value
// - follow with :: for an optional value
//
// Test for an option with isset()
// @see http://php.net/getopt
$options = getopt('u::p::s');
$optind = 1 + count($options);
$pos_args = array_slice($argv, $optind);

/* Require 2 or 3 positional arguments.
if (count($pos_args) <2 || count($pos_args)>3) {
  die_with_help("Wrong arguments.");
}
 */

// These things are typically needed.
$_SERVER["SCRIPT_FILENAME"] = __FILE__;
$_SERVER["REMOTE_ADDR"] = '127.0.0.1';
$_SERVER["SERVER_SOFTWARE"] = NULL;
$_SERVER["REQUEST_METHOD"] = 'GET';
$_SERVER["SCRIPT_NAME"] = __FILE__;

// Boostrap drupal
define('DRUPAL_ROOT', '/var/www/support.opendemocracy.net');
chdir(DRUPAL_ROOT); // This seems to be required.
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);


// Bootstrap CiviCRM.
$GLOBALS['_CV'] = array ();
define("CIVICRM_SETTINGS_PATH", DRUPAL_ROOT . '/sites/default/civicrm.settings.php');
$error = @include_once CIVICRM_SETTINGS_PATH;
if ($error == FALSE) {
  throw new \Exception("Could not load the CiviCRM settings file: {$settings}");
}
require_once $GLOBALS["civicrm_root"] . "/CRM/Core/ClassLoader.php";
\CRM_Core_ClassLoader::singleton()->register();\CRM_Core_Config::singleton();\CRM_Utils_System::loadBootStrap(array(), FALSE);

if (!civicrm_initialize()) {
  die_with_help("Failed to initialise civi.");
  exit;
}


echo "Ok, ready to start\n";

// Load all the contact records.
$sql = "SELECT id, hasGDPRConsent, GDPRConsentDate, GDPRConsentSource FROM civicrm_contact WHERE hasGDPRConsent = 1";
$dao = CRM_Core_DAO::executeQuery($sql);
$done = 0;
while ($dao->fetch()) {
  if (!$dao->GDPRConsentDate || $dao->GDPRConsentDate === '0000-00-00') {
    print "Skipped $dao->id\n";
  }
  $params = [
    'source_contact_id'  => $dao->id,
    'target_id'          => $dao->id,
    'activity_type_id'   => "marketing_consent",
    'subject'            => "Consent imported from previous CiviCRM field",
    'details'            => '<p>Source was given as:</p><p>' . htmlspecialchars($dao->GDPRConsentSource) . '</p>',
    'status_id'          => 'Completed',
    'activity_date_time' => $dao->GDPRConsentDate,
  ];
  $result = civicrm_api3('activity', 'create', $params);
  if ($result['is_error']) {
    print "failed $dao->id\n" . json_encode($result);
    print "\n";
  }
  $done++;
  if (($done % 100) === 0) {
    print "done $done\n";
  }
  //print "done $dao->id\n" ;
}
print "done $done\n";
