<script setup lang="ts">
/**
 * Hero ilustrasi BisaKirim — flat vector 2D, warnanya mengikuti waktu nyata
 * lewat palet di @/lib/heroSky, sama seperti hero menu lain.
 *
 * ADEGANNYA SENGAJA BUKAN JALAN RAYA. Hero BisaJemput sudah memakai susunan
 * itu — langit, siluet kota berjajar, aspal bermarka, satu kendaraan melintas —
 * dan mengulanginya di sini membuat dua menu berbeda terlihat seperti menu yang
 * sama pada pandangan pertama.
 *
 * Yang digambar di sini SERAH-TERIMA PAKET di depan rumah: kurir menyerahkan
 * kardus ke penerima, dengan pagar, pohon, dan jalan setapak. Itu bagian dari
 * BisaKirim yang tidak dimiliki BisaJemput — paket berpindah tangan, bukan
 * orang berpindah tempat.
 *
 * Id di <defs> diberi awalan useId(). Id SVG berlaku sedokumen, dan nama polos
 * akan diambil dari definisi pertama yang kebetulan dirender lebih dulu —
 * diam-diam, tanpa galat, dan yang hilang justru elemennya tidak digambar.
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
  return `Ilustrasi kurir BisaKirim menyerahkan paket di depan rumah pada ${waktu}`
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

      <!-- Rumah memakai palet gedung supaya tetap sekeluarga dengan hero lain. -->
      <linearGradient :id="id('rumah')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgNear[0]" />
        <stop offset="100%" :stop-color="p.bldgNear[1]" />
      </linearGradient>
      <linearGradient :id="id('atap')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgMid[0]" />
        <stop offset="100%" :stop-color="p.bldgMid[1]" />
      </linearGradient>
      <linearGradient :id="id('pohon')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2E5B33" />
        <stop offset="100%" stop-color="#173A21" />
      </linearGradient>

      <linearGradient :id="id('kardus')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#D9A967" />
        <stop offset="100%" stop-color="#B8853F" />
      </linearGradient>
      <linearGradient :id="id('seragam')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585" />
        <stop offset="100%" stop-color="#1B2C5E" />
      </linearGradient>
      <linearGradient :id="id('baju')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#B8EF63" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>

      <radialGradient :id="id('terasGlow')" cx="50%" cy="40%" r="60%">
        <stop offset="0%" stop-color="#FFE9A8" :stop-opacity="0.42 * p.lampOpacity" />
        <stop offset="100%" stop-color="#FFE9A8" stop-opacity="0" />
      </radialGradient>
      <radialGradient :id="id('groundShade')" cx="50%" cy="50%" r="50%">
        <stop offset="0%" :stop-color="p.groundShade" stop-opacity=".5" />
        <stop offset="100%" :stop-color="p.groundShade" stop-opacity="0" />
      </radialGradient>
      <linearGradient :id="id('waveMain')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#A6DE55" />
        <stop offset="100%" stop-color="#7CB539" />
      </linearGradient>
      <linearGradient :id="id('jalanSetapak')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.road.kerb" />
        <stop offset="100%" :stop-color="p.road.asphalt" />
      </linearGradient>
    </defs>

    <!-- ============ LANGIT ============ -->
    <rect width="400" height="620" :fill="url('sky')" />
    <rect y="230" width="400" height="200" :fill="url('horizonHaze')" />

    <g v-if="p.starOpacity > 0" fill="#FFFFFF" :opacity="p.starOpacity">
      <circle cx="42" cy="56" r="1.4" opacity=".85" />
      <circle cx="96" cy="30" r="1" opacity=".55" />
      <circle cx="150" cy="72" r="1.3" opacity=".7" />
      <circle cx="206" cy="40" r="1" opacity=".6" />
      <circle cx="262" cy="88" r="1.2" opacity=".65" />
      <circle cx="330" cy="34" r="1" opacity=".5" />
      <circle cx="24" cy="132" r="1" opacity=".45" />
      <circle cx="120" cy="118" r="1.2" opacity=".6" />
      <circle cx="292" cy="140" r="1" opacity=".45" />
      <circle cx="372" cy="104" r="1.2" opacity=".55" />
    </g>
    <g v-if="p.starOpacity > 0" fill="#FFFFFF" :opacity="0.9 * p.starOpacity">
      <path d="M76,90 l1.6,4.4 4.4,1.6 -4.4,1.6 -1.6,4.4 -1.6,-4.4 -4.4,-1.6 4.4,-1.6z" />
      <path
        d="M318,150 l1.2,3.4 3.4,1.2 -3.4,1.2 -1.2,3.4 -1.2,-3.4 -3.4,-1.2 3.4,-1.2z"
        opacity=".7"
      />
    </g>

    <!-- Matahari / bulan, digeser ke kiri supaya tidak menimpa atap rumah -->
    <circle cx="92" cy="86" r="74" :fill="url('moonGlow')" />
    <g>
      <circle cx="92" cy="86" r="28" :fill="p.celestialCore" />
      <circle v-if="p.celestial === 'moon'" cx="103" cy="77" r="25" :fill="p.crescentCut" />
      <g v-else :stroke="p.celestialCore" stroke-width="3.5" stroke-linecap="round" opacity=".75">
        <line x1="92" y1="44" x2="92" y2="32" />
        <line x1="92" y1="128" x2="92" y2="140" />
        <line x1="50" y1="86" x2="38" y2="86" />
        <line x1="134" y1="86" x2="146" y2="86" />
        <line x1="62" y1="56" x2="54" y2="48" />
        <line x1="122" y1="116" x2="130" y2="124" />
        <line x1="122" y1="56" x2="130" y2="48" />
        <line x1="62" y1="116" x2="54" y2="124" />
      </g>
    </g>

    <g :fill="p.cloud.color" :opacity="p.cloud.opacity">
      <ellipse cx="270" cy="70" rx="54" ry="7" />
      <ellipse cx="228" cy="84" rx="34" ry="5" />
    </g>

    <!-- ============ POHON DI BELAKANG PAGAR ============ -->
    <g :fill="url('pohon')" opacity=".9">
      <ellipse cx="46" cy="268" rx="44" ry="52" />
      <rect x="41" y="300" width="10" height="52" :fill="p.bldgFar[1]" />
      <ellipse cx="366" cy="252" rx="38" ry="46" />
      <rect x="361" y="282" width="9" height="70" :fill="p.bldgFar[1]" />
      <ellipse cx="318" cy="286" rx="26" ry="30" opacity=".85" />
    </g>

    <!-- ============ RUMAH TUJUAN ============ -->
    <g>
      <!-- Badan rumah -->
      <rect x="150" y="238" width="196" height="150" :fill="url('rumah')" />
      <!-- Atap -->
      <path d="M138,240 L248,168 L358,240 Z" :fill="url('atap')" />
      <path d="M138,240 L358,240 L358,248 L138,248 Z" :fill="p.bldgMid[1]" />

      <!-- Jendela menyala; ikut padam saat siang lewat window.mid -->
      <rect x="172" y="272" width="42" height="38" rx="3" fill="#0F1C3E" />
      <rect
        x="176"
        y="276"
        width="34"
        height="30"
        rx="2"
        :fill="p.window.color"
        :opacity="p.window.mid"
      />
      <line x1="193" y1="272" x2="193" y2="310" :stroke="p.bldgNear[1]" stroke-width="2.5" />

      <rect x="292" y="272" width="42" height="38" rx="3" fill="#0F1C3E" />
      <rect
        x="296"
        y="276"
        width="34"
        height="30"
        rx="2"
        :fill="p.window.color"
        :opacity="p.window.mid"
      />
      <line x1="313" y1="272" x2="313" y2="310" :stroke="p.bldgNear[1]" stroke-width="2.5" />

      <!-- Pintu tempat paket diserahkan -->
      <rect x="228" y="288" width="56" height="100" rx="4" fill="#101E45" />
      <rect x="233" y="294" width="46" height="88" rx="3" :fill="p.bldgMid[0]" opacity=".85" />
      <circle cx="272" cy="340" r="3" fill="#B8EF63" opacity=".9" />

      <!-- Lampu teras -->
      <circle cx="300" cy="286" r="42" :fill="url('terasGlow')" />
      <path d="M296,272 h8 l3,10 h-14 z" fill="#101E45" />
      <ellipse cx="300" cy="284" rx="6" ry="4" :fill="p.lampOpacity > 0 ? '#FFE9A8' : '#9FB4C6'" />

      <!-- Nomor rumah -->
      <rect x="196" y="330" width="18" height="12" rx="2" fill="#8BC53F" opacity=".85" />
    </g>

    <!-- ============ PAGAR & HALAMAN ============ -->
    <rect x="0" y="388" width="400" height="232" :fill="p.road.asphalt" opacity=".55" />
    <path d="M0,388 L400,388 L400,398 L0,398 Z" :fill="p.road.kerb" />

    <!-- Jalan setapak menuju pintu: pengganti aspal bermarka -->
    <path d="M232,388 L280,388 L326,500 L186,500 Z" :fill="url('jalanSetapak')" />
    <g :fill="p.road.lane" opacity=".38">
      <path d="M236,404 L276,404 L280,420 L232,420 Z" />
      <path d="M228,432 L284,432 L289,450 L223,450 Z" />
      <path d="M218,462 L294,462 L300,482 L212,482 Z" />
    </g>

    <!-- Pagar rendah kiri-kanan -->
    <g :fill="p.bldgFar[1]">
      <rect x="-4" y="360" width="146" height="6" />
      <rect x="-4" y="376" width="146" height="6" />
      <rect v-for="x in 8" :key="`kiri-${x}`" :x="(x - 1) * 20 - 4" y="352" width="6" height="36" />
      <rect x="352" y="360" width="52" height="6" />
      <rect x="352" y="376" width="52" height="6" />
      <rect v-for="x in 3" :key="`kanan-${x}`" :x="352 + (x - 1) * 20" y="352" width="6" height="36" />
    </g>

    <!-- ============ SERAH-TERIMA PAKET ============ -->
    <ellipse cx="196" cy="516" rx="120" ry="16" :fill="url('groundShade')" />

    <!-- Penerima, berdiri di depan pintu -->
    <g transform="translate(246,384)">
      <path d="M10,124 C4,84 12,58 30,58 C48,58 56,84 50,124 Z" :fill="url('baju')" />
      <!-- Lengan menerima kardus -->
      <path
        d="M14,74 L-14,86"
        stroke="#9CD44F"
        stroke-width="10"
        stroke-linecap="round"
        fill="none"
      />
      <path d="M20,120 L18,142" stroke="#1B2C5E" stroke-width="9" stroke-linecap="round" />
      <path d="M40,120 L44,142" stroke="#1B2C5E" stroke-width="9" stroke-linecap="round" />
      <circle cx="30" cy="40" r="19" fill="#E8B98C" />
      <path d="M11,38 a19,19 0 0 1 38,0 l-6,-14 -26,0 z" fill="#2C2015" />
    </g>

    <!-- Kardus yang sedang berpindah tangan: pusat cerita hero ini -->
    <g transform="translate(176,440)">
      <rect x="0" y="0" width="62" height="46" rx="5" :fill="url('kardus')" />
      <rect x="0" y="16" width="62" height="8" fill="#9C6C2E" opacity=".8" />
      <rect x="28" y="0" width="10" height="46" fill="#9C6C2E" opacity=".45" />
      <path d="M8,0 h14 v-7 h-14 z" fill="#8BC53F" opacity=".9" />
    </g>

    <!-- Kurir, menyerahkan -->
    <g transform="translate(112,378)">
      <path d="M14,130 C8,88 16,60 34,60 C52,60 60,88 54,130 Z" :fill="url('seragam')" />
      <!-- Lengan mengulurkan kardus -->
      <path
        d="M48,80 L76,72"
        stroke="#2A4585"
        stroke-width="10"
        stroke-linecap="round"
        fill="none"
      />
      <path d="M24,126 L20,150" stroke="#16244F" stroke-width="9" stroke-linecap="round" />
      <path d="M44,126 L50,150" stroke="#16244F" stroke-width="9" stroke-linecap="round" />
      <circle cx="34" cy="42" r="19.5" fill="#E8B98C" />
      <path d="M14.5,42 a19.5,19.5 0 0 1 39,0 z" fill="#16244F" />
      <path d="M14.5,42 h39 q8,0 9.5,5.5 h-48.5 z" fill="#8BC53F" />
      <!-- Tas punggung kurir -->
      <rect x="2" y="70" width="14" height="24" rx="4" fill="#8BC53F" opacity=".9" />
    </g>

    <!-- Tumpukan kardus di sisi kiri jalan setapak -->
    <g transform="translate(48,452)">
      <ellipse cx="17" cy="42" rx="22" ry="5" fill="#04081A" opacity=".35" />
      <rect x="0" y="18" width="35" height="23" rx="3" :fill="url('kardus')" />
      <rect x="0" y="26" width="35" height="5" fill="#9C6C2E" opacity=".8" />
      <rect x="6" y="0" width="24" height="18" rx="3" fill="#C79A5A" />
      <rect x="6" y="6.5" width="24" height="4" fill="#8B6636" opacity=".8" />
    </g>

    <!-- ============ GELOMBANG ============ -->
    <path
      d="M0,512 C64,482 128,552 208,532 C282,514 332,480 400,504 L400,620 L0,620 Z"
      fill="#8BC53F"
      opacity=".22"
    />
    <path
      d="M0,530 C70,498 130,568 210,550 C280,534 332,496 400,522 L400,620 L0,620 Z"
      :fill="url('waveMain')"
    />
  </svg>
</template>
