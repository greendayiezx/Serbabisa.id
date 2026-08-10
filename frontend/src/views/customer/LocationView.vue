<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { useLocationStore, type PlaceItem, type SavedPlaceKey, type TaskLocation } from '@/stores/location'
import { useAuthStore } from '@/stores/auth'
import { useDriverStore } from '@/stores/driver'
import { driverPinIcon } from '@/lib/driverIcon'
import cardBorderLocationPagi from '@/assets/card-border-location-pagi.png'
import cardBorderLocationSore from '@/assets/card-border-location-sore.png'
import cardBorderLocationMalam from '@/assets/card-border-location-malam.png'
import cardBorderBisaAngkutPagi from '@/assets/card-border-BisaAngkut-pagi.png'
import cardBorderBisaAngkutSore from '@/assets/card-border-BisaAngkut-sore.png'
import cardBorderBisaAngkutMalam from '@/assets/card-border-BisaAngkut-malam.png'

const router = useRouter()
const route = useRoute()
const locationStore = useLocationStore()
const authStore = useAuthStore()
const driverStore = useDriverStore()

const alamat = ref(locationStore.draft?.alamat ?? '')
const lat = ref(locationStore.draft?.lat ?? 0)
const lng = ref(locationStore.draft?.lng ?? 0)
const selectedPlace = ref<TaskLocation | null>(locationStore.draft ? { ...locationStore.draft } : null)
const inputRef = ref<HTMLInputElement | null>(null)
const locating = ref(false)
const mapOpen = ref(false)
const mapEl = ref<HTMLDivElement | null>(null)
const previewMapEl = ref<HTMLDivElement | null>(null)
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
    seen.add(`${h.lat},${h.lng}`)
    list.push({ id: 'saved:home', title: 'Rumah', address: h.alamat, lat: h.lat, lng: h.lng, lastUsed: null, kind: 'saved' })
  }
  if (savedPlaces.value.office) {
    const o = savedPlaces.value.office
    seen.add(`${o.lat},${o.lng}`)
    list.push({ id: 'saved:office', title: 'Kantor', address: o.alamat, lat: o.lat, lng: o.lng, lastUsed: null, kind: 'saved' })
  }
  for (const r of searchHistory.value) {
    const key = `${r.lat},${r.lng}`
    if (seen.has(key)) continue
    seen.add(key)
    list.push({ id: r.id, title: r.label, address: r.address, lat: r.lat, lng: r.lng, lastUsed: r.last_used_at, kind: 'recent' })
  }
  for (const r of locationStore.recent) {
    const key = `${r.lat},${r.lng}`
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

const timeOfDay = computed<TimeOfDay>(() => {
  const hour = clock.value.getHours()
  if (hour >= 4 && hour < 15) return 'pagi'
  if (hour >= 15 && hour < 18) return 'sore'
  return 'malam'
})

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

const cardBorderLocationImg = computed(() =>
  route.query.category === 'bisaangkut'
    ? bisaAngkutHeaderImages[timeOfDay.value]
    : headerBorderImages[timeOfDay.value],
)

const firstName = computed(() => authStore.user?.name?.split(' ')[0] ?? '')

function coordsLabel(latv: number, lngv: number) {
  return `Lokasi pada ${latv.toFixed(5)}, ${lngv.toFixed(5)}`
}

async function reverseGeocode(latv: number, lngv: number) {
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latv}&lon=${lngv}`)
    const data = await res.json()
    return typeof data.display_name === 'string' ? data.display_name : coordsLabel(latv, lngv)
  } catch {
    return coordsLabel(latv, lngv)
  }
}

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
    if (MAPBOX_TOKEN) {
      const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${MAPBOX_TOKEN}&autocomplete=true&limit=6&language=id&country=id`
      const res = await fetch(url)
      const data = await res.json()
      searchResults.value = Array.isArray(data.features)
        ? data.features.map((f: { place_name: string; center: [number, number] }) => ({
            label: f.place_name,
            lat: f.center[1],
            lng: f.center[0],
          }))
        : []
    } else {
      const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&countrycodes=id&limit=6`
      const res = await fetch(url)
      const data = await res.json()
      searchResults.value = Array.isArray(data)
        ? data.map((d: { display_name: string; lat: string; lon: string }) => ({
            label: d.display_name,
            lat: parseFloat(d.lat),
            lng: parseFloat(d.lon),
          }))
        : []
    }
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
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(previewMap)
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

async function openMap() {
  mapOpen.value = true
  await nextTick()
  initMap()
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

  marker = L.marker(center, { icon: pinIcon, draggable: true }).addTo(map)
  mapDriverLayer = addDriversToMap(map)

  map.on('click', (e: L.LeafletMouseEvent) => {
    marker?.setLatLng(e.latlng)
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

// router.back() silently does nothing when this page has no in-app history to
// return to (e.g. opened directly via URL or after a hard refresh) — fall back
// to a known route so the button always does something.
function goBackOrHome() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push({ name: 'home' })
  }
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
  await nextTick()
  initPreviewMap()
})

onBeforeUnmount(() => {
  stopClock()
  closeMap()
  closePreviewMap()
})
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-6 overflow-x-hidden">
    <!-- Full-Width Header: card-border-location illustration, shown in full (no crop) -->
    <div class="relative w-full overflow-hidden bg-[#155b0e]">
      <div class="relative w-full">
        <img :src="cardBorderLocationImg" alt="Tugasin" class="w-full h-auto block" />

        <div class="absolute inset-x-0 bottom-0 h-[22%] bg-gradient-to-t from-black/55 to-transparent pointer-events-none"></div>

        <!-- Floating Back Button (White circle, Gojek style) -->
        <button
          type="button"
          class="absolute top-4 left-4 z-30 w-10 h-10 rounded-full bg-white text-slate-800 flex items-center justify-center shadow-md transition-transform active:scale-95"
          @click="goBackOrHome"
        >
          <Icon name="arrow-left" class="w-5 h-5 text-slate-800" />
        </button>

        <!-- Greeting & Subtitle, anchored right in the green band at the bottom of the artwork -->
        <div class="absolute inset-x-5 bottom-0 z-10 flex flex-col items-center justify-center text-center text-white">
          <h1 class="font-display font-extrabold text-[13px] sm:text-[15px] leading-tight text-white text-center drop-shadow-md">
            {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
          </h1>
          <p class="text-white/95 text-[10px] sm:text-[11px] font-semibold text-center mt-0.5 drop-shadow-sm">
            Mau anter tugas ke mana hari ini?
          </p>
        </div>
      </div>
    </div>

    <!-- Floating White Content Sheet (lowered so the green hill text stays clearly visible) -->
    <div class="px-4 mt-0 relative z-20">
      <div class="bg-(--color-surface-0) rounded-3xl shadow-(--shadow-float) p-4 flex flex-col gap-4">
        <!-- map preview: real Leaflet map, centered on the device's live GPS position -->
        <button
          type="button"
          class="relative w-full h-40 rounded-2xl overflow-hidden bg-(--color-surface-container) text-left shadow-inner"
          @click="openMap"
        >
          <div ref="previewMapEl" class="absolute inset-0 pointer-events-none"></div>
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
              <span class="text-[13px] text-(--color-on-surface) leading-snug">{{ result.label }}</span>
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
            class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-full text-[12.5px] font-bold text-(--color-on-surface)"
            :class="savedPlaces.home ? 'bg-(--color-primary-container)' : 'bg-(--color-surface-container)'"
            @click="handleQuickSave('home')"
          >
            <Icon name="home" class="w-4 h-4 text-(--color-on-primary-container)" />
            {{ savedPlaces.home ? 'Rumah' : 'Simpan Rumah' }}
          </button>
          <button
            type="button"
            class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-full text-[12.5px] font-bold text-(--color-on-surface)"
            :class="savedPlaces.office ? 'bg-(--color-primary-container)' : 'bg-(--color-surface-container)'"
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
          <div class="text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1">Tersimpan / Baru-Baru Ini</div>
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

    <!-- fullscreen Leaflet map picker overlay -->
    <div v-if="mapOpen" class="fixed inset-0 z-50 flex flex-col bg-(--color-surface)">
      <div class="flex items-center gap-3 px-5 py-4 bg-(--color-surface-0) border-b border-(--color-outline)">
        <button type="button" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="closeMap">
          <Icon name="arrow-left" class="w-[19px] h-[19px]" />
        </button>
        <h3 class="text-base font-extrabold flex-1">Pilih di Peta</h3>
      </div>

      <div ref="mapEl" class="flex-1 w-full"></div>

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
