<script setup lang="ts">
/** Beranda BisaKirim: spanduk, kartu dua titik, alamat terakhir, keunggulan. */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-28">
    <Skeleton class="w-full h-[230px] rounded-none" />

    <div class="max-w-[430px] mx-auto px-4 -mt-8 relative flex flex-col gap-3.5">
      <section class="bg-(--color-surface-0) rounded-2xl shadow-lg p-4 flex items-start gap-3">
        <div class="flex-1 flex flex-col gap-3">
          <div v-for="i in 2" :key="i" class="flex items-start gap-3">
            <Skeleton class="w-[22px] h-[22px]" bulat />
            <div class="flex-1 flex flex-col gap-1.5">
              <Skeleton class="h-2.5 w-24" />
              <Skeleton class="h-3.5 w-4/5" />
            </div>
          </div>
        </div>
        <Skeleton class="w-10 h-10 self-center" bulat />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-4">
        <Skeleton class="h-2.5 w-28 mb-3" />
        <div v-for="i in 3" :key="i" class="flex items-start gap-3 py-2.5">
          <Skeleton class="w-4 h-4 mt-0.5" bulat />
          <div class="flex-1 flex flex-col gap-1.5">
            <Skeleton class="h-3 w-2/5" />
            <Skeleton class="h-2.5 w-4/5" />
          </div>
        </div>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <Skeleton class="h-3.5 w-40 mb-3" />
        <div class="flex flex-col gap-2.5">
          <div
            v-for="i in 3"
            :key="i"
            class="flex items-start gap-3 rounded-xl bg-(--color-surface-container) p-3.5"
          >
            <Skeleton class="w-9 h-9 rounded-xl" />
            <div class="flex-1 flex flex-col gap-1.5">
              <Skeleton class="h-3 w-1/3" />
              <Skeleton class="h-2.5 w-4/5" />
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
