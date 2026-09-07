<script setup lang="ts">
/**
 * Avatar tukang BisaTukang — ilustrasi, bukan foto.
 *
 * Sepola dengan AvatarPengemudi milik BisaJemput dan CleanerAvatar milik
 * BisaBersih, dan alasannya sama: wajah orang sungguhan yang dipasang sebagai
 * "tukang yang akan datang ke rumahmu" tidak boleh dikarang. Selama aplikasi
 * mitra belum mengirimkan potret aslinya, yang tampil gambar berseragam —
 * jelas sebuah ilustrasi, dan tidak ada yang bisa salah mengira itu wajah
 * orang yang akan mengetuk pintunya.
 *
 * Sebelumnya bagian ini hanya inisial di atas lingkaran berwarna. Inisial tidak
 * salah, tapi juga tidak mengatakan apa-apa; sosok berhelm kerja menyampaikan
 * pekerjaannya sekali lihat.
 *
 * Warna seragam diturunkan dari nama, jadi mitra yang sama selalu tampil sama
 * dan tiga kartu berjajar tidak terlihat sebagai orang yang sama tiga kali.
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan dua avatar
 * di satu halaman dengan nama polos membuat yang kedua memakai definisi milik
 * yang pertama — kalau yang pertama dilepas, gradien yang kedua hilang tanpa
 * satu pun galat.
 */
import { computed, useId } from 'vue'

const props = defineProps<{ nama: string }>()

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

/** Tiga seragam kerja; dipilih tetap berdasarkan nama, bukan acak tiap render. */
const SERAGAM = [
  { terang: '#1683FF', gelap: '#0756D9', latar: '#E8F2FE', helm: '#F5A623', helmGelap: '#C97F05' },
  { terang: '#8BC53F', gelap: '#5E9127', latar: '#EEF7E2', helm: '#F5A623', helmGelap: '#C97F05' },
  { terang: '#F5A623', gelap: '#D07C05', latar: '#FEF3E2', helm: '#1683FF', helmGelap: '#0756D9' },
  { terang: '#3BBEB8', gelap: '#1F8983', latar: '#E4F6F5', helm: '#F5A623', helmGelap: '#C97F05' },
]

const s = computed(() => {
  let n = 0
  for (const c of props.nama) n = (n + c.charCodeAt(0)) % 997
  return SERAGAM[n % SERAGAM.length]
})

const alt = computed(() => (props.nama ? `Ilustrasi tukang ${props.nama}` : 'Ilustrasi tukang'))
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
        <linearGradient :id="id('seragam')" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" :stop-color="s.terang" />
          <stop offset="100%" :stop-color="s.gelap" />
        </linearGradient>
        <linearGradient :id="id('helm')" x1="0" y1="0" x2="0.4" y2="1">
          <stop offset="0%" :stop-color="s.helm" />
          <stop offset="100%" :stop-color="s.helmGelap" />
        </linearGradient>
        <linearGradient :id="id('kulit')" x1="0" y1="0" x2="0.3" y2="1">
          <stop offset="0%" stop-color="#F7C79B" />
          <stop offset="100%" stop-color="#E0A472" />
        </linearGradient>
      </defs>

      <!-- Latar lingkaran, senada seragamnya tapi jauh lebih terang -->
      <rect x="0" y="0" width="200" height="200" :fill="s.latar" />

      <!-- Bahu dan baju kerja -->
      <path d="M100 116 c26 0 46 17 46 42 v42 H54 v-42 c0-25 20-42 46-42 z" :fill="url('seragam')" />
      <!-- Kerah -->
      <path
        d="M84 118 L100 138 L116 118 L108 114 L100 126 L92 114 Z"
        fill="#FFFFFF"
        opacity="0.92"
      />
      <!-- Tali celemek tukang, melintang di dada -->
      <path
        d="M74 146 L126 168"
        :stroke="s.gelap"
        stroke-width="7"
        stroke-linecap="round"
        opacity="0.75"
      />
      <!-- Saku dada, penanda kecil bahwa ini pakaian kerja -->
      <rect x="112" y="140" width="20" height="16" rx="3" fill="#FFFFFF" opacity="0.85" />
      <path d="M117 140 v-4 h10 v4" fill="none" :stroke="s.gelap" stroke-width="3" />

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
      <path d="M82 68 q9 -5 17 -1" fill="none" stroke="#3A2A18" stroke-width="4" stroke-linecap="round" />
      <path d="M101 67 q9 -4 17 1" fill="none" stroke="#3A2A18" stroke-width="4" stroke-linecap="round" />
      <circle cx="89" cy="79" r="4.5" fill="#3A2A18" />
      <circle cx="111" cy="79" r="4.5" fill="#3A2A18" />
      <circle cx="90.5" cy="77.5" r="1.6" fill="#FFFFFF" />
      <circle cx="112.5" cy="77.5" r="1.6" fill="#FFFFFF" />
      <path d="M100 83 q4 6 -1 8" fill="none" stroke="#C98F5E" stroke-width="3" stroke-linecap="round" />
      <path d="M89 95 q11 9 22 0" fill="none" stroke="#3A2A18" stroke-width="4.5" stroke-linecap="round" />
      <ellipse cx="78" cy="88" rx="7" ry="5" fill="#FF9A9A" opacity="0.4" />
      <ellipse cx="122" cy="88" rx="7" ry="5" fill="#FF9A9A" opacity="0.4" />

      <!-- Rambut di bawah helm, supaya helmnya tidak tampak menempel di kulit -->
      <path d="M70 70 c-1 -14 8 -23 20 -23 h20 c12 0 21 9 20 23 c-6 -9 -16 -13 -30 -13 c-14 0 -24 4 -30 13 z" fill="#3A2A18" />

      <!-- Helm kerja: batok, bibir depan, dan rusuk tengah -->
      <path
        d="M68 64 c-2 -23 13 -38 32 -38 c19 0 34 15 32 38 z"
        :fill="url('helm')"
      />
      <path d="M62 64 h76 a4 4 0 0 1 4 4 v3 a3 3 0 0 1 -3 3 h-78 a3 3 0 0 1 -3 -3 v-3 a4 4 0 0 1 4 -4 z" :fill="s.helmGelap" />
      <path d="M100 27 v37" :stroke="s.helmGelap" stroke-width="4" opacity="0.55" />
    </svg>
  </span>
</template>
