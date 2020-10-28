<template>
  <div id="revenuedashboard" class="revenuedashboard">

    <area-chart
      :data="incomeAndChurnChartData.data"
      :dataset="incomeAndChurnChartData.dataset"
      :library="incomeAndChurnChartData.library"
      :curve="false"
      :min="incomeAndChurnChartData.min"
      ></area-chart>

    <h2>Main stats</h2>
    <div class="bigstats">
      <div>
        <div class="bignums two">
          <h2>Monthly Donors</h2>
          <div class="l">
            <div class="bignum" >{{formatNum(latestFull.regularDonorCount, 0)}}</div>
            <div class="othernum" >Donor count</div>
          </div>
          <div class="r">
            <div class="bignum" >£{{formatNum(latestFull.regularDonorAvgAmount, 2)}}</div>
            <div class="othernum" >Average donation</div>
          </div>
        </div>
      </div>

      <div>
        <div class="bignums">
          <h2>Year to Date</h2>
          <div>
            <div class="bignum" >£{{formatNum(latest.thisYearTotal, 0)}}</div>
          </div>
        </div>
      </div>
    </div>
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
          <td><bg-barchart :value="latest.thisYearQ1Total" :total="latest.thisYearTotal" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ2Total" :total="latest.thisYearTotal" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ3Total" :total="latest.thisYearTotal" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ4Total" :total="latest.thisYearTotal" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearTotal" :total="latest.thisYearTotal"  ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearTotal" :total="latest.thisYearTotal"  ></bg-barchart></td>
        </tr>
        <tr>
          <th>One off</th>
          <td><bg-barchart :value="latest.thisYearQ1OneOff" :total="latest.thisYearOneOff" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ2OneOff" :total="latest.thisYearOneOff" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ3OneOff" :total="latest.thisYearOneOff" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ4OneOff" :total="latest.thisYearOneOff" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearOneOff" :total="latest.thisYearOneOff"  ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearOneOff" :total="latest.thisYearOneOff"  ></bg-barchart></td>
        </tr>
        <tr>
          <th>Regular</th>
          <td><bg-barchart :value="latest.thisYearQ1Regular" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ2Regular" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ3Regular" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearQ4Regular" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.thisYearRegular" :total="latest.thisYearRegular"  ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearRegular" :total="latest.thisYearRegular"  ></bg-barchart></td>
        </tr>
        <tr>
          <th>Previous Year</th>
          <td><bg-barchart :value="latest.previousYearQ1Total" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearQ2Total" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearQ3Total" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearQ4Total" :total="latest.thisYearRegular" bar=1 ></bg-barchart></td>
          <td><bg-barchart :value="latest.previousYearTotal" :total="latest.thisYearRegular"  ></bg-barchart></td>
          <td></td>
        </tr>
      </tbody>
    </table>

    <h2>Figures for {{selectedMonth}}</h2>
    <ul class="months">
      <li v-for="m in all.filter(m => m.period[2] === 'full')" :key="m.period[0]" >
        <a href @click.prevent="latestFull = m" v-show="m !== latestFull" >{{ formatDateAsMonthYear(m.period[0]) }}</a>
        <span  v-show="m === latestFull" >{{ formatDateAsMonthYear(m.period[0]) }}</span>
      </li>
    </ul>


    <h2>Sources</h2>
    <table>
      <thead>
        <tr>
          <th></th>
          <th colspan=2 class="center" >Count</th>
          <th colspan=2 class="center" >Average £</th>
          <th colspan=2 class="center" >Income £</th>
        </tr>
        <tr>
          <th></th>
          <th class="center">One Off</th>
          <th class="center">Regular</th>
          <th class="center">One Off</th>
          <th class="center">Regular</th>
          <th class="center">One Off</th>
          <th class="center">Regular</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="source in ['', 'Email', 'Social', 'Website', 'Other']" :key="source" >
          <th>{{source ? source : 'All'}}</th>
          <td v-for="t in [
            'oneOffDonorCount', 'regularDonorCount',
            'oneOffDonorAvgAmount', 'regularDonorAvgAmount',
            'oneOffDonorIncome', 'regularDonorIncome',
            ]" :key="t"
              class="right"
              >{{formatSourceValue(source, t)}}</td>
        </tr>
      </tbody>
    </table>

    <div class="bigstats">
      <div>
        <div class="bignums two">
          <h2>Retention</h2>
          <div class="l">
            <div class="bignum" >{{Math.round(latestFull.annualRetainedRegularDonorsPercent * 10)/10}}%</div>
            <div class="othernum" >{{latestFull.annualRetainedRegularDonorsCount + ' / ' + latestFull.annualPreviousRegularDonorsCount}}<br />
            last year ({{latestFull.annualChurnPercent}}% churn)</div>
          </div>
          <div class="r">
            <div class="bignum" >{{Math.round(latestFull.monthlyRetainedRegularDonorsPercent*10)/10}}%</div>
            <div class="othernum" >{{latestFull.monthlyRetainedRegularDonorsCount + ' / ' + latestFull.monthlyPreviousRegularDonorsCount}}<br />
            last month ({{latestFull.churnPercent}}% churn)</div>
          </div>
        </div>
      </div>
      <div>
        <div class="bignums two">
          <h2>Recruitment</h2>
          <div class="l">
            <div class="bignum" >{{Math.round(latestFull.annualRecruitmentPercent)}}%</div>
            <div class="othernum" >+{{latestFull.annualNewDonors}}<br />
              last year
            </div>
          </div>
          <div class="r">
            <div class="bignum" >{{Math.round(latestFull.monthlyRecruitmentPercent)}}%</div>
            <div class="othernum" >+{{latestFull.monthlyNewDonors}}<br />
              last month
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="bignums two">
          <h2>Standard Stats</h2>
          <div class="l">
            <div class="bignum" >£{{Math.round(latestFull.MRR).toLocaleString()}}</div>
            <div class="othernum" >MRR</div>
          </div>
          <div class="r">
            <div class="bignum" >£{{Math.round(latestFull.LTV).toLocaleString()}}</div>
            <div class="othernum" >LTV</div>
          </div>
        </div>
      </div>

    </div><!-- /.bigstats -->

    <h2>One Off Donations</h2>

    <div class="ood">
      <div class="first"
           :style="{ width: oodFirstsPercent + '%'}">
        <span class="qty" >{{latestFull.oneOffDonors1st || 0}} donations</span>
        <span class="bar" style="width: 100%">First donation</span>
      </div>
      <div class="repeats"
           :style="{left: oodFirstsPercent + '%', width: oodRepeatsPercent + '%'}">
        <span class="qty" >{{latestFull.oneOffDonorsRepeat || 0}} repeat donations</span>
        <span class="bar"
          v-for="r in oodRepeatsParts"
          :style="r.style"
          :title="r.title"
          :key="r.title" >{{ r.title }}</span>
      </div>
    </div>

    <!-- country -->
    <table border="">
      <thead>
        <tr><th>Donations</th><th>Country</th></tr>
      </thead>
      <tbody>
        <tr v-for="row in latestFull.oneOffTopCountries" :key="row.country">
          <td><bg-barchart
            :total="latestFull.oneOffTopCountries[0].payments"
            :value="row.payments"
            :text="row.payments"
            bar=1
          ></bg-barchart></td>
          <td>{{row.country}}</td>
        </tr>
      </tbody>
    </table>

  </div>
</template>
<style lang="scss">

$strongBlue: #0162B7; //= hsl(208, 99%, 36%)
.revenuedashboard .center { text-align: center; }
.revenuedashboard .right { text-align: right; }
.revenuedashboard .bgbar {
  position: relative;
}
.revenuedashboard .bgbar .bar {
  background: #BFDCF7;
  height: 100%;
  position: absolute;
  right: 0;
}
.revenuedashboard .bgbar .text {
  position: relative;
  text-align: right;
}

.revenuedashboard ul.months {
  margin:0;
  padding:0;
  list-style: none;
}
.revenuedashboard ul.months li {
  margin: 0;
  padding: 0;
  display: inline-block;
  line-height: 1.5;
}
.revenuedashboard ul.months a,
.revenuedashboard ul.months span {
  margin-right: 2rem;
  width: 5rem;
  display: inline-block;
}

.revenuedashboard .bigstats {
  display: flex;
  flex-wrap: wrap;
  margin: 1rem -1rem;
}
.revenuedashboard .bigstats>div {
  flex: 1 0 auto;
  padding: 0 1rem;
  margin-bottom: 2rem;
}
.revenuedashboard .bigstats>div>div { height: 100%; }

.revenuedashboard .bignums {
  margin: 0;
  padding: 2rem 1rem;
  background: white;
  text-align: center;
}
.revenuedashboard .bignums>h2 {
  margin: 0;
  line-height: 1;
  font-size: 1.7rem;
}

/* Two columns */
.revenuedashboard .bignums.two {
  width: auto; /* civicrm.css adds a 2em size to .two! */
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-gap: 1rem;
  gap: 1rem;
}
.revenuedashboard .bignums.two>h2 {
  grid-column: 1 / 3 ;
}

.revenuedashboard .bignum {
  padding: 0.5rem 0;
  font-size: 3rem;
  line-height: 1;
  font-weight: bold;
  text-align: center;
}
.revenuedashboard .othernum {
  padding: 0;
}

.revenuedashboard .ood {
  outline: solid 1px #eee;
  position: relative;
  height: 4rem;
  padding-top:2rem;
  margin-bottom: 2rem;
}
.revenuedashboard .ood>div {
  position: absolute;
  height: 2rem;
}
.revenuedashboard .ood .qty {
  width: 100%;
  position: absolute;
  top: -1.8rem;
  text-align: center;
}
.revenuedashboard .ood .bar {
  min-height: 2rem;
  position: absolute;
  text-align: center;
  line-height: 2rem;
}
.revenuedashboard .ood .first .qty {
  border-bottom:solid 2px hsl(208, 80%, 60%);
}
.revenuedashboard .ood .repeats .qty {
  border-bottom:solid 2px hsl(208, 80%, 40%);
}
.revenuedashboard .ood .first .bar {
  background: hsl(208, 80%, 90%);
}
.revenuedashboard .ood .repeats .bar {
  background: hsl(208, 80%, 80%);
}

</style>
<script>
export default {
  props: ['config'],
  data() {
    const data = this.config;

    var i = data.all.length - 1;
    data.latest = data.all[i];

    while ((i > 0) && data.all[i].period[2] !== 'full') {
      i--;
    }
    data.latestFull = (i < 0) ? null : data.all[i];

    return data;
  },
  computed: {
    incomeAndChurnChartData() {

      const d = [
        {name: "Churn", data: {}, dataset:    {fill: 'origin',}},
        {name: "MRR", data: {}, dataset:      {fill: 'origin',}},
        {name: "One Offs", data: {}, dataset: {fill: '-1',    }}
      ];

      var i = 0;
      [
        '190, 93, 93', // Red
        '148, 204, 100', // Green
        '225, 191, 101', // Amber
      ].forEach(c => {
        d[i].dataset.backgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.hoverBackgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.pointBackgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.pointHoverBackgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.backgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.hoverBackgroundColor = `rgba(${c}, 0.4)`;
        d[i].dataset.pointHoverBackgroundColor = `rgba(${c}, 0.4)`;

        d[i].dataset.borderColor = `rgba(${c}, 1)`;
        d[i].dataset.hoverBorderColor = `rgba(${c}, 1)`;

        d[i].dataset.pointBorderColor = `rgba(${c}, 1)`;
        d[i].dataset.pointHoverBorderColor = `rgba(${c}, 1)`;
        i++;
      });

      var min = 0, max=0;
      this.all.forEach(series => {
        if (series.period[2] !== 'full') {
          return;
        }
        const x = series.period[0];
        min = Math.min(min, series.monthlyChurnAmount);
        d[0].data[x] = parseFloat(series.monthlyChurnAmount);
        d[1].data[x] = parseFloat(series.regularDonorIncome);
        // Show one off stacked on top of regular.
        d[2].data[x] = parseFloat(series.oneOffDonorIncome) + d[1].data[x];
        max = Math.max(max, d[2].data[x]);
      });

      // Require negative to be at least 10% of positive. (or the label gets squashed)
      min = Math.min(Math.abs(max) * -0.1, min);
      // Round min to next 1000
      min = Math.ceil(min/1000) * 1000;
      return {
        min,
        data: d,
        dataset: [
          { fill: 'origin' },
          { fill: '-1'},
          { fill: 'origin' },
        ],
        library: {
          tooltips: {
            callbacks: {
              label(tooltipItem, data) {
                var label = data.datasets[tooltipItem.datasetIndex].label || '';
                var val = tooltipItem.yLabel;

                if (tooltipItem.datasetIndex === 2) {
                  // When presenting the One-offs we need to subtract the regulars.
                  // As that was only there to stack the chart.
                  val -= data.datasets[1].data[tooltipItem.index];
                }
                if (label) {
                  label += ': ';
                }
                label += Math.round(val).toLocaleString();
                return label;
              }
            }
          }
        }
      };
    },
    oodFirstsPercent() {
      return parseInt(this.latestFull.oneOffDonors1st) / (parseInt(this.latestFull.oneOffDonors1st) + parseInt(this.latestFull.oneOffDonorsRepeat)) * 100.0;
    },
    oodRepeatsPercent() {
      return parseInt(this.latestFull.oneOffDonorsRepeat) / (parseInt(this.latestFull.oneOffDonors1st) + parseInt(this.latestFull.oneOffDonorsRepeat)) * 100.0;
    },
    oodRepeatsParts() {
      var t = parseInt(this.latestFull.oneOffDonorsRepeat) / 100.0;
      var l = 0;
      var n = 0;
      const parts = [];
      ['2nd', '3rd', '4th', '5OrMore'].forEach(th => {
        var v = this.latestFull['oneOffDonors' + th] || 0;
        v = parseInt(v);
        if (v > 0) {
          parts.push({
            style: {
              left: l + '%',
              width: (v/t) + '%',
              background: hslToHex(208, 60, 50+8*n),
            },
            title: th
          });
          l += v/t;
          n += 1;
        }
      });
      return parts;
    },
    selectedMonth() {
      return this.formatDateAsMonthYear(this.latestFull.period[0]);
    }
  },
  methods: {
    formatPercentage(stat) {
      return Math.round(data.latest[stat]) + '%';
    },
    formatSourceValue(source, t) {
      if (source) {
        source = 'Source' + source;
      }
      const key = t + source;
      if (key in this.latestFull) {
        return parseFloat(this.latestFull[key]).toLocaleString();
      }
      return '';
    },
    formatNum(v, dp) {
      return parseFloat(v).toLocaleFixed(dp);
    },
    formatDateAsMonthYear(iso8601string) {
      if (!iso8601string) {
        return '';
      }
      const pretendUTCTime = iso8601string.replace(/(\+\d\d:\d\d)?$/, '+00:00');
      const d = new Date(pretendUTCTime);
      return d.toUTCString().replace(/^\w+, \d+ (\w+ \d+).*$/, '$1');
    }
  },
  components: {
    bgBarchart: {
      props: ['total', 'value', 'prefix', 'bar'],
      data() {
        return {};
      },
      computed: {
        percent() {
          return parseFloat(this.value) * 100 / parseFloat(this.total);
        }
      },
      template: `<div class="bgbar">
        <div v-if="bar" class="bar" :style="{width: percent + '%'}"></div>
        <div class="text">{{prefix}}{{Math.round(parseFloat(value)).toLocaleString()}}</div>
      </div>`
    }
  }
};
</script>

