<script setup lang="ts">
/**
 * Pola doodle BisaBersih dengan Nama Brand & Slogan (Style GoSend).
 *
 * Feature:
 * - Brand logo text 'bisabersih' & badge tagline slogan 'Rumah & kantor bersih, best-nya BisaBersih aja!'
 * - Ikon-ikon doodle pattern yang dibesarkan (scale 0.55) & berwarna lebih jelas.
 * - Seamless & Rapat dengan height flex-1 menutupi seluruh sisa area layar.
 */
import { useId } from 'vue'

withDefaults(
  defineProps<{
    /** Tinggi pita dalam piksel; jika tidak diisi/undefined, akan mengisi fleksibel hingga bawah layar. */
    tinggi?: number
    /** Sudut membulat; matikan saat pita dipakai selebar layar. */
    bulat?: boolean
  }>(),
  { bulat: true },
)

const uid = useId()

/*
 * Posisi ikon di dalam ubin 360×360:
 * Staggered offset antar baris (baris 2 & 4 digeser pertengahan 45px),
 * variasi skala (s) dan rotasi (r) acak teratur untuk tekstur alami.
 */
const IKON = [
  { id: 'spray', x: 30, y: 35, r: -12, s: 1.05, warna: 'biru' },
  { id: 'ember', x: 120, y: 40, r: 8, s: 1.15 },
  { id: 'pel', x: 210, y: 35, r: -15, s: 1.0 },
  { id: 'sapu', x: 300, y: 42, r: 18, s: 1.2 },

  { id: 'gelembung', x: 75, y: 105, r: 5, s: 1.25, warna: 'lime' },
  { id: 'kain', x: 165, y: 110, r: -10, s: 0.95 },
  { id: 'sarung-tangan', x: 255, y: 102, r: 12, s: 1.1, warna: 'emas' },
  { id: 'kemoceng', x: 345, y: 108, r: -8, s: 1.0 },

  { id: 'vakum', x: 30, y: 180, r: 14, s: 1.15, warna: 'biru' },
  { id: 'sabun', x: 120, y: 175, r: -12, s: 1.05 },
  { id: 'kilau', x: 210, y: 182, r: 10, s: 1.2, warna: 'emas' },
  { id: 'spons', x: 300, y: 176, r: -6, s: 0.95, warna: 'lime' },

  { id: 'tetes', x: 75, y: 250, r: -8, s: 1.1, warna: 'biru' },
  { id: 'keranjang', x: 165, y: 255, r: 11, s: 1.15 },
  { id: 'handuk', x: 255, y: 248, r: -14, s: 1.0 },
  { id: 'rambu', x: 345, y: 252, r: 7, s: 1.05 },

  { id: 'pengki', x: 30, y: 320, r: -10, s: 1.2 },
  { id: 'karet', x: 120, y: 325, r: 15, s: 0.95 },
  { id: 'spray', x: 210, y: 318, r: -5, s: 1.1, warna: 'biru' },
  { id: 'gelembung', x: 300, y: 322, r: 12, s: 1.15, warna: 'lime' },
] as const

const WARNA: Record<string, { stroke: string; opacity: number }> = {
  biru: { stroke: '#2196F3', opacity: 0.55 },
  lime: { stroke: '#8BC53F', opacity: 0.6 },
  emas: { stroke: '#F5B301', opacity: 0.55 },
}

function gaya(w?: string) {
  return w ? WARNA[w] : { stroke: 'currentColor', opacity: 0.24 }
}
</script>

<template>
  <div
    class="relative w-full overflow-hidden bg-(--color-surface-container) text-(--color-on-surface) flex-1 flex flex-col items-center justify-center py-8 px-4 text-center select-none"
    :class="[bulat ? 'rounded-2xl' : '', tinggi ? '' : 'h-full min-h-[220px]']"
    :style="tinggi ? { height: `${tinggi}px` } : {}"
  >
    <!-- Background SVG Pattern -->
    <svg
      width="100%"
      height="100%"
      fill="none"
      class="absolute inset-0 w-full h-full block pointer-events-none"
      aria-hidden="true"
    >
      <defs>
        <g :id="`${uid}-spray`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9.5 3.5h4v3.5h-4z" />
          <rect x="6.5" y="7" width="10" height="13.5" rx="3" />
          <path d="M15.5 3l3-1M15.5 5.2h3.2M15.5 7.4l3-1" />
        </g>

        <g :id="`${uid}-ember`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 9h14l-1.5 10.5a2 2 0 0 1-2 1.7H8.5a2 2 0 0 1-2-1.7z" />
          <path d="M7.5 9a4.5 4.5 0 0 1 9 0" />
          <circle cx="9" cy="5" r="1.4" />
          <circle cx="13.5" cy="3.8" r="1" />
        </g>

        <g :id="`${uid}-pel`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v9" />
          <path d="M7 12h10l-1.5 7.5h-7z" />
          <path d="M10 12.5v6.5M14 12.5v6.5" />
        </g>

        <g :id="`${uid}-sapu`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 3.5L9.8 11" />
          <path d="M6 11.5l7 4.5-3.2 5-7-4.5z" />
          <path d="M7.8 15.2l-1.6 2.6M10.4 16.8l-1.6 2.6" />
        </g>

        <g :id="`${uid}-gelembung`" fill="none" stroke-width="1.8">
          <circle cx="9" cy="14" r="4.2" />
          <circle cx="15.6" cy="9.4" r="2.6" />
          <circle cx="16.6" cy="16.8" r="1.6" />
        </g>

        <g :id="`${uid}-kain`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 7c2-2 4 2 6 0s4-2 6 0v10c-2 2-4-2-6 0s-4 2-6 0z" />
        </g>

        <g :id="`${uid}-sarung-tangan`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path
            d="M8.5 21v-5.2c-1.7-.9-2.8-2.5-2.8-4.4V8a1.6 1.6 0 0 1 3.2 0v2.4m0-3.6a1.6 1.6 0 0 1 3.2 0v3.4m0-2.2a1.6 1.6 0 0 1 3.2 0V13c0 3.2-1.4 5.4-2.9 6.4V21"
          />
        </g>

        <g :id="`${uid}-kemoceng`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 21v-7" />
          <path d="M12 14c-3.6 0-5.8-2.4-5.8-5.4S8.6 3 12 3s5.8 2.6 5.8 5.6S15.6 14 12 14z" />
          <path d="M9.6 6.6c.9 1.5 1.4 3.3 1.4 4.9M14.4 6.6c-.9 1.5-1.4 3.3-1.4 4.9" />
        </g>

        <g :id="`${uid}-vakum`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="11" width="9.5" height="8" rx="3" />
          <circle cx="6.8" cy="19.6" r="1.4" />
          <circle cx="11.2" cy="19.6" r="1.4" />
          <path d="M13.5 13c4.2 0 5.2-3 5.2-5.6V5" />
          <path d="M17 4h3.4v2.2H17z" />
        </g>

        <g :id="`${uid}-sabun`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="10" width="15" height="8" rx="3" />
          <circle cx="16.2" cy="6.2" r="2" />
        </g>

        <g :id="`${uid}-kilau`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3l1.8 5.4L19 10.2l-5.2 1.8L12 17.4l-1.8-5.4L5 10.2l5.2-1.8z" />
          <path d="M18.4 16.2l.6 1.8 1.8.6-1.8.6-.6 1.8-.6-1.8-1.8-.6 1.8-.6z" />
        </g>

        <g :id="`${uid}-spons`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="8" width="15" height="9" rx="3" />
          <circle cx="9" cy="12" r="1" />
          <circle cx="13.2" cy="14" r="1" />
          <circle cx="15.6" cy="11" r=".9" />
        </g>

        <g :id="`${uid}-tetes`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 3.5s4.4 5.5 4.4 8.4a4.4 4.4 0 1 1-8.8 0C6.6 9 11 3.5 11 3.5z" />
          <circle cx="18.4" cy="17.4" r="1.8" />
        </g>

        <g :id="`${uid}-keranjang`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 9h14l-1.2 10a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z" />
          <path d="M8.5 9V7a3.5 3.5 0 0 1 7 0v2" />
          <path d="M9.4 12.4v6M12 12.4v6M14.6 12.4v6" />
        </g>

        <g :id="`${uid}-handuk`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="7" width="15" height="10" rx="2.5" />
          <path d="M4.5 10.6h15M8.6 7v10" />
        </g>

        <g :id="`${uid}-rambu`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 20.5L12 4.5l3 16" />
          <path d="M6.8 20.5h10.4" />
          <path d="M12 9.4s1.5 2 1.5 3a1.5 1.5 0 1 1-3 0c0-1 1.5-3 1.5-3z" />
        </g>

        <g :id="`${uid}-pengki`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4.5 10h11.5l-1.6 7.8H6.1z" />
          <path d="M16 10l4-3.2" />
          <path d="M6.4 17.8h8" />
        </g>

        <g :id="`${uid}-karet`" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3.5v8" />
          <rect x="5" y="11.5" width="14" height="3.6" rx="1.6" />
          <path d="M5.8 15.4h12.4" />
        </g>

        <pattern
          :id="`${uid}-pola`"
          width="360"
          height="360"
          patternUnits="userSpaceOnUse"
          patternTransform="scale(0.55)"
        >
          <g v-for="(i, idx) in IKON" :key="idx">
            <use
              :href="`#${uid}-${i.id}`"
              :transform="`translate(${i.x} ${i.y}) rotate(${i.r}) scale(${i.s}) translate(-12 -12)`"
              :stroke="gaya(i.warna).stroke"
              :opacity="gaya(i.warna).opacity"
            />
          </g>
        </pattern>
      </defs>

      <rect width="100%" height="100%" :fill="`url(#${uid}-pola)`" />
    </svg>

    <!-- Brand Logo Title with User's House SVG Icon & Slogan -->
    <div class="relative z-10 flex flex-col items-center justify-center my-auto py-6 pointer-events-none select-none">
      <!-- Title Row: Custom SVG Icon + bisabersih -->
      <div class="flex items-center gap-3 mb-2">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 120 120"
          class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 drop-shadow-sm"
          fill="none"
        >
          <defs>
            <linearGradient
              :id="`${uid}-bisaBersih-house`"
              x1="25"
              y1="20"
              x2="95"
              y2="100"
              gradientUnits="userSpaceOnUse"
            >
              <stop offset="0%" stop-color="#167FE8" />
              <stop offset="100%" stop-color="#3BBEB8" />
            </linearGradient>

            <linearGradient
              :id="`${uid}-lime-house`"
              x1="0"
              y1="0"
              x2="1"
              y2="1"
            >
              <stop offset="0%" stop-color="#E8FF82" />
              <stop offset="100%" stop-color="#C8FF00" />
            </linearGradient>

            <filter
              :id="`${uid}-shadow-house`"
              x="-30%"
              y="-30%"
              width="160%"
              height="170%"
            >
              <feDropShadow
                dx="0"
                dy="4"
                stdDeviation="4"
                flood-color="#0A326B"
                flood-opacity=".14"
              />
            </filter>
          </defs>

          <!-- Soft background -->
          <circle cx="60" cy="60" r="48" fill="#F1FBFC" />

          <!-- House -->
          <g :filter="`url(#${uid}-shadow-house)`">
            <!-- Roof -->
            <path
              d="M23 55 L60 24 L97 55 L91 62 L60 36 L29 62Z"
              :fill="`url(#${uid}-bisaBersih-house)`"
            />

            <!-- House body -->
            <path
              d="M31 55 L60 33 L89 55 V91 C89 94 87 96 84 96 H36 C33 96 31 94 31 91Z"
              fill="#FFFFFF"
            />

            <!-- Door -->
            <path
              d="M51 96V70 C51 67 53 65 56 65 H64 C67 65 69 67 69 70 V96Z"
              :fill="`url(#${uid}-bisaBersih-house)`"
            />

            <!-- Door knob -->
            <circle cx="64" cy="82" r="2" fill="#C8FF00" />

            <!-- Window -->
            <rect
              x="38"
              y="62"
              width="11"
              height="13"
              rx="2"
              fill="#DFF7FA"
              stroke="#3BBEB8"
              stroke-width="2"
            />
            <path d="M43.5 62V75 M38 68.5H49" stroke="#3BBEB8" stroke-width="1.5" />

            <!-- Right window -->
            <rect
              x="71"
              y="62"
              width="11"
              height="13"
              rx="2"
              fill="#DFF7FA"
              stroke="#3BBEB8"
              stroke-width="2"
            />
            <path d="M76.5 62V75 M71 68.5H82" stroke="#3BBEB8" stroke-width="1.5" />
          </g>

          <!-- Lime sparkle -->
          <path
            d="M92 19 L96 29 L106 33 L96 37 L92 47 L88 37 L78 33 L88 29Z"
            :fill="`url(#${uid}-lime-house)`"
          />

          <!-- Gold sparkle -->
          <path
            d="M25 28 L28 35 L35 38 L28 41 L25 48 L22 41 L15 38 L22 35Z"
            fill="#FFD43B"
          />

          <!-- Cleaning sparkle -->
          <path
            d="M96 69 L99 76 L106 79 L99 82 L96 89 L93 82 L86 79 L93 76Z"
            fill="#C8FF00"
          />

          <!-- Small clean particles -->
          <circle cx="18" cy="67" r="3" fill="#3BBEB8" />
          <circle cx="24" cy="76" r="2" fill="#167FE8" />
          <circle cx="103" cy="57" r="2.5" fill="#3BBEB8" />

          <!-- Ground accent -->
          <path
            d="M29 101 C42 96 54 97 65 101 C76 105 87 105 96 101"
            stroke="#3BBEB8"
            stroke-width="4"
            stroke-linecap="round"
          />
        </svg>

        <h2
          class="font-display font-extrabold text-[32px] sm:text-[40px] tracking-tight text-[#585F70] leading-none drop-shadow-xs"
        >
          bisabersih
        </h2>
      </div>

      <!-- Tagline without box border, blending directly into background pattern -->
      <p class="text-[13px] sm:text-[14px] font-bold text-[#525969] opacity-90 leading-snug">
        Rumah &amp; kantor bersih, best-nya BisaBersih aja!
      </p>
    </div>
  </div>
</template>
