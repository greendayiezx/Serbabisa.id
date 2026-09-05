<script setup lang="ts">
/**
 * Ikon voucher BisaKirim — kartu melayang dengan kilau yang lewat.
 *
 * Dipakai apa adanya dari berkas yang diberikan; tiga hal disesuaikan agar
 * benar sebagai komponen, dan ketiganya bukan soal selera:
 *
 * 1. Id di <defs> diberi awalan useId(). Id SVG berlaku SEDOKUMEN, jadi nama
 *    sepolos "shine" atau "voucher" akan diambil dari definisi pertama yang
 *    kebetulan dirender lebih dulu — diam-diam, tanpa galat, dan yang terkena
 *    justru elemen yang tidak ikut tergambar sama sekali.
 * 2. Animasinya SMIL, dan SMIL TIDAK BISA dimatikan lewat CSS. Untuk pengguna
 *    yang meminta gerakan dikurangi, animasinya dihentikan lewat
 *    pauseAnimations(). Tiap elemen tetap utuh dalam keadaan diamnya, jadi
 *    tidak ada yang hilang saat animasinya berhenti.
 * 3. Ukurannya bisa diatur lewat prop; di berkas asal tingginya dipatok 40px.
 */
import { onMounted, ref, useId } from 'vue'

withDefaults(defineProps<{ ukuran?: number }>(), { ukuran: 40 })

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

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
    :width="ukuran"
    :height="ukuran"
    viewBox="0 0 40 40"
    fill="none"
    aria-hidden="true"
    class="shrink-0"
  >
    <defs>
      <linearGradient :id="id('voucherBg')" x1="4" y1="4" x2="36" y2="36">
        <stop offset="0%" stop-color="#167FE8" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <linearGradient :id="id('voucher')" x1="10" y1="10" x2="30" y2="30">
        <stop offset="0%" stop-color="#FFFFFF" />
        <stop offset="100%" stop-color="#EAF8FF" />
      </linearGradient>

      <linearGradient :id="id('shine')" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="50%" stop-color="#FFFFFF" stop-opacity="0.9" />
        <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
      </linearGradient>

      <clipPath :id="id('voucherClip')">
        <path
          d="M9 13.5C9 12.67 9.67 12 10.5 12H29.5C30.33 12 31 12.67 31 13.5V16C29.9 16 29 16.9 29 18C29 19.1 29.9 20 31 20V26.5C31 27.33 30.33 28 29.5 28H10.5C9.67 28 9 27.33 9 26.5V20C10.1 20 11 19.1 11 18C11 16.9 10.1 16 9 16V13.5Z"
        />
      </clipPath>
    </defs>

    <rect width="40" height="40" rx="12" :fill="url('voucherBg')" />

    <!-- Bayangan yang ikut naik-turun bersama kartunya -->
    <ellipse cx="20" cy="30" rx="10" ry="2" fill="#075EAA" opacity="0.2">
      <animate attributeName="rx" values="10;8;10" dur="1.6s" repeatCount="indefinite" />
      <animate attributeName="opacity" values="0.2;0.1;0.2" dur="1.6s" repeatCount="indefinite" />
    </ellipse>

    <g>
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 0;0 -1.2;0 0"
        dur="1.6s"
        repeatCount="indefinite"
      />

      <path
        d="M9 13.5C9 12.67 9.67 12 10.5 12H29.5C30.33 12 31 12.67 31 13.5V16C29.9 16 29 16.9 29 18C29 19.1 29.9 20 31 20V26.5C31 27.33 30.33 28 29.5 28H10.5C9.67 28 9 27.33 9 26.5V20C10.1 20 11 19.1 11 18C11 16.9 10.1 16 9 16V13.5Z"
        :fill="url('voucher')"
      />

      <path
        d="M20 13.5V22"
        stroke="#167FE8"
        stroke-width="1.2"
        stroke-linecap="round"
        stroke-dasharray="2 2"
        opacity="0.35"
      />

      <circle cx="14" cy="18" r="2.2" fill="#C8FF00" />
      <path d="M14 18L15 17" stroke="#167FE8" stroke-width="1" stroke-linecap="round" />

      <circle cx="25" cy="16.5" r="1.1" fill="#167FE8" />
      <circle cx="25" cy="20" r="1.1" fill="#167FE8" />
      <path d="M24 21L26 16" stroke="#167FE8" stroke-width="1.1" stroke-linecap="round" />

      <g :clip-path="url('voucherClip')">
        <rect
          x="-8"
          y="8"
          width="6"
          height="24"
          :fill="url('shine')"
          transform="rotate(18 0 20)"
        >
          <animate attributeName="x" from="-12" to="42" dur="1.8s" repeatCount="indefinite" />
        </rect>
      </g>
    </g>

    <g transform="translate(29 8)">
      <path d="M2 0L2.6 1.4L4 2L2.6 2.6L2 4L1.4 2.6L0 2L1.4 1.4L2 0Z" fill="#C8FF00">
        <animate attributeName="opacity" values="0.3;1;0.3" dur="1s" repeatCount="indefinite" />
        <animateTransform
          attributeName="transform"
          type="rotate"
          values="0 2 2;90 2 2;180 2 2;360 2 2"
          dur="2s"
          repeatCount="indefinite"
        />
      </path>
    </g>
  </svg>
</template>
