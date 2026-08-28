<script setup lang="ts">
import { computed } from 'vue'
/**
 * Avatar mitra BisaBersih: ilustrasi (bukan foto) sesuai jenis kelamin.
 *
 * viewBox tiap SVG dipangkas ke area kepala–bahu supaya pas jadi avatar bulat
 * (preserveAspectRatio "slice" mengisi lingkaran penuh). Ukuran ditentukan induk
 * lewat class (mis. w-12 h-12) — komponen ini hanya klip lingkaran + gambar.
 *
 * Semua id gradient/filter diberi awalan clpr-/clwn- (cleaner pria/wanita) supaya
 * tidak bentrok dengan SVG lain di halaman. Beberapa avatar dengan gender sama
 * memakai id yang identik — aman karena definisinya sama persis.
 */
/**
 * `gender` boleh kosong: data mitra dari server tidak memuatnya, dan menebak
 * jenis kelamin dari nama bukan sesuatu yang boleh dilakukan halaman ini. Tanpa
 * gender, yang tampil adalah inisial namanya.
 */
const props = defineProps<{ gender?: 'pria' | 'wanita'; nama?: string }>()

/** Huruf depan nama, untuk avatar netral. */
const inisial = computed(() => (props.nama ?? '?').trim().charAt(0).toUpperCase() || '?')
</script>

<template>
  <span class="relative block overflow-hidden rounded-full bg-(--color-surface-container)">
    <!-- ============ WANITA (seragam biru BisaBersih) ============ -->
    <svg
      v-if="gender === 'wanita'"
      viewBox="28 10 112 112"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full block"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id="clwn-blue" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#2196F3" />
          <stop offset="100%" stop-color="#0A326B" />
        </linearGradient>
        <linearGradient id="clwn-lime" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#C8FF70" />
          <stop offset="100%" stop-color="#8BC53F" />
        </linearGradient>
        <linearGradient id="clwn-skin" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#FFD0A8" />
          <stop offset="100%" stop-color="#D99168" />
        </linearGradient>
      </defs>

      <!-- HAIR -->
      <path d="M54 46 C53 28 65 14 82 14 C101 14 113 28 111 48 C110 62 101 71 94 74 L61 70 C55 63 53 55 54 46Z" fill="#26354A" />
      <!-- FACE -->
      <path d="M62 41 C62 28 71 21 83 21 C98 21 106 31 105 46 C104 60 96 68 84 68 C72 68 63 59 62 41Z" fill="url(#clwn-skin)" />
      <!-- Hair front -->
      <path d="M58 40 C59 23 70 17 84 18 C98 18 107 26 109 38 C101 34 94 30 88 26 C81 35 70 40 58 40Z" fill="#26354A" />
      <!-- Ear -->
      <circle cx="105" cy="46" r="5" fill="#D99168" />
      <!-- Eyes -->
      <circle cx="75" cy="46" r="2.4" fill="#0A326B" />
      <circle cx="94" cy="46" r="2.4" fill="#0A326B" />
      <!-- Eyebrows -->
      <path d="M71 41 Q75 38 79 41" fill="none" stroke="#26354A" stroke-width="2" stroke-linecap="round" />
      <path d="M91 41 Q95 38 98 41" fill="none" stroke="#26354A" stroke-width="2" stroke-linecap="round" />
      <!-- Nose -->
      <path d="M84 47 L82 54 L86 55" fill="none" stroke="#B96F55" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
      <!-- Smile -->
      <path d="M78 58 Q84 62 91 58" fill="none" stroke="#963F42" stroke-width="2" stroke-linecap="round" />
      <!-- NECK -->
      <path d="M77 66 V78 H94 V66" fill="url(#clwn-skin)" />
      <!-- UNIFORM -->
      <path d="M57 76 C65 71 73 70 84 73 C96 70 105 72 113 78 L127 137 H43Z" fill="url(#clwn-blue)" />
      <!-- Collar -->
      <path d="M72 74 L84 86 L96 74" fill="#FFFFFF" />
      <path d="M78 76 L84 84 L90 76" fill="none" stroke="#8BC53F" stroke-width="3" />
      <!-- LOGO -->
      <g transform="translate(72 94)">
        <rect x="0" y="0" width="27" height="27" rx="7" fill="#FFFFFF" />
        <path d="M6 14 L13.5 8 L21 14" fill="none" stroke="#0A326B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M8 13 V20 H19 V13" fill="none" stroke="#0A326B" stroke-width="2.5" />
        <path d="M21 5 L22.5 8 L25.5 9.5 L22.5 11 L21 14 L19.5 11 L16.5 9.5 L19.5 8Z" fill="#8BC53F" />
      </g>
      <!-- LEFT ARM -->
      <path d="M58 81 C50 88 45 100 42 111 L30 124" fill="none" stroke="#0A326B" stroke-width="15" stroke-linecap="round" />
      <circle cx="29" cy="126" r="8" fill="url(#clwn-skin)" />
      <!-- RIGHT ARM -->
      <path d="M108 82 C117 91 120 104 121 116 L131 124" fill="none" stroke="#0A326B" stroke-width="15" stroke-linecap="round" />
      <circle cx="133" cy="126" r="8" fill="url(#clwn-skin)" />
      <!-- SPRAY BOTTLE -->
      <g transform="translate(119 113) rotate(-8)">
        <path d="M3 12 H18 L17 34 H5Z" fill="#FFFFFF" stroke="#0A326B" stroke-width="2.5" />
        <path d="M5 23 C9 20 13 25 17 22 V33 H5Z" fill="#8BC53F" />
        <rect x="7" y="6" width="8" height="7" rx="1.5" fill="#8BC53F" stroke="#0A326B" stroke-width="2" />
        <path d="M9 6 V2 H16 L20 5 H25" fill="none" stroke="#0A326B" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
      </g>
      <!-- CLOTH -->
      <path d="M17 123 C22 118 30 120 35 124 L31 136 H20Z" fill="url(#clwn-lime)" stroke="#0A326B" stroke-width="2" />
      <!-- UNIFORM ACCENT -->
      <path d="M47 136 H120" stroke="#8BC53F" stroke-width="5" stroke-linecap="round" />
      <!-- SPARKLES -->
      <path d="M30 45 L33 52 L40 55 L33 58 L30 65 L27 58 L20 55 L27 52Z" fill="#C8FF00" />
      <path d="M128 54 L130 59 L135 61 L130 63 L128 68 L126 63 L121 61 L126 59Z" fill="#F4C542" />
      <circle cx="41" cy="75" r="3" fill="#8BC53F" />
      <circle cx="126" cy="78" r="2.5" fill="#2196F3" />
    </svg>

    <!-- ============ PRIA (seragam lime + topi) ============ -->
    <svg
      v-else-if="gender === 'pria'"
      viewBox="96 48 208 208"
      preserveAspectRatio="xMidYMid slice"
      class="w-full h-full block"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id="clpr-lime" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#C2F55A" />
          <stop offset="100%" stop-color="#63C21C" />
        </linearGradient>
        <linearGradient id="clpr-limeDark" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#8FDD3A" />
          <stop offset="100%" stop-color="#4A9E12" />
        </linearGradient>
        <linearGradient id="clpr-navy" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#2C4B9E" />
          <stop offset="100%" stop-color="#152A6B" />
        </linearGradient>
        <linearGradient id="clpr-skin" x1="0" y1="0" x2="0.4" y2="1">
          <stop offset="0%" stop-color="#FFD9B4" />
          <stop offset="100%" stop-color="#EFB07C" />
        </linearGradient>
        <linearGradient id="clpr-apron" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#FFFFFF" />
          <stop offset="100%" stop-color="#E3ECF7" />
        </linearGradient>
        <linearGradient id="clpr-bottleBlue" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stop-color="#DCEEFF" />
          <stop offset="100%" stop-color="#9EC9EC" />
        </linearGradient>
        <filter id="clpr-charShadow" x="-40%" y="-40%" width="180%" height="180%">
          <feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#0A2E5C" flood-opacity="0.28" />
        </filter>
        <filter id="clpr-smallShadow" x="-50%" y="-50%" width="200%" height="200%">
          <feDropShadow dx="0" dy="5" stdDeviation="5" flood-color="#0A2E5C" flood-opacity="0.28" />
        </filter>
      </defs>

      <!-- BADAN -->
      <g filter="url(#clpr-charShadow)">
        <path d="M200,196 c46,0 78,30 78,72 v82 a14,14 0 0 1 -14,14 h-128 a14,14 0 0 1 -14,-14 v-82 c0,-42 32,-72 78,-72 z" fill="url(#clpr-lime)" />
        <path d="M172,200 L200,232 L228,200 L214,193 L200,212 L186,193 Z" fill="#FFFFFF" opacity="0.9" />
        <path d="M128,254 q30,-16 62,-18" fill="none" stroke="#FFFFFF" stroke-width="7" stroke-linecap="round" opacity="0.35" />
      </g>

      <!-- CELEMEK -->
      <g>
        <path d="M182,214 q18,10 36,0" fill="none" stroke="#4A9E12" stroke-width="7" stroke-linecap="round" />
        <path d="M170,232 h60 v56 h-60 z" fill="url(#clpr-apron)" />
        <path d="M154,288 h92 v82 a12,12 0 0 1 -12,12 h-68 a12,12 0 0 1 -12,-12 z" fill="url(#clpr-apron)" />
        <rect x="176" y="312" width="48" height="34" rx="8" fill="none" stroke="#C3D4E8" stroke-width="4" />
        <line x1="200" y1="312" x2="200" y2="346" stroke="#C3D4E8" stroke-width="4" />
        <rect x="150" y="282" width="100" height="12" rx="6" fill="url(#clpr-limeDark)" />
      </g>

      <!-- BADGE DADA -->
      <g transform="translate(240,250)">
        <circle cx="0" cy="0" r="17" fill="#FFFFFF" />
        <path d="M0,-11 l3.5,7.5 7.5,3.5 -7.5,3.5 -3.5,7.5 -3.5,-7.5 -7.5,-3.5 7.5,-3.5 z" fill="#63C21C" />
      </g>

      <!-- LENGAN KIRI -->
      <g filter="url(#clpr-smallShadow)">
        <path d="M132,244 C 106,268 96,308 100,340" fill="none" stroke="url(#clpr-lime)" stroke-width="34" stroke-linecap="round" />
        <path d="M104,326 q14,4 24,-2" fill="none" stroke="#4A9E12" stroke-width="10" stroke-linecap="round" />
        <circle cx="100" cy="352" r="22" fill="url(#clpr-skin)" />
        <g transform="translate(84,382) rotate(-12)">
          <path d="M-26,-14 h52 a8,8 0 0 1 8,8 l-6,34 a10,10 0 0 1 -10,8 h-36 a10,10 0 0 1 -10,-8 l-6,-34 a8,8 0 0 1 8,-8 z" fill="#8FD8FF" />
          <g stroke="#FFFFFF" stroke-width="4" opacity="0.7" stroke-linecap="round">
            <line x1="-14" y1="4" x2="14" y2="4" />
            <line x1="-12" y1="18" x2="12" y2="18" />
          </g>
        </g>
      </g>

      <!-- LENGAN KANAN -->
      <g filter="url(#clpr-smallShadow)">
        <path d="M268,244 C 300,254 316,238 316,214" fill="none" stroke="url(#clpr-lime)" stroke-width="34" stroke-linecap="round" />
        <path d="M304,222 q12,-8 20,-2" fill="none" stroke="#4A9E12" stroke-width="10" stroke-linecap="round" />
        <circle cx="318" cy="200" r="22" fill="url(#clpr-skin)" />
      </g>

      <!-- BOTOL SPRAY -->
      <g filter="url(#clpr-smallShadow)" transform="translate(322,166) rotate(12)">
        <rect x="-22" y="-6" width="44" height="66" rx="14" fill="url(#clpr-bottleBlue)" />
        <rect x="-14" y="10" width="28" height="30" rx="8" fill="#63C21C" opacity="0.85" />
        <rect x="-9" y="-20" width="18" height="16" rx="5" fill="#4A9E12" />
        <path d="M9,-18 l26,-12 0,15 -26,5 z" fill="#4A9E12" />
        <path d="M-4,-20 q-14,4 -12,16" fill="none" stroke="#4A9E12" stroke-width="7" stroke-linecap="round" />
        <g fill="#8FD8FF">
          <circle cx="46" cy="-24" r="7" opacity="0.95" />
          <circle cx="62" cy="-36" r="5.5" opacity="0.8" />
          <circle cx="66" cy="-16" r="4.5" opacity="0.7" />
          <circle cx="80" cy="-30" r="4" opacity="0.6" />
          <circle cx="84" cy="-6" r="3" opacity="0.5" />
        </g>
      </g>

      <!-- KEPALA -->
      <g filter="url(#clpr-charShadow)">
        <path d="M182,176 h36 v26 h-36 z" fill="#EFB07C" />
        <circle cx="152" cy="140" r="12" fill="url(#clpr-skin)" />
        <circle cx="248" cy="140" r="12" fill="url(#clpr-skin)" />
        <path d="M200,88 c30,0 50,22 50,52 c0,32 -22,54 -50,54 c-28,0 -50,-22 -50,-54 c0,-30 20,-52 50,-52 z" fill="url(#clpr-skin)" />
        <path d="M152,132 c-2,-30 20,-48 48,-48 c28,0 50,18 48,48 c-6,-18 -24,-26 -48,-26 c-24,0 -42,8 -48,26 z" fill="#25324F" />
        <path d="M154,140 q-6,16 2,28 q-10,-14 -2,-28 z" fill="#25324F" />
        <path d="M246,140 q6,16 -2,28 q10,-14 2,-28 z" fill="#25324F" />
        <path d="M148,110 a52,50 0 0 1 104,0 z" fill="url(#clpr-navy)" />
        <path d="M148,110 h-30 a11,11 0 0 0 0,22 h30 z" fill="url(#clpr-navy)" />
        <rect x="146" y="104" width="108" height="14" rx="7" fill="#0F2559" />
        <circle cx="200" cy="62" r="9" fill="#C2F55A" />
        <path d="M200,80 l4,9 9,4 -9,4 -4,9 -4,-9 -9,-4 9,-4 z" fill="#C2F55A" />
        <path d="M172,140 q12,-7 24,-1" fill="none" stroke="#25324F" stroke-width="6" stroke-linecap="round" />
        <path d="M204,139 q12,-6 24,1" fill="none" stroke="#25324F" stroke-width="6" stroke-linecap="round" />
        <circle cx="184" cy="156" r="6.5" fill="#1B3268" />
        <circle cx="216" cy="156" r="6.5" fill="#1B3268" />
        <circle cx="186" cy="153.5" r="2.2" fill="#FFFFFF" />
        <circle cx="218" cy="153.5" r="2.2" fill="#FFFFFF" />
        <path d="M200,162 q5,8 -2,11" fill="none" stroke="#D9945E" stroke-width="4" stroke-linecap="round" />
        <path d="M184,180 q16,14 32,0" fill="none" stroke="#1B3268" stroke-width="6" stroke-linecap="round" />
        <ellipse cx="168" cy="170" rx="10" ry="7" fill="#FF9A9A" opacity="0.45" />
        <ellipse cx="232" cy="170" rx="10" ry="7" fill="#FF9A9A" opacity="0.45" />
      </g>
    </svg>

    <!-- ============ TANPA GENDER (inisial) ============ -->
    <span
      v-else
      class="w-full h-full flex items-center justify-center bg-(--color-primary-container) text-(--color-on-primary-container) font-extrabold"
      aria-hidden="true"
    >
      {{ inisial }}
    </span>
  </span>
</template>
