<script setup lang="ts">
/**
 * Ilustrasi Kartu Promo Langganan Kantor (SVG).
 * Mengusung dimensi (260x180) yang serasi dengan PromoKantorArt.
 */
</script>

<template>
  <svg xmlns="http://www.w3.org/2000/svg"
       viewBox="0 0 260 180"
       width="260"
       height="180"
       class="w-full h-auto block">

    <defs>
      <!-- Brand gradient untuk langganan -->
      <linearGradient id="langgananBg"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#0A326B"/>
        <stop offset="48%" stop-color="#167FE8"/>
        <stop offset="100%" stop-color="#4A9E12"/>
      </linearGradient>

      <!-- Lime -->
      <linearGradient id="limeSub"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#E5FF7A"/>
        <stop offset="100%" stop-color="#8BC53F"/>
      </linearGradient>

      <!-- Gold -->
      <linearGradient id="goldSub"
                      x1="0" y1="0"
                      x2="1" y2="1">
        <stop offset="0%" stop-color="#FFF3A6"/>
        <stop offset="50%" stop-color="#FFD43B"/>
        <stop offset="100%" stop-color="#D99E16"/>
      </linearGradient>

      <filter id="shadowSub"
              x="-20%"
              y="-20%"
              width="140%"
              height="150%">
        <feDropShadow
          dx="0"
          dy="5"
          stdDeviation="6"
          flood-color="#062A5A"
          flood-opacity=".22"/>
      </filter>
    </defs>

    <!-- CARD BACKGROUND -->
    <rect
      x="2"
      y="2"
      width="256"
      height="176"
      rx="16"
      fill="url(#langgananBg)"
      filter="url(#shadowSub)"/>

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

    <!-- SUBSCRIPTION ICON -->
    <g transform="translate(16 16)">
      <!-- Icon container -->
      <rect
        width="42"
        height="42"
        rx="12"
        fill="#FFFFFF"
        opacity=".16"/>

      <!-- Calendar -->
      <rect
        x="9"
        y="11"
        width="24"
        height="22"
        rx="4"
        fill="#FFFFFF"/>

      <!-- Calendar header line -->
      <path
        d="M9 17H33"
        stroke="#3BBEB8"
        stroke-width="3"/>

      <!-- Calendar rings -->
      <path
        d="M15 8V14M27 8V14"
        stroke="#C8FF00"
        stroke-width="3"
        stroke-linecap="round"/>

      <!-- Calendar dates -->
      <circle cx="15" cy="22" r="1.8" fill="#2196F3"/>
      <circle cx="21" cy="22" r="1.8" fill="#3BBEB8"/>
      <circle cx="27" cy="22" r="1.8" fill="#2196F3"/>
      <circle cx="15" cy="28" r="1.8" fill="#3BBEB8"/>
      <circle cx="21" cy="28" r="1.8" fill="#C8FF00"/>
      <circle cx="27" cy="28" r="1.8" fill="#3BBEB8"/>

      <!-- Repeat Arrow -->
      <path
        d="M32 31 C37 30 38 23 34 20"
        fill="none"
        stroke="#FFD43B"
        stroke-width="2.5"
        stroke-linecap="round"/>
      <path
        d="M34 18L35 22L31 21"
        fill="none"
        stroke="#FFD43B"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round">
        <animateTransform
          attributeName="transform"
          type="rotate"
          values="0 34 20;10 34 20;0 34 20"
          dur="1.5s"
          repeatCount="indefinite"/>
      </path>
    </g>

    <!-- TITLE & SUBTITLE -->
    <text
      x="70"
      y="32"
      font-family="Arial, Helvetica, sans-serif"
      font-size="11"
      font-weight="700"
      fill="#C8FF00">
      Langganan
    </text>

    <!-- MAIN TITLE (dikecilkan agar pas dan tidak terpotong) -->
    <text
      x="70"
      y="49"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="800"
      fill="#FFFFFF">
      Hemat Paket Rutin
    </text>

    <!-- DESCRIPTION -->
    <text
      x="70"
      y="63"
      font-family="Arial, Helvetica, sans-serif"
      font-size="9.5"
      font-weight="500"
      fill="#EAF7FF">
      Paket mingguan &amp; bulanan
    </text>

    <!-- PROMO / BENEFIT BOX -->
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
      PENOWARAN RUTIN
    </text>

    <!-- CODE / VALUE -->
    <text
      x="28"
      y="102"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="800"
      letter-spacing=".5"
      fill="#FFD43B">
      DISKON S.D. 25%
    </text>

    <!-- QUICK ACTIONS -->

    <!-- MINGGUAN -->
    <g transform="translate(16 124)">
      <rect
        width="70"
        height="34"
        rx="10"
        fill="#FFFFFF"
        opacity=".13"/>

      <!-- check icon -->
      <circle cx="16" cy="17" r="5" fill="#C8FF00"/>
      <path
        d="M13.5 17L15.5 19L18.5 14.5"
        fill="none"
        stroke="#0A326B"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"/>

      <text
        x="27"
        y="20.5"
        font-family="Arial, Helvetica, sans-serif"
        font-size="8.5"
        font-weight="700"
        fill="#FFFFFF">
        Mingguan
      </text>
    </g>

    <!-- BULANAN -->
    <g transform="translate(94 124)">
      <rect
        width="70"
        height="34"
        rx="10"
        fill="#FFFFFF"
        opacity=".13"/>

      <!-- check icon -->
      <circle cx="16" cy="17" r="5" fill="#FFD43B"/>
      <path
        d="M13.5 17L15.5 19L18.5 14.5"
        fill="none"
        stroke="#0A326B"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"/>

      <text
        x="27"
        y="20.5"
        font-family="Arial, Helvetica, sans-serif"
        font-size="8.5"
        font-weight="700"
        fill="#FFFFFF">
        Bulanan
      </text>
    </g>

    <!-- CTA BUTTON -->
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
        Lihat Paket
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
