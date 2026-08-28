<script setup lang="ts">
/**
 * Pelacakan cleaner menuju lokasi pengerjaan.
 *
 * SEJAUH MANA INI NYATA: tujuan (lokasi pengerjaan) memakai koordinat asli yang
 * dikirim server saat checkout, dan rutenya diambil dari OSRM sehingga benar
 * mengikuti jalan. Yang MASIH DISIMULASIKAN adalah titik keberangkatan dan
 * posisi cleaner di sepanjang rute — server belum mengirim GPS mitra. Karena itu
 * layar ini menyebut posisinya "perkiraan", bukan menampilkannya sebagai fakta.
 * Begitu GPS mitra tersedia, dua titik itu tinggal diganti tanpa mengubah
 * tampilan.
 *
 * Peta dibuat setelah lembarnya terpasang di DOM: Leaflet menghitung ukuran
 * dari elemen wadahnya, dan wadah yang masih tersembunyi berukuran nol.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'

const props = defineProps<{
  tampil: boolean
  lat: number | null
  lng: number | null
  namaCleaner?: string | null
  alamat?: string | null
}>()

const emit = defineEmits<{ tutup: [] }>()

const petaEl = ref<HTMLDivElement | null>(null)
const memuat = ref(false)
let peta: L.Map | null = null

function ikonCleaner(): L.DivIcon {
  return L.divIcon({
    className: '',
    html:
      '<svg viewBox="0 0 40 40" width="40" height="40" style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3))">' +
      '<circle cx="20" cy="20" r="15" fill="#63C21C" stroke="#fff" stroke-width="3"/>' +
      '<path d="M20 11 l2.2 5.4 5.6 0.8 -4 3.9 1 5.6 -4.8 -2.8 -4.8 2.8 1 -5.6 -4 -3.9 5.6 -0.8Z" fill="#fff"/></svg>',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
  })
}

/** Rute A→B yang mengikuti jalan, atau null kalau layanannya tidak menjawab. */
async function ruteJalan(a: L.LatLngTuple, b: L.LatLngTuple): Promise<L.LatLngTuple[] | null> {
  try {
    const res = await fetch(
      `https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`,
    )
    const data = await res.json()
    const titik = data?.routes?.[0]?.geometry?.coordinates
    if (!Array.isArray(titik)) return null
    return titik.map((c: [number, number]) => [c[1], c[0]] as L.LatLngTuple)
  } catch {
    return null
  }
}

function lepas() {
  peta?.remove()
  peta = null
}

async function pasang() {
  if (props.lat == null || props.lng == null) return

  memuat.value = true
  await nextTick()
  if (!petaEl.value || peta) return

  const tujuan: L.LatLngTuple = [props.lat, props.lng]
  const asal: L.LatLngTuple = [tujuan[0] - 0.012, tujuan[1] + 0.014]

  peta = L.map(petaEl.value, { center: tujuan, zoom: 14, zoomControl: false })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  requestAnimationFrame(() => peta?.invalidateSize())

  const rute = (await ruteJalan(asal, tujuan)) ?? [asal, tujuan]
  if (!peta) return

  const garis = L.polyline(rute, {
    color: '#63C21C',
    weight: 6,
    opacity: 0.95,
    lineCap: 'round',
    lineJoin: 'round',
  }).addTo(peta)

  L.marker(tujuan, { icon: pinIcon() }).addTo(peta)

  // Sekitar 40% panjang rute: sedang di jalan, bukan masih di titik berangkat.
  const posisi: L.LatLngTuple =
    rute.length > 2
      ? rute[Math.floor(rute.length * 0.4)]
      : [(asal[0] + tujuan[0]) / 2, (asal[1] + tujuan[1]) / 2]
  L.marker(posisi, { icon: ikonCleaner() }).addTo(peta)

  peta.fitBounds(garis.getBounds(), { padding: [50, 50] })
  memuat.value = false
}

watch(
  () => props.tampil,
  (v) => (v ? void pasang() : lepas()),
)

onBeforeUnmount(lepas)
</script>

<template>
  <Teleport to="body">
    <div v-if="tampil" class="fixed inset-0 z-[90] bg-(--color-surface) flex flex-col">
      <div class="relative flex-1 min-h-0">
        <div ref="petaEl" class="absolute inset-0"></div>

        <button
          type="button"
          aria-label="Tutup pelacakan"
          class="absolute top-4 left-4 z-[800] w-10 h-10 rounded-full bg-white text-(--color-on-surface) flex items-center justify-center shadow-md active:scale-95 transition-transform"
          @click="emit('tutup')"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <p
          v-if="memuat"
          class="absolute inset-x-0 top-1/2 text-center text-[12.5px] text-(--color-on-surface-variant)"
        >
          Memuat peta&hellip;
        </p>
      </div>

      <div class="shrink-0 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
        <div class="max-w-[430px] mx-auto px-5 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
          <h2 class="text-[15px] font-display font-extrabold">
            {{ namaCleaner ? `${namaCleaner} menuju lokasi` : 'Menuju lokasi pengerjaan' }}
          </h2>

          <div class="mt-3 flex items-start gap-3 rounded-2xl bg-(--color-secondary-container)/40 p-3.5">
            <span
              class="w-8 h-8 rounded-full bg-(--color-azure) text-white flex items-center justify-center shrink-0"
            >
              <Icon name="pin" class="w-4 h-4" />
            </span>
            <p class="flex-1 min-w-0 text-[12.5px] leading-snug">{{ alamat || 'Lokasi pengerjaan' }}</p>
          </div>

          <!--
            Dinyatakan terus terang. Posisi di peta adalah perkiraan di
            sepanjang rute, bukan GPS mitra — dan pengguna berhak tahu itu
            sebelum memperkirakan waktu kedatangan dari layar ini.
          -->
          <p class="mt-3 text-[11.5px] text-(--color-on-surface-variant) leading-snug">
            Posisi cleaner masih berupa perkiraan di sepanjang rute. Untuk
            kepastian waktu tiba, hubungi cleanernya langsung.
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
