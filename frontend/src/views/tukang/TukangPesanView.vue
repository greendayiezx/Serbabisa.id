<script setup lang="ts">
/**
 * BisaTukang — alur pelanggan, dirampingkan mengikuti model Kanggo.
 *
 * Enam fase saja, masing-masing satu layar yang fokus: MASALAH → JADWAL →
 * CARI TUKANG → PENAWARAN → PENGERJAAN → SELESAI. Intinya sama dengan Kanggo:
 * tukang survei dulu, harganya disepakati sebelum dikerjakan, dan pekerjaannya
 * bergaransi. Tidak ada yang ditagih sebelum penawaran disetujui.
 *
 * Simulasi sisi tukang sengaja tidak ada di layar ini — yang dilihat pelanggan
 * cukup sisinya sendiri. Tombol bertanda "Simulasi" hanya memajukan keadaan
 * yang di produksi datang dari server; ditandai jujur supaya tidak terbaca
 * sebagai aksi sungguhan.
 */
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { RouterLink } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import { useLocationStore } from '@/stores/location'
import {
  useTukangStore,
  KATEGORI_TUKANG,
  MODEL_KERJA,
  PENYEDIAAN_MATERIAL,
  TIPE_PROPERTI,
} from '@/stores/tukang'
import IkonKategoriTukang from '@/components/tukang/IkonKategoriTukang.vue'
import AvatarTukang from '@/components/tukang/AvatarTukang.vue'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'

const kembali = useKembali()
const locationStore = useLocationStore()
const tukangStore = useTukangStore()

const addressLabel = computed(
  () => locationStore.draft?.alamat ?? 'Jl. Sudirman No. 123, Jakarta Pusat',
)


const userLat = computed(() => locationStore.draft?.lat ?? -6.2088)
const userLng = computed(() => locationStore.draft?.lng ?? 106.8456)

/* ────────── Fase ──────────
 * customerStep dipakai ulang sebagai nomor fase 1–6. Keadaan lama yang
 * tertinggal di luar rentang itu (mis. 9 dari versi 13 langkah) dikembalikan
 * ke awal, bukan dibiarkan menggambar layar yang tidak ada lagi.
 */
const FASE = [
  { judul: 'Masalah', ikon: 'wrench' },
  { judul: 'Jadwal', ikon: 'calendar' },
  { judul: 'Cari tukang', ikon: 'search' },
  { judul: 'Penawaran', ikon: 'clipboard' },
  { judul: 'Pengerjaan', ikon: 'gauge' },
  { judul: 'Selesai', ikon: 'check-circle' },
]
const fase = computed(() => tukangStore.customerStep)

/**
 * Tiga langkah pemesanan yang ditunjukkan di kepala.
 *
 * Langkah ketiga belum bisa dibuka dari formulir — estimasinya baru ada
 * setelah tukang memeriksa. Ditampilkan justru supaya itu terbaca: yang
 * diisi sekarang bukan harga, melainkan bahan untuk menghitungnya.
 */
const LANGKAH_PESAN = ['Detail masalah', 'Jadwal & lokasi', 'Estimasi RAB']

/** Batas foto; empat kotak selalu tergambar supaya barisnya tidak berubah tinggi. */
const MAKS_FOTO = 5
const KOTAK_FOTO = 4

const jumlahFoto = computed(() => tukangStore.uploadedPhotos.length)
const bolehTambahFoto = computed(() => jumlahFoto.value < MAKS_FOTO)

/** Kotak kosong sisa, hanya untuk mengisi baris — bukan tombol. */
const kotakKosong = computed(() =>
  Math.max(0, KOTAK_FOTO - jumlahFoto.value - (bolehTambahFoto.value ? 1 : 0)),
)

/** Sub-keadaan fase 3 (cari → menuju → tiba) dan fase 6 (bayar → nilai). */
const lacakSub = ref<'cari' | 'menuju' | 'tiba'>('cari')
const selesaiSub = ref<'bayar' | 'nilai'>('bayar')

function formatRupiah(amount: number) {
  return 'Rp' + Math.round(amount).toLocaleString('id-ID')
}

function keFase(n: number) {
  tukangStore.customerStep = n
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function prevStep() {
  const s = tukangStore.customerStep
  if (s === 3 && lacakSub.value === 'tiba') {
    lacakSub.value = 'menuju'
    return
  }
  if (s === 6 && selesaiSub.value === 'nilai') {
    selesaiSub.value = 'bayar'
    return
  }
  if (s > 1) keFase(s - 1)
  else kembali()
}

/* ────────── Fase 1: kelengkapan ────────── */
const boleh1 = computed(
  () =>
    !!tukangStore.selectedCategoryId &&
    !!tukangStore.selectedSubCategory &&
    tukangStore.deskripsiMasalah.trim().length >= 8,
)

/* ────────── Fase 3: pencocokan ────────── */
const matchingStepDone = ref<boolean[]>([false, false, false])
let matchingTimer: ReturnType<typeof setInterval> | null = null

function mulaiCari() {
  keFase(3)
  lacakSub.value = 'cari'
  matchingStepDone.value = [false, false, false]
  if (matchingTimer) clearInterval(matchingTimer)

  let progress = 0
  matchingTimer = setInterval(() => {
    progress += 10
    if (progress >= 30) matchingStepDone.value[0] = true
    if (progress >= 60) matchingStepDone.value[1] = true
    if (progress >= 90) matchingStepDone.value[2] = true
    if (progress >= 100) {
      if (matchingTimer) clearInterval(matchingTimer)
      setTimeout(() => (lacakSub.value = 'menuju'), 400)
    }
  }, 260)
}

/* ────────── Peta pelacakan ────────── */
const mapEl = ref<HTMLDivElement | null>(null)
let liveMap: L.Map | null = null
let tukangMarker: L.Marker | null = null
let trackingTimer: ReturnType<typeof setInterval> | null = null
let pengamatPeta: ResizeObserver | null = null

const pinUserIcon = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="36" height="36" fill="#1e9bf0" stroke="#ffffff" stroke-width="2" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#ffffff"/></svg>`,
  iconSize: [36, 36],
  iconAnchor: [18, 36],
})

/*
 * Kunci inggris digambar sebagai SVG, bukan glyph font: penanda ini dibangun
 * dari HTML mentah oleh Leaflet, di luar jangkauan komponen ikon aplikasi, dan
 * glyph font yang belum termuat muncul sebagai tulisan mentah di dalam pin.
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
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(liveMap)
  L.marker(center, { icon: pinUserIcon }).addTo(liveMap)

  const tukangLat = userLat.value + 0.008
  const tukangLng = userLng.value + 0.007
  tukangMarker = L.marker([tukangLat, tukangLng], { icon: pinTukangIcon }).addTo(liveMap)
  liveMap.fitBounds(L.latLngBounds([center, [tukangLat, tukangLng]]), { padding: [40, 40] })

  /*
   * invalidateSize wajib: peta dibangun tepat saat kotaknya baru dipasang dan
   * Leaflet mencatat ukurannya nol, lalu menyimpulkan tak ada ubin yang perlu
   * diambil — yang tampak adalah peta kelabu dengan pin melayang di atasnya.
   */
  const amati = new ResizeObserver(() => liveMap?.invalidateSize())
  amati.observe(mapEl.value)
  pengamatPeta?.disconnect()
  pengamatPeta = amati
  requestAnimationFrame(() => liveMap?.invalidateSize())

  if (trackingTimer) clearInterval(trackingTimer)
  let step = 0
  trackingTimer = setInterval(() => {
    step = (step + 1) % 41
    const ratio = (step / 40) * 0.8
    tukangMarker?.setLatLng([
      tukangLat + (userLat.value - tukangLat) * ratio,
      tukangLng + (userLng.value - tukangLng) * ratio,
    ])
  }, 1000)
}

function bongkarPeta() {
  if (trackingTimer) clearInterval(trackingTimer)
  trackingTimer = null
  pengamatPeta?.disconnect()
  pengamatPeta = null
  if (liveMap) {
    liveMap.remove()
    liveMap = null
  }
}

/* Peta hanya hidup selama sub-keadaan yang menampilkannya. */
watch(lacakSub, (s) => {
  if (s === 'menuju') nextTick(initLiveMap)
  else bongkarPeta()
})

onBeforeUnmount(() => {
  if (matchingTimer) clearInterval(matchingTimer)
  bongkarPeta()
})

/* ────────── Foto ────────── */
const fileInput = ref<HTMLInputElement | null>(null)
function triggerUpload() {
  fileInput.value?.click()
}
function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0 && bolehTambahFoto.value) {
    const url = URL.createObjectURL(target.files[0])
    tukangStore.uploadedPhotos.push(url)
  }
  target.value = ''
}

/* ────────── Selesai ────────── */
const showPointsModal = ref(false)

function bayar() {
  tukangStore.isPaid = true
  selesaiSub.value = 'nilai'
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function submitRating() {
  tukangStore.reviewSubmitted = true
  showPointsModal.value = true
}

function selesaikan() {
  showPointsModal.value = false
  tukangStore.resetFlow()
  lacakSub.value = 'cari'
  selesaiSub.value = 'bayar'
  kembali()
}

/* ────────── Tombol aksi utama (bilah bawah) ──────────
 * Satu tombol besar per fase, tempatnya tetap di bawah. Aksi yang berpindah-
 * pindah tempat memaksa mata mencarinya tiap layar; yang diam terbaca sebagai
 * "langkah berikutnya" tanpa perlu dijelaskan.
 */
const cta = computed(() => {
  const s = tukangStore.customerStep
  if (s === 1) return { show: true, label: 'Lanjut ke jadwal', disabled: !boleh1.value, act: () => keFase(2) }
  if (s === 2) return { show: true, label: 'Cari tukang sekarang', disabled: false, act: mulaiCari }
  if (s === 3) {
    if (lacakSub.value === 'cari') return { show: false, label: '', disabled: true, act: () => {} }
    if (lacakSub.value === 'menuju')
      return { show: true, label: 'Simulasi: Tukang tiba', disabled: false, act: () => (lacakSub.value = 'tiba') }
    return { show: true, label: 'Mulai diagnosis', disabled: false, act: () => keFase(4) }
  }
  if (s === 4) return { show: true, label: 'Setujui & kerjakan', disabled: false, act: () => keFase(5) }
  if (s === 5) return { show: true, label: 'Simulasi: Pekerjaan selesai', disabled: false, act: () => keFase(6) }
  if (s === 6) {
    if (selesaiSub.value === 'bayar')
      return { show: true, label: `Bayar ${formatRupiah(tukangStore.grandTotal)}`, disabled: false, act: bayar }
    return { show: true, label: 'Kirim penilaian', disabled: tukangStore.ratingValue < 1, act: submitRating }
  }
  return { show: false, label: '', disabled: true, act: () => {} }
})

function toggleTag(tag: string) {
  const i = tukangStore.reviewTags.indexOf(tag)
  if (i >= 0) tukangStore.reviewTags.splice(i, 1)
  else tukangStore.reviewTags.push(tag)
}

const LOKASI_MASALAH = ['Dalam rumah', 'Luar rumah', 'Atap', 'Lantai atas', 'Kamar mandi', 'Dapur']
const TAG_NILAI = ['Profesional', 'Cepat', 'Ramah', 'Harga transparan', 'Hasil rapi']

onMounted(() => {
  tukangStore.appMode = 'customer'
  if (tukangStore.customerStep < 1 || tukangStore.customerStep > 6) tukangStore.resetFlow()
})
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!-- ── Kepala ── -->
    <header
      class="sticky top-0 z-50 bg-(--color-surface-0)/90 backdrop-blur-md border-b border-(--color-outline)/12"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
          <button
            type="button"
            aria-label="Kembali"
            class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="prevStep"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <div class="flex-1 min-w-0">
            <h1 class="text-[15px] font-display font-extrabold leading-tight text-(--color-azure)">
              Pesan BisaTukang
            </h1>
            <p class="text-[11px] text-(--color-on-surface-variant) truncate">{{ addressLabel }}</p>
          </div>
        </div>
      </div>

      <!--
        Dua penanda kemajuan, bukan satu.

        Mengisi formulir dan menunggu pekerjaan berjalan adalah dua hal yang
        berbeda: yang pertama punya langkah yang bisa dibolak-balik pemesan,
        yang kedua berjalan sendiri di luar kendalinya. Satu batang untuk
        keduanya membuat "3 dari 6" terbaca seperti formulir yang belum
        setengah jalan, padahal tukangnya sudah di jalan.
      -->
      <div v-if="fase <= 2" class="bg-(--color-surface-container-high) px-4 py-3">
        <div class="max-w-[430px] mx-auto relative">
          <!-- Garis penghubung, di belakang bulatan langkah -->
          <div class="absolute top-3.5 left-8 right-8 h-0.5 -translate-y-1/2 bg-(--color-outline)/25"></div>
          <div
            class="absolute top-3.5 left-8 h-0.5 -translate-y-1/2 bg-(--color-azure) transition-[width]"
            :style="{ width: `calc((100% - 4rem) * ${(fase - 1) / (LANGKAH_PESAN.length - 1)})` }"
          ></div>

          <div class="relative flex items-start justify-between">
            <div
              v-for="(l, i) in LANGKAH_PESAN"
              :key="l"
              class="flex flex-col items-center gap-1 w-16"
            >
              <span
                class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-extrabold transition-colors"
                :class="
                  i + 1 === fase
                    ? 'bg-(--color-azure) text-white ring-4 ring-(--color-azure)/20'
                    : i + 1 < fase
                      ? 'bg-(--color-azure) text-white'
                      : 'bg-(--color-surface-0) text-(--color-on-surface-variant)'
                "
              >
                {{ i + 1 }}
              </span>
              <span
                class="text-[10px] leading-tight text-center font-semibold"
                :class="i + 1 === fase ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
              >
                {{ l }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sesudah dipesan: yang berjalan sendiri, ditandai berbeda -->
      <div v-else class="max-w-[430px] mx-auto px-4 pb-3">
        <div class="flex items-center gap-1.5">
          <span
            v-for="(f, i) in FASE.slice(2)"
            :key="f.judul"
            class="h-1.5 flex-1 rounded-full transition-colors"
            :class="i + 3 <= fase ? 'bg-(--color-azure)' : 'bg-(--color-outline)/18'"
          ></span>
        </div>
        <p class="mt-1.5 text-[11.5px] font-semibold text-(--color-on-surface-variant)">
          {{ FASE[fase - 1]?.judul }}
        </p>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4">
      <!-- ═══════════ FASE 1 — MASALAH ═══════════ -->
      <section v-if="fase === 1" class="flex flex-col gap-5">
        <!-- ── Model penanganan ── -->
        <div>
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-[14px] font-display font-extrabold">Model penanganan</h3>
            <span
              class="rounded-full px-2.5 py-1 text-[10.5px] font-extrabold flex items-center gap-1 shrink-0"
              :class="
                tukangStore.modelKerja === 'harian'
                  ? 'bg-(--color-lime) text-(--color-on-surface)'
                  : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
              "
            >
              <Icon name="sparkle" class="w-3.5 h-3.5" />
              {{ tukangStore.modelKerja === 'harian' ? 'Gercep' : 'Survei gratis' }}
            </span>
          </div>

          <div class="mt-2.5 grid grid-cols-2 gap-1 p-1 rounded-2xl bg-(--color-surface-container-high)">
            <button
              v-for="m in MODEL_KERJA"
              :key="m.id"
              type="button"
              class="py-2.5 px-2 rounded-xl text-center transition-colors"
              :class="
                tukangStore.modelKerja === m.id
                  ? 'bg-(--color-azure) text-white shadow-sm'
                  : 'text-(--color-on-surface-variant)'
              "
              :aria-pressed="tukangStore.modelKerja === m.id"
              @click="tukangStore.modelKerja = m.id"
            >
              <span class="block text-[13px] font-extrabold leading-tight">{{ m.nama }}</span>
              <span
                class="block text-[10px] leading-tight mt-0.5"
                :class="tukangStore.modelKerja === m.id ? 'text-white/85' : ''"
              >
                {{ m.tempo }}
              </span>
            </button>
          </div>
        </div>

        <!-- ── Kategori masalah ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Kategori kerusakan</h3>
          <div class="mt-2.5 grid grid-cols-3 gap-2.5">
            <button
              v-for="k in KATEGORI_TUKANG"
              :key="k.id"
              type="button"
              class="flex flex-col items-center justify-start p-3 rounded-2xl bg-(--color-surface-0) text-center transition-all active:scale-[0.97]"
              :class="
                tukangStore.selectedCategoryId === k.id
                  ? 'border-2 border-(--color-azure) shadow-[0_6px_18px_rgba(30,155,240,0.18)]'
                  : 'border border-(--color-outline)/20'
              "
              :aria-pressed="tukangStore.selectedCategoryId === k.id"
              @click="tukangStore.setCategory(k.id)"
            >
              <span
                class="w-10 h-10 rounded-full flex items-center justify-center p-1.5 mb-1.5"
                :class="
                  tukangStore.selectedCategoryId === k.id
                    ? 'bg-(--color-azure)/12'
                    : 'bg-(--color-surface-container)'
                "
              >
                <IkonKategoriTukang :id="k.id" />
              </span>
              <span
                class="text-[11.5px] font-extrabold leading-tight"
                :class="tukangStore.selectedCategoryId === k.id ? 'text-(--color-azure)' : ''"
              >
                {{ k.nama }}
              </span>
            </button>
          </div>
        </div>

        <!-- ── Jenis masalah ── -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/15 p-4">
          <h3 class="text-[13.5px] font-display font-extrabold mb-2.5">Jenis masalah</h3>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="sub in tukangStore.currentCategory.subKategori"
              :key="sub"
              type="button"
              class="px-3 py-2 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tukangStore.selectedSubCategory === sub
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
              "
              @click="tukangStore.setSubCategory(sub)"
            >
              {{ sub }}
            </button>
          </div>
        </div>

        <!-- ── Tipe properti ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Tipe properti</h3>
          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="t in TIPE_PROPERTI"
              :key="t.id"
              type="button"
              class="px-3.5 py-2 rounded-full text-[12.5px] font-bold flex items-center gap-1.5 transition-colors active:scale-95"
              :class="
                tukangStore.tipeProperti === t.id
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
              "
              :aria-pressed="tukangStore.tipeProperti === t.id"
              @click="tukangStore.tipeProperti = t.id"
            >
              <Icon :name="t.ikon" class="w-4 h-4" />
              {{ t.nama }}
            </button>
          </div>
        </div>

        <!-- ── Deskripsi ── -->
        <div>
          <div class="flex items-baseline justify-between gap-3">
            <label for="deskripsi-kerusakan" class="text-[14px] font-display font-extrabold">
              Deskripsi kerusakan
            </label>
            <span class="text-[11px] text-(--color-on-surface-variant) shrink-0">Wajib diisi</span>
          </div>
          <textarea
            id="deskripsi-kerusakan"
            v-model="tukangStore.deskripsiMasalah"
            rows="3"
            class="mt-2 w-full rounded-2xl bg-(--color-surface-0) px-3.5 py-3 text-[13px] border-2 border-(--color-outline)/20 focus:border-(--color-azure) outline-none resize-none transition-colors"
            placeholder="Contoh: stop kontak kamar mandi memercik dan MCB turun tiap water heater dinyalakan…"
          ></textarea>
          <p class="mt-1.5 flex items-start gap-1.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            <Icon name="info" class="w-4 h-4 shrink-0 text-(--color-azure)" />
            Makin jelas ceritanya, makin dekat estimasi tukang ke harga akhirnya.
          </p>
        </div>

        <!-- ── Foto ── -->
        <div>
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-[14px] font-display font-extrabold">Foto area rusak</h3>
            <span class="text-[11px] font-bold text-(--color-azure) shrink-0">
              {{ jumlahFoto }}/{{ MAKS_FOTO }} terunggah
            </span>
          </div>

          <input
            ref="fileInput"
            type="file"
            accept="image/*,video/*"
            class="hidden"
            @change="handleFileChange"
          />

          <div class="mt-2.5 grid grid-cols-4 gap-2.5">
            <div
              v-for="(img, idx) in tukangStore.uploadedPhotos"
              :key="idx"
              class="relative aspect-square rounded-xl overflow-hidden border border-(--color-outline)/20"
            >
              <img :src="img" alt="" class="w-full h-full object-cover" />
              <button
                type="button"
                aria-label="Hapus foto"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-(--color-on-surface)/75 text-white flex items-center justify-center"
                @click="tukangStore.uploadedPhotos.splice(idx, 1)"
              >
                <Icon name="x" class="w-3 h-3" />
              </button>
            </div>

            <button
              v-if="bolehTambahFoto"
              type="button"
              class="aspect-square rounded-xl border-2 border-dashed border-(--color-azure)/50 bg-(--color-azure)/6 text-(--color-azure) flex flex-col items-center justify-center gap-1 active:scale-95 transition-transform"
              @click="triggerUpload"
            >
              <Icon name="camera" class="w-5 h-5" />
              <span class="text-[10px] font-bold">Tambah</span>
            </button>

            <!-- Kotak sisa: penanda tempat, sengaja tidak bisa diketuk -->
            <div
              v-for="n in kotakKosong"
              :key="`kosong-${n}`"
              aria-hidden="true"
              class="aspect-square rounded-xl border border-dashed border-(--color-outline)/30 bg-(--color-surface-0) flex items-center justify-center text-(--color-outline)/40"
            >
              <Icon name="image" class="w-5 h-5" />
            </div>
          </div>
        </div>

        <!-- ── Penyediaan material ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Penyediaan material</h3>
          <p class="mt-0.5 text-[11.5px] text-(--color-on-surface-variant)">
            Ditanya sekarang supaya tidak jadi selisih paham di akhir.
          </p>
          <div class="mt-2.5 flex flex-col gap-2">
            <label
              v-for="m in PENYEDIAAN_MATERIAL"
              :key="m.id"
              class="flex items-start gap-3 p-3.5 rounded-2xl bg-(--color-surface-0) cursor-pointer transition-colors"
              :class="
                tukangStore.penyediaanMaterial === m.id
                  ? 'border-2 border-(--color-azure)'
                  : 'border border-(--color-outline)/20'
              "
            >
              <input
                v-model="tukangStore.penyediaanMaterial"
                type="radio"
                name="penyediaan-material"
                :value="m.id"
                class="mt-0.5 h-4 w-4 shrink-0 accent-(--color-azure)"
              />
              <span class="min-w-0">
                <span class="block text-[13px] font-extrabold leading-tight">{{ m.nama }}</span>
                <span class="block mt-0.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                  {{ m.ringkas }}
                </span>
              </span>
            </label>
          </div>
        </div>

        <!-- ── Lokasi di properti ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Bagian mana?</h3>
          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="loc in LOKASI_MASALAH"
              :key="loc"
              type="button"
              class="px-3 py-2 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tukangStore.lokasiMasalah === loc
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
              "
              @click="tukangStore.lokasiMasalah = loc"
            >
              {{ loc }}
            </button>
          </div>
        </div>
      </section>

      <!-- ═══════════ FASE 2 — JADWAL ═══════════ -->
      <section v-else-if="fase === 2" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-5">
          <h3 class="text-[15px] font-display font-extrabold mb-1">Kapan tukang datang?</h3>
          <p class="text-[12px] text-(--color-on-surface-variant) mb-4">
            Pilih waktu kunjungan yang paling pas buat kamu.
          </p>

          <div class="flex flex-col gap-2.5">
            <!-- Secepatnya -->
            <button
              type="button"
              class="w-full p-3.5 rounded-2xl border text-left flex items-center justify-between transition-all"
              :class="
                tukangStore.jadwalTipe === 'secepatnya'
                  ? 'bg-(--color-azure) text-white border-(--color-azure) shadow-[0_8px_18px_rgba(30,155,240,0.3)]'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
              @click="tukangStore.jadwalTipe = 'secepatnya'"
            >
              <span class="flex items-center gap-2.5">
                <Icon name="sparkle" class="w-6 h-6" />
                <span>
                  <span class="block text-[13.5px] font-bold">Datang secepatnya</span>
                  <span class="block text-[11px] opacity-80">Teknisi tiba dalam 1–2 jam</span>
                </span>
              </span>
              <Icon name="chevron-right" class="w-5 h-5" />
            </button>

            <!-- Hari ini -->
            <div
              class="p-3.5 rounded-2xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'hari_ini'
                  ? 'bg-(--color-azure)/6 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px]"
                @click="tukangStore.jadwalTipe = 'hari_ini'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Hari ini
                </span>
                <span class="text-[11px] text-(--color-azure)">Pilih jam</span>
              </button>
              <div v-if="tukangStore.jadwalTipe === 'hari_ini'" class="grid grid-cols-3 gap-2 mt-3">
                <button
                  v-for="jam in ['14:00-16:00', '16:00-18:00', '18:00-20:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/25'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>

            <!-- Besok -->
            <div
              class="p-3.5 rounded-2xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'besok'
                  ? 'bg-(--color-azure)/6 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px]"
                @click="tukangStore.jadwalTipe = 'besok'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Besok
                </span>
                <span class="text-[11px] text-(--color-azure)">Pilih jam</span>
              </button>
              <div v-if="tukangStore.jadwalTipe === 'besok'" class="grid grid-cols-2 gap-2 mt-3">
                <button
                  v-for="jam in ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/25'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Alamat tujuan, bisa diubah tanpa kehilangan isian formulir -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4">
          <div class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-full bg-(--color-azure)/10 text-(--color-azure) flex items-center justify-center shrink-0">
              <Icon name="pin" class="w-4.5 h-4.5" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-on-surface-variant)">
                Tukang datang ke
              </p>
              <p class="mt-0.5 text-[13px] font-semibold leading-snug">{{ addressLabel }}</p>
            </div>
            <RouterLink
              :to="{ name: 'task-location', query: { category: 'bisatukang' } }"
              class="shrink-0 text-[12.5px] font-extrabold text-(--color-azure)"
            >
              Ubah
            </RouterLink>
          </div>
        </div>

        <!-- Biaya kunjungan transparan -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4">
          <div class="flex items-center justify-between">
            <span class="text-[13px] font-semibold text-(--color-on-surface-variant)">Biaya kunjungan</span>
            <span class="text-[14px] font-extrabold">
              {{ formatRupiah(tukangStore.currentCategory.biayaKunjunganMin) }}
            </span>
          </div>
          <p class="mt-2 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            Biaya perbaikan &amp; material dihitung setelah teknisi survei, dan
            <span class="font-semibold text-(--color-on-surface)">disetujui dulu sebelum dikerjakan.</span>
          </p>
        </div>
      </section>

      <!-- ═══════════ FASE 3 — CARI TUKANG ═══════════ -->
      <section v-else-if="fase === 3" class="flex flex-col gap-4">
        <!-- Sub: mencocokkan -->
        <div v-if="lacakSub === 'cari'" class="flex flex-col items-center justify-center py-8">
          <div class="relative w-36 h-36 flex items-center justify-center mb-6">
            <div class="absolute inset-0 rounded-full border-4 border-(--color-azure)/20 animate-ping"></div>
            <div class="absolute inset-2 rounded-full border-4 border-(--color-azure)/25 animate-pulse"></div>
            <div class="w-24 h-24 rounded-full bg-(--color-azure) text-white flex items-center justify-center shadow-[0_10px_28px_rgba(30,155,240,0.4)]">
              <Icon name="search" class="w-9 h-9" />
            </div>
          </div>
          <h3 class="text-[16px] font-display font-extrabold">Mencari tukang terdekat…</h3>
          <p class="text-[12px] text-(--color-on-surface-variant) mt-1 mb-6 text-center">
            Menghubungkan dengan teknisi terverifikasi di sekitarmu.
          </p>
          <div class="w-full bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4 flex flex-col gap-3">
            <div
              v-for="(t, i) in ['Menemukan tukang tersedia', 'Memeriksa jadwal', 'Mengirim penugasan']"
              :key="t"
              class="flex items-center gap-3 text-[12.5px] font-semibold"
              :class="matchingStepDone[i] ? 'text-(--color-on-secondary-container)' : 'text-(--color-on-surface-variant)'"
            >
              <Icon :name="matchingStepDone[i] ? 'check-circle' : 'clock'" class="w-5 h-5 shrink-0" />
              <span>{{ t }}</span>
            </div>
          </div>
        </div>

        <!-- Sub: menuju / tiba — profil tukang + peta -->
        <template v-else>
          <div
            v-if="lacakSub === 'tiba'"
            class="rounded-2xl bg-(--color-secondary-container) text-(--color-on-secondary-container) p-4 flex items-center gap-3"
          >
            <span class="w-11 h-11 rounded-full bg-white/40 flex items-center justify-center shrink-0">
              <Icon name="check-circle" class="w-6 h-6" />
            </span>
            <div>
              <p class="text-[14px] font-display font-extrabold leading-tight">Tukang sudah tiba</p>
              <p class="text-[11.5px] opacity-90">{{ tukangStore.tukangInfo.nama }} ada di lokasimu.</p>
            </div>
          </div>

          <!-- Kartu tukang -->
          <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
            <div class="flex items-center gap-3.5">
              <AvatarTukang :nama="tukangStore.tukangInfo.nama" class="w-14 h-14 text-[17px] ring-2 ring-(--color-azure)/25 shrink-0" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <h3 class="text-[15px] font-display font-extrabold truncate">{{ tukangStore.tukangInfo.nama }}</h3>
                  <span class="inline-flex items-center gap-1 text-[12px] font-bold shrink-0">
                    <Icon name="star" class="w-3.5 h-3.5 text-amber-500" />
                    {{ tukangStore.tukangInfo.rating }}
                  </span>
                </div>
                <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">
                  {{ tukangStore.tukangInfo.spesialis }} · {{ tukangStore.tukangInfo.jobsCount }} pekerjaan
                </p>
                <div class="flex flex-wrap gap-1.5 mt-1.5">
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

            <div class="grid grid-cols-2 gap-2 mt-3.5">
              <a
                href="tel:+62000000000"
                class="h-11 rounded-full border-[1.5px] border-(--color-outline)/40 text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
              >
                <Icon name="phone" class="w-4 h-4 text-(--color-azure)" /> Telepon
              </a>
              <button
                type="button"
                class="h-11 rounded-full bg-(--color-azure) text-white text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
              >
                <Icon name="chat" class="w-4 h-4" /> Chat
              </button>
            </div>
          </div>

          <!-- Peta pelacakan -->
          <div v-if="lacakSub === 'menuju'" class="bg-(--color-surface-0) rounded-2xl overflow-hidden border border-(--color-outline)/12 shadow-sm">
            <div class="px-4 py-2.5 flex items-center justify-between border-b border-(--color-outline)/12">
              <span class="text-[12px] font-bold">Menuju lokasimu</span>
              <span class="text-[11.5px] font-extrabold text-(--color-azure)">{{ tukangStore.tukangInfo.etaMinutes }} mnt lagi</span>
            </div>
            <div ref="mapEl" class="w-full h-56 z-0"></div>
          </div>

          <!-- Verifikasi saat tiba -->
          <div v-else class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
            <h4 class="text-[13.5px] font-display font-extrabold mb-2.5 flex items-center gap-1.5">
              <Icon name="shield" class="w-5 h-5 text-(--color-azure)" />
              Cek sebelum mulai
            </h4>
            <div class="flex flex-col gap-2 text-[12.5px] font-semibold">
              <div
                v-for="v in ['Wajah sesuai foto profil', 'Kartu identitas resmi', 'Peralatan lengkap &amp; standar K3']"
                :key="v"
                class="flex items-center gap-2 p-2.5 rounded-xl bg-(--color-surface-container)"
              >
                <Icon name="check-circle" class="w-4.5 h-4.5 text-(--color-on-secondary-container) shrink-0" />
                <span v-html="v"></span>
              </div>
            </div>
          </div>
        </template>
      </section>

      <!-- ═══════════ FASE 4 — PENAWARAN ═══════════ -->
      <section v-else-if="fase === 4" class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-5">
          <div class="flex items-center gap-2.5 border-b border-(--color-outline)/12 pb-3 mb-3">
            <span class="w-9 h-9 rounded-full bg-(--color-azure)/10 flex items-center justify-center shrink-0">
              <Icon name="clipboard" class="w-5 h-5 text-(--color-azure)" />
            </span>
            <div>
              <h3 class="text-[15px] font-display font-extrabold leading-tight">Hasil survei teknisi</h3>
              <p class="text-[11px] text-(--color-on-surface-variant)">oleh {{ tukangStore.tukangInfo.nama }}</p>
            </div>
          </div>

          <div class="rounded-xl bg-(--color-surface-container) p-3 text-[12.5px] mb-3">
            <span class="font-bold">Ditemukan:</span> {{ tukangStore.diagnosisData.masalah }}
          </div>

          <p class="text-[12px] font-bold mb-1.5">Rekomendasi tindakan</p>
          <ul class="flex flex-col gap-1.5 mb-4">
            <li
              v-for="(rec, i) in tukangStore.diagnosisData.rekomendasi"
              :key="i"
              class="flex items-start gap-2 text-[12.5px] text-(--color-on-surface-variant)"
            >
              <Icon name="check" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
              {{ rec }}
            </li>
          </ul>

          <!-- Rincian biaya transparan -->
          <div class="border-t border-(--color-outline)/12 pt-3 flex flex-col gap-2 text-[12.5px]">
            <div class="flex justify-between text-(--color-on-surface-variant)">
              <span>Biaya kunjungan</span>
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
              <span>Jasa perbaikan</span>
              <span>{{ formatRupiah(tukangStore.diagnosisData.jasaPerbaikan) }}</span>
            </div>
            <div class="flex justify-between text-[15px] font-extrabold text-(--color-azure) border-t border-(--color-outline)/12 pt-2.5 mt-1">
              <span>Total</span>
              <span>{{ formatRupiah(tukangStore.subTotal) }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-start gap-2 px-1 text-[11.5px] text-(--color-on-surface-variant)">
          <Icon name="shield" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
          <p>Harga di atas final. Tidak ada biaya tambahan tanpa persetujuanmu lebih dulu.</p>
        </div>

        <button
          type="button"
          class="w-full h-11 rounded-full border-[1.5px] border-(--color-outline)/40 text-[13px] font-bold text-(--color-on-surface-variant) active:scale-[0.98] transition-transform"
        >
          Minta penjelasan dulu
        </button>
      </section>

      <!-- ═══════════ FASE 5 — PENGERJAAN ═══════════ -->
      <section v-else-if="fase === 5" class="flex flex-col gap-4">
        <div class="bg-(--color-azure) rounded-2xl p-5 text-white shadow-[0_10px_26px_rgba(30,155,240,0.32)]">
          <div class="flex items-center justify-between">
            <span class="text-[15px] font-display font-extrabold">Pekerjaan berlangsung</span>
            <span class="text-[11px] bg-white/20 px-2.5 py-1 rounded-full font-bold">±45 mnt</span>
          </div>
          <p class="text-[12px] text-white/85 mt-1">Teknisi sedang mengerjakan perbaikan sesuai penawaran.</p>
        </div>

        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
          <h4 class="text-[13.5px] font-display font-extrabold mb-3">Progres perbaikan</h4>
          <div class="flex flex-col gap-2">
            <div
              v-for="item in tukangStore.progressChecklist"
              :key="item.label"
              class="flex items-center gap-2.5 p-2.5 rounded-xl text-[12.5px]"
              :class="
                item.done
                  ? 'bg-(--color-secondary-container) text-(--color-on-secondary-container) font-bold'
                  : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
              "
            >
              <Icon :name="item.done ? 'check-circle' : 'clock'" class="w-5 h-5 shrink-0" />
              <span>{{ item.label }}</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-(--color-outline)/12">
            <button type="button" class="h-10 rounded-full border-[1.5px] border-(--color-outline)/40 text-[12.5px] font-bold flex items-center justify-center gap-1.5">
              <Icon name="image" class="w-4 h-4 text-(--color-azure)" /> Foto progres
            </button>
            <button type="button" class="h-10 rounded-full bg-(--color-azure) text-white text-[12.5px] font-bold flex items-center justify-center gap-1.5">
              <Icon name="chat" class="w-4 h-4" /> Chat tukang
            </button>
          </div>
        </div>
      </section>

      <!-- ═══════════ FASE 6 — SELESAI ═══════════ -->
      <section v-else-if="fase === 6" class="flex flex-col gap-4">
        <!-- Sub: bayar -->
        <template v-if="selesaiSub === 'bayar'">
          <div class="rounded-2xl bg-(--color-secondary-container) text-(--color-on-secondary-container) p-5 text-center">
            <span class="w-14 h-14 rounded-full bg-white/40 flex items-center justify-center mx-auto mb-2">
              <Icon name="check-circle" class="w-8 h-8" />
            </span>
            <h3 class="text-[17px] font-display font-extrabold">Pekerjaan selesai</h3>
            <p class="text-[12px] opacity-90 mt-1">Sudah diuji dan diperiksa. Berikut hasilnya.</p>
          </div>

          <!-- Hasil pengujian -->
          <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
            <h4 class="text-[13.5px] font-display font-extrabold mb-2.5">Hasil pengujian</h4>
            <div class="flex flex-col gap-1.5 mb-3">
              <div
                v-for="t in tukangStore.testingChecklist"
                :key="t.label"
                class="flex items-center gap-2 text-[12.5px] font-semibold text-(--color-on-secondary-container)"
              >
                <Icon name="check" class="w-4 h-4 shrink-0" /> {{ t.label }}
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div
                v-for="sisi in [{ label: 'Sebelum' }, { label: 'Sesudah' }]"
                :key="sisi.label"
                class="relative rounded-xl overflow-hidden border border-(--color-outline)/12 h-24 bg-(--color-surface-container) flex flex-col items-center justify-center gap-1"
              >
                <Icon name="camera" class="w-5 h-5 text-(--color-on-surface-variant)" />
                <span class="text-[10px] font-semibold text-(--color-on-surface-variant)">Menunggu foto</span>
                <span class="absolute bottom-1 left-1 text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-(--color-on-surface)/70 text-white">
                  {{ sisi.label }}
                </span>
              </div>
            </div>

            <div class="mt-3 p-3 rounded-xl bg-(--color-surface-container) text-[11.5px] flex flex-col gap-1.5">
              <p class="text-(--color-on-surface-variant)"><span class="font-bold text-(--color-on-surface)">Catatan:</span> {{ tukangStore.catatanTukang }}</p>
              <p class="flex items-center gap-1.5 font-bold text-(--color-azure)">
                <Icon name="shield" class="w-4 h-4" />
                Garansi resmi {{ tukangStore.garansiHari }} hari
              </p>
            </div>
          </div>

          <!-- Ringkasan bayar -->
          <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
            <h4 class="text-[13.5px] font-display font-extrabold mb-3">Ringkasan pembayaran</h4>
            <div class="flex flex-col gap-2 text-[12.5px] text-(--color-on-surface-variant)">
              <div class="flex justify-between">
                <span>Biaya kunjungan</span><span>{{ formatRupiah(tukangStore.diagnosisData.biayaKunjungan) }}</span>
              </div>
              <div v-for="item in tukangStore.diagnosisData.materialItems" :key="item.nama" class="flex justify-between">
                <span>{{ item.nama }}</span><span>{{ formatRupiah(item.harga) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Jasa perbaikan</span><span>{{ formatRupiah(tukangStore.diagnosisData.jasaPerbaikan) }}</span>
              </div>
              <div v-if="tukangStore.promoDiscount > 0" class="flex justify-between text-(--color-on-secondary-container) font-semibold">
                <span>Promo {{ tukangStore.promoCode }}</span><span>- {{ formatRupiah(tukangStore.promoDiscount) }}</span>
              </div>
            </div>
            <div class="flex justify-between text-[15px] font-extrabold border-t border-(--color-outline)/12 pt-2.5 mt-2.5">
              <span>Total bayar</span>
              <span>{{ formatRupiah(tukangStore.grandTotal) }}</span>
            </div>
          </div>

          <!-- Metode -->
          <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-4">
            <h4 class="text-[13.5px] font-display font-extrabold mb-2.5">Metode pembayaran</h4>
            <div class="flex flex-col gap-2">
              <button
                v-for="pm in [
                  { id: 'gopay', label: 'GoPay / OVO / DANA', ikon: 'wallet' },
                  { id: 'transfer', label: 'Transfer Bank (VA)', ikon: 'business' },
                  { id: 'cc', label: 'Kartu Kredit / Debit', ikon: 'card' },
                  { id: 'cash', label: 'Tunai di tempat', ikon: 'receipt' },
                ]"
                :key="pm.id"
                type="button"
                class="w-full p-3 rounded-xl border text-left flex items-center justify-between text-[12.5px] font-semibold transition-colors"
                :class="
                  tukangStore.paymentMethod === pm.id
                    ? 'bg-(--color-azure)/6 border-(--color-azure) text-(--color-azure)'
                    : 'bg-(--color-surface-container) border-(--color-outline)/12'
                "
                @click="tukangStore.paymentMethod = pm.id as any"
              >
                <span class="flex items-center gap-2">
                  <Icon :name="pm.ikon" class="w-5 h-5" /> {{ pm.label }}
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
        </template>

        <!-- Sub: nilai -->
        <template v-else>
          <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 shadow-sm p-5 text-center">
            <AvatarTukang :nama="tukangStore.tukangInfo.nama" class="w-16 h-16 text-[19px] mx-auto ring-2 ring-amber-400/50" />
            <h3 class="text-[15px] font-display font-extrabold mt-3">Beri penilaian</h3>
            <p class="text-[12px] text-(--color-on-surface-variant) mb-3">Bagaimana kerja {{ tukangStore.tukangInfo.nama }}?</p>

            <div class="flex items-center justify-center gap-1.5 mb-4">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                :aria-label="`${star} bintang`"
                class="active:scale-110 transition-transform"
                @click="tukangStore.ratingValue = star"
              >
                <Icon name="star" class="w-8 h-8" :class="star <= tukangStore.ratingValue ? 'text-amber-500' : 'text-(--color-outline)/35'" />
              </button>
            </div>

            <textarea
              v-model="tukangStore.reviewComment"
              rows="2"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none mb-3"
              placeholder="Ceritakan pengalamanmu (opsional)"
            ></textarea>

            <div class="flex flex-wrap gap-2 justify-center">
              <button
                v-for="tag in TAG_NILAI"
                :key="tag"
                type="button"
                class="px-3 py-1.5 rounded-full border text-[11.5px] font-semibold transition-colors"
                :class="
                  tukangStore.reviewTags.includes(tag)
                    ? 'bg-(--color-azure) border-(--color-azure) text-white'
                    : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
                "
                @click="toggleTag(tag)"
              >
                {{ tag }}
              </button>
            </div>
          </div>
        </template>
      </section>
    </main>

    <!-- ── Bilah aksi utama ── -->
    <footer
      v-if="cta.show"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/95 backdrop-blur-md border-t border-(--color-outline)/12"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-13 rounded-2xl bg-(--color-azure) text-white font-extrabold text-[14.5px] shadow-[0_8px_22px_rgba(30,155,240,0.35)] active:scale-[0.98] transition-transform flex items-center justify-center gap-2 disabled:opacity-40 disabled:active:scale-100"
          :disabled="cta.disabled"
          @click="cta.act"
        >
          <span>{{ cta.label }}</span>
          <Icon v-if="fase < 6" name="arrow-right" class="w-4.5 h-4.5" />
        </button>
      </div>
    </footer>

    <!-- ── Modal poin ── -->
    <div v-if="showPointsModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-6">
      <div class="bg-(--color-surface-0) rounded-3xl p-6 max-w-xs w-full text-center shadow-2xl">
        <span class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-3">
          <Icon name="star" class="w-8 h-8 text-amber-500" />
        </span>
        <h3 class="text-[17px] font-display font-extrabold">Terima kasih!</h3>
        <p class="text-[12px] text-(--color-on-surface-variant) mt-1">
          Kamu dapat <strong class="text-amber-500">+{{ tukangStore.earnedPoints }} BisaPoints</strong> dari ulasan ini.
        </p>
        <button
          type="button"
          class="w-full h-11 rounded-full bg-(--color-azure) text-white font-extrabold text-[13.5px] mt-4 active:scale-[0.98] transition-transform"
          @click="selesaikan"
        >
          Kembali ke beranda
        </button>
      </div>
    </div>
  </div>
</template>
