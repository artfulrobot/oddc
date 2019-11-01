<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Contact.Tokentest API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_contact_Tokentest_spec(&$spec) {
  $spec['contact_id']['api.required'] = 1;
  $spec['contact_id']['description'] = 'Contact ID';
  $spec['text'] = [
    'description' => 'Plain text with tokens in for testing.',
  ];
  $spec['html'] = [
    'description' => 'HTML with tokens in for testing.',
  ];
}

/**
 * Contact.Tokentest API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_contact_Tokentest($params) {
  $tp = new \Civi\Token\TokenProcessor(\Civi::dispatcher(), ['controller' => 'none', 'smarty' => FALSE]);
  $parts = [];
  foreach (['text', 'html'] as $_) {
    if ($params[$_]) {
      $tp->addMessage($_, $params[$_], ['text' => 'text/plain', 'html' => 'text/html'][$_]);
      $parts[] = $_;
    }
  }
  if (!$parts) {
    throw new API_Exception('You must provide at least one of: text, html.');
  }
  $row = $tp->addRow()->context('contactId', $params['contact_id']);

  $tp->evaluate();
  $returnValues = [];
  foreach ($parts as $_) {
    $returnValues[$_] = $row->render($_);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'Tokentest');
}
