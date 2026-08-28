<script setup lang="ts">
/**
 * Daftar "Tugas Saya": waktu pemesanan, gambar layanan, judul + nomor pesanan,
 * alamat tujuan, lalu baris status.
 *
 * Bentuknya mengikuti kartu aslinya baris demi baris supaya tingginya sama —
 * begitu data datang, tidak ada yang bergeser. Skeleton yang ukurannya asal
 * justru menambah lompatan yang ingin dicegahnya.
 *
 * Lebar judul dan alamat dibuat berbeda antar kartu; deretan balok seragam
 * terbaca sebagai tabel, bukan sebagai daftar yang sedang dimuat.
 */
import Skeleton from '@/components/ui/Skeleton.vue'

withDefaults(defineProps<{ jumlah?: number }>(), { jumlah: 4 })

const RAGAM = [
  { judul: 'w-[72%]', alamat: 'w-[80%]' },
  { judul: 'w-[58%]', alamat: 'w-[66%]' },
  { judul: 'w-[80%]', alamat: 'w-[54%]' },
  { judul: 'w-[64%]', alamat: 'w-[74%]' },
]
</script>

<template>
  <div class="space-y-3" role="status" aria-busy="true" aria-live="polite">
    <span class="sr-only">Memuat daftar tugas…</span>

    <div
      v-for="i in jumlah"
      :key="i"
      class="rounded-(--radius-card) bg-(--color-surface-0) border border-(--color-outline)/60 p-4"
    >
      <Skeleton class="h-2.5 w-24" />

      <div class="mt-2 flex items-start gap-3">
        <!-- Kotak gambar layanan -->
        <Skeleton class="w-20 h-20 shrink-0 rounded-xl" />

        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <Skeleton :class="`h-[15px] ${RAGAM[(i - 1) % RAGAM.length].judul}`" />
              <Skeleton class="mt-1.5 h-3 w-24" />
            </div>
            <Skeleton class="h-[14px] w-20 shrink-0" />
          </div>

          <div class="mt-2.5 flex items-center gap-1.5">
            <Skeleton bulat class="h-3.5 w-3.5 shrink-0" />
            <Skeleton :class="`h-3 ${RAGAM[(i - 1) % RAGAM.length].alamat}`" />
          </div>

          <div class="mt-3 flex items-center justify-between gap-3">
            <Skeleton class="h-3.5 w-28" />
            <Skeleton class="h-8 w-24 shrink-0 rounded-full" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
