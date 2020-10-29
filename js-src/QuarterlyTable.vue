<template>
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
        <td><bg-barchart :value="latest.thisYearQ1Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ2Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ3Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ4Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearTotal" :total="barTotal"  ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearTotal" :total="barTotal"  ></bg-barchart></td>
      </tr>
      <tr>
        <th>One off</th>
        <td><bg-barchart :value="latest.thisYearQ1OneOff" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ2OneOff" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ3OneOff" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ4OneOff" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearOneOff" :total="barTotal"  ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearOneOff" :total="barTotal"  ></bg-barchart></td>
      </tr>
      <tr>
        <th>Regular</th>
        <td><bg-barchart :value="latest.thisYearQ1Regular" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ2Regular" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ3Regular" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearQ4Regular" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.thisYearRegular" :total="barTotal"  ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearRegular" :total="barTotal"  ></bg-barchart></td>
      </tr>
      <tr>
        <th>Previous Year</th>
        <td><bg-barchart :value="latest.previousYearQ1Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearQ2Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearQ3Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearQ4Total" :total="barTotal" bar=1 ></bg-barchart></td>
        <td><bg-barchart :value="latest.previousYearTotal" :total="barTotal"  ></bg-barchart></td>
        <td></td>
      </tr>
    </tbody>
  </table>
</template>
<script>
import BgBarchart from './BgBarchart.vue';
export default {
  components: {BgBarchart},
  props: ['latest'],
  data() {
    return {};
  },
  computed: {
    barTotal() {
      return Math.max(this.latest.thisYearTotal, this.latest.previousYearTotal);
    }
  }
};
</script>
