<script setup lang="ts">
/**
 * Halaman konfirmasi BisaAngkut: pratinjau peta (h-40 di dalam kartu), Detail
 * Penerima, Ringkasan Layanan, dan Perlindungan barang.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) pb-28">
    <SkNavbar gelap />

    <div class="max-w-[430px] mx-auto px-5 pt-5 flex flex-col gap-5">
      <!-- Kartu peta -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 overflow-hidden">
        <Skeleton class="w-full h-40 rounded-none" />
        <div class="p-4 flex flex-col gap-2">
          <Skeleton class="h-3 w-1/3" />
          <Skeleton class="h-2.5 w-4/5" />
        </div>
      </section>

      <!-- Detail Penerima -->
      <section class="flex flex-col gap-3.5">
        <Skeleton class="h-3.5 w-36" />
        <div class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4 flex flex-col gap-3">
          <Skeleton class="h-11 w-full rounded-xl" />
          <Skeleton class="h-11 w-full rounded-xl" />
          <Skeleton class="h-11 w-full rounded-xl" />
        </div>
      </section>

      <!-- Ringkasan Layanan -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4">
        <Skeleton class="h-3.5 w-40 mb-3.5" />
        <div class="flex flex-col gap-3">
          <div v-for="i in 4" :key="i" class="flex items-center justify-between gap-3">
            <Skeleton class="h-3 w-2/5" />
            <Skeleton class="h-3 w-20" />
          </div>
        </div>
      </section>

      <!-- Perlindungan: 3 pilihan berjajar -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4">
        <Skeleton class="h-3.5 w-2/3 mb-3.5" />
        <div class="grid grid-cols-3 gap-2.5">
          <div
            v-for="i in 3"
            :key="i"
            class="rounded-2xl border border-(--color-outline)/25 p-3 flex flex-col items-center gap-2"
          >
            <Skeleton class="w-10 h-10" bulat />
            <Skeleton class="h-2.5 w-4/5" />
            <Skeleton class="h-2.5 w-3/5" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
