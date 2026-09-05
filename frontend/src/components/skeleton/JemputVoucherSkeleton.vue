<script setup lang="ts">
/** Voucher: kolom kode promo, satu kartu terpakai, lalu daftar yang belum bisa. */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container)">
    <div class="bg-(--color-surface-0)">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-3">
        <Skeleton class="w-6 h-6" bulat />
        <Skeleton class="h-4 w-24" />
      </div>
      <div class="max-w-[430px] mx-auto px-4 pb-4">
        <Skeleton class="h-11 w-full rounded-full" />
      </div>
    </div>

    <div class="max-w-[430px] mx-auto px-4 pt-2">
      <div class="flex items-center gap-2 mb-4">
        <Skeleton class="h-5 w-16 rounded-md" />
        <Skeleton class="h-3 w-32" />
      </div>

      <Skeleton class="h-3.5 w-48 mb-2.5" />

      <div class="rounded-2xl bg-(--color-surface-0) p-4 mb-6">
        <Skeleton class="h-5 w-2/5 mb-2" />
        <Skeleton class="h-3 w-3/5 mb-2" />
        <Skeleton class="h-2.5 w-4/5" />
        <div class="mt-3 pt-3 border-t border-dashed border-(--color-outline)/30 flex items-center justify-between gap-3">
          <Skeleton class="h-2.5 w-1/3" />
          <Skeleton class="h-9 w-20 rounded-full" />
        </div>
      </div>

      <Skeleton class="h-3.5 w-36 mb-2.5" />
      <div class="flex flex-col gap-2.5">
        <div v-for="i in 3" :key="i" class="rounded-2xl bg-(--color-surface-0) p-4 flex flex-col gap-2">
          <Skeleton class="h-4 w-2/5" />
          <Skeleton class="h-2.5 w-4/5" />
          <Skeleton class="h-2.5 w-1/2" />
        </div>
      </div>
    </div>
  </div>
</template>
