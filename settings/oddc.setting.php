<?php
return [
  'oddc_dashboards' => [
    'name'        => 'oddc_dashboards',
    'title'       => ts('oD custom dashboard settings'),
    'description' => ts('JSON encoded settings.'),
    'group_name'  => 'domain',
    'type'        => 'String',
    'default'     => FALSE,
    'add'         => '5.10',
    'is_domain'   => 1,
    'is_contact'  => 0,
  ],
];
