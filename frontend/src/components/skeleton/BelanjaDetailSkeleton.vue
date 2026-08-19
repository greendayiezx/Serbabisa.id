<script setup lang="ts">
/**
 * Halaman daftar belanja: navbar gelap, kolom cari, kartu Lokasi Belanja
 * (dropdown toko + alamat), kartu Daftar Belanja dengan grid kategori 4 kolom
 * (13 tile), lalu kartu Instruksi Khusus.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) pb-32">
    <SkNavbar gelap />

    <!-- Kolom cari -->
    <div class="max-w-[430px] mx-auto px-4 pt-3 pb-1">
      <Skeleton class="h-12 w-full rounded-2xl" />
    </div>

    <div class="max-w-[430px] mx-auto px-4 pt-5 flex flex-col gap-5">
      <!-- Lokasi Belanja -->
      <section class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/10 p-5 flex flex-col gap-3.5">
        <div class="flex items-center gap-2">
          <Skeleton class="w-6 h-6 shrink-0" />
          <Skeleton class="h-3.5 w-32" />
        </div>
        <Skeleton class="h-14 w-full rounded-xl" />
        <Skeleton class="h-14 w-full rounded-xl" />
      </section>

      <!-- Daftar Belanja: grid kategori 4 kolom -->
      <section class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/10 p-5">
        <div class="flex items-center gap-2 mb-4">
          <Skeleton class="w-6 h-6 shrink-0" />
          <Skeleton class="h-3.5 w-28" />
        </div>
        <div class="grid grid-cols-4 gap-2">
          <div
            v-for="i in 13"
            :key="i"
            class="rounded-xl border-2 border-(--color-outline)/10 p-2.5 flex flex-col items-center gap-1.5"
          >
            <Skeleton class="w-14 h-14 rounded-lg" />
            <Skeleton class="h-2 w-4/5" />
          </div>
        </div>
      </section>

      <!-- Instruksi Khusus -->
      <section class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/10 p-5">
        <div class="flex items-center gap-2 mb-4">
          <Skeleton class="w-6 h-6 shrink-0" />
          <Skeleton class="h-3.5 w-48" />
        </div>
        <Skeleton class="h-20 w-full rounded-xl" />
      </section>
    </div>
  </div>
</template>
