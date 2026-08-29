<script setup lang="ts">
/**
 * Hero banner Cuci AC — SVG beranimasi.
 *
 * Gambar dan animasinya dipakai apa adanya. Yang disesuaikan hanya hal-hal
 * yang membuatnya benar sebagai komponen, bukan sebagai gambar lepas:
 *
 * 1. Semua id di <defs> dan id tujuan aria-labelledby diberi awalan useId().
 *    Id SVG berlaku sedokumen — dan banner promo di halaman yang sama juga
 *    punya defs sendiri, jadi nama sepolos "gold" atau "lime" akan diambil
 *    dari definisi pertama yang kebetulan dirender lebih dulu.
 * 2. Gradient `shirt` ditambahkan. Baju teknisi merujuk url(#shirt) yang tidak
 *    pernah didefinisikan; paint yang tak terselesaikan membuat elemennya tidak
 *    tergambar sama sekali, jadi bajunya hilang dan badan putihnya tembus.
 * 3. Label podium dan teks badge dijadikan prop, dengan nilai bawaan dari
 *    katalog paket yang benar-benar dijual.
 * 4. Mode `penuh` untuk dipakai tanpa navbar: sudutnya dilempengkan dan
 *    viewBox-nya ditinggikan 80 satuan ke atas. Ruang itu bukan hiasan —
 *    tombol kembali melayang di pojok kiri atas, dan tanpanya ia menimpa
 *    baris pertama judul.
 *
 * Semua animasi mati sendiri saat prefers-reduced-motion: reduce, dan tiap
 * elemen tetap tampil utuh tanpa animasi — tidak ada yang mengandalkan
 * keyframe untuk sekadar terlihat.
 */
import { useId } from 'vue'

withDefaults(
  defineProps<{
    judulBaris1?: string
    judulBaris2?: string
    penjelasBaris1?: string
    penjelasBaris2?: string
    /** Teks badge bulat di kiri; ganti sesuai janji yang benar-benar ditanggung. */
    badgeJudul?: string
    badgeCatatan?: string
    labelKiri?: string
    labelTengah?: string
    labelKanan?: string
    /** Selebar layar tanpa sudut membulat, untuk halaman tanpa navbar. */
    penuh?: boolean
  }>(),
  {
    judulBaris1: 'AC Dingin Lagi,',
    judulBaris2: 'Rumah Nyaman Lagi',
    penjelasBaris1: 'Cuci AC profesional dengan teknisi',
    penjelasBaris2: 'terverifikasi dan berpengalaman.',
    badgeJudul: 'Teknisi Terverifikasi',
    badgeCatatan: 'Dipilih dan diperiksa Serbabisa',
    labelKiri: 'Cuci Standard',
    labelTengah: 'Cuci Premium',
    labelKanan: 'Cek Freon',
    penuh: false,
  },
)

const uid = useId()
</script>

<template>
  <div class="ac-hero" :class="{ 'ac-hero--penuh': penuh }">
    <svg
      class="ac-hero__svg"
      :viewBox="penuh ? '0 -80 750 480' : '0 0 750 400'"
      width="100%"
      role="img"
      :aria-labelledby="`${uid}-judul ${uid}-desk`"
      preserveAspectRatio="xMidYMid meet"
    >
      <title :id="`${uid}-judul`">Cuci AC - {{ judulBaris1 }} {{ judulBaris2 }}</title>

      <desc :id="`${uid}-desk`">
        Ilustrasi layanan cuci AC dengan teknisi terverifikasi, podium layanan, dan efek angin
        sejuk.
      </desc>

      <defs>
        <!-- BACKGROUND GRADIENT -->
        <linearGradient
          :id="`${uid}-ac-bg`"
          x1="55"
          y1="30"
          x2="690"
          y2="370"
          gradientUnits="userSpaceOnUse"
        >
          <stop offset="0" stop-color="#0B3D91" />
          <stop offset="0.48" stop-color="#176EBB" />
          <stop offset="1" stop-color="#7FD8E8" />
        </linearGradient>

        <!-- Background glow -->
        <radialGradient
          :id="`${uid}-ac-glow`"
          cx="0"
          cy="0"
          r="1"
          gradientUnits="userSpaceOnUse"
          gradientTransform="translate(560 100) rotate(90) scale(230)"
        >
          <stop stop-color="#FFFFFF" stop-opacity=".22" />
          <stop offset="1" stop-color="#FFFFFF" stop-opacity="0" />
        </radialGradient>

        <!-- Podium center -->
        <linearGradient :id="`${uid}-podium-main`" x1="0" y1="0" x2="0" y2="1">
          <stop stop-color="#35BFE2" />
          <stop offset="1" stop-color="#0874C9" />
        </linearGradient>

        <!-- Podium side -->
        <linearGradient :id="`${uid}-podium-side`" x1="0" y1="0" x2="0" y2="1">
          <stop stop-color="#55C9E2" />
          <stop offset="1" stop-color="#126CB7" />
        </linearGradient>

        <!-- AC body -->
        <linearGradient :id="`${uid}-ac-body`" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#FFFFFF" />
          <stop offset="1" stop-color="#D8F4FA" />
        </linearGradient>

        <!-- Blue glass -->
        <linearGradient :id="`${uid}-ac-screen`" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#2BBBE2" />
          <stop offset="1" stop-color="#0872C7" />
        </linearGradient>

        <!--
          Baju teknisi. Tidak ada di berkas asal, padahal dirujuk url(#shirt) —
          dan paint yang tidak ketemu membuat elemennya batal digambar.
        -->
        <linearGradient :id="`${uid}-shirt`" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#2E9BE0" />
          <stop offset="1" stop-color="#0B62B0" />
        </linearGradient>

        <!-- Gold -->
        <linearGradient :id="`${uid}-gold`" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#FFE87A" />
          <stop offset="1" stop-color="#F4B82E" />
        </linearGradient>

        <!-- Lime -->
        <linearGradient :id="`${uid}-lime`" x1="0" y1="0" x2="1" y2="1">
          <stop stop-color="#D2F54A" />
          <stop offset="1" stop-color="#A6D91B" />
        </linearGradient>

        <!-- Small shadow -->
        <filter :id="`${uid}-small-shadow`" x="-30%" y="-30%" width="160%" height="180%">
          <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#06376D" flood-opacity=".20" />
        </filter>
      </defs>

      <!-- ================= 01. BACKGROUND ================= -->
      <g class="layer-background">
        <rect
          :x="penuh ? 0 : 4"
          :y="penuh ? -80 : 4"
          :width="penuh ? 750 : 742"
          :height="penuh ? 480 : 392"
          :rx="penuh ? 0 : 34"
          :fill="`url(#${uid}-ac-bg)`"
        />
        <rect
          :x="penuh ? 0 : 4"
          :y="penuh ? -80 : 4"
          :width="penuh ? 750 : 742"
          :height="penuh ? 480 : 392"
          :rx="penuh ? 0 : 34"
          :fill="`url(#${uid}-ac-glow)`"
        />

        <!-- Soft circles -->
        <circle cx="685" cy="52" r="75" fill="#FFFFFF" opacity=".045" />
        <circle cx="665" cy="345" r="110" fill="#FFFFFF" opacity=".035" />
      </g>

      <!-- ================= 02. WIND / WATER DECORATION ================= -->
      <g class="layer-decoration">
        <!-- Wind swirl 1 -->
        <path
          d="M38 282 C92 248 136 270 153 246 C168 226 151 209 129 215"
          fill="none"
          stroke="#FFFFFF"
          stroke-width="2"
          stroke-linecap="round"
          opacity=".16"
        />

        <!-- Wind swirl 2 -->
        <path
          d="M30 304 C85 276 119 294 142 276 C157 264 153 249 139 244"
          fill="none"
          stroke="#BFEFF6"
          stroke-width="1.5"
          stroke-linecap="round"
          opacity=".14"
        />

        <!-- Wind swirl 3 -->
        <path
          d="M575 305 C623 277 669 287 703 263 C721 251 719 235 705 228"
          fill="none"
          stroke="#FFFFFF"
          stroke-width="2"
          stroke-linecap="round"
          opacity=".13"
        />

        <!-- WATER DROPLETS -->
        <g class="water-drop water-drop--1">
          <path
            d="M82 86 C82 86 75 96 75 101 C75 105 78 108 82 108 C86 108 89 105 89 101 C89 96 82 86 82 86Z"
            fill="#CFF7FC"
            opacity=".5"
          />
        </g>

        <g class="water-drop water-drop--2">
          <path
            d="M185 52 C185 52 179 60 179 64 C179 67 182 70 185 70 C188 70 191 67 191 64 C191 60 185 52 185 52Z"
            fill="#FFFFFF"
            opacity=".4"
          />
        </g>

        <g class="water-drop water-drop--3">
          <path
            d="M293 315 C293 315 286 325 286 329 C286 333 289 336 293 336 C297 336 300 333 300 329 C300 325 293 315 293 315Z"
            fill="#D9F9FC"
            opacity=".45"
          />
        </g>

        <g class="water-drop water-drop--4">
          <path
            d="M677 155 C677 155 671 164 671 168 C671 171 674 174 677 174 C681 174 684 171 684 168 C684 164 677 155 677 155Z"
            fill="#FFFFFF"
            opacity=".45"
          />
        </g>

        <g class="water-drop water-drop--5">
          <path
            d="M715 211 C715 211 711 218 711 221 C711 224 713 226 715 226 C718 226 720 224 720 221 C720 218 715 211 715 211Z"
            fill="#D8F9FC"
            opacity=".4"
          />
        </g>
      </g>

      <!-- ================= 03. HEADLINE AREA ================= -->
      <g class="layer-text">
        <!-- Headline -->
        <!--
          Ukuran naik 31 -> 38, dan jarak antar-baris ikut dilebarkan dari 36
          jadi 50. Menaikkan ukuran huruf tanpa menggeser garis dasarnya
          membuat kedua baris saling menyentuh.
        -->
        <text
          x="32"
          y="100"
          fill="#FFFFFF"
          font-family="Inter, Arial, sans-serif"
          font-size="38"
          font-weight="900"
          letter-spacing="-1.1"
        >
          {{ judulBaris1 }}
        </text>

        <text
          x="32"
          y="150"
          fill="#FFFFFF"
          font-family="Inter, Arial, sans-serif"
          font-size="38"
          font-weight="900"
          letter-spacing="-1.1"
        >
          {{ judulBaris2 }}
        </text>

        <!-- Supporting text -->
        <text
          x="32"
          y="182"
          fill="#DDF8FC"
          font-family="Inter, Arial, sans-serif"
          font-size="15"
          font-weight="500"
        >
          {{ penjelasBaris1 }}
        </text>

        <text
          x="32"
          y="204"
          fill="#DDF8FC"
          font-family="Inter, Arial, sans-serif"
          font-size="15"
          font-weight="500"
        >
          {{ penjelasBaris2 }}
        </text>

        <!-- GUARANTEE BADGE -->
        <g class="guarantee-badge">
          <circle
            cx="65"
            cy="241"
            r="32"
            :fill="`url(#${uid}-lime)`"
            :filter="`url(#${uid}-small-shadow)`"
          />

          <circle cx="65" cy="241" r="25" fill="#FFFFFF" opacity=".15" />

          <!-- Shield -->
          <path
            d="M65 223 L79 228 V239 C79 249 72 255 65 258 C58 255 51 249 51 239 V228 Z"
            fill="#0B4C9D"
          />

          <!-- Check -->
          <path
            d="M57 240 L63 246 L74 234"
            fill="none"
            stroke="#D8F64A"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
          />

          <text
            x="108"
            y="236"
            fill="#FFFFFF"
            font-family="Inter, Arial, sans-serif"
            font-size="16"
            font-weight="800"
          >
            {{ badgeJudul }}
          </text>

          <text
            x="108"
            y="256"
            fill="#DDF8FC"
            font-family="Inter, Arial, sans-serif"
            font-size="12.5"
            font-weight="500"
          >
            {{ badgeCatatan }}
          </text>
        </g>
      </g>

      <!-- ================= 04. PODIUM GROUP ================= -->
      <g class="layer-podium">
        <!-- Ground shadow -->
        <ellipse cx="531" cy="355" rx="185" ry="17" fill="#063E78" opacity=".22" />

        <!-- LEFT PODIUM -->
        <g class="podium podium-left">
          <path d="M345 260 H423 L434 271 H356 Z" fill="#66D1E6" />
          <path d="M356 271 H434 V348 H356 Z" :fill="`url(#${uid}-podium-side)`" />
          <path d="M365 280H425V338H365Z" fill="#FFFFFF" opacity=".045" />

          <circle cx="395" cy="286" r="13" fill="#B5E61D" />
          <text
            x="395"
            y="291"
            text-anchor="middle"
            fill="#174B76"
            font-family="Inter, Arial, sans-serif"
            font-size="11"
            font-weight="900"
          >
            2
          </text>

          <text
            x="395"
            y="370"
            text-anchor="middle"
            fill="#FFFFFF"
            font-family="Inter, Arial, sans-serif"
            font-size="13"
            font-weight="700"
          >
            {{ labelKiri }}
          </text>
        </g>

        <!-- CENTER PODIUM -->
        <g class="podium podium-center">
          <path d="M435 205 H539 L551 217 H447 Z" fill="#70D8E9" />
          <path d="M447 217 H551 V348 H447 Z" :fill="`url(#${uid}-podium-main)`" />
          <path d="M458 227H540V337H458Z" fill="#FFFFFF" opacity=".055" />

          <circle cx="499" cy="234" r="15" :fill="`url(#${uid}-gold)`" />
          <text
            x="499"
            y="239"
            text-anchor="middle"
            fill="#6D4C00"
            font-family="Inter, Arial, sans-serif"
            font-size="12"
            font-weight="900"
          >
            1
          </text>

          <text
            x="499"
            y="370"
            text-anchor="middle"
            fill="#FFFFFF"
            font-family="Inter, Arial, sans-serif"
            font-size="13"
            font-weight="800"
          >
            {{ labelTengah }}
          </text>
        </g>

        <!-- RIGHT PODIUM -->
        <g class="podium podium-right">
          <path d="M565 260 H643 L654 271 H576 Z" fill="#66D1E6" />
          <path d="M576 271 H654 V348 H576 Z" :fill="`url(#${uid}-podium-side)`" />
          <path d="M585 280H645V338H585Z" fill="#FFFFFF" opacity=".045" />

          <circle cx="615" cy="286" r="13" fill="#B5E61D" />
          <text
            x="615"
            y="291"
            text-anchor="middle"
            fill="#174B76"
            font-family="Inter, Arial, sans-serif"
            font-size="11"
            font-weight="900"
          >
            3
          </text>

          <text
            x="615"
            y="370"
            text-anchor="middle"
            fill="#FFFFFF"
            font-family="Inter, Arial, sans-serif"
            font-size="13"
            font-weight="700"
          >
            {{ labelKanan }}
          </text>
        </g>
      </g>

      <!-- ================= 05. LEFT TOOL ICON ================= -->
      <g class="icon-tool icon-tool--left">
        <!-- Spray bottle -->
        <g :filter="`url(#${uid}-small-shadow)`">
          <path d="M367 213 H392 L398 221 V257 H361 V221 Z" fill="#FFFFFF" />
          <rect x="365" y="222" width="30" height="31" rx="5" fill="#DFF7FA" />

          <!-- nozzle -->
          <path d="M371 214 V207 H389 L400 214 H389" fill="#B5E61D" />
          <path
            d="M379 202 C384 199 390 202 391 207"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="3"
            stroke-linecap="round"
          />

          <!-- cleaning sparkle -->
          <path
            d="M376 233 L380 241 L388 245 L380 249 L376 257 L372 249 L364 245 L372 241 Z"
            fill="#0B83D5"
          />
        </g>

        <!-- Small bubbles -->
        <circle cx="404" cy="215" r="4" fill="#B5E61D" opacity=".9" />
        <circle cx="414" cy="205" r="2.5" fill="#FFFFFF" opacity=".7" />
      </g>

      <!-- ================= 06. CENTER AC INDOOR ICON ================= -->
      <g class="icon-ac">
        <!-- AC shadow -->
        <ellipse cx="499" cy="202" rx="67" ry="10" fill="#053E7B" opacity=".18" />

        <!-- AC unit -->
        <g :filter="`url(#${uid}-small-shadow)`">
          <rect x="445" y="120" width="108" height="69" rx="18" :fill="`url(#${uid}-ac-body)`" />

          <!-- Top highlight -->
          <path
            d="M462 130H536"
            stroke="#FFFFFF"
            stroke-width="4"
            stroke-linecap="round"
            opacity=".9"
          />

          <!-- Display -->
          <rect x="468" y="140" width="29" height="14" rx="7" :fill="`url(#${uid}-ac-screen)`" />
          <circle cx="477" cy="147" r="2.5" fill="#B5E61D" />
          <circle cx="486" cy="147" r="2.5" fill="#FFFFFF" opacity=".8" />

          <!-- AC vent -->
          <path
            d="M465 171 C485 165 516 165 535 171"
            fill="none"
            stroke="#76CFE5"
            stroke-width="4"
            stroke-linecap="round"
          />

          <path
            d="M470 177 C489 172 511 172 530 177"
            fill="none"
            stroke="#9BE0ED"
            stroke-width="3"
            stroke-linecap="round"
          />
        </g>

        <!-- COOL AIR / WIND -->
        <g class="cool-air">
          <path
            d="M457 194 C474 188 491 194 505 190"
            fill="none"
            stroke="#DDFBFF"
            stroke-width="3"
            stroke-linecap="round"
          />

          <path
            d="M466 205 C482 199 497 205 514 200"
            fill="none"
            stroke="#BDF3FA"
            stroke-width="2.5"
            stroke-linecap="round"
          />

          <path
            d="M480 215 C493 210 506 214 520 211"
            fill="none"
            stroke="#E9FDFF"
            stroke-width="2"
            stroke-linecap="round"
          />
        </g>

        <!-- Cooling droplets -->
        <g class="cool-drop cool-drop--1">
          <path
            d="M530 201C530 201 525 208 525 211C525 214 527 216 530 216C533 216 535 214 535 211C535 208 530 201 530 201Z"
            fill="#B5E61D"
          />
        </g>

        <g class="cool-drop cool-drop--2">
          <path
            d="M546 209C546 209 542 215 542 218C542 220 544 222 546 222C548 222 550 220 550 218C550 215 546 209 546 209Z"
            fill="#FFFFFF"
            opacity=".8"
          />
        </g>
      </g>

      <!-- ================= 07. RIGHT TECHNICIAN ================= -->
      <g class="icon-tech">
        <g :filter="`url(#${uid}-small-shadow)`">
          <!-- body -->
          <path
            d="M588 224 C589 211 599 204 614 204 C629 204 639 212 640 224 L643 260 H585 Z"
            fill="#FFFFFF"
          />

          <!-- shirt -->
          <path
            d="M588 224 C590 214 599 208 614 208 C628 208 637 214 640 224 L642 258 H587 Z"
            :fill="`url(#${uid}-shirt)`"
          />

          <!-- neck -->
          <path d="M606 202 V211 C609 215 618 215 621 211 V202 Z" fill="#F2B48B" />

          <!-- face -->
          <circle cx="614" cy="192" r="17" fill="#F4BB91" />

          <!-- hair -->
          <path
            d="M598 191 C596 180 603 172 614 172 C626 172 633 181 631 192 C626 185 621 183 614 183 C607 183 602 186 598 191Z"
            fill="#173C68"
          />

          <!-- cap -->
          <path
            d="M600 178 C604 170 612 167 620 169 C627 171 631 176 632 181 H600Z"
            fill="#086FD3"
          />

          <path
            d="M598 180 H636 C635 184 631 186 626 186 H604 C600 186 598 184 598 180Z"
            fill="#075CB9"
          />

          <!-- cap lime mark -->
          <circle cx="617" cy="178" r="3.5" fill="#B5E61D" />

          <!-- shirt detail -->
          <path d="M612 216V249" stroke="#D8F7FB" stroke-width="3" opacity=".8" />
        </g>

        <!-- Tool -->
        <g class="tool-wrench">
          <path d="M637 224 L651 210 L655 214 L641 229 Z" fill="#B5E61D" />
          <circle cx="653" cy="211" r="6" fill="#EAFBFF" />
          <circle cx="653" cy="211" r="2.5" fill="#0874C9" />
        </g>
      </g>

      <!-- ================= 08. PREMIUM SPARKLES ================= -->
      <g class="premium-sparkle premium-sparkle--1">
        <path
          d="M438 105 L442 115 L452 119 L442 123 L438 133 L434 123 L424 119 L434 115 Z"
          :fill="`url(#${uid}-gold)`"
        />
      </g>

      <g class="premium-sparkle premium-sparkle--2">
        <path
          d="M664 105 L667 112 L674 115 L667 118 L664 125 L661 118 L654 115 L661 112 Z"
          fill="#B5E61D"
        />
      </g>
    </svg>
  </div>
</template>

<style scoped>
/* ========================= BASE ========================= */

.ac-hero {
  width: 100%;
  max-width: 750px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 34px;
}

/* Tanpa batas lebar dan tanpa sudut membulat: gambarnya menyentuh tepi layar. */
.ac-hero--penuh {
  max-width: none;
  border-radius: 0;
}

.ac-hero__svg {
  display: block;
  width: 100%;
  height: auto;
}

/* =========================================================
   01. CENTER PODIUM ENTRANCE
   Target: podium utama di tengah, scale 0.9 -> 1
========================================================= */

.podium-center {
  transform-box: fill-box;
  transform-origin: center bottom;

  animation: podiumEntrance 0.4s cubic-bezier(0.2, 0.85, 0.3, 1) both;
}

@keyframes podiumEntrance {
  from {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
  }

  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

/* =========================================================
   02. COOL AIR
   Target: tiga garis angin di bawah AC indoor
========================================================= */

.cool-air path {
  transform-box: fill-box;
  transform-origin: center;

  animation: coolWind 2s ease-in-out infinite;
}

.cool-air path:nth-child(2) {
  animation-delay: 0.22s;
}

.cool-air path:nth-child(3) {
  animation-delay: 0.42s;
}

@keyframes coolWind {
  0% {
    opacity: 0.2;
    transform: translateX(0) scaleX(0.85);
  }

  45% {
    opacity: 1;
    transform: translateX(7px) scaleX(1);
  }

  100% {
    opacity: 0.15;
    transform: translateX(15px) scaleX(0.9);
  }
}

/* =========================================================
   03. BACKGROUND WATER DROPLETS
   Target: droplet yang tersebar di background
========================================================= */

.water-drop {
  transform-box: fill-box;
  transform-origin: center;

  animation: waterFloat ease-in-out infinite;
}

.water-drop--1 {
  animation-duration: 4.2s;
}

.water-drop--2 {
  animation-duration: 3.4s;
  animation-delay: -0.8s;
}

.water-drop--3 {
  animation-duration: 4.8s;
  animation-delay: -1.4s;
}

.water-drop--4 {
  animation-duration: 3.8s;
  animation-delay: -0.5s;
}

.water-drop--5 {
  animation-duration: 5s;
  animation-delay: -2s;
}

@keyframes waterFloat {
  0%,
  100% {
    transform: translateY(0) rotate(0deg);
  }

  50% {
    transform: translateY(-10px) rotate(3deg);
  }
}

/* =========================================================
   04. GUARANTEE BADGE
   Target: badge bulat di kolom teks
========================================================= */

.guarantee-badge {
  transform-box: fill-box;
  transform-origin: center;

  animation: guaranteeGlow 1.5s ease-in-out infinite;
}

@keyframes guaranteeGlow {
  0%,
  100% {
    opacity: 0.92;
    transform: scale(1);
    filter: drop-shadow(0 0 0 rgba(181, 230, 29, 0));
  }

  50% {
    opacity: 1;
    transform: scale(1.025);
    filter: drop-shadow(0 0 8px rgba(181, 230, 29, 0.35));
  }
}

/* ============ 05. CLEANING TOOL FLOAT ============ */

.icon-tool--left {
  transform-box: fill-box;
  transform-origin: center;

  animation: toolFloat 3.5s ease-in-out infinite;
}

@keyframes toolFloat {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-5px);
  }
}

/* ============ 06. TECHNICIAN FLOAT ============ */

.icon-tech {
  transform-box: fill-box;
  transform-origin: center;

  animation: technicianFloat 3.8s ease-in-out infinite;
}

@keyframes technicianFloat {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-4px);
  }
}

/* ============ 07. COOLING DROPLETS ============ */

.cool-drop {
  transform-box: fill-box;
  transform-origin: center;

  animation: coolingDrop 2.4s ease-in-out infinite;
}

.cool-drop--2 {
  animation-delay: 0.6s;
}

@keyframes coolingDrop {
  0%,
  100% {
    opacity: 0.15;
    transform: translateY(0) scale(0.8);
  }

  50% {
    opacity: 1;
    transform: translateY(6px) scale(1);
  }
}

/* ============ 08. PREMIUM SPARKLE ============ */

.premium-sparkle {
  transform-box: fill-box;
  transform-origin: center;

  animation: premiumTwinkle 1.7s ease-in-out infinite;
}

.premium-sparkle--2 {
  animation-delay: 0.7s;
}

@keyframes premiumTwinkle {
  0%,
  100% {
    opacity: 0.25;
    transform: scale(0.7) rotate(0);
  }

  50% {
    opacity: 1;
    transform: scale(1.12) rotate(10deg);
  }
}

/* =========================================================
   09. REDUCED MOTION
   Mematikan seluruh animasi untuk pengguna yang memilih
   prefers-reduced-motion: reduce. Tiap elemen sudah tampil
   utuh tanpa animasi, jadi tidak ada yang ikut hilang.
========================================================= */

@media (prefers-reduced-motion: reduce) {
  .ac-hero *,
  .ac-hero *::before,
  .ac-hero *::after {
    animation: none !important;
    transition: none !important;
  }
}
</style>
