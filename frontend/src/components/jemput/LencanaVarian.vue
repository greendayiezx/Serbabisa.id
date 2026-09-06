<script setup lang="ts">
/**
 * Lencana varian BisaJemput — CEPAT, HEMAT, COMFORT, PREMIUM, dan sisanya.
 *
 * SVG-nya dipakai di dua tempat: saat memilih kendaraan (JemputPesanView) dan
 * saat perjalanan berlangsung (PelacakanPerjalanan). Disatukan di sini supaya
 * bentuk lencananya tidak menyimpang antara halaman pemesanan dan pelacakan —
 * dua salinan gambar yang sama selalu bergeser, cepat atau lambat.
 *
 * Tiap gradasi, filter, dan clip diberi id yang unik per contoh lewat useId().
 * Dua lencana yang berbagi id gradasi membuat yang kedua meminjam definisi
 * yang pertama, dan di halaman panjang keduanya bisa saling menimpa warna.
 */
import { useId } from 'vue'

defineProps<{
  /** Teks lencana apa adanya dari server: 'CEPAT', 'HEMAT', 'COMFORT', … */
  label: string | null | undefined
}>()

const uid = useId()
</script>

<template>
  <svg
    v-if="label === 'CEPAT'"
    xmlns="http://www.w3.org/2000/svg"
    width="74"
    height="22"
    viewBox="0 0 74 22"
    role="img"
    aria-label="CEPAT"
    class="shrink-0"
  >
    <defs>
      <!-- Background Yellow Gradient -->
      <linearGradient :id="'bg-cepat-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFD43B" />
        <stop offset="100%" stop-color="#F5B301" />
      </linearGradient>

      <!-- Glow -->
      <filter :id="'glow-cepat-' + uid" x="-100%" y="-100%" width="300%" height="300%">
        <feGaussianBlur stdDeviation="0.8" result="blur" />
        <feMerge>
          <feMergeNode in="blur" />
          <feMergeNode in="SourceGraphic" />
        </feMerge>
      </filter>

      <!-- Kilatan -->
      <linearGradient :id="'shine-cepat-' + uid">
        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="45%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="50%" stop-color="#FFFFFF" stop-opacity="1" />
        <stop offset="55%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
      </linearGradient>

      <clipPath :id="'rounded-cepat-' + uid">
        <rect width="74" height="22" rx="6" />
      </clipPath>
    </defs>

    <!-- Badge Background -->
    <rect width="74" height="22" rx="6" :fill="'url(#bg-cepat-' + uid + ')'" />

    <g :clip-path="'url(#rounded-cepat-' + uid + ')'">
      <!-- Lightning -->
      <g :filter="'url(#glow-cepat-' + uid + ')'">
        <path d="M12 3 L7.5 10 H11 L9 19 L16.5 9 H13 Z" fill="#B7F34A">
          <!-- Kilatan petir -->
          <animate
            attributeName="opacity"
            values="1; .35; 1; .7; 1"
            dur="0.7s"
            repeatCount="indefinite"
          />
          <!-- Gerakan kecil -->
          <animateTransform
            attributeName="transform"
            type="translate"
            values="0 0; 1 -0.4; 0 0"
            dur="0.7s"
            repeatCount="indefinite"
          />
        </path>
      </g>

      <!-- Text -->
      <text
        x="24"
        y="14.5"
        font-family="Arial, Helvetica, sans-serif"
        font-size="10px"
        font-weight="900"
        letter-spacing="0.5px"
        fill="#FFFFFF"
      >
        CEPAT
      </text>

      <!-- Kilatan cahaya menyapu badge -->
      <rect x="-25" y="0" width="8" height="22" :fill="'url(#shine-cepat-' + uid + ')'" transform="skewX(-18)">
        <animate attributeName="x" from="-25" to="90" dur="1.4s" repeatCount="indefinite" />
      </rect>
    </g>
  </svg>

  <svg
    v-else-if="label === 'HEMAT'"
    xmlns="http://www.w3.org/2000/svg"
    width="74"
    height="22"
    viewBox="0 0 74 22"
    role="img"
    aria-label="HEMAT"
    class="shrink-0"
  >
    <defs>
      <!-- Background -->
      <linearGradient :id="'hematBg-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#1593ED" />
        <stop offset="55%" stop-color="#087EDC" />
        <stop offset="100%" stop-color="#066CC5" />
      </linearGradient>

      <!-- Lime gradient -->
      <linearGradient :id="'lime-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#D2FF62" />
        <stop offset="100%" stop-color="#91E52F" />
      </linearGradient>

      <!-- Soft glow -->
      <filter :id="'hematGlow-' + uid" x="-100%" y="-100%" width="300%" height="300%">
        <feGaussianBlur stdDeviation="0.7" result="blur" />
        <feMerge>
          <feMergeNode in="blur" />
          <feMergeNode in="SourceGraphic" />
        </feMerge>
      </filter>

      <!-- Shine -->
      <linearGradient :id="'shine-hemat-' + uid">
        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="42%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".95" />
        <stop offset="58%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
      </linearGradient>

      <clipPath :id="'hematClip-' + uid">
        <rect x="0" y="0" width="74" height="22" rx="6" />
      </clipPath>
    </defs>

    <!-- BACKGROUND -->
    <rect width="74" height="22" rx="6" :fill="'url(#hematBg-' + uid + ')'" />

    <g :clip-path="'url(#hematClip-' + uid + ')'">
      <!-- WALLET + COIN ICON -->
      <g transform="translate(4 4)" :filter="'url(#hematGlow-' + uid + ')'">
        <!-- Wallet body -->
        <path
          d="M2.2 5 V11.2 Q2.2 13.5 4.5 13.5 H10.8 Q13 13.5 13 11.2 V6.8 Q13 5 11 5 Z"
          :fill="'url(#lime-' + uid + ')'"
        />
        <!-- Wallet flap -->
        <path d="M2.2 6.2 V4.5 Q2.2 2.8 4 2.8 H9.6 Q11 2.8 11.8 4.2 L13 6.2 Z" fill="#B8F34A" />
        <!-- Wallet pocket -->
        <path d="M9 7 H13 V10.8 H9.5 Q8 10.8 8 8.9 Q8 7 9.5 7 Z" fill="#78D51F" />
        <!-- Coin -->
        <circle cx="5.7" cy="5.2" r="2.2" fill="#FFFFFF" />
        <path
          d="M5.7 3.9 V6.5 M4.8 4.7 Q5.7 4 6.5 4.7 Q5.5 5.3 4.9 5.7 Q5.7 6.4 6.6 5.6"
          fill="none"
          stroke="#1593ED"
          stroke-width=".55"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <!-- Wallet button -->
        <circle cx="10.2" cy="8.9" r=".75" fill="#FFFFFF" />
        <!-- Icon pulse -->
        <animate attributeName="opacity" values="1;.7;1" dur=".9s" repeatCount="indefinite" />
      </g>

      <!-- TEXT -->
      <text
        x="23"
        y="14.5"
        font-family="Inter, Arial, Helvetica, sans-serif"
        font-size="10px"
        font-weight="900"
        letter-spacing="0.3px"
        fill="#FFFFFF"
      >
        HEMAT
      </text>

      <!-- SPARK -->
      <g transform="translate(64 5)" fill="#D2FF62">
        <path d="M2 0 L2.5 1.5 L4 2 L2.5 2.5 L2 4 L1.5 2.5 L0 2 L1.5 1.5 Z">
          <animate attributeName="opacity" values="1;.2;1" dur=".65s" repeatCount="indefinite" />
          <animateTransform
            attributeName="transform"
            type="scale"
            values="1;1.35;1"
            dur=".65s"
            repeatCount="indefinite"
            additive="sum"
          />
        </path>
      </g>

      <!-- LIGHT SWEEP -->
      <rect x="-25" y="-5" width="8" height="30" :fill="'url(#shine-hemat-' + uid + ')'" transform="skewX(-18)">
        <animate attributeName="x" from="-25" to="90" dur="1.45s" repeatCount="indefinite" />
      </rect>
    </g>
  </svg>

  <svg
    v-else-if="label === 'COMFORT'"
    xmlns="http://www.w3.org/2000/svg"
    width="88"
    height="24"
    viewBox="0 0 88 24"
    role="img"
    aria-label="COMFORT"
    class="shrink-0"
  >
    <defs>
      <!-- Background Maroon Gradient -->
      <linearGradient :id="'comfortBg-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#9B1B30" />
        <stop offset="100%" stop-color="#650A19" />
      </linearGradient>

      <!-- Shine -->
      <linearGradient :id="'shine-comfort-' + uid" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#fff" stop-opacity="0" />
        <stop offset="50%" stop-color="#fff" stop-opacity=".95" />
        <stop offset="100%" stop-color="#fff" stop-opacity="0" />
      </linearGradient>

      <clipPath :id="'clip-comfort-' + uid">
        <rect width="88" height="24" rx="6" />
      </clipPath>
    </defs>

    <!-- BADGE BACKGROUND -->
    <rect width="88" height="24" rx="6" :fill="'url(#comfortBg-' + uid + ')'" />

    <g :clip-path="'url(#clip-comfort-' + uid + ')'">
      <!-- COMFORT ICON -->
      <!-- Sofa -->
      <path d="M5 14.5 Q5 12 7.5 12 H14.5 Q17 12 17 14.5 V18 H5Z" fill="#7ED321" />
      <!-- Sofa back -->
      <path d="M7 12 V9.5 Q7 8 8.8 8 H13.2 Q15 8 15 9.5 V12" fill="#B7F34A" />
      <!-- Person head -->
      <circle cx="11" cy="6" r="2.2" fill="#FFD0A6" />
      <!-- Hair -->
      <path d="M8.8 5.8 Q8.8 3.5 11 3.5 Q13.2 3.5 13.2 5.4 Q12.2 4.7 11 4.8 Q9.7 4.9 8.8 5.8Z" fill="#650A19" />
      <!-- Body relaxed -->
      <path d="M9 8 Q11 7 13 8 L14.5 12 H11.5 L10.5 10.5 L8.5 12 H6.8 L8.2 9Z" fill="#FFFFFF" />
      <!-- Relaxed arm -->
      <path d="M12.5 8.5 Q14.5 9.5 15 11" stroke="#FFD0A6" stroke-width="1.3" stroke-linecap="round" />

      <!-- COMFORT TEXT -->
      <text
        x="21"
        y="16"
        fill="#FFFFFF"
        font-family="Arial, sans-serif"
        font-size="9.5px"
        font-weight="900"
        letter-spacing=".2px"
      >
        COMFORT
      </text>

      <!-- SHINE ANIMATION -->
      <rect x="-25" y="0" width="9" height="24" :fill="'url(#shine-comfort-' + uid + ')'" transform="skewX(-18)">
        <animate attributeName="x" from="-25" to="105" dur="1.6s" repeatCount="indefinite" />
      </rect>
    </g>
  </svg>

  <svg
    v-else-if="label === 'PREMIUM'"
    xmlns="http://www.w3.org/2000/svg"
    width="82"
    height="24"
    viewBox="0 0 82 24"
    role="img"
    aria-label="PREMIUM"
    class="shrink-0"
  >
    <defs>
      <!-- Premium Black Background Gradient -->
      <linearGradient :id="'premiumBg-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#2E2E2E" />
        <stop offset="100%" stop-color="#0F0F0F" />
      </linearGradient>

      <!-- Gold Crown Gradient -->
      <linearGradient :id="'crown-' + uid" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFE066" />
        <stop offset="100%" stop-color="#F5B301" />
      </linearGradient>

      <!-- Shine -->
      <linearGradient :id="'shine-premium-' + uid" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0" />
        <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".95" />
        <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
      </linearGradient>

      <clipPath :id="'premiumClip-' + uid">
        <rect width="82" height="24" rx="6" />
      </clipPath>
    </defs>

    <!-- BADGE BACKGROUND -->
    <rect width="82" height="24" rx="6" :fill="'url(#premiumBg-' + uid + ')'" />

    <g :clip-path="'url(#premiumClip-' + uid + ')'">
      <!-- PREMIUM CROWN ICON -->
      <!-- Crown -->
      <path
        d="M5 7 L8 10 L11 5 L14 10 L17 7 L15.5 14 H6.5 Z"
        :fill="'url(#crown-' + uid + ')'"
        stroke="#FFE885"
        stroke-width=".7"
        stroke-linejoin="round"
      />
      <!-- Crown base -->
      <rect x="6.5" y="14" width="9" height="2" rx="1" fill="#FFD700" />
      <!-- Crown jewel -->
      <circle cx="11" cy="10.5" r="1" fill="#FFFFFF">
        <animate attributeName="opacity" values="1;.35;1" dur="1.2s" repeatCount="indefinite" />
      </circle>

      <!-- PREMIUM TEXT -->
      <text
        x="21"
        y="16"
        fill="#FFFFFF"
        font-family="Arial, sans-serif"
        font-size="9.5px"
        font-weight="900"
        letter-spacing=".15px"
      >
        PREMIUM
      </text>

      <!-- SHIMMER -->
      <rect x="-25" y="0" width="9" height="24" :fill="'url(#shine-premium-' + uid + ')'" transform="skewX(-18)">
        <animate attributeName="x" from="-25" to="100" dur="1.6s" repeatCount="indefinite" />
      </rect>
    </g>
  </svg>

  <!-- Sisanya (mis. VAN) — pita polos memakai warna aplikasi. -->
  <span
    v-else-if="label"
    class="px-2 py-0.5 rounded-md bg-(--color-azure) text-white text-[9.5px] font-extrabold tracking-wide"
  >
    {{ label }}
  </span>
</template>
