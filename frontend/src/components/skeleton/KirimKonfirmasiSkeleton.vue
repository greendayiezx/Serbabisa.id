<script setup lang="ts">
/** Detail pengirim & penerima: peta rute, isian, rincian biaya, pembayaran. */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-36">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Peta rute: setinggi PetaRuteKirim (h-44) di dalam kartu p-3. -->
      <section class="bg-(--color-surface-0) rounded-2xl p-3">
        <Skeleton class="h-44 w-full rounded-xl" />
        <div class="px-1 pt-3 flex flex-col gap-2">
          <Skeleton class="h-3 w-4/5" />
          <Skeleton class="h-3 w-3/5" />
        </div>
      </section>

      <section v-for="i in 2" :key="i" class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-32 mb-3" />
        <Skeleton class="h-2.5 w-4/5 mb-3" />
        <div class="flex flex-col gap-2.5">
          <Skeleton v-for="j in 3" :key="j" class="h-11 w-full rounded-xl" />
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
        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex items-center justify-between">
          <Skeleton class="h-3.5 w-14" />
          <Skeleton class="h-4 w-24" />
        </div>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-3">
        <Skeleton class="w-5 h-5" bulat />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-3 w-24" />
          <Skeleton class="h-2.5 w-32" />
        </div>
      </section>
    </div>

    <footer class="fixed bottom-0 inset-x-0 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-2.5">
          <Skeleton class="h-3 w-20" />
          <Skeleton class="h-4 w-24" />
        </div>
        <Skeleton class="h-12 w-full rounded-full" />
      </div>
    </footer>
  </div>
</template>
