<?php
/*
Thinking on timezones.

MySQL does not store timezone with the datetime field type. Therefore it's just storing whatever is passed in.
This is likely the LOCAL TIME according to the server, so Europe/London time, and so sometimes UTC, sometimes BST.

The stats outputs period dates with their timezone, in ISO 8601 format (adding +01:00 at the end, for example).

Javascript's Date object understands timezones, but this causes problems.
Therefore the JS takes the timezone off and forces a +00:00 declaring the time
as UTC. That way it can then use toUTCString() to obtain the correct month
name.

When parsing a date like Y-m-d H:i:s in Javascript, 

 */

class CRM_Oddc_Stats {
  /** @var Redis|NULL */
  public $redis;

  public function __construct($startDate = NULL, $endDate = NULL) {

    return;
    $this->setStartDate($startDate)->setEndDate($endDate);

    // See if we have Redis available.
    if (class_exists('Redis')) {
      $r = new Redis();
      if ($r->connect('/run/redis/redis.sock')) {
        if ($r->ping()) {
          $this->redis = $r;
        }
      }
    }
  }

  /**
   * Get the stats for the period.
   *
   * @param array $stats An array of stat names. These correlate like 'Foo' stat is generated by method calcStatFoo()
   * @param bool $reset  By default it will use cached versions.
   */
  public function getStats($statsToCalc = NULL, $reset = FALSE) {
    $stats = [];
    if ($statsToCalc === NULL) {
      $statsToCalc = [];
    }
    foreach ($statsToCalc as $stat) {
      $m = "calcStat$stat";
      if (method_exists($this, $m)) {
        $cacheKey = 'oddStats_' . md5(json_encode([$this->startDate, $this->endDate, $stat]));

        if ($this->redis) {
          if (!$reset && $this->redis->exists($cacheKey)) {
            $cached = $this->redis->get($cacheKey);
            $cached = json_decode($cached, TRUE);
            if (is_array($cached)) {
              $stats += $cached;
            }
            else {
              $stats[$stat] = $cached;
            }
            continue;
          }
        }

        // No redis, or not found. Do the work now.
        $value = $this->$m();

        if (is_array($value)) {
          $stats += $value;
        }
        else {
          $stats[$stat] = $value;
        }
        if ($this->redis) {
          // Store it until midnight.
          $this->redis->set($cacheKey, json_encode($value), 60*60*(24 - (int) date('H')));
        }
      }
      else {
        throw new BadMethodCallException("$stat is invalid stat.");
      }
    }

    foreach ($stats as &$_) {
      if ($_ !== NULL) {
        $_ = (float) $_;
      }
    }
    unset($_);

    // Meta stats
    if (!empty($stats['churnPercent']) && !empty($stats['regularDonorIncome'])) {
      // Just an alias
    }

    ksort($stats);
    return $stats;
  }
  /**
   * Given a date, generate a list of dates for the first and last second of the
   * month up to this month.
   *
   * @param string Something strtotime can understand.
   * @return array
   */
  public function getMonthDates($from) {
    // Get first of month.
    $given = new DateTimeImmutable($from);
    $startOfMonth = $given->modify('first day of');
    $endOfMonth = $startOfMonth->modify('+1 month - 1 second');
    $now = new DateTimeImmutable('today');

    $months = [];
    while ($startOfMonth < $now) {
      $months[] = [$startOfMonth->format('c'), $endOfMonth->format('c')];
      $startOfMonth = $startOfMonth->modify('+1 month');
      $endOfMonth = $startOfMonth->modify('+1 month -1 second');
    }
    return $months;
  }
}

class Statx {
  /**
   * @var Array
   */
  public $params = [];

  protected $statProviderIndex = [];
  public $providers = [
    'StatXGeneric' => [
      'params' => ['startDate', 'endDate'],
      'methods' => [
        'calcStatOneOffDonors' => [
          'depends' => [],
          'provides' => [
            'oneOffDonorCount', 'oneOffDonorIncome', 'oneOffDonorAvgAmount',
            'oneOffDonorCountSourceOther', 'oneOffDonorIncomeSourceOther', 'oneOffDonorAvgAmountSourceOther',
            'oneOffDonorCountSourceSocial', 'oneOffDonorIncomeSourceSocial', 'oneOffDonorAvgAmountSourceSocial',
            'oneOffDonorCountSourceWebsite', 'oneOffDonorIncomeSourceWebsite', 'oneOffDonorAvgAmountSourceWebsite',
            'oneOffDonorCountSourceEmail', 'oneOffDonorIncomeSourceEmail', 'oneOffDonorAvgAmountSourceEmail',
          ],
        ],
        'calcStatRegularDonors' => [
          'depends' => [],
          'provides' => [
            'regularDonorCount', 'regularDonorIncome', 'regularDonorAvgAmount',
            'regularDonorCountSourceOther', 'regularDonorIncomeSourceOther', 'regularDonorAvgAmountSourceOther',
            'regularDonorCountSourceSocial', 'regularDonorIncomeSourceSocial', 'regularDonorAvgAmountSourceSocial',
            'regularDonorCountSourceWebsite', 'regularDonorIncomeSourceWebsite', 'regularDonorAvgAmountSourceWebsite',
            'regularDonorCountSourceEmail', 'regularDonorIncomeSourceEmail', 'regularDonorAvgAmountSourceEmail',
          ],
        ],
        'calcStatQuarterlySummary' => [
          'depends' => [],
          'provides' => [
            "previousYearQ1OneOff", "previousYearQ1Regular", "previousYearQ1Total",
            "previousYearQ2OneOff", "previousYearQ2Regular", "previousYearQ2Total",
            "previousYearQ3OneOff", "previousYearQ3Regular", "previousYearQ3Total",
            "previousYearQ4OneOff", "previousYearQ4Regular", "previousYearQ4Total",
            "previousYearOneOff", "previousYearRegular", "previousYearTotal",
            "thisYearQ1OneOff", "thisYearQ1Regular", "thisYearQ1Total",
            "thisYearQ2OneOff", "thisYearQ2Regular", "thisYearQ2Total",
            "thisYearQ3OneOff", "thisYearQ3Regular", "thisYearQ3Total",
            "thisYearQ4OneOff", "thisYearQ4Regular", "thisYearQ4Total",
            "thisYearOneOff", "thisYearRegular", "thisYearTotal",
          ],
        ],
        // Custom: openTrust only
        'calcStatQuarterlySummaryOt' => [
          'depends' => [],
          'provides' => [
            "otPreviousYearQ1OneOff", "otPreviousYearQ1Regular", "otPreviousYearQ1Total",
            "otPreviousYearQ2OneOff", "otPreviousYearQ2Regular", "otPreviousYearQ2Total",
            "otPreviousYearQ3OneOff", "otPreviousYearQ3Regular", "otPreviousYearQ3Total",
            "otPreviousYearQ4OneOff", "otPreviousYearQ4Regular", "otPreviousYearQ4Total",
            "otPreviousYearOneOff", "otPreviousYearRegular", "otPreviousYearTotal",
            "otThisYearQ1OneOff", "otThisYearQ1Regular", "otThisYearQ1Total",
            "otThisYearQ2OneOff", "otThisYearQ2Regular", "otThisYearQ2Total",
            "otThisYearQ3OneOff", "otThisYearQ3Regular", "otThisYearQ3Total",
            "otThisYearQ4OneOff", "otThisYearQ4Regular", "otThisYearQ4Total",
            "otThisYearOneOff", "otThisYearRegular", "otThisYearTotal",
          ],
        ],
        'calcStatRegularRetentionAnnual' => [
          'depends' => [],
          'provides' => [
            'annualRetainedRegularDonorsCount',
            'annualRetainedRegularDonorsPercent',
            'annualPreviousRegularDonorsCount',
            'annualChurnPercent',
          ],
        ],
        'calcStatRegularRetentionMonthly' => [
          'depends' => [],
          'provides' => [
            'monthlyRetainedRegularDonorsCount',
            'monthlyRetainedRegularDonorsPercent',
            'monthlyPreviousRegularDonorsCount',
            'churnPercent',
          ],
        ],
        'calcStatRegularRecruitmentAnnual' => [
          'depends' => [],
          'provides' => [
            'annualNewDonors',
            'annualOldDonors',
            'annualRecruitmentPercent',
          ],
        ],
        'calcStatRegularRecruitmentMonthly' => [
          'depends' => [],
          'provides' => [
            'monthlyNewDonors',
            'monthlyOldDonors',
            'monthlyRecruitmentPercent',
          ],
        ],
        'calcStatOneOffSpecial' => [
          'depends' => [],
          'provides' => [
            'oneOffDonorsRepeat',
            'oneOffDonors1st',
            'oneOffDonors2nd',
            'oneOffDonors3rd',
            'oneOffDonors4th',
            'oneOffDonors5OrMore',
            'oneOffsFromRegularDonor',
          ],
        ],
        'calcStatMarketing' => [
          'depends' => [
            'regularDonorIncome',
            'regularDonorCount',
            'churnPercent',
            'annualChurnPercent',
          ],
          'provides' => [ 'MRR', 'ARR', 'ARPU', 'LTV' ]
        ],
        'calcStatOneOffTopCountries' => [
          'depends' => [],
          'provides' => ['oneOffTopCountries']
        ]
      ],
    ],
  ];
  /**
   * @var array of provider objects.
   */
  protected $singletons = [];

  /**
   * @var Array cache of calculated stats.
   */
  public $outputs = [];

  /**
   */
  public function __construct($params = []) {
    $this->params = $params;

    // @todo hook to let others add defintions.

    // Index the stats.
    foreach ($this->providers as $providerClass => $providerDetails) {
      foreach ($providerDetails['methods'] as $methodName => $methodDetails) {
        foreach ($methodDetails['provides'] as $stat) {
          $this->statProviderIndex[$stat] = [$providerClass, $methodName];
        }
      }
    }
  }
  /**
   * Output a list of stats.
   */
  public function listStats() {
    $stats = array_keys($this->statProviderIndex);
    sort($stats);
    return $stats;
  }

  /**
   * Calculate a set of required stats.
   *
   * @param NULL|array of string stat names, as from oddc.getoddstats stats_set=2 list=1
   * If NULL, all stats are returned.
   */
  public function get($stats = NULL) {
    if ($stats === NULL) {
      $stats = $this->listStats();
    }
    return $this->runStats($stats)->outputs;
  }
  /**
   * Recursively get stats
   *
   * @var array $stats stat names
   * @var array $list (used internally)
   *
   * @return Statx
   */
  public function runStats($stats) {

    foreach ($stats as $statName) {

      $provider = $this->statProviderIndex[$statName] ?? NULL;
      if (!$provider) {
        throw new \InvalidArgumentException("'$statName' has no registered provider.");
      }

      // Do we have one of these objects?
      $providerClass = $provider[0];
      $providerMethod = $provider[1];
      $providerDetails = $this->providers[$providerClass];
      if (!isset($this->singletons[$providerClass])) {
        $args = [$this];
        foreach ($providerDetails['params'] ?? [] as $input) {
          $args[] = $this->getParam($input);
        }
        $this->singletons[$providerClass] = new $providerClass(...$args);
      }

      $methodDetails = $providerDetails['methods'][$providerMethod];
      // Depth-first, see if there are dependencies.
      if (!empty($methodDetails['depends'])) {
        $this->runStats($methodDetails['depends']);
      }
      // All dependencies met.

      // Has the stat already been calculated (e.g. one method that provides several)
      if (!isset($this->outputs[$statName])) {
        // Calc stats.
        $this->singletons[$providerClass]->$providerMethod();
      }
    }

    return $this;
  }

  public function getParam($name, $default=NULL) {
    return $this->params[$name] ?? $default;
  }
  public function getOutput($name) {
    return $this->outputs[$name];
  }
  public function setOutput($name, $value) {
    $this->outputs[$name] = $value;
  }
  public function setOutputs($keyValuePairs) {
    $this->outputs = array_merge($this->outputs, $keyValuePairs);
  }
}

class StatXGeneric {
  public $startDate;
  public $endDate;
  public $startDateTime;
  public $endDateTime;


  public $statx;

  public function __construct($statx, $start, $end) {
    $this->statx = $statx;
    $this->setStartDate($start)->setEndDate($end);
  }
  public function setStartDate($date) {
    if (!$date) {
      $this->startDate = NULL;
      $this->startDateTime = NULL;
    }
    else {
      $this->startDateTime = new DateTimeImmutable($date);
      $this->startDate = $this->startDateTime->format('YmdHis');
    }
    return $this;
  }

  public function setEndDate($date) {
    if (!$date) {
      $this->endDate = NULL;
      $this->endDateTime = NULL;
    }
    else {
      $this->endDateTime = new DateTimeImmutable($date);
      $this->endDate = $this->endDateTime->format('YmdHis');
    }
    return $this;
  }


  /**
   * How many donors making regular donations did we have in the period?
   *
   * This is MMR, assuming the period specified is a month.
   */
  public function calcStatRegularDonors() {
    $this->statx->setOutputs($this->basic(TRUE));
  }

  /**
   * How many donors making non-regulr donations did we have in the period?
   */
  public function calcStatOneOffDonors() {
    $this->statx->setOutputs($this->basic(FALSE));
  }

  /**
   * @todo separate out oD specific stuff
   *
   * Calculate count unique donors, income and avg per unique donor for
   * contributions in the date range, restricted to either regular or one off,
   * and break down by simplified source.
   *
   * @param bool $is_recurring
   *
   * @return array
   */
  protected function basic($isRecurring) {

    $isRecurringClause = $isRecurring ? 'IS NOT NULL' : 'IS NULL';

    $sql = "
WITH simplifiedSource AS (
  SELECT
    CASE
    WHEN source LIKE 'mailing%' THEN 'email'
    WHEN source LIKE '|website|%' THEN 'website'
    WHEN source LIKE 'website-%' THEN 'website'
    WHEN source LIKE '|social|%' THEN 'social'
    ELSE 'other'
    END source,
    contact_id,
    net_amount
  FROM civicrm_contribution cc
  WHERE receive_date >= $this->startDate AND receive_date <= $this->endDate
  AND is_test=0
  AND contribution_status_id = 1
  AND contribution_recur_id $isRecurringClause
)

SELECT
  source,
  COUNT(DISTINCT contact_id) DonorCount,
  SUM(net_amount) DonorIncome,
  ROUND(SUM(net_amount)/COUNT(DISTINCT contact_id), 2) DonorAvgAmount
FROM simplifiedSource
GROUP BY source WITH ROLLUP
      ";

    $dao = CRM_Core_DAO::executeQuery($sql);

    $prefix = $isRecurring ? 'regular' : 'oneOff';

    while ($dao->fetch()) {
      $suffix = '';
      if ($dao->source) {
        $suffix = 'Source' . ucfirst($dao->source);
      }
      foreach (['DonorCount', 'DonorIncome', 'DonorAvgAmount'] as $s) {
        $stats[ $prefix . $s . $suffix ] = $dao->$s;
      }
    }
    return $stats;
  }

  /**
   *
   * Provides stats to summarise Quarterly and year to date income, by regular/one off with comparisons on last year
   *
   * @return array
   */
  public function calcStatQuarterlySummary() {

    // Calculate quarter cut-offs.
    $result = Civi::settings()->get('fiscalYearStart');
      // {"M": "1", "d": "1"}
    $fiscalYearStartYear = date('Y') - ((date('m-d') < "$result[M]-$result[d]") ? 1 : 0);
    $datetimes = [
      'thisYear' => new DateTimeImmutable("$fiscalYearStartYear-$result[M]-$result[d]"),
    ];
    $datetimes['lastYear'] = $datetimes['thisYear']->modify('-1 year');
    $dateSQL = ['lastYearToDateSQL' => (new DateTimeImmutable('today - 1 year'))->format('Ymd')];
    foreach (['thisYear', 'lastYear'] as $start) {
      $dt = $datetimes[$start];
      $dateSQL[$start . 'StartSQL'] = $dt->format('Ymd');
      foreach ([
        'Q2StartSQL' => '+ 3 months',
        'Q3StartSQL' => '+ 6 months',
        'Q4StartSQL' => '+ 9 months',
      ] as $name => $modify) {
      $dateSQL[$start . "$name"] = $dt->modify($modify)->format('Ymd');
      }
    }

    $sql = "
WITH contribsWithDates AS (
  SELECT
    IF(receive_date < $dateSQL[thisYearStartSQL], 'previousYear', 'thisYear') fy,

    CASE
    WHEN receive_date < $dateSQL[lastYearQ2StartSQL] THEN 'Q1'
    WHEN receive_date < $dateSQL[lastYearQ3StartSQL] THEN 'Q2'
    WHEN receive_date < $dateSQL[lastYearQ4StartSQL] THEN 'Q3'
    WHEN receive_date < $dateSQL[thisYearStartSQL] THEN 'Q4'
    WHEN receive_date < $dateSQL[thisYearQ2StartSQL] THEN 'Q1'
    WHEN receive_date < $dateSQL[thisYearQ3StartSQL] THEN 'Q2'
    WHEN receive_date < $dateSQL[thisYearQ4StartSQL] THEN 'Q3'
    ELSE 'Q4'
    END quarter,

    IF(contribution_recur_id IS NULL, 'OneOff', 'Regular') donorType,
    contact_id,
    net_amount

  FROM civicrm_contribution cc
  WHERE receive_date >= $dateSQL[lastYearStartSQL]
  AND is_test=0
  AND contribution_status_id = 1
)

SELECT fy, quarter, donorType, SUM(net_amount) DonorIncome
FROM contribsWithDates
GROUP BY fy, quarter, donorType WITH ROLLUP
      ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    $stats = [];

    while ($dao->fetch()) {

      if (!$dao->fy) {
        // Ignore the 2 year total, it's meaningless.
        continue;
      }
      $name = $dao->fy;
      if (!$dao->quarter) {
        // This is the total of all contribs in this year.
        // thisYearTotal or previousYearTotal
        $name .= "Total";
      }
      else {
        // e.g. thisYearQ1OneOff or thisYearQ1OneOffTotal
        $name .= $dao->quarter . ($dao->donorType ?? 'Total');
        if ($dao->donorType) {
          $stats[$dao->fy . $dao->donorType ] += $dao->DonorIncome;
        }
      }
      $stats[$name] = $dao->DonorIncome;
    }

    // We need another set of stats for donor income last year to date. ? or not?

    $this->statx->setOutputs($stats);
    return;
  }

  /**
   *
   * Provides stats to summarise Quarterly and year to date income, by regular/one off with comparisons on last year
   *
   * This is a copy of calcStatQuarterlySummary
   *
   * @return array
   */
  public function calcStatQuarterlySummaryOt() {

    // Calculate quarter cut-offs.
    $result = Civi::settings()->get('fiscalYearStart');
      // {"M": "1", "d": "1"}
    $fiscalYearStartYear = date('Y') - ((date('m-d') < "$result[M]-$result[d]") ? 1 : 0);
    $datetimes = [
      'thisYear' => new DateTimeImmutable("$fiscalYearStartYear-$result[M]-$result[d]"),
    ];
    $datetimes['lastYear'] = $datetimes['thisYear']->modify('-1 year');
    $dateSQL = ['lastYearToDateSQL' => (new DateTimeImmutable('today - 1 year'))->format('Ymd')];
    foreach (['thisYear', 'lastYear'] as $start) {
      $dt = $datetimes[$start];
      $dateSQL[$start . 'StartSQL'] = $dt->format('Ymd');
      foreach ([
        'Q2StartSQL' => '+ 3 months',
        'Q3StartSQL' => '+ 6 months',
        'Q4StartSQL' => '+ 9 months',
      ] as $name => $modify) {
      $dateSQL[$start . "$name"] = $dt->modify($modify)->format('Ymd');
      }
    }

    $sql = "
WITH contribsWithDates AS (
  SELECT
    IF(receive_date < $dateSQL[thisYearStartSQL], 'previousYear', 'thisYear') fy,

    CASE
    WHEN receive_date < $dateSQL[lastYearQ2StartSQL] THEN 'Q1'
    WHEN receive_date < $dateSQL[lastYearQ3StartSQL] THEN 'Q2'
    WHEN receive_date < $dateSQL[lastYearQ4StartSQL] THEN 'Q3'
    WHEN receive_date < $dateSQL[thisYearStartSQL] THEN 'Q4'
    WHEN receive_date < $dateSQL[thisYearQ2StartSQL] THEN 'Q1'
    WHEN receive_date < $dateSQL[thisYearQ3StartSQL] THEN 'Q2'
    WHEN receive_date < $dateSQL[thisYearQ4StartSQL] THEN 'Q3'
    ELSE 'Q4'
    END quarter,

    IF(contribution_recur_id IS NULL, 'OneOff', 'Regular') donorType,
    contact_id,
    net_amount

  FROM civicrm_contribution cc
  WHERE receive_date >= $dateSQL[lastYearStartSQL]
  AND is_test=0
  AND contribution_status_id = 1
  AND financial_type_id = 8
)

SELECT fy, quarter, donorType, SUM(net_amount) DonorIncome
FROM contribsWithDates
GROUP BY fy, quarter, donorType WITH ROLLUP
      ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    $stats = [];

    while ($dao->fetch()) {

      if (!$dao->fy) {
        // Ignore the 2 year total, it's meaningless.
        continue;
      }
      $name = 'OT' . $dao->fy;
      if (!$dao->quarter) {
        // This is the total of all contribs in this year.
        // thisYearTotal or previousYearTotal
        $name .= "Total";
      }
      else {
        // e.g. thisYearQ1OneOff or thisYearQ1OneOffTotal
        $name .= $dao->quarter . ($dao->donorType ?? 'Total');
        if ($dao->donorType) {
          // Also accumulate thisYearRegular etc
          $stats['OT' . $dao->fy . $dao->donorType ] += $dao->DonorIncome;
        }
      }
      $stats[$name] = $dao->DonorIncome;
    }

    // We need another set of stats for donor income last year to date. ? or not?

    // xxx
    // Normally we also have thisYearRegular 


    $this->statx->setOutputs($stats);
    return;
  }


  /**
   * Annual retention rate
   *
   * This is the year up-to the start date.
   *
   * Number of people who gave a regular donation in this month and also gave one in "this month a year ago"
   *                                ÷
   * Number of people who gave a regular donation in "this month a year ago"
   *
   * 0 - 100%
   *
   */
  public function calcStatRegularRetentionAnnual() {

    $referenceMonthStartDateTime = $this->startDateTime->modify('-1 year');
    $stats = $this->retentionRates($referenceMonthStartDateTime);

    $stats = [
        'annualRetainedRegularDonorsCount'   => $stats['retainedCount'],
        'annualRetainedRegularDonorsPercent' => $stats['retainedPercentage'],
        'annualPreviousRegularDonorsCount'   => $stats['referenceDonorCount'],
        'annualChurnAmount'                  => $stats['churnAmount'],
        'annualChurnPercent'                 => round(
          ($stats['referenceDonorCount'] - $stats['retainedCount']) / $stats['referenceDonorCount'] * 100
          , 1)
    ];
    $this->statx->setOutputs($stats);
  }

  /**
   * Monthly retention rate
   *
   * This compares the given month with the one before it.
   *
   * Churn = number of people lost in this month ÷ number of donors in the month before
   *
   * The Simple Way from
   * https://www.profitwell.com/customer-churn/calculate-churn-rate
   */
  public function calcStatRegularRetentionMonthly() {

    if (!$this->startDate) {
      throw BadMethodCallException("Need startDate for calcStatRegularRetentionMonthly");
    }
    $referenceMonthStartDateTime = $this->startDateTime->modify('-1 month');
    $stats = $this->retentionRates($referenceMonthStartDateTime);

    $stats = [
        'monthlyRetainedRegularDonorsCount'   => $stats['retainedCount'],
        'monthlyRetainedRegularDonorsPercent' => $stats['retainedPercentage'],
        'monthlyPreviousRegularDonorsCount'   => $stats['referenceDonorCount'],
        'monthlyChurnAmount'                  => $stats['churnAmount'],
        'churnPercent'                        => round(
          ($stats['referenceDonorCount'] - $stats['retainedCount']) / $stats['referenceDonorCount'] * 100
          , 1)
    ];
    $this->statx->setOutputs($stats);
  }

  /**
   * Retention
   *
   * This compares the given month with the reference month.
   *
   * This is basically a cohort analysis; this matters when considering annual.
   * e.g. the churnAmount for annual compares the amount from the cohort of reg
   * givers 12 months ago to see how much is still coming in from them. This is
   * NOT the total loss over the year from churned donors, as you might think.
   *
   * But for monthly it works ok.
   *
   * @param DateTimeImmutable
   */
  protected function retentionRates(DateTimeImmutable $referenceMonthStartDateTime) {

    if (!$this->startDate) {
      throw BadMethodCallException("Need startDate for calcStatRegularRetentionAnnual");
    }
    if (!$this->endDate) {
      throw BadMethodCallException("Need endDate for calcStatRegularRetentionAnnual");
    }

    $refMonthStart = $referenceMonthStartDateTime->format('YmdHis');
    $refMonthEndDateTime = $referenceMonthStartDateTime->modify('+1 month -1 second');
    $refMonthEnd = $refMonthEndDateTime->format('YmdHis');

    // Number of people who gave regular donation in this month and the previous month
    // divided by the number of people who also gave in the previous month. 0 - 100%
    $sql = "
WITH lastMonthsDonors AS (
SELECT contact_id, net_amount
FROM civicrm_contribution
WHERE receive_date >= $refMonthStart AND receive_date <= $refMonthEnd
AND contribution_recur_id IS NOT NULL
AND contribution_status_id = 1
AND is_test = 0
GROUP BY contact_id
),

thisMonthsDonors AS (
SELECT contact_id, net_amount
FROM civicrm_contribution
WHERE receive_date >= $this->startDate AND receive_date <= $this->endDate
AND contribution_recur_id IS NOT NULL
AND contribution_status_id = 1
AND is_test = 0
GROUP BY contact_id
)

SELECT
  SUM(thisMonthsDonors.contact_id IS NOT NULL) retainedCount,
  COUNT(lastMonthsDonors.contact_id) referenceDonorCount,
  ROUND(SUM(thisMonthsDonors.contact_id IS NOT NULL) * 100 / COUNT(lastMonthsDonors.contact_id), 1) retainedPercentage,
  SUM(thisMonthsDonors.net_amount) - SUM(lastMonthsDonors.net_amount) churnAmount
FROM lastMonthsDonors
LEFT JOIN thisMonthsDonors ON lastMonthsDonors.contact_id = thisMonthsDonors.contact_id
      ;
      ";

    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return $dao->toArray();
    }
    else {
      return [
        'retainedCount' => NULL,
        'referenceDonorCount' => NULL,
        'retainedPercentage' => NULL,
        'churnAmount' => NULL,
        'referencePeriod' => [$referenceMonthStartDateTime, $refMonthEndDateTime]
      ];
    }
  }

  /**
   * Monthly recruitment rate
   */
  public function calcStatRegularRecruitmentMonthly() {
    return $this->recruitmentRate('monthly');
  }

  /**
   * Annual recruitment rate
   */
  public function calcStatRegularRecruitmentAnnual() {
    return $this->recruitmentRate('annual');
  }

  /**
   * Monthly recruitment rate
   *
   * @param string $period monthly|annual
   */
  public function recruitmentRate($period) {

    if (!$this->startDate) {
      throw BadMethodCallException("Need startDate for calcStatRegularRecruitmentMonthly");
    }
    if (!$this->endDate) {
      throw BadMethodCallException("Need endDate for calcStatRegularRecruitmentMonthly");
    }

    // Figure out the reference month.
    if ($period === 'monthly') {
      // Compare with the previous month.
      $lastMonthStartDateTime = $this->startDateTime->modify('-1 month');
    }
    elseif ($period === 'annual') {
      // Compare with the same month the previous year.
      $lastMonthStartDateTime = $this->startDateTime->modify('-1 year');
    }
    else {
      // Protect from SQL injection.
      throw new \Exception("$period must be monthly|annual");
    }

    $lastMonthStart = $lastMonthStartDateTime->format('YmdHis');
    $lastMonthEndDateTime = $lastMonthStartDateTime->modify('+ 1 month - 1 second');
    $lastMonthEndDate = $lastMonthEndDateTime->format('YmdHis');

    // Number of people who gave regular donation this year/month but had not
    // given a regular donation in the previous year/month
    //
    // divided by the number of people who gave regular donation in previous
    // year/month.
    //
    // 0 - 100+%
    $sql = "
      WITH lastMonthsDonors AS (
      SELECT contact_id
      FROM civicrm_contribution
      WHERE receive_date >= $lastMonthStart AND receive_date <= $lastMonthEndDate
      AND contribution_recur_id IS NOT NULL
      AND is_test = 0
      AND contribution_status_id = 1
      GROUP BY contact_id
      ),

      thisMonthsNewDonors AS (
      SELECT contact_id
      FROM civicrm_contribution
      WHERE receive_date >= $this->startDate AND receive_date <= $this->endDate
      AND contribution_recur_id IS NOT NULL
      AND is_test = 0
      AND contribution_status_id = 1
      AND NOT EXISTS (SELECT contact_id FROM lastMonthsDonors lm WHERE lm.contact_id = civicrm_contribution.contact_id)
      GROUP BY contact_id
      ),

      counts AS (
        SELECT (SELECT COUNT(*) FROM thisMonthsNewDonors) newDonors,
               (SELECT COUNT(*) FROM lastMonthsDonors) oldDonors
      )

      SELECT newDonors {$period}NewDonors,
        oldDonors {$period}OldDonors,
        newDonors / oldDonors * 100 {$period}RecruitmentPercent
      FROM counts
      ;";

    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $stats = $dao->toArray();
 //     $stats["{$period}Dates"] = "$lastMonthStart - $lastMonthEndDate compared to $this->startDate - $this->endDate";
    }
    else {
      $stats = [
//        "{$period}Dates" => "$lastMonthStart - $lastMonthEndDate compared to $this->startDate - $this->endDate",
        "{$period}NewDonors" => NULL,
        "{$period}OldDonors" => NULL,
        "{$period}RecruitmentPercent" => NULL,
      ];
    }

    $this->statx->setOutputs($stats);
  }

  /**
   *
   */
  public function calcStatOneOffSpecial() {

    $sql = "
WITH thisMonthsDonors AS (
  SELECT
    contact_id
  FROM civicrm_contribution cc
  WHERE receive_date >= $this->startDate AND receive_date <= $this->endDate
  AND is_test=0
  AND contribution_status_id = 1
  AND contribution_recur_id IS NULL
  GROUP BY contact_id
),
previousGiving AS (
  SELECT contact_id,
    COUNT(id) totalContribs,
    SUM(contribution_recur_id IS NULL) totalOneOffs
  FROM civicrm_contribution
  WHERE receive_date < $this->startDate
  AND is_test=0
  AND contribution_status_id = 1
  GROUP BY contact_id
)

SELECT
  SUM(previousGiving.totalContribs IS NULL) AS oneOffDonors1st,
  SUM(previousGiving.totalOneOffs > 0) AS oneOffDonorsRepeat,
  SUM(previousGiving.totalOneOffs = 1) AS oneOffDonors2nd,
  SUM(previousGiving.totalOneOffs = 2) AS oneOffDonors3rd,
  SUM(previousGiving.totalOneOffs = 3) AS oneOffDonors4th,
  SUM(previousGiving.totalOneOffs > 3) AS oneOffDonors5OrMore,
  SUM(previousGiving.totalContribs > 0 AND previousGiving.totalOneOffs = 0) AS oneOffsFromRegularDonor
FROM thisMonthsDonors
LEFT JOIN previousGiving ON thisMonthsDonors.contact_id = previousGiving.contact_id
";

    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      $stats = $dao->toArray();
    }
    else {
      $stats = [
        'oneOffDonorsRepeat' => 0,
        'oneOffDonors1st' => 0,
        'oneOffDonors2nd' => 0,
        'oneOffDonors3rd' => 0,
        'oneOffDonors4th' => 0,
        'oneOffDonors5OrMore' => 0,
        'oneOffsFromRegularDonor' => 0,
      ];
    }
    $this->statx->setOutputs($stats);
  }

  /**
   * Year to date from one offs.
   */
  public function calcStatOneOffYearToDate() {

    $startOfYear = date('Y') . '0101000000';
    $sql = "
      SELECT SUM(net_amount) oneOffYearToDate
      FROM civicrm_contribution cc
      WHERE receive_date >= $startOfYear
      AND is_test=0
      AND contribution_status_id = 1
      AND contribution_recur_id IS NULL
    ";

    $this->statx->setOutput('OneOffYearToDate', CRM_Core_DAO::singleValueQuery($sql));
  }

  /**
   * Target
   *
   */
  public function calcStatTarget() {

    $sql = "
      SELECT ROUND(SUM(net_amount)/ 500000 * 100) targetPercent,
             ROUND(SUM((contribution_recur_id IS NOT NULL) * net_amount)/ 500000 * 100) targetPercentRegular
      FROM civicrm_contribution cc
      WHERE receive_date >= $this->endDate - INTERVAL 1 YEAR
          AND receive_date <= $this->endDate
      AND is_test=0
      AND contribution_status_id = 1
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return $dao->toArray();
    }
    return [
      'targetPercent' => 0,
      'targetPercentRegular' => 0,
    ];

  }

  public function calcStatMarketing() {

    $stats = [];
    // Monthly Recurring Revenue
    $stats['MRR'] = $this->statx->getOutput('regularDonorIncome');
    // Annual Recurring Revenue
    $stats['ARR'] = 12 * $stats['MRR'];

    // Average Revenue Per User (donor)
    $arpu =  $stats['ARR'] / $this->statx->getOutput('regularDonorCount');
    // Lifetime Value
    $stats['LTV'] = round($arpu / ($this->statx->getOutput('churnPercent') / 100));
    $stats['LTV'] = round($arpu / ($this->statx->getOutput('annualChurnPercent') / 100));
    $stats['ARPU'] = round($arpu);

    $this->statx->setOutputs($stats);
  }
  public function calcStatOneOffTopCountries() {

    $stats = [];
    $sql = "
      WITH contactCountries AS (
        SELECT ad.contact_id, FIRST_VALUE(country.name) OVER (PARTITION BY contact_id ORDER BY is_primary) country
        FROM civicrm_address ad
        INNER JOIN civicrm_country country ON ad.country_id = country.id
        GROUP BY ad.contact_id
      )

      SELECT COALESCE(contactCountries.country, 'Unknown') country, COUNT(*) payments
      FROM civicrm_contribution cc
      LEFT JOIN contactCountries ON cc.contact_id = contactCountries.contact_id
      WHERE receive_date >= $this->startDate AND receive_date <= $this->endDate
      AND is_test=0
      AND contribution_status_id = 1
      AND contribution_recur_id IS NULL
      GROUP BY contactCountries.country
      ORDER BY COUNT(*) DESC
      LIMIT 10
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $results = [];
    while ($dao->fetch()) {
      $results[] = ['country' => $dao->country, 'payments' => (int) $dao->payments];
    }
    $stats['oneOffTopCountries'] = $results;
    $this->statx->setOutputs($stats);
  }
}
