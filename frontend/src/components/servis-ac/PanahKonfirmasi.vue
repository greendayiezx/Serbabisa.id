<script setup lang="ts">
/**
 * Panah animasi di ujung tombol konfirmasi.
 *
 * Dijadikan komponen supaya berkas Lottie-nya diimpor sekali, bukan di tiap
 * halaman yang memakai tombolnya — dan supaya ukurannya seragam. Tombol
 * konfirmasi di seluruh alur Servis AC harus terasa sebagai tombol yang sama;
 * panah yang ukurannya berbeda-beda membuatnya terbaca sebagai tiga tombol.
 */
import LottieIcon from '@/components/LottieIcon.vue'
import panahLottie from '@/assets/lottie/panah-konfirmasi.json'

withDefaults(defineProps<{ size?: number }>(), { size: 36 })
</script>

<template>
  <LottieIcon :data="panahLottie" :size="size" />
</template>
