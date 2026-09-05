<script setup lang="ts">
/**
 * Peta rute kiriman: titik ambil, tujuan, dan garis di antaranya.
 *
 * Dipakai layar detail dan layar konfirmasi. Diangkat jadi komponen karena
 * bentuk garisnya menyampaikan sesuatu — lihat catatan di bawah — dan aturan
 * seperti itu tidak boleh hidup dua kali dengan kemungkinan berbeda.
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'

const props = defineProps<{
  ambil: { lat: number; lng: number } | null
  antar: { lat: number; lng: number } | null
  /** Titik-titik rute dari server, sudah [lat,lng]. */
  geometri?: [number, number][] | null
  /** Benar bila geometri di atas benar-benar mengikuti jalan. */
  lewatJalan?: boolean
}>()

const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let garis: L.Polyline | null = null
let bayang: L.Polyline | null = null
let penandaAmbil: L.Marker | null = null
let penandaAntar: L.Marker | null = null

function gambar() {
  if (!petaEl.value || !props.ambil || !props.antar) return

  if (!peta) {
    peta = L.map(petaEl.value, { zoomControl: false, attributionControl: false })
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  }

  const a: L.LatLngTuple = [props.ambil.lat, props.ambil.lng]
  const b: L.LatLngTuple = [props.antar.lat, props.antar.lng]

  // Dibuang dulu, bukan dipindahkan: penanda lama yang tertinggal membuat dua
  // pin untuk satu titik, dan yang basi tampak sama sahihnya.
  penandaAmbil?.remove()
  penandaAntar?.remove()
  penandaAmbil = L.marker(a, { icon: pinIcon('#1e9bf0') }).addTo(peta)
  penandaAntar = L.marker(b, { icon: pinIcon('#f97316') }).addTo(peta)

  /*
   * Garis mengikuti jalan bila server bisa mengambil rutenya — dan titik-titik
   * itu berasal dari perhitungan yang sama yang dipakai menagih. Kalau tidak,
   * yang tergambar garis lurus PUTUS-PUTUS: bentuk yang berbeda supaya tidak
   * terbaca sebagai rute sungguhan.
   */
  const titik: L.LatLngExpression[] =
    (props.geometri as L.LatLngExpression[] | null | undefined) ?? [a, b]

  garis?.remove()
  bayang?.remove()
  bayang = L.polyline(titik, { color: '#1B2C5E', weight: 8, opacity: 0.22, lineJoin: 'round' }).addTo(peta)
  garis = L.polyline(titik, {
    color: '#8BC53F',
    weight: 5,
    lineJoin: 'round',
    dashArray: props.lewatJalan ? undefined : '8 8',
  }).addTo(peta)

  peta.fitBounds(L.latLngBounds(titik as L.LatLngTuple[]).pad(0.25))
  setTimeout(() => peta?.invalidateSize(), 120)
}

onMounted(async () => {
  await nextTick()
  gambar()
})

onBeforeUnmount(() => {
  peta?.remove()
  peta = null
})

watch(() => [props.ambil, props.antar, props.geometri, props.lewatJalan], gambar, { deep: true })

defineExpose({ gambar })
</script>

<template>
  <!--
    isolate wajib. Panel Leaflet punya z-index sendiri (ubin 400, penanda 600)
    dan tanpa stacking context sendiri ia naik menembus elemen lain di halaman —
    termasuk footer yang melayang.
  -->
  <div
    ref="petaEl"
    class="isolate w-full h-44 rounded-xl overflow-hidden"
    aria-label="Peta rute kiriman"
  ></div>
</template>
