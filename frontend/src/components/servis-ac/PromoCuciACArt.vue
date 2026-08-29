<script setup lang="ts">
/**
 * Banner promo Cuci AC (SVG).
 *
 * Gambarnya dipakai apa adanya; yang disesuaikan hanya dua hal, dan keduanya
 * bukan soal selera:
 *
 * 1. Semua id di <defs> diberi awalan useId(). Id SVG berlaku sedokumen, jadi
 *    nama sepolos "bg", "gold", atau "shadow" akan bertabrakan dengan ilustrasi
 *    lain yang kebetulan tampil di halaman yang sama — dan yang menang adalah
 *    definisi pertama, diam-diam.
 * 2. Angka diskonnya datang dari katalog promo, bukan ditulis di gambar. Banner
 *    yang menyebut angka berbeda dari yang ditagih adalah janji yang salah.
 */
import { useId } from 'vue'

defineProps<{
  /** Besar diskon dalam persen, dari katalog promo. */
  persen: number
}>()

const uid = useId()
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 398 150"
    preserveAspectRatio="xMidYMid meet"
    fill="none"
    class="w-full h-auto block"
    role="img"
    :aria-label="`Promo spesial: Cuci AC diskon ${persen} persen`"
  >
    <defs>
      <!-- Brand Background -->
      <linearGradient :id="`${uid}-bg`" x1="0" y1="0" x2="398" y2="150" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stop-color="#167FE8" />
        <stop offset="58%" stop-color="#168CE0" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <!-- Gold -->
      <linearGradient :id="`${uid}-gold`" x1="270" y1="55" x2="360" y2="135" gradientUnits="userSpaceOnUse">
        <stop offset="0%" stop-color="#FFE98A" />
        <stop offset="50%" stop-color="#FFD43B" />
        <stop offset="100%" stop-color="#E7B72D" />
      </linearGradient>

      <!-- White -->
      <linearGradient :id="`${uid}-white`" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#FFFFFF" />
        <stop offset="100%" stop-color="#EAF8FF" />
      </linearGradient>

      <!-- Soft shadow -->
      <filter :id="`${uid}-shadow`" x="-30%" y="-30%" width="160%" height="180%">
        <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#063B75" flood-opacity=".20" />
      </filter>

      <!-- Clip rounded card -->
      <clipPath :id="`${uid}-cardClip`">
        <rect x="0" y="0" width="398" height="150" rx="16" />
      </clipPath>
    </defs>

    <g :clip-path="`url(#${uid}-cardClip)`">
      <!-- Background -->
      <rect width="398" height="150" rx="16" :fill="`url(#${uid}-bg)`" />

      <!-- Soft brand blobs -->
      <circle cx="330" cy="35" r="85" fill="#FFFFFF" opacity=".06" />
      <circle cx="25" cy="145" r="65" fill="#C8FF00" opacity=".08" />

      <!-- Left decoration -->
      <circle cx="30" cy="30" r="18" fill="#C8FF00" opacity=".12" />
      <path
        d="M30 19 L33 27 L41 30 L33 33 L30 41 L27 33 L19 30 L27 27Z"
        fill="#C8FF00"
        opacity=".9"
      />

      <!-- Text area -->
      <g font-family="Arial, Helvetica, sans-serif">
        <text x="20" y="61" fill="#E8FF82" font-size="11" font-weight="700" letter-spacing=".8">
          PROMO SPESIAL
        </text>

        <text x="20" y="88" fill="#FFFFFF" font-size="22" font-weight="800">Cuci AC</text>

        <text x="20" y="118" fill="#FFFFFF" font-size="26" font-weight="900">Diskon</text>

        <!--
          Angkanya menyatu dengan tanda persen dalam satu <text> supaya diskon
          satu, dua, atau tiga digit tetap rapat — bukan dua elemen dengan
          jarak yang harus ditebak ulang tiap kali angkanya berubah. Digeser 7px dari
          112 ke 119: koordinat aslinya disiapkan untuk tanda persen sendirian,
          sehingga angkanya menempel pada kata "Diskon".
        -->
        <text x="119" y="118" fill="#C8FF00" font-size="30" font-weight="900">{{ persen }}%</text>
      </g>

      <!-- Air conditioner illustration -->
      <g transform="translate(220 18)" :filter="`url(#${uid}-shadow)`">
        <rect x="0" y="0" width="106" height="47" rx="12" :fill="`url(#${uid}-white)`" />

        <!-- Top highlight -->
        <path d="M15 11H91" stroke="#D8F4FA" stroke-width="4" stroke-linecap="round" />

        <!-- Display -->
        <rect x="76" y="17" width="17" height="9" rx="3" fill="#DFF8FA" />
        <circle cx="81" cy="21.5" r="2" fill="#C8FF00" />

        <!-- Vent -->
        <path d="M15 35H91" stroke="#9EAFB5" stroke-width="3" stroke-linecap="round" />
        <path
          d="M23 35V40 M34 35V40 M45 35V40 M56 35V40 M67 35V40 M78 35V40"
          stroke="#B4C2C6"
          stroke-width="2"
          stroke-linecap="round"
        />
      </g>

      <!-- Air flow -->
      <g stroke="#E8FF82" stroke-width="3" stroke-linecap="round" opacity=".9">
        <path d="M240 75 C236 84 241 90 237 98" />
        <path d="M257 75 C253 84 258 91 254 100" />
        <path d="M274 75 C270 84 275 90 271 97" />
      </g>

      <!-- Decorative sparkle -->
      <g transform="translate(302 54)">
        <path
          d="M40 0 L47 25 L72 32 L47 39 L40 64 L33 39 L8 32 L33 25Z"
          :fill="`url(#${uid}-gold)`"
          opacity=".30"
        />
        <path
          d="M78 45 L82 57 L94 61 L82 65 L78 77 L74 65 L62 61 L74 57Z"
          fill="#C8FF00"
          opacity=".35"
        />
        <path
          d="M17 62 L20 70 L28 73 L20 76 L17 84 L14 76 L6 73 L14 70Z"
          fill="#FFFFFF"
          opacity=".45"
        />
      </g>

      <!-- Gold discount badge -->
      <g transform="translate(300 94)" :filter="`url(#${uid}-shadow)`">
        <circle cx="30" cy="30" r="30" :fill="`url(#${uid}-gold)`" />
        <circle cx="30" cy="30" r="25" fill="#FFF4BC" opacity=".35" />

        <!-- Percent -->
        <circle cx="21" cy="22" r="4" fill="#FFFFFF" />
        <circle cx="39" cy="39" r="4" fill="#FFFFFF" />
        <path d="M41 17L19 44" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" />
      </g>

      <!-- Lime accent line -->
      <path
        d="M20 133 C58 125 94 130 130 133"
        stroke="#C8FF00"
        stroke-width="4"
        stroke-linecap="round"
        opacity=".9"
      />
    </g>
  </svg>
</template>
