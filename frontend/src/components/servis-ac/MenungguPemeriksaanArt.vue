<script setup lang="ts">
/**
 * Ilustrasi "menunggu pemeriksaan teknisi".
 *
 * Gambarnya dipakai apa adanya; tiga hal disesuaikan agar benar sebagai
 * komponen, dan ketiganya bukan soal selera:
 *
 * 1. Id di <defs> diberi awalan useId(). Id SVG berlaku sedokumen, jadi nama
 *    sepolos "brand", "gold", atau "shadow" akan diambil dari definisi pertama
 *    yang kebetulan dirender lebih dulu — diam-diam, tanpa galat.
 * 2. Animasinya SMIL, dan SMIL tidak bisa dimatikan lewat CSS. Untuk pengguna
 *    yang meminta gerakan dikurangi, animasinya dihentikan lewat
 *    pauseAnimations(). Tiap elemen tetap tampil utuh dalam keadaan diamnya,
 *    jadi tidak ada yang hilang saat animasinya berhenti.
 * 3. Gradien `lime` dari berkas asal dibuang: tidak ada satu pun elemen yang
 *    merujuknya.
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
    fill="none"
    class="w-full h-auto block"
    role="img"
    aria-label="Teknisi sedang dalam perjalanan untuk memeriksa AC Anda"
  >
    <defs>
      <!-- Brand gradient -->
      <linearGradient
        :id="`${uid}-brand`"
        x1="70"
        y1="30"
        x2="230"
        y2="190"
        gradientUnits="userSpaceOnUse"
      >
        <stop stop-color="#167FE8" />
        <stop offset="1" stop-color="#3BBEB8" />
      </linearGradient>

      <!-- Gold -->
      <linearGradient :id="`${uid}-gold`" x1="0" y1="0" x2="1" y2="1">
        <stop stop-color="#FFE98A" />
        <stop offset="1" stop-color="#E5B72F" />
      </linearGradient>

      <filter :id="`${uid}-shadow`" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow dx="0" dy="5" stdDeviation="5" flood-color="#0A326B" flood-opacity=".15" />
      </filter>
    </defs>

    <!-- Soft background -->
    <ellipse cx="150" cy="188" rx="105" ry="16" fill="#DDF5F7" opacity=".7" />

    <!-- Floating checklist -->
    <g :filter="`url(#${uid}-shadow)`">
      <rect x="79" y="35" width="105" height="125" rx="14" fill="#FFFFFF" />
      <rect x="79" y="35" width="105" height="30" rx="14" :fill="`url(#${uid}-brand)`" />

      <circle cx="98" cy="50" r="8" fill="#FFFFFF" opacity=".25" />
      <path
        d="M94 50L97 53L102 47"
        stroke="#FFFFFF"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />

      <!-- Checklist 1 -->
      <circle cx="96" cy="83" r="7" fill="#EAF8FA" />
      <path
        d="M92.5 83L95 85.5L99.5 80"
        stroke="#3BBEB8"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <rect x="110" y="79" width="53" height="7" rx="3.5" fill="#DCECEF" />

      <!-- Checklist 2 -->
      <circle cx="96" cy="108" r="7" fill="#EAF8FA" />
      <path
        d="M92.5 108L95 110.5L99.5 105"
        stroke="#3BBEB8"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <rect x="110" y="104" width="42" height="7" rx="3.5" fill="#DCECEF" />

      <!-- Checklist 3 waiting -->
      <circle cx="96" cy="133" r="7" fill="#FFF5D1" />
      <circle cx="96" cy="133" r="3" fill="#FFD43B" />
      <rect x="110" y="129" width="48" height="7" rx="3.5" fill="#DCECEF" />
    </g>

    <!-- Magnifying glass -->
    <g transform-origin="207px 112px" :filter="`url(#${uid}-shadow)`">
      <circle cx="207" cy="104" r="25" fill="#FFFFFF" :stroke="`url(#${uid}-brand)`" stroke-width="7" />
      <circle cx="207" cy="104" r="14" fill="#EAFBFF" />
      <path d="M224 122L240 138" stroke="#167FE8" stroke-width="9" stroke-linecap="round" />

      <path
        d="M198 96C201 92 205 90 209 90"
        stroke="#FFFFFF"
        stroke-width="3"
        stroke-linecap="round"
        opacity=".9"
      />

      <circle cx="207" cy="104" r="25" stroke="#C8FF00" stroke-width="2" opacity=".5">
        <animate attributeName="r" values="25;32;25" dur="2s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".5;0;.5" dur="2s" repeatCount="indefinite" />
      </circle>
    </g>

    <!-- Waiting clock -->
    <g transform-origin="218px 163px">
      <circle
        cx="218"
        cy="163"
        r="20"
        :fill="`url(#${uid}-gold)`"
        stroke="#FFFFFF"
        stroke-width="4"
        :filter="`url(#${uid}-shadow)`"
      />
      <circle cx="218" cy="163" r="14" fill="#FFF8D9" />

      <path
        d="M218 154V163L224 167"
        stroke="#B98517"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
      />

      <path d="M218 163V157" stroke="#B98517" stroke-width="2.5" stroke-linecap="round">
        <animateTransform
          attributeName="transform"
          type="rotate"
          from="0 218 163"
          to="360 218 163"
          dur="2.5s"
          repeatCount="indefinite"
        />
      </path>
    </g>

    <!-- Floating dots -->
    <circle cx="55" cy="66" r="5" fill="#C8FF00">
      <animate attributeName="cy" values="66;59;66" dur="2s" repeatCount="indefinite" />
      <animate attributeName="opacity" values=".5;1;.5" dur="2s" repeatCount="indefinite" />
    </circle>

    <circle cx="244" cy="62" r="4" fill="#3BBEB8">
      <animate attributeName="cy" values="62;55;62" dur="2.3s" repeatCount="indefinite" />
    </circle>

    <circle cx="63" cy="145" r="3" fill="#FFD43B">
      <animate attributeName="cy" values="145;139;145" dur="1.8s" repeatCount="indefinite" />
    </circle>

    <!-- Lime sparkle -->
    <path d="M239 29 L243 39 L253 43 L243 47 L239 57 L235 47 L225 43 L235 39Z" fill="#C8FF00">
      <animate attributeName="opacity" values="1;.35;1" dur="1.6s" repeatCount="indefinite" />
      <animateTransform
        attributeName="transform"
        type="rotate"
        from="0 239 43"
        to="360 239 43"
        dur="5s"
        repeatCount="indefinite"
      />
    </path>

    <!-- Bottom wavy brand accent -->
    <path
      d="M45 190 C72 177 97 181 120 190 C143 199 165 199 188 190 C211 181 232 181 255 190"
      stroke="#3BBEB8"
      stroke-width="5"
      stroke-linecap="round"
      opacity=".8"
    />
  </svg>
</template>
