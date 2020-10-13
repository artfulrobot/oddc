{literal}
<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>

<div x-data="getData()" class="revenuedashboard">
  <h2>Quarterly Income Summary</h2>

  <table>
    <thead>
      <tr>
        <th></th>
        <th class="right">Q1</th>
        <th class="right">Q2</th>
        <th class="right">Q3</th>
        <th class="right">Q4</th>
        <th class="right">Total year to date</th>
        <th class="right">Total for previous year</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th>Total donor income</th>
        <td x-html="formatQuarterlyAmount('thisYearQ1Total')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ2Total')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ3Total')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ4Total')"></td>
        <td x-html="formatQuarterlyAmount('thisYearTotal', 1)"></td>
        <td x-html="formatQuarterlyAmount('previousYearTotal', 1)"></td>
      </tr>
      <tr>
        <th>One-off</th>
        <td x-html="formatQuarterlyAmount('thisYearQ1OneOff')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ2OneOff')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ3OneOff')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ4OneOff')"></td>
        <td x-html="formatQuarterlyAmount('thisYearOneOff', 1)"></td>
        <td x-html="formatQuarterlyAmount('previousYearOneOff', 1)"></td>
      </tr>
      <tr>
        <th>Regular</th>
        <td x-html="formatQuarterlyAmount('thisYearQ1Regular')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ2Regular')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ3Regular')"></td>
        <td x-html="formatQuarterlyAmount('thisYearQ4Regular')"></td>
        <td x-html="formatQuarterlyAmount('thisYearRegular', 1)"></td>
        <td x-html="formatQuarterlyAmount('previousYearRegular', 1)"></td>
      </tr>
      <tr>
        <th>Previous Year</th>
        <td x-html="formatQuarterlyAmount('previousYearQ1Total')"></td>
        <td x-html="formatQuarterlyAmount('previousYearQ2Total')"></td>
        <td x-html="formatQuarterlyAmount('previousYearQ3Total')"></td>
        <td x-html="formatQuarterlyAmount('previousYearQ4Total')"></td>
        <td x-html="formatQuarterlyAmount('previousYearTotal', 1)"></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <h2>Figures for <span x-text="formatDate(latestFull.period[0])"></span></h2>

  <div class="bigstats">
    <div class="bignums">
      <h2>Retention</h2>
      <div class="l">
        <div class="bignum" x-text="Math.round(latestFull.annualRetainedRegularDonorsPercent) + '%'"></div>
        <div class="othernum" x-text="latestFull.annualRetainedRegularDonorsCount + ' / ' + latestFull.annualPreviousRegularDonorsCount" ></div>
        <div class="othernum" >last year</div>
      </div>
      <div class="r">
        <div class="bignum" x-text="Math.round(latestFull.monthlyRetainedRegularDonorsPercent) + '%'"></div>
        <div class="othernum" x-text="latestFull.monthlyRetainedRegularDonorsCount + ' / ' + latestFull.monthlyPreviousRegularDonorsCount" ></div>
        <div class="othernum" >last month</div>
      </div>
    </div>
    <div class="bignums">
      <h2>Recruitment</h2>
      <div class="l">
        <div class="bignum" x-text="Math.round(latestFull.annualRecruitmentPercent) + '%'"></div>
        <div class="othernum" x-text="'+' + latestFull.annualNewDonors" ></div>
        <div class="othernum" >last year</div>
      </div>
      <div class="r">
        <div class="bignum" x-text="Math.round(latestFull.monthlyRecruitmentPercent) + '%'"></div>
        <div class="othernum" x-text="'+' + latestFull.monthlyNewDonors" ></div>
        <div class="othernum" >last month</div>
      </div>
    </div>
  </div>
  <p>MMR £<span x-text="parseInt(latestFull.MRR).toLocaleString()" ></span>. AAR £<span x-text="parseInt(latestFull.ARR).toLocaleString()" ></span>. LTV £<span x-text="parseInt(latestFull.LTV).toLocaleString()"></span></p>
  <ul>
    <template x-for="m in all.filter(m => m.period[2] === 'full')" :key="m.period[0]"  >
      <li>
        <a href x-text="m.period[0]" ></a>
      </li>
    </template>
  </ul>
</div>

<style>

.revenuedashboard .bgbar {
  position: relative;
}
.revenuedashboard .bgbar .bar {
  background: rgba(0,0,0, 0.1);
  height: 100%;
  position: absolute;
  right: 0;
}
.revenuedashboard .bgbar .text {
  position: relative;
  text-align: right;
}


.revenuedashboard .bigstats {
  display: flex;
  flex-wrap: wrap;
}
.revenuedashboard .bigstats>div {
  flex: 1 0 auto;
}

.revenuedashboard .bignums {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-gap: 1rem;
  gap: 1rem;
  margin: 1rem;
  padding: 2rem 1rem;

  background: white;
  text-align: center;
}
.revenuedashboard .bignums>h2 {
  grid-column: 1 / 3 ;
  margin: 0;
  line-height: 1;
}
.revenuedashboard .bignum {
  padding: 0.5rem 0;
  font-size: 3.5rem;
  line-height: 1;
  font-weight: bold;
  text-align: center;
}
.revenuedashboard .othernum {
  padding: 0;
}
</style>

<script>
  function getData() {
    const data = {all: {/literal}{$stats}{literal}};
    console.log("data called");

    var i = data.all.length - 1;
    data.latest = data.all[i];
    while ((i > 0) && data.all[i].period[2] !== 'full') {
      i--;
    }
    data.latestFull = (i < 0) ? null : data.all[i];
    var quarterlyReportMax = Math.max(data.latest.thisYearTotal || 0, data.latest.previousYearTotal || 0);

    data.formatQuarterlyAmount = (a, withoutBar)  => {
      console.log("formatQuarterlyAmount");
      var val = data.latest[a];
      return data.formatBarchart('£', val, quarterlyReportMax, withoutBar);
    };

    data.formatPercentage = (stat) => Math.round(data.latest[stat]) + '%';

    data.formatBarchart = function(prefix, number, max, withoutBar) {
      var text = prefix + Math.round(number).toLocaleString();
      var percent = number/max*100;
      return '<div class="bgbar">'
        + (withoutBar ? '' : '<div class="bar" style="width:' + percent + '%;"></div>')
        + '<div class="text">' + text + '</div></div>';
    };

    data.formatDate = (datetimeString) => {
      var d = new Date(datetimeString);
      return d.toUTCString().replace(/^\w+, \d+ (\w+ \d+).*$/, '$1');
    };

    return data;
  }
</script>
{/literal}
