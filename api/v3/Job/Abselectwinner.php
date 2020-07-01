<?php
use CRM_Oddc_ExtensionUtil as E;

/**
 * Job.Abselectwinner API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_Abselectwinner_spec(&$spec) {
 //  $spec['magicword']['api.required'] = 1;
}

/**
 * Job.Abselectwinner API
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
function civicrm_api3_job_Abselectwinner($params) {

  // Loop donation page ABs that don't have a winner yet.
  // This uses db_query from drupal:
  $abs = db_query("SELECT * FROM od_donation_page_ab WHERE winner = ''");
  $apiOutput = [];
  foreach ($abs->fetchAllAssoc('id') as $ab) {
    $ab = (array) $ab;

    // Get stats.
    $stats = CRM_Oddc::getAbStats([$ab['nid_a'], $ab['nid_b']], $ab['start_timestamp']);

    /* Do something like this to test it.
    $ab['count_a'] = 500;
    $ab['count_b'] = 500;
    $stats['B']['count'] = 50;
    $stats['B']['sum'] = 5000;
    $stats['A']['count'] = 100;
    $stats['A']['sum'] = 100;
    // */

    $updates = [
      ':id'    => $ab['id'],
      ':stats' => json_encode($stats),
    ];
    $theWinnerIfReady = '';

    // Do we have enough to select a winner?
    // We need at least 1000 people to have gone through.
    $minSample = 1000;
    $minContribsDiff = 10;

    if (($ab['count_a'] + $ab['count_b']) >= $minSample) {
      // And we need at least some donations(!)
      if (abs($stats['A']['count'] - $stats['B']['count']) >= $minContribsDiff) {
        if ($stats['A']['sum'] > $stats['B']['sum']) {
          $updates[':winner'] = 'A';
        }
        elseif ($stats['B']['sum'] > $stats['A']['sum']) {
          $updates[':winner'] = 'B';
        }
        // else: they're 50:50, don't select yet.
      }
    }
    if (isset($updates[':winner'])) {
      $theWinnerIfReady = 'log = :log, winner = :winner,';
      $updates[':log'] = ($ab['log'] ? $ab['log'] . "\n" : '')
        . "Auto-selected " . $updates[':winner']
        . " as winner at " . date('H:i d M Y') . "\n";
    }

    $stats = json_encode($stats);
    db_query(
      "UPDATE od_donation_page_ab
       SET $theWinnerIfReady stats_cache = :stats
       WHERE id = :id",
      $updates);

    $apiOutput[] = ['id' => $ab['id'], 'updates: ' => $updates];
  }

  return civicrm_api3_create_success($apiOutput, $params, 'Job', 'Abselectwinner');
}
