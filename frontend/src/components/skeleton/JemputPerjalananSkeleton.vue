<script setup lang="ts">
/**
 * Layar perjalanan: kartu tahap, kartu rute, keselamatan, dan rincian tarif.
 *
 * Kartu pengemudi TIDAK ditiru di sini. Selama masih memuat, belum tentu ada
 * pengemudi yang ditugaskan; menggambar kerangkanya lebih dulu menjanjikan
 * sesuatu yang mungkin tidak pernah muncul.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-28">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Tahap -->
      <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center flex flex-col items-center">
        <Skeleton class="w-[150px] h-[150px]" bulat />
        <Skeleton class="mt-3 h-4 w-4/5" />
        <Skeleton class="mt-2 h-2.5 w-full" />
        <Skeleton class="mt-1.5 h-2.5 w-3/5" />
      </section>

      <!-- Rute -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex gap-3">
          <div class="flex flex-col items-center pt-1">
            <Skeleton class="w-3 h-3" bulat />
            <span class="w-0.5 flex-1 my-1 bg-(--color-outline)/20"></span>
            <Skeleton class="w-3 h-3" bulat />
          </div>
          <div class="flex-1 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
              <Skeleton class="h-2.5 w-24" />
              <Skeleton class="h-3 w-4/5" />
            </div>
            <div class="flex flex-col gap-1.5">
              <Skeleton class="h-2.5 w-16" />
              <Skeleton class="h-3 w-3/4" />
            </div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-(--color-outline)/15 flex items-center justify-between">
          <Skeleton class="h-3 w-2/5" />
          <Skeleton class="h-3.5 w-20" />
        </div>
      </section>

      <!-- Keselamatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-28 mb-3" />
        <div class="grid grid-cols-2 gap-2.5">
          <Skeleton class="h-11 rounded-full" />
          <Skeleton class="h-11 rounded-full" />
        </div>
      </section>

      <!-- Rincian tarif -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-28 mb-3" />
        <div class="flex flex-col gap-2.5">
          <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-3">
            <Skeleton class="h-3 w-2/5" />
            <Skeleton class="h-3 w-16" />
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex items-center justify-between">
          <Skeleton class="h-3.5 w-14" />
          <Skeleton class="h-4 w-24" />
        </div>
      </section>
    </div>
  </div>
</template>
