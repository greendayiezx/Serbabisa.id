<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { cariLokasi, reverseGeocode } from '@/lib/geocode'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import PolaBisaBersih from '@/components/bersih/PolaBisaBersih.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useLocationStore, kunciAlamat, type PlaceItem, type SavedPlaceKey, type TaskLocation } from '@/stores/location'
import { useJemputStore } from '@/stores/jemput'
import { useKirimStore } from '@/stores/kirim'
import { useAuthStore } from '@/stores/auth'
import { useDriverStore } from '@/stores/driver'
import { driverPinIcon } from '@/lib/driverIcon'
import cardBorderLocationPagi from '@/assets/card-border-location-pagi.png'
import cardBorderLocationSore from '@/assets/card-border-location-sore.png'
import cardBorderLocationMalam from '@/assets/card-border-location-malam.png'
import cardBorderBisaAngkutPagi from '@/assets/card-border-BisaAngkut-pagi.png'
import cardBorderBisaAngkutSore from '@/assets/card-border-BisaAngkut-sore.png'
import cardBorderBisaAngkutMalam from '@/assets/card-border-BisaAngkut-malam.png'
import AngkutHeroArt from '@/components/angkut/AngkutHeroArt.vue'
import BisaBelanjaHeroArt from '@/components/belanja/BisaBelanjaHeroArt.vue'
import BisaBersihHeroArt from '@/components/bersih/BisaBersihHeroArt.vue'
import BisaJemputHeroArt from '@/components/jemput/BisaJemputHeroArt.vue'
import { heroTimeOfDayFromHour } from '@/lib/heroSky'
import { useSkeleton } from '@/composables/useSkeleton'
import LokasiSkeleton from '@/components/skeleton/LokasiSkeleton.vue'

const router = useRouter()
const route = useRoute()
const locationStore = useLocationStore()
const jemputStore = useJemputStore()
const kirimStore = useKirimStore()
const authStore = useAuthStore()
const driverStore = useDriverStore()

const alamat = ref(locationStore.draft?.alamat ?? '')
const lat = ref(locationStore.draft?.lat ?? 0)
const lng = ref(locationStore.draft?.lng ?? 0)
const selectedPlace = ref<TaskLocation | null>(locationStore.draft ? { ...locationStore.draft } : null)
const inputRef = ref<HTMLInputElement | null>(null)
const locating = ref(false)
const mapOpen = ref(false)
// Bumped every time the fullscreen picker opens; bound to :key on the map div so
// Vue always throws away the old DOM node and mounts a brand new one.
const mapInstanceKey = ref(0)
const mapEl = ref<HTMLDivElement | null>(null)
const previewMapEl = ref<HTMLDivElement | null>(null)
/**
 * Tile peta datang dari jaringan dan pada koneksi lambat butuh beberapa detik.
 * Sampai tile pertama termuat, kotak petanya diisi skeleton — tanpa ini yang
 * terlihat cuma kotak abu kosong yang tidak jelas sedang memuat atau rusak.
 */
const tilePratinjauSiap = ref(false)
const selectedId = ref<string | null>(null)
const fieldFocused = ref(false)

let map: L.Map | null = null
let marker: L.Marker | null = null
let mapResizeObserver: ResizeObserver | null = null
let previewMap: L.Map | null = null
let previewMarker: L.Marker | null = null
let previewResizeObserver: ResizeObserver | null = null
let previewDriverLayer: L.LayerGroup | null = null
let mapDriverLayer: L.LayerGroup | null = null

// Mapbox's "streets" style when a token is configured (free tier, no billing needed);
// falls back to the key-less CARTO tiles otherwise so the map still works either way.
const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN as string | undefined

const TILE_URL = MAPBOX_TOKEN
  ? 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/{z}/{x}/{y}{r}?access_token={accessToken}'
  : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png'

const TILE_OPTIONS: L.TileLayerOptions & { accessToken?: string } = MAPBOX_TOKEN
  ? {
      maxZoom: 20,
      tileSize: 512,
      zoomOffset: -1,
      accessToken: MAPBOX_TOKEN,
      attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }
  : {
      subdomains: 'abcd',
      maxZoom: 20,
      attribution: '© <a href="https://carto.com/">CARTO</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }

const pinIcon = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 40],
})

// Satu marker scooter SVG per mitra terdekat (tanpa label nama).
function addDriversToMap(map: L.Map): L.LayerGroup {
  const group = L.layerGroup()
  for (const d of driverStore.drivers) {
    L.marker([d.lat, d.lng], { icon: driverPinIcon(), keyboard: false }).addTo(group)
  }
  group.addTo(map)
  return group
}

// Guards against Leaflet leaving a stale map bound to a DOM node (e.g. after HMR
// swaps this component without the old instance's onBeforeUnmount ever firing).
function resetStaleLeafletContainer(el: HTMLElement) {
  if ((el as unknown as { _leaflet_id?: number })._leaflet_id) {
    el.innerHTML = ''
    delete (el as unknown as { _leaflet_id?: number })._leaflet_id
  }
}

const savedPlaces = ref<Partial<Record<SavedPlaceKey, TaskLocation>>>({})
const searchHistory = ref<PlaceItem[]>([])

function recordSearchHistory(location: { alamat: string; lat: number; lng: number }) {
  searchHistory.value = locationStore.addSearchHistory(location)
}

interface DisplayPlace {
  id: string
  title: string
  address: string
  lat: number
  lng: number
  lastUsed: string | null
  kind: 'saved' | 'recent'
}

function relativeTime(iso: string | null) {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  const min = Math.floor(diff / 60000)
  if (min < 1) return 'Baru saja'
  if (min < 60) return `${min} menit lalu`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr} jam lalu`
  const day = Math.floor(hr / 24)
  if (day === 1) return 'Kemarin'
  if (day < 7) return `${day} hari lalu`
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(iso))
}

const placeList = computed<DisplayPlace[]>(() => {
  const list: DisplayPlace[] = []
  const seen = new Set<string>()

  if (savedPlaces.value.home) {
    const h = savedPlaces.value.home
    seen.add(kunciAlamat(h.alamat, h.lat, h.lng))
    list.push({ id: 'saved:home', title: 'Rumah', address: h.alamat, lat: h.lat, lng: h.lng, lastUsed: null, kind: 'saved' })
  }
  if (savedPlaces.value.office) {
    const o = savedPlaces.value.office
    seen.add(kunciAlamat(o.alamat, o.lat, o.lng))
    list.push({ id: 'saved:office', title: 'Kantor', address: o.alamat, lat: o.lat, lng: o.lng, lastUsed: null, kind: 'saved' })
  }
  for (const r of searchHistory.value) {
    const key = kunciAlamat(r.address, r.lat, r.lng)
    if (seen.has(key)) continue
    seen.add(key)
    list.push({ id: r.id, title: r.label, address: r.address, lat: r.lat, lng: r.lng, lastUsed: r.last_used_at, kind: 'recent' })
  }
  for (const r of locationStore.recent) {
    const key = kunciAlamat(r.address, r.lat, r.lng)
    if (seen.has(key)) continue
    seen.add(key)
    list.push({ id: r.id, title: r.label, address: r.address, lat: r.lat, lng: r.lng, lastUsed: r.last_used_at, kind: 'recent' })
  }

  return list
})

const JAKARTA: L.LatLngTuple = [-6.2088, 106.8456]

function currentCenter(): L.LatLngTuple {
  return lat.value !== 0 && lng.value !== 0 ? [lat.value, lng.value] : JAKARTA
}

// Jam realtime — diperbarui setiap menit agar sapaan & gambar header ikut berganti.
const clock = ref(new Date())
let clockTimer: ReturnType<typeof setInterval> | null = null
function startClock() {
  stopClock()
  clockTimer = window.setInterval(() => { clock.value = new Date() }, 60000)
}
function stopClock() {
  if (clockTimer) {
    window.clearInterval(clockTimer)
    clockTimer = null
  }
}

const greeting = computed(() => {
  const hour = clock.value.getHours()
  if (hour >= 4 && hour < 11) return 'Selamat pagi'
  if (hour >= 11 && hour < 15) return 'Selamat siang'
  if (hour >= 15 && hour < 18) return 'Selamat sore'
  return 'Selamat malam'
})

type TimeOfDay = 'pagi' | 'sore' | 'malam'

// Gambar PNG header hanya punya 3 varian, jadi 'siang' ikut memakai aset 'pagi'.
const timeOfDay = computed<TimeOfDay>(() => {
  const hour = clock.value.getHours()
  if (hour >= 4 && hour < 15) return 'pagi'
  if (hour >= 15 && hour < 18) return 'sore'
  return 'malam'
})

// Ilustrasi SVG digambar ulang per fase, jadi bisa memakai 4 fase penuh.
const heroTimeOfDay = computed(() => heroTimeOfDayFromHour(clock.value.getHours()))

const headerBorderImages: Record<TimeOfDay, string> = {
  pagi: cardBorderLocationPagi,
  sore: cardBorderLocationSore,
  malam: cardBorderLocationMalam,
}

const bisaAngkutHeaderImages: Record<TimeOfDay, string> = {
  pagi: cardBorderBisaAngkutPagi,
  sore: cardBorderBisaAngkutSore,
  malam: cardBorderBisaAngkutMalam,
}

const isBisaAngkut = computed(() => route.query.category === 'bisaangkut' || route.name === 'task-angkut-location')
const isBisaBelanja = computed(() => route.query.category === 'bisabelanja')
const isBisaBersih = computed(() => route.query.category === 'bisabersih')
const isBisaJemput = computed(() => route.query.category === 'bisajemput')

const cardBorderLocationImg = computed(() =>
  isBisaAngkut.value
    ? bisaAngkutHeaderImages[timeOfDay.value]
    : headerBorderImages[timeOfDay.value],
)

const subtitleText = computed(() => {
  if (isBisaAngkut.value) return 'Mau angkut barang ke mana hari ini?'
  if (isBisaBelanja.value) return 'Mau belanja apa hari ini?'
  if (isBisaBersih.value) return 'Bersih-bersih di mana hari ini?'
  if (isBisaJemput.value) return 'Mau dijemput di mana?'
  return 'Mau anter tugas ke mana hari ini?'
})

/*
 * Seberapa jauh kartu putih menaiki hero.
 *
 * Menu yang punya hero SVG sendiri dinaikkan sampai kartunya menumpang di atas
 * gelombang hijau — tanpa itu ada pita kosong di antara hero dan kartu, dan
 * petanya terdorong turun sampai nyaris tidak kelihatan tanpa menggulir.
 */
const contentSheetMarginClass = computed(() => {
  if (isBisaJemput.value || isBisaAngkut.value || isBisaBelanja.value || isBisaBersih.value) return '-mt-14'
  return '-mt-1'
})

const firstName = computed(() => authStore.user?.name?.split(' ')[0] ?? '')

/*
 * Penerjemahan koordinat dan pencarian tempat memakai lib bersama
 * (lib/geocode.ts). Salinan lokal yang dulu ada di sini bertanya pada zoom
 * bawaan Nominatim, yang berhenti di tingkat jalan — nama gedung dan tempat
 * usaha tidak pernah ikut.
 */

async function useMyLocation(autoConfirm = false) {
  if (!navigator.geolocation) return
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      lat.value = pos.coords.latitude
      lng.value = pos.coords.longitude
      skipNextSearch = true
      alamat.value = await reverseGeocode(lat.value, lng.value)
      selectedId.value = null
      selectedPlace.value = { alamat: alamat.value, lat: lat.value, lng: lng.value }
      recordSearchHistory({ alamat: alamat.value, lat: lat.value, lng: lng.value })
      locating.value = false
      if (autoConfirm) finishLocationSelection()
    },
    () => { locating.value = false },
    { enableHighAccuracy: true, timeout: 10000 },
  )
}

interface SearchResult {
  /** Baris pertama: nama tempat kalau ada. */
  nama: string
  /** Baris kedua: alamatnya. */
  alamat: string
  label: string
  lat: number
  lng: number
}

const searchResults = ref<SearchResult[]>([])
const searching = ref(false)
const showDropdown = ref(false)
let searchDebounceId: ReturnType<typeof setTimeout> | null = null
let skipNextSearch = false

async function runSearch(query: string) {
  searching.value = true
  try {
    // Titik yang sedang aktif dipakai sebagai bias: "Indomaret" harus berarti
    // yang di dekat pengguna, bukan cabang di kota lain.
    searchResults.value = await cariLokasi(query, { lat: lat.value, lng: lng.value })
  } catch {
    searchResults.value = []
  } finally {
    showDropdown.value = searchResults.value.length > 0
    searching.value = false
  }
}

watch(alamat, (value) => {
  if (skipNextSearch) {
    skipNextSearch = false
    return
  }
  if (searchDebounceId) clearTimeout(searchDebounceId)
  const query = value.trim()
  if (!query) {
    searchResults.value = []
    showDropdown.value = false
    return
  }
  searchDebounceId = setTimeout(() => runSearch(query), 350)
})

function pickSearchResult(result: SearchResult) {
  if (searchDebounceId) clearTimeout(searchDebounceId)
  skipNextSearch = true
  alamat.value = result.label
  lat.value = result.lat
  lng.value = result.lng
  selectedId.value = null
  selectedPlace.value = { alamat: result.label, lat: result.lat, lng: result.lng }
  showDropdown.value = false
  searchResults.value = []
  recordSearchHistory({ alamat: result.label, lat: result.lat, lng: result.lng })
  finishLocationSelection()
}

function hideDropdownDelayed() {
  setTimeout(() => { showDropdown.value = false }, 150)
}

function onFieldFocus() {
  fieldFocused.value = true
}

function onFieldBlur() {
  hideDropdownDelayed()
  setTimeout(() => { fieldFocused.value = false }, 150)
}


function initPreviewMap() {
  if (!previewMapEl.value) return
  if (previewMap) {
    previewMap.remove()
    previewMap = null
    previewMarker = null
  }
  resetStaleLeafletContainer(previewMapEl.value)
  const center = currentCenter()
  previewMap = L.map(previewMapEl.value, {
    center,
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
  tilePratinjauSiap.value = false
  L.tileLayer(TILE_URL, TILE_OPTIONS)
    .on('load', () => (tilePratinjauSiap.value = true))
    .addTo(previewMap)
  previewMarker = L.marker(center, { icon: pinIcon }).addTo(previewMap)
  previewDriverLayer = addDriversToMap(previewMap)

  previewResizeObserver?.disconnect()
  previewResizeObserver = new ResizeObserver(() => previewMap?.invalidateSize())
  previewResizeObserver.observe(previewMapEl.value)

  requestAnimationFrame(() => previewMap?.invalidateSize())
}

function syncPreviewMap() {
  if (!previewMap || !previewMarker) return
  const center = currentCenter()
  previewMap.setView(center, previewMap.getZoom())
  previewMarker.setLatLng(center)
  if (map && marker) {
    map.setView(center, map.getZoom())
    marker.setLatLng(center)
  }
}

watch([lat, lng], syncPreviewMap)

function selectPlace(place: DisplayPlace) {
  selectedId.value = place.id
  skipNextSearch = true
  alamat.value = place.address
  lat.value = place.lat
  lng.value = place.lng
  selectedPlace.value = { alamat: place.address, lat: place.lat, lng: place.lng }
  showDropdown.value = false
  searchResults.value = []
  finishLocationSelection()
}

function sameLocation(a: TaskLocation, b: TaskLocation) {
  return a.alamat === b.alamat && Number(a.lat) === Number(b.lat) && Number(a.lng) === Number(b.lng)
}

const showClearAllConfirm = ref(false)

function confirmClearAll() {
  showClearAllConfirm.value = true
}

function clearAllPlaces() {
  locationStore.clearAll()
  savedPlaces.value = locationStore.loadSavedPlaces()
  searchHistory.value = locationStore.loadSearchHistory()
  selectedId.value = null
  selectedPlace.value = null
  showClearAllConfirm.value = false
}

function handleQuickSave(key: SavedPlaceKey) {
  const savedPlace = savedPlaces.value[key]
  const current: TaskLocation = { alamat: alamat.value, lat: lat.value, lng: lng.value }
  const currentIsSet = current.alamat.trim() !== '' && (current.lat !== 0 || current.lng !== 0)

  if (currentIsSet && (!savedPlace || !sameLocation(savedPlace, current))) {
    locationStore.savePlace(key, current)
    savedPlaces.value = locationStore.loadSavedPlaces()
    selectedId.value = `saved:${key}`
    return
  }

  if (savedPlace) {
    selectPlace({
      id: `saved:${key}`,
      title: key === 'home' ? 'Rumah' : 'Kantor',
      address: savedPlace.alamat,
      lat: savedPlace.lat,
      lng: savedPlace.lng,
      lastUsed: null,
      kind: 'saved',
    })
    return
  }

  inputRef.value?.focus()
}

function clearAddress() {
  alamat.value = ''
  selectedId.value = null
  selectedPlace.value = null
}

// Re-entry guard: openMap() awaits nextTick() before touching the DOM, so a
// second call fired before the first one resumes (e.g. a double tap) must not
// race past this point and create two map instances in the same container.
let openingMap = false

async function openMap() {
  if (openingMap) return
  openingMap = true
  try {
    mapOpen.value = true
    // Force a brand new DOM node for the map (old one, and anything Leaflet
    // attached to it, gets fully destroyed by Vue — nothing to leak or reuse).
    mapInstanceKey.value += 1
    await nextTick()
    initMap()
  } finally {
    openingMap = false
  }
}

function initMap() {
  if (!mapEl.value) return
  if (map) {
    map.remove()
    map = null
    marker = null
  }
  resetStaleLeafletContainer(mapEl.value)
  const center = currentCenter()
  map = L.map(mapEl.value, {
    center,
    zoom: 15,
    zoomControl: false,
    attributionControl: true,
  })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(map)

  // autoPan makes Leaflet itself pan the map when the dragged pin nears/exits
  // the visible edge — it stays put while the pin is still comfortably in view.
  marker = L.marker(center, { icon: pinIcon, draggable: true, autoPan: true }).addTo(map)
  mapDriverLayer = addDriversToMap(map)

  map.on('click', (e: L.LeafletMouseEvent) => {
    marker?.setLatLng(e.latlng)
    map?.panTo(e.latlng, { animate: true, duration: 0.3 })
  })

  mapResizeObserver?.disconnect()
  mapResizeObserver = new ResizeObserver(() => map?.invalidateSize())
  mapResizeObserver.observe(mapEl.value)

  requestAnimationFrame(() => map?.invalidateSize())
}

function closeMap() {
  mapOpen.value = false
  mapResizeObserver?.disconnect()
  mapResizeObserver = null
  if (mapDriverLayer) {
    mapDriverLayer.remove()
    mapDriverLayer = null
  }
  if (map) {
    map.remove()
    map = null
    marker = null
  }
}

function closePreviewMap() {
  previewResizeObserver?.disconnect()
  previewResizeObserver = null
  if (previewDriverLayer) {
    previewDriverLayer.remove()
    previewDriverLayer = null
  }
  if (previewMap) {
    previewMap.remove()
    previewMap = null
    previewMarker = null
  }
}

async function confirmMap() {
  if (!marker) return
  const ll = marker.getLatLng()
  lat.value = ll.lat
  lng.value = ll.lng
  skipNextSearch = true
  alamat.value = await reverseGeocode(ll.lat, ll.lng)
  selectedId.value = null
  selectedPlace.value = { alamat: alamat.value, lat: ll.lat, lng: ll.lng }
  recordSearchHistory({ alamat: alamat.value, lat: ll.lat, lng: ll.lng })
  closeMap()
  finishLocationSelection()
}

// The location picker is the first step of every new-task flow (Home ->
// Location -> detail/kategori), so "back" always exits to the home route.
// We deliberately do NOT use router.back()/window.history.state.back: in the
// belanja flow the detail page re-enters this page (BisaBelanjaNavbar, step >= 2),
// which leaves history.state.back pointing at /tasks/new/belanja/detail. router.back()
// would bounce right back into detail, trapping the user in a Location <-> Detail
// loop that can never reach `/` (home) — exactly the "can't go back to home" bug.
function goBackOrHome() {
  router.push({ name: 'home' })
}

// Alamat yang dipilih tampil sebagai "jembatan" di bawah — tombol Lanjut
// dipakai untuk melangkah ke halaman berikutnya.
const hasSelectedAddress = computed(() => selectedPlace.value !== null)

function finishLocationSelection() {
  const place = selectedPlace.value ?? { alamat: alamat.value, lat: lat.value, lng: lng.value }
  if (!place.alamat.trim()) return
  locationStore.setDraft(place)

  if (route.query.category === 'bisaangkut') {
    router.push({ name: 'task-angkut-detail' })
    return
  }

  if (route.query.category === 'bisabelanja') {
    router.push({ name: 'task-belanja-detail' })
    return
  }

  if (route.query.category === 'bisabersih') {
    router.push({ name: 'task-bersih-detail', query: { category: 'bisabersih' } })
    return
  }

  /*
   * BisaJemput tidak langsung ke pemesanan: alamat yang dipilih di sini baru
   * jadi TITIK JEMPUT setelah dikonfirmasi di peta. Hasil pencarian alamat
   * menunjuk ke tengah jalan atau ke gedung, bukan ke tempat orang berdiri —
   * dan pengemudi yang menunggu di titik yang salah adalah cara paling umum
   * sebuah perjalanan batal.
   */
  if (route.query.category === 'bisajemput') {
    jemputStore.setJemput({ alamat: place.alamat, lat: place.lat, lng: place.lng })
    router.push({ name: 'task-jemput-titik' })
    return
  }

  /*
   * BisaKirim: alamat yang dipilih di sini jadi TITIK AMBIL — tempat kurir
   * menjemput paket. Tujuannya diisi di halaman berikutnya, karena satu
   * kiriman selalu punya dua titik dan halaman ini hanya menanyakan satu.
   */
  if (route.query.category === 'bisakirim') {
    kirimStore.setAmbil({ alamat: place.alamat, lat: place.lat, lng: place.lng })
    router.push({ name: 'task-kirim' })
    return
  }

  goBackOrHome()
}

onMounted(async () => {
  startClock()
  savedPlaces.value = locationStore.loadSavedPlaces()
  searchHistory.value = locationStore.loadSearchHistory()
  if (!locationStore.draft) {
    await useMyLocation()
  }
  try {
    await locationStore.fetchRecent()
  } catch {
    // riwayat bersifat opsional — jangan memblokir halaman
  }
  const [centerLat, centerLng] = currentCenter()
  await driverStore.loadNearby(centerLat, centerLng)
})

onBeforeUnmount(() => {
  stopClock()
  closeMap()
  closePreviewMap()
})

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => tandaiSiap())))

/*
 * Peta dibuat SETELAH skeleton pergi, bukan di onMounted.
 *
 * Skeleton menempati seluruh template lewat v-if, jadi saat onMounted berjalan
 * div petanya belum ada di DOM sama sekali dan ref-nya masih null. initPreviewMap
 * berhenti di penjagaan null-nya, dan ketika skeleton hilang tidak ada lagi
 * yang memanggilnya — petanya tidak pernah tergambar.
 */
watch(skelTampil, async (masihSkeleton) => {
  if (masihSkeleton) return
  await nextTick()
  initPreviewMap()
})
</script>

<template>
  <LokasiSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="relative min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-0 overflow-x-hidden flex flex-col justify-between">
      <!--
        Menempel pada HALAMAN, bukan pada layar: dengan posisi tetap (fixed) ia
        ikut turun saat digulung dan menutupi isi di bawahnya.

        Konsekuensinya tombol ini tergulung hilang bersama header, dan halaman
        ini TIDAK punya bottom nav — jalan pulangnya tinggal gestur kembali
        peramban atau menggulung naik lagi. Itu pertukaran yang disengaja.

        z-40 supaya tetap di bawah overlay peta & dialog yang pakai z-50.
      -->
      <button
        type="button"
        aria-label="Kembali"
        class="absolute top-4 left-4 z-40 w-10 h-10 rounded-full bg-white text-slate-800 flex items-center justify-center shadow-md transition-transform active:scale-95"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5 text-slate-800" />
      </button>
  
      <!-- Full-Width Header: BisaAngkut = ilustrasi flat vector + wave lime (pola Gojek) -->
      <div
        v-if="isBisaAngkut"
        class="relative w-full overflow-hidden bg-[#060f29] rounded-b-[2rem]"
      >
        <AngkutHeroArt :time-of-day="heroTimeOfDay" />
  
        <!-- Greeting & Subtitle floats directly on the illustration's own wave -->
        <div class="absolute inset-x-5 bottom-16 z-10 flex flex-col items-center justify-center text-center">
          <h1 class="font-display font-extrabold text-[16px] sm:text-[18px] leading-tight text-white text-center drop-shadow-sm">
            {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
          </h1>
          <p class="text-white/95 text-[12px] sm:text-[13.5px] font-bold text-center mt-0.5">
            {{ subtitleText }}
          </p>
        </div>
      </div>
  
      <!-- Full-Width Header: BisaBelanja = ilustrasi flat vector + wave lime (pola Gojek) -->
      <div
        v-else-if="route.query.category === 'bisabelanja'"
        class="relative w-full overflow-hidden bg-[#060f29] rounded-b-[2rem]"
      >
        <BisaBelanjaHeroArt :time-of-day="heroTimeOfDay" />
  
        <!-- Greeting & Subtitle: floats directly on the illustration's own wave
             (no separate flat-color box), so there's no seam/edge against it -->
        <div class="absolute inset-x-5 bottom-16 z-10 flex flex-col items-center justify-center text-center">
          <h1 class="font-display font-extrabold text-[16px] sm:text-[18px] leading-tight text-white text-center drop-shadow-sm">
            {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
          </h1>
          <p class="text-white/95 text-[12px] sm:text-[13.5px] font-bold text-center mt-0.5">
            {{ subtitleText }}
          </p>
        </div>
      </div>
  
      <!-- Full-Width Header: BisaBersih mengikuti waktu nyata (pagi/siang/sore/malam)
           lewat heroTimeOfDay, sama seperti dua hero lain. Sapaannya bagian dari
           SVG (panel lime), jadi tidak ada overlay teks. -->
      <div
        v-else-if="route.query.category === 'bisabersih'"
        class="relative w-full overflow-hidden bg-[#0D1536] rounded-b-[2rem]"
      >
        <BisaBersihHeroArt :greeting="greeting" :nama="firstName" :subtitle="subtitleText" :time-of-day="heroTimeOfDay" />
      </div>
  
      <!-- Full-Width Header: BisaJemput, sekeluarga dengan hero lain — langit,
           siluet kota, dan lampu jalan ikut waktu nyata lewat heroTimeOfDay. -->
      <div
        v-else-if="isBisaJemput"
        class="relative w-full overflow-hidden bg-[#060f29] rounded-b-[2rem]"
      >
        <BisaJemputHeroArt :time-of-day="heroTimeOfDay" />

        <div
          class="absolute inset-x-5 bottom-16 z-10 flex flex-col items-center justify-center text-center"
        >
          <h1
            class="font-display font-extrabold text-[16px] sm:text-[18px] leading-tight text-white text-center drop-shadow-sm"
          >
            {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
          </h1>
          <p class="text-white/95 text-[12px] sm:text-[13.5px] font-bold text-center mt-0.5">
            {{ subtitleText }}
          </p>
        </div>
      </div>

      <!-- Full-Width Header: ilustrasi PNG lokasi lainnya (pagi/sore/malam) -->
      <div v-else class="relative w-full overflow-hidden bg-[#155b0e]">
        <div class="relative w-full">
          <img :src="cardBorderLocationImg" alt="Tugasin" class="w-full h-auto block" />
  
          <div class="absolute inset-x-0 bottom-0 h-[22%] bg-gradient-to-t from-black/55 to-transparent pointer-events-none"></div>
  
          <!-- Greeting & Subtitle, anchored right in the green band at the bottom of the artwork -->
          <div class="absolute inset-x-5 bottom-1 z-10 flex flex-col items-center justify-center text-center text-white">
            <h1 class="font-display font-extrabold text-[13px] sm:text-[15px] leading-tight text-white text-center drop-shadow-md">
              {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
            </h1>
            <p class="text-white/95 text-[10px] sm:text-[11px] font-semibold text-center mt-0.5 drop-shadow-sm">
              {{ subtitleText }}
            </p>
          </div>
        </div>
      </div>
  
      <!-- Floating White Content Sheet (raised slightly for maps) -->
      <div
        class="px-4 relative z-20"
        :class="contentSheetMarginClass"
      >
        <div class="bg-(--color-surface-0) rounded-3xl shadow-(--shadow-float) p-4 flex flex-col gap-4">
          <!-- map preview: real Leaflet map, centered on the device's live GPS position -->
          <button
            type="button"
            class="relative w-full h-40 rounded-2xl overflow-hidden bg-(--color-surface-container) text-left shadow-inner"
            @click="openMap"
          >
            <div
              ref="previewMapEl"
              class="absolute inset-0 pointer-events-none"
              :style="{ visibility: mapOpen ? 'hidden' : 'visible' }"
            ></div>
            <!-- Placeholder sampai tile peta pertama termuat -->
            <div
              v-if="!tilePratinjauSiap"
              class="absolute inset-0 pointer-events-none p-3 flex flex-col justify-end gap-2"
              data-map-skeleton
            >
              <Skeleton class="absolute inset-0 rounded-2xl" />
              <Skeleton class="relative h-2.5 w-2/5" />
              <Skeleton class="relative h-2.5 w-1/4" />
            </div>
            <span class="absolute top-2.5 left-2.5 glass-panel rounded-full text-[11px] font-bold px-2.5 py-1 flex items-center gap-1 text-(--color-on-surface)">
              <Icon name="map" class="w-3 h-3" />Pilih di Peta
            </span>
            <span v-if="locating" class="absolute inset-0 flex items-center justify-center bg-(--color-surface-0)/80 text-xs font-bold text-(--color-on-surface-variant)">
              Mencari lokasi kamu...
            </span>
          </button>
  
          <!-- search -->
          <div class="relative">
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <Icon name="search" class="w-4.5 h-4.5 text-(--color-azure)" />
              </div>
              <input
                ref="inputRef"
                v-model="alamat"
                :readonly="hasSelectedAddress"
                placeholder="Cari lokasi tujuan"
                class="w-full bg-(--color-surface-container) py-3.5 pl-11 pr-11 rounded-xl outline-none text-sm text-(--color-on-surface) placeholder:text-(--color-on-surface-variant)"
                @focus="onFieldFocus"
                @input="selectedId = null; fieldFocused = true"
                @blur="onFieldBlur"
              />
              <button v-if="alamat" type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center" @click="clearAddress">
                <Icon name="x" class="w-4 h-4 text-(--color-outline)" />
              </button>
            </div>
  
            <div v-if="showDropdown && searchResults.length" class="absolute z-30 top-full left-0 right-0 mt-1.5 bg-(--color-surface-0) rounded-xl shadow-(--shadow-float) border border-(--color-outline)/40 max-h-64 overflow-y-auto">
              <button
                v-for="(result, idx) in searchResults"
                :key="idx"
                type="button"
                class="w-full flex items-start gap-2.5 px-3.5 py-3 text-left border-b border-(--color-outline)/25 last:border-b-0 hover:bg-(--color-surface-container)"
                @mousedown.prevent="pickSearchResult(result)"
              >
                <Icon name="pin" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0 mt-0.5" />
                <span class="min-w-0 flex-1">
                  <span class="block text-[13px] font-bold text-(--color-on-surface) leading-snug truncate">
                    {{ result.nama }}
                  </span>
                  <span
                    v-if="result.alamat"
                    class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug"
                  >
                    {{ result.alamat }}
                  </span>
                </span>
              </button>
            </div>
            <div
              v-else-if="searching"
              class="absolute z-30 top-full left-0 right-0 mt-1.5 bg-(--color-surface-0) rounded-xl shadow-(--shadow-float) border border-(--color-outline)/40 px-3.5 py-3 text-[13px] text-(--color-on-surface-variant)"
            >
              Mencari...
            </div>
  
            <!-- confirmation card: active address display; tapping it proceeds to the next page -->
            <button
              type="button"
              v-if="hasSelectedAddress && fieldFocused"
              class="mt-2.5 w-full flex items-center gap-2.5 rounded-2xl border border-(--color-surface-container) px-3.5 py-3 text-left cursor-pointer"
              @click="finishLocationSelection"
            >
              <Icon name="pin" class="w-4 h-4 text-(--color-azure) shrink-0" />
              <p class="text-[13px] font-bold text-(--color-on-surface) leading-snug">{{ selectedPlace?.alamat }}</p>
            </button>
          </div>
  
          <!-- quick actions -->
          <div class="flex gap-2.5">
            <button
              type="button"
              class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-full text-[12.5px] font-bold text-(--color-on-surface) border-0 border-transparent transition-colors"
              :class="savedPlaces.home ? 'bg-(--color-primary-container)' : 'bg-white'"
              @click="handleQuickSave('home')"
            >
              <Icon name="home" class="w-4 h-4 text-(--color-on-primary-container)" />
              {{ savedPlaces.home ? 'Rumah' : 'Simpan Rumah' }}
            </button>
            <button
              type="button"
              class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-full text-[12.5px] font-bold text-(--color-on-surface) border-0 border-transparent transition-colors"
              :class="savedPlaces.office ? 'bg-(--color-primary-container)' : 'bg-white'"
              @click="handleQuickSave('office')"
            >
              <Icon name="business" class="w-4 h-4 text-(--color-on-primary-container)" />
              {{ savedPlaces.office ? 'Kantor' : 'Simpan Kantor' }}
            </button>
          </div>
  
          <button
            type="button"
            class="flex items-center gap-1.5 text-[12.5px] font-bold text-(--color-azure) -mt-1"
            @click="useMyLocation(true)"
          >
            <Icon name="crosshair" class="w-4 h-4" />
            {{ locating ? 'Mencari lokasi...' : 'Gunakan Lokasi Saat Ini' }}
          </button>
  
          <div class="h-px w-full bg-(--color-outline)/40"></div>
  
          <!-- saved / recent places -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <div class="text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide">Tersimpan / Baru-Baru Ini</div>
                <button
                  v-if="placeList.length"
                  type="button"
                  class="flex items-center gap-1 text-[11px] font-semibold text-(--color-on-surface-variant) hover:text-(--color-error) transition-colors"
                  @click="confirmClearAll"
                >
                  <Icon name="trash" class="w-3.5 h-3.5" />
                  Hapus Semua
                </button>
              </div>
              <div v-if="placeList.length" class="flex flex-col">
                <button
                  v-for="place in placeList"
                :key="place.id"
                type="button"
                class="flex items-center gap-3.5 py-3 border-b border-(--color-outline)/30 last:border-b-0 text-left"
                :class="selectedId === place.id ? 'text-(--color-on-primary-container)' : ''"
                @click="selectPlace(place)"
              >
                <Icon
                  :name="place.kind === 'saved' ? (place.title === 'Rumah' ? 'home' : 'business') : 'clock'"
                  class="w-[18px] h-[18px] text-(--color-on-surface-variant) shrink-0"
                />
                <div class="flex-1 min-w-0">
                  <h3 class="text-sm font-bold text-(--color-on-surface) truncate">{{ place.title }}</h3>
                  <p class="text-[12.5px] text-(--color-on-surface-variant) truncate">
                    {{ place.kind === 'saved' ? place.address : `Terakhir dipakai ${relativeTime(place.lastUsed)}` }}
                  </p>
                </div>
                <Icon
                  :name="selectedId === place.id ? 'check-circle' : 'bookmark'"
                  class="w-[18px] h-[18px] shrink-0"
                  :class="selectedId === place.id ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
                />
              </button>
            </div>
            <p v-else class="text-[12.5px] text-(--color-on-surface-variant) py-2">
              Belum ada alamat tersimpan. Pilih lokasi lalu klik &ldquo;Simpan Rumah&rdquo; atau &ldquo;Simpan Kantor&rdquo;.
            </p>
            </div>
          </div>
        </div>

      <!--
        Hiasan penutup halaman, khusus BisaBersih.

        Di LUAR kartu lokasi dan selebar layar: ia latar, bukan isi. Ditaruh di
        dalam kartu, pola berulang akan bersaing dengan alamat yang sedang
        dicari orang; dibatasi lebar kartu, ia terbaca sebagai satu blok lagi
        yang harus diperhatikan.
      -->
      <PolaBisaBersih v-if="isBisaBersih" class="-mt-6 flex-1 w-full" :bulat="false" />

      <!-- Clear All confirmation dialog: sibling of the content sheet, not nested
           inside it — otherwise its fixed z-50 overlay inherits the sheet's own
           (lower) stacking context and ends up hit-testing BELOW the header's
           z-30 back button instead of on top of it. Same reasoning applies to
           the fullscreen map picker overlay just below. -->
          <div v-if="showClearAllConfirm" class="fixed inset-0 z-50 flex items-end bg-black/30">
           <div class="w-full bg-(--color-surface-0) border-t-2 border-(--color-outline) rounded-t-[20px] pb-safe:pb-6">
             <div class="px-5 pt-5 pb-2">
               <p class="text-center text-[13px] text-(--color-on-surface-variant)">
                 Hapus semua alamat tersimpan dan riwayat? Tindakan ini tidak dapat dibatalkan.
               </p>
             </div>
             <div class="flex gap-3 px-5 pt-3">
               <button
                 type="button"
                 class="flex-1 py-2.5 rounded-full bg-(--color-surface-container) text-(--color-on-surface) font-semibold text-[14px]"
                 @click="showClearAllConfirm = false"
               >
                 Batal
               </button>
               <button
                 type="button"
                 class="flex-1 py-2.5 rounded-full bg-(--color-error) text-white font-semibold text-[14px]"
                 @click="clearAllPlaces"
               >
                 Hapus
               </button>
             </div>
           </div>
         </div>
  
         <!-- fullscreen Leaflet map picker overlay -->
         <div v-if="mapOpen" class="fixed inset-0 z-50 flex flex-col bg-(--color-surface)">
        <div class="flex items-center gap-3 px-5 py-4 bg-(--color-surface-0) border-b border-(--color-outline)">
          <button type="button" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="closeMap">
            <Icon name="arrow-left" class="w-[19px] h-[19px]" />
          </button>
          <h3 class="text-base font-extrabold flex-1">Pilih di Peta</h3>
        </div>
  
        <div ref="mapEl" :key="mapInstanceKey" class="flex-1 w-full"></div>
  
        <div class="px-5 py-4 bg-(--color-surface-0) border-t border-(--color-outline)">
          <p class="text-center text-xs text-(--color-on-surface-variant) mb-3">Geser peta lalu ketuk pin untuk menandai lokasi tugas</p>
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11"
            @click="confirmMap"
          >
            <Icon name="pin" class="w-[18px] h-[18px]" />Gunakan Lokasi Ini
          </button>
        </div>
      </div>
    </div>
  </template>
</template>

<style>
.driver-pin {
  background: transparent;
  border: none;
}
.driver-pin__img {
  width: 56px;
  height: 40px;
  object-fit: contain;
  filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.22));
}
</style>
