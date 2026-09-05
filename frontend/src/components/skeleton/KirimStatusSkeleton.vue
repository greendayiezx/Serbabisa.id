<script setup lang="ts">
/**
 * Status kiriman: kartu tahap, rute, dan rincian.
 *
 * Kartu kurir dan kode terima TIDAK ditiru: keduanya belum tentu ada, dan
 * menggambar kerangkanya lebih dulu menjanjikan sesuatu yang mungkin tidak
 * pernah muncul.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-16">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <section class="bg-(--color-surface-0) rounded-2xl p-6 flex flex-col items-center">
        <Skeleton class="w-[140px] h-[140px]" bulat />
        <Skeleton class="mt-3 h-4 w-4/5" />
        <Skeleton class="mt-2 h-2.5 w-3/5" />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex gap-3">
          <div class="flex flex-col items-center pt-1">
            <Skeleton class="w-3 h-3" bulat />
            <span class="w-0.5 flex-1 my-1 bg-(--color-outline)/20"></span>
            <Skeleton class="w-3 h-3" bulat />
          </div>
          <div class="flex-1 flex flex-col gap-4">
            <div v-for="i in 2" :key="i" class="flex flex-col gap-1.5">
              <Skeleton class="h-2.5 w-24" />
              <Skeleton class="h-3 w-4/5" />
              <Skeleton class="h-2.5 w-2/5" />
            </div>
          </div>
        </div>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-28 mb-3" />
        <div class="flex flex-col gap-2.5">
          <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-3">
            <Skeleton class="h-3 w-2/5" />
            <Skeleton class="h-3 w-16" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
