CREATE TABLE IF NOT EXISTS civicrm_oddc_cache (
  `key` VARCHAR(255) NOT NULL PRIMARY KEY,
  `value` VARCHAR(2048) NOT NULL,
  `expires` TIMESTAMP NOT NULL DEFAULT 0
);


