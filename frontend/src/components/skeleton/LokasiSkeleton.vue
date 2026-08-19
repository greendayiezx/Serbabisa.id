<script setup lang="ts">
/**
 * Halaman pemilih lokasi (BisaAngkut & BisaBelanja).
 *
 * Proporsinya mengikuti komponen asli: hero SVG ber-viewBox 400x620 (rasio
 * 0,645, jadi aspect-[400/620]), lalu lembar konten yang ditarik naik -mt-14,
 * berisi pratinjau peta h-40, kolom cari, dua tombol simpan, dan daftar tempat.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface)">
    <!-- Hero ilustrasi -->
    <Skeleton class="w-full aspect-[400/620] rounded-none rounded-b-[2rem]" />

    <div class="max-w-[430px] mx-auto px-4 -mt-14 relative">
      <div class="bg-(--color-surface-0) rounded-3xl border border-(--color-outline)/20 p-4 flex flex-col gap-4">
        <!-- Pratinjau peta -->
        <Skeleton class="w-full h-40 rounded-2xl" />
        <!-- Kolom cari alamat -->
        <Skeleton class="h-12 w-full rounded-2xl" />
        <!-- Simpan Rumah / Kantor -->
        <div class="flex gap-3">
          <Skeleton class="h-9 flex-1 rounded-xl" />
          <Skeleton class="h-9 flex-1 rounded-xl" />
        </div>
        <Skeleton class="h-4 w-44" />
        <!-- Daftar tempat tersimpan -->
        <div v-for="i in 3" :key="i" class="flex items-center gap-3">
          <Skeleton class="w-9 h-9 rounded-xl shrink-0" />
          <div class="flex-1 flex flex-col gap-1.5">
            <Skeleton class="h-3 w-1/3" />
            <Skeleton class="h-2.5 w-4/5" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
