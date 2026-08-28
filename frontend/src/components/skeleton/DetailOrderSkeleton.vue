<script setup lang="ts">
/**
 * Halaman Detail Order: kartu alamat, kartu layanan (dengan gambar, status,
 * invoice, jadwal), kartu cleaner, lalu rincian harga.
 *
 * Urutan kartunya sengaja mengikuti halaman aslinya. Pengguna yang datang dari
 * layar tunggu sudah tahu apa yang ia cari, dan kerangka yang sesuai membuat
 * matanya berada di tempat yang benar sebelum datanya sampai.
 *
 * Yang ditiru hanya RUANG-nya, bukan isinya: kerangka yang terlalu menyerupai
 * teks asli membuat orang mengira data sudah tampil lalu membacanya.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="flex flex-col gap-3.5" role="status" aria-busy="true" aria-live="polite">
    <span class="sr-only">Memuat detail pesanan…</span>

    <!-- Alamat pengerjaan -->
    <section class="bg-(--color-surface-0) rounded-2xl p-5">
      <Skeleton class="h-[15px] w-40" />
      <Skeleton class="mt-3 h-[14px] w-[55%]" />
      <Skeleton class="mt-2 h-3 w-[80%]" />
    </section>

    <!-- Layanan, status, invoice, jadwal -->
    <section class="bg-(--color-surface-0) rounded-2xl p-5">
      <div class="flex items-start gap-3.5">
        <div class="flex-1">
          <Skeleton class="h-[17px] w-32" />
          <Skeleton class="mt-2.5 h-3 w-44" />
          <Skeleton class="mt-2 h-3 w-24" />
        </div>
        <Skeleton class="w-16 h-16 rounded-xl shrink-0" />
      </div>

      <div
        v-for="i in 3"
        :key="i"
        class="mt-3 pt-3 border-t border-(--color-outline)/10 flex items-center justify-between gap-3"
      >
        <div class="flex-1">
          <Skeleton class="h-2.5 w-16" />
          <Skeleton class="mt-2 h-[15px] w-40" />
        </div>
        <Skeleton class="h-3.5 w-14 shrink-0" />
      </div>
    </section>

    <!-- Cleaner -->
    <section class="bg-(--color-surface-0) rounded-2xl p-5">
      <div class="flex items-center justify-between gap-3 mb-4">
        <Skeleton class="h-[15px] w-28" />
        <div class="flex items-center gap-2">
          <Skeleton bulat class="w-8 h-8" />
          <Skeleton bulat class="w-8 h-8" />
        </div>
      </div>
      <div class="flex items-center gap-3.5">
        <Skeleton bulat class="w-12 h-12 shrink-0" />
        <div class="flex-1">
          <Skeleton class="h-[15px] w-36" />
          <Skeleton class="mt-2 h-3 w-28" />
        </div>
      </div>
    </section>

    <!-- Rincian -->
    <section class="bg-(--color-surface-0) rounded-2xl p-5">
      <Skeleton class="h-[15px] w-36" />
      <div v-for="i in 3" :key="i" class="mt-3 flex items-center justify-between gap-3">
        <Skeleton class="h-3 w-[45%]" />
        <Skeleton class="h-3 w-20 shrink-0" />
      </div>
      <div class="mt-4 pt-3 border-t border-(--color-outline)/12 flex items-center justify-between gap-3">
        <Skeleton class="h-[15px] w-16" />
        <Skeleton class="h-[18px] w-28 shrink-0" />
      </div>
    </section>
  </div>
</template>
