<script setup lang="ts">
/**
 * Layar "mencari kurir" BisaKirim — satu SVG yang memenuhi layar.
 *
 * ADEGANNYA SENGAJA BUKAN KACA PEMBESAR seperti BisaBersih, dan bukan radar
 * polos seperti BisaJemput. Yang digambar di sini PETA yang sedang disapu:
 * jalanan, titik ambil biru, tujuan oranye, garis putus-putus yang berjalan di
 * antaranya, dan tiga kurir bermotor yang berkedip bergantian di sekitarnya.
 * Itu yang sebenarnya terjadi — paket punya dua ujung, dan yang dicari adalah
 * orang di antara keduanya.
 *
 * Teks judulnya TIDAK ikut di dalam SVG. Teks SVG tidak melipat sendiri, tidak
 * ikut ukuran huruf pilihan pengguna, dan tidak bisa dipilih; kalimat sepanjang
 * "Mencari kurir terdekat untuk kamu…" akan menembus tepi layar sempit tanpa
 * satu pun galat. Jadi yang di sini gambarnya saja, teksnya HTML di atasnya.
 *
 * preserveAspectRatio="slice" membuat gambarnya menutup layar penuh dengan
 * memangkas sisi yang berlebih — karena itu semua yang penting ditaruh di
 * sekitar tengah, bukan di pinggir.
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan nama polos
 * akan diambil dari definisi pertama yang kebetulan dirender lebih dulu.
 */
import { onMounted, ref, useId } from 'vue'

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

const svg = ref<SVGSVGElement | null>(null)

onMounted(() => {
  /*
   * SMIL tidak bisa dimatikan lewat CSS — `animation: none` tidak menyentuhnya
   * sama sekali. Satu-satunya cara menghormati "kurangi gerak" adalah
   * menghentikannya lewat API-nya sendiri.
   */
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    svg.value?.pauseAnimations()
  }
})
</script>

<template>
  <svg
    ref="svg"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 390 780"
    preserveAspectRatio="xMidYMid slice"
    class="absolute inset-0 w-full h-full"
    role="img"
    aria-label="Peta sedang disapu untuk mencari kurir terdekat"
  >
    <defs>
      <linearGradient :id="id('langit')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#EAF6FF" />
        <stop offset="52%" stop-color="#D6ECFF" />
        <stop offset="100%" stop-color="#BFE0FA" />
      </linearGradient>

      <radialGradient :id="id('sorot')" cx="50%" cy="50%">
        <stop offset="0%" stop-color="#1e9bf0" stop-opacity=".22" />
        <stop offset="70%" stop-color="#1e9bf0" stop-opacity=".05" />
        <stop offset="100%" stop-color="#1e9bf0" stop-opacity="0" />
      </radialGradient>

      <linearGradient :id="id('sapuan')" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#1e9bf0" stop-opacity="0" />
        <stop offset="100%" stop-color="#1e9bf0" stop-opacity=".28" />
      </linearGradient>

      <filter :id="id('bayang')" x="-40%" y="-40%" width="180%" height="190%">
        <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#0A326B" flood-opacity=".18" />
      </filter>

      <!-- Petanya dipotong lingkaran: yang di luar jangkauan pencarian tidak digambar. -->
      <clipPath :id="id('jangkauan')">
        <circle cx="195" cy="360" r="168" />
      </clipPath>
    </defs>

    <rect width="390" height="780" :fill="url('langit')" />

    <!-- ── Peta: blok kota dan jalanan, dipotong lingkaran jangkauan ── -->
    <g :clip-path="url('jangkauan')">
      <circle cx="195" cy="360" r="168" fill="#F4FAFF" />

      <g fill="#DCEBF8">
        <rect x="52" y="216" width="86" height="60" rx="7" />
        <rect x="156" y="204" width="70" height="48" rx="7" />
        <rect x="246" y="222" width="92" height="54" rx="7" />
        <rect x="40" y="300" width="98" height="72" rx="7" />
        <rect x="252" y="300" width="96" height="72" rx="7" />
        <rect x="44" y="398" width="92" height="66" rx="7" />
        <rect x="156" y="410" width="74" height="54" rx="7" />
        <rect x="250" y="398" width="94" height="66" rx="7" />
        <rect x="96" y="486" width="98" height="52" rx="7" />
        <rect x="212" y="486" width="88" height="52" rx="7" />
      </g>

      <!-- Jalan utama, dua arah -->
      <g stroke="#FFFFFF" stroke-width="16" stroke-linecap="round" fill="none">
        <path d="M-10 288 H400" />
        <path d="M-10 386 H400" />
        <path d="M-10 476 H400" />
        <path d="M146 190 V560" />
        <path d="M240 190 V560" />
      </g>
      <g stroke="#BFD9EC" stroke-width="1.5" stroke-dasharray="7 9" fill="none" opacity=".85">
        <path d="M-10 288 H400" />
        <path d="M-10 386 H400" />
        <path d="M146 190 V560" />
      </g>

      <!-- Sapuan radar: satu sektor yang berputar mengitari peta -->
      <g>
        <path d="M195 360 L195 192 A168 168 0 0 1 314 242 Z" :fill="url('sapuan')" />
        <animateTransform
          attributeName="transform"
          type="rotate"
          from="0 195 360"
          to="360 195 360"
          dur="4.5s"
          repeatCount="indefinite"
        />
      </g>
    </g>

    <circle cx="195" cy="360" r="200" :fill="url('sorot')" />

    <!-- ── Gelombang pencarian: tiga lingkaran yang membesar bergantian ── -->
    <g fill="none" stroke="#1e9bf0" stroke-width="2">
      <circle cx="195" cy="360" r="60" opacity="0">
        <animate attributeName="r" values="60;168" dur="3s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".55;0" dur="3s" repeatCount="indefinite" />
      </circle>
      <circle cx="195" cy="360" r="60" opacity="0">
        <animate attributeName="r" values="60;168" dur="3s" begin="1s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".55;0" dur="3s" begin="1s" repeatCount="indefinite" />
      </circle>
      <circle cx="195" cy="360" r="60" opacity="0">
        <animate attributeName="r" values="60;168" dur="3s" begin="2s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".55;0" dur="3s" begin="2s" repeatCount="indefinite" />
      </circle>
    </g>

    <!-- Cincin putus-putus yang berputar pelan -->
    <circle
      cx="195"
      cy="360"
      r="150"
      fill="none"
      stroke="#8BC53F"
      stroke-width="2.5"
      stroke-dasharray="4 16"
      opacity=".55"
    >
      <animateTransform
        attributeName="transform"
        type="rotate"
        from="360 195 360"
        to="0 195 360"
        dur="14s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- ── Rute: titik ambil biru ke tujuan oranye ── -->
    <!--
      Garisnya MEMUTAR lewat bawah, tidak lurus dari pin ke pin: jalur lurus
      antara kedua pin lewat persis di belakang cakram paket di tengah, dan yang
      tersisa cuma dua potong garis pendek di kiri dan kanan — terbaca seperti
      gambar yang rusak, bukan seperti rute.
    -->
    <path
      d="M118 300 C132 372 140 440 190 466 C222 482 254 462 268 424"
      fill="none"
      stroke="#1e9bf0"
      stroke-width="3.5"
      stroke-linecap="round"
      stroke-dasharray="10 12"
      opacity=".75"
    >
      <animate attributeName="stroke-dashoffset" values="44;0" dur="1.6s" repeatCount="indefinite" />
    </path>

    <!-- Pin titik ambil -->
    <g :filter="url('bayang')">
      <path
        d="M118 300 c-11 -13 -16 -21 -16 -29 a16 16 0 0 1 32 0 c0 8 -5 16 -16 29 z"
        fill="#1e9bf0"
      />
      <circle cx="118" cy="270" r="6" fill="#FFFFFF" />
    </g>

    <!-- Pin tujuan -->
    <g :filter="url('bayang')">
      <path
        d="M272 432 c-11 -13 -16 -21 -16 -29 a16 16 0 0 1 32 0 c0 8 -5 16 -16 29 z"
        fill="#f97316"
      />
      <circle cx="272" cy="402" r="6" fill="#FFFFFF" />
    </g>

    <!-- ── Kurir bermotor yang sedang dijajaki, berkedip bergantian ── -->
    <g>
      <g transform="translate(96,432)">
        <circle r="24" fill="#FFFFFF" :filter="url('bayang')" />
        <g transform="translate(-13,-11) scale(1.08)">
          <circle cx="5" cy="16" r="4.4" fill="none" stroke="#0A326B" stroke-width="2.2" />
          <circle cx="19" cy="16" r="4.4" fill="none" stroke="#0A326B" stroke-width="2.2" />
          <path
            d="M5 16 L10 8 H16 l3 8"
            fill="none"
            stroke="#1e9bf0"
            stroke-width="2.4"
            stroke-linejoin="round"
            stroke-linecap="round"
          />
          <rect x="13" y="1.5" width="9" height="7" rx="1.6" fill="#8BC53F" />
        </g>
        <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" repeatCount="indefinite" />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="96 432;96 424;96 432"
          dur="2.4s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>

      <g transform="translate(300,318)">
        <circle r="22" fill="#FFFFFF" :filter="url('bayang')" />
        <g transform="translate(-12,-10)">
          <circle cx="5" cy="16" r="4.2" fill="none" stroke="#0A326B" stroke-width="2.2" />
          <circle cx="19" cy="16" r="4.2" fill="none" stroke="#0A326B" stroke-width="2.2" />
          <path
            d="M5 16 L10 8 H16 l3 8"
            fill="none"
            stroke="#f97316"
            stroke-width="2.4"
            stroke-linejoin="round"
            stroke-linecap="round"
          />
          <rect x="13" y="1.5" width="9" height="7" rx="1.6" fill="#FFD43B" />
        </g>
        <animate
          attributeName="opacity"
          values=".35;1;.35"
          dur="2.4s"
          begin=".8s"
          repeatCount="indefinite"
        />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="300 318;300 310;300 318"
          dur="2.4s"
          begin=".8s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>

      <g transform="translate(178,232)">
        <circle r="20" fill="#FFFFFF" :filter="url('bayang')" />
        <g transform="translate(-11,-9) scale(0.92)">
          <circle cx="5" cy="16" r="4.2" fill="none" stroke="#0A326B" stroke-width="2.4" />
          <circle cx="19" cy="16" r="4.2" fill="none" stroke="#0A326B" stroke-width="2.4" />
          <path
            d="M5 16 L10 8 H16 l3 8"
            fill="none"
            stroke="#3BBEB8"
            stroke-width="2.6"
            stroke-linejoin="round"
            stroke-linecap="round"
          />
          <rect x="13" y="1.5" width="9" height="7" rx="1.6" fill="#8BC53F" />
        </g>
        <animate
          attributeName="opacity"
          values=".35;1;.35"
          dur="2.4s"
          begin="1.6s"
          repeatCount="indefinite"
        />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="178 232;178 224;178 232"
          dur="2.4s"
          begin="1.6s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>
    </g>

    <!-- ── Paket di tengah: yang sedang menunggu dijemput ── -->
    <g>
      <circle cx="195" cy="360" r="46" fill="#FFFFFF" :filter="url('bayang')" />
      <g transform="translate(195,360)">
        <rect x="-24" y="-18" width="48" height="36" rx="4" fill="#F2C185" />
        <rect x="-24" y="-18" width="48" height="12" rx="4" fill="#E0A863" />
        <rect x="-5" y="-18" width="10" height="36" fill="#D39150" opacity=".75" />
        <path
          d="M-5 -18 c-6 -8 -14 -7 -14 -1 c0 4 6 5 14 1 z M5 -18 c6 -8 14 -7 14 -1 c0 4 -6 5 -14 1 z"
          fill="#8BC53F"
        />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="195 360;195 353;195 360"
          dur="2.8s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>
    </g>

    <!-- ── Percik kecil di sekitar paket ── -->
    <g fill="#FFD43B">
      <path d="M262 300 l3 7 7 3 -7 3 -3 7 -3 -7 -7 -3 7 -3 z">
        <animate attributeName="opacity" values=".2;1;.2" dur="2s" repeatCount="indefinite" />
      </path>
      <path d="M128 372 l2.5 6 6 2.5 -6 2.5 -2.5 6 -2.5 -6 -6 -2.5 6 -2.5 z">
        <animate
          attributeName="opacity"
          values=".2;1;.2"
          dur="2.2s"
          begin=".6s"
          repeatCount="indefinite"
        />
      </path>
    </g>
    <circle cx="238" cy="486" r="5" fill="#8BC53F">
      <animate attributeName="cy" values="486;478;486" dur="2.6s" repeatCount="indefinite" />
    </circle>
    <circle cx="150" cy="196" r="4" fill="#1e9bf0" opacity=".7">
      <animate attributeName="cy" values="196;188;196" dur="2.2s" repeatCount="indefinite" />
    </circle>
  </svg>
</template>
