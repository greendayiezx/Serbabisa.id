<script setup lang="ts">
/**
 * Ilustrasi "cleaner ditemukan" — pasangan dari MencariCleanerArt.
 *
 * Bahasa rupanya sengaja disambung dari layar sebelumnya: dua lencana cleaner
 * yang sama, warna yang sama, gelombang yang sama. Yang berubah hanya pusatnya
 * — kaca pembesar berganti tanda centang, dan kedua lencana yang tadi
 * mengambang berjauhan kini merapat oleh sebuah tautan.
 *
 * Dua aturan SVG yang berlaku di seluruh aplikasi ini:
 *
 * 1. Semua id di <defs> diberi awalan useId(); id SVG bersifat global sedokumen
 *    dan yang bertabrakan akan tampil salah warna.
 * 2. Keadaan DIAM setiap elemen adalah keadaan akhirnya yang terlihat penuh.
 *    Animasi SMIL hanya menggoyang dari sana. Ini penting: `pauseAnimations()`
 *    untuk "kurangi gerakan" membekukan animasi di titik nol, jadi elemen yang
 *    keadaan dasarnya `opacity="0"` akan hilang sama sekali. Efek muncul-membesar
 *    karena itu ditulis sebagai animasi CSS, yang bisa dimatikan media query.
 */
import { onMounted, ref, useId } from 'vue'

withDefaults(
  defineProps<{
    judul?: string
    subjudul?: string
  }>(),
  {
    judul: 'Horeee!',
    subjudul: 'Anda berhasil connect dengan cleaner',
  },
)

const uid = useId()
const svg = ref<SVGSVGElement | null>(null)

onMounted(() => {
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    svg.value?.pauseAnimations()
  }
})
</script>

<template>
  <svg
    ref="svg"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 240"
    class="w-full h-auto block"
    role="img"
    :aria-label="`${judul} ${subjudul}`"
  >
    <defs>
      <radialGradient :id="`${uid}-glow`">
        <stop offset="0%" stop-color="#8BC53F" stop-opacity=".28" />
        <stop offset="100%" stop-color="#8BC53F" stop-opacity="0" />
      </radialGradient>

      <linearGradient :id="`${uid}-centang`" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#8BC53F" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <filter :id="`${uid}-shadow`" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow dx="0" dy="6" stdDeviation="7" flood-color="#0A326B" flood-opacity=".16" />
      </filter>
    </defs>

    <!-- Cahaya yang bernapas -->
    <circle cx="200" cy="100" r="95" :fill="`url(#${uid}-glow)`">
      <animate attributeName="r" values="82;108;82" dur="3s" repeatCount="indefinite" />
      <animate attributeName="opacity" values=".55;1;.55" dur="3s" repeatCount="indefinite" />
    </circle>

    <!-- Tautan antara kedua cleaner: garis yang berdenyut -->
    <path
      d="M137 96 C160 78 240 78 263 96"
      fill="none"
      stroke="#3BBEB8"
      stroke-width="2.5"
      stroke-linecap="round"
      stroke-dasharray="4 8"
      opacity=".55"
    >
      <animate attributeName="opacity" values=".3;.8;.3" dur="2s" repeatCount="indefinite" />
    </path>

    <!-- Lencana cleaner kiri -->
    <g>
      <circle cx="119" cy="91" r="18" fill="#FFFFFF" :filter="`url(#${uid}-shadow)`" />
      <circle cx="119" cy="87" r="5" fill="#FFD0AE" />
      <path d="M111 101C112 94 116 92 119 92C123 92 127 95 128 101Z" fill="#167FE8" />
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 2;0 -3;0 2"
        dur="2.5s"
        repeatCount="indefinite"
      />
    </g>

    <!-- Lencana cleaner kanan -->
    <g>
      <circle cx="281" cy="89" r="18" fill="#FFFFFF" :filter="`url(#${uid}-shadow)`" />
      <circle cx="281" cy="85" r="5" fill="#D8906D" />
      <path d="M273 99C274 92 278 90 281 90C285 90 289 93 290 99Z" fill="#3BBEB8" />
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 -2;0 3;0 -2"
        dur="2.7s"
        repeatCount="indefinite"
      />
    </g>

    <!-- Cincin sorak: melebar lalu memudar, terus-menerus -->
    <circle cx="200" cy="96" r="45" fill="none" stroke="#8BC53F" stroke-width="2" opacity="0">
      <animate attributeName="r" values="40;72" dur="2s" repeatCount="indefinite" />
      <animate attributeName="opacity" values=".45;0" dur="2s" repeatCount="indefinite" />
    </circle>

    <!-- Tanda centang -->
    <g class="muncul">
      <circle cx="200" cy="96" r="38" fill="#FFFFFF" :filter="`url(#${uid}-shadow)`" />
      <circle cx="200" cy="96" r="30" :fill="`url(#${uid}-centang)`" />
      <!--
        Digambar sebagai garis biasa, bukan garis yang "ditulis" lewat
        stroke-dashoffset: begitu animasinya dibekukan untuk mode kurangi
        gerakan, centang bergaya tulis akan hilang sama sekali.
      -->
      <path
        d="M186 96.5L196 107L215 86"
        fill="none"
        stroke="#FFFFFF"
        stroke-width="6.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </g>

    <!-- Percik perayaan -->
    <g>
      <path d="M143 55L146 62L153 65L146 68L143 75L140 68L133 65L140 62Z" fill="#C8FF00">
        <animate attributeName="opacity" values=".25;1;.25" dur="1.5s" repeatCount="indefinite" />
      </path>
      <path d="M262 52L265 59L272 62L265 65L262 72L259 65L252 62L259 59Z" fill="#FFD43B">
        <animate attributeName="opacity" values="1;.25;1" dur="1.7s" repeatCount="indefinite" />
      </path>
      <circle cx="168" cy="141" r="4.5" fill="#3BBEB8">
        <animate attributeName="cy" values="141;133;141" dur="1.2s" repeatCount="indefinite" />
      </circle>
      <circle cx="232" cy="141" r="4.5" fill="#167FE8">
        <animate attributeName="cy" values="141;133;141" dur="1.2s" begin=".3s" repeatCount="indefinite" />
      </circle>
    </g>

    <text
      x="200"
      y="178"
      text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif"
      font-size="17"
      font-weight="800"
      fill="#0A326B"
    >
      {{ judul }}
    </text>

    <text
      x="200"
      y="198"
      text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="500"
      fill="#557086"
    >
      {{ subjudul }}
    </text>

    <!-- Gelombang bawah, sama seperti layar pencarian -->
    <path
      d="M0 218C35 207 62 207 95 218C128 229 155 229 188 218C221 207 248 207 281 218C314 229 341 229 374 218C384 215 392 215 400 217L400 240L0 240Z"
      fill="#C8FF00"
      opacity=".85"
    >
      <animate
        attributeName="d"
        dur="3s"
        repeatCount="indefinite"
        values="
          M0 218C35 207 62 207 95 218C128 229 155 229 188 218C221 207 248 207 281 218C314 229 341 229 374 218C384 215 392 215 400 217L400 240L0 240Z;
          M0 222C35 211 62 211 95 222C128 233 155 233 188 222C221 211 248 211 281 222C314 233 341 233 374 222C384 219 392 219 400 221L400 240L0 240Z;
          M0 218C35 207 62 207 95 218C128 229 155 229 188 218C221 207 248 207 281 218C314 229 341 229 374 218C384 215 392 215 400 217L400 240L0 240Z
        "
      />
    </path>

    <path
      d="M0 225C40 216 65 216 100 225C135 234 160 234 200 225C240 216 265 216 300 225C335 234 360 234 400 225"
      fill="none"
      stroke="#3BBEB8"
      stroke-width="3"
      opacity=".7"
    >
      <animate
        attributeName="d"
        dur="2.5s"
        repeatCount="indefinite"
        values="
          M0 225C40 216 65 216 100 225C135 234 160 234 200 225C240 216 265 216 300 225C335 234 360 234 400 225;
          M0 222C40 213 65 213 100 222C135 231 160 231 200 222C240 213 265 213 300 222C335 231 360 231 400 222;
          M0 225C40 216 65 216 100 225C135 234 160 234 200 225C240 216 265 216 300 225C335 234 360 234 400 225
        "
      />
    </path>
  </svg>
</template>

<style scoped>
/*
 * Muncul-membesar ditulis di CSS, bukan SMIL, supaya bisa dimatikan media query
 * di bawah — dan supaya keadaan diamnya tetap terlihat penuh.
 */
@keyframes muncul {
  0% {
    transform: scale(0.5);
    opacity: 0;
  }
  60% {
    transform: scale(1.08);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.muncul {
  transform-origin: 200px 96px;
  animation: muncul 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@media (prefers-reduced-motion: reduce) {
  .muncul {
    animation: none;
  }
}
</style>
