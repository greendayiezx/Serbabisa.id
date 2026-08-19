<script setup lang="ts">
/**
 * Halaman status pembayaran: header, ilustrasi (viewBox 400x240), judul dan
 * subjudul di tengah, lalu kartu berisi nomor invoice + tombol Unduh PDF dan
 * tiga baris rincian.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-32">
    <SkNavbar />

    <section class="bg-(--color-surface-0) px-4 pb-8">
      <div class="max-w-[430px] mx-auto">
        <div class="px-6 pt-2">
          <Skeleton class="w-full aspect-[400/240] rounded-2xl" />
        </div>
        <div class="flex flex-col items-center gap-2.5 mt-4">
          <Skeleton class="h-5 w-52" />
          <Skeleton class="h-3 w-64" />
        </div>
      </div>
    </section>

    <section class="bg-(--color-surface-0) px-4 py-5 mt-2.5">
      <div class="max-w-[430px] mx-auto flex flex-col gap-3.5">
        <div class="flex items-start gap-3">
          <Skeleton class="h-4 flex-1" />
          <Skeleton class="h-9 w-28 rounded-full shrink-0" />
        </div>
        <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-3">
          <Skeleton class="h-3 w-2/5" />
          <Skeleton class="h-3 w-28" />
        </div>
      </div>
    </section>
  </div>
</template>
