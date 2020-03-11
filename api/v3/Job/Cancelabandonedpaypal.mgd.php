<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return array (
  0 => 
  array (
    'name' => 'Cron:Job.Cancelabandonedpaypal',
    'entity' => 'Job',
    'update' => 'never', // Never update this; otherwise it's impossible to deactivate it!
    'params' => 
    array (
      'version' => 3,
      'name' => 'Call Job.Cancelabandonedpaypal API',
      'description' => 'Call Job.Cancelabandonedpaypal API',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'Cancelabandonedpaypal',
      'parameters' => '',
    ),
  ),
);
