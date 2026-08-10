<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { useLocationStore } from '@/stores/location'
import { useAngkutDraftStore } from '@/stores/angkutDraft'
import { useAuthStore } from '@/stores/auth'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'

const router = useRouter()
const locationStore = useLocationStore()
const angkutDraftStore = useAngkutDraftStore()
const authStore = useAuthStore()

const draft = computed(() => angkutDraftStore.draft)
const alamat = computed(() => locationStore.draft?.alamat ?? '')
const alamatTitle = computed(() => alamat.value.split(',')[0] ?? alamat.value)
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)

const patokan = ref('')
const namaPenerima = ref('')
const teleponPenerima = ref('')
const submitting = ref(false)

const jadwalLabel = computed(() => {
  if (!draft.value?.tanggal) return ''
  const d = new Date(`${draft.value.tanggal}T00:00:00`)
  const tanggalFmt = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return draft.value.waktu ? `${tanggalFmt}, ${draft.value.waktu} WIB` : tanggalFmt
})

const pinIcon = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 40],
})

const mapEl = ref<HTMLDivElement | null>(null)
let map: L.Map | null = null
let mapMarker: L.Marker | null = null
let resizeObserver: ResizeObserver | null = null

function initMap() {
  if (!mapEl.value) return
  map = L.map(mapEl.value, {
    center: [lat.value, lng.value],
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
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(map)
  mapMarker = L.marker([lat.value, lng.value], { icon: pinIcon }).addTo(map)

  resizeObserver = new ResizeObserver(() => map?.invalidateSize())
  resizeObserver.observe(mapEl.value)
  requestAnimationFrame(() => map?.invalidateSize())
}

function goBackOrHome() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push({ name: 'home' })
  }
}

// Editing the pin stays on this page (a fullscreen picker overlay), instead of
// navigating back to the separate location-picking route.
const pickerOpen = ref(false)
// Bumped every time the picker opens; bound to :key on the map div so Vue always
// throws away the old DOM node and mounts a brand new one — no element, and
// therefore no Leaflet instance, can ever be reused or left behind.
const pickerInstanceKey = ref(0)
const pickerMapEl = ref<HTMLDivElement | null>(null)
let pickerMap: L.Map | null = null
let pickerMarker: L.Marker | null = null

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

// Guards against Leaflet leaving a stale map bound to a DOM node (e.g. after HMR
// swaps this component, or the picker being opened twice before it's closed).
function resetStaleLeafletContainer(el: HTMLElement) {
  if ((el as unknown as { _leaflet_id?: number })._leaflet_id) {
    el.innerHTML = ''
    delete (el as unknown as { _leaflet_id?: number })._leaflet_id
  }
}

// Re-entry guard: openPicker() awaits nextTick() before touching the DOM, so a
// second call fired before the first one resumes (e.g. a double tap) must not
// race past this point and create two map instances in the same container.
let openingPicker = false

async function openPicker() {
  if (openingPicker) return
  openingPicker = true
  try {
    // Force a brand new DOM node for the map (old one, and anything Leaflet
    // attached to it, gets fully destroyed by Vue — nothing to leak or reuse).
    pickerInstanceKey.value += 1
    pickerOpen.value = true
    if (pickerMap) {
      pickerMap.remove()
      pickerMap = null
      pickerMarker = null
    }
    await nextTick()
    if (!pickerMapEl.value) return
    resetStaleLeafletContainer(pickerMapEl.value)

    const center: L.LatLngTuple = [lat.value, lng.value]
    const thisMap = L.map(pickerMapEl.value, {
      center,
      zoom: 16,
      zoomControl: false,
      attributionControl: true,
    })
    pickerMap = thisMap
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(thisMap)
    // autoPan makes Leaflet itself pan the map when the dragged pin nears/exits
    // the visible edge — it stays put while the pin is still comfortably in view.
    pickerMarker = L.marker(center, { icon: pinIcon, draggable: true, autoPan: true }).addTo(thisMap)

    thisMap.on('click', (e: L.LeafletMouseEvent) => {
      pickerMarker?.setLatLng(e.latlng)
      thisMap.panTo(e.latlng, { animate: true, duration: 0.3 })
    })

    // The flex layout can take a frame or two to settle into its final size,
    // so re-measure a few times instead of trusting a single early snapshot.
    requestAnimationFrame(() => thisMap.invalidateSize())
    setTimeout(() => thisMap.invalidateSize(), 150)
    setTimeout(() => thisMap.invalidateSize(), 400)
  } finally {
    openingPicker = false
  }
}

function closePicker() {
  pickerOpen.value = false
  if (pickerMap) {
    pickerMap.remove()
    pickerMap = null
    pickerMarker = null
  }
}

async function confirmPicker() {
  if (!pickerMarker) return
  const ll = pickerMarker.getLatLng()
  const newAlamat = await reverseGeocode(ll.lat, ll.lng)
  locationStore.setDraft({ alamat: newAlamat, lat: ll.lat, lng: ll.lng })
  map?.setView(ll, map.getZoom())
  mapMarker?.setLatLng(ll)
  closePicker()
}

interface CountryOption {
  code: string
  iso: string
  name: string
}

function flagUrl(iso: string) {
  return `https://flagcdn.com/w40/${iso}.png`
}

const countries: CountryOption[] = [
  { code: '+62', iso: 'id', name: 'Indonesia' },
  { code: '+60', iso: 'my', name: 'Malaysia' },
  { code: '+65', iso: 'sg', name: 'Singapura' },
  { code: '+61', iso: 'au', name: 'Australia' },
  { code: '+81', iso: 'jp', name: 'Jepang' },
  { code: '+1', iso: 'us', name: 'Amerika Serikat' },
  { code: '+44', iso: 'gb', name: 'Inggris' },
  { code: '+66', iso: 'th', name: 'Thailand' },
  { code: '+84', iso: 'vn', name: 'Vietnam' },
  { code: '+63', iso: 'ph', name: 'Filipina' },
  { code: '+82', iso: 'kr', name: 'Korea Selatan' },
  { code: '+86', iso: 'cn', name: 'Tiongkok' },
  { code: '+91', iso: 'in', name: 'India' },
  { code: '+966', iso: 'sa', name: 'Arab Saudi' },
]

const selectedCountry = ref<CountryOption>(countries[0])
const countryDropdownOpen = ref(false)
const countryDropdownRef = ref<HTMLDivElement | null>(null)
const alamatSaved = ref(false)
let alamatSavedTimer: ReturnType<typeof setTimeout> | null = null

function selectCountry(country: CountryOption) {
  selectedCountry.value = country
  countryDropdownOpen.value = false
}

function handleSaveAddress() {
  if (locationStore.draft) {
    locationStore.savePlace('home', locationStore.draft)
    locationStore.addSearchHistory(locationStore.draft)
  } else if (alamat.value) {
    const newLoc = { alamat: alamat.value, lat: lat.value, lng: lng.value }
    locationStore.savePlace('home', newLoc)
    locationStore.addSearchHistory(newLoc)
  }
  alamatSaved.value = true
  if (alamatSavedTimer) clearTimeout(alamatSavedTimer)
  alamatSavedTimer = setTimeout(() => {
    alamatSaved.value = false
    alamatSavedTimer = null
  }, 2500)
}

function handleOutsideClick(e: MouseEvent) {
  if (countryDropdownRef.value && !countryDropdownRef.value.contains(e.target as Node)) {
    countryDropdownOpen.value = false
  }
}

function pakaiDetailSaya() {
  namaPenerima.value = authStore.user?.name ?? ''
  const phone = authStore.user?.phone ?? ''
  let matched = false
  for (const c of countries) {
    if (phone.startsWith(c.code)) {
      selectedCountry.value = c
      teleponPenerima.value = phone.slice(c.code.length)
      matched = true
      break
    }
  }
  if (!matched) {
    teleponPenerima.value = phone.replace(/^\+?62/, '')
  }
}

const canConfirm = computed(() => namaPenerima.value.trim() !== '' && teleponPenerima.value.trim() !== '')

function konfirmasiPesanan() {
  if (!canConfirm.value || submitting.value) return
  submitting.value = true
  // Backend untuk submit pesanan BisaAngkut belum tersedia — alur ini masih
  // sebatas UI. Bersihkan draft lalu kembali ke Home.
  locationStore.clearDraft()
  angkutDraftStore.clearDraft()
  router.push({ name: 'home' })
}

onMounted(async () => {
  document.addEventListener('click', handleOutsideClick)
  await nextTick()
  initMap()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  if (alamatSavedTimer) clearTimeout(alamatSavedTimer)
  resizeObserver?.disconnect()
  if (map) {
    map.remove()
    map = null
  }
  closePicker()
})
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-28">
    <!-- Header -->
    <div class="flex items-center gap-3 px-5 pt-5 pb-2">
      <button
        type="button"
        class="w-10 h-10 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>
      <h1 class="font-extrabold text-[16px] text-(--color-on-surface)">Konfirmasi Pesanan</h1>
    </div>

    <div class="px-5 pt-4 flex flex-col gap-5">
      <!-- Map + location -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) shadow-(--shadow-lift) border border-(--color-outline)/40 overflow-hidden">
        <button type="button" class="block w-full text-left" @click="openPicker">
          <div ref="mapEl" class="w-full h-40 bg-(--color-surface-container) pointer-events-none"></div>
        </button>
        <div class="p-4 flex flex-col gap-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="text-sm font-bold text-(--color-on-surface) truncate">{{ alamatTitle || 'Lokasi belum dipilih' }}</h3>
              <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5 line-clamp-2">{{ alamat }}</p>
            </div>
            <button type="button" class="shrink-0 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) text-[12px] font-bold px-3.5 py-1.5" @click="openPicker">
              Edit
            </button>
          </div>
          <div class="flex items-center gap-2 rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3">
            <Icon name="pin" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
            <input
              v-model="patokan"
              placeholder="Ada patokan terdekat? (opsional)"
              class="w-full bg-transparent text-sm outline-none placeholder:text-(--color-on-surface-variant)"
            />
          </div>
        </div>
      </section>

      <!-- Recipient details -->
      <section class="flex flex-col gap-3.5">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-(--color-on-surface)">Detail Penerima</h2>
          <button type="button" class="text-[12.5px] font-bold text-(--color-azure)" @click="pakaiDetailSaya">Pakai detail saya</button>
        </div>

        <div>
          <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5">Nama penerima*</label>
          <input
            v-model="namaPenerima"
            placeholder="Masukkan nama penerima..."
            class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none placeholder:text-(--color-on-surface-variant)"
          />
        </div>

        <div>
          <label class="block text-sm font-bold text-(--color-on-surface) mb-2">
            Nomor telepon<span class="text-red-500">*</span>
          </label>
          <div class="flex items-center gap-3">
            <!-- Country code selector pill button -->
            <div ref="countryDropdownRef" class="relative shrink-0">
              <button
                type="button"
                class="flex items-center gap-1.5 rounded-full border border-(--color-outline)/40 bg-(--color-surface-0) px-3.5 py-1.5 text-sm font-bold text-(--color-on-surface) shadow-xs hover:bg-(--color-surface-container) transition-colors"
                @click.stop="countryDropdownOpen = !countryDropdownOpen"
              >
                <img :src="flagUrl(selectedCountry.iso)" :alt="selectedCountry.name" class="w-5 h-3.5 rounded-[3px] object-cover shrink-0" />
                <span>{{ selectedCountry.code }}</span>
                <Icon name="chevron-down" class="w-3.5 h-3.5 text-(--color-on-surface-variant) transition-transform" :class="{ 'rotate-180': countryDropdownOpen }" />
              </button>

              <!-- Country dropdown menu -->
              <div
                v-if="countryDropdownOpen"
                class="absolute left-0 top-full mt-1.5 z-40 w-56 rounded-2xl bg-(--color-surface-0) shadow-xl border border-(--color-outline)/40 py-1.5 max-h-60 overflow-y-auto"
              >
                <button
                  v-for="country in countries"
                  :key="country.code + country.name"
                  type="button"
                  class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold text-left text-(--color-on-surface) hover:bg-(--color-surface-container) transition-colors"
                  :class="{ 'bg-(--color-primary-container)/40 font-bold': country.code === selectedCountry.code }"
                  @click="selectCountry(country)"
                >
                  <img :src="flagUrl(country.iso)" :alt="country.name" class="w-5 h-3.5 rounded-[3px] object-cover shrink-0" />
                  <span class="font-bold min-w-10">{{ country.code }}</span>
                  <span class="truncate text-(--color-on-surface-variant)">{{ country.name }}</span>
                </button>
              </div>
            </div>

            <!-- Phone input field -->
            <div class="flex-1 border-b border-(--color-outline)/40 transition-colors">
              <input
                v-model="teleponPenerima"
                type="tel"
                placeholder="Masukkan nomor telepon"
                class="w-full bg-transparent text-sm font-medium text-(--color-on-surface) outline-none placeholder:text-(--color-on-surface-variant)/70 py-1 px-1"
              />
            </div>
          </div>
        </div>

        <!-- Simpan alamat? section -->
        <div class="flex items-center justify-between gap-3 flex-wrap pt-3 mt-1">
          <div class="flex items-center gap-3 text-(--color-on-surface) min-w-0">
            <svg viewBox="0 0 24 24" width="22" height="22" class="fill-current text-(--color-on-surface) shrink-0">
              <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
            </svg>
            <span class="font-extrabold text-[15px] text-(--color-on-surface) truncate">Simpan alamat?</span>
          </div>
          <button
            type="button"
            class="shrink-0 min-h-11 rounded-full px-6 text-sm font-extrabold transition-all active:scale-95 shadow-xs"
            :class="alamatSaved ? 'bg-(--color-primary-container) text-(--color-on-primary-container)' : 'bg-(--color-azure) text-white hover:opacity-90'"
            @click="handleSaveAddress"
          >
            {{ alamatSaved ? 'Tersimpan ✓' : 'Simpan' }}
          </button>
        </div>
      </section>

      <!-- Service summary -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) shadow-(--shadow-lift) border border-(--color-outline)/40 p-4">
        <h2 class="text-sm font-bold text-(--color-on-surface) mb-3.5">Ringkasan Layanan</h2>
        <div class="flex items-start gap-3.5">
          <span class="w-11 h-11 flex items-center justify-center shrink-0">
            <img v-if="draft?.vehicleImage" :src="draft.vehicleImage" alt="" class="w-full h-full object-contain" />
            <Icon v-else name="truck" class="w-6 h-6 text-(--color-on-surface)" />
          </span>
          <div class="min-w-0">
            <h3 class="text-sm font-bold text-(--color-on-surface)">BisaAngkut</h3>
            <p class="text-[12.5px] text-(--color-on-surface-variant) mt-0.5">Kendaraan: {{ draft?.vehicleLabel }} &middot; {{ draft?.deliveryLabel }}</p>
            <div v-if="jadwalLabel" class="flex items-center gap-1.5 mt-1.5 text-(--color-on-surface-variant)">
              <Icon name="clock" class="w-3.5 h-3.5" />
              <span class="text-[12px]">{{ jadwalLabel }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Sticky footer -->
    <div class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0) border-t border-(--color-outline)/40 px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11 shadow-(--shadow-lift) disabled:opacity-50"
        :disabled="!canConfirm || submitting"
        @click="konfirmasiPesanan"
      >
        <Icon name="check-circle" class="w-[18px] h-[18px]" />
        Konfirmasi Pesanan
      </button>
    </div>

    <!-- Fullscreen pin picker, stays on this page instead of navigating away -->
    <div v-if="pickerOpen" class="fixed inset-0 z-50 flex flex-col bg-(--color-surface)">
      <div class="flex items-center gap-3 px-5 py-4 bg-(--color-surface-0) border-b border-(--color-outline)">
        <button type="button" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="closePicker">
          <Icon name="arrow-left" class="w-[19px] h-[19px]" />
        </button>
        <h3 class="text-base font-extrabold flex-1">Pilih di Peta</h3>
      </div>

      <div ref="pickerMapEl" class="flex-1 w-full"></div>

      <div class="px-5 py-4 bg-(--color-surface-0) border-t border-(--color-outline)">
        <p class="text-center text-xs text-(--color-on-surface-variant) mb-3">Geser peta lalu ketuk pin untuk menandai lokasi</p>
        <button
          type="button"
          class="w-full flex items-center justify-center gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11"
          @click="confirmPicker"
        >
          <Icon name="pin" class="w-[18px] h-[18px]" />Gunakan Lokasi Ini
        </button>
      </div>
    </div>
  </div>
</template>
