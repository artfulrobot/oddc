<?php
use CRM_Oddc_ExtensionUtil as E;

class CRM_Oddc_Page_MailingConversion extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Mailing Conversion'));

    $from = strtotime('today - 6 months');
    $to = date('Ymd');

    if (!empty($_GET['from'])) {
      $from = strtotime($_GET['from']);
      if (!$from) {
        // error @todo
        return;
      }
    }
    $fromHuman = date('j F Y', $from);
    $from = date('Ymd', $from);

    // mailing.id=773 is interesting: it includes 3 people who did not receive the mailing but who did donate!
    //
    // Calculating delivery is complicated:
    // - you can count the delivery events
    // - but you then have to remove the bounce events, since some bounces can come later.
    //
    // - also, there's the option of is_distinct on the queue id in CRM_Mailing_Event_BAO_Delivered which GROUPs BY it - unclear why!
    // - also the CRM_Mailing_Event_BAO_Delivered uselessly joins the mailing table and uses left join for bounces which is probably inefficient.
    //
    // - in the end went back to using our own SQL for this.
    $sql = '
SELECT mailing.id,
  MIN(mailing.approval_date) mailingDate,
  MIN(mailing.name) mailingName,
  COUNT(DISTINCT queue.id) recipients,
  COUNT(DISTINCT contribution.contact_id) contactConversions,
  COUNT(DISTINCT contribution.id) * 100 / COUNT(DISTINCT queue.id) contactConversionRate,
  COUNT(contribution.id) contributionConversions,
  COUNT(contribution.id) * 100 / COUNT(DISTINCT queue.id) contributionConversionRate,
  SUM(contribution.total_amount) totalRaised
FROM civicrm_mailing mailing
INNER JOIN civicrm_mailing_job job ON mailing.id = job.mailing_id AND job.is_test = 0
INNER JOIN civicrm_mailing_event_queue queue ON job.id = queue.job_id
INNER JOIN civicrm_mailing_event_delivered delivered ON queue.id = delivered.event_queue_id
LEFT JOIN civicrm_contribution contribution ON queue.contact_id = contribution.contact_id
  AND contribution.contribution_status_id = 1
  AND contribution.is_test = 0
  AND contribution.source = CONCAT("mailing", mailing.id)
WHERE mailing.approval_date > %1
  AND NOT EXISTS (
    SELECT queue.id
    FROM civicrm_mailing_event_bounce bounce
    WHERE bounce.event_queue_id = queue.id
  )
GROUP BY mailing.id
ORDER BY mailing.approval_date DESC';

    $results = CRM_Core_DAO::executeQuery($sql, [1 => [$from, 'String']])->fetchAll();
    if (0) foreach ($results as &$row) {
      $stats = civicrm_api3('Mailing', 'stats', ['mailing_id' => $row['id']])['values'][$row['id']] ?? [];
      /* are these cached? NO THEY ARE NOT! and it's hideously slow.
        ⬦ $stats['Delivered'] = (string [4]) `2830`
        ⬦ $stats['Bounces'] = (string [1]) `1`
        ⬦ $stats['Unsubscribers'] = (string [1]) `5`
        ⬦ $stats['Unique Clicks'] = (string [3]) `191`
        ⬦ $stats['Opened'] = (string [4]) `2422`
        ⬦ $stats['clickthrough_rate'] = (string [5]) `6.75%`
        ⬦ $stats['opened_rate'] = (string [6]) `85.58%`
        ⬦ $stats['delivered_rate'] = (string [6]) `99.96%`
       */
      if (!$stats) {
        $row += [
          'recipients' => 0,
          'contactConversionRate' => 0,
          'contributionConversionRate' => 0,
        ];
      }
      else {
        $_ = (int) $stats['Delivered'];
        $row += [
          'recipients' => $_,
          'contributionConversionRate' => $row['contributionConversions'] * 100 / $_,
          'contactConversionRate' => $row['contactConversions'] * 100 / $_,
        ];
      }
    }

    if (!empty($_GET['csv'])) {
      $csv = '';
      $row = [];
      $fields = array_keys($results[0]);
      // Remove ID
      array_shift($fields);
      foreach ($fields as $_) {
        $row[] = '"' . $_ . '"';
      }
      $csv .= implode(',', $row) . "\r\n";

      // Remove date, descr.
      array_shift($fields);
      array_shift($fields);
      foreach ($results as $mailing) {
        $row = ["\"$mailing[mailingDate]\"", "\"$mailing[mailingName]\""];
        foreach ($fields as $_) {
          $row[] = $mailing[$_];
        }
        $csv .= implode(',', $row) . "\r\n";
      }

      header("Content-Length: " . strlen($csv));
      header("Content-Type: text/csv");
      header('Content-Disposition: attachment; filename=MailingConversionStats.csv');
      header('Content-Description: File Transfer');
      header('Expires: 0');
      print $csv;
      exit;
    }

    foreach ($results as &$row) {
      $row['dateHuman'] = date('j M Y H:i', strtotime($row['mailingDate']));
      $row['contributionConversionRate'] = number_format($row['contributionConversionRate'], 2);
      $row['contactConversionRate'] = number_format($row['contactConversionRate'], 2);
    }
    unset($row);

    $this->assign('statsJson', json_encode($results));
    $this->assign('fromHuman', $fromHuman);
    $this->assign('downloadUrl', '?' . http_build_query(array_merge(['csv' => 1], $_GET)));
    parent::run();
  }

}
