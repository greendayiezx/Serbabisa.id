<script setup lang="ts">
defineProps<{
  statusText?: string
}>()
</script>

<template>
  <div class="w-full max-w-[430px] mx-auto flex items-center justify-center">
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 430 235"
      width="430"
      height="235"
      class="w-full h-auto"
    >
      <!-- BACKGROUND -->
      <rect width="430" height="300" fill="transparent"/>

      <!-- SMALL CLOUDS -->
      <g fill="#EAF2FA" opacity="0.35">
        <path
          d="M60 70
             C60 59 69 51 80 51
             C87 51 94 55 97 61
             C100 59 104 58 108 58
             C117 58 124 65 124 74
             H60Z"/>

        <path
          d="M310 82
             C310 73 318 66 328 66
             C334 66 340 69 343 75
             C346 73 349 72 353 72
             C361 72 368 78 368 86
             H310Z"/>
      </g>

      <!-- ROAD -->
      <g class="road">
        <path
          d="M0 224H470"
          stroke="#DCE7F1"
          stroke-width="8"
          stroke-linecap="round"
          opacity="0.3"/>

        <path
          d="M20 224H70
             M110 224H160
             M200 224H250
             M290 224H340
             M380 224H430"
          stroke="#8BC53F"
          stroke-width="5"
          stroke-linecap="round"/>
      </g>

      <!-- TRUCK -->
      <g class="truck">
        <!-- Cargo -->
        <rect
          x="90"
          y="130"
          width="175"
          height="91"
          rx="9"
          fill="#0A326B"/>

        <!-- Cargo top -->
        <path
          d="M90 130
             H255
             L267 140
             H90Z"
          fill="#174785"/>

        <!-- Cargo highlight -->
        <rect
          x="101"
          y="143"
          width="153"
          height="8"
          rx="4"
          fill="#123F7A"/>

        <!-- BRAND: Mini truck logo -->
        <g transform="translate(111 163) scale(.45)">
          <path
            d="M5 27
               V12
               H48
               V27
               H57
               L64 34
               V46
               H5Z"
            fill="#8BC53F"/>

          <circle cx="18" cy="47" r="7" fill="#071B3A"/>
          <circle cx="51" cy="47" r="7" fill="#071B3A"/>
          <path d="M48 18H58L63 28H48Z" fill="#8BC53F"/>
        </g>

        <!-- BRAND: BisaAngkut -->
        <text
          x="185"
          y="190"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="19"
          font-weight="800"
          fill="#FFFFFF">
          Bisa<tspan fill="#8BC53F">Angkut</tspan>
        </text>

        <!-- Lime stripe -->
        <rect
          x="90"
          y="205"
          width="175"
          height="7"
          rx="3.5"
          fill="#8BC53F"/>

        <!-- CAB -->
        <path
          d="M265 155
             H306
             C317 155 324 161 330 170
             L346 193
             V221
             H264Z"
          fill="#123F7A"/>

        <!-- Windshield -->
        <path
          d="M273 163
             H300
             C306 163 310 166 314 172
             L325 187
             H272Z"
          fill="#75A3CE"/>

        <!-- Windshield reflection -->
        <path
          d="M278 166H294L283 184H272Z"
          fill="#BBD8F0"
          opacity=".6"/>

        <!-- Door -->
        <path
          d="M270 191H318V220H270Z"
          fill="#0A326B"/>

        <!-- Door handle -->
        <rect
          x="296"
          y="197"
          width="14"
          height="4"
          rx="2"
          fill="#8BC53F"/>

        <!-- Headlight -->
        <path
          d="M324 190H342L348 202H324Z"
          fill="#FFF2A8"/>

        <!-- Bumper -->
        <path
          d="M324 220H352"
          stroke="#07111F"
          stroke-width="9"
          stroke-linecap="round"/>

        <!-- WHEELS -->
        <g class="wheel">
          <circle cx="130" cy="224" r="25" fill="#07111F"/>
          <circle cx="130" cy="224" r="14" fill="#66788E"/>
          <circle cx="130" cy="224" r="6" fill="#DCE3EA"/>
        </g>

        <g class="wheel">
          <circle cx="300" cy="224" r="25" fill="#07111F"/>
          <circle cx="300" cy="224" r="14" fill="#66788E"/>
          <circle cx="300" cy="224" r="6" fill="#DCE3EA"/>
        </g>
      </g>

    </svg>


  </div>
</template>

<style scoped>
.truck {
  animation: moveTruck 2.4s ease-in-out infinite;
}

.wheel {
  transform-box: fill-box;
  transform-origin: center;
  animation: wheelSpin .8s linear infinite;
}

.road {
  animation: roadMove 1s linear infinite;
}

@keyframes moveTruck {
  0%, 100% {
    transform: translateX(-8px);
  }

  50% {
    transform: translateX(8px);
  }
}

@keyframes wheelSpin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}


@keyframes roadMove {
  from {
    transform: translateX(0);
  }

  to {
    transform: translateX(-40px);
  }
}
</style>
