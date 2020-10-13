{literal}
<script src="https://unpkg.com/vue@next"></script>
<!-- oD colours
strong blue: #0162B7 = hsl(208, 99%, 36%)
-->

<div id="revenuedashboard" class="revenuedashboard">
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
    <li v-for="m in all.filter(m => m.period[2] === 'full')" :key="m" >
      <a href @click.prevent="latestFull = m" v-show="m !== latestFull" >{{ formatDate(m.period[0]) }}</a>
      <span  v-show="m === latestFull" >{{ formatDate(m.period[0]) }}</span>
    </li>
  </ul>
  <div class="bigstats">
    <div>
      <div class="bignums">
        <h2>Retention</h2>
        <div class="l">
          <div class="bignum" >{{Math.round(latestFull.annualRetainedRegularDonorsPercent * 10)/10}}%</div>
          <div class="othernum" >{{latestFull.annualRetainedRegularDonorsCount + ' / ' + latestFull.annualPreviousRegularDonorsCount}}<br />
            last year
          </div>
        </div>
        <div class="r">
          <div class="bignum" >{{Math.round(latestFull.monthlyRetainedRegularDonorsPercent*10)/10}}%</div>
          <div class="othernum" >{{latestFull.monthlyRetainedRegularDonorsCount + ' / ' + latestFull.monthlyPreviousRegularDonorsCount}}</div>
          <div class="othernum" >last month ({{latestFull.churnPercent}}% churn)</div>
        </div>
      </div>
    </div>
    <div>
      <div class="bignums">
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
      <div class="bignums">
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
        </template>
      </tr>
      </template>
    </tbody>
  </table>

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
        :key="r" >{{ r.title }}</span>
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
  <ul>
    <template x-for="m in all.filter(m => m.period[2] === 'full')" :key="m.period[0]"  >
      <li>
        <a href x-text="m.period[0]" ></a>
      </li>
    </template>
  </ul>

<style>

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
}
.revenuedashboard ul.months a,
.revenuedashboard ul.months span {
  margin-right: 2rem;
}

.revenuedashboard .bigstats {
  display: flex;
  flex-wrap: wrap;
  margin: 1rem -1rem;
}
.revenuedashboard .bigstats>div {
  flex: 1 0 auto;
  padding: 0 1rem;
}
.revenuedashboard .bigstats>div>div { height: 100%; }

.revenuedashboard .bignums {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-gap: 1rem;
  gap: 1rem;
  margin: 0;
  padding: 2rem 1rem;

  background: white;
  text-align: center;
}
.revenuedashboard .bignums>h2 {
  grid-column: 1 / 3 ;
  margin: 0;
  line-height: 1;
  font-size: 1.7rem;
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
const statsData = {all: {/literal}{$stats}{literal}};
document.addEventListener('DOMContentLoaded', () => {

const app = Vue.createApp({
  data() {
    console.log("data called");
    const data = statsData;

    var i = data.all.length - 1;
    data.latest = data.all[i];

    while ((i > 0) && data.all[i].period[2] !== 'full') {
      i--;
    }
    data.latestFull = (i < 0) ? null : data.all[i];

    data.formatPercentage = (stat) => Math.round(data.latest[stat]) + '%';

    data.formatDate = (datetimeString) => {
      var d = new Date(datetimeString);
      return d.toUTCString().replace(/^\w+, \d+ (\w+ \d+).*$/, '$1');
    };

    console.log("data ends");
    return data;
  },
  computed: {
    oodFirstsPercent() {
      console.log("hyia");
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
      console.log("b", parts);
      return parts;
    },
    selectedMonth() {
      var d = new Date(this.latestFull.period[0]);
      return d.toUTCString().replace(/^\w+, \d+ (\w+ \d+).*$/, '$1');
    }
  },
  methods: {
    formatSourceValue(source, t) {
      if (source) {
        source = 'Source' + source;
      }
      const key = t + source;
      if (key in this.latestFull) {
        return parseFloat(this.latestFull[key]).toLocaleString();
      }
      return '';
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
}).mount('#revenuedashboard');


});
// https://stackoverflow.com/a/44134328/623519
function hslToHex(h, s, l) {
  h /= 360;
  s /= 100;
  l /= 100;
  let r, g, b;
  if (s === 0) {
    r = g = b = l; // achromatic
  } else {
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1 / 6) return p + (q - p) * 6 * t;
      if (t < 1 / 2) return q;
      if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
      return p;
    };
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    r = hue2rgb(p, q, h + 1 / 3);
    g = hue2rgb(p, q, h);
    b = hue2rgb(p, q, h - 1 / 3);
  }
  const toHex = x => {
    const hex = Math.round(x * 255).toString(16);
    return hex.length === 1 ? '0' + hex : hex;
  };
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}
</script>
{/literal}
