<script setup lang="ts">
/**
 * Hero ilustrasi BisaKirim — flat vector 2D, sekeluarga dengan hero BisaAngkut,
 * BisaBelanja, BisaBersih, dan BisaJemput: kanvas 400×620, langit dan siluet
 * kota mengikuti waktu nyata lewat palet di @/lib/heroSky.
 *
 * Subjeknya kurir motor dengan boks paket di belakang, plus tumpukan kardus di
 * trotoar — yang dijual menu ini mengantar barang, bukan orang, dan gambarnya
 * harus bisa dibedakan dari hero BisaJemput dalam sekali lihat.
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
  return `Ilustrasi kurir BisaKirim mengantar paket melewati jalan kota pada ${waktu}`
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
      <linearGradient :id="id('boxGrad')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#28407F" />
        <stop offset="100%" stop-color="#16264F" />
      </linearGradient>
      <linearGradient :id="id('parcel')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#D9A967" />
        <stop offset="100%" stop-color="#B8853F" />
      </linearGradient>
      <linearGradient :id="id('uniform')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585" />
        <stop offset="100%" stop-color="#1B2C5E" />
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
      <circle cx="30" cy="50" r="1.5" opacity=".85" />
      <circle cx="76" cy="94" r="1" opacity=".55" />
      <circle cx="114" cy="32" r="1.3" opacity=".75" />
      <circle cx="160" cy="76" r=".9" opacity=".5" />
      <circle cx="200" cy="26" r="1.5" opacity=".8" />
      <circle cx="238" cy="64" r="1" opacity=".6" />
      <circle cx="20" cy="128" r="1" opacity=".45" />
      <circle cx="148" cy="118" r="1.2" opacity=".6" />
      <circle cx="268" cy="22" r="1.1" opacity=".7" />
      <circle cx="378" cy="148" r="1.3" opacity=".55" />
      <circle cx="14" cy="194" r="1.1" opacity=".45" />
      <circle cx="94" cy="150" r=".9" opacity=".45" />
      <circle cx="214" cy="106" r="1" opacity=".5" />
      <circle cx="310" cy="162" r="1" opacity=".4" />
    </g>
    <g v-if="p.starOpacity > 0" fill="#FFFFFF" :opacity="0.9 * p.starOpacity">
      <path d="M134,64 l1.6,4.4 4.4,1.6 -4.4,1.6 -1.6,4.4 -1.6,-4.4 -4.4,-1.6 4.4,-1.6z" />
      <path
        d="M260,122 l1.2,3.4 3.4,1.2 -3.4,1.2 -1.2,3.4 -1.2,-3.4 -3.4,-1.2 3.4,-1.2z"
        opacity=".7"
      />
    </g>

    <!-- Matahari / bulan -->
    <circle cx="316" cy="80" r="78" :fill="url('moonGlow')" />
    <g>
      <circle cx="316" cy="80" r="30" :fill="p.celestialCore" />
      <circle v-if="p.celestial === 'moon'" cx="328" cy="70" r="27" :fill="p.crescentCut" />
      <g v-else :stroke="p.celestialCore" stroke-width="3.5" stroke-linecap="round" opacity=".75">
        <line x1="316" y1="34" x2="316" y2="22" />
        <line x1="316" y1="126" x2="316" y2="138" />
        <line x1="270" y1="80" x2="258" y2="80" />
        <line x1="362" y1="80" x2="374" y2="80" />
        <line x1="283" y1="47" x2="275" y2="39" />
        <line x1="349" y1="113" x2="357" y2="121" />
        <line x1="349" y1="47" x2="357" y2="39" />
        <line x1="283" y1="113" x2="275" y2="121" />
      </g>
    </g>

    <g :fill="p.cloud.color" :opacity="p.cloud.opacity">
      <ellipse cx="98" cy="176" rx="60" ry="7" />
      <ellipse cx="148" cy="188" rx="38" ry="5" />
      <ellipse cx="302" cy="164" rx="50" ry="6" />
    </g>

    <!-- ============ KOTA: lapis jauh ============ -->
    <g :fill="url('bldgFar')" opacity=".75">
      <rect x="-8" y="248" width="40" height="154" />
      <rect x="34" y="228" width="30" height="174" />
      <rect x="100" y="240" width="34" height="162" />
      <rect x="170" y="222" width="26" height="180" />
      <rect x="240" y="244" width="32" height="158" />
      <rect x="294" y="230" width="28" height="172" />
      <rect x="350" y="238" width="58" height="164" />
    </g>
    <g :fill="p.window.color" :opacity="p.window.far">
      <rect x="2" y="262" width="5" height="7" /><rect x="16" y="262" width="5" height="7" />
      <rect x="2" y="282" width="5" height="7" /><rect x="42" y="244" width="5" height="7" />
      <rect x="54" y="244" width="5" height="7" /><rect x="42" y="268" width="5" height="7" />
      <rect x="108" y="254" width="5" height="7" /><rect x="122" y="254" width="5" height="7" />
      <rect x="108" y="276" width="5" height="7" /><rect x="176" y="236" width="5" height="7" />
      <rect x="176" y="260" width="5" height="7" /><rect x="248" y="258" width="5" height="7" />
      <rect x="260" y="280" width="5" height="7" /><rect x="302" y="244" width="5" height="7" />
      <rect x="302" y="268" width="5" height="7" /><rect x="360" y="252" width="5" height="7" />
      <rect x="378" y="272" width="5" height="7" />
    </g>

    <!-- ============ KOTA: lapis tengah ============ -->
    <g :fill="url('bldgMid')">
      <rect x="-10" y="282" width="50" height="120" />
      <rect x="44" y="262" width="36" height="140" />
      <rect x="84" y="294" width="26" height="108" />
      <rect x="282" y="268" width="40" height="134" />
      <rect x="326" y="288" width="30" height="114" />
      <rect x="360" y="264" width="48" height="138" />
    </g>
    <g fill="#101E45">
      <rect x="52" y="252" width="6" height="10" />
      <rect x="60" y="256" width="12" height="6" />
      <rect x="370" y="252" width="5" height="12" />
      <rect x="292" y="260" width="14" height="8" />
    </g>
    <line x1="55" y1="236" x2="55" y2="252" stroke="#101E45" stroke-width="2" />
    <circle cx="55" cy="234" r="2.4" fill="#FF6B6B" opacity=".9" />
    <line x1="372" y1="238" x2="372" y2="252" stroke="#101E45" stroke-width="2" />
    <circle cx="372" cy="236" r="2.2" fill="#FF6B6B" opacity=".8" />

    <g :fill="p.window.color" :opacity="p.window.mid">
      <rect x="0" y="296" width="6" height="8" /><rect x="14" y="296" width="6" height="8" />
      <rect x="28" y="296" width="6" height="8" /><rect x="0" y="318" width="6" height="8" />
      <rect x="28" y="318" width="6" height="8" /><rect x="14" y="340" width="6" height="8" />
      <rect x="52" y="276" width="6" height="8" /><rect x="66" y="276" width="6" height="8" />
      <rect x="52" y="298" width="6" height="8" /><rect x="66" y="320" width="6" height="8" />
      <rect x="92" y="308" width="6" height="8" /><rect x="92" y="330" width="6" height="8" />
      <rect x="290" y="282" width="6" height="8" /><rect x="304" y="282" width="6" height="8" />
      <rect x="290" y="304" width="6" height="8" /><rect x="304" y="326" width="6" height="8" />
      <rect x="334" y="302" width="6" height="8" /><rect x="334" y="324" width="6" height="8" />
      <rect x="368" y="278" width="6" height="8" /><rect x="382" y="278" width="6" height="8" />
      <rect x="368" y="300" width="6" height="8" /><rect x="382" y="322" width="6" height="8" />
    </g>

    <!-- ============ KOTA: lapis dekat ============ -->
    <g :fill="url('bldgNear')">
      <path d="M-6,342 L28,342 L28,396 L-6,396 Z" />
      <path d="M-10,344 L11,326 L32,344 Z" />
      <rect x="34" y="352" width="30" height="44" />
      <path d="M30,354 L49,340 L68,354 Z" />
      <path d="M348,340 L382,340 L382,396 L348,396 Z" />
      <path d="M344,342 L365,324 L386,342 Z" />
      <rect x="318" y="354" width="28" height="42" />
    </g>
    <g fill="#B7E36B" opacity=".55">
      <rect x="2" y="356" width="8" height="9" rx="1" />
      <rect x="16" y="356" width="8" height="9" rx="1" />
      <rect x="42" y="364" width="8" height="9" rx="1" />
      <rect x="356" y="354" width="8" height="9" rx="1" />
      <rect x="370" y="354" width="8" height="9" rx="1" />
      <rect x="326" y="366" width="8" height="9" rx="1" />
    </g>

    <!-- Lampu jalan -->
    <g>
      <circle cx="64" cy="318" r="34" :fill="url('lampGlow')" />
      <rect x="62" y="318" width="3.5" height="80" fill="#0C1734" />
      <path d="M63.5,318 q0,-14 16,-14" fill="none" stroke="#0C1734" stroke-width="3.5" />
      <ellipse cx="80" cy="306" rx="7" ry="4" :fill="p.lampOpacity > 0 ? '#CDEB8E' : '#9FB4C6'" />
    </g>

    <!--
      Rute: titik ambil (bulat) ke titik antar (pin). Urutannya sengaja begitu —
      di seluruh menu ini paket selalu diambil dulu baru diantar, dan gambarnya
      tidak boleh mengatakan hal yang berbeda dari alurnya.
    -->
    <g opacity=".95">
      <path
        d="M84,238 C124,208 184,208 224,224"
        fill="none"
        stroke="#8BC53F"
        stroke-width="2.4"
        stroke-linecap="round"
        stroke-dasharray="7 7"
        opacity=".6"
      />
      <circle cx="84" cy="238" r="5.5" fill="#8BC53F" opacity=".85" />
      <circle cx="84" cy="238" r="2.2" fill="#12224E" />
      <g transform="translate(224,196)">
        <path d="M12,34 C12,34 24,20 24,12 A12,12 0 1 0 0,12 C0,20 12,34 12,34 Z" fill="#F97316" />
        <circle cx="12" cy="12" r="4.6" fill="#FFFFFF" />
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

    <!-- ============ MOTOR KURIR DENGAN BOKS PAKET ============ -->
    <ellipse cx="196" cy="400" rx="120" ry="13" :fill="url('groundShade')" />

    <g transform="translate(104,282)">
      <!-- Roda -->
      <circle cx="26" cy="106" r="25" fill="#141F3E" />
      <circle cx="26" cy="106" r="16" fill="#233258" />
      <circle cx="26" cy="106" r="6" fill="#8BC53F" opacity=".85" />
      <circle cx="158" cy="106" r="25" fill="#141F3E" />
      <circle cx="158" cy="106" r="16" fill="#233258" />
      <circle cx="158" cy="106" r="6" fill="#8BC53F" opacity=".85" />

      <!-- Bodi skuter -->
      <path
        d="M30,100 C36,74 58,68 76,68 L108,68 C122,68 132,76 140,90 L150,102
           C152,105 150,109 146,109 L124,109 C116,92 100,88 88,96
           C82,100 78,104 76,109 L38,109 C32,109 29,105 30,100 Z"
        :fill="url('bodyGrad')"
      />
      <path d="M48,98 L108,98 L108,105 L48,105 Z" fill="#101B38" opacity=".85" />

      <!-- Boks paket di belakang: penanda paling jelas bahwa ini kurir barang -->
      <g transform="translate(4,10)">
        <rect x="0" y="14" width="66" height="52" rx="7" :fill="url('boxGrad')" />
        <rect x="0" y="14" width="66" height="6" rx="3" fill="#33508F" opacity=".8" />
        <line x1="0" y1="42" x2="66" y2="42" stroke="#16264F" stroke-width="1" opacity=".8" />
        <!-- Lambang paket di sisi boks -->
        <rect x="20" y="26" width="26" height="20" rx="3" :fill="url('parcel')" />
        <rect x="20" y="33" width="26" height="4" fill="#9C6C2E" opacity=".8" />
      </g>

      <!-- Jok, setang, garpu -->
      <path d="M74,70 L118,70 C124,70 126,76 120,78 L78,78 C72,78 70,72 74,70 Z" fill="#0E1730" />
      <path
        d="M136,62 L154,46"
        stroke="#0E1730"
        stroke-width="6"
        stroke-linecap="round"
        fill="none"
      />
      <circle cx="156" cy="44" r="4.5" fill="#8BC53F" />
      <path d="M144,74 L158,100" stroke="#101B38" stroke-width="6" stroke-linecap="round" />

      <!-- Batok lampu menempel di pangkal setang, bukan mengambang -->
      <path d="M140,52 L156,48 L158,62 L142,64 Z" fill="#1B2C5E" />
      <ellipse
        cx="154"
        cy="55"
        rx="6"
        ry="5.5"
        fill="#FFF3C4"
        :opacity="p.lampOpacity > 0 ? 0.95 : 0.55"
      />

      <path
        d="M136,90 a24,24 0 0 1 40,10"
        fill="none"
        stroke="#1B2C5E"
        stroke-width="5"
        stroke-linecap="round"
      />

      <!-- ===== Kurir ===== -->
      <g transform="translate(80,2)">
        <path d="M12,66 C8,46 16,32 28,32 C40,32 46,46 42,66 Z" :fill="url('uniform')" />
        <path
          d="M38,46 L58,52"
          stroke="#2A4585"
          stroke-width="6.5"
          stroke-linecap="round"
          fill="none"
        />
        <path d="M18,64 L14,82" stroke="#16244F" stroke-width="7.5" stroke-linecap="round" />
        <circle cx="28" cy="20" r="11.5" fill="#E8B98C" />
        <path d="M16.5,20 a11.5,11.5 0 0 1 23,0 z" fill="#16244F" />
        <path d="M16.5,20 h23 q5,0 5.8,3.4 h-28.8 z" fill="#8BC53F" />
        <path d="M36,15 a9.5,9.5 0 0 1 1,8.5 l-6.5,-1 z" :fill="url('visor')" opacity=".9" />
      </g>
    </g>

    <!-- Tumpukan kardus di trotoar kanan -->
    <g transform="translate(346,362)">
      <ellipse cx="15" cy="36" rx="19" ry="4" fill="#04081A" opacity=".4" />
      <rect x="0" y="16" width="30" height="19" rx="2.5" :fill="url('parcel')" />
      <rect x="0" y="23" width="30" height="4" fill="#9C6C2E" opacity=".8" />
      <rect x="5" y="1" width="21" height="15" rx="2.5" fill="#C79A5A" />
      <rect x="5" y="6.5" width="21" height="3.4" fill="#8B6636" opacity=".8" />
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
