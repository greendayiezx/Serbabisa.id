<script setup lang="ts">
/**
 * Ilustrasi konfirmasi permintaan penawaran.
 *
 * Judul dan subjudulnya ikut digambar di dalam SVG (bagian dari desainnya),
 * jadi keduanya dijadikan prop — halaman ini punya tiga keadaan kedatangan dan
 * teks tetap akan salah di dua di antaranya.
 */
import { computed, onBeforeUnmount, ref, useId } from 'vue'

const props = defineProps<{
  judul: string
  subjudul: string
}>()

/**
 * ID gradien & filter bersifat global untuk seluruh dokumen. Tanpa awalan unik,
 * SVG lain dengan id yang sama akan saling menimpa warnanya.
 */
const uid = useId()
const idBrand = `${uid}-brand`
const idLime = `${uid}-lime`
const idShadow = `${uid}-shadow`

/**
 * Animasi SMIL tidak bisa dimatikan lewat CSS, jadi elemen <animate>-nya yang
 * tidak dirender ketika sistem meminta pengurangan gerak.
 */
const kurangiGerak = ref(false)
let mq: MediaQueryList | null = null

if (typeof window !== 'undefined' && window.matchMedia) {
  mq = window.matchMedia('(prefers-reduced-motion: reduce)')
  kurangiGerak.value = mq.matches
  const ubah = (e: MediaQueryListEvent) => (kurangiGerak.value = e.matches)
  mq.addEventListener('change', ubah)
  onBeforeUnmount(() => mq?.removeEventListener('change', ubah))
}

const bergerak = computed(() => !kurangiGerak.value)

/**
 * Centangnya digambar dengan menganimasikan stroke-dashoffset dari 50 ke 0.
 *
 * Kalau <animate>-nya tidak dirender, offset-nya tetap 50 dan centangnya TIDAK
 * PERNAH terlihat. Jadi saat gerak dikurangi, garis putus-putusnya dilepas sama
 * sekali supaya centangnya tergambar penuh sejak awal.
 */
const dashCentang = computed(() => (bergerak.value ? '50' : undefined))
const dashOffsetCentang = computed(() => (bergerak.value ? '50' : undefined))

/**
 * Ukuran judul menyesuaikan panjangnya.
 *
 * <text> SVG tidak membungkus baris; judul yang lebih panjang dari desain
 * aslinya akan meluber keluar bingkai kalau ukurannya tidak diturunkan.
 */
const ukuranJudul = computed(() => {
  const n = props.judul.length
  if (n <= 28) return 16
  if (n <= 38) return 13.5
  return 12
})
</script>

<template>
  <svg viewBox="0 0 400 240" class="w-full h-auto block" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient :id="idBrand" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#0A326B" />
        <stop offset="55%" stop-color="#167FE8" />
        <stop offset="100%" stop-color="#3BBEB8" />
      </linearGradient>

      <linearGradient :id="idLime" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#E5FF78" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>

      <filter :id="idShadow" x="-30%" y="-30%" width="160%" height="170%">
        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#062A5A" flood-opacity=".18" />
      </filter>
    </defs>

    <!-- Tanpa latar kotak: ilustrasinya menyatu dengan halaman. -->

    <!-- Cahaya brand -->
    <circle cx="200" cy="105" r="70" fill="#3BBEB8" opacity=".08">
      <template v-if="bergerak">
        <animate attributeName="r" values="62;78;62" dur="3s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".04;.11;.04" dur="3s" repeatCount="indefinite" />
      </template>
    </circle>

    <!-- Dokumen melayang -->
    <g :filter="`url(#${idShadow})`">
      <g>
        <animateTransform
          v-if="bergerak"
          attributeName="transform"
          type="translate"
          values="0 2;0 -4;0 2"
          dur="2.8s"
          repeatCount="indefinite"
        />

        <rect x="137" y="43" width="126" height="126" rx="18" fill="#FFFFFF" />
        <path d="M169 43H231L263 75H201C183 75 169 61 169 43Z" fill="#EAF7FF" />
        <path d="M231 43V72H260" fill="none" stroke="#B9E3F4" stroke-width="3" />

        <rect x="161" y="91" width="72" height="7" rx="3.5" fill="#DCECF5" />
        <rect x="161" y="107" width="54" height="7" rx="3.5" fill="#DCECF5" />
        <rect x="161" y="123" width="64" height="7" rx="3.5" fill="#DCECF5" />
        <rect x="161" y="143" width="45" height="6" rx="3" fill="#3BBEB8" />
      </g>
    </g>

    <!-- Lencana berhasil -->
    <g>
      <circle cx="245" cy="145" r="31" fill="none" stroke="#C8FF00" stroke-width="3" opacity=".5">
        <template v-if="bergerak">
          <animate attributeName="r" values="27;40;27" dur="2s" repeatCount="indefinite" />
          <animate attributeName="opacity" values=".6;0;.6" dur="2s" repeatCount="indefinite" />
        </template>
      </circle>

      <circle cx="245" cy="145" r="27" :fill="`url(#${idLime})`" :filter="`url(#${idShadow})`" />

      <path
        d="M231 145L241 155L260 134"
        fill="none"
        stroke="#FFFFFF"
        stroke-width="7"
        stroke-linecap="round"
        stroke-linejoin="round"
        :stroke-dasharray="dashCentang"
        :stroke-dashoffset="dashOffsetCentang"
      >
        <animate
          v-if="bergerak"
          attributeName="stroke-dashoffset"
          values="50;0"
          dur="0.7s"
          begin="0.2s"
          fill="freeze"
        />
      </path>
    </g>

    <!-- Tim peninjau -->
    <g transform="translate(103 128)">
      <circle cx="0" cy="0" r="25" fill="#EAF8FF" />

      <circle cx="-7" cy="-5" r="6" fill="#FFD0AD" />
      <path d="M-18 14 C-18 5 -14 1 -7 1 C0 1 4 5 4 14" fill="#167FE8" />

      <circle cx="8" cy="-6" r="5" fill="#D88C68" />
      <path d="M0 13 C1 5 4 1 9 1 C15 1 18 6 18 13" fill="#3BBEB8" />

      <path d="M18 -22 L21 -15 L28 -12 L21 -9 L18 -2 L15 -9 L8 -12 L15 -15Z" fill="#FFD43B">
        <animate
          v-if="bergerak"
          attributeName="opacity"
          values=".4;1;.4"
          dur="1.5s"
          repeatCount="indefinite"
        />
      </path>

      <animateTransform
        v-if="bergerak"
        attributeName="transform"
        type="translate"
        values="103 128;103 124;103 128"
        dur="2.8s"
        repeatCount="indefinite"
      />
    </g>

    <!-- Alur proses -->
    <path
      d="M128 128 C135 128 137 128 143 128"
      fill="none"
      stroke="#3BBEB8"
      stroke-width="3"
      stroke-dasharray="6 6"
      stroke-linecap="round"
    >
      <animate
        v-if="bergerak"
        attributeName="stroke-dashoffset"
        values="0;-24"
        dur="1s"
        repeatCount="indefinite"
      />
    </path>

    <!-- Kilau kecil -->
    <g fill="#C8FF00">
      <path d="M106 67L109 74L116 77L109 80L106 87L103 80L96 77L103 74Z">
        <template v-if="bergerak">
          <animate attributeName="opacity" values=".2;1;.2" dur="1.8s" repeatCount="indefinite" />
          <animateTransform
            attributeName="transform"
            type="rotate"
            values="0 106 77;180 106 77;360 106 77"
            dur="4s"
            repeatCount="indefinite"
          />
        </template>
      </path>
    </g>

    <circle cx="290" cy="96" r="4" fill="#FFD43B">
      <animate
        v-if="bergerak"
        attributeName="cy"
        values="96;89;96"
        dur="1.6s"
        repeatCount="indefinite"
      />
    </circle>

    <!-- Judul & subjudul: memakai huruf brand, bukan Arial bawaan SVG. -->
    <text x="200" y="192" text-anchor="middle" class="judul" :style="{ fontSize: `${ukuranJudul}px` }">
      {{ judul }}
    </text>
    <text x="200" y="212" text-anchor="middle" class="subjudul">{{ subjudul }}</text>

    <!-- Titik status -->
    <g transform="translate(177 225)">
      <circle cx="0" cy="0" r="3.5" fill="#C8FF00" />
      <circle cx="13" cy="0" r="3.5" fill="#3BBEB8">
        <animate
          v-if="bergerak"
          attributeName="opacity"
          values=".3;1;.3"
          dur="1.2s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="26" cy="0" r="3.5" fill="#167FE8">
        <animate
          v-if="bergerak"
          attributeName="opacity"
          values=".3;1;.3"
          dur="1.2s"
          begin=".4s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="39" cy="0" r="3.5" fill="#FFD43B">
        <animate
          v-if="bergerak"
          attributeName="opacity"
          values=".3;1;.3"
          dur="1.2s"
          begin=".8s"
          repeatCount="indefinite"
        />
      </circle>
    </g>
  </svg>
</template>

<style scoped>
.judul {
  font-family: var(--font-display, inherit);
  font-weight: 800;
  fill: #0a326b;
}

.subjudul {
  font-family: inherit;
  font-size: 11.5px;
  font-weight: 500;
  fill: #557086;
}
</style>
