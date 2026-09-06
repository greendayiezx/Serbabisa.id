<script setup lang="ts">
/**
 * Hero ilustrasi BisaTukang — flat vector 2D, warnanya mengikuti waktu nyata
 * lewat palet di @/lib/heroSky, sama seperti hero menu lain.
 *
 * Menggambarkan teknisi terverifikasi membawa kotak perkakas (toolbox) di depan
 * rumah/bangunan dengan peralatan perbaikan (kunci inggris, meteran, tangga).
 */
import { computed, useId } from 'vue'
import { HERO_SKY, type HeroTimeOfDay } from '@/lib/heroSky'

const props = withDefaults(defineProps<{ timeOfDay?: HeroTimeOfDay }>(), {
  timeOfDay: 'malam',
})

const p = computed(() => HERO_SKY[props.timeOfDay])

const uid = useId()
const id = (nama: string) => `${uid}-${nama}`
const url = (nama: string) => `url(#${uid}-${nama})`

const altText = computed(() => {
  const waktu = {
    pagi: 'pagi hari',
    siang: 'siang hari',
    sore: 'sore hari',
    malam: 'malam hari',
  }[props.timeOfDay]
  return `Ilustrasi teknisi BisaTukang membawa kotak perkakas di depan rumah pada ${waktu}`
})
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 620"
    preserveAspectRatio="xMidYMax slice"
    role="img"
    :aria-label="altText"
    class="block w-full h-auto"
  >
    <defs>
      <linearGradient :id="id('sky')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.sky[0]" />
        <stop offset="38%" :stop-color="p.sky[1]" />
        <stop offset="72%" :stop-color="p.sky[2]" />
        <stop offset="100%" :stop-color="p.sky[3]" />
      </linearGradient>

      <radialGradient :id="id('moonGlow')" cx="50%" cy="50%" r="50%">
        <stop offset="0%" :stop-color="p.celestialGlow" stop-opacity="0.30" />
        <stop offset="45%" :stop-color="p.celestialGlow" stop-opacity="0.10" />
        <stop offset="100%" :stop-color="p.celestialGlow" stop-opacity="0" />
      </radialGradient>

      <radialGradient :id="id('horizonHaze')" cx="50%" cy="100%" r="70%">
        <stop offset="0%" :stop-color="p.haze.color" :stop-opacity="p.haze.inner" />
        <stop offset="60%" :stop-color="p.haze.color" :stop-opacity="p.haze.mid" />
        <stop offset="100%" :stop-color="p.haze.color" stop-opacity="0" />
      </radialGradient>

      <linearGradient :id="id('rumah')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgNear[0]" />
        <stop offset="100%" :stop-color="p.bldgNear[1]" />
      </linearGradient>
      <linearGradient :id="id('atap')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgMid[0]" />
        <stop offset="100%" :stop-color="p.bldgMid[1]" />
      </linearGradient>
      
      <linearGradient :id="id('toolbox')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#E53935" />
        <stop offset="100%" stop-color="#B71C1C" />
      </linearGradient>

      <linearGradient :id="id('helmet')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#FBC02D" />
        <stop offset="100%" stop-color="#F57F17" />
      </linearGradient>

      <linearGradient :id="id('vest')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#FB8C00" />
        <stop offset="100%" stop-color="#E65100" />
      </linearGradient>
    </defs>

    <!-- 1. Langit -->
    <rect width="400" height="620" :fill="url('sky')" />
    <rect width="400" height="620" :fill="url('horizonHaze')" />

    <!-- Bintang/Awan/Matahari tergantung heroTimeOfDay -->
    <circle cx="320" cy="110" r="45" :fill="url('moonGlow')" />
    <!--
      celestialCore, BUKAN celestial.

      `celestial` berisi 'moon' atau 'sun' — jenisnya, bukan warnanya. Dipakai
      sebagai fill, SVG menerima "moon" sebagai warna yang tidak dikenal lalu
      jatuh ke hitam: yang tergambar di langit senja adalah CAKRAM HITAM pekat,
      tanpa satu pun galat di konsol. Warnanya ada di celestialCore.
    -->
    <circle cx="320" cy="110" r="22" :fill="p.celestialCore" />

    <!-- 2. Siluet Bangunan & Rumah -->
    <path
      d="M20 380 L180 250 L340 380 V550 H20 Z"
      :fill="url('rumah')"
      opacity="0.85"
    />
    <path
      d="M10 385 L180 245 L350 385 L340 400 L180 265 L20 400 Z"
      :fill="url('atap')"
    />

    <!-- Jendela Berlampu -->
    <rect x="70" y="390" width="50" height="60" rx="4" :fill="p.window.color" opacity="0.9" />
    <rect x="240" y="390" width="50" height="60" rx="4" :fill="p.window.color" opacity="0.9" />
    <line x1="95" y1="390" x2="95" y2="450" stroke="#2a3848" stroke-width="2" />
    <line x1="70" y1="420" x2="120" y2="420" stroke="#2a3848" stroke-width="2" />
    <line x1="265" y1="390" x2="265" y2="450" stroke="#2a3848" stroke-width="2" />
    <line x1="240" y1="420" x2="290" y2="420" stroke="#2a3848" stroke-width="2" />

    <!-- Pintu Rumah -->
    <rect x="155" y="440" width="50" height="110" rx="2" fill="#3E2723" />
    <circle cx="165" cy="495" r="4" fill="#FFD54F" />

    <!-- Tanah/Halaman -->
    <path d="M0 520 Q200 500 400 520 V620 H0 Z" fill="#1C2B36" />

    <!-- 3. Karakter Teknisi BisaTukang -->
    <g transform="translate(140, 360)">
      <!-- Helm Proyek -->
      <path d="M40 50 C40 30 80 30 80 50 Z" :fill="url('helmet')" />
      <rect x="35" y="48" width="50" height="5" rx="2" fill="#F57F17" />

      <!-- Kepala -->
      <circle cx="60" cy="65" r="16" fill="#F1C27D" />

      <!-- Badan & Rompi K3 -->
      <path d="M35 85 L85 85 L90 150 L30 150 Z" fill="#1E88E5" />
      <path d="M40 85 L80 85 L83 145 L37 145 Z" :fill="url('vest')" />
      <!-- Pit Reflektif pada Rompi -->
      <rect x="42" y="100" width="36" height="6" fill="#FFFFFF" opacity="0.8" />
      <rect x="40" y="120" width="40" height="6" fill="#FFFFFF" opacity="0.8" />

      <!-- Celana -->
      <rect x="38" y="150" width="20" height="60" fill="#263238" />
      <rect x="62" y="150" width="20" height="60" fill="#263238" />

      <!-- Sepatu Safety -->
      <path d="M32 205 H60 V215 H32 Z" fill="#37474F" />
      <path d="M60 205 H88 V215 H60 Z" fill="#37474F" />

      <!-- Tangan & Perkakas (Toolbox) -->
      <!-- Tangan Kiri memegang Tangga -->
      <path d="M35 90 L15 130" stroke="#F1C27D" stroke-width="8" stroke-linecap="round" />
      <!-- Tangga Aluminium -->
      <g transform="translate(-15, 70) rotate(10)">
        <rect x="0" y="0" width="6" height="130" fill="#B0BEC5" />
        <rect x="25" y="0" width="6" height="130" fill="#B0BEC5" />
        <line x1="0" y1="25" x2="31" y2="25" stroke="#78909C" stroke-width="4" />
        <line x1="0" y1="55" x2="31" y2="55" stroke="#78909C" stroke-width="4" />
        <line x1="0" y1="85" x2="31" y2="85" stroke="#78909C" stroke-width="4" />
        <line x1="0" y1="115" x2="31" y2="115" stroke="#78909C" stroke-width="4" />
      </g>

      <!-- Tangan Kanan memegang Kotak Perkakas Red Toolbox -->
      <path d="M85 90 L105 130" stroke="#F1C27D" stroke-width="8" stroke-linecap="round" />
      <g transform="translate(95, 125)">
        <rect x="0" y="10" width="40" height="28" rx="4" :fill="url('toolbox')" />
        <rect x="15" y="4" width="10" height="6" fill="#37474F" />
        <rect x="16" y="18" width="8" height="6" fill="#FFD54F" />
      </g>
    </g>

    <!-- Gelombang Transisi Bawah ke Card Sheet -->
    <path
      d="M0 570 Q200 530 400 570 V620 H0 Z"
      :fill="p.haze.color"
      opacity="0.3"
    />
  </svg>
</template>
