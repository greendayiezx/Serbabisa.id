<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { useKembali } from '@/composables/useKembali'
import { useLocationStore } from '@/stores/location'
import { useTukangStore, KATEGORI_TUKANG } from '@/stores/tukang'
import BisaTukangHeroArt from '@/components/tukang/BisaTukangHeroArt.vue'
import IkonKategoriTukang from '@/components/tukang/IkonKategoriTukang.vue'
import AvatarTukang from '@/components/tukang/AvatarTukang.vue'
import { heroTimeOfDayFromHour } from '@/lib/heroSky'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'

const kembali = useKembali()
const locationStore = useLocationStore()
const tukangStore = useTukangStore()

// Address info from locationStore
const addressLabel = computed(
  () => locationStore.draft?.alamat ?? 'Jl. Sudirman No. 123, Jakarta Pusat',
)
/*
 * Langit hero mengikuti jam nyata, sama seperti hero menu lain. Dibaca sekali
 * saat halaman dibuka: layar ini tidak dibiarkan terbuka semalaman, dan jam
 * yang berdetak sendiri hanya menambah kerja render tanpa ada yang melihat
 * pergantiannya.
 */
const heroTimeOfDay = heroTimeOfDayFromHour(new Date().getHours())

/** Nama tiap langkah, dipakai penanda di kepala halaman. */
const JUDUL_LANGKAH = [
  'Pilih kategori',
  'Jenis masalah',
  'Deskripsi masalah',
  'Estimasi biaya',
  'Pilih jadwal',
  'Mencari tukang',
  'Profil & pelacakan',
  'Tukang tiba',
  'Diagnosis & persetujuan',
  'Pekerjaan berlangsung',
  'Pengujian hasil',
  'Pembayaran',
  'Penilaian',
]

const userLat = computed(() => locationStore.draft?.lat ?? -6.2088)
const userLng = computed(() => locationStore.draft?.lng ?? 106.8456)

// Leaflet Map reference for Live Tracking
const mapEl = ref<HTMLDivElement | null>(null)
let liveMap: L.Map | null = null
let tukangMarker: L.Marker | null = null
let trackingAnimationTimer: ReturnType<typeof setInterval> | null = null
let pengamatPeta: ResizeObserver | null = null

// Penanda peta: titik pelanggan dan posisi tukang.
const pinUserIcon = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="36" height="36" fill="#1e9bf0" stroke="#ffffff" stroke-width="2" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#ffffff"/></svg>`,
  iconSize: [36, 36],
  iconAnchor: [18, 36],
})

/*
 * Kunci inggris digambar sebagai SVG, bukan glyph font.
 *
 * Penanda ini dibangun dari HTML mentah oleh Leaflet, di luar jangkauan
 * komponen ikon aplikasi — dan glyph font di dalamnya bergantung pada berkas
 * font yang mungkin belum termuat saat peta digambar. Yang muncul waktu itu
 * bukan kotak kosong melainkan TULISAN "build" di dalam lingkaran merah.
 */
const pinTukangIcon = L.divIcon({
  className: '',
  html:
    '<div style="background:#1e9bf0; border:2.5px solid #fff; border-radius:50%; width:38px; height:38px; box-shadow:0 5px 14px rgba(30,155,240,0.45); display:flex; align-items:center; justify-content:center;">' +
    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M15.5 3.5a5.5 5.5 0 0 0-4.9 8L4 18.1a2 2 0 0 0 2.8 2.8l6.6-6.6a5.5 5.5 0 0 0 2.1-10.8z"/>' +
    '</svg></div>',
  iconSize: [38, 38],
  iconAnchor: [19, 19],
})

function initLiveMap() {
  if (!mapEl.value) return
  if (liveMap) {
    liveMap.remove()
    liveMap = null
  }

  const center: L.LatLngTuple = [userLat.value, userLng.value]
  liveMap = L.map(mapEl.value, {
    center,
    zoom: 15,
    zoomControl: false,
    attributionControl: false,
  })

  // Ubin dari lib bersama: menu lain memakai Mapbox saat tokennya ada, dan
  // peta yang satu ini menuliskan sendiri alamat CARTO — hasilnya satu peta
  // yang bergaya beda dari semua peta lain di aplikasi.
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(liveMap)

  L.marker(center, { icon: pinUserIcon }).addTo(liveMap)

  // Initial tukang location slightly offset
  const tukangLat = userLat.value + 0.008
  const tukangLng = userLng.value + 0.007
  tukangMarker = L.marker([tukangLat, tukangLng], { icon: pinTukangIcon }).addTo(liveMap)

  // Fit bounds to show both
  const bounds = L.latLngBounds([center, [tukangLat, tukangLng]])
  liveMap.fitBounds(bounds, { padding: [40, 40] })

  /*
   * invalidateSize WAJIB di sini.
   *
   * Peta dibangun tepat saat langkah 7 muncul, dan pada saat itu kotaknya baru
   * saja dipasang — Leaflet mencatat ukurannya nol, lalu menyimpulkan tidak ada
   * satu pun ubin yang perlu diambil. Penandanya tetap tergambar karena
   * posisinya dihitung dari koordinat, jadi yang terlihat adalah peta KELABU
   * dengan dua pin melayang di atasnya: tampak seperti peta yang gagal memuat,
   * padahal tidak ada yang pernah diminta untuk dimuat.
   */
  const amati = new ResizeObserver(() => liveMap?.invalidateSize())
  amati.observe(mapEl.value)
  pengamatPeta?.disconnect()
  pengamatPeta = amati
  requestAnimationFrame(() => liveMap?.invalidateSize())

  // Start smooth animated movement towards user
  if (trackingAnimationTimer) clearInterval(trackingAnimationTimer)
  let step = 0
  trackingAnimationTimer = setInterval(() => {
    step += 1
    if (step > 40) {
      step = 0
    }
    const ratio = step / 40
    const curLat = tukangLat + (userLat.value - tukangLat) * ratio * 0.8
    const curLng = tukangLng + (userLng.value - tukangLng) * ratio * 0.8
    tukangMarker?.setLatLng([curLat, curLng])
  }, 1000)
}

watch(
  () => tukangStore.customerStep,
  (newStep) => {
    if (newStep === 7) {
      nextTick(() => initLiveMap())
    }
  },
)

onMounted(() => {
  if (tukangStore.customerStep === 7) {
    nextTick(() => initLiveMap())
  }
})

onBeforeUnmount(() => {
  if (trackingAnimationTimer) clearInterval(trackingAnimationTimer)
  if (liveMap) {
    liveMap.remove()
    liveMap = null
  }
})

// Matching Animation State (Step 6)
const matchingProgress = ref(0)
const matchingStepDone = ref<boolean[]>([false, false, false])
let matchingTimer: ReturnType<typeof setInterval> | null = null

function startMatchingAnimation() {
  tukangStore.customerStep = 6
  matchingProgress.value = 0
  matchingStepDone.value = [false, false, false]
  if (matchingTimer) clearInterval(matchingTimer)

  matchingTimer = setInterval(() => {
    matchingProgress.value += 10
    if (matchingProgress.value >= 30) matchingStepDone.value[0] = true
    if (matchingProgress.value >= 60) matchingStepDone.value[1] = true
    if (matchingProgress.value >= 90) matchingStepDone.value[2] = true

    if (matchingProgress.value >= 100) {
      if (matchingTimer) clearInterval(matchingTimer)
      setTimeout(() => {
        tukangStore.customerStep = 7 // Move to Step 7 (Profil Tukang & Live Map)
      }, 500)
    }
  }, 300)
}

// Media upload helpers
const fileInput = ref<HTMLInputElement | null>(null)
function triggerUpload() {
  fileInput.value?.click()
}
function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    const file = target.files[0]
    const dummyUrl = URL.createObjectURL(file)
    if (file.type.startsWith('video/')) {
      tukangStore.uploadedVideos.push(dummyUrl)
    } else {
      tukangStore.uploadedPhotos.push(dummyUrl)
    }
  }
}

// Points & Loyalty Modal
const showPointsModal = ref(false)
function submitRating() {
  tukangStore.reviewSubmitted = true
  showPointsModal.value = true
}

// Rupiah Formatter
function formatRupiah(amount: number) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(amount)
}

// Customer Navigation Controls
function prevStep() {
  if (tukangStore.customerStep > 1) {
    tukangStore.customerStep -= 1
  } else {
    kembali()
  }
}

// Tukang App Simulation Actions
function toggleTukangOnline() {
  tukangStore.tukangOnline = !tukangStore.tukangOnline
  if (tukangStore.tukangOnline) {
    tukangStore.tukangStatus = 'Available'
  } else {
    tukangStore.tukangStatus = 'Offline'
  }
}

function acceptIncomingOrder() {
  tukangStore.hasIncomingOrder = false
  tukangStore.tukangStep = 'navigation'
}

function declineIncomingOrder() {
  tukangStore.hasIncomingOrder = false
  tukangStore.tukangStep = 'online'
}

function triggerIncomingOrder() {
  tukangStore.hasIncomingOrder = true
  tukangStore.tukangStep = 'order_request'
  tukangStore.incomingTimer = 30
}
</script>

<template>
  <div
    class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-24"
  >
    <!-- Top Fixed Bar with Header & Mode Switcher -->
    <!--
      Warna diambil dari token aplikasi, bukan palet Tailwind mentah.
      `bg-(--color-surface-0)` memaksa halaman ini punya dua warna sendiri
      yang tidak ikut tema — di sebelah menu lain ia terlihat seperti aplikasi
      yang berbeda, dan setiap penyesuaian tema harus diingat dua kali.
    -->
    <header
      class="sticky top-0 z-50 bg-(--color-surface-0)/90 backdrop-blur-md border-b border-(--color-outline)/12 px-4 py-3"
    >
      <div class="max-w-[430px] mx-auto flex items-center gap-3">
        <button
          type="button"
          aria-label="Kembali"
          class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="prevStep"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <div class="flex-1 min-w-0 text-center">
          <h1 class="text-[16px] font-display font-extrabold leading-tight">BisaTukang</h1>
          <p class="text-[11px] text-(--color-on-surface-variant) truncate">
            {{ addressLabel }}
          </p>
        </div>

        <!-- Pengalih mode: layar pelanggan atau simulasi sisi tukang -->
        <button
          type="button"
          :aria-label="`Beralih ke sisi ${tukangStore.appMode === 'customer' ? 'tukang' : 'pelanggan'}`"
          class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          :class="
            tukangStore.appMode === 'customer'
              ? 'bg-(--color-azure) text-white'
              : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
          "
          @click="
            tukangStore.appMode = tukangStore.appMode === 'customer' ? 'tukang' : 'customer'
          "
        >
          <Icon :name="tukangStore.appMode === 'customer' ? 'user' : 'wrench'" class="w-4.5 h-4.5" />
        </button>
      </div>

      <!-- Penanda langkah -->
      <div v-if="tukangStore.appMode === 'customer'" class="max-w-[430px] mx-auto mt-3">
        <div class="flex items-baseline justify-between gap-3 text-[11px] mb-1.5">
          <span class="font-extrabold text-(--color-azure) tabular-nums">
            Langkah {{ tukangStore.customerStep }}/13
          </span>
          <span class="font-semibold text-(--color-on-surface-variant) truncate">
            {{ JUDUL_LANGKAH[tukangStore.customerStep - 1] }}
          </span>
        </div>
        <div class="w-full bg-(--color-outline)/15 h-1.5 rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-[width] duration-300 bg-gradient-to-r from-(--color-azure) to-(--color-secondary)"
            :style="{ width: `${(tukangStore.customerStep / 13) * 100}%` }"
          ></div>
        </div>
      </div>
    </header>

    <!-- ========================================================================= -->
    <!-- MODE 1: CUSTOMER JOURNEY (13 STEPS)                                      -->
    <!-- ========================================================================= -->
    <main v-if="tukangStore.appMode === 'customer'" class="max-w-[430px] mx-auto px-4 pt-4">
      <!-- STEP 1: Pilih Kategori -->
      <section v-if="tukangStore.customerStep === 1" class="flex flex-col gap-4">
        <!--
          Hero dipakai UTUH, bukan sebagai cap air di pojok.

          Sebelumnya ilustrasinya ditaruh -right-10 -bottom-10 dengan opacity 35%
          di belakang teks: yang terlihat cuma potongan tangga dan sepotong tubuh
          teknisi, terpotong dua sisi. Ilustrasi yang digambar untuk dilihat utuh
          tidak menjadi latar yang baik — ia hanya menambah keruh di belakang
          huruf. Sekarang ia mengisi kartunya, dan teksnya duduk di atas gradasi
          gelap di bagian bawah tempat langitnya memang sudah gelap.
        -->
        <!--
          Tingginya dikunci, ilustrasinya yang dipangkas.

          Hero ini digambar tegak (400x620) untuk halaman lokasi yang memang
          setinggi layar. Dibiarkan mengikuti lebar di sini, kartunya jadi 570
          piksel dan mendorong seluruh pilihan kategori ke luar layar — orang
          harus menggulir sebelum melihat satu pun kategori. preserveAspectRatio
          "slice" di komponennya memangkas kelebihannya dari atas.
        -->
        <div class="relative h-[248px] rounded-3xl overflow-hidden shadow-[0_10px_30px_rgba(10,26,58,0.18)] bg-[#0a1a3a]">
          <BisaTukangHeroArt :time-of-day="heroTimeOfDay" class="absolute inset-0 w-full h-full" />

          <div
            class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-[#050e22] via-[#050e22]/85 to-transparent pointer-events-none"
          ></div>

          <div class="absolute inset-x-5 bottom-5 z-10 text-white">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm ring-1 ring-white/25 font-bold text-[10.5px] uppercase tracking-[0.14em]"
            >
              <Icon name="shield" class="w-3 h-3" />
              Teknisi terverifikasi
            </span>
            <h2 class="text-[20px] leading-tight font-extrabold font-display mt-2">
              Solusi perbaikan rumah &amp; kantor
            </h2>
            <p class="text-[12.5px] leading-snug text-white/80 mt-1">
              Datang ke lokasi, diagnosis dulu, harganya disetujui sebelum dikerjakan.
            </p>
          </div>
        </div>

        <div>
          <h3
            class="text-[15px] font-display font-extrabold mb-3 flex items-center gap-2 text-(--color-on-surface)"
          >
            <span class="w-1 h-4 rounded-full bg-(--color-azure)"></span>
            Pilih kategori layanan
          </h3>

          <div class="grid grid-cols-3 gap-2.5">
            <button
              v-for="k in KATEGORI_TUKANG"
              :key="k.id"
              type="button"
              class="relative flex flex-col items-center justify-center px-2 py-3.5 rounded-2xl border transition-all text-center group"
              :class="
                tukangStore.selectedCategoryId === k.id
                  ? 'bg-(--color-azure) border-(--color-azure) text-white shadow-[0_8px_20px_rgba(30,155,240,0.35)]'
                  : 'bg-(--color-surface-0) border-(--color-outline)/15 text-(--color-on-surface) shadow-xs'
              "
              :aria-pressed="tukangStore.selectedCategoryId === k.id"
              @click="
                tukangStore.setCategory(k.id);
                tukangStore.customerStep = 2;
              "
            >
              <span
                class="w-11 h-11 rounded-2xl flex items-center justify-center mb-2 p-2 transition-transform group-active:scale-95"
                :class="
                  tukangStore.selectedCategoryId === k.id
                    ? 'bg-white/15'
                    : 'bg-(--color-surface-container)'
                "
              >
                <IkonKategoriTukang :id="k.id" :aktif="tukangStore.selectedCategoryId === k.id" />
              </span>
              <span class="text-[11.5px] leading-tight font-bold">{{ k.nama }}</span>
            </button>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-full bg-(--color-azure) text-white font-extrabold text-[14.5px] shadow-[0_8px_20px_rgba(30,155,240,0.35)] active:scale-[0.98] transition-transform flex items-center justify-center gap-2 mt-1"
          @click="tukangStore.customerStep = 2"
        >
          <span>Lanjut pilih masalah</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 2: Pilih Jenis Masalah (Sub-Kategori) -->
      <section v-else-if="tukangStore.customerStep === 2" class="flex flex-col gap-4">
        <div class="bg-(--color-azure)/8 border border-(--color-azure)/25 rounded-2xl p-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-(--color-azure) text-white flex items-center justify-center shrink-0">
            <IkonKategoriTukang :id="tukangStore.currentCategory.id" class="w-7 h-7" />
          </div>
          <div>
            <h3 class="font-bold text-[15px] text-(--color-on-surface)">
              Kategori {{ tukangStore.currentCategory.nama }}
            </h3>
            <p class="text-[12px] text-(--color-on-surface-variant)">
              Pilih jenis masalah spesifik yang dialami:
            </p>
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <button
            v-for="sub in tukangStore.currentCategory.subKategori"
            :key="sub"
            type="button"
            class="w-full p-3.5 rounded-xl border text-left flex items-center justify-between transition-all"
            :class="
              tukangStore.selectedSubCategory === sub
                ? 'bg-(--color-azure) text-white border-(--color-azure) shadow-sm font-semibold'
                : 'bg-(--color-surface-0) border-(--color-outline)/15 text-(--color-on-surface) hover:bg-(--color-surface-container)'
            "
            @click="
              tukangStore.setSubCategory(sub);
              tukangStore.customerStep = 3;
            "
          >
            <span class="text-[13.5px] flex items-center gap-2">
              <Icon name="check-circle" class="w-4.5 h-4.5" />
              {{ sub }}
            </span>
            <Icon name="arrow-right" class="w-4 h-4 opacity-70" />
          </button>
        </div>
      </section>

      <!-- STEP 3: Deskripsi Masalah & Foto/Video -->
      <section v-else-if="tukangStore.customerStep === 3" class="flex flex-col gap-5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-xs">
          <label class="block font-bold text-[14px] text-(--color-on-surface) mb-2 flex items-center gap-1.5">
            <Icon name="edit" class="w-5 h-5 text-(--color-azure)" />
            Jelaskan Masalah Anda:
          </label>
          <textarea
            v-model="tukangStore.deskripsiMasalah"
            rows="3"
            class="w-full p-3 rounded-xl border border-(--color-outline)/30 bg-(--color-surface-container) text-[13px] focus:ring-2 focus:ring-(--color-azure) focus:outline-none"
            placeholder="Contoh: Stop kontak di kamar mandi korslet dan mengeluarkan percikan api..."
          ></textarea>
        </div>

        <!-- Upload Foto / Video -->
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-xs">
          <label class="block font-bold text-[14px] text-(--color-on-surface) mb-2 flex items-center gap-1.5">
            <Icon name="camera" class="w-5 h-5 text-(--color-azure)" />
            Upload Foto / Video Masalah:
          </label>

          <input ref="fileInput" type="file" accept="image/*,video/*" class="hidden" @change="handleFileChange" />

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="flex-1 py-2.5 px-3 rounded-xl border border-dashed border-(--color-azure) bg-(--color-azure)/8 text-(--color-azure) text-[12.5px] font-bold flex items-center justify-center gap-1.5 active:scale-98 transition-all"
              @click="triggerUpload"
            >
              <Icon name="camera" class="w-5 h-5" />
              + Tambah Foto
            </button>

            <button
              type="button"
              class="flex-1 py-2.5 px-3 rounded-xl border border-dashed border-teal-500 bg-(--color-secondary-container) text-(--color-on-secondary-container) text-[12.5px] font-bold flex items-center justify-center gap-1.5 active:scale-98 transition-all"
              @click="triggerUpload"
            >
              <Icon name="camera" class="w-5 h-5" />
              + Tambah Video
            </button>
          </div>

          <!-- Upload Previews -->
          <div v-if="tukangStore.uploadedPhotos.length > 0" class="flex gap-2 mt-3 overflow-x-auto pb-1">
            <div
              v-for="(img, idx) in tukangStore.uploadedPhotos"
              :key="idx"
              class="relative w-16 h-16 rounded-lg overflow-hidden shrink-0 border border-(--color-outline)/30"
            >
              <img :src="img" class="w-full h-full object-cover" />
              <button
                type="button"
                class="absolute top-0.5 right-0.5 w-4 h-4 bg-red-600 text-white rounded-full flex items-center justify-center text-[10px]"
                @click="tukangStore.uploadedPhotos.splice(idx, 1)"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Lokasi Masalah -->
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-xs">
          <label class="block font-bold text-[14px] text-(--color-on-surface) mb-2">
            Lokasi Masalah:
          </label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="loc in ['Dalam rumah', 'Luar rumah', 'Atap', 'Lantai atas', 'Kamar mandi', 'Dapur']"
              :key="loc"
              type="button"
              class="py-2 px-3 rounded-xl border text-[12.5px] font-medium text-left flex items-center gap-2"
              :class="
                tukangStore.lokasiMasalah === loc
                  ? 'bg-(--color-azure) text-white border-(--color-azure) font-bold'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15 text-(--color-on-surface)'
              "
              @click="tukangStore.lokasiMasalah = loc"
            >
              <Icon name="pin" class="w-4 h-4" />
              {{ loc }}
            </button>
          </div>
        </div>

        <!-- Kondisi Akses -->
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-xs">
          <label class="block font-bold text-[14px] text-(--color-on-surface) mb-2">
            Kondisi Akses Kerjaan:
          </label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="acc in ['Mudah dijangkau', 'Perlu tangga', 'Perlu alat khusus', 'Area sempit']"
              :key="acc"
              type="button"
              class="py-2 px-3 rounded-xl border text-[12.5px] font-medium text-left flex items-center gap-2"
              :class="
                tukangStore.kondisiAkses === acc
                  ? 'bg-amber-600 text-white border-amber-600 font-bold'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15 text-(--color-on-surface)'
              "
              @click="tukangStore.kondisiAkses = acc"
            >
              <Icon name="wrench" class="w-4 h-4" />
              {{ acc }}
            </button>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="tukangStore.customerStep = 4"
        >
          <span>Lihat Estimasi Biaya</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 4: Estimasi Harga -->
      <section v-else-if="tukangStore.customerStep === 4" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-sm">
          <div class="flex items-center gap-2 border-b border-(--color-outline)/12 pb-3 mb-4">
            <Icon name="wallet" class="w-7 h-7 text-emerald-600" />
            <h3 class="font-bold text-[16px] text-(--color-on-surface)">
              Estimasi Biaya Transparan
            </h3>
          </div>

          <div class="space-y-3.5 text-[13.5px]">
            <div class="flex justify-between items-center bg-(--color-surface-container) p-3 rounded-xl">
              <span class="text-(--color-on-surface-variant)">Biaya Kunjungan:</span>
              <span class="font-bold text-(--color-on-surface)">
                {{ formatRupiah(tukangStore.currentCategory.biayaKunjunganMin) }} - {{ formatRupiah(tukangStore.currentCategory.biayaKunjunganMax) }}
              </span>
            </div>

            <div class="flex justify-between items-center bg-(--color-surface-container) p-3 rounded-xl">
              <span class="text-(--color-on-surface-variant)">Biaya Perbaikan:*</span>
              <span class="font-bold text-(--color-azure)">
                {{ formatRupiah(tukangStore.currentCategory.biayaPerbaikanMin) }} - {{ formatRupiah(tukangStore.currentCategory.biayaPerbaikanMax) }}
              </span>
            </div>

            <div class="flex justify-between items-center bg-(--color-surface-container) p-3 rounded-xl">
              <span class="text-(--color-on-surface-variant)">Material Tambahan:</span>
              <span class="font-medium text-(--color-on-surface-variant)">Dihitung terpisah sesuai kebutuhan</span>
            </div>

            <div class="flex justify-between items-center bg-(--color-surface-container) p-3 rounded-xl">
              <span class="text-(--color-on-surface-variant)">Waktu Pengerjaan:</span>
              <span class="font-semibold text-amber-600">Estimasi 1-3 jam</span>
            </div>
          </div>

          <div class="mt-4 p-3 rounded-xl bg-amber-400/12 border border-amber-400/35 text-[12px] text-amber-600 flex items-start gap-2">
            <Icon name="info" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <p>
              *Harga final ditentukan setelah diagnosis langsung oleh teknisi. Teknisi wajib mengkonfirmasi persetujuan biaya sebelum memulai pekerjaan.
            </p>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="tukangStore.customerStep = 5"
        >
          <span>Pilih Jadwal Kunjungan</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 5: Pilih Jadwal -->
      <section v-else-if="tukangStore.customerStep === 5" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-sm">
          <h3 class="font-bold text-[15px] text-(--color-on-surface) mb-3 flex items-center gap-2">
            <Icon name="clock" class="w-5 h-5 text-(--color-azure)" />
            Pilih Waktu Kunjungan Teknisi:
          </h3>

          <div class="space-y-2.5">
            <!-- Option 1: Datang secepatnya -->
            <button
              type="button"
              class="w-full p-3.5 rounded-xl border text-left flex items-center justify-between transition-all"
              :class="
                tukangStore.jadwalTipe === 'secepatnya'
                  ? 'bg-(--color-azure) text-white border-(--color-azure) font-bold shadow-xs'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15 text-(--color-on-surface)'
              "
              @click="tukangStore.jadwalTipe = 'secepatnya'"
            >
              <div class="flex items-center gap-2.5">
                <Icon name="sparkle" class="w-6 h-6" />
                <div>
                  <span class="block text-[13.5px]">Datang Secepatnya</span>
                  <span class="block text-[11px] opacity-80">Teknisi tiba dalam 1 - 2 jam</span>
                </div>
              </div>
              <Icon name="chevron-right" class="w-5 h-5" />
            </button>

            <!-- Option 2: Hari ini -->
            <div
              class="p-3.5 rounded-xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'hari_ini'
                  ? 'bg-(--color-azure)/8 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px] text-(--color-on-surface) mb-2"
                @click="tukangStore.jadwalTipe = 'hari_ini'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Hari Ini
                </span>
                <span class="text-xs text-(--color-azure)">Pilih Slot Jam</span>
              </button>

              <div v-if="tukangStore.jadwalTipe === 'hari_ini'" class="grid grid-cols-3 gap-2 mt-2">
                <button
                  v-for="jam in ['14:00-16:00', '16:00-18:00', '18:00-20:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/30 text-(--color-on-surface)'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>

            <!-- Option 3: Besok -->
            <div
              class="p-3.5 rounded-xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'besok'
                  ? 'bg-(--color-azure)/8 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px] text-(--color-on-surface) mb-2"
                @click="tukangStore.jadwalTipe = 'besok'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Besok
                </span>
                <span class="text-xs text-(--color-azure)">Pilih Slot Jam</span>
              </button>

              <div v-if="tukangStore.jadwalTipe === 'besok'" class="grid grid-cols-2 gap-2 mt-2">
                <button
                  v-for="jam in ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/30 text-(--color-on-surface)'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="startMatchingAnimation"
        >
          <span>Konfirmasi Jadwal & Cari Tukang</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 6: Matching Tukang (Radar Animation) -->
      <section v-else-if="tukangStore.customerStep === 6" class="flex flex-col items-center justify-center py-10">
        <div class="relative w-36 h-36 flex items-center justify-center mb-6">
          <div class="absolute inset-0 rounded-full border-4 border-(--color-azure)/20 animate-ping"></div>
          <div class="absolute inset-2 rounded-full border-4 border-teal-500/30 animate-pulse"></div>
          <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-blue-600 to-teal-400 text-white flex items-center justify-center shadow-xl">
            <Icon name="search" class="w-9 h-9 animate-bounce" />
          </div>
        </div>

        <h3 class="font-extrabold text-lg text-(--color-on-surface) mb-1">
          Mencari tukang terdekat…
        </h3>
        <p class="text-xs text-(--color-on-surface-variant) mb-6">Menghubungkan dengan teknisi terverifikasi di area Anda</p>

        <div class="w-full bg-(--color-surface-0) p-4 rounded-2xl border border-(--color-outline)/15 space-y-3">
          <div class="flex items-center gap-3 text-xs font-semibold" :class="matchingStepDone[0] ? 'text-emerald-600' : 'text-(--color-on-surface-variant)'">
            <Icon :name="matchingStepDone[0] ? 'check-circle' : 'clock'" class="w-5 h-5" />
            <span>Menemukan 3 tukang tersedia</span>
          </div>
          <div class="flex items-center gap-3 text-xs font-semibold" :class="matchingStepDone[1] ? 'text-emerald-600' : 'text-(--color-on-surface-variant)'">
            <Icon :name="matchingStepDone[1] ? 'check-circle' : 'clock'" class="w-5 h-5" />
            <span>Memverifikasi ketersediaan jadwal</span>
          </div>
          <div class="flex items-center gap-3 text-xs font-semibold" :class="matchingStepDone[2] ? 'text-emerald-600' : 'text-(--color-on-surface-variant)'">
            <Icon :name="matchingStepDone[2] ? 'check-circle' : 'clock'" class="w-5 h-5" />
            <span>Mengirim permintaan penugasan</span>
          </div>
        </div>
      </section>

      <!-- STEP 7: Profil Tukang & Live Map Tracking -->
      <section v-else-if="tukangStore.customerStep === 7" class="flex flex-col gap-4">
        <!-- Tukang Card -->
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-md">
          <div class="flex items-center gap-3.5 mb-3">
            <AvatarTukang :nama="tukangStore.tukangInfo.nama" class="w-14 h-14 text-[17px] ring-2 ring-(--color-azure)/30 shrink-0" />
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <h3 class="font-extrabold text-[15px] text-(--color-on-surface)">
                  {{ tukangStore.tukangInfo.nama }}
                </h3>
                <span class="text-xs font-bold bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full flex items-center gap-0.5">
                  <Icon name="star" class="w-3.5 h-3.5 inline text-amber-500" /> {{ tukangStore.tukangInfo.rating }} ({{ tukangStore.tukangInfo.jobsCount }})
                </span>
              </div>
              <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">
                Spesialis: {{ tukangStore.tukangInfo.spesialis }}
              </p>

              <!-- Badges -->
              <div class="flex gap-1.5 mt-1.5">
                <span
                  v-for="b in tukangStore.tukangInfo.badges"
                  :key="b"
                  class="text-[10px] font-bold bg-(--color-azure)/10 text-(--color-azure) px-2 py-0.5 rounded-md"
                >
                  {{ b }}
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between p-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/25 text-[12.5px] font-bold text-(--color-azure)">
            <span class="flex items-center gap-1.5">
              <Icon name="crosshair" class="w-5 h-5 text-(--color-azure)" />
              {{ tukangStore.tukangInfo.statusMessage }}
            </span>
            <span class="text-amber-600 font-extrabold">{{ tukangStore.tukangInfo.etaMinutes }} mnt lagi</span>
          </div>

          <!-- Call & Chat Buttons -->
          <div class="grid grid-cols-2 gap-2 mt-3">
            <button
              type="button"
              class="py-2.5 rounded-xl border border-(--color-outline)/30 text-(--color-on-surface) font-bold text-xs flex items-center justify-center gap-1.5 active:scale-98 transition-all"
            >
              <Icon name="phone" class="w-4 h-4 text-emerald-600" />
              Telepon
            </button>

            <button
              type="button"
              class="py-2.5 rounded-xl bg-(--color-azure) text-white font-bold text-xs flex items-center justify-center gap-1.5 active:scale-98 transition-all"
            >
              <Icon name="chat" class="w-4 h-4" />
              Chat Tukang
            </button>
          </div>
        </div>

        <!-- Live Map Tracking Box -->
        <div class="bg-(--color-surface-0) rounded-2xl overflow-hidden border border-(--color-outline)/15 shadow-md">
          <div class="p-3 bg-(--color-surface-container) font-bold text-xs text-(--color-on-surface) flex items-center justify-between border-b">
            <span>Pelacakan langsung teknisi</span>
            <span class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Real-time
            </span>
          </div>

          <div ref="mapEl" class="w-full h-56 z-0"></div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="tukangStore.customerStep = 8"
        >
          <span>Simulasi: Tukang Tiba di Lokasi</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 8: Tukang Tiba -->
      <section v-else-if="tukangStore.customerStep === 8" class="flex flex-col gap-4">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl p-5 text-white shadow-lg text-center">
          <div class="w-14 h-14 rounded-full bg-white/20 text-white flex items-center justify-center mx-auto mb-2 animate-bounce">
            <Icon name="bell" class="w-8 h-8" />
          </div>
          <h3 class="font-extrabold text-lg">Tukang sudah tiba</h3>
          <p class="text-xs text-emerald-100 opacity-90 mt-1">
            Teknisi kami {{ tukangStore.tukangInfo.nama }} sudah berada di depan rumah Anda.
          </p>
        </div>

        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm space-y-3">
          <h4 class="font-bold text-sm text-(--color-on-surface) flex items-center gap-1.5">
            <Icon name="shield" class="w-5 h-5 text-(--color-azure)" />
            Verifikasi Keamanan Teknisi:
          </h4>

          <div class="space-y-2 text-xs font-semibold text-(--color-on-surface)">
            <div class="flex items-center gap-2 p-2.5 rounded-xl bg-(--color-surface-container)">
              <Icon name="check-circle" class="w-4 h-4 text-(--color-on-secondary-container) shrink-0" /> Foto tukang sesuai profil
            </div>
            <div class="flex items-center gap-2 p-2.5 rounded-xl bg-(--color-surface-container)">
              <Icon name="check-circle" class="w-4 h-4 text-(--color-on-secondary-container) shrink-0" /> ID Card resmi terverifikasi
            </div>
            <div class="flex items-center gap-2 p-2.5 rounded-xl bg-(--color-surface-container)">
              <Icon name="check-circle" class="w-4 h-4 text-(--color-on-secondary-container) shrink-0" /> Peralatan kerja lengkap & standar K3
            </div>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all"
          @click="tukangStore.customerStep = 9"
        >
          [Mulai Pekerjaan & Cek Diagnosis]
        </button>
      </section>

      <!-- STEP 9: Diagnosis & Persetujuan -->
      <section v-else-if="tukangStore.customerStep === 9" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-md">
          <div class="flex items-center gap-2 border-b border-(--color-outline)/12 pb-3 mb-3">
            <Icon name="clipboard" class="w-7 h-7 text-amber-500" />
            <div>
              <h3 class="font-extrabold text-[15px] text-(--color-on-surface)">
                Hasil Diagnosis Teknisi
              </h3>
              <p class="text-[11px] text-(--color-on-surface-variant)">Pemeriksaan fisik oleh {{ tukangStore.tukangInfo.nama }}</p>
            </div>
          </div>

          <!-- Problem found -->
          <div class="p-3 rounded-xl bg-amber-400/12 text-amber-600 text-xs mb-3 font-medium">
            <span class="font-bold">Masalah Ditemukan:</span> {{ tukangStore.diagnosisData.masalah }}
          </div>

          <!-- Recommendations list -->
          <div class="mb-4">
            <span class="block text-xs font-bold text-(--color-on-surface) mb-1.5">Rekomendasi Tindakan:</span>
            <ul class="space-y-1 text-xs text-(--color-on-surface-variant) pl-4 list-disc">
              <li v-for="(rec, i) in tukangStore.diagnosisData.rekomendasi" :key="i">{{ rec }}</li>
            </ul>
          </div>

          <!-- Cost Breakdown -->
          <div class="border-t border-(--color-outline)/15 pt-3 space-y-2 text-xs">
            <span class="block font-bold text-(--color-on-surface)">Rincian Biaya:</span>
            <div class="flex justify-between text-(--color-on-surface-variant)">
              <span>Biaya Kunjungan</span>
              <span>{{ formatRupiah(tukangStore.diagnosisData.biayaKunjungan) }}</span>
            </div>
            <div
              v-for="item in tukangStore.diagnosisData.materialItems"
              :key="item.nama"
              class="flex justify-between text-(--color-on-surface-variant)"
            >
              <span>{{ item.nama }}</span>
              <span>{{ formatRupiah(item.harga) }}</span>
            </div>
            <div class="flex justify-between text-(--color-on-surface-variant)">
              <span>Jasa Perbaikan</span>
              <span>{{ formatRupiah(tukangStore.diagnosisData.jasaPerbaikan) }}</span>
            </div>
            <div class="flex justify-between text-sm font-extrabold text-(--color-azure) border-t pt-2 mt-2">
              <span>TOTAL BIAYA</span>
              <span>{{ formatRupiah(tukangStore.subTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- Approval Buttons -->
        <div class="flex flex-col gap-2">
          <button
            type="button"
            class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
            @click="tukangStore.customerStep = 10"
          >
            <span>[Setujui Perbaikan]</span>
          </button>

          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="py-2.5 rounded-xl border border-(--color-outline)/30 text-(--color-on-surface) font-bold text-xs"
            >
              [Minta Penjelasan]
            </button>
            <button
              type="button"
              class="py-2.5 rounded-xl border border-red-300 text-red-600 font-bold text-xs"
            >
              [Tolak Pekerjaan]
            </button>
          </div>
        </div>
      </section>

      <!-- STEP 10: Pekerjaan Berlangsung -->
      <section v-else-if="tukangStore.customerStep === 10" class="flex flex-col gap-4">
        <div class="bg-(--color-azure) rounded-2xl p-5 text-white shadow-lg">
          <div class="flex items-center justify-between mb-2">
            <span class="font-extrabold text-[15px] flex items-center gap-2">
              <Icon name="gauge" class="w-5 h-5 text-amber-300 animate-spin" />
              Pekerjaan Berlangsung
            </span>
            <span class="text-xs bg-white/20 px-2.5 py-1 rounded-full font-bold">45 menit lagi</span>
          </div>
          <p class="text-xs text-white/80 opacity-90">Teknisi sedang melakukan penggantian komponen dan perbaikan.</p>
        </div>

        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm space-y-3">
          <h4 class="font-bold text-sm text-(--color-on-surface)">Live Progress Perbaikan:</h4>

          <div class="space-y-2 text-xs">
            <div
              v-for="item in tukangStore.progressChecklist"
              :key="item.label"
              class="flex items-center gap-2.5 p-2.5 rounded-xl border"
              :class="
                item.done
                  ? 'bg-(--color-secondary-container) border-emerald-200 text-(--color-on-secondary-container) font-bold'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15 text-(--color-on-surface-variant)'
              "
            >
              <Icon
                :name="item.done ? 'check-circle' : 'clock'"
                class="w-5 h-5"
              />
              <span>{{ item.label }}</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-4 pt-2 border-t">
            <button
              type="button"
              class="py-2.5 rounded-xl border border-(--color-azure) text-(--color-azure) font-bold text-xs flex items-center justify-center gap-1.5"
            >
              <Icon name="image" class="w-4 h-4" />
              Foto Progress
            </button>
            <button
              type="button"
              class="py-2.5 rounded-xl bg-(--color-azure) text-white font-bold text-xs flex items-center justify-center gap-1.5"
            >
              <Icon name="chat" class="w-4 h-4" />
              Chat Tukang
            </button>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[14px] shadow-md active:scale-98 transition-all"
          @click="tukangStore.customerStep = 11"
        >
          [Simulasi: Pekerjaan Selesai & Testing]
        </button>
      </section>

      <!-- STEP 11: Testing & Quality Check -->
      <section v-else-if="tukangStore.customerStep === 11" class="flex flex-col gap-4">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl p-5 text-white shadow-md text-center">
          <div class="w-12 h-12 rounded-full bg-white/20 text-white flex items-center justify-center mx-auto mb-2">
            <Icon name="check-circle" class="w-8 h-8" />
          </div>
          <h3 class="font-extrabold text-lg">Pekerjaan selesai</h3>
          <p class="text-xs text-emerald-100 opacity-90 mt-1">Pengujian fungsi & inspeksi mutu telah dilaksanakan.</p>
        </div>

        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm space-y-3">
          <h4 class="font-bold text-xs text-(--color-on-surface) uppercase tracking-wider">Hasil Testing & Quality Check:</h4>
          <div class="space-y-1.5 text-xs">
            <div v-for="t in tukangStore.testingChecklist" :key="t.label" class="flex items-center gap-2 text-emerald-600 font-semibold">
              <Icon name="check" class="w-3.5 h-3.5 shrink-0" /> {{ t.label }}
            </div>
          </div>

          <!--
            Bukti pengerjaan.

            Sebelumnya dua foto Unsplash — ruangan milik orang lain, dipasang
            sebagai "foto pekerjaan di rumah Anda". Foto contoh yang tampak
            seperti bukti bukan penempatan sementara yang aman: yang melihatnya
            tidak punya cara tahu itu bukan rumahnya. Sekarang tempatnya
            disediakan dan disebut apa adanya sampai fotonya benar-benar dikirim
            tukang.
          -->
          <div class="mt-4 pt-3 border-t border-(--color-outline)/15">
            <span class="block text-xs font-bold text-(--color-on-surface) mb-2">
              Foto bukti pengerjaan
            </span>
            <div class="grid grid-cols-2 gap-2">
              <div
                v-for="sisi in [
                  { kunci: 'before', label: 'Sebelum' },
                  { kunci: 'after', label: 'Sesudah' },
                ]"
                :key="sisi.kunci"
                class="relative rounded-xl overflow-hidden border border-(--color-outline)/15 h-28 bg-(--color-surface-container) flex flex-col items-center justify-center gap-1.5"
              >
                <Icon name="camera" class="w-6 h-6 text-(--color-on-surface-variant)" />
                <span class="text-[10.5px] font-semibold text-(--color-on-surface-variant)">
                  Menunggu foto
                </span>
                <span
                  class="absolute bottom-1 left-1 text-[10px] font-bold px-2 py-0.5 rounded bg-(--color-on-surface)/70 text-white"
                >
                  {{ sisi.label }}
                </span>
              </div>
            </div>
          </div>

          <!-- Technician note & Warranty -->
          <div class="mt-3 p-3 rounded-xl bg-(--color-surface-container) border text-xs space-y-1.5">
            <p class="text-(--color-on-surface-variant)"><span class="font-bold">Catatan Tukang:</span> "{{ tukangStore.catatanTukang }}"</p>
            <div class="flex items-center gap-1.5 text-(--color-azure) font-bold">
              <Icon name="shield" class="w-4 h-4" />
              Garansi Resmi {{ tukangStore.garansiHari }} Hari untuk perbaikan ini.
            </div>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="tukangStore.customerStep = 12"
        >
          <span>Selesai & Bayar</span>
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </section>

      <!-- STEP 12: Pembayaran -->
      <section v-else-if="tukangStore.customerStep === 12" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-md">
          <h3 class="font-extrabold text-[16px] text-(--color-on-surface) border-b pb-3 mb-3">
            Ringkasan Pembayaran
          </h3>

          <div class="space-y-2 text-xs text-(--color-on-surface-variant)">
            <div class="flex justify-between">
              <span>Biaya Kunjungan</span>
              <span>{{ formatRupiah(tukangStore.diagnosisData.biayaKunjungan) }}</span>
            </div>
            <div v-for="item in tukangStore.diagnosisData.materialItems" :key="item.nama" class="flex justify-between">
              <span>{{ item.nama }}</span>
              <span>{{ formatRupiah(item.harga) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Jasa Perbaikan</span>
              <span>{{ formatRupiah(tukangStore.diagnosisData.jasaPerbaikan) }}</span>
            </div>
            <div class="flex justify-between font-bold text-(--color-on-surface) border-t pt-2">
              <span>Subtotal</span>
              <span>{{ formatRupiah(tukangStore.subTotal) }}</span>
            </div>
            <div v-if="tukangStore.promoDiscount > 0" class="flex justify-between text-emerald-600 font-bold">
              <span>Promo ({{ tukangStore.promoCode }})</span>
              <span>- {{ formatRupiah(tukangStore.promoDiscount) }}</span>
            </div>
            <div class="flex justify-between text-base font-extrabold text-(--color-azure) border-t pt-2">
              <span>TOTAL BAYAR</span>
              <span>{{ formatRupiah(tukangStore.grandTotal) }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Method Selection -->
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm">
          <label class="block font-bold text-xs text-(--color-on-surface) mb-2 uppercase tracking-wider">
            Pilih Metode Pembayaran:
          </label>
          <div class="space-y-2">
            <button
              v-for="pm in [
                { id: 'gopay', label: 'GoPay / OVO / DANA', ikon: 'wallet' },
                { id: 'transfer', label: 'Transfer Bank (VA)', ikon: 'business' },
                { id: 'cc', label: 'Kartu Kredit / Debit', ikon: 'card' },
                { id: 'cash', label: 'Cash di Tempat', ikon: 'receipt' },
              ]"
              :key="pm.id"
              type="button"
              class="w-full p-3 rounded-xl border text-left flex items-center justify-between text-xs font-semibold"
              :class="
                tukangStore.paymentMethod === pm.id
                  ? 'bg-(--color-azure)/8 border-(--color-azure) text-(--color-azure) font-bold'
                  : 'bg-(--color-surface-container) border-(--color-outline)/15 text-(--color-on-surface)'
              "
              @click="tukangStore.paymentMethod = pm.id as any"
            >
              <span class="flex items-center gap-2">
                <Icon :name="pm.ikon" class="w-5 h-5" />
                {{ pm.label }}
              </span>
              <span
                class="w-4.5 h-4.5 rounded-full border-2 flex items-center justify-center shrink-0"
                :class="tukangStore.paymentMethod === pm.id ? 'border-(--color-azure)' : 'border-(--color-outline)/40'"
              >
                <span v-if="tukangStore.paymentMethod === pm.id" class="w-2 h-2 rounded-full bg-(--color-azure)"></span>
              </span>
            </button>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3.5 rounded-xl bg-(--color-azure) text-white font-bold text-[14px] shadow-md active:scale-98 transition-all flex items-center justify-center gap-2"
          @click="
            tukangStore.isPaid = true;
            tukangStore.customerStep = 13;
          "
        >
          <span>Bayar Sekarang ({{ formatRupiah(tukangStore.grandTotal) }})</span>
        </button>
      </section>

      <!-- STEP 13: Rating & Review -->
      <section v-else-if="tukangStore.customerStep === 13" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-md text-center">
          <div class="w-16 h-16 rounded-full overflow-hidden mx-auto mb-2 border-2 border-amber-400">
            <AvatarTukang :nama="tukangStore.tukangInfo.nama" class="w-full h-full text-[19px]" />
          </div>
          <h3 class="font-extrabold text-base text-(--color-on-surface)">Beri Rating untuk {{ tukangStore.tukangInfo.nama }}</h3>
          <p class="text-xs text-(--color-on-surface-variant) mb-4">Bagaimana pengalaman perbaikan rumah Anda?</p>

          <!-- Star Rating Picker -->
          <div class="flex items-center justify-center gap-2 mb-4">
            <button
              v-for="star in 5"
              :key="star"
              type="button"
              class="text-3xl transition-transform active:scale-125"
              @click="tukangStore.ratingValue = star"
            >
              <Icon name="star" class="w-7 h-7" :class="star <= tukangStore.ratingValue ? 'text-amber-500' : 'text-(--color-outline)/40'" />
            </button>
          </div>

          <!-- Text Comment -->
          <textarea
            v-model="tukangStore.reviewComment"
            rows="3"
            class="w-full p-3 rounded-xl border border-(--color-outline)/30 bg-(--color-surface-container) text-xs focus:ring-2 focus:ring-(--color-azure) focus:outline-none mb-3"
            placeholder="Tulis ulasan Anda tentang tukang ini..."
          ></textarea>

          <!-- Quick Tags -->
          <div class="flex flex-wrap gap-1.5 justify-center mb-4">
            <span
              v-for="tag in ['Profesional', 'Cepat', 'Ramah', 'Harga transparan', 'Hasil rapi']"
              :key="tag"
              class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-(--color-azure)/10 text-(--color-azure) border border-(--color-azure)/25 cursor-pointer"
            >
              {{ tag }}
            </span>
          </div>

          <button
            type="button"
            class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[14px] shadow-md active:scale-98 transition-all"
            @click="submitRating"
          >
            [Submit Rating & Dapatkan BisaPoints]
          </button>
        </div>
      </section>

      <!-- Points Modal Celebration -->
      <div v-if="showPointsModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-(--color-surface-0) rounded-3xl p-6 max-w-xs w-full text-center shadow-2xl animate-scaleIn">
          <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-500 flex items-center justify-center mx-auto mb-3 text-3xl animate-bounce">
            <Icon name="star" class="w-7 h-7 text-amber-500" />
          </div>
          <h3 class="font-extrabold text-lg text-(--color-on-surface)">Selamat!</h3>
          <p class="text-xs text-(--color-on-surface-variant) mt-1">Anda mendapatkan <strong class="text-amber-500">+150 BisaPoints</strong> loyalty atas ulasan layanan BisaTukang!</p>
          <button
            type="button"
            class="w-full py-2.5 rounded-xl bg-(--color-azure) text-white font-bold text-xs mt-4"
            @click="
              showPointsModal = false;
              tukangStore.resetFlow();
            "
          >
            Kembali ke Beranda
          </button>
        </div>
      </div>
    </main>

    <!-- ========================================================================= -->
    <!-- MODE 2: SIMULASI SISI TUKANG (TECHNICIAN APP FLOW)                       -->
    <!-- ========================================================================= -->
    <main v-else class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <!-- Top Banner Status -->
      <div class="bg-slate-800 text-white rounded-2xl p-4 flex items-center justify-between shadow-md">
        <div class="flex items-center gap-3">
          <div class="relative">
            <AvatarTukang :nama="tukangStore.tukangInfo.nama" class="w-12 h-12 text-[15px] ring-2 ring-amber-400" />
            <span
              class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full border-2 border-slate-800"
              :class="tukangStore.tukangOnline ? 'bg-emerald-500' : 'bg-slate-400'"
            ></span>
          </div>
          <div>
            <h3 class="font-bold text-sm">{{ tukangStore.tukangInfo.nama }}</h3>
            <p class="text-[11px] text-(--color-on-surface-variant)">Status: {{ tukangStore.tukangOnline ? 'Go Online (Aktif)' : 'Offline' }}</p>
          </div>
        </div>

        <button
          type="button"
          class="px-3 py-1.5 rounded-xl font-bold text-xs shadow-xs"
          :class="tukangStore.tukangOnline ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white'"
          @click="toggleTukangOnline"
        >
          {{ tukangStore.tukangOnline ? 'Go Offline' : 'Go Online' }}
        </button>
      </div>

      <!-- Trigger order simulation button -->
      <button
        v-if="tukangStore.tukangOnline && !tukangStore.hasIncomingOrder"
        type="button"
        class="w-full py-2.5 rounded-xl bg-amber-500 text-amber-950 font-extrabold text-xs shadow-sm flex items-center justify-center gap-2"
        @click="triggerIncomingOrder"
      >
        <Icon name="bell" class="w-4 h-4" />
        Simulasi: Terima Order Masuk
      </button>

      <!-- Step 2: Incoming Order Request Popup -->
      <div
        v-if="tukangStore.hasIncomingOrder"
        class="bg-(--color-surface-0) rounded-2xl p-5 border-2 border-amber-400 shadow-xl space-y-3"
      >
        <div class="flex justify-between items-center border-b pb-2">
          <span class="font-extrabold text-sm text-(--color-on-surface) flex items-center gap-1.5">
            Permintaan baru
          </span>
          <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">
            {{ tukangStore.incomingTimer }}s
          </span>
        </div>

        <div class="space-y-1.5 text-xs text-(--color-on-surface)">
          <div><strong class="text-(--color-on-surface)">Kategori:</strong> {{ tukangStore.currentCategory.nama }}</div>
          <div><strong class="text-(--color-on-surface)">Masalah:</strong> {{ tukangStore.selectedSubCategory }}</div>
          <div><strong class="text-(--color-on-surface)">Jarak:</strong> {{ tukangStore.tukangInfo.distanceKm }} km dari Anda</div>
          <div><strong class="text-(--color-on-surface)">Estimasi Earnings:</strong> Rp250.000 - Rp400.000</div>
          <div><strong class="text-(--color-on-surface)">Rating Customer:</strong> 4,7</div>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-2">
          <button
            type="button"
            class="py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs shadow-sm"
            @click="acceptIncomingOrder"
          >
            [Accept Order]
          </button>
          <button
            type="button"
            class="py-2.5 rounded-xl bg-(--color-outline)/20 text-(--color-on-surface) font-bold text-xs"
            @click="declineIncomingOrder"
          >
            [Decline]
          </button>
        </div>
      </div>

      <!-- Step 3 & 4: Navigation & Arrival -->
      <div
        v-if="tukangStore.tukangStep === 'navigation'"
        class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm space-y-3"
      >
        <div class="flex items-center justify-between font-bold text-xs">
          <span>Navigasi ke lokasi customer</span>
          <span class="text-emerald-600">2.3 km (25 mnt)</span>
        </div>
        <p class="text-xs text-(--color-on-surface-variant)">{{ addressLabel }}</p>

        <div class="grid grid-cols-2 gap-2">
          <button type="button" class="py-2 rounded-xl border text-xs font-bold">Telepon customer</button>
          <button type="button" class="py-2 rounded-xl border text-xs font-bold">Chat Customer</button>
        </div>

        <button
          type="button"
          class="w-full py-3 rounded-xl bg-(--color-azure) text-white font-bold text-xs"
          @click="tukangStore.tukangStep = 'diagnosis'"
        >
          [I've Arrived & Start Diagnosis]
        </button>
      </div>

      <!-- Step 5: Input Diagnosis Form (Sisi Tukang) -->
      <div
        v-if="tukangStore.tukangStep === 'diagnosis'"
        class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/15 shadow-sm space-y-3"
      >
        <h4 class="font-bold text-xs uppercase tracking-wider text-(--color-on-surface)">
          Form Input Diagnosis & Biaya (Mitra):
        </h4>

        <div>
          <label class="block text-[11px] font-bold text-(--color-on-surface-variant) mb-1">Masalah Ditemukan:</label>
          <input
            v-model="tukangStore.diagnosisData.masalah"
            class="w-full p-2 rounded-lg border text-xs bg-(--color-surface-container)"
          />
        </div>

        <div>
          <label class="block text-[11px] font-bold text-(--color-on-surface-variant) mb-1">Biaya Jasa Perbaikan (IDR):</label>
          <input
            v-model.number="tukangStore.diagnosisData.jasaPerbaikan"
            type="number"
            class="w-full p-2 rounded-lg border text-xs bg-(--color-surface-container)"
          />
        </div>

        <button
          type="button"
          class="w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs"
          @click="tukangStore.tukangStep = 'progress'"
        >
          [Kirim Persetujuan ke Customer]
        </button>
      </div>

      <!-- Earnings Dashboard (Sisi Tukang) -->
      <div class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15 shadow-md">
        <h4 class="font-bold text-xs text-(--color-on-surface-variant) uppercase tracking-wider mb-3">
          Pendapatan (sisi tukang)
        </h4>

        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="p-3 rounded-xl bg-(--color-azure)/8">
            <span class="block text-(--color-on-surface-variant)">Total Hari Ini</span>
            <span class="font-extrabold text-sm text-(--color-azure)">{{ formatRupiah(tukangStore.tukangEarnings.hariIni) }}</span>
          </div>

          <div class="p-3 rounded-xl bg-(--color-secondary-container)">
            <span class="block text-(--color-on-surface-variant)">Total Minggu Ini</span>
            <span class="font-extrabold text-sm text-emerald-600">{{ formatRupiah(tukangStore.tukangEarnings.mingguIni) }}</span>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
