<script setup lang="ts">
/**
 * Beranda BisaKirim: banner promo, kartu dua titik beserta alamat terakhir,
 * kartu voucher, dan keunggulan.
 *
 * Tinggi bannernya mengikuti rasio gambar aslinya (1764x892) ditambah pita
 * titik penanda — skeleton yang ukurannya berbeda hanya memindahkan kedipan
 * dari "layar kosong" ke "isi yang melompat".
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-28">
    <div class="w-full" style="background: #01226a">
      <Skeleton class="w-full aspect-[1764/892] rounded-none" />
      <div class="h-12 flex items-center justify-center gap-1.5">
        <span class="block w-5 h-1.5 rounded-full bg-white/60"></span>
        <span class="block w-1.5 h-1.5 rounded-full bg-white/30"></span>
      </div>
    </div>

    <div class="max-w-[430px] mx-auto px-4 -mt-5 relative flex flex-col gap-3.5">
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

      <div class="bg-(--color-surface-0) rounded-2xl p-4 flex items-center gap-3">
        <Skeleton class="w-10 h-10 rounded-xl" />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-3.5 w-2/5" />
          <Skeleton class="h-2.5 w-3/5" />
        </div>
      </div>

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
