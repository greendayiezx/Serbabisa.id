<script setup lang="ts">
/**
 * Avatar teknisi dari inisial namanya.
 *
 * Menggantikan foto Unsplash yang dipakai sebelumnya. Dua alasan, dan yang
 * kedua lebih penting daripada yang pertama:
 *
 * 1. Gambarnya diambil dari jaringan luar setiap kali layar dibuka — lambat di
 *    koneksi tipis, dan hilang sama sekali kalau situsnya tidak terjangkau.
 * 2. Yang tampil adalah WAJAH ORANG SUNGGUHAN yang tidak ada hubungannya
 *    dengan layanan ini, dipasang sebagai "teknisi yang akan datang ke rumah
 *    Anda". Foto contoh boleh salah; foto orang yang seolah-olah petugas yang
 *    akan Anda temui di pintu tidak boleh.
 *
 * Warnanya diturunkan dari nama, jadi teknisi yang sama selalu punya warna yang
 * sama — bukan warna acak yang berganti tiap render.
 */
import { computed } from 'vue'

const props = defineProps<{ nama: string }>()

const inisial = computed(() =>
  props.nama
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((k) => k[0]?.toUpperCase() ?? '')
    .join(''),
)

/** Dua warna merek yang dipilih tetap berdasarkan namanya. */
const PALET = [
  ['#1e9bf0', '#0B67B0'],
  ['#8BC53F', '#5E9127'],
  ['#F5A623', '#D07C05'],
  ['#3BBEB8', '#1F8983'],
]

const warna = computed(() => {
  let n = 0
  for (const c of props.nama) n = (n + c.charCodeAt(0)) % 997
  return PALET[n % PALET.length]
})
</script>

<template>
  <span
    class="inline-flex items-center justify-center rounded-full text-white font-display font-extrabold select-none"
    :style="{ background: `linear-gradient(135deg, ${warna[0]}, ${warna[1]})` }"
    :aria-label="`Foto profil ${nama}`"
    role="img"
  >
    {{ inisial }}
  </span>
</template>
