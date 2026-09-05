<script setup lang="ts">
/**
 * Kartu lokasi dengan peta pratinjau.
 *
 * Peta hanya untuk DILIHAT: interaksinya dimatikan supaya gerakan jari saat
 * menggulung halaman tidak berubah jadi menggeser peta. Pinnya dipindahkan
 * lewat lembar pilih lokasi, tempat pencariannya ada.
 *
 * Dijadikan komponen setelah blok yang sama ditulis di empat halaman —
 * pemeriksaan freon, perbaikan, pemasangan, dan cuci AC. Satu siklus hidup
 * peta yang dijaga, bukan empat; dan perbaikan pada salah satunya berlaku di
 * semuanya.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { TILE_OPTIONS, TILE_URL, pinIcon } from '@/lib/mapTiles'

const props = withDefaults(
  defineProps<{
    alamat: string
    lat: number
    lng: number
    label?: string
    /** Peta disembunyikan selama lembar pemilih terbuka di atasnya. */
    tersembunyi?: boolean
    /** Kosongkan kalau kartunya tidak boleh diketuk. */
    tombol?: string
    /**
     * Warna pin. BisaKirim memakai dua kartu di satu alur — biru untuk titik
     * ambil, oranye untuk tujuan — dan warnanya harus sama dengan pin di peta
     * rute, karena di situlah orang belajar mana ujung yang mana.
     */
    warnaPin?: string
  }>(),
  {
    label: 'Lokasi servis',
    tersembunyi: false,
    tombol: 'Ganti lokasi',
    warnaPin: '#1e9bf0',
  },
)

const emit = defineEmits<{ ubah: [] }>()

/** Baris pertama alamat — dipakai sebagai judul kartu, seperti di BisaAngkut. */
const alamatJudul = computed(() => props.alamat.split(',')[0] ?? props.alamat)

const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let penanda: L.Marker | null = null
let pengamat: ResizeObserver | null = null

function initPeta() {
  if (!petaEl.value || peta) return

  const titik: L.LatLngTuple = [props.lat, props.lng]
  peta = L.map(petaEl.value, {
    center: titik,
    zoom: 16,
    zoomControl: false,
    attributionControl: false,
    dragging: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    boxZoom: false,
    keyboard: false,
    touchZoom: false,
  })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  // pinIcon() dipanggil, bukan dipakai sebagai konstanta bersama: satu objek
  // DivIcon menyimpan elemen DOM-nya sendiri, jadi kartu kedua di halaman yang
  // sama akan mencuri pin milik kartu pertama.
  penanda = L.marker(titik, { icon: pinIcon(props.warnaPin) }).addTo(peta)

  pengamat = new ResizeObserver(() => peta?.invalidateSize())
  pengamat.observe(petaEl.value)
  requestAnimationFrame(() => peta?.invalidateSize())
}

function lepasPeta() {
  pengamat?.disconnect()
  pengamat = null
  peta?.remove()
  peta = null
  penanda = null
}

/*
 * Peta mengikuti titik yang dikirim induknya, bukan menyimpan titiknya sendiri.
 * Dengan begitu halaman pemanggil tetap jadi satu-satunya pemilik alamat — dan
 * tidak ada dua sumber kebenaran yang bisa berselisih.
 */
watch(
  () => [props.lat, props.lng] as const,
  ([lat, lng]) => {
    const titik: L.LatLngTuple = [lat, lng]
    peta?.setView(titik, peta.getZoom())
    penanda?.setLatLng(titik)
  },
)

onMounted(async () => {
  await nextTick()
  initPeta()
})

onBeforeUnmount(lepasPeta)

defineExpose({ lepasPeta })
</script>

<template>
  <!--
    isolate wajib. Panel Leaflet punya z-index sendiri (ubin 400, penanda 600)
    dan tanpa stacking context sendiri ia naik menembus elemen lain di halaman —
    termasuk footer yang melayang.
  -->
  <section
    class="isolate bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/25 overflow-hidden"
  >
    <button
      type="button"
      :aria-label="`Ubah ${label.toLowerCase()}`"
      class="block w-full text-left"
      @click="emit('ubah')"
    >
      <div
        ref="petaEl"
        class="w-full h-40 bg-(--color-surface-container) pointer-events-none"
        :style="{ visibility: tersembunyi ? 'hidden' : 'visible' }"
      ></div>
    </button>

    <div class="p-4 flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-[11px] text-(--color-on-surface-variant)">{{ label }}</p>
        <h3 class="text-[14px] font-display font-extrabold truncate">
          {{ alamatJudul || 'Lokasi belum dipilih' }}
        </h3>
        <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug line-clamp-2">
          {{ alamat || 'Ketuk peta untuk menandai lokasinya' }}
        </p>
      </div>

      <button
        v-if="tombol"
        type="button"
        class="shrink-0 px-4 py-2 rounded-full border-[1.5px] border-(--color-azure) text-(--color-azure) text-[12.5px] font-extrabold active:scale-95 transition-transform"
        @click="emit('ubah')"
      >
        {{ tombol }}
      </button>
    </div>
  </section>
</template>
