<script setup lang="ts">
/**
 * Ilustrasi hero BisaBersih di halaman pemilih lokasi.
 *
 * Berbeda dengan AngkutHeroArt & BisaBelanjaHeroArt yang sapaannya ditumpuk
 * dari LocationView, panel sapaan di sini bagian dari gambarnya sendiri (gelombang
 * lime di bawah). Karena itu teksnya masuk lewat props, bukan overlay.
 *
 * Nuansa langitnya kini mengikuti waktu nyata (pagi/siang/sore/malam) lewat palet
 * bersama di @/lib/heroSky — sama seperti dua hero lainnya. Langit, benda langit
 * (bulan sabit / matahari), bintang, kabut horizon, siluet pohon, jalan, kaca
 * jendela, dan pendar lampu teras semuanya diturunkan dari palet itu. Foreground
 * (rumah, petugas, ember, gelembung, gelombang lime) tetap tampil sama di semua
 * waktu karena punya pencahayaan sendiri.
 *
 * Semua id gradient/filter diberi awalan bb- supaya tidak bentrok dengan SVG
 * lain di halaman yang sama.
 */
import { computed } from 'vue'
import { HERO_SKY, type HeroTimeOfDay } from '@/lib/heroSky'

const props = withDefaults(
  defineProps<{
    greeting: string
    nama?: string
    subtitle: string
    timeOfDay?: HeroTimeOfDay
  }>(),
  { timeOfDay: 'malam' },
)

const p = computed(() => HERO_SKY[props.timeOfDay])
</script>

<template>
  <svg viewBox="0 0 400 620" xmlns="http://www.w3.org/2000/svg" class="block w-full h-auto">
    <defs>
      <linearGradient id="bbSky" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.sky[0]" />
        <stop offset="38%" :stop-color="p.sky[1]" />
        <stop offset="72%" :stop-color="p.sky[2]" />
        <stop offset="100%" :stop-color="p.sky[3]" />
      </linearGradient>
      <radialGradient id="bbHaze" cx="50%" cy="100%" r="70%">
        <stop offset="0%" :stop-color="p.haze.color" :stop-opacity="p.haze.inner" />
        <stop offset="60%" :stop-color="p.haze.color" :stop-opacity="p.haze.mid" />
        <stop offset="100%" :stop-color="p.haze.color" stop-opacity="0" />
      </radialGradient>
      <linearGradient id="bbWave" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#A6DD5C" />
        <stop offset="100%" stop-color="#7CB232" />
      </linearGradient>
      <linearGradient id="bbLime" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#C2F55A" />
        <stop offset="100%" stop-color="#63C21C" />
      </linearGradient>
      <linearGradient id="bbLimeDeep" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#8FDD3A" />
        <stop offset="100%" stop-color="#3E8F12" />
      </linearGradient>
      <linearGradient id="bbNavy" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#2C4B9E" />
        <stop offset="100%" stop-color="#152A6B" />
      </linearGradient>
      <linearGradient id="bbRoof" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#3C63C4" />
        <stop offset="100%" stop-color="#17296B" />
      </linearGradient>
      <linearGradient id="bbWall" x1="0" y1="0" x2="0.4" y2="1">
        <stop offset="0%" stop-color="#E3E9F6" />
        <stop offset="100%" stop-color="#B9C6E0" />
      </linearGradient>
      <linearGradient id="bbGrass" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="p.road.asphalt" />
        <stop offset="100%" :stop-color="p.groundShade" />
      </linearGradient>
      <linearGradient id="bbSkin" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFD9B4" />
        <stop offset="100%" stop-color="#F0B584" />
      </linearGradient>
      <linearGradient id="bbCelest" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" :stop-color="p.celestialCore" />
        <stop offset="100%" :stop-color="p.celestialGlow" />
      </linearGradient>
      <linearGradient id="bbSteel" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#F4F8FF" />
        <stop offset="100%" stop-color="#AFC3DE" />
      </linearGradient>
      <radialGradient id="bbCelGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" :stop-color="p.celestialGlow" stop-opacity="0.45" />
        <stop offset="100%" :stop-color="p.celestialGlow" stop-opacity="0" />
      </radialGradient>
      <radialGradient id="bbLampGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#FFE9A6" stop-opacity="0.55" />
        <stop offset="100%" stop-color="#FFE9A6" stop-opacity="0" />
      </radialGradient>
      <radialGradient id="bbBubble" cx="35%" cy="30%" r="70%">
        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.92" />
        <stop offset="60%" stop-color="#DFF3FF" stop-opacity="0.35" />
        <stop offset="100%" stop-color="#A9DDFF" stop-opacity="0.16" />
      </radialGradient>

      <filter id="bbSoft" x="-40%" y="-40%" width="180%" height="180%">
        <feDropShadow dx="0" dy="12" stdDeviation="14" flood-color="#02030F" flood-opacity="0.32" />
      </filter>
      <filter id="bbSoftSm" x="-50%" y="-50%" width="200%" height="200%">
        <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#02030F" flood-opacity="0.3" />
      </filter>

      <clipPath id="bbFrame"><rect x="0" y="0" width="400" height="620" rx="22" ry="22" /></clipPath>
      <clipPath id="bbWinClip"><rect x="252" y="392" width="132" height="112" rx="12" /></clipPath>
    </defs>

    <g clip-path="url(#bbFrame)">
      <rect width="400" height="620" fill="url(#bbSky)" />
      <rect y="150" width="400" height="320" fill="url(#bbHaze)" />

      <!-- Adegan digambar pada koordinat aslinya lalu diskala ke kanvas -->
      <g transform="translate(-48,0) scale(0.62)">
        <circle cx="648" cy="166" r="160" fill="url(#bbCelGlow)" />
        <!-- Bulan sabit di malam/sore, matahari bersinar di pagi/siang -->
        <path
          v-if="p.celestial === 'moon'"
          d="M704,104 a76,76 0 1 0 26,120 a60,60 0 1 1 -26,-120 Z"
          fill="url(#bbCelest)"
        />
        <g v-else>
          <circle cx="700" cy="152" r="60" fill="url(#bbCelest)" />
          <g :stroke="p.celestialCore" stroke-width="7" stroke-linecap="round" opacity="0.75">
            <line x1="700" y1="60" x2="700" y2="34" />
            <line x1="700" y1="244" x2="700" y2="270" />
            <line x1="608" y1="152" x2="582" y2="152" />
            <line x1="792" y1="152" x2="818" y2="152" />
            <line x1="635" y1="87" x2="617" y2="69" />
            <line x1="765" y1="217" x2="783" y2="235" />
            <line x1="765" y1="87" x2="783" y2="69" />
            <line x1="635" y1="217" x2="617" y2="235" />
          </g>
        </g>

        <g fill="#FFFFFF" :opacity="p.starOpacity">
          <circle cx="120" cy="96" r="3" />
          <circle cx="214" cy="152" r="2.4" />
          <circle cx="300" cy="74" r="2.8" />
          <circle cx="386" cy="182" r="2.2" />
          <circle cx="470" cy="112" r="2.6" />
          <circle cx="92" cy="232" r="2.4" />
          <circle cx="524" cy="244" r="2.2" />
          <circle cx="168" cy="300" r="2.6" />
          <circle cx="748" cy="240" r="2.4" />
          <circle cx="84" cy="330" r="2.6" />
        </g>
        <g fill="#FFFFFF" :opacity="0.95 * p.starOpacity">
          <path d="M262,110 l6,16 16,6 -16,6 -6,16 -6,-16 -16,-6 16,-6 z" />
          <path d="M146,168 l6,16 16,6 -16,6 -6,16 -6,-16 -16,-6 16,-6 z" opacity="0.75" />
        </g>
        <g :fill="p.cloud.color" :opacity="p.cloud.opacity">
          <ellipse cx="196" cy="244" rx="94" ry="18" />
          <ellipse cx="576" cy="330" rx="66" ry="14" />
        </g>

        <!-- Barisan pohon di kejauhan -->
        <g :fill="p.bldgNear[1]" opacity="0.9">
          <ellipse cx="80" cy="612" rx="110" ry="70" />
          <ellipse cx="200" cy="628" rx="90" ry="56" />
          <ellipse cx="700" cy="606" rx="120" ry="74" />
          <ellipse cx="600" cy="632" rx="86" ry="52" />
        </g>

        <!-- Trotoar tipis di depan rumah -->
        <path
          d="M0,648 C 150,624 300,662 470,644 C 620,628 700,656 800,640 L800,712 L0,724 Z"
          :fill="p.road.kerb"
        />
        <!-- Badan jalan (aspal) -->
        <path
          d="M0,712 C 150,700 300,724 470,708 C 620,694 700,716 800,706 L800,1000 L0,1000 Z"
          fill="url(#bbGrass)"
        />
        <path
          d="M0,712 C 150,700 300,724 470,708 C 620,694 700,716 800,706"
          fill="none"
          :stroke="p.road.kerb"
          stroke-width="8"
        />
        <!-- Marka jalan hijau -->
        <g stroke="#6FC22A" stroke-width="9" stroke-linecap="round">
          <line x1="40" y1="790" x2="128" y2="786" />
          <line x1="190" y1="784" x2="278" y2="782" />
          <line x1="340" y1="784" x2="428" y2="782" />
          <line x1="490" y1="782" x2="578" y2="780" />
          <line x1="640" y1="780" x2="728" y2="778" />
        </g>
        <g stroke="#6FC22A" stroke-width="11" stroke-linecap="round" opacity="0.75">
          <line x1="-10" y1="884" x2="106" y2="880" />
          <line x1="176" y1="878" x2="292" y2="876" />
          <line x1="362" y1="876" x2="478" y2="874" />
          <line x1="548" y1="874" x2="664" y2="872" />
          <line x1="734" y1="872" x2="850" y2="870" />
        </g>
        <!-- Jalur setapak dari trotoar ke pintu -->
        <path d="M470,660 L560,660 L578,712 L452,712 Z" fill="#8E9DBA" />

        <!-- Rumah -->
        <g filter="url(#bbSoft)">
          <path d="M164,336 L440,182 L716,336 Z" fill="url(#bbRoof)" />
          <rect x="150" y="330" width="580" height="26" rx="13" fill="#1B3480" />
          <rect x="596" y="228" width="46" height="80" rx="10" fill="#1B3480" />
          <rect x="200" y="352" width="480" height="308" rx="22" fill="url(#bbWall)" />
          <g stroke="#FFFFFF" stroke-width="9" stroke-linecap="round" opacity="0.5">
            <line x1="440" y1="378" x2="500" y2="378" />
            <line x1="464" y1="404" x2="504" y2="404" />
          </g>
        </g>

        <!-- Jendela: kaca mengikuti warna palet (hangat malam, sejuk siang) -->
        <g>
          <rect x="252" y="392" width="132" height="112" rx="12" :fill="p.window.color" />
          <g clip-path="url(#bbWinClip)">
            <path d="M244,504 L336,388 L378,388 L286,504 Z" fill="#FFFFFF" opacity="0.25" />
            <circle cx="300" cy="424" r="18" fill="#FFF3C8" opacity="0.9" />
          </g>
          <rect
            x="252"
            y="392"
            width="132"
            height="112"
            rx="12"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="9"
          />
          <line x1="318" y1="392" x2="318" y2="504" stroke="#FFFFFF" stroke-width="8" />
          <line x1="252" y1="448" x2="384" y2="448" stroke="#FFFFFF" stroke-width="8" />
          <path
            d="M338,410 q14,16 -6,30"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="6"
            stroke-linecap="round"
            opacity="0.9"
          />
          <rect x="242" y="506" width="152" height="30" rx="10" fill="#1B3480" />
          <g fill="url(#bbLimeDeep)">
            <circle cx="272" cy="502" r="15" />
            <circle cx="304" cy="498" r="17" />
            <circle cx="338" cy="502" r="15" />
            <circle cx="366" cy="500" r="13" />
          </g>
          <g fill="#FF9AB4">
            <circle cx="288" cy="492" r="6" />
            <circle cx="322" cy="488" r="6" />
            <circle cx="356" cy="492" r="5" />
          </g>
        </g>

        <!-- Pintu -->
        <g>
          <rect x="466" y="452" width="146" height="208" rx="26" fill="#0F2559" />
          <rect x="474" y="460" width="130" height="200" rx="22" fill="url(#bbNavy)" />
          <rect x="492" y="482" width="94" height="66" rx="12" fill="#FFFFFF" opacity="0.14" />
          <rect x="492" y="562" width="94" height="66" rx="12" fill="#FFFFFF" opacity="0.14" />
          <circle cx="588" cy="560" r="9" fill="url(#bbLime)" />
          <rect x="452" y="654" width="174" height="20" rx="10" fill="#C7D2E4" />
          <rect x="474" y="676" width="130" height="16" rx="8" fill="#AEBCD2" />
          <rect x="486" y="682" width="106" height="26" rx="8" fill="url(#bbLimeDeep)" />
          <g stroke="#FFFFFF" stroke-width="3" opacity="0.6">
            <line x1="500" y1="686" x2="500" y2="704" />
            <line x1="520" y1="686" x2="520" y2="704" />
            <line x1="540" y1="686" x2="540" y2="704" />
            <line x1="560" y1="686" x2="560" y2="704" />
            <line x1="578" y1="686" x2="578" y2="704" />
          </g>
        </g>

        <!-- Lampu teras: pendar menyala di malam/sore, redup di siang -->
        <g>
          <circle cx="640" cy="446" r="70" fill="url(#bbLampGlow)" :opacity="p.lampOpacity * 2" />
          <rect x="634" y="404" width="12" height="26" rx="6" fill="#1B3480" />
          <path d="M614,430 h52 l-10,40 h-32 z" fill="#1B3480" />
          <path d="M620,434 h40 l-8,32 h-24 z" fill="#FFE9A6" :opacity="0.4 + p.lampOpacity" />
        </g>

        <!-- Gelombang ketukan pintu -->
        <g fill="none" stroke="#C2F55A" stroke-width="7" stroke-linecap="round">
          <path d="M470,392 a34,34 0 0 1 0,48" opacity="0.95" />
          <path d="M492,376 a56,56 0 0 1 0,80" opacity="0.6" />
          <path d="M514,360 a78,78 0 0 1 0,112" opacity="0.3" />
        </g>

        <!-- Petugas kebersihan -->
        <g filter="url(#bbSoft)" transform="translate(372,470)">
          <g transform="translate(-92,0)">
            <line
              x1="10"
              y1="-42"
              x2="-24"
              y2="176"
              stroke="#C9A96A"
              stroke-width="11"
              stroke-linecap="round"
            />
            <rect
              x="-40"
              y="164"
              width="44"
              height="16"
              rx="7"
              fill="url(#bbNavy)"
              transform="rotate(-10 -18 172)"
            />
            <path d="M-44,178 h48 l-8,28 h-36 z" fill="#E2ECF8" transform="rotate(-10 -20 192)" />
          </g>

          <path d="M-34,118 h30 v66 a15,15 0 0 1 -30,0 z" fill="url(#bbNavy)" />
          <path d="M6,118 h30 v66 a15,15 0 0 1 -30,0 z" fill="url(#bbNavy)" />
          <path d="M-38,180 h32 v12 a8,8 0 0 1 -8,8 h-26 a8,8 0 0 1 2,-20 z" fill="#0F2559" />
          <path d="M4,180 h34 a8,8 0 0 1 0,20 h-26 a8,8 0 0 1 -8,-8 z" fill="#0F2559" />

          <path
            d="M0,10 c38,0 62,24 62,60 v40 a12,12 0 0 1 -12,12 h-100 a12,12 0 0 1 -12,-12 v-40 c0,-36 24,-60 62,-60 z"
            fill="url(#bbLime)"
          />
          <path
            d="M-26,32 h52 v62 a10,10 0 0 1 -10,10 h-32 a10,10 0 0 1 -10,-10 z"
            fill="#FFFFFF"
            opacity="0.88"
          />
          <path d="M-26,32 q26,16 52,0" fill="none" stroke="#63C21C" stroke-width="6" />
          <circle cx="36" cy="46" r="12" fill="#FFFFFF" />
          <path d="M36,38 l3,7 7,3 -7,3 -3,7 -3,-7 -7,-3 7,-3 z" fill="#63C21C" />

          <path
            d="M46,32 C 76,10 92,-2 104,-8"
            fill="none"
            stroke="url(#bbLime)"
            stroke-width="26"
            stroke-linecap="round"
          />
          <circle cx="110" cy="-12" r="19" fill="url(#bbSkin)" />
          <path
            d="M100,-22 q12,-8 22,0"
            fill="none"
            stroke="#E09A64"
            stroke-width="4"
            stroke-linecap="round"
          />

          <path
            d="M-48,36 C -70,60 -74,86 -70,104"
            fill="none"
            stroke="url(#bbLime)"
            stroke-width="24"
            stroke-linecap="round"
          />
          <circle cx="-70" cy="110" r="17" fill="url(#bbSkin)" />

          <circle cx="6" cy="-30" r="40" fill="url(#bbSkin)" />
          <circle cx="-32" cy="-26" r="9" fill="url(#bbSkin)" />
          <path d="M-34,-38 a40,40 0 0 1 80,0 z" fill="url(#bbNavy)" />
          <path d="M46,-38 h22 a9,9 0 0 1 0,18 h-22 z" fill="url(#bbNavy)" />
          <circle cx="6" cy="-72" r="7" fill="url(#bbLime)" />
          <g fill="#1B3268">
            <circle cx="14" cy="-26" r="5" />
            <circle cx="34" cy="-26" r="5" />
          </g>
          <path
            d="M14,-12 q12,11 24,-1"
            fill="none"
            stroke="#1B3268"
            stroke-width="5"
            stroke-linecap="round"
          />
          <circle cx="-4" cy="-14" r="7" fill="#FF9A9A" opacity="0.5" />
          <circle cx="44" cy="-14" r="7" fill="#FF9A9A" opacity="0.5" />
        </g>

        <!-- Ember & botol semprot -->
        <g filter="url(#bbSoftSm)" transform="translate(276,632)">
          <path
            d="M-40,-26 h80 l-11,58 a11,11 0 0 1 -11,9 h-36 a11,11 0 0 1 -11,-9 z"
            fill="url(#bbNavy)"
          />
          <ellipse cx="0" cy="-26" rx="40" ry="12" fill="#E6F4FF" />
          <path d="M-40,-28 a40,28 0 0 1 80,0" fill="none" stroke="url(#bbSteel)" stroke-width="6" />
          <g transform="translate(20,-52)">
            <rect x="-12" y="-16" width="26" height="42" rx="9" fill="url(#bbLime)" />
            <rect x="-6" y="-30" width="14" height="16" rx="5" fill="#3E8F12" />
            <path d="M6,-28 l18,-8 0,10 -18,4 z" fill="#3E8F12" />
          </g>
          <g fill="#FFFFFF" opacity="0.92">
            <circle cx="-14" cy="-44" r="10" />
            <circle cx="-32" cy="-58" r="6" />
          </g>
        </g>

        <!-- Gelembung sabun & kilau -->
        <g>
          <circle
            cx="150"
            cy="470"
            r="34"
            fill="url(#bbBubble)"
            stroke="#FFFFFF"
            stroke-opacity="0.5"
            stroke-width="2"
          />
          <ellipse
            cx="139"
            cy="458"
            rx="10"
            ry="6"
            fill="#FFFFFF"
            opacity="0.75"
            transform="rotate(-30 139 458)"
          />
          <circle
            cx="104"
            cy="546"
            r="22"
            fill="url(#bbBubble)"
            stroke="#FFFFFF"
            stroke-opacity="0.45"
            stroke-width="2"
          />
          <circle
            cx="694"
            cy="512"
            r="28"
            fill="url(#bbBubble)"
            stroke="#FFFFFF"
            stroke-opacity="0.45"
            stroke-width="2"
          />
          <circle
            cx="726"
            cy="590"
            r="17"
            fill="url(#bbBubble)"
            stroke="#FFFFFF"
            stroke-opacity="0.4"
            stroke-width="2"
          />
        </g>
        <g fill="#FFFFFF">
          <path d="M676,392 l8,21 21,8 -21,8 -8,21 -8,-21 -21,-8 21,-8 z" opacity="0.9" />
          <path d="M226,336 l6,17 17,6 -17,6 -6,17 -6,-17 -17,-6 17,-6 z" opacity="0.8" />
          <path d="M430,258 l7,19 19,7 -19,7 -7,19 -7,-19 -19,-7 19,-7 z" opacity="0.75" />
        </g>
      </g>

      <!-- Gelombang bawah: lengkungan & warna disalin persis dari
           BisaBelanjaHeroArt supaya kedua hero terlihat sebaris. -->
      <path
        d="M0,452 C64,420 128,492 208,472 C282,454 332,420 400,444 L400,620 L0,620 Z"
        fill="#8BC53F"
        opacity=".22"
      />
      <path
        d="M0,470 C70,438 130,508 210,490 C280,474 332,436 400,462 L400,620 L0,620 Z"
        fill="url(#bbWave)"
      />

      <g font-family="Arial, Helvetica, sans-serif" text-anchor="middle">
        <text x="200" y="534" font-size="17" font-weight="800" fill="#FFFFFF">
          {{ greeting }}{{ nama ? `, ${nama}` : '' }}
        </text>
        <text x="200" y="554" font-size="11.5" font-weight="700" fill="#FFFFFF" opacity="0.95">{{ subtitle }}</text>
      </g>
    </g>
  </svg>
</template>
