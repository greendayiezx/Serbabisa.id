<script setup lang="ts">
import { computed, ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { usePesananStore, type StatusPesanan, type Pesanan } from '@/stores/pesanan'
import { rentangJam } from '@/lib/invoice'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'
import { useSkeleton } from '@/composables/useSkeleton'
import StatusPesananSkeleton from '@/components/skeleton/StatusPesananSkeleton.vue'

const route = useRoute()
const router = useRouter()
const pesananStore = usePesananStore()

const nomorPesanan = computed(() => String(route.params.nomor ?? ''))

/**
 * Pesanan dibaca dari server, bukan dari localStorage. Cache lokal dipakai
 * lebih dulu supaya halaman tidak kosong sepersekian detik setelah membayar,
 * lalu ditimpa versi server begitu tiba.
 */
const pesanan = ref<Pesanan | null>(pesananStore.cari(nomorPesanan.value))

const LABEL_STATUS: Record<StatusPesanan, { judul: string; catatan: string }> = {
  diproses: { judul: 'Pesanan lagi diproses', catatan: 'Kami sedang mempersiapkan pesananmu' },
  dibelanjakan: { judul: 'Mitra sedang belanja', catatan: 'Barangmu sedang dipilih di gerai' },
  diantar: { judul: 'Pesanan dalam perjalanan', catatan: 'Mitra sedang menuju alamatmu' },
  selesai: { judul: 'Pesanan selesai', catatan: 'Terima kasih sudah belanja' },
}

const status = computed(() => LABEL_STATUS[pesanan.value?.status ?? 'diproses'])
const rincianTerbuka = ref(true)

/** Kode referral statis; pembuatan kode per pengguna belum ada. */
const KODE_REFERRAL = 'BISA15'
const kodeTersalin = ref(false)
const invoiceTersalin = ref(false)

async function salin(teks: string, penanda: 'kode' | 'invoice') {
  try {
    await navigator.clipboard.writeText(teks)
  } catch {
    // Clipboard diblokir (mis. bukan HTTPS) — diamkan, tidak ada yang rusak.
    return
  }
  if (penanda === 'kode') {
    kodeTersalin.value = true
    setTimeout(() => (kodeTersalin.value = false), 1600)
  } else {
    invoiceTersalin.value = true
    setTimeout(() => (invoiceTersalin.value = false), 1600)
  }
}

function kembali() {
  router.push({ name: 'home' })
}

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/* ---------------- Driver (mitra pengantar) ---------------- */
/**
 * Nama & plat driver masih PLACEHOLDER — server belum mengirim identitas mitra
 * pengantar. Ratingnya SENGAJA tidak dipalsukan: nilai itu harus lahir dari
 * ulasan customer sungguhan. Selama server belum mengirimnya, yang tampil adalah
 * "Belum ada penilaian", bukan angka karangan.
 */
const DRIVER_NAMA = 'Asep Suparman'
const DRIVER_PLAT = 'B 3947 KMR'
/** Rating asli dari server; null = belum ada ulasan. Tidak pernah diisi dummy. */
const driverRating = computed<number | null>(() => null)

/** Driver dianggap sudah ketemu begitu pesanan lepas dari antrean 'diproses'. */
const adaDriver = computed(() => (pesanan.value?.status ?? 'diproses') !== 'diproses')

function chatDriver() {
  if (!pesanan.value?.id) return
  router.push({ name: 'task-chat', params: { id: pesanan.value.id } })
}

/* ---------------- Pemantauan status ---------------- */
// Status berubah di server (mitra menerima → mengantar → selesai). Halaman
// menanyakannya berkala supaya "Pesanan lagi diproses" otomatis berganti jadi
// "Driver ditemukan" tanpa perlu memuat ulang.
const SELANG_MS = 5000
let timer: ReturnType<typeof setInterval> | null = null

function hentiPantau() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

onMounted(() => {
  timer = setInterval(async () => {
    const p = await pesananStore.muat(nomorPesanan.value)
    if (p) pesanan.value = p
    if (p?.status === 'selesai') hentiPantau()
  }, SELANG_MS)
})

onBeforeUnmount(() => {
  hentiPantau()
  tutupLacak()
})

/* ---------------- Lacak driver (peta tujuan) ---------------- */
// Belum ada GPS mitra, jadi peta ini menunjukkan LOKASI TUJUAN (dari alamat
// pesanan, digeokode saat dibuka) — bukan titik driver palsu. Posisi driver
// sungguhan menyusul begitu backend mengirimkannya.
const lacakBuka = ref(false)
const lacakEl = ref<HTMLDivElement | null>(null)
let lacakPeta: L.Map | null = null

/** Pin tujuan (rumah customer) — biru. */
const pinTujuan = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 40],
})

/** Pin titik A (tempat driver mengambil pesanan) — oranye. */
const pinToko = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="38" height="38" stroke="#FF7A00" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#FF7A00" stroke="none"/></svg>`,
  iconSize: [38, 38],
  iconAnchor: [19, 38],
})

/** Penanda driver di jalan — lingkaran hijau + motor. */
const ikonDriver = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 40 40" width="40" height="40" style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3))"><circle cx="20" cy="20" r="15" fill="#2E7D32" stroke="#fff" stroke-width="3"/><g stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="15" cy="24" r="3.2"/><circle cx="26" cy="24" r="3.2"/><path d="M15 24 L19 18 H24 L26 24"/><path d="M19 18 L17 15 H15"/><path d="M24 18 H27"/></g></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 20],
})

/** Geokode alamat jadi koordinat, atau null kalau gagal. */
async function geocode(alamat?: string): Promise<L.LatLngTuple | null> {
  if (!alamat) return null
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(alamat)}`,
    )
    const data = await res.json()
    const p0 = Array.isArray(data) ? data[0] : null
    return p0 ? [Number(p0.lat), Number(p0.lon)] : null
  } catch {
    return null
  }
}

/** Rute jalan A→B dari OSRM (mengikuti jalan), atau null kalau gagal. */
async function ambilRute(a: L.LatLngTuple, b: L.LatLngTuple): Promise<L.LatLngTuple[] | null> {
  try {
    const res = await fetch(
      `https://router.project-osrm.org/route/v1/driving/${a[1]},${a[0]};${b[1]},${b[0]}?overview=full&geometries=geojson`,
    )
    const data = await res.json()
    const coords = data?.routes?.[0]?.geometry?.coordinates
    if (!Array.isArray(coords)) return null
    return coords.map((c: [number, number]) => [c[1], c[0]] as L.LatLngTuple)
  } catch {
    return null
  }
}

async function bukaLacak() {
  lacakBuka.value = true
  await nextTick()
  if (!lacakEl.value || lacakPeta) return

  const bawaan: L.LatLngTuple = [-6.2088, 106.8456]
  lacakPeta = L.map(lacakEl.value, { center: bawaan, zoom: 14, zoomControl: false })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(lacakPeta)
  requestAnimationFrame(() => lacakPeta?.invalidateSize())

  // Tujuan = alamat customer (digeokode). Titik A (tempat ambil pesanan) &
  // posisi driver DISIMULASIKAN — server belum mengirim lokasi toko/GPS mitra,
  // jadi ini ilustrasi perjalanan menuju lokasimu, bukan GPS langsung.
  const tujuan = await geocode(pesanan.value?.alamat)
  if (!tujuan || !lacakPeta) return
  const titikA: L.LatLngTuple = [tujuan[0] + 0.013, tujuan[1] - 0.011]

  const rute = (await ambilRute(titikA, tujuan)) ?? [titikA, tujuan]
  if (!lacakPeta) return

  const garis = L.polyline(rute, {
    color: '#FF7A00',
    weight: 6,
    opacity: 0.95,
    lineCap: 'round',
    lineJoin: 'round',
  }).addTo(lacakPeta)

  L.marker(titikA, { icon: pinToko }).addTo(lacakPeta)
  L.marker(tujuan, { icon: pinTujuan }).addTo(lacakPeta)

  // Driver di ~45% panjang rute, dalam perjalanan menuju customer.
  const posDriver: L.LatLngTuple =
    rute.length > 2
      ? rute[Math.floor(rute.length * 0.45)]
      : [(titikA[0] + tujuan[0]) / 2, (titikA[1] + tujuan[1]) / 2]
  L.marker(posDriver, { icon: ikonDriver }).addTo(lacakPeta)

  lacakPeta.fitBounds(garis.getBounds(), { padding: [50, 50] })
}

function tutupLacak() {
  lacakBuka.value = false
  if (lacakPeta) {
    lacakPeta.remove()
    lacakPeta = null
  }
}

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(async () => {
  try {
    const dariServer = await pesananStore.muat(nomorPesanan.value)
    if (dariServer) pesanan.value = dariServer
  } finally {
    // Skeleton dilepas setelah request selesai — berhasil maupun gagal —
    // supaya halaman tidak menggantung di kondisi memuat kalau server mati.
    tandaiSiap()
  }
})
</script>

<template>
  <StatusPesananSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
      <header class="sticky top-0 z-30 bg-(--color-surface-0)">
        <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
          <button
            type="button"
            aria-label="Kembali ke beranda"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="kembali"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <div class="flex-1 text-center">
            <h1 class="text-[17px] font-extrabold leading-tight">Status Pesanan</h1>
            <p v-if="pesanan" class="text-[11px] text-(--color-on-surface-variant)">#{{ pesanan.nomor }}</p>
          </div>
          <span class="text-[13px] font-bold text-(--color-azure) shrink-0">Bantuan</span>
        </div>
      </header>
  
      <div v-if="!pesanan" class="max-w-[430px] mx-auto px-4 py-16 text-center">
        <p class="text-4xl mb-3">📦</p>
        <p class="text-[14px] font-bold mb-1">Pesanan tidak ditemukan</p>
        <button
          type="button"
          class="mt-4 h-11 px-6 rounded-full bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
          @click="kembali"
        >
          Kembali ke beranda
        </button>
      </div>
  
      <main v-else class="max-w-[430px] mx-auto flex flex-col gap-2.5">
        <!-- Estimasi tiba + ilustrasi mitra belanja (animasi CSS, bukan Lottie). -->
        <section class="relative bg-gradient-to-b from-[#2E9DF7] to-[#1E7FE0] pt-5 overflow-hidden">
          <p class="text-center text-[13px] font-semibold text-white/90">Estimasi tiba</p>
          <p class="text-center text-[30px] font-extrabold text-white leading-tight">
            {{ rentangJam(pesanan.estimasiMulai, pesanan.estimasiSelesai) }}
          </p>
          <svg viewBox="0 0 400 300" class="w-full h-auto block" xmlns="http://www.w3.org/2000/svg">
            <!-- Tanah -->
            <path
              d="M45 260 C120 255 270 255 355 260"
              fill="none"
              stroke="#8BC53F"
              stroke-width="7"
              stroke-linecap="round"
            />
  
            <!-- Troli -->
            <g class="sp-cart">
              <path
                d="M238 125 H354 L338 203 H253 Z"
                fill="#FFFFFF"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linejoin="round"
              />
              <path d="M238 125H354" stroke="#2196F3" stroke-width="9" stroke-linecap="round" />
              <path
                d="M253 203 H338 L347 218 H259"
                fill="none"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle cx="272" cy="232" r="11" fill="#0A326B" />
              <circle cx="326" cy="232" r="11" fill="#0A326B" />
              <circle cx="272" cy="232" r="4" fill="#FFFFFF" />
              <circle cx="326" cy="232" r="4" fill="#FFFFFF" />
  
              <!-- Belanjaan -->
              <g class="sp-items">
                <path d="M270 118 H292 L289 155 H273Z" fill="#8BC53F" />
                <path
                  d="M276 119 C276 109 286 109 286 119"
                  fill="none"
                  stroke="#0A326B"
                  stroke-width="4"
                  stroke-linecap="round"
                />
                <rect x="299" y="126" width="28" height="28" rx="3" fill="#F4C542" />
                <path d="M299 137H327" stroke="#DDAA22" stroke-width="3" />
                <path d="M334 124 H346 V154 H332 V130Z" fill="#2196F3" />
                <rect x="335" y="119" width="9" height="7" rx="2" fill="#0A326B" />
              </g>
            </g>
  
            <!-- Orang -->
            <g class="sp-person">
              <g class="sp-leg-back">
                <path d="M180 194 L166 229 L148 252" class="sp-line" />
                <path
                  d="M144 250 C137 251 132 256 132 260 H157 C158 255 153 251 144 250Z"
                  fill="#0A326B"
                />
              </g>
  
              <g class="sp-leg-front">
                <path d="M181 194 L196 229 L211 251" class="sp-line" />
                <path
                  d="M207 248 C214 249 219 253 220 259 H195 C195 254 199 250 207 248Z"
                  fill="#0A326B"
                />
              </g>
  
              <path
                d="M156 112 C159 102 169 97 180 100 L196 106 C205 109 210 119 208 129 L198 178 C196 188 189 196 179 196 H169 C158 194 152 185 153 175 Z"
                class="sp-body"
              />
              <path
                d="M158 143 C171 148 187 148 201 143"
                fill="none"
                stroke="#8BC53F"
                stroke-width="6"
                stroke-linecap="round"
              />
              <path
                d="M177 119 C171 119 168 122 168 126 C168 130 171 132 177 133 C182 134 184 136 184 139 C184 143 181 145 176 145 H170"
                fill="none"
                stroke="#FFFFFF"
                stroke-width="4"
                stroke-linecap="round"
              />
  
              <circle cx="177" cy="78" r="27" class="sp-skin" />
              <path
                d="M151 75 C150 58 162 47 178 48 C193 48 203 58 202 72 C195 65 188 62 178 63 C168 63 160 67 151 75Z"
                fill="#0A326B"
              />
              <circle cx="202" cy="80" r="7" class="sp-skin" />
              <circle cx="190" cy="78" r="2.8" fill="#0A326B" />
              <path
                d="M195 82L199 84L195 86"
                fill="none"
                stroke="#9A5F43"
                stroke-width="2"
                stroke-linecap="round"
              />
              <path
                d="M188 91 Q194 96 199 91"
                fill="none"
                stroke="#0A326B"
                stroke-width="2.5"
                stroke-linecap="round"
              />
  
              <path d="M159 119 L143 153 L170 166" class="sp-line" />
              <path d="M199 119 L216 151 L241 143" class="sp-line" />
              <circle cx="241" cy="143" r="7" class="sp-skin" />
              <path
                d="M241 143 L254 125"
                fill="none"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linecap="round"
              />
            </g>
  
            <!-- Garis gerak -->
            <g fill="none" stroke="#8BC53F" stroke-width="4" stroke-linecap="round" opacity=".8">
              <path d="M67 205H91" />
              <path d="M55 218H74" />
              <path d="M72 232H96" />
            </g>
          </svg>
        </section>
  
        <!-- Referral -->
        <section class="relative z-10 -mt-8 bg-[#1B1749] px-4 py-3.5">
          <div class="flex items-center gap-3">
            <p class="flex-1 min-w-0 text-[12px] font-semibold text-white leading-snug">
              Dapatkan voucher belanja! Ajak teman pakai kode referral kamu:
            </p>
            <button
              type="button"
              class="shrink-0 h-9 px-4 rounded-lg bg-white text-[13px] font-extrabold text-(--color-on-surface) flex items-center gap-2 active:scale-95 transition-transform"
              @click="salin(KODE_REFERRAL, 'kode')"
            >
              {{ kodeTersalin ? 'Tersalin!' : KODE_REFERRAL }}
              <Icon name="send" class="w-3.5 h-3.5 text-(--color-azure)" />
            </button>
          </div>
        </section>
  
        <!-- Status -->
        <section class="bg-(--color-surface-0) px-4 py-4">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="flex items-center gap-1.5 text-left"
              @click="rincianTerbuka = !rincianTerbuka"
            >
              <span class="text-[15px] font-extrabold">{{ status.judul }}</span>
              <Icon
                name="chevron-down"
                class="w-4 h-4 text-(--color-on-surface-variant) transition-transform"
                :class="rincianTerbuka ? '' : '-rotate-90'"
              />
            </button>
            <span
              class="ml-auto shrink-0 text-[11.5px] font-bold text-(--color-azure) bg-(--color-azure)/10 rounded-full px-3 py-1 flex items-center gap-1"
            >
              <Icon name="clock" class="w-3 h-3" />
              25 - 45 menit
            </span>
          </div>
          <p v-if="rincianTerbuka" class="text-[12px] text-(--color-on-surface-variant) mt-1">
            {{ status.catatan }}
          </p>
  
          <div class="mt-3 bg-(--color-secondary-container)/40 rounded-xl p-3.5 flex items-start gap-3">
            <span class="text-lg shrink-0">🛡️</span>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-extrabold">Pesanan Anda Terlindungi Jaminan Garansi</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug mt-0.5">
                Cek pesanan saat tiba. Kalau ada kendala produk, hubungi CS 1x24 jam untuk dana kembali.
              </p>
            </div>
            <Icon name="chevron-right" class="w-4 h-4 text-(--color-outline) shrink-0 mt-1" />
          </div>
        </section>

        <!-- Driver ditemukan -->
        <section v-if="adaDriver" class="bg-(--color-surface-0) px-4 py-4">
          <div class="flex items-center gap-2 mb-3">
            <span class="w-2 h-2 rounded-full bg-(--color-secondary) animate-pulse"></span>
            <h2 class="text-[15px] font-extrabold">Driver Ditemukan...</h2>
          </div>

          <div class="flex items-center gap-3.5">
            <span class="w-14 h-14 rounded-full overflow-hidden bg-(--color-primary-container) shrink-0">
              <svg
                viewBox="30 14 104 104"
                preserveAspectRatio="xMidYMid slice"
                class="w-full h-full block"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
              >
                <defs>
                  <linearGradient id="drv-uniform" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2196F3" />
                    <stop offset="100%" stop-color="#0A326B" />
                  </linearGradient>
                  <linearGradient id="drv-skin" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFD2AE" />
                    <stop offset="100%" stop-color="#D58A65" />
                  </linearGradient>
                </defs>
                <circle cx="82" cy="51" r="30" fill="#26354A" />
                <path d="M61 48 C61 31 71 22 83 22 C98 22 106 33 105 48 C104 62 96 70 83 70 C70 70 62 61 61 48Z" fill="url(#drv-skin)" />
                <path d="M59 45 C60 26 71 17 84 18 C98 18 108 27 109 41 C101 36 95 31 89 27 C82 38 70 43 59 45Z" fill="#26354A" />
                <circle cx="105" cy="48" r="5" fill="#D58A65" />
                <path d="M70 42Q75 38 80 42" fill="none" stroke="#26354A" stroke-width="2" stroke-linecap="round" />
                <path d="M89 42Q94 38 99 42" fill="none" stroke="#26354A" stroke-width="2" stroke-linecap="round" />
                <circle cx="75" cy="47" r="2.5" fill="#0A326B" />
                <circle cx="94" cy="47" r="2.5" fill="#0A326B" />
                <circle cx="76" cy="46" r="0.8" fill="#FFFFFF" />
                <circle cx="95" cy="46" r="0.8" fill="#FFFFFF" />
                <path d="M84 48L82 55L86 56" fill="none" stroke="#B96E54" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M77 61Q84 66 91 61" fill="none" stroke="#963F42" stroke-width="2" stroke-linecap="round" />
                <path d="M76 68V80H94V68" fill="url(#drv-skin)" />
                <path d="M56 78 C65 72 73 72 85 75 C97 72 106 73 114 80 L128 137 H42Z" fill="url(#drv-uniform)" />
                <path d="M72 75L85 88L98 75" fill="#FFFFFF" />
                <path d="M78 77L85 85L92 77" fill="none" stroke="#8BC53F" stroke-width="3" />
                <g transform="translate(71 96)">
                  <rect x="0" y="0" width="29" height="29" rx="7" fill="#FFFFFF" />
                  <circle cx="14.5" cy="14.5" r="8" fill="none" stroke="#0A326B" stroke-width="2.5" />
                  <circle cx="14.5" cy="14.5" r="2" fill="#8BC53F" />
                  <path d="M14.5 6.5V12M7 11L12 14.5M22 11L17 14.5" fill="none" stroke="#0A326B" stroke-width="2" stroke-linecap="round" />
                </g>
                <path d="M57 82 C48 91 43 105 40 117 L29 128" fill="none" stroke="#0A326B" stroke-width="15" stroke-linecap="round" />
                <circle cx="27" cy="130" r="8" fill="url(#drv-skin)" />
                <path d="M112 83 C120 92 123 105 125 117 L135 128" fill="none" stroke="#0A326B" stroke-width="15" stroke-linecap="round" />
                <circle cx="137" cy="130" r="8" fill="url(#drv-skin)" />
                <path d="M47 137H121" stroke="#8BC53F" stroke-width="5" stroke-linecap="round" />
                <path d="M29 45 L32 52 L39 55 L32 58 L29 65 L26 58 L19 55 L26 52Z" fill="#C8FF00" />
                <path d="M129 52 L131 58 L137 61 L131 64 L129 70 L127 64 L121 61 L127 58Z" fill="#F4C542" />
                <circle cx="42" cy="76" r="3" fill="#3BBEB8" />
                <circle cx="124" cy="77" r="2.5" fill="#8BC53F" />
              </svg>
            </span>
            <div class="flex-1 min-w-0">
              <h3 class="text-[15px] font-extrabold truncate">{{ DRIVER_NAMA }}</h3>
              <div class="flex items-center gap-1 text-[12px] mt-0.5">
                <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
                <span v-if="driverRating != null" class="font-bold text-(--color-on-surface)">
                  {{ driverRating?.toFixed(1) }}
                </span>
                <span v-else class="text-(--color-on-surface-variant)">Belum ada penilaian</span>
              </div>
              <span
                class="inline-block mt-1.5 text-[13px] font-extrabold tracking-widest bg-(--color-surface-container) rounded-md px-2 py-0.5"
              >
                {{ DRIVER_PLAT }}
              </span>
            </div>

            <!-- Lacak driver: di samping nama driver -->
            <button
              type="button"
              class="shrink-0 self-start flex flex-col items-center gap-0.5 active:scale-95 transition-transform"
              @click="bukaLacak"
            >
              <span
                class="w-10 h-10 rounded-full bg-(--color-azure)/10 text-(--color-azure) flex items-center justify-center"
              >
                <Icon name="pin" class="w-5 h-5" />
              </span>
              <span class="text-[10px] font-bold text-(--color-azure)">Lacak</span>
            </button>
          </div>

          <button
            type="button"
            class="mt-4 w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform"
            @click="chatDriver"
          >
            <Icon name="chat" class="w-4 h-4" />
            Chat Live dengan Driver
          </button>
        </section>

        <!-- Lokasi tujuan -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[16px] font-extrabold mb-3">Lokasi Tujuan</h2>
          <p class="text-[13.5px] font-bold">{{ pesanan.toko || 'Toko belum dipilih' }}</p>
          <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mt-1">{{ pesanan.alamat }}</p>
          <div v-if="pesanan.catatan" class="flex items-start gap-2 mt-3">
            <Icon name="clipboard" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0 mt-0.5" />
            <p class="text-[12.5px] text-(--color-on-surface-variant)">{{ pesanan.catatan }}</p>
          </div>
          <p class="text-[13.5px] font-bold mt-4">Detail Penerima</p>
          <p class="text-[12.5px] text-(--color-on-surface-variant) mt-0.5">
            {{ pesanan.penerima }}<span v-if="pesanan.telepon">, {{ pesanan.telepon }}</span>
          </p>
        </section>
  
        <!-- Detil pesanan -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[16px] font-extrabold mb-4">Detil Pesanan</h2>
  
          <div class="flex items-center justify-between gap-3">
            <span class="text-[13px] text-(--color-on-surface-variant)">Metode Pembayaran</span>
            <span class="text-[13px] font-bold">{{ pesanan.metodePembayaran }}</span>
          </div>
  
          <div class="flex items-center gap-3 mt-3.5 pt-3.5 border-t border-(--color-outline)/12">
            <span class="flex-1 min-w-0 text-[12.5px] font-semibold break-all">{{ pesanan.invoice }}</span>
            <button
              type="button"
              class="shrink-0 text-[12px] font-bold text-(--color-azure) flex items-center gap-1 active:scale-95 transition-transform"
              @click="salin(pesanan.invoice, 'invoice')"
            >
              <Icon name="clipboard" class="w-3.5 h-3.5" />
              {{ invoiceTersalin ? 'Tersalin!' : 'Salin Invoice' }}
            </button>
          </div>
  
          <div class="flex items-center gap-3 mt-3.5 pt-3.5 border-t border-(--color-outline)/12">
            <span class="flex-1 text-[13px] font-bold">Pengiriman</span>
            <span class="text-[12.5px] font-semibold text-(--color-on-surface-variant) flex items-center gap-1">
              <Icon name="clock" class="w-3.5 h-3.5" />
              25 - 45 menit
            </span>
          </div>
  
          <ul class="mt-3 flex flex-col divide-y divide-(--color-outline)/12">
            <li v-for="item in pesanan.items" :key="item.id" class="py-3 flex items-start gap-3">
              <span class="text-[12.5px] font-bold text-(--color-on-surface-variant) shrink-0 w-7">
                {{ item.qty }}x
              </span>
              <span class="flex-1 min-w-0 text-[12.5px] leading-snug">{{ item.nama }}</span>
              <span class="text-[12.5px] font-bold shrink-0">
                {{ item.harga != null ? rp(item.harga * item.qty) : '—' }}
              </span>
            </li>
          </ul>
  
          <div class="mt-3 pt-3.5 border-t border-(--color-outline)/12 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Harga Barang</span>
              <span class="text-[13px] font-bold">{{ rp(pesanan.biaya.totalBarang) }}</span>
            </div>
            <div v-if="pesanan.biaya.potongan" class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Potongan Promo</span>
              <span class="text-[13px] font-bold text-(--color-secondary)">
                &minus;{{ rp(pesanan.biaya.potongan) }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Biaya Layanan</span>
              <span class="text-[13px] font-bold">{{ rp(pesanan.biaya.biayaLayanan) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Ongkir</span>
              <span class="text-[13px] font-bold flex items-center gap-1.5">
                <span
                  v-if="pesanan.biaya.ongkirGratis"
                  class="text-[11.5px] font-semibold text-(--color-on-surface-variant) line-through decoration-(--color-error)"
                >
                  {{ rp(pesanan.biaya.ongkirNormal) }}
                </span>
                <span :class="pesanan.biaya.ongkirGratis ? 'text-(--color-secondary)' : ''">
                  {{ pesanan.biaya.ongkirGratis ? 'Gratis' : rp(pesanan.biaya.ongkir) }}
                </span>
              </span>
            </div>
            <div class="pt-3 mt-1 border-t border-(--color-outline)/12 flex items-center justify-between">
              <span class="text-[14px] font-extrabold">Total</span>
              <span class="text-[15px] font-extrabold">{{ rp(pesanan.biaya.totalTagihan) }}</span>
            </div>
            <p
              v-if="pesanan.biaya.cashback"
              class="text-[11.5px] font-bold text-(--color-secondary) text-right"
            >
              Cashback {{ rp(pesanan.biaya.cashback) }} sudah masuk ke saldomu
            </p>
          </div>
        </section>
      </main>

      <!-- Lacak driver: peta lokasi tujuan (posisi driver menyusul dari server) -->
      <div v-if="lacakBuka" class="fixed inset-0 z-50 flex flex-col bg-(--color-surface)">
        <div class="flex items-center gap-3 px-5 py-4 bg-(--color-surface-0) border-b border-(--color-outline)/20">
          <button
            type="button"
            aria-label="Tutup"
            class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="tutupLacak"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <h3 class="text-[16px] font-extrabold flex-1">Lacak Driver</h3>
        </div>

        <div ref="lacakEl" class="flex-1 w-full"></div>

        <div class="px-5 py-4 bg-(--color-surface-0) border-t border-(--color-outline)/20">
          <p class="text-[13px] font-bold mb-2">{{ DRIVER_NAMA }} sedang menuju lokasimu</p>
          <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[11.5px] text-(--color-on-surface-variant)">
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-[#FF7A00]"></span>Tempat ambil pesanan
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-[#2E7D32]"></span>Posisi driver
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-(--color-azure)"></span>Lokasimu
            </span>
          </div>
        </div>
      </div>
    </div>
  </template>
</template>

<style scoped>
/*
 * Animasi ilustrasi "mitra sedang belanja".
 *
 * Kelasnya diberi awalan sp- dan ditaruh di <style scoped>, bukan di dalam
 * <defs> SVG. Nama asli seperti .body dan .line akan menjadi CSS global begitu
 * ada di dalam komponen Vue, dan itu cukup untuk mengacaukan seluruh aplikasi.
 */
.sp-body {
  fill: #2196f3;
}
.sp-skin {
  fill: #f2b58d;
}
.sp-line {
  fill: none;
  stroke: #0a326b;
  stroke-width: 8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sp-leg-back {
  transform-box: fill-box;
  transform-origin: 50% 0%;
  animation: sp-leg-back 0.65s ease-in-out infinite alternate;
}
.sp-leg-front {
  transform-box: fill-box;
  transform-origin: 50% 0%;
  animation: sp-leg-front 0.65s ease-in-out infinite alternate;
}
.sp-person {
  transform-box: fill-box;
  transform-origin: 50% 100%;
  animation: sp-walk 0.65s ease-in-out infinite;
}
.sp-cart {
  animation: sp-cart 0.65s ease-in-out infinite alternate;
}
.sp-items {
  animation: sp-items 0.65s ease-in-out infinite alternate;
}

@keyframes sp-leg-back {
  from {
    transform: rotate(18deg);
  }
  to {
    transform: rotate(-18deg);
  }
}
@keyframes sp-leg-front {
  from {
    transform: rotate(-18deg);
  }
  to {
    transform: rotate(18deg);
  }
}
@keyframes sp-walk {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3px);
  }
}
@keyframes sp-cart {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(3px);
  }
}
@keyframes sp-items {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(-2px);
  }
}

/* Animasi ini berjalan tanpa henti — hormati setelan "kurangi gerakan". */
@media (prefers-reduced-motion: reduce) {
  .sp-leg-back,
  .sp-leg-front,
  .sp-person,
  .sp-cart,
  .sp-items {
    animation: none;
  }
}
</style>
