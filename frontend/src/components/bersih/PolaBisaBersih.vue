<script setup lang="ts">
/**
 * Pola doodle BisaBersih — ubin berulang tanpa sambungan.
 *
 * Seamless-nya didapat dari KONSTRUKSI, bukan dari menyambung tepi: tiap ikon
 * digambar utuh di dalam ubin 360×360 dan tidak ada yang menyentuh tepinya,
 * jadi ubin mana pun bisa ditempel bersebelahan tanpa potongan yang harus
 * bertemu. Itu juga yang membuat tidak ada ikon terpotong di pinggir.
 *
 * Warnanya ikut tema, bukan dipatok. Garis netralnya memakai token permukaan
 * dengan opasitas rendah, jadi pola ini tetap terbaca sebagai tekstur di tema
 * terang maupun gelap — warna abu-abu tetap akan hilang di salah satunya.
 * Aksen brand (biru, lime, emas) dipatok karena memang identitasnya, dengan
 * opasitas sedikit lebih tinggi supaya terbaca tanpa merebut perhatian.
 */
import { useId } from 'vue'

withDefaults(
  defineProps<{
    /** Tinggi pita dalam piksel. */
    tinggi?: number
  }>(),
  { tinggi: 132 },
)

const uid = useId()

/*
 * Posisi ikon di dalam ubin: kisi 5 baris yang digeser-geser, bukan kisi rapi.
 * Kisi rapi terbaca sebagai tabel; geseran kecil membuatnya terbaca sebagai
 * taburan. Dua slot sengaja dikosongkan supaya ada ruang bernapas.
 */
const IKON = [
  { id: 'spray', x: 40, y: 45, r: -12, warna: 'biru' },
  { id: 'ember', x: 140, y: 45, r: 8 },
  { id: 'pel', x: 240, y: 45, r: -6 },
  { id: 'sapu', x: 320, y: 45, r: 15 },

  { id: 'gelembung', x: 85, y: 115, r: 0, warna: 'lime' },
  { id: 'kain', x: 185, y: 115, r: -14 },
  { id: 'sarung-tangan', x: 285, y: 115, r: 10, warna: 'emas' },

  { id: 'kemoceng', x: 35, y: 185, r: -8 },
  { id: 'vakum', x: 135, y: 185, r: 6, warna: 'biru' },
  { id: 'sabun', x: 235, y: 185, r: -10 },
  { id: 'kilau', x: 330, y: 185, r: 12, warna: 'emas' },

  { id: 'spons', x: 80, y: 255, r: 9, warna: 'lime' },
  { id: 'tetes', x: 180, y: 255, r: -5, warna: 'biru' },
  { id: 'keranjang', x: 280, y: 255, r: 7 },

  { id: 'handuk', x: 45, y: 320, r: -11 },
  { id: 'rambu', x: 145, y: 320, r: 5 },
  { id: 'pengki', x: 245, y: 320, r: -9 },
  { id: 'karet', x: 320, y: 320, r: 14 },
] as const

const WARNA: Record<string, { stroke: string; opacity: number }> = {
  biru: { stroke: '#2E9BFF', opacity: 0.5 },
  lime: { stroke: '#B6E320', opacity: 0.55 },
  emas: { stroke: '#F5B301', opacity: 0.5 },
}

function gaya(w?: string) {
  return w ? WARNA[w] : { stroke: 'currentColor', opacity: 0.22 }
}
</script>

<template>
  <!--
    aria-hidden: ini murni hiasan. Membacakannya ke pembaca layar berarti
    menyisipkan belasan nama benda di tengah daftar alamat.
  -->
  <div
    class="w-full overflow-hidden rounded-2xl bg-(--color-surface-container) text-(--color-on-surface)"
    :style="{ height: `${tinggi}px` }"
    aria-hidden="true"
  >
    <svg width="100%" :height="tinggi" fill="none">
      <defs>
        <g :id="`${uid}-spray`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9.5 3.5h4v3.5h-4z" />
          <rect x="6.5" y="7" width="10" height="13.5" rx="3" />
          <path d="M15.5 3l3-1M15.5 5.2h3.2M15.5 7.4l3-1" />
        </g>

        <g :id="`${uid}-ember`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 9h14l-1.5 10.5a2 2 0 0 1-2 1.7H8.5a2 2 0 0 1-2-1.7z" />
          <path d="M7.5 9a4.5 4.5 0 0 1 9 0" />
          <circle cx="9" cy="5" r="1.4" />
          <circle cx="13.5" cy="3.8" r="1" />
        </g>

        <g :id="`${uid}-pel`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v9" />
          <path d="M7 12h10l-1.5 7.5h-7z" />
          <path d="M10 12.5v6.5M14 12.5v6.5" />
        </g>

        <g :id="`${uid}-sapu`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 3.5L9.8 11" />
          <path d="M6 11.5l7 4.5-3.2 5-7-4.5z" />
          <path d="M7.8 15.2l-1.6 2.6M10.4 16.8l-1.6 2.6" />
        </g>

        <g :id="`${uid}-gelembung`" fill="none" stroke-width="1.6">
          <circle cx="9" cy="14" r="4.2" />
          <circle cx="15.6" cy="9.4" r="2.6" />
          <circle cx="16.6" cy="16.8" r="1.6" />
        </g>

        <g :id="`${uid}-kain`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 7c2-2 4 2 6 0s4-2 6 0v10c-2 2-4-2-6 0s-4 2-6 0z" />
        </g>

        <g :id="`${uid}-sarung-tangan`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path
            d="M8.5 21v-5.2c-1.7-.9-2.8-2.5-2.8-4.4V8a1.6 1.6 0 0 1 3.2 0v2.4m0-3.6a1.6 1.6 0 0 1 3.2 0v3.4m0-2.2a1.6 1.6 0 0 1 3.2 0V13c0 3.2-1.4 5.4-2.9 6.4V21"
          />
        </g>

        <g :id="`${uid}-kemoceng`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 21v-7" />
          <path d="M12 14c-3.6 0-5.8-2.4-5.8-5.4S8.6 3 12 3s5.8 2.6 5.8 5.6S15.6 14 12 14z" />
          <path d="M9.6 6.6c.9 1.5 1.4 3.3 1.4 4.9M14.4 6.6c-.9 1.5-1.4 3.3-1.4 4.9" />
        </g>

        <g :id="`${uid}-vakum`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="11" width="9.5" height="8" rx="3" />
          <circle cx="6.8" cy="19.6" r="1.4" />
          <circle cx="11.2" cy="19.6" r="1.4" />
          <path d="M13.5 13c4.2 0 5.2-3 5.2-5.6V5" />
          <path d="M17 4h3.4v2.2H17z" />
        </g>

        <g :id="`${uid}-sabun`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="10" width="15" height="8" rx="3" />
          <circle cx="16.2" cy="6.2" r="2" />
        </g>

        <g :id="`${uid}-kilau`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3l1.8 5.4L19 10.2l-5.2 1.8L12 17.4l-1.8-5.4L5 10.2l5.2-1.8z" />
          <path d="M18.4 16.2l.6 1.8 1.8.6-1.8.6-.6 1.8-.6-1.8-1.8-.6 1.8-.6z" />
        </g>

        <g :id="`${uid}-spons`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="8" width="15" height="9" rx="3" />
          <circle cx="9" cy="12" r="1" />
          <circle cx="13.2" cy="14" r="1" />
          <circle cx="15.6" cy="11" r=".9" />
        </g>

        <g :id="`${uid}-tetes`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 3.5s4.4 5.5 4.4 8.4a4.4 4.4 0 1 1-8.8 0C6.6 9 11 3.5 11 3.5z" />
          <circle cx="18.4" cy="17.4" r="1.8" />
        </g>

        <g :id="`${uid}-keranjang`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 9h14l-1.2 10a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z" />
          <path d="M8.5 9V7a3.5 3.5 0 0 1 7 0v2" />
          <path d="M9.4 12.4v6M12 12.4v6M14.6 12.4v6" />
        </g>

        <g :id="`${uid}-handuk`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4.5" y="7" width="15" height="10" rx="2.5" />
          <path d="M4.5 10.6h15M8.6 7v10" />
        </g>

        <g :id="`${uid}-rambu`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 20.5L12 4.5l3 16" />
          <path d="M6.8 20.5h10.4" />
          <path d="M12 9.4s1.5 2 1.5 3a1.5 1.5 0 1 1-3 0c0-1 1.5-3 1.5-3z" />
        </g>

        <g :id="`${uid}-pengki`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4.5 10h11.5l-1.6 7.8H6.1z" />
          <path d="M16 10l4-3.2" />
          <path d="M6.4 17.8h8" />
        </g>

        <g :id="`${uid}-karet`" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3.5v8" />
          <rect x="5" y="11.5" width="14" height="3.6" rx="1.6" />
          <path d="M5.8 15.4h12.4" />
        </g>

        <!--
          patternTransform mengecilkan seluruh ubin, bukan tiap ikon: dengan
          begitu jarak antar-ikon ikut mengecil dan kerapatannya naik. Kalau
          hanya ikonnya yang dikecilkan, yang bertambah cuma ruang kosong.

          Ubin 360×360. Tiap ikon berjarak minimal 20px dari tepi ubin, jadi
          tidak ada yang terpotong saat pola diulang.
        -->
        <pattern
          :id="`${uid}-pola`"
          width="360"
          height="360"
          patternUnits="userSpaceOnUse"
          patternTransform="scale(0.58)"
        >
          <g v-for="i in IKON" :key="i.id">
            <use
              :href="`#${uid}-${i.id}`"
              :transform="`translate(${i.x} ${i.y}) rotate(${i.r}) scale(1.15) translate(-12 -12)`"
              :stroke="gaya(i.warna).stroke"
              :opacity="gaya(i.warna).opacity"
            />
          </g>
        </pattern>
      </defs>

      <rect width="100%" :height="tinggi" :fill="`url(#${uid}-pola)`" />
    </svg>
  </div>
</template>
