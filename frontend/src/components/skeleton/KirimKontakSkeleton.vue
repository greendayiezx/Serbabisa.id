<script setup lang="ts">
/** Detail satu sisi kiriman: kartu titik, isian kontak, dan patokan. */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-32">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <section class="bg-(--color-surface-0) rounded-2xl p-4 flex items-start gap-3">
        <Skeleton class="w-[22px] h-[22px]" bulat />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-2.5 w-24" />
          <Skeleton class="h-3.5 w-4/5" />
        </div>
        <Skeleton class="h-3 w-10 self-center" />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
          <Skeleton class="h-3.5 w-32" />
          <Skeleton class="h-3 w-24" />
        </div>

        <Skeleton class="h-2.5 w-28 mb-1.5" />
        <Skeleton class="h-11 w-full rounded-xl mb-4" />

        <Skeleton class="h-2.5 w-28 mb-1.5" />
        <div class="flex items-center gap-3 mb-4">
          <Skeleton class="h-8 w-24 rounded-full" />
          <Skeleton class="h-6 flex-1" />
        </div>

        <Skeleton class="h-2.5 w-32 mb-1.5" />
        <Skeleton class="h-11 w-full rounded-xl" />
      </section>
    </div>

    <footer class="fixed bottom-0 inset-x-0 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <Skeleton class="h-12 w-full rounded-full" />
      </div>
    </footer>
  </div>
</template>
