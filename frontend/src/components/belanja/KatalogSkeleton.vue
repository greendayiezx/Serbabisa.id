<script setup lang="ts">
/**
 * Placeholder grid produk 3 kolom, dipakai saat katalog belum selesai dirender.
 *
 * Bentuknya sengaja meniru kartu asli (kotak gambar persegi, harga, dua baris
 * nama, ukuran) supaya tidak ada lompatan tata letak saat konten muncul.
 */
import Skeleton from '@/components/ui/Skeleton.vue'

withDefaults(defineProps<{ jumlah?: number }>(), { jumlah: 9 })
</script>

<template>
  <div class="grid grid-cols-3 gap-2.5">
    <div
      v-for="i in jumlah"
      :key="i"
      class="bg-(--color-surface-0) rounded-2xl p-2 border border-(--color-outline)/20"
    >
      <Skeleton class="w-full aspect-square mb-2 rounded-xl" />
      <Skeleton class="h-3.5 w-3/5 mb-1.5" />
      <Skeleton class="h-2.5 w-full mb-1" />
      <Skeleton class="h-2.5 w-4/5" />
    </div>
  </div>
</template>
