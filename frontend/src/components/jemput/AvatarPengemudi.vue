<script setup lang="ts">
/**
 * Avatar pengemudi BisaJemput — ilustrasi, bukan foto.
 *
 * Sepola dengan CleanerAvatar milik BisaBersih, dan alasannya sama: foto orang
 * sungguhan yang dipasang sebagai "pengemudi yang akan menjemput kamu" tidak
 * boleh dikarang. Selama aplikasi pengemudi belum mengirimkan potret aslinya,
 * yang tampil adalah gambar seragam — jelas sebuah ilustrasi, dan tidak ada
 * yang bisa salah mengira itu wajah orang yang akan datang.
 *
 * viewBox dipangkas ke kepala–bahu supaya penuh mengisi lingkaran; ukurannya
 * ditentukan induk lewat class (mis. w-14 h-14).
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan dua avatar
 * di satu halaman dengan nama polos akan membuat yang kedua memakai definisi
 * milik yang pertama — kalau yang pertama dilepas, gradien yang kedua hilang
 * tanpa satu pun galat, dan seragamnya sekadar jadi hitam.
 */
import { computed, useId } from 'vue'

const props = withDefaults(defineProps<{ nama?: string; kelas?: string | null }>(), {
  nama: '',
  kelas: null,
})

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

/** Pengemudi motor berhelm; pengemudi mobil tanpa helm. */
const motor = computed(() => (props.kelas ?? '').startsWith('motor'))

const alt = computed(() =>
  props.nama ? `Ilustrasi pengemudi ${props.nama}` : 'Ilustrasi pengemudi',
)
</script>

<template>
  <span class="relative block overflow-hidden rounded-full bg-(--color-surface-container)">
    <svg
      viewBox="40 26 120 120"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full block"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      :aria-label="alt"
    >
      <defs>
        <linearGradient :id="id('biru')" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#1683FF" />
          <stop offset="100%" stop-color="#0756D9" />
        </linearGradient>
        <linearGradient :id="id('biruTua')" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#084EBD" />
          <stop offset="100%" stop-color="#06348E" />
        </linearGradient>
        <linearGradient :id="id('kulit')" x1="0" y1="0" x2="0.3" y2="1">
          <stop offset="0%" stop-color="#F7C79B" />
          <stop offset="100%" stop-color="#E0A472" />
        </linearGradient>
      </defs>

      <!-- Latar lingkaran, sedikit lebih terang dari seragamnya -->
      <rect x="0" y="0" width="200" height="200" fill="#E8F2FE" />

      <!-- Bahu dan seragam -->
      <path
        d="M100 116 c26 0 46 17 46 42 v42 H54 v-42 c0-25 20-42 46-42 z"
        :fill="url('biru')"
      />
      <!-- Kerah -->
      <path d="M84 118 L100 138 L116 118 L108 114 L100 126 L92 114 Z" fill="#FFFFFF" opacity="0.92" />
      <!-- Garis reflektif, penanda seragam yang sama dengan ikon di peta -->
      <path d="M62 168 h76" stroke="#8BC53F" stroke-width="6" stroke-linecap="round" />

      <!-- Leher -->
      <path d="M90 100 h20 v20 h-20 z" :fill="url('kulit')" />
      <path d="M90 100 h20 v9 a10 10 0 0 1 -20 0 z" fill="#C98F5E" opacity="0.5" />

      <!-- Telinga -->
      <circle cx="70" cy="76" r="7" :fill="url('kulit')" />
      <circle cx="130" cy="76" r="7" :fill="url('kulit')" />

      <!-- Wajah -->
      <path
        d="M100 40 c18 0 30 13 30 32 c0 20 -13 33 -30 33 c-17 0 -30 -13 -30 -33 c0 -19 12 -32 30 -32 z"
        :fill="url('kulit')"
      />

      <!-- Alis, mata, hidung, senyum -->
      <path d="M82 68 q9 -5 17 -1" fill="none" stroke="#25324F" stroke-width="4" stroke-linecap="round" />
      <path d="M101 67 q9 -4 17 1" fill="none" stroke="#25324F" stroke-width="4" stroke-linecap="round" />
      <circle cx="89" cy="79" r="4.5" fill="#1B3268" />
      <circle cx="111" cy="79" r="4.5" fill="#1B3268" />
      <circle cx="90.5" cy="77.5" r="1.6" fill="#FFFFFF" />
      <circle cx="112.5" cy="77.5" r="1.6" fill="#FFFFFF" />
      <path d="M100 83 q4 6 -1 8" fill="none" stroke="#C98F5E" stroke-width="3" stroke-linecap="round" />
      <path d="M89 95 q11 9 22 0" fill="none" stroke="#1B3268" stroke-width="4.5" stroke-linecap="round" />
      <ellipse cx="78" cy="88" rx="7" ry="5" fill="#FF9A9A" opacity="0.4" />
      <ellipse cx="122" cy="88" rx="7" ry="5" fill="#FF9A9A" opacity="0.4" />

      <!-- Helm untuk pengemudi motor; rambut untuk pengemudi mobil -->
      <template v-if="motor">
        <path
          d="M68 66 c-2 -22 13 -37 32 -37 c19 0 34 15 32 37 l-8 0 c-3 -14 -12 -22 -24 -22 c-12 0 -21 8 -24 22 z"
          :fill="url('biru')"
        />
        <path d="M68 62 h64 v9 a4 4 0 0 1 -4 4 h-56 a4 4 0 0 1 -4 -4 z" :fill="url('biruTua')" />
        <circle cx="100" cy="35" r="5" fill="#8BC53F" />
      </template>
      <template v-else>
        <path
          d="M70 70 c-2 -23 13 -38 30 -38 c17 0 32 15 30 38 c-5 -13 -16 -19 -30 -19 c-14 0 -25 6 -30 19 z"
          fill="#25324F"
        />
      </template>
    </svg>
  </span>
</template>
