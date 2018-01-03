<template>
  <div class="w-4/5">
    <img class="logo" src="./../assets/logo.png" alt="">
    <p class="font-sans text-black-darkest"><span class="font-bold">DEV:</span><span class="font-hairline">Coin</span> Web User Interface</p>
    <bar :chart-data="chartData"></bar>
  </div>
</template>

<script>
  import Bar from './Bar.vue'

  export default {
    name: 'home',
    components: {
      Bar
    },
    data: function () {
      return {
        labels: [],
        blocks: []
      }
    },
    computed: {
      chartData: function () {
        return {
          labels: this.labels,
          datasets: [
            {
              label: 'Total coins',
              backgroundColor: '#f87979',
              data: this.blocks
            }
          ]
        }
      }
    },
    created: function () {
      setInterval(function () {
        this.getBlocks()
      }.bind(this), 1000)
    },
    methods: {
      getBlocks: function () {
        this.$http.get('http://192.168.99.100:9000/').then(response => {
          this.updateBlocks(response.data.data.blocks)
        }).bind(this)
      },
      updateBlocks: function (blocks) {
        let now = new Date()
        this.blocks.push(blocks)
        this.labels.push(now.toTimeString())
      }
    }
  }
</script>

<style>
.logo {
  width: 200px;
}
</style>

<style src="./../styles/main.css"></style>
