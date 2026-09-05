<script setup lang="ts">
/**
 * Cari tujuan: judul, dua baris alamat berpin, tombol peta, lalu daftar
 * riwayat lokasi.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-0)">
    <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-3">
      <Skeleton class="w-6 h-6" bulat />
      <Skeleton class="h-4 w-48" />
    </div>

    <div class="max-w-[430px] mx-auto px-4">
      <div class="px-0.5">
        <div class="flex items-center gap-3">
          <Skeleton class="w-[22px] h-[22px]" bulat />
          <Skeleton class="h-3 flex-1" />
        </div>

        <div class="w-[22px] my-1 flex flex-col items-center gap-[3px]">
          <span v-for="i in 3" :key="i" class="w-[3px] h-[3px] rounded-full bg-(--color-outline)/30"></span>
        </div>

        <div class="flex items-center gap-3">
          <Skeleton class="w-[22px] h-[22px]" bulat />
          <Skeleton class="h-3.5 w-3/5" />
        </div>
      </div>

      <Skeleton class="mt-3 h-10 w-40 rounded-full" />
    </div>

    <div class="max-w-[430px] mx-auto mt-5">
      <Skeleton class="mx-4 h-2.5 w-36 mb-3" />
      <div
        v-for="i in 5"
        :key="i"
        class="px-4 py-3.5 flex items-start gap-3 border-b border-(--color-outline)/12"
      >
        <Skeleton class="w-4 h-4 mt-0.5" bulat />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-3 w-2/5" />
          <Skeleton class="h-2.5 w-4/5" />
        </div>
      </div>
    </div>
  </div>
</template>
