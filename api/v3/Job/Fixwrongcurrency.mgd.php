<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return array (
  0 => 
  array (
    'name' => 'Cron:Job.Fixwrongcurrency',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Correct total amount for Contributions received in foreign currency',
      'description' => 'Call Job.Fixwrongcurrency API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Job',
      'api_action' => 'Fixwrongcurrency',
      'parameters' => '',
    ),
  ),
);
