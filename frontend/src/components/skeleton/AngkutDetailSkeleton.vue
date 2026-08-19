<script setup lang="ts">
/**
 * Halaman detail BisaAngkut: navbar gelap, lalu empat section — Kendaraan &
 * Layanan (kartu pilihan), Detail Barang Bawaan, Jadwal Pengambilan, dan
 * Butuh Helper.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
import SkKartu from './SkKartu.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) pb-28">
    <SkNavbar gelap />

    <div class="max-w-[430px] mx-auto px-5 pt-5 flex flex-col gap-5">
      <!-- Kendaraan & Layanan: judul + 3 kartu pilihan kendaraan -->
      <section class="flex flex-col gap-3.5">
        <Skeleton class="h-3.5 w-44" />
        <div
          v-for="i in 3"
          :key="i"
          class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4 flex items-center gap-3.5"
        >
          <Skeleton class="w-14 h-14 rounded-2xl shrink-0" />
          <div class="flex-1 flex flex-col gap-2">
            <Skeleton class="h-3.5 w-1/2" />
            <Skeleton class="h-2.5 w-4/5" />
          </div>
          <Skeleton class="h-3.5 w-16 shrink-0" />
        </div>
      </section>

      <!-- Detail Barang Bawaan -->
      <section class="flex flex-col gap-3.5">
        <Skeleton class="h-3.5 w-40" />
        <SkKartu :judul="false" :baris="3" />
      </section>

      <!-- Jadwal Pengambilan -->
      <section class="flex flex-col gap-3.5">
        <Skeleton class="h-3.5 w-40" />
        <div class="flex gap-3">
          <Skeleton class="h-12 flex-1 rounded-2xl" />
          <Skeleton class="h-12 flex-1 rounded-2xl" />
        </div>
      </section>

      <!-- Butuh Helper -->
      <SkKartu :baris="2" />
    </div>
  </div>
</template>
