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

  /**
   * Helper function for creating data structures.
   *
   * @param string $entity - name of the API entity.
   * @param Array $params_min parameters to use for search.
   * @param Array $params_extra these plus $params_min are used if a create call
   *              is needed.
   */
  $api_get_or_create = function ($entity, $params_min, $params_extra) {
    $params_min += ['sequential' => 1];
    $result = civicrm_api3($entity, 'get', $params_min);
    if (!$result['count']) {
      Civi::log()->notice('get_or_create Could not find entity, creating now', ['entity' => $entity, 'min' => $params_min, 'extra' => $params_extra]);
      // Couldn't find it, create it now.
      $result = civicrm_api3($entity, 'create', $params_extra + $params_min);
      // reload
      $result = civicrm_api3($entity, 'get', $params_min);
    }
    else {
      Civi::log()->notice('get_or_create Found entity', ['entity' => $entity, 'min' => $params_min, 'found' => $result['values'][0]]);
    }
    return $result['values'][0];
  };

  // Ensure we have the marketing_consent activity type.
  $consent_activity = $api_get_or_create('OptionValue',
    [ 'option_group_id' => "activity_type", 'name' => "marketing_consent" ],
    [
      'label'           => "Consent",
      'description'     => "Records a log of this contact having given marketing consent to help comply with the GDPR.",
    ]);


  // Ensure we have the custom field group we need for project.
  $contribution_custom_group = $api_get_or_create('CustomGroup', [
    'name' => "od_project_group",
    'extends' => "Contribution",
  ],
  ['title' => 'oD Project']);

  // Add our 'Project' field.
  // ...This is a drop-down select field, first we need to check the option
  //    group exists, and its values.
  $opts_group = $api_get_or_create('OptionGroup',
    ['name' => 'od_project_opts'],
    ['title' => 'oD Project', 'is_active' => 1]);
  $weight = 0;
  foreach ([
    "50.50"                          => "50.50",
    "Beyond Trafficking and Slavery" => "Beyond Trafficking and Slavery",
    "Can Europe Make It?"            => "Can Europe Make It?",
    "Dark Money Investigations"      => "Dark Money Investigations",
    "democraciaAbierta"              => "democraciaAbierta",
    "DigitaLiberties"                => "DigitaLiberties",
    "North Africa, West Asia"        => "North Africa, West Asia",
    "oDR"                            => "oDR",
    "openDemocracyUK"                => "openDemocracyUK",
    "openJustice"                    => "openJustice",
    "openMedia"                      => "openMedia",
    "openMigration"                  => "openMigration",
    "ourBeeb"                        => "ourBeeb",
    "ourNHS"                         => "ourNHS",
    "Shine a Light"                  => "Shine a Light",
    "Transformation"                 => "Transformation",
  ] as $name => $label) {
    $api_get_or_create('OptionValue',
      [ 'option_group_id' => "od_project_opts", 'name' => $name, ],
      [ 'label' => $label, 'value' => $name, 'weight' => $weight++ ]);
  }

  // ... Now we can add the Project field to the custom group for contributions.
  $project = $api_get_or_create('CustomField', [
    'name' => "od_project",
    'custom_group_id' => $contribution_custom_group['id'],
    'data_type' => "String",
    'html_type' => "Select",
    'is_required' => "1",
    'is_searchable' => "1",
    'default_value' => "unknown",
    'text_length' => "30",
    'option_group_id' => $opts_group['id'],
  ],
  ['label' => 'oD Project']);

  // ... we also need to add donation_page_nid field. @todo
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
 * Uses hook_civicrm_post to update campaign targets after saving a contribution.
 */
function oddc_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($op === 'view' || $objectName !== 'Contribution') {
    return;
  }
  // Contribution has been changed. Update the stats.
  CRM_Oddc::factory()->updateCampaignTargetStats();
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
function oddc__complete_go_cardless_redirect_url($input) {
  return CRM_Oddc::factory()->completeGoCardlessRedirectFlow($input);
}
/**
 * User has successfully made a payment and clicked Return To Merchant on the
 * PayPal page.
 *
 * @input array $input (POST data)
 */
function oddc__complete_paypal($input) {
  return CRM_Oddc::factory()->completePayPal($input);
}
/**
 * Augment the app config array with details loaded from a contact's record, if
 * the checksum is valid.
 */
function oddc__add_contact_data_from_checksum(&$odd_app_config, $cid, $cs) {

  // Set default empty giving string.
  $odd_app_config['giving'] = '';

  $cid = (int) $cid;
  if (!$cid>0) {
    // Suspicious.
    return;
  }
  if (!CRM_Contact_BAO_Contact_Utils::validChecksum($cid, $cs)) {
    // Invalid checksum.
    return;
  }
  // Checksum is, load contact details.
  $fields = ["email", "first_name", 'last_name', "street_address", "city", "postal_code", "country_id"];
  $contact = civicrm_api3('Contact', 'getsingle', ['id' => $cid, 'return' =>  $fields]);

  // We need an ISO 3166-1 alpha-2 version of the country, not the CiviCRM country ID.
  if (!empty($contact['country_id'])) {
    $contact['country'] = CRM_Core_PseudoConstant::countryIsoCode($contact['country_id']);
  }

  // remove country_id, add country.
  array_pop($fields);
  $fields[] = 'country';

  // Look up current giving
  $odd_app_config['giving'] = CRM_Oddc::factory()->getCurrentRegularGivingDescription($cid);

  // Copy any data we have into the app config.
  foreach ($fields as $_) {
    if (!empty($contact[$_])) {
      $odd_app_config[$_] = $contact[$_];
    }
  }
}
/** Augment the app config array with details loaded campaign funding.
 *
 * @param Array $odd_app_config
 * @param String $campaign_name
 */
function oddc__add_campaign_funding_data(&$odd_app_config, $campaign_name) {
  $result = civicrm_api3('Campaign', 'get', [
    'sequential' => 1,
    'return'     => [CRM_Oddc::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_TARGET, CRM_Oddc::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD],
    'name'       => $campaign_name,
  ]);
  $odd_app_config['campaign_target'] = (int) ($result['values'][0][CRM_Oddc::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_TARGET] ?? 0);
  $odd_app_config['campaign_total']  = (int) ($result['values'][0][CRM_Oddc::API_CUSTOM_FIELD_CAMPAIGN_FUNDING_RCVD] ?? 0);
}
/**
 * Return a map of countries.
 */
function oddc__get_country_list() {
  $result = civicrm_api3('Country', 'get', ['return' => ["iso_code", "name"], 'options' => ['limit' => 0, 'sort' => 'name']]);
  $list = [];
  foreach ($result['values'] as $_) {
    $list[$_['iso_code']] = $_['name'];
  }
  return $list;
}
/**
 * Implements hook_civicrm_alterMailParams
 *
 * Used to abort sending payment receipt for odd Donation Pages.
 *
 * $params['abortMailSend'] = TRUE;
 *
 * @param array &$params
 * @param array $context
 */
function oddc_civicrm_alterMailParams(&$params, $context) {

  if ( ($params['groupName'] ?? '') === 'msg_tpl_workflow_contribution'
    && ($params['valueName'] ?? '') === 'contribution_online_receipt'
    && CRM_Utils_Rule::positiveInteger($params['tplParams']['contributionID'] ?? 0)
  ) {

    // Civi::log()->debug("alterMailParams", ['params' => $params, 'context' => $context]);

    // OK, looks like one we want to stop.
    // Stop it IF it's the first contribution of a recurring one, or it's a one off.
    // We also need to watch out for old style contribution forms which we should leave alone.
    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'return' => ['contribution_page_id', 'contribution_recur_id', 'receive_date'],
      'id'     => $params['tplParams']['contributionID'],
    ]);

    if (!empty($contribution['contribution_page_id'])) {
      // Old style contribution page, leave it alone.
      //Civi::log()->notice(__FUNCTION__ . ' Not altering: belongs to a contribution page.', []);
      return;
    }

    if (!empty($contribution['contribution_recur_id'])) {
      // OK this is a recurring contribution. Is it the first one?
      // it is first, if there are no other contributions before this.
      $count = civicrm_api3('Contribution', 'getcount',  [
        'receive_date'          => ['<' => $contribution['receive_date']],
        'contribution_recur_id' => $contribution['contribution_recur_id'],
      ]);
      if ($count) {
        // This is not the first, leave it alone.
        // Civi::log()->notice(__FUNCTION__ . ' Not altering: is a repeat recurring donation.', ['count' => $count]);
        return;
      }
    }

    // OK, do not send.
    Civi::log()->notice(__FUNCTION__ . ' Aborting mail - think it was triggered from a Donation Page which will send it\'s own');
    $params['abortMailSend'] = TRUE;
  }
  else {
    // Civi::log()->notice(__FUNCTION__ . ' Not altering: not msg_tpl_workflow_contribution and contribution_online_receipt or no contributionID', []);
  }
}
/**
 * Implements hook_civicrm_unsubscribeGroups()
 *
 * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_unsubscribeGroups/
 */
function oddc_civicrm_unsubscribeGroups($op, $mailingId, $contactId, &$groups, &$baseGroups) {

  // Nb. this hook is caled for GET requests, too, which have yet to be confirmed.
  if ($op === 'unsubscribe' && (($_POST['_qf_Unsubscribe_next'] ?? '') === 'Unsubscribe')) {
    try {
      Civi::log()->info(__FUNCTION__ . " inputs", ['contactId' => $contactId, 'mailingId' => $mailingId, 'groups' => $groups, 'baseGroups' => $baseGroups]);
      $mailing = civicrm_api3('Mailing', 'getsingle', [
        'id' => $mailingId,
        'return' => ["campaign_id.title"],
      ]);
      if (strpos(($mailing['campaign_id.title'] ?? ''), 'FundraisingSend') !== FALSE) {
        // Tag this person.
        Civi::log()->info(__FUNCTION__ . " will tag Contact with 'No fundraising emails' because they unsubscribed from mailing",
          ['contactId' => $contactId, 'mailingId' => $mailingId, 'mailing' => $mailing]);
				$result = civicrm_api3('EntityTag', 'getcount', [
					'entity_table' => "civicrm_contact",
					'entity_id'    => $contactId,
					'tag_id'       => "No fundraising emails",
				]);
        if ($result == 0) {
          Civi::log()->info(__FUNCTION__ . " tagging...");
          civicrm_api3('EntityTag', 'create', [
            'entity_table' => "civicrm_contact",
            'entity_id'    => $contactId,
            'tag_id'       => "No fundraising emails",
          ]);
        }
        else {
          Civi::log()->info(__FUNCTION__ . " already tagged");
        }
      }
      else {
        // Debugging.
        // Civi::log()->info(__FUNCTION__ . " will NOT tag contact with 'No fundraising emails' because mailing campaign does not include FundraisingSend", ['contactId' => $contactId, 'mailingId' => $mailingId, 'mailing' => $mailing]);
      }
    }
    catch (\Exception $e) {
      Civi::log()->warning(__FUNCTION__ . " failed to find mailing/tag contact",
        [
          'mailingId' => $mailingId,
          'message' => $e->getMessage(),
        ]);
    }
  }
}

/**
 * Provide the {currentRegularGiving} token.
 */
function oddc_civicrm_tokens( &$tokens ) {
  $tokens['oD'] = [
    'currentRegularGiving' => E::ts('Current regular giving'),
  ];
}
/**
 * Creates the currentRegularGiving token.
 */
function oddc_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  if (empty($tokens['oD'])) {
    return;
  }

  // $tokens is sometimes like: { 'contact': { 'foo': 1 } } and sometimes like { 'contact': ['foo'] }
  if (is_numeric(key($tokens['oD']))) {
    // We have the 2nd form.
    $tokens_in_use = array_values($tokens['oD']);
  }
  else {
    // tokens are keys.
    $tokens_in_use = array_keys($tokens['oD']);
  }

  $contact_ids = [];
  foreach($cids as $cid) {
    $contact_ids[] = (int) $cid;
  }

  foreach ($contact_ids as $cid) {
    $values[$cid]['oD.currentRegularGiving'] = CRM_Oddc::factory()->getCurrentRegularGivingDescription($cid);
  }
}

