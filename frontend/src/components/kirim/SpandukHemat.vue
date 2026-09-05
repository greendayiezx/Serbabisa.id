<script setup lang="ts">
/**
 * Spanduk "Kamu hemat" — pita hemat di layar detail pengiriman.
 *
 * Digambar sebagai SVG utuh, teksnya ikut: dengan begitu seluruh pita punya
 * satu perbandingan yang sama di lebar layar mana pun, dan gelombang di
 * sudutnya tidak pernah bergeser dari tulisannya.
 *
 * Ukuran hurufnya DIUKUR, bukan ditebak. Nama voucher panjangnya berbeda-beda
 * — dari "Kirim pagi" sampai "Kiriman pertama, hemat lebih" — dan teks SVG
 * tidak melipat sendiri: yang kepanjangan akan menembus tepi pita tanpa satu
 * pun galat muncul. Setelah tergambar, panjang aslinya dibaca lewat
 * getComputedTextLength() lalu hurufnya dikecilkan secukupnya.
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan nama polos
 * akan diambil dari definisi pertama yang kebetulan dirender lebih dulu.
 */
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue'

const props = defineProps<{
  /** Nominal yang dihemat, sudah berformat rupiah. */
  jumlah: string
  /** Nama vouchernya. */
  nama: string
}>()

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

/**
 * Ruang teks di dalam pita, dalam satuan viewBox.
 *
 * Teksnya berpusat di x=880, jadi 1160 berarti membentang 300..1460 — berhenti
 * sebelum lencana bunga di kiri (tepinya di 278) dan sebelum percik di kanan.
 *
 * Batas bawahnya sengaja rendah. Nama terpanjang di katalog, "Kiriman pertama,
 * hemat lebih", baru muat di ukuran 43; batas 46 membuat hurufnya berhenti
 * mengecil justru sebelum pas, lalu tulisannya menembus lencana — tanpa satu
 * pun galat, hanya tabrakan yang mesti dilihat sendiri.
 */
const LEBAR_TEKS = 1160
const UKURAN_DASAR = 74
const UKURAN_MINIMUM = 38

const teksEl = ref<SVGTextElement | null>(null)
const ukuranHuruf = ref(UKURAN_DASAR)

async function paskan() {
  ukuranHuruf.value = UKURAN_DASAR
  await nextTick()

  const el = teksEl.value
  if (!el) return

  const panjang = el.getComputedTextLength()
  if (panjang <= LEBAR_TEKS) return

  ukuranHuruf.value = Math.max(
    UKURAN_MINIMUM,
    Math.floor((UKURAN_DASAR * LEBAR_TEKS) / panjang),
  )
}

onMounted(paskan)
watch(() => [props.jumlah, props.nama], paskan)

const label = computed(() => `Kamu hemat ${props.jumlah} · ${props.nama}`)
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 1600 260"
    role="img"
    :aria-label="label"
    class="block w-full h-auto"
  >
    <defs>
      <linearGradient :id="id('kelopak')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#7BC043" />
        <stop offset="100%" stop-color="#4E9E2E" />
      </linearGradient>
      <linearGradient :id="id('ombakHijau')" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#D9EDB6" />
        <stop offset="100%" stop-color="#C7E59A" />
      </linearGradient>
      <linearGradient :id="id('ombakBiru')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#2F8AE0" />
        <stop offset="100%" stop-color="#1E6FC4" />
      </linearGradient>

      <!-- Ombak hanya boleh terlihat di dalam pita, tidak melewati sudutnya. -->
      <clipPath :id="id('pita')">
        <rect x="6" y="6" width="1588" height="248" rx="56" />
      </clipPath>
    </defs>

    <rect x="6" y="6" width="1588" height="248" rx="56" fill="#FAFCEF" />

    <g :clip-path="url('pita')">
      <!-- Gelombang hijau di sudut kiri bawah -->
      <path
        d="M-40,214 C110,150 250,214 340,260 L-40,260 Z"
        :fill="url('ombakHijau')"
        opacity=".9"
      />
      <!-- Gelombang hijau lalu biru di sudut kanan bawah -->
      <path
        d="M1230,260 C1330,196 1420,132 1640,116 L1640,260 Z"
        :fill="url('ombakHijau')"
      />
      <path
        d="M1372,260 C1452,214 1520,176 1640,164 L1640,260 Z"
        :fill="url('ombakBiru')"
      />
    </g>

    <rect
      x="6"
      y="6"
      width="1588"
      height="248"
      rx="56"
      fill="none"
      stroke="#E4EFD2"
      stroke-width="6"
    />

    <!-- Lencana bunga di kiri -->
    <g transform="translate(196,130)">
      <circle r="82" fill="#EAF6D6" />
      <g :fill="url('kelopak')">
        <ellipse cx="0" cy="-40" rx="20" ry="34" />
        <ellipse cx="0" cy="40" rx="20" ry="34" />
        <ellipse cx="-40" cy="0" rx="34" ry="20" />
        <ellipse cx="40" cy="0" rx="34" ry="20" />
      </g>
      <g fill="#2F8AE0" opacity=".95">
        <ellipse cx="-29" cy="-29" rx="30" ry="17" transform="rotate(-45 -29 -29)" />
        <ellipse cx="29" cy="29" rx="30" ry="17" transform="rotate(-45 29 29)" />
        <ellipse cx="29" cy="-29" rx="30" ry="17" transform="rotate(45 29 -29)" />
        <ellipse cx="-29" cy="29" rx="30" ry="17" transform="rotate(45 -29 29)" />
      </g>
      <path
        d="M0,-34 L9.6,-11.4 32.3,-10.5 14.6,3.9 20.6,25.8 0,13 -20.6,25.8 -14.6,3.9 -32.3,-10.5 -9.6,-11.4 Z"
        fill="#FFD54A"
      />
    </g>

    <!-- Percik kecil -->
    <g>
      <circle cx="207" cy="56" r="11" fill="#2F8AE0" />
      <circle cx="98" cy="196" r="9" fill="#7BC043" opacity=".8" />
      <circle cx="1497" cy="58" r="11" fill="#4E9E2E" />
      <path
        d="M262,120 l9,22 22,9 -22,9 -9,22 -9,-22 -22,-9 22,-9 z"
        fill="#FFD54A"
      />
      <path
        d="M1452,96 l10,25 25,10 -25,10 -10,25 -10,-25 -25,-10 25,-10 z"
        fill="#FFD54A"
      />
    </g>

    <!-- Teks: navy, nominalnya hijau -->
    <text
      ref="teksEl"
      x="880"
      y="130"
      text-anchor="middle"
      dominant-baseline="central"
      :font-size="ukuranHuruf"
      font-weight="800"
      fill="#12386B"
      style="font-family: inherit"
    >
      Kamu hemat
      <tspan fill="#4E9E2E">{{ jumlah }}</tspan>
      <tspan fill="#12386B"> · {{ nama }}</tspan>
    </text>
  </svg>
</template>
