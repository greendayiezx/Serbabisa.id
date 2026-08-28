<script setup lang="ts">
/**
 * Ilustrasi Kartu Promo Kantor (SVG).
 */
</script>

<template>
  <svg xmlns="http://www.w3.org/2000/svg"
       viewBox="0 0 260 180"
       width="260"
       height="180"
       class="w-full h-auto block">

    <defs>
      <!-- Brand gradient -->
      <linearGradient id="officeBg"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#0A326B"/>
        <stop offset="52%" stop-color="#167FE8"/>
        <stop offset="100%" stop-color="#3BBEB8"/>
      </linearGradient>

      <!-- Gold -->
      <linearGradient id="gold"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#FFF1A6"/>
        <stop offset="50%" stop-color="#FFD43B"/>
        <stop offset="100%" stop-color="#D99E16"/>
      </linearGradient>

      <!-- Lime -->
      <linearGradient id="lime"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#E4FF7A"/>
        <stop offset="100%" stop-color="#8BC53F"/>
      </linearGradient>

      <filter id="shadow"
              x="-20%"
              y="-20%"
              width="140%"
              height="150%">
        <feDropShadow
          dx="0"
          dy="5"
          stdDeviation="6"
          flood-color="#062A5A"
          flood-opacity=".20"/>
      </filter>
    </defs>

    <!-- OFFICE PROMO CARD -->
    <rect
      x="2"
      y="2"
      width="256"
      height="176"
      rx="16"
      fill="url(#officeBg)"
      filter="url(#shadow)"/>

    <!-- DECORATIVE GLOW -->
    <circle
      cx="235"
      cy="20"
      r="48"
      fill="#C8FF00"
      opacity=".09">
      <animate
        attributeName="r"
        values="42;52;42"
        dur="3s"
        repeatCount="indefinite"/>
      <animate
        attributeName="opacity"
        values=".05;.14;.05"
        dur="3s"
        repeatCount="indefinite"/>
    </circle>

    <!-- OFFICE ICON -->
    <g transform="translate(16 16)">
      <!-- Icon background -->
      <rect
        width="42"
        height="42"
        rx="12"
        fill="#FFFFFF"
        opacity=".16"/>

      <!-- Building -->
      <rect
        x="11"
        y="10"
        width="20"
        height="25"
        rx="2"
        fill="#FFFFFF"/>

      <!-- Roof -->
      <path
        d="M9 11L21 4L33 11"
        fill="none"
        stroke="#C8FF00"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"/>

      <!-- Windows -->
      <rect x="15" y="14" width="4" height="4" rx="1" fill="#2196F3"/>
      <rect x="23" y="14" width="4" height="4" rx="1" fill="#3BBEB8"/>
      <rect x="15" y="22" width="4" height="4" rx="1" fill="#3BBEB8"/>
      <rect x="23" y="22" width="4" height="4" rx="1" fill="#2196F3"/>

      <!-- Door -->
      <rect
        x="19"
        y="29"
        width="5"
        height="6"
        rx="1"
        fill="#0A326B"/>

      <!-- Sparkle -->
      <path
        d="M35 4 L37 9 L42 11 L37 13 L35 18 L33 13 L28 11 L33 9Z"
        fill="#FFD43B">
        <animate
          attributeName="opacity"
          values=".4;1;.4"
          dur="1.6s"
          repeatCount="indefinite"/>
      </path>
    </g>

    <!-- TITLE -->
    <text
      x="70"
      y="34"
      font-family="Arial, Helvetica, sans-serif"
      font-size="16"
      font-weight="800"
      fill="#FFFFFF">
      Promo Kantor
    </text>

    <!-- SUBTITLE -->
    <text
      x="70"
      y="52"
      font-family="Arial, Helvetica, sans-serif"
      font-size="10.5"
      font-weight="500"
      fill="#EAF7FF">
      Lihat Promo Kantor
    </text>

    <!-- PROMO CODE -->
    <rect
      x="16"
      y="72"
      width="228"
      height="38"
      rx="10"
      fill="#FFFFFF"
      opacity=".14"/>

    <!-- CODE LABEL -->
    <text
      x="28"
      y="88"
      font-family="Arial, Helvetica, sans-serif"
      font-size="9"
      font-weight="600"
      fill="#D9F6FF">
      KODE PROMO
    </text>

    <!-- CODE -->
    <text
      x="28"
      y="102"
      font-family="Arial, Helvetica, sans-serif"
      font-size="13"
      font-weight="800"
      letter-spacing=".8"
      fill="#FFD43B">
      KANTORBARU
    </text>

    <!-- QUICK ACTIONS -->

    <!-- KONTAK -->
    <g transform="translate(16 124)">
      <rect
        width="70"
        height="34"
        rx="10"
        fill="#FFFFFF"
        opacity=".13"/>

      <!-- phone icon -->
      <path
        d="M14 9 C12 9 11 11 12 13 C15 20 20 25 27 28 C29 29 31 28 31 26 L28 22 C27 21 25 21 24 22 L22 23 C19 21 17 19 15 16 L16 14 C17 13 17 11 16 10Z"
        fill="#C8FF00"/>

      <text
        x="35"
        y="21"
        font-family="Arial, Helvetica, sans-serif"
        font-size="8.5"
        font-weight="700"
        fill="#FFFFFF">
        Kontak
      </text>
    </g>

    <!-- REFERRAL -->
    <g transform="translate(94 124)">
      <rect
        width="70"
        height="34"
        rx="10"
        fill="#FFFFFF"
        opacity=".13"/>

      <!-- people -->
      <circle cx="20" cy="12" r="5" fill="#FFD43B"/>
      <circle cx="28" cy="13" r="4" fill="#3BBEB8"/>
      <path
        d="M12 27 C12 21 16 19 20 19 C24 19 27 21 27 27"
        fill="none"
        stroke="#FFD43B"
        stroke-width="3"
        stroke-linecap="round"/>
      <path
        d="M27 21 C31 20 34 22 35 26"
        fill="none"
        stroke="#3BBEB8"
        stroke-width="3"
        stroke-linecap="round"/>

      <text
        x="36"
        y="21"
        font-family="Arial, Helvetica, sans-serif"
        font-size="7.5"
        font-weight="700"
        fill="#FFFFFF">
        Referral
      </text>
    </g>

    <!-- ARROW / CTA -->
    <g transform="translate(175 124)">
      <rect
        width="69"
        height="34"
        rx="10"
        fill="#C8FF00"/>

      <text
        x="6"
        y="21"
        font-family="Arial, Helvetica, sans-serif"
        font-size="6.8"
        font-weight="800"
        fill="#0A326B">
        Lihat Promo
      </text>

      <path
        d="M52 13L57 17L52 21"
        fill="none"
        stroke="#0A326B"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round">
        <animateTransform
          attributeName="transform"
          type="translate"
          values="0 0;2.5 0;0 0"
          dur="1.2s"
          repeatCount="indefinite"/>
      </path>
    </g>

    <!-- BOTTOM LIME ACCENT -->
    <rect
      x="16"
      y="166"
      width="228"
      height="3"
      rx="1.5"
      fill="#C8FF00">
      <animate
        attributeName="width"
        values="100;228;100"
        dur="3s"
        repeatCount="indefinite"/>
    </rect>
  </svg>
</template>
