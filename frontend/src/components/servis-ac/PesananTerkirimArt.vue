<script setup lang="ts">
/**
 * Ilustrasi "pesanan servis terkirim".
 */
import { onMounted, ref, useId } from 'vue'

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
    viewBox="0 0 300 220"
    width="100%"
    height="auto"
    fill="none"
    class="w-full h-auto block max-w-[280px] mx-auto"
    role="img"
    aria-label="Pesanan servis terkirim"
  >
    <defs>
      <!-- Brand -->
      <linearGradient
        :id="`${uid}-brand`"
        x1="75"
        y1="35"
        x2="225"
        y2="185"
        gradientUnits="userSpaceOnUse"
      >
        <stop offset="0%" stop-color="#167FE8" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <!-- Lime -->
      <linearGradient :id="`${uid}-lime`" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#E8FF82" />
        <stop offset="100%" stop-color="#C8FF00" />
      </linearGradient>

      <!-- Gold -->
      <linearGradient :id="`${uid}-gold`" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFE98A" />
        <stop offset="100%" stop-color="#E5B72F" />
      </linearGradient>

      <filter :id="`${uid}-shadow`" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow
          dx="0"
          dy="5"
          stdDeviation="5"
          flood-color="#0A326B"
          flood-opacity=".16"
        />
      </filter>
    </defs>

    <!-- SOFT BACKGROUND -->
    <ellipse cx="150" cy="191" rx="100" ry="15" fill="#DDF5F7" opacity=".7" />

    <!-- TECHNICIAN BODY -->
    <g :filter="`url(#${uid}-shadow)`">
      <!-- Body / Uniform -->
      <path
        d="M103 112 C103 96 115 87 130 87 H166 C181 87 193 96 193 112 V157 H103Z"
        :fill="`url(#${uid}-brand)`"
      />

      <!-- Uniform highlight -->
      <path
        d="M128 91 L149 109 L171 91"
        stroke="#FFFFFF"
        stroke-width="4"
        stroke-linecap="round"
        stroke-linejoin="round"
        opacity=".55"
      />

      <!-- Collar -->
      <path d="M128 91L149 111L140 119L121 96" fill="#FFFFFF" opacity=".85" />
      <path d="M171 91L149 111L158 119L178 96" fill="#EAFBFF" opacity=".85" />

      <!-- Head -->
      <circle cx="149" cy="67" r="24" fill="#F3C6A4" />

      <!-- Hair -->
      <path
        d="M126 66 C125 47 136 37 151 39 C166 39 174 49 172 65 C167 57 161 54 154 54 C144 54 135 59 126 66Z"
        fill="#243746"
      />

      <!-- Ear -->
      <circle cx="126" cy="68" r="5" fill="#E8B795" />
      <circle cx="172" cy="68" r="5" fill="#E8B795" />

      <!-- Face -->
      <circle cx="141" cy="69" r="2" fill="#243746" />
      <circle cx="158" cy="69" r="2" fill="#243746" />
      <path
        d="M143 80 C147 83 152 83 156 80"
        stroke="#A66D5A"
        stroke-width="2"
        stroke-linecap="round"
      />

      <!-- Technician cap -->
      <path
        d="M127 48 C131 35 142 30 153 32 C163 33 170 40 172 49 C158 44 142 44 127 48Z"
        fill="#167FE8"
      />
      <path
        d="M127 48 C140 44 157 43 172 49"
        stroke="#C8FF00"
        stroke-width="3"
        stroke-linecap="round"
      />

      <!-- Shirt pocket -->
      <path d="M165 119 H181 V137 H165Z" fill="#FFFFFF" opacity=".18" />

      <!-- Pocket tool -->
      <path
        d="M172 121V133"
        stroke="#C8FF00"
        stroke-width="3"
        stroke-linecap="round"
      />
    </g>

    <!-- TOOL / WRENCH -->
    <g transform="translate(91 139) rotate(-25)" :filter="`url(#${uid}-shadow)`">
      <path
        d="M17 2 C10 1 4 5 2 11 C1 15 3 19 6 21 L13 14 L20 21 C24 19 26 15 25 11 C24 6 21 3 17 2Z"
        :fill="`url(#${uid}-lime)`"
      />
      <rect x="11" y="16" width="7" height="27" rx="3.5" fill="#FFFFFF" />
    </g>

    <!-- SEARCH MAGNIFYING GLASS -->
    <g transform-origin="207px 117px" :filter="`url(#${uid}-shadow)`">
      <!-- Search circle -->
      <circle
        cx="207"
        cy="108"
        r="30"
        fill="#FFFFFF"
        :stroke="`url(#${uid}-brand)`"
        stroke-width="7"
      />

      <!-- Search field -->
      <circle cx="207" cy="108" r="19" fill="#EAFBFF" />

      <!-- Person silhouette inside search -->
      <circle cx="207" cy="101" r="6" fill="#3BBEB8" />
      <path
        d="M196 119 C198 110 216 110 218 119"
        stroke="#167FE8"
        stroke-width="5"
        stroke-linecap="round"
      />

      <!-- Handle -->
      <path
        d="M229 130L246 147"
        stroke="#167FE8"
        stroke-width="10"
        stroke-linecap="round"
      />

      <!-- Search pulse -->
      <circle
        cx="207"
        cy="108"
        r="30"
        stroke="#C8FF00"
        stroke-width="2"
        opacity=".6"
      >
        <animate
          attributeName="r"
          values="30;39;30"
          dur="2s"
          repeatCount="indefinite"
        />
        <animate
          attributeName="opacity"
          values=".6;0;.6"
          dur="2s"
          repeatCount="indefinite"
        />
      </circle>
    </g>

    <!-- LOCATION PIN -->
    <g transform="translate(223 35)" :filter="`url(#${uid}-shadow)`">
      <path
        d="M18 3 C8 3 2 10 2 19 C2 31 18 43 18 43 C18 43 34 31 34 19 C34 10 28 3 18 3Z"
        :fill="`url(#${uid}-gold)`"
      />
      <circle cx="18" cy="18" r="7" fill="#FFFFFF" />
      <circle cx="18" cy="18" r="3" fill="#167FE8" />
    </g>

    <!-- SEARCHING DOTS -->
    <circle cx="62" cy="91" r="4" fill="#C8FF00">
      <animate
        attributeName="cy"
        values="91;84;91"
        dur="1.6s"
        repeatCount="indefinite"
      />
      <animate
        attributeName="opacity"
        values=".4;1;.4"
        dur="1.6s"
        repeatCount="indefinite"
      />
    </circle>

    <circle cx="75" cy="73" r="3" fill="#3BBEB8">
      <animate
        attributeName="cy"
        values="73;67;73"
        dur="1.8s"
        repeatCount="indefinite"
      />
    </circle>

    <circle cx="239" cy="89" r="3" fill="#FFD43B">
      <animate
        attributeName="cy"
        values="89;83;89"
        dur="1.5s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- LIME SPARKLE -->
    <path
      d="M55 42 L59 52 L69 56 L59 60 L55 70 L51 60 L41 56 L51 52Z"
      fill="#C8FF00"
    >
      <animate
        attributeName="opacity"
        values="1;.35;1"
        dur="1.8s"
        repeatCount="indefinite"
      />
    </path>

    <!-- GOLD SPARKLE -->
    <path
      d="M257 130 L260 137 L267 140 L260 143 L257 150 L254 143 L247 140 L254 137Z"
      fill="#FFD43B"
    />

    <!-- BRAND WAVE -->
    <path
      d="M55 183 C79 174 102 176 124 183 C146 190 168 190 190 183 C212 176 232 176 253 183"
      stroke="#3BBEB8"
      stroke-width="4"
      stroke-linecap="round"
      opacity=".8"
    />
  </svg>
</template>
