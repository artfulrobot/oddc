<template>
  <div class="bgbar">
    <div v-if="bar" class="bar" :style="{width: percent + '%'}"></div>
    <div class="text">{{prefix}}{{formattedValue}}</div>
  </div>
</template>
<script>
export default {
  props: ['total', 'value', 'prefix', 'bar'],
  data() {
    return {};
  },
  computed: {
    percent() {
      if (!this.value) {
        return '0';
      }
      return parseFloat(this.value) * 100 / parseFloat(this.total);
    },
    formattedValue() {
      if (!this.value) {
        return '0';
      }
      return parseFloat(this.value).toLocaleFixed(0);
    }
  }
};
</script>
