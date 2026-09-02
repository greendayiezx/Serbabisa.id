<script setup lang="ts">
/**
 * Hero ilustrasi BisaJemput — flat vector 2D, sekeluarga dengan hero
 * BisaAngkut, BisaBelanja, dan BisaBersih: kanvas 400×620, langit dan siluet
 * kota mengikuti waktu nyata lewat palet di @/lib/heroSky.
 *
 * Subjeknya sengaja BUKAN kendaraan kosong: yang dijual menu ini adalah
 * mengantar orang, jadi yang digambar adalah pengemudi DAN penumpang di atas
 * satu motor, plus garis rute dari titik jemput ke tujuan — dua hal yang
 * persis jadi isi halaman berikutnya.
 *
 * Id di <defs> diberi awalan useId(). Id SVG berlaku sedokumen, dan nama
 * sepolos "sky" atau "glass" akan diambil dari definisi pertama yang kebetulan
 * dirender lebih dulu — diam-diam, tanpa galat, dan yang hilang justru
 * elemennya sama sekali tidak digambar.
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
  return `Ilustrasi pengemudi BisaJemput mengantar penumpang melewati jalan kota pada ${waktu}`
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

      <linearGradient :id="id('bldgFar')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgFar[0]" />
        <stop offset="100%" :stop-color="p.bldgFar[1]" />
      </linearGradient>
      <linearGradient :id="id('bldgMid')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgMid[0]" />
        <stop offset="100%" :stop-color="p.bldgMid[1]" />
      </linearGradient>
      <linearGradient :id="id('bldgNear')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.bldgNear[0]" />
        <stop offset="100%" :stop-color="p.bldgNear[1]" />
      </linearGradient>

      <linearGradient :id="id('bodyGrad')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2B4791" />
        <stop offset="60%" stop-color="#1B2C5E" />
        <stop offset="100%" stop-color="#142244" />
      </linearGradient>
      <linearGradient :id="id('uniform')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585" />
        <stop offset="100%" stop-color="#1B2C5E" />
      </linearGradient>
      <linearGradient :id="id('limeGrad')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#B8EF63" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>
      <linearGradient :id="id('visor')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#BFE4F5" />
        <stop offset="55%" stop-color="#7FC0E4" />
        <stop offset="100%" stop-color="#4E8FC4" />
      </linearGradient>

      <radialGradient :id="id('lampGlow')" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#F5E6A8" :stop-opacity="0.30 * p.lampOpacity" />
        <stop offset="100%" stop-color="#F5E6A8" stop-opacity="0" />
      </radialGradient>
      <linearGradient :id="id('beam')" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#FFF3C4" stop-opacity=".55" />
        <stop offset="100%" stop-color="#FFF3C4" stop-opacity="0" />
      </linearGradient>
      <linearGradient :id="id('waveMain')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#A6DE55" />
        <stop offset="100%" stop-color="#7CB539" />
      </linearGradient>
      <radialGradient :id="id('groundShade')" cx="50%" cy="50%" r="50%">
        <stop offset="0%" :stop-color="p.groundShade" stop-opacity=".55" />
        <stop offset="100%" :stop-color="p.groundShade" stop-opacity="0" />
      </radialGradient>
    </defs>

    <!-- ============ LANGIT ============ -->
    <rect width="400" height="620" :fill="url('sky')" />
    <rect y="200" width="400" height="200" :fill="url('horizonHaze')" />

    <g v-if="p.starOpacity > 0" fill="#FFFFFF" :opacity="p.starOpacity">
      <circle cx="34" cy="52" r="1.5" opacity=".85" />
      <circle cx="80" cy="96" r="1" opacity=".55" />
      <circle cx="118" cy="34" r="1.3" opacity=".75" />
      <circle cx="164" cy="78" r=".9" opacity=".5" />
      <circle cx="204" cy="28" r="1.5" opacity=".8" />
      <circle cx="242" cy="66" r="1" opacity=".6" />
      <circle cx="24" cy="130" r="1" opacity=".45" />
      <circle cx="152" cy="120" r="1.2" opacity=".6" />
      <circle cx="272" cy="24" r="1.1" opacity=".7" />
      <circle cx="382" cy="150" r="1.3" opacity=".55" />
      <circle cx="16" cy="196" r="1.1" opacity=".45" />
      <circle cx="98" cy="152" r=".9" opacity=".45" />
      <circle cx="218" cy="108" r="1" opacity=".5" />
      <circle cx="314" cy="164" r="1" opacity=".4" />
    </g>
    <g v-if="p.starOpacity > 0" fill="#FFFFFF" :opacity="0.9 * p.starOpacity">
      <path d="M138,66 l1.6,4.4 4.4,1.6 -4.4,1.6 -1.6,4.4 -1.6,-4.4 -4.4,-1.6 4.4,-1.6z" />
      <path
        d="M264,124 l1.2,3.4 3.4,1.2 -3.4,1.2 -1.2,3.4 -1.2,-3.4 -3.4,-1.2 3.4,-1.2z"
        opacity=".7"
      />
    </g>

    <!-- Matahari / bulan -->
    <circle cx="318" cy="82" r="78" :fill="url('moonGlow')" />
    <g>
      <circle cx="318" cy="82" r="30" :fill="p.celestialCore" />
      <circle v-if="p.celestial === 'moon'" cx="330" cy="72" r="27" :fill="p.crescentCut" />
      <g v-else :stroke="p.celestialCore" stroke-width="3.5" stroke-linecap="round" opacity=".75">
        <line x1="318" y1="36" x2="318" y2="24" />
        <line x1="318" y1="128" x2="318" y2="140" />
        <line x1="272" y1="82" x2="260" y2="82" />
        <line x1="364" y1="82" x2="376" y2="82" />
        <line x1="285" y1="49" x2="277" y2="41" />
        <line x1="351" y1="115" x2="359" y2="123" />
        <line x1="351" y1="49" x2="359" y2="41" />
        <line x1="285" y1="115" x2="277" y2="123" />
      </g>
    </g>

    <g :fill="p.cloud.color" :opacity="p.cloud.opacity">
      <ellipse cx="96" cy="178" rx="60" ry="7" />
      <ellipse cx="146" cy="190" rx="38" ry="5" />
      <ellipse cx="304" cy="166" rx="50" ry="6" />
    </g>

    <!-- ============ KOTA: lapis jauh ============ -->
    <g :fill="url('bldgFar')" opacity=".75">
      <rect x="-8" y="250" width="40" height="152" />
      <rect x="34" y="230" width="30" height="172" />
      <rect x="102" y="242" width="34" height="160" />
      <rect x="172" y="224" width="26" height="178" />
      <rect x="242" y="246" width="32" height="156" />
      <rect x="296" y="232" width="28" height="170" />
      <rect x="352" y="240" width="56" height="162" />
    </g>
    <g :fill="p.window.color" :opacity="p.window.far">
      <rect x="2" y="264" width="5" height="7" /><rect x="16" y="264" width="5" height="7" />
      <rect x="2" y="284" width="5" height="7" /><rect x="42" y="246" width="5" height="7" />
      <rect x="54" y="246" width="5" height="7" /><rect x="42" y="270" width="5" height="7" />
      <rect x="110" y="256" width="5" height="7" /><rect x="124" y="256" width="5" height="7" />
      <rect x="110" y="278" width="5" height="7" /><rect x="178" y="238" width="5" height="7" />
      <rect x="178" y="262" width="5" height="7" /><rect x="250" y="260" width="5" height="7" />
      <rect x="262" y="282" width="5" height="7" /><rect x="304" y="246" width="5" height="7" />
      <rect x="304" y="270" width="5" height="7" /><rect x="362" y="254" width="5" height="7" />
      <rect x="380" y="274" width="5" height="7" />
    </g>

    <!-- ============ KOTA: lapis tengah ============ -->
    <g :fill="url('bldgMid')">
      <rect x="-10" y="284" width="50" height="118" />
      <rect x="44" y="264" width="36" height="138" />
      <rect x="84" y="296" width="26" height="106" />
      <rect x="284" y="270" width="40" height="132" />
      <rect x="328" y="290" width="30" height="112" />
      <rect x="362" y="266" width="46" height="136" />
    </g>
    <g fill="#101E45">
      <rect x="52" y="254" width="6" height="10" />
      <rect x="60" y="258" width="12" height="6" />
      <rect x="372" y="254" width="5" height="12" />
      <rect x="294" y="262" width="14" height="8" />
    </g>
    <line x1="55" y1="238" x2="55" y2="254" stroke="#101E45" stroke-width="2" />
    <circle cx="55" cy="236" r="2.4" fill="#FF6B6B" opacity=".9" />
    <line x1="374" y1="240" x2="374" y2="254" stroke="#101E45" stroke-width="2" />
    <circle cx="374" cy="238" r="2.2" fill="#FF6B6B" opacity=".8" />

    <g :fill="p.window.color" :opacity="p.window.mid">
      <rect x="0" y="298" width="6" height="8" /><rect x="14" y="298" width="6" height="8" />
      <rect x="28" y="298" width="6" height="8" /><rect x="0" y="320" width="6" height="8" />
      <rect x="28" y="320" width="6" height="8" /><rect x="14" y="342" width="6" height="8" />
      <rect x="52" y="278" width="6" height="8" /><rect x="66" y="278" width="6" height="8" />
      <rect x="52" y="300" width="6" height="8" /><rect x="66" y="322" width="6" height="8" />
      <rect x="92" y="310" width="6" height="8" /><rect x="92" y="332" width="6" height="8" />
      <rect x="292" y="284" width="6" height="8" /><rect x="306" y="284" width="6" height="8" />
      <rect x="292" y="306" width="6" height="8" /><rect x="306" y="328" width="6" height="8" />
      <rect x="336" y="304" width="6" height="8" /><rect x="336" y="326" width="6" height="8" />
      <rect x="370" y="280" width="6" height="8" /><rect x="384" y="280" width="6" height="8" />
      <rect x="370" y="302" width="6" height="8" /><rect x="384" y="324" width="6" height="8" />
    </g>

    <!-- ============ KOTA: lapis dekat ============ -->
    <g :fill="url('bldgNear')">
      <path d="M-6,344 L28,344 L28,396 L-6,396 Z" />
      <path d="M-10,346 L11,328 L32,346 Z" />
      <rect x="34" y="354" width="30" height="42" />
      <path d="M30,356 L49,342 L68,356 Z" />
      <path d="M350,342 L384,342 L384,396 L350,396 Z" />
      <path d="M346,344 L367,326 L388,344 Z" />
      <rect x="320" y="356" width="28" height="40" />
    </g>
    <g fill="#B7E36B" opacity=".55">
      <rect x="2" y="358" width="8" height="9" rx="1" />
      <rect x="16" y="358" width="8" height="9" rx="1" />
      <rect x="42" y="366" width="8" height="9" rx="1" />
      <rect x="358" y="356" width="8" height="9" rx="1" />
      <rect x="372" y="356" width="8" height="9" rx="1" />
      <rect x="328" y="368" width="8" height="9" rx="1" />
    </g>

    <!-- Lampu jalan -->
    <g>
      <circle cx="66" cy="320" r="34" :fill="url('lampGlow')" />
      <rect x="64" y="320" width="3.5" height="78" fill="#0C1734" />
      <path d="M65.5,320 q0,-14 16,-14" fill="none" stroke="#0C1734" stroke-width="3.5" />
      <ellipse cx="82" cy="308" rx="7" ry="4" :fill="p.lampOpacity > 0 ? '#CDEB8E' : '#9FB4C6'" />
    </g>

    <!--
      Rute: titik jemput (bulat, lime) ke tujuan (pin). Urutannya sengaja
      begitu — di seluruh menu ini titik jemput selalu yang pertama, dan
      gambarnya tidak boleh mengatakan hal yang berbeda dari alurnya.
    -->
    <g opacity=".95">
      <path
        d="M86,236 C126,206 186,206 226,222"
        fill="none"
        stroke="#8BC53F"
        stroke-width="2.4"
        stroke-linecap="round"
        stroke-dasharray="7 7"
        opacity=".6"
      />
      <circle cx="86" cy="236" r="5.5" fill="#8BC53F" opacity=".85" />
      <circle cx="86" cy="236" r="2.2" fill="#12224E" />
      <g transform="translate(226,194)">
        <path d="M12,34 C12,34 24,20 24,12 A12,12 0 1 0 0,12 C0,20 12,34 12,34 Z" fill="#8BC53F" />
        <circle cx="12" cy="12" r="4.6" fill="#12224E" />
      </g>
    </g>

    <!-- ============ JALAN ============ -->
    <rect x="0" y="396" width="400" height="224" :fill="p.road.asphalt" />
    <rect x="0" y="396" width="400" height="3" :fill="p.road.kerb" />
    <g :fill="p.road.lane" opacity=".45">
      <rect x="18" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="70" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="122" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="174" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="226" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="278" y="428" width="26" height="3.5" rx="1.5" />
      <rect x="330" y="428" width="26" height="3.5" rx="1.5" />
    </g>
    <path
      d="M300,384 L400,372 L400,400 L300,390 Z"
      :fill="url('beam')"
      :opacity="p.celestial === 'moon' ? 0.55 : 0.18"
    />

    <!-- ============ MOTOR: pengemudi + penumpang ============ -->
    <ellipse cx="196" cy="400" rx="118" ry="13" :fill="url('groundShade')" />

    <g transform="translate(112,286)">
      <!-- Roda belakang -->
      <circle cx="24" cy="102" r="25" fill="#141F3E" />
      <circle cx="24" cy="102" r="16" fill="#233258" />
      <circle cx="24" cy="102" r="6" fill="#8BC53F" opacity=".85" />
      <!-- Roda depan -->
      <circle cx="152" cy="102" r="25" fill="#141F3E" />
      <circle cx="152" cy="102" r="16" fill="#233258" />
      <circle cx="152" cy="102" r="6" fill="#8BC53F" opacity=".85" />

      <!-- Bodi skuter -->
      <path
        d="M28,96 C34,70 56,64 74,64 L104,64 C118,64 128,72 136,86 L146,98
           C148,101 146,105 142,105 L120,105 C112,88 96,84 84,92
           C78,96 74,100 72,105 L36,105 C30,105 27,101 28,96 Z"
        :fill="url('bodyGrad')"
      />
      <!-- Pijakan kaki & dek -->
      <path d="M46,94 L104,94 L104,101 L46,101 Z" fill="#101B38" opacity=".85" />
      <!-- Jok memanjang: dua orang duduk di sini -->
      <path d="M40,66 L112,66 C118,66 120,72 114,74 L44,74 C38,74 36,68 40,66 Z" fill="#0E1730" />
      <!-- Setang -->
      <path
        d="M132,60 L150,44"
        stroke="#0E1730"
        stroke-width="6"
        stroke-linecap="round"
        fill="none"
      />
      <circle cx="152" cy="42" r="4.5" fill="#8BC53F" />
      <!-- Garpu depan -->
      <path d="M140,72 L152,96" stroke="#101B38" stroke-width="6" stroke-linecap="round" />
      <!--
        Batok lampu menempel di pangkal setang, bukan mengambang di udara.
        Lampunya ikut padam saat siang lewat lampOpacity, seperti lampu jalan.
      -->
      <path d="M136,50 L152,46 L154,60 L138,62 Z" fill="#1B2C5E" />
      <ellipse
        cx="150"
        cy="53"
        rx="6"
        ry="5.5"
        fill="#FFF3C4"
        :opacity="p.lampOpacity > 0 ? 0.95 : 0.55"
      />
      <!-- Spakbor -->
      <path
        d="M132,88 a24,24 0 0 1 40,10"
        fill="none"
        stroke="#1B2C5E"
        stroke-width="5"
        stroke-linecap="round"
      />

      <!-- ===== Penumpang (di belakang) ===== -->
      <g transform="translate(30,6)">
        <!-- Badan -->
        <path d="M14,60 C10,42 16,30 26,30 C36,30 42,42 38,60 Z" :fill="url('limeGrad')" />
        <!-- Lengan memegang pegangan -->
        <path
          d="M34,42 L46,52"
          stroke="#9CD44F"
          stroke-width="6"
          stroke-linecap="round"
          fill="none"
        />
        <!-- Kaki -->
        <path d="M18,58 L16,74" stroke="#1B2C5E" stroke-width="7" stroke-linecap="round" />
        <!-- Kepala + helm -->
        <circle cx="26" cy="20" r="11" fill="#E8B98C" />
        <path d="M15,20 a11,11 0 0 1 22,0 z" fill="#2A4585" />
        <path d="M15,20 h22 q4,0 4.6,3 h-26.6 z" fill="#8BC53F" />
        <path d="M34,15 a9,9 0 0 1 1,8 l-6,-1 z" :fill="url('visor')" opacity=".9" />
      </g>

      <!-- ===== Pengemudi (di depan) ===== -->
      <g transform="translate(76,0)">
        <path d="M12,64 C8,44 16,30 28,30 C40,30 46,44 42,64 Z" :fill="url('uniform')" />
        <!-- Lengan ke setang -->
        <path
          d="M38,44 L58,50"
          stroke="#2A4585"
          stroke-width="6.5"
          stroke-linecap="round"
          fill="none"
        />
        <!-- Kaki di pijakan -->
        <path d="M18,62 L14,80" stroke="#16244F" stroke-width="7.5" stroke-linecap="round" />
        <!-- Kepala + helm -->
        <circle cx="28" cy="18" r="11.5" fill="#E8B98C" />
        <path d="M16.5,18 a11.5,11.5 0 0 1 23,0 z" fill="#16244F" />
        <path d="M16.5,18 h23 q5,0 5.8,3.4 h-28.8 z" fill="#8BC53F" />
        <path d="M36,13 a9.5,9.5 0 0 1 1,8.5 l-6.5,-1 z" :fill="url('visor')" opacity=".9" />
        <!-- Tas punggung kurir -->
        <rect x="4" y="38" width="12" height="18" rx="3" fill="#8BC53F" opacity=".9" />
      </g>
    </g>

    <!-- ============ GELOMBANG ============ -->
    <path
      d="M0,452 C64,420 128,492 208,472 C282,454 332,420 400,444 L400,620 L0,620 Z"
      fill="#8BC53F"
      opacity=".22"
    />
    <path
      d="M0,470 C70,438 130,508 210,490 C280,474 332,436 400,462 L400,620 L0,620 Z"
      :fill="url('waveMain')"
    />
  </svg>
</template>
