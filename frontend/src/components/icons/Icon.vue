<script setup lang="ts">
defineProps<{ name: string }>()
</script>

<template>
  <svg class="icon" aria-hidden="true">
    <use :href="`#i-${name}`" />
  </svg>
</template>

<style scoped>
.icon {
  width: 1.25em;
  height: 1.25em;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  flex-shrink: 0;
}
</style>
