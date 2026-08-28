<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId } from 'vue'

/**
 * Kartu promo BisaBersih.
 *
 * Teksnya datang dari props, bukan tertanam di dalam SVG, supaya satu kartu ini
 * melayani kesembilan voucher — dan angka yang tampil selalu ikut katalog promo.
 */
const props = withDefaults(
  defineProps<{
    judul: string
    kode: string
    minTransaksi: number
    /** Baris kecil tambahan, mis. periode berlaku atau masa berlaku cashback. */
    catatan?: string
  }>(),
  { catatan: undefined },
)

/**
 * ID gradien & filter berlaku se-dokumen.
 *
 * Halaman promo menampilkan sembilan kartu sekaligus; tanpa awalan unik, kartu
 * kedua dan seterusnya akan memakai gradien milik kartu pertama.
 */
const uid = useId()
const id = (nama: string) => `${uid}-${nama}`

/**
 * Judul promo panjangnya berbeda-beda — dari "Cashback 10%" (12 huruf) sampai
 * "Akhir Tahun: Diskon Rp40.000" (28 huruf). Ukuran huruf mengikuti panjangnya
 * supaya yang panjang tidak keluar kanvas dan yang pendek tidak terlihat kecil.
 */
const ukuranJudul = computed(() => {
  const n = props.judul.length
  if (n <= 16) return 18
  if (n <= 24) return 15.5
  return 13.5
})

const rp = (n: number) => 'Rp' + n.toLocaleString('id-ID')

/**
 * Animasi SMIL tidak bisa dimatikan lewat CSS, jadi elemen <animate>-nya yang
 * tidak dirender sama sekali ketika sistem meminta pengurangan gerak.
 */
const kurangiGerak = ref(false)
let mq: MediaQueryList | null = null

if (typeof window !== 'undefined' && window.matchMedia) {
  mq = window.matchMedia('(prefers-reduced-motion: reduce)')
  kurangiGerak.value = mq.matches
  const ubah = (e: MediaQueryListEvent) => (kurangiGerak.value = e.matches)
  mq.addEventListener('change', ubah)
  onBeforeUnmount(() => mq?.removeEventListener('change', ubah))
}

const bergerak = computed(() => !kurangiGerak.value)
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 120"
    class="w-full h-auto block"
    role="img"
    :aria-label="`${judul}, kode ${kode}, minimal transaksi ${rp(minTransaksi)}`"
  >
    <defs>
      <linearGradient :id="id('brandBg')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#0A326B" />
        <stop offset="48%" stop-color="#167FE8" />
        <stop offset="78%" stop-color="#3BBEB8" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>

      <linearGradient :id="id('gold')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFF3A6" />
        <stop offset="45%" stop-color="#FFD43B" />
        <stop offset="100%" stop-color="#D99E16" />
      </linearGradient>

      <filter :id="id('shadow')">
        <feDropShadow dx="0" dy="3" stdDeviation="4" flood-color="#062A5A" flood-opacity=".25" />
      </filter>
    </defs>

    <!-- Kartu -->
    <rect width="400" height="120" rx="16" :fill="`url(#${id('brandBg')})`" />

    <!-- Cahaya latar -->
    <circle cx="370" cy="18" r="55" fill="#C8FF00" opacity=".10">
      <template v-if="bergerak">
        <animate attributeName="r" values="48;60;48" dur="3s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".06;.18;.06" dur="3s" repeatCount="indefinite" />
      </template>
    </circle>

    <!-- Partikel -->
    <g fill="#FFFFFF">
      <circle cx="345" cy="88" r="2">
        <template v-if="bergerak">
          <animate attributeName="cy" values="88;82;88" dur="2s" repeatCount="indefinite" />
          <animate attributeName="opacity" values=".2;1;.2" dur="2s" repeatCount="indefinite" />
        </template>
      </circle>
      <circle cx="370" cy="72" r="3">
        <template v-if="bergerak">
          <animate attributeName="cy" values="72;66;72" dur="2.5s" repeatCount="indefinite" />
          <animate attributeName="opacity" values=".3;.9;.3" dur="2.5s" repeatCount="indefinite" />
        </template>
      </circle>
    </g>

    <!-- Ikon tiket diskon -->
    <g :filter="`url(#${id('shadow')})`">
      <g>
        <animateTransform
          v-if="bergerak"
          attributeName="transform"
          type="translate"
          values="0 2;0 -2;0 2"
          dur="2s"
          repeatCount="indefinite"
        />

        <path
          d="M18 27
             Q18 20 25 20
             H63
             V28
             C58 28 58 38 63 38
             V46
             H25
             Q18 46 18 39
             C23 39 23 27 18 27Z"
          fill="#FFFFFF"
        />

        <path d="M29 24V42" stroke="#D9E7F2" stroke-width="2" stroke-dasharray="3 3" />

        <circle cx="40" cy="29" r="3" :fill="`url(#${id('gold')})`" />
        <circle cx="51" cy="38" r="3" :fill="`url(#${id('gold')})`" />
        <path d="M51 28L40 40" stroke="#F4C542" stroke-width="3" stroke-linecap="round" />

        <path
          d="M68 14
             L71 21
             L78 24
             L71 27
             L68 34
             L65 27
             L58 24
             L65 21Z"
          fill="#C8FF00"
        >
          <template v-if="bergerak">
            <animate attributeName="opacity" values=".3;1;.3" dur="1.4s" repeatCount="indefinite" />
            <animateTransform
              attributeName="transform"
              type="rotate"
              values="0 68 24;10 68 24;0 68 24"
              dur="1.4s"
              repeatCount="indefinite"
            />
          </template>
        </path>
      </g>
    </g>

    <!-- Judul -->
    <text
      x="88"
      y="39"
      font-family="Arial, Helvetica, sans-serif"
      :font-size="ukuranJudul"
      font-weight="800"
      fill="#FFFFFF"
    >
      {{ judul }}
    </text>

    <!-- Kode -->
    <text
      x="88"
      y="64"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="600"
      fill="#FFFFFF"
    >
      Kode
    </text>

    <rect x="123" y="50" width="125" height="24" rx="7" fill="#FFFFFF" opacity=".18" />
    <text
      x="185"
      y="67"
      text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="800"
      letter-spacing=".5"
      fill="#FFF3A6"
    >
      {{ kode }}
    </text>

    <!-- Syarat -->
    <text
      x="88"
      y="91"
      font-family="Arial, Helvetica, sans-serif"
      font-size="11.5"
      font-weight="500"
      fill="#EAF7FF"
    >
      min. transaksi {{ rp(minTransaksi) }}<template v-if="catatan"> · {{ catatan }}</template>
    </text>

    <!-- Garis lime -->
    <rect x="88" y="101" width="150" height="3" rx="1.5" fill="#C8FF00">
      <animate
        v-if="bergerak"
        attributeName="width"
        values="90;150;90"
        dur="2.5s"
        repeatCount="indefinite"
      />
    </rect>
  </svg>
</template>
