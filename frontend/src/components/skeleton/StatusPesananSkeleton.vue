<script setup lang="ts">
/**
 * Halaman status pesanan: header dua baris, blok estimasi biru dengan
 * ilustrasi (viewBox 400x300), banner referral gelap, kartu status + jaminan,
 * Lokasi Tujuan, dan Detil Pesanan.
 *
 * Blok berlatar gelap memakai placeholder putih transparan, bukan abu, supaya
 * tetap terbaca sebagai "sedang memuat" dan bukan lubang di tengah warna.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-10">
    <!-- Header dua baris (judul + nomor pesanan) -->
    <div class="bg-(--color-surface-0)">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <Skeleton class="w-9 h-9 shrink-0" bulat />
        <div class="flex-1 flex flex-col items-center gap-1.5">
          <Skeleton class="h-4 w-32" />
          <Skeleton class="h-2.5 w-20" />
        </div>
        <Skeleton class="h-3.5 w-14 shrink-0" />
      </div>
    </div>

    <div class="max-w-[430px] mx-auto flex flex-col gap-2.5">
      <!-- Estimasi tiba + ilustrasi -->
      <section class="bg-[#2E9DF7] pt-5">
        <div class="flex flex-col items-center gap-2">
          <Skeleton class="h-3 w-24 bg-white/30" />
          <Skeleton class="h-7 w-40 bg-white/30" />
        </div>
        <Skeleton class="w-full aspect-[400/300] mt-2 rounded-none bg-white/20" />
      </section>

      <!-- Banner referral -->
      <section class="bg-[#1B1749] px-4 py-3.5 flex items-center gap-3">
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-2.5 w-full bg-white/25" />
          <Skeleton class="h-2.5 w-3/5 bg-white/25" />
        </div>
        <Skeleton class="h-9 w-24 rounded-lg shrink-0 bg-white/25" />
      </section>

      <!-- Status + jaminan -->
      <section class="bg-(--color-surface-0) px-4 py-4">
        <div class="flex items-center gap-2">
          <Skeleton class="h-4 w-44 flex-1" />
          <Skeleton class="h-6 w-24 rounded-full shrink-0" />
        </div>
        <Skeleton class="h-3 w-3/5 mt-2" />
        <div class="mt-3 rounded-xl p-3.5 border border-(--color-outline)/15 flex items-start gap-3">
          <Skeleton class="w-5 h-5 shrink-0" />
          <div class="flex-1 flex flex-col gap-1.5">
            <Skeleton class="h-3.5 w-4/5" />
            <Skeleton class="h-2.5 w-full" />
          </div>
        </div>
      </section>

      <!-- Lokasi Tujuan -->
      <section class="bg-(--color-surface-0) px-4 py-5 flex flex-col gap-2">
        <Skeleton class="h-4 w-32 mb-1" />
        <Skeleton class="h-3.5 w-2/5" />
        <Skeleton class="h-2.5 w-full" />
        <Skeleton class="h-3.5 w-32 mt-2" />
        <Skeleton class="h-2.5 w-1/2" />
      </section>

      <!-- Detil Pesanan -->
      <section class="bg-(--color-surface-0) px-4 py-5">
        <Skeleton class="h-4 w-32 mb-4" />
        <div v-for="i in 3" :key="i" class="py-3 flex items-center gap-3">
          <Skeleton class="h-3 w-7 shrink-0" />
          <Skeleton class="h-3 flex-1" />
          <Skeleton class="h-3 w-20 shrink-0" />
        </div>
        <div class="border-t border-(--color-outline)/12 pt-3 flex items-center justify-between">
          <Skeleton class="h-4 w-20" />
          <Skeleton class="h-4 w-24" />
        </div>
      </section>
    </div>
  </div>
</template>
