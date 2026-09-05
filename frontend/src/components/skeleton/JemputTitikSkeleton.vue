<script setup lang="ts">
/**
 * Konfirmasi titik jemput: peta selayar penuh dengan lembar konfirmasi di
 * bawahnya.
 *
 * Bentuk dan tingginya menyalin halaman aslinya supaya tidak ada lompatan tata
 * letak saat konten menggantikannya — skeleton yang ukurannya berbeda hanya
 * memindahkan kedipan dari "layar kosong" ke "isi yang melompat".
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container)">
    <!-- Peta -->
    <Skeleton class="absolute inset-0 rounded-none" />

    <!-- Tombol kembali melayang -->
    <div class="absolute top-4 left-4 z-10">
      <Skeleton class="w-11 h-11" bulat />
    </div>

    <!-- Lembar konfirmasi -->
    <section
      class="absolute inset-x-0 bottom-0 z-10 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>

        <div class="flex items-center justify-between gap-3 mb-3">
          <Skeleton class="h-4 w-40" />
          <Skeleton class="h-9 w-20 rounded-full" />
        </div>

        <!-- Kartu alamat -->
        <div class="rounded-2xl bg-(--color-azure)/8 p-4 flex flex-col gap-2">
          <Skeleton class="h-3.5 w-4/5" />
          <Skeleton class="h-2.5 w-2/5" />
        </div>

        <Skeleton class="mt-3 h-12 w-full rounded-xl" />
        <Skeleton class="mt-3 h-2.5 w-full" />
        <Skeleton class="mt-1.5 h-2.5 w-3/5" />
        <Skeleton class="mt-3 h-12 w-full rounded-full" />
      </div>
    </section>
  </div>
</template>
