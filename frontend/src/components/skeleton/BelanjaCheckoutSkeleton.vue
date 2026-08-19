<script setup lang="ts">
/**
 * Halaman checkout: header putih, lalu deretan section berlatar putih dipisah
 * jarak 2,5 — Alamat Pengiriman, Pengiriman 45–60 Menit, daftar barang,
 * Tambah Catatan, baris promo, dua banner (rasio 1000x169), rincian biaya,
 * dan rekomendasi bergulir.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) pb-40">
    <SkNavbar />

    <div class="max-w-[430px] mx-auto flex flex-col gap-2.5 pt-2.5">
      <!-- Alamat Pengiriman -->
      <section class="bg-(--color-surface-0) px-4 py-5">
        <Skeleton class="h-4 w-40 mb-3" />
        <div class="border border-(--color-outline)/20 rounded-2xl p-3.5 flex items-start gap-3">
          <Skeleton class="w-5 h-5 shrink-0 mt-0.5" />
          <div class="flex-1 flex flex-col gap-2">
            <Skeleton class="h-3.5 w-1/3" />
            <Skeleton class="h-2.5 w-full" />
          </div>
          <Skeleton class="w-6 h-6 shrink-0" />
        </div>
      </section>

      <!-- Estimasi pengiriman -->
      <section class="bg-(--color-surface-0) px-4 py-4 flex items-center gap-2">
        <Skeleton class="h-4 flex-1" />
        <Skeleton class="w-4 h-4 shrink-0" />
      </section>

      <!-- Daftar barang -->
      <section class="bg-(--color-surface-0) px-4 py-2">
        <div v-for="i in 2" :key="i" class="py-3.5 flex items-center gap-3">
          <div class="flex-1 flex flex-col gap-2">
            <Skeleton class="h-3.5 w-3/5" />
            <Skeleton class="h-3 w-20" />
          </div>
          <Skeleton class="w-24 h-9 rounded-xl shrink-0" />
        </div>
      </section>

      <!-- Tambah Catatan -->
      <section class="bg-(--color-surface-0) px-4 py-4 flex items-center gap-2.5">
        <Skeleton class="w-5 h-5 shrink-0" />
        <Skeleton class="h-3.5 w-32" />
      </section>

      <!-- Baris promo -->
      <section class="bg-(--color-surface-0) px-4 py-4 flex items-center gap-3">
        <Skeleton class="w-10 h-10 rounded-xl shrink-0" />
        <div class="flex-1 flex flex-col gap-1.5">
          <Skeleton class="h-3.5 w-2/5" />
          <Skeleton class="h-2.5 w-1/2" />
        </div>
        <Skeleton class="w-4 h-4 shrink-0" />
      </section>

      <!-- Dua banner: Langganan & Jaminan Garansi, rasio 1000x169 -->
      <section class="px-4"><Skeleton class="w-full aspect-[1000/169] rounded-2xl" /></section>
      <section class="px-4"><Skeleton class="w-full aspect-[1000/169] rounded-2xl" /></section>

      <!-- Rincian biaya -->
      <section class="bg-(--color-surface-0) px-4 py-5 flex flex-col gap-2.5">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between">
          <Skeleton class="h-3 w-2/5" />
          <Skeleton class="h-3 w-20" />
        </div>
        <div class="border-t border-(--color-outline)/15 pt-2.5 flex items-center justify-between">
          <Skeleton class="h-4 w-28" />
          <Skeleton class="h-4 w-24" />
        </div>
      </section>

      <!-- Rekomendasi bergulir -->
      <section class="bg-(--color-surface-0) px-4 py-5">
        <Skeleton class="h-4 w-52 mb-3" />
        <div class="flex gap-2.5 overflow-hidden">
          <div
            v-for="i in 4"
            :key="i"
            class="w-[112px] shrink-0 rounded-2xl border border-(--color-outline)/20 p-2"
          >
            <Skeleton class="w-full aspect-square rounded-xl mb-2" />
            <Skeleton class="h-3 w-3/5 mb-1.5" />
            <Skeleton class="h-2.5 w-full" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
