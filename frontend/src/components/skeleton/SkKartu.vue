<script setup lang="ts">
/** Kartu putih ber-radius, bentuk dasar yang dipakai hampir semua section. */
import Skeleton from '@/components/ui/Skeleton.vue'
withDefaults(defineProps<{ judul?: boolean; baris?: number }>(), { judul: true, baris: 3 })
</script>

<template>
  <div class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4">
    <Skeleton v-if="judul" class="h-3.5 w-40 mb-3.5" />
    <div class="flex flex-col gap-2.5">
      <Skeleton v-for="i in baris" :key="i" class="h-3" :class="i === baris ? 'w-2/3' : 'w-full'" />
    </div>
  </div>
</template>
