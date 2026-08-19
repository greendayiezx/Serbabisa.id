<script setup lang="ts">
/**
 * Halaman kategori: header, kolom cari, baris chip filter, lalu grid produk
 * 3 kolom berkelompok. Bentuk kartunya memakai KatalogSkeleton yang sama
 * dipakai saat katalog dirender bertahap, jadi tidak ada lompatan tata letak
 * saat produk asli menggantikannya.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
import KatalogSkeleton from '@/components/belanja/KatalogSkeleton.vue'
import SkNavbar from './SkNavbar.vue'
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface)">
    <SkNavbar />
    <div class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <Skeleton class="h-11 w-full rounded-full" />
      <div class="flex gap-2 overflow-hidden">
        <Skeleton class="h-8 w-28 rounded-full shrink-0" />
        <Skeleton class="h-8 w-24 rounded-full shrink-0" />
        <Skeleton class="h-8 w-32 rounded-full shrink-0" />
      </div>
      <section v-for="i in 2" :key="i">
        <Skeleton class="h-3 w-28 mb-2.5" />
        <KatalogSkeleton :jumlah="9" />
      </section>
    </div>
  </div>
</template>
