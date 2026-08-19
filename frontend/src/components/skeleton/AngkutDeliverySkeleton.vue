<script setup lang="ts">
/**
 * Halaman detail pengiriman BisaAngkut: navbar gelap, kartu alamat, kartu
 * rincian pesanan (layanan, barang, perlindungan, ukuran), lalu baris metode
 * pembayaran dan bar bayar.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) pb-56">
    <SkNavbar gelap />

    <div class="px-5 pt-4 flex flex-col gap-3.5">
      <!-- Alamat -->
      <div class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4 flex items-start gap-3">
        <Skeleton class="w-6 h-6 shrink-0 mt-0.5" bulat />
        <div class="flex-1 flex flex-col gap-2">
          <Skeleton class="h-3.5 w-1/3" />
          <Skeleton class="h-2.5 w-1/4" />
          <Skeleton class="h-2.5 w-full" />
        </div>
      </div>

      <!-- Rincian pesanan: 4 baris ikon + dua baris teks -->
      <div class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4 flex flex-col gap-4">
        <div v-for="i in 4" :key="i" class="flex items-center gap-3">
          <Skeleton class="w-6 h-6 shrink-0" />
          <div class="flex-1 flex flex-col gap-1.5">
            <Skeleton class="h-2.5 w-24" />
            <Skeleton class="h-3 w-3/5" />
          </div>
        </div>
      </div>

      <!-- Metode pembayaran -->
      <div class="bg-(--color-surface-0) rounded-(--radius-card) border border-(--color-outline)/40 p-4 flex items-center gap-3">
        <Skeleton class="w-9 h-9 rounded-xl shrink-0" />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-3 w-1/3" />
          <Skeleton class="h-2.5 w-3/5" />
        </div>
        <Skeleton class="w-4 h-4 shrink-0" />
      </div>
    </div>
  </div>
</template>
