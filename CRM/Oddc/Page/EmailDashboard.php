<?php
use CRM_Oddc_ExtensionUtil as E;

class CRM_Oddc_Page_EmailDashboard extends CRM_Core_Page {

  /** caches **/
  protected $all_lists;
  protected $selected_list_ids;

  /** From  GET */
  protected $date_range_type = '';
  protected $date_range_start = '';
  protected $date_range_end = '';
  /** comes from getSubscribersByNumberOfSubscriptions */
  protected $total_unique;

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('EmailDashboard'));

    // Example: Assign a variable for use in a template
    //$this->assign('currentTime', date('Y-m-d H:i:s'));

    // Params on GET data.

    // Store date_range_type and calculate/parse the actual date range to be used.
    $this->date_range_type = $_GET['date_range_type'] ?? 'last_6_months';
    function parseDate($d) {
      if ($d) {
        $t = strtotime($d);
        if ($t!==FALSE) {
          return date('Y-m-d', $t);
        }
      }
      return '';
    }
    switch ($this->date_range_type) {
    case 'last_3_months':
      $this->date_range_start = date('Y-m-d', strtotime('today - 3 months'));
      $this->date_range_end = date('Y-m-d');
      break;
    case 'between':
      // User-specified dates apply.
      $this->date_range_start = parseDate($_GET['date_range_start'] ?? '');
      $this->date_range_end = parseDate($_GET['date_range_end'] ?? '');
      if ($this->date_range_start && $this->date_range_end && $this->date_range_end < $this->date_range_start) {
        // Swap dates.
        $a = $this->date_range_start;
        $this->date_range_start = $this->date_range_end;
        $this->date_range_end = $a;
      }
    case 'last_6_months':
    default:
      $this->date_range_type = 'last_6_months'; // Clarify this if something else had been set.
      $this->date_range_start = date('Y-m-d', strtotime('today - 6 months'));
      $this->date_range_end = date('Y-m-d');
      break;
    }

    $subscriptions = $this->getSubscribersByNumberOfSubscriptions();
    $this->assign('currentTotalUniqueSubscribers', number_format($this->total_unique));
    $this->assign('subscribersByListCount', $subscriptions);
    $active = $this->getActiveSubscribers();
    $this->assign('activeSubscribers', number_format($active));
    $this->assign('activeSubscribersPc', number_format($active*100/$this->total_unique, 1));
    $this->assign('selectedListCounts', $this->getSelectedListCounts());
    $this->assign('allLists', $this->getAllMailingLists());
    $this->assign('date_range_type', $this->date_range_type);
    $this->assign('date_range_start', $this->date_range_start ? date('j M Y', strtotime($this->date_range_start)) : '');
    $this->assign('date_range_end', $this->date_range_end ? date('j M Y', strtotime($this->date_range_end)) : '');
    $data = $this->getMailingsData();
    $this->assign('mailings', $data['mailings']);
    $this->assign('mailingsMaxes', json_encode($data['maxes']));

    parent::run();
  }
  /**
   * Count unique non-deleted contacts in selected mailing lists who have opened something in last 6 months...
   *
   * @return int
   */
  public function getActiveSubscribers() {
    $selected_list_ids = implode(',', $this->getSelectedLists());
    if (!$selected_list_ids) {
      return 0;
    }

    $sql = "SELECT COUNT(distinct gc.contact_id) FROM civicrm_group_contact gc
      WHERE gc.group_id IN ($selected_list_ids) AND status = 'Added'
        AND gc.contact_id NOT IN (SELECT id FROM civicrm_contact WHERE is_deleted = 1)
        AND gc.contact_id IN (
          SELECT DISTINCT eq.contact_id
          FROM civicrm_mailing_event_opened eo
          INNER JOIN civicrm_mailing_event_queue eq ON eo.event_queue_id = eq.id
          WHERE eo.time_stamp > NOW() - INTERVAL 6 MONTH
        );";

    $unique_contacts = (int) CRM_Core_DAO::executeQuery($sql)->fetchValue();
    return $unique_contacts;
  }

  /**
   * Count unique non-deleted contacts in selected mailing lists.
   *
   * @return int
   */
  public function getSubscribersByNumberOfSubscriptions() {
    $selected_list_ids = implode(',', $this->getSelectedLists());
    if (!$selected_list_ids) {
      return 0;
    }

    $sql = "SELECT groups, COUNT(contact_id) contacts
      FROM (
        SELECT gc.contact_id, COUNT(distinct gc.group_id) groups
        FROM civicrm_group_contact gc
        WHERE gc.group_id IN ($selected_list_ids)
              AND status = 'Added'
              AND gc.contact_id NOT IN (
                SELECT id FROM civicrm_contact WHERE is_deleted = 1)
        GROUP BY contact_id
        ) d
        GROUP BY groups
        ORDER BY groups
    ;";
    $subscriptions = CRM_Core_DAO::executeQuery($sql)->fetchAll();
    $total = 0;
    foreach ($subscriptions as &$_) {
      $total += $_['contacts'];
      $_['contacts'] = number_format($_['contacts'], 0);
    }
    $subscriptions[] = ['groups' => 'Total', 'contacts' => number_format($total, 0)];
    $this->total_unique = $total;
    return $subscriptions;
  }

  /**
   * Get count of members in each of the selected mailing lists.
   *
   * @return array of group IDs => [ 'title' => ..., 'count' => ...]
   */
  public function getSelectedListCounts() {

    $selected_lists = [];
    $groups = $this->getAllMailingLists();

    foreach ($this->getSelectedLists() as $group_id) {
      $_ = CRM_Contact_BAO_Group::memberCount($group_id);
      $selected_lists[$group_id] = [
        'title'   => $groups[$group_id]['title'],
        'count'   => $_,
        'percent' => number_format(100*$_/$this->total_unique, 1) . '%',
      ];
    }

    return $selected_lists;
  }

  /**
   * Get data for mailings table:
   *
   * - Find all mailings within the date boundaries.
   *
   * - Conversion rate is defined as:
   *   (unique people who made donation) / (total who opened email) × 100%
   *
   * - Conversion rate is needed for one off and regular, and then those added
   *   to give total.
   *
   * - "made donation" is a Completed Contribution.
   *
   * - Also need net amount totals for the above.
   */
  public function getMailingsData() {
    $mailings = $this->getMailingsInDateRange();

    // Don't use CiviCRM's opened_rate from mail stats api which is not unique opens and therefore pretty useless.
    $mailing_ids = implode(',', array_keys($mailings));
    $stats_dao = CRM_Core_DAO::executeQuery(
      "SELECT j.mailing_id, COUNT(DISTINCT q.contact_id) opened, (
        SELECT COUNT(md.id)
        FROM civicrm_mailing_event_delivered md
        INNER JOIN civicrm_mailing_event_queue mdq ON md.event_queue_id = mdq.id
        INNER JOIN civicrm_mailing_job mdj ON mdq.job_id = mdj.id
        WHERE mdj.mailing_id = j.mailing_id
      ) AS delivered
      FROM civicrm_mailing_event_opened o
      INNER JOIN civicrm_mailing_event_queue q ON o.event_queue_id = q.id
      INNER JOIN civicrm_mailing_job j ON q.job_id = j.id
      WHERE j.mailing_id IN ($mailing_ids)
      GROUP BY j.mailing_id
      ;");

    while ($stats_dao->fetch()) {
      $mailings[$stats_dao->mailing_id]['opened'] = $stats_dao->opened ? $stats_dao->opened : 0;
      $mailings[$stats_dao->mailing_id]['delivered'] = $stats_dao->delivered ? $stats_dao->delivered : 0;
      $mailings[$stats_dao->mailing_id]['opened_rate'] = empty($stats_dao->delivered)
        ? ''
        : number_format($stats_dao->opened * 100 / $stats_dao->delivered, 1);
    }
    $stats_dao->free();


    // Now add in conversion rates.
    $sql_params = [];
    $sql = "SELECT
        source,
        IF(cc.contribution_recur_id IS NULL,
          'one-off',
          IF (EXISTS (
              SELECT id FROM civicrm_contribution cc_first
              WHERE cc.contribution_recur_id = cc_first.contribution_recur_id
                AND cc_first.id < cc.id
            ),
          'repeat',
          'first'
          )) recur,
        SUM(net_amount) amount,
        COUNT(DISTINCT cc.contact_id) contributors
      FROM civicrm_contribution cc
      WHERE is_test = 0 AND source REGEXP '^mailing[0-9]+'
      GROUP BY source, recur
    ";
    $stats_dao = CRM_Core_DAO::executeQuery($sql, $sql_params);
    while ($stats_dao->fetch()) {
      // Extract mailing ID.
      if (preg_match('/^mailing(\d+)/', $stats_dao->source, $matches)) {
        $mailing_id = (int) $matches[1];
        if (isset($mailings[$mailing_id])) {
          // This is one of our mailings.
          if ($stats_dao->recur === 'one-off') {
            $key = 'one_off_';
            $amount = $stats_dao->amount ?? 0;
          }
          elseif ($stats_dao->recur === 'first') {
            $key = 'regular_';
            $amount = ($stats_dao->amount ?? 0) * 12; // Assumed monthly
          }
          else {
            // ignore 'repeat' types.
            continue;
          }

          $mailings[$mailing_id][$key . 'people'] = $stats_dao->contributors ?? 0;
          $mailings[$mailing_id][$key . 'amount'] = $amount;
          $mailings[$mailing_id]['total_people'] += $stats_dao->contributors ?? 0;
          $mailings[$mailing_id]['total_amount'] += $amount;
        }
      }
    }
    $stats_dao->free();

    // Tidy up the mailngs data table; lookup maxes.
    $maxes = [
            'opened_rate'    => 0,
            'one_off_people' => 0,
            'one_off_amount' => 0,
            'one_off_cr'     => 0,
            'regular_people' => 0,
            'regular_amount' => 0,
            'regular_cr'     => 0,
            'total_people'   => 0,
            'total_amount'   => 0,
            'total_cr'       => 0,
          ];
    foreach ($mailings as &$mailing) {

      // Convert _people ones into a conversion %age for the _cr ones.
      if ($mailing['opened'] > 0) {
        $convert_to_rate = 100 / $mailing['opened'];
        foreach (['one_off_', 'regular_', 'total_'] as $_) {
          $mailing[$_ . 'cr'] = number_format($mailing[$_ . 'people'] * $convert_to_rate, 1);
        }
      }
      else {
        foreach (['one_off_cr', 'regular_cr', 'total_cr'] as $_) {
          $mailing[$_] = '';
        }
      }

      // Round £ to integers.
      foreach (['one_off_amount', 'regular_amount', 'total_amount'] as $_) {
        $mailing[$_] = round($mailing[$_]);
      }

      // Calculate maximums.
      foreach (array_keys($maxes) as $_) {
        if ($maxes[$_] < $mailing[$_]) {
          $maxes[$_] = $mailing[$_];
        }
      }
    }
    // Actually it's simpler than this.
    $maxes['one_off_people'] = $maxes['regular_people'] = $maxes['total_people'];
    $maxes['one_off_cr'] = $maxes['regular_cr'] = $maxes['total_cr'];
    $maxes['one_off_amount'] = $maxes['regular_amount'] = $maxes['total_amount'];

    return ['mailings' => $mailings, 'maxes' => $maxes];
  }
  /**
   */
  public function getMailingsInDateRange() {
    $params = [
      'is_completed' => 1,
      'return'       => ['name', 'scheduled_date'],
      'options'      => ['limit' => 0, 'sort' => 'scheduled_date DESC'],
    ];
    if ($this->date_range_end && $this->date_range_start) {
      // Between.
      $params['scheduled_date'] = ['BETWEEN' => [$this->date_range_start, $this->date_range_end]];
    }
    elseif ($this->date_range_start) {
      // Only a start date.
      $params['scheduled_date'] = ['>=' => $this->date_range_start];
    }
    elseif ($this->date_range_end) {
      // Only an end date.
      $params['scheduled_date'] = ['<=' => $this->date_range_end];
    }
    else {
      // All mailings, ever!
    }
    $result = civicrm_api3('Mailing', 'get', $params);
    if (empty($result['values'])) {
      return [];
    }

    // We also need to know the open rate for each of these mailings.
    // Get definitely SQL-safe list of mailing IDs.
    $mailings = [];
    foreach ($result['values'] as $id=>$vals) {
      $id = (int) $id;
      if ($id > 0) {
        $mailings[$id] = $vals +
          [
            'opened_rate'    => '',
            'one_off_people' => 0,
            'one_off_amount' => 0,
            'one_off_cr'     => 0,
            'regular_people' => 0,
            'regular_amount' => 0,
            'regular_cr'     => 0,
            'total_people'   => 0,
            'total_amount'   => 0,
            'total_cr'       => 0,
          ];
      }
    }

    return $mailings;
  }

  /**
   * Get array of integer group IDs.
   *
   * @return array of ints.
   */
  public function getSelectedLists() {
    if (!isset($this->selected_list_ids)) {
      $this->selected_list_ids = array_filter(array_map(function ($x) { return ($x > 0) ? (int) $x : 0; }, $this->getConfig('mailingLists', [])));
    }
    return $this->selected_list_ids;
  }
  /**
   * Get all active CiviCRM mailing lists
   *
   * @return array of group IDs => ['title' => 'foo']
   */
  public function getAllMailingLists() {
    if (!isset($this->all_lists)) {

      $selected = $this->getSelectedLists();

      $result = civicrm_api3('Group', 'get', [
        'return'     => ["title"],
        'group_type' => "Mailing List",
        'is_active'  => 1,
        'is_hidden'  => 0,
        'options'    => ['limit' => 0],
      ]);
      $this->all_lists = [];
      foreach ($result['values'] as $id => $_) {
        $this->all_lists[$id] = $_ + ['selected' => in_array($id, $selected)];
      }
    }
    return $this->all_lists;
  }

  /**
   * Get config.
   *
   * @param NULL|string sub-setting to return.
   */
  public function getConfig($key=NULL, $default=NULL) {

    $settings = [];

    //$json_encoded = Civi::settings()->set('oddc_dashboards', json_encode(['mailingLists' => [5, 6, 7,8, 9, 10, 11, 12, 13, 14, 16, 17, 18 ]]));

    $json_encoded = Civi::settings()->get('oddc_dashboards');
    if ($json_encoded) {
      $settings = json_decode($json_encoded, TRUE);
    }

    if ($key) {
      return $settings[$key] ?? $default;
    }

    return $settings;
  }

}
