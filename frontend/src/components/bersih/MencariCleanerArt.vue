<script setup lang="ts">
/**
 * Ilustrasi "sedang mencari cleaner".
 *
 * Menggunakan ID unik via useId() untuk gradien & filter agar tidak bentrok dengan SVG lain,
 * serta mendukung pembatalan animasi untuk preferensi reduced motion.
 * Background rect sengaja dihilangkan agar transparan tanpa border/latar belakang.
 */
import { onMounted, ref, useId } from 'vue'

withDefaults(
  defineProps<{
    judul?: string
    subjudul?: string
  }>(),
  {
    judul: 'Mencari cleaner profesional',
    subjudul: 'untuk Anda',
  },
)

const uid = useId()
const svg = ref<SVGSVGElement | null>(null)

onMounted(() => {
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    svg.value?.pauseAnimations()
  }
})
</script>

<template>
  <svg
    ref="svg"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 240"
    class="w-full h-auto block"
    role="img"
    :aria-label="`${judul} ${subjudul}`"
  >
    <defs>
      <linearGradient :id="`${uid}-brandBlue`" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#0A326B" />
        <stop offset="55%" stop-color="#167FE8" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <linearGradient :id="`${uid}-lime`" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#E5FF78" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>

      <radialGradient :id="`${uid}-glow`">
        <stop offset="0%" stop-color="#3BBEB8" stop-opacity=".20" />
        <stop offset="100%" stop-color="#3BBEB8" stop-opacity="0" />
      </radialGradient>

      <filter :id="`${uid}-shadow`" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow
          dx="0"
          dy="6"
          stdDeviation="7"
          flood-color="#0A326B"
          flood-opacity=".16"
        />
      </filter>
    </defs>

    <!-- SOFT GLOW -->
    <circle cx="200" cy="100" r="95" :fill="`url(#${uid}-glow)`">
      <animate attributeName="r" values="82;108;82" dur="3s" repeatCount="indefinite" />
      <animate attributeName="opacity" values=".5;1;.5" dur="3s" repeatCount="indefinite" />
    </circle>

    <!-- ROTATING SEARCH RING -->
    <circle
      cx="200"
      cy="96"
      r="57"
      fill="none"
      stroke="#3BBEB8"
      stroke-width="2"
      stroke-dasharray="5 9"
      opacity=".45"
    >
      <animateTransform
        attributeName="transform"
        type="rotate"
        from="0 200 96"
        to="360 200 96"
        dur="8s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- SECOND RING -->
    <circle
      cx="200"
      cy="96"
      r="72"
      fill="none"
      stroke="#C8FF00"
      stroke-width="2"
      stroke-dasharray="3 14"
      opacity=".30"
    >
      <animateTransform
        attributeName="transform"
        type="rotate"
        from="360 200 96"
        to="0 200 96"
        dur="10s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- LEFT CLEANER BADGE -->
    <g>
      <circle cx="119" cy="91" r="18" fill="#FFFFFF" :filter="`url(#${uid}-shadow)`" />
      <circle cx="119" cy="87" r="5" fill="#FFD0AE" />
      <path
        d="M111 101 C112 94 116 92 119 92 C123 92 127 95 128 101Z"
        fill="#167FE8"
      />
      <path
        d="M110 74L112 79L117 81L112 83L110 88L108 83L103 81L108 79Z"
        fill="#C8FF00"
      >
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1.5s"
          repeatCount="indefinite"
        />
      </path>
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 3;0 -4;0 3"
        dur="2.5s"
        repeatCount="indefinite"
      />
    </g>

    <!-- RIGHT CLEANER BADGE -->
    <g>
      <circle cx="281" cy="89" r="18" fill="#FFFFFF" :filter="`url(#${uid}-shadow)`" />
      <circle cx="281" cy="85" r="5" fill="#D8906D" />
      <path
        d="M273 99 C274 92 278 90 281 90 C285 90 289 93 290 99Z"
        fill="#3BBEB8"
      />
      <path
        d="M291 73L293 78L298 80L293 82L291 87L289 82L284 80L289 78Z"
        fill="#FFD43B"
      >
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1.7s"
          repeatCount="indefinite"
        />
      </path>
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 -3;0 4;0 -3"
        dur="2.7s"
        repeatCount="indefinite"
      />
    </g>

    <!-- MAIN MAGNIFYING GLASS -->
    <g :filter="`url(#${uid}-shadow)`">
      <circle cx="192" cy="91" r="38" fill="#FFFFFF" />
      <circle
        cx="192"
        cy="91"
        r="25"
        fill="#EAF8FF"
        stroke="#167FE8"
        stroke-width="5"
      />
      <path
        d="M178 82 C182 76 187 73 193 73"
        fill="none"
        stroke="#FFFFFF"
        stroke-width="4"
        stroke-linecap="round"
      />
      <!-- HANDLE -->
      <path
        d="M215 114L239 138"
        stroke="#0A326B"
        stroke-width="10"
        stroke-linecap="round"
      />
      <path
        d="M229 128L239 138"
        stroke="#C8FF00"
        stroke-width="5"
        stroke-linecap="round"
      />
      <animateTransform
        attributeName="transform"
        type="rotate"
        values="-3 192 91;3 192 91;-3 192 91"
        dur="1.8s"
        repeatCount="indefinite"
      />
    </g>

    <!-- SEARCHING DOTS -->
    <g>
      <circle cx="159" cy="137" r="5" fill="#C8FF00">
        <animate
          attributeName="cy"
          values="137;130;137"
          dur="1s"
          repeatCount="indefinite"
        />
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1s"
          repeatCount="indefinite"
        />
      </circle>

      <circle cx="176" cy="143" r="4" fill="#3BBEB8">
        <animate
          attributeName="cy"
          values="143;136;143"
          dur="1s"
          begin=".15s"
          repeatCount="indefinite"
        />
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1s"
          begin=".15s"
          repeatCount="indefinite"
        />
      </circle>

      <circle cx="192" cy="145" r="4" fill="#167FE8">
        <animate
          attributeName="cy"
          values="145;138;145"
          dur="1s"
          begin=".3s"
          repeatCount="indefinite"
        />
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1s"
          begin=".3s"
          repeatCount="indefinite"
        />
      </circle>

      <circle cx="208" cy="143" r="4" fill="#FFD43B">
        <animate
          attributeName="cy"
          values="143;136;143"
          dur="1s"
          begin=".45s"
          repeatCount="indefinite"
        />
        <animate
          attributeName="opacity"
          values=".3;1;.3"
          dur="1s"
          begin=".45s"
          repeatCount="indefinite"
        />
      </circle>
    </g>

    <!-- SPARKLE -->
    <path
      d="M106 67 L109 74 L116 77 L109 80 L106 87 L103 80 L96 77 L103 74Z"
      fill="#C8FF00"
    >
      <animate
        attributeName="opacity"
        values=".2;1;.2"
        dur="1.8s"
        repeatCount="indefinite"
      />
      <animateTransform
        attributeName="transform"
        type="rotate"
        values="0 106 77;180 106 77;360 106 77"
        dur="4s"
        repeatCount="indefinite"
      />
    </path>

    <!-- GOLD FLOATING DOT -->
    <circle cx="290" cy="96" r="4" fill="#FFD43B">
      <animate
        attributeName="cy"
        values="96;89;96"
        dur="1.6s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- TITLE -->
    <text
      x="200"
      y="178"
      text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif"
      font-size="17"
      font-weight="800"
      fill="#0A326B"
    >
      {{ judul }}
    </text>

    <!-- SUBTITLE -->
    <text
      x="200"
      y="198"
      text-anchor="middle"
      font-family="Arial, Helvetica, sans-serif"
      font-size="12"
      font-weight="500"
      fill="#557086"
    >
      {{ subjudul }}
      <tspan>
        <animate
          attributeName="opacity"
          values="1;0;1"
          dur="1.2s"
          repeatCount="indefinite"
        />
        ...
      </tspan>
    </text>

    <!-- LOADING DOTS -->
    <circle cx="177" cy="218" r="3.5" fill="#C8FF00" />
    <circle cx="190" cy="218" r="3.5" fill="#3BBEB8">
      <animate
        attributeName="opacity"
        values=".3;1;.3"
        dur="1.2s"
        repeatCount="indefinite"
      />
    </circle>
    <circle cx="203" cy="218" r="3.5" fill="#167FE8">
      <animate
        attributeName="opacity"
        values=".3;1;.3"
        dur="1.2s"
        begin=".4s"
        repeatCount="indefinite"
      />
    </circle>
    <circle cx="216" cy="218" r="3.5" fill="#FFD43B">
      <animate
        attributeName="opacity"
        values=".3;1;.3"
        dur="1.2s"
        begin=".8s"
        repeatCount="indefinite"
      />
    </circle>
  </svg>
</template>
