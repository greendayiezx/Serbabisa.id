<script setup lang="ts">
/** Detail pengiriman: dua titik, paket, isi kiriman, peta, lalu lembar harga. */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-[21rem]">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <section class="bg-(--color-surface-0) rounded-2xl p-4 flex items-start gap-3">
        <div class="flex-1 flex flex-col gap-3">
          <div v-for="i in 2" :key="i" class="flex items-start gap-3">
            <Skeleton class="w-[22px] h-[22px]" bulat />
            <div class="flex-1 flex flex-col gap-1.5">
              <Skeleton class="h-3 w-1/3" />
              <Skeleton class="h-2.5 w-4/5" />
            </div>
          </div>
        </div>
        <Skeleton class="w-9 h-9 self-center" bulat />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-4">
        <div class="flex items-center gap-3">
          <Skeleton class="w-5 h-5" bulat />
          <div class="flex-1 flex flex-col gap-1.5">
            <Skeleton class="h-3 w-2/5" />
            <Skeleton class="h-2.5 w-1/3" />
          </div>
        </div>
        <Skeleton class="h-11 w-full rounded-xl" />
        <Skeleton class="h-11 w-full rounded-xl" />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-28 mb-2" />
        <Skeleton class="h-2.5 w-4/5 mb-3" />
        <div class="flex flex-col gap-2">
          <Skeleton v-for="i in 5" :key="i" class="h-11 w-full rounded-xl" />
        </div>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-4">
        <Skeleton class="h-2.5 w-40 mx-auto mb-3" />
        <Skeleton class="w-full h-44 rounded-xl" />
      </section>
    </div>

    <footer
      class="fixed bottom-0 inset-x-0 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-3"></div>
        <div class="flex flex-col gap-2 mb-3">
          <div
            v-for="i in 2"
            :key="i"
            class="flex items-center gap-3 rounded-2xl border-2 border-(--color-outline)/20 px-3.5 py-3"
          >
            <Skeleton class="w-10 h-10 rounded-xl" />
            <div class="flex-1 flex flex-col gap-1.5">
              <Skeleton class="h-3 w-2/5" />
              <Skeleton class="h-2.5 w-1/2" />
            </div>
            <Skeleton class="h-4 w-16" />
          </div>
        </div>
        <Skeleton class="h-12 w-full rounded-full" />
      </div>
    </footer>
  </div>
</template>
