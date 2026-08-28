<script setup lang="ts">
/**
 * Status pesanan BisaBersih.
 *
 * Dua keadaan dalam satu halaman:
 *
 * 1. MENUNGGU — belum ada cleaner yang menerima. Latar navy khas BisaBersih
 *    dengan animasi mencari. Pembayaran bersih memakai tunai, jadi tidak ada
 *    langkah bayar; yang ditunggu pengguna adalah orangnya.
 * 2. DITERIMA — sudah ada cleaner yang memegang pesanan. Berganti jadi layar
 *    terang berisi pelacakan, kartu cleaner, peta lokasi, dan detail pesanan.
 *
 * Yang menentukan pergantian adalah STATUS DI SERVER, bukan timer di browser:
 * halaman menanyakannya berkala dan berhenti begitu diterima. Kalau tidak ada
 * cleaner yang menerima, layar tunggu tetap layar tunggu — tidak berpura-pura
 * sudah dapat orang.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import LottieIcon from '@/components/LottieIcon.vue'
import CleanerAvatar from '@/components/bersih/CleanerAvatar.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import animasiMencari from '@/assets/lottie/bersih-mencari-cleaner.json'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'
import { ambilStatusPesananBersih, type StatusPesananBersih } from '@/api/bersih'
import { LABEL_METODE, labelMetode, type MetodeId } from '@/lib/metodeBayar'

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const pesanan = ref<StatusPesananBersih | null>(null)
const galat = ref<string | null>(null)

/* ---------------- Menanyakan status berkala ---------------- */

/**
 * Selang tanya ulang. Cukup renggang supaya tidak membebani server, tapi masih
 * terasa langsung bagi yang menunggu.
 */
const SELANG_MS = 4000
let timer: ReturnType<typeof setTimeout> | null = null

async function muat() {
  try {
    pesanan.value = await ambilStatusPesananBersih(nomor)
    galat.value = null
  } catch {
    // Sekali gagal bukan alasan menghentikan pemantauan — jaringan ponsel
    // sering putus sebentar. Pesan baru ditampilkan kalau belum pernah
    // berhasil sama sekali.
    if (!pesanan.value) galat.value = 'Gagal memuat status pesanan.'
  }
}

function jadwalkanTanya() {
  if (timer) clearTimeout(timer)
  // Sudah diterima berarti tidak ada lagi yang ditunggu di layar ini.
  if (pesanan.value?.diterima) return
  timer = setTimeout(async () => {
    await muat()
    jadwalkanTanya()
  }, SELANG_MS)
}

onMounted(async () => {
  await muat()
  jadwalkanTanya()
})

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
  tutupLacak()
})

/* ---------------- Tampilan ---------------- */

const diterima = computed(() => pesanan.value?.diterima === true)
const cleaner = computed(() => pesanan.value?.cleaner ?? null)

function rp(n: number) {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}

/** Metode dari server dijamin salah satu MetodeId hanya kalau dikenal; kalau tidak, ikon jatuh ke tampilan generik ('tunai'). */
const metodeIdAman = computed<MetodeId>(() => {
  const m = pesanan.value?.metode
  return m && m in LABEL_METODE ? (m as MetodeId) : 'tunai'
})

/** "-" selama belum ada ulasan: nol berarti belum dinilai, bukan nilai buruk. */
function nilai(n: number, pembanding = n) {
  return pembanding > 0 ? n.toLocaleString('id-ID') : '-'
}

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const jadwal = computed(() => {
  const iso = pesanan.value?.dijadwalkan_pada
  if (!iso) return null
  const mulai = new Date(iso)
  if (Number.isNaN(mulai.getTime())) return null

  const jam = pesanan.value?.durasi_jam ?? 0
  const selesai = new Date(mulai.getTime() + jam * 3600_000)
  const jj = (d: Date) =>
    String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')

  return {
    tanggal: `${HARI[mulai.getDay()]}, ${mulai.getDate()} ${BULAN[mulai.getMonth()]} ${mulai.getFullYear()}`,
    rentang: jam > 0 ? `${jj(mulai)} - ${jj(selesai)} WIB` : `${jj(mulai)} WIB`,
    mulai,
    selesai,
  }
})

/**
 * Ilustrasi pelacakan memakai SMIL (<animate> di dalam SVG). SMIL tidak bisa
 * dimatikan lewat CSS `animation: none`, jadi kalau pengguna memilih "kurangi
 * gerakan" di sistemnya, animasinya dihentikan lewat API SVG-nya sendiri.
 */
const svgLacak = ref<SVGSVGElement | null>(null)
onMounted(() => {
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    svgLacak.value?.pauseAnimations()
  }
})

const langkah = computed(() => {
  const s = pesanan.value?.status
  if (s === 'completed') return 3
  if (s === 'accepted' || s === 'in_progress') return 2
  return 1
})

/* ---------------- Lacak cleaner (peta rute menuju rumah Anda) ---------------- */
/**
 * Rumah Anda = alamat pesanan, koordinatnya SUDAH ASLI (dikirim server saat
 * checkout). Titik keberangkatan & posisi cleaner di jalan masih DISIMULASIKAN
 * — server belum mengirim GPS mitra secara langsung. Begitu tersedia, dua
 * titik itu tinggal diganti data asli tanpa mengubah tampilan.
 */
const lacakBuka = ref(false)
const lacakEl = ref<HTMLDivElement | null>(null)
let lacakPetaFull: L.Map | null = null

const pinRumahAnda = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 40],
})

const ikonCleanerJalan = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 40 40" width="40" height="40" style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3))"><circle cx="20" cy="20" r="15" fill="#63C21C" stroke="#fff" stroke-width="3"/><path d="M20 11 l2.2 5.4 5.6 0.8 -4 3.9 1 5.6 -4.8 -2.8 -4.8 2.8 1 -5.6 -4 -3.9 5.6 -0.8Z" fill="#fff"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 20],
})

/** Rute jalan A→B dari OSRM (mengikuti jalan), atau null kalau gagal. */
async function ambilRuteJalan(a: L.LatLngTuple, b: L.LatLngTuple): Promise<L.LatLngTuple[] | null> {
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
  const lok = pesanan.value?.lokasi
  if (!lok?.lat || !lok?.lng) return

  lacakBuka.value = true
  await nextTick()
  if (!lacakEl.value || lacakPetaFull) return

  const tujuan: L.LatLngTuple = [lok.lat, lok.lng]
  const asal: L.LatLngTuple = [tujuan[0] - 0.012, tujuan[1] + 0.014]

  lacakPetaFull = L.map(lacakEl.value, { center: tujuan, zoom: 14, zoomControl: false })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(lacakPetaFull)
  requestAnimationFrame(() => lacakPetaFull?.invalidateSize())

  const rute = (await ambilRuteJalan(asal, tujuan)) ?? [asal, tujuan]
  if (!lacakPetaFull) return

  const garis = L.polyline(rute, {
    color: '#63C21C',
    weight: 6,
    opacity: 0.95,
    lineCap: 'round',
    lineJoin: 'round',
  }).addTo(lacakPetaFull)

  L.marker(tujuan, { icon: pinRumahAnda }).addTo(lacakPetaFull)

  // Posisi cleaner di ~40% panjang rute — sedang dalam perjalanan, bukan di titik awal.
  const posCleaner: L.LatLngTuple =
    rute.length > 2 ? rute[Math.floor(rute.length * 0.4)] : [(asal[0] + tujuan[0]) / 2, (asal[1] + tujuan[1]) / 2]
  L.marker(posCleaner, { icon: ikonCleanerJalan }).addTo(lacakPetaFull)

  lacakPetaFull.fitBounds(garis.getBounds(), { padding: [50, 50] })
}

function tutupLacak() {
  lacakBuka.value = false
  if (lacakPetaFull) {
    lacakPetaFull.remove()
    lacakPetaFull = null
  }
}

/* ---------------- Aksi ---------------- */
function kembali() {
  router.replace({ name: 'home' })
}

/** Dari layar tunggu, kembali langsung ke daftar tugas (/tasks/mine). */
function keDaftarTugas() {
  router.replace({ name: 'task-list' })
}

function keChat() {
  if (!pesanan.value) return
  router.push({ name: 'task-chat', params: { id: pesanan.value.task_id } })
}
</script>

<template>
  <!-- ============ MENUNGGU ============ -->
  <div
    v-if="!diterima"
    class="relative min-h-dvh w-full bg-[#0D1B47] text-white flex items-center justify-center px-6 py-10"
  >
    <!-- Kembali ke daftar tugas -->
    <button
      type="button"
      aria-label="Kembali ke daftar tugas"
      class="absolute top-4 left-4 w-10 h-10 rounded-full flex items-center justify-center text-white/90 bg-white/10 active:scale-95 transition-transform"
      @click="keDaftarTugas"
    >
      <Icon name="arrow-left" class="w-5 h-5" />
    </button>

    <div class="w-full max-w-[420px] text-center">
      <LottieIcon :data="animasiMencari" :width="300" :height="210" class="mx-auto" />

      <h2 class="mt-1 text-[21px] font-extrabold text-white">
        Menunggu <span class="text-[#8FDD3A]">Cleaner</span>...
      </h2>
      <p class="mt-1.5 text-[13px] text-white/60">
        <template v-if="cleaner">
          Menunggu {{ cleaner.nama }} menerima pesananmu
        </template>
        <template v-else>Kami sedang mencarikan petugas terdekat untukmu</template>
      </p>

      <!-- Bar progres tak tentu (indeterminate) -->
      <div class="mt-5 h-2 rounded-full bg-white/10 overflow-hidden">
        <span class="block h-full w-[45%] rounded-full bg-gradient-to-r from-[#C2F55A] to-[#63C21C] bar-run"></span>
      </div>

      <p v-if="nomor" class="mt-5 text-[11.5px] text-white/45">
        No. pesanan <span class="font-bold text-white/70">{{ nomor }}</span>
      </p>

      <p v-if="galat" class="mt-4 text-[12px] text-[#FFB4AB]">{{ galat }}</p>
    </div>
  </div>

  <!-- ============ DITERIMA ============ -->
  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Status Pesanan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <!--
        Pelacakan: ilustrasi beranimasi (SMIL di dalam SVG, jalan sendiri tanpa
        pustaka). Semua id di <defs> diberi awalan trk- supaya tidak bentrok
        dengan SVG lain di halaman ini.
      -->
      <section class="bg-(--color-surface-0) rounded-2xl overflow-hidden">
        <svg
          ref="svgLacak"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 400 240"
          class="w-full h-auto block"
          role="img"
          aria-label="Cleaner sedang dalam perjalanan menuju rumah Anda"
        >
          <defs>
            <linearGradient id="trk-sky" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#EAF8FF" />
              <stop offset="100%" stop-color="#FFFFFF" />
            </linearGradient>
            <linearGradient id="trk-uniform" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#2196F3" />
              <stop offset="100%" stop-color="#0A326B" />
            </linearGradient>
            <linearGradient id="trk-skin" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#FFD5B5" />
              <stop offset="100%" stop-color="#D88C68" />
            </linearGradient>
            <filter id="trk-shadow" x="-30%" y="-30%" width="160%" height="160%">
              <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0A326B" flood-opacity=".18" />
            </filter>
          </defs>

          <rect width="400" height="240" rx="20" fill="url(#trk-sky)" />

          <!-- Matahari -->
          <circle cx="48" cy="45" r="18" fill="#FFD43B" opacity=".9">
            <animate attributeName="r" values="17;20;17" dur="3s" repeatCount="indefinite" />
          </circle>
          <g stroke="#FFD43B" stroke-width="3" stroke-linecap="round" opacity=".7">
            <path d="M48 17V10" />
            <path d="M48 73V80" />
            <path d="M20 45H13" />
            <path d="M76 45H83" />
            <path d="M28 25L23 20" />
            <path d="M68 65L73 70" />
            <path d="M68 25L73 20" />
            <path d="M28 65L23 70" />
          </g>

          <!-- Awan -->
          <g fill="#FFFFFF" opacity=".9">
            <path d="M105 42 C105 35 111 30 118 30 C124 30 129 34 131 39 C134 35 139 34 143 34 C150 34 155 39 155 46 H105Z">
              <animateTransform attributeName="transform" type="translate" values="0 0;8 0;0 0" dur="7s" repeatCount="indefinite" />
            </path>
          </g>

          <!-- Rumah customer -->
          <g transform="translate(270 65)" filter="url(#trk-shadow)">
            <rect x="15" y="48" width="92" height="72" rx="4" fill="#FFFFFF" />
            <path d="M5 52 L61 8 L117 52 Z" fill="#0A326B" />
            <path d="M16 49L61 14L106 49" fill="none" stroke="#C8FF00" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
            <rect x="52" y="79" width="25" height="41" rx="3" fill="#2196F3" />
            <circle cx="71" cy="100" r="2" fill="#FFD43B" />
            <rect x="25" y="68" width="22" height="22" rx="3" fill="#3BBEB8" />
            <path d="M36 68V90M25 79H47" stroke="#FFFFFF" stroke-width="2" />
            <g transform="translate(82 55)">
              <path d="M13 0 C6 0 1 5 1 12 C1 21 13 30 13 30 C13 30 25 21 25 12 C25 5 20 0 13 0Z" fill="#C8FF00" />
              <circle cx="13" cy="11" r="4" fill="#0A326B" />
              <animateTransform attributeName="transform" type="translate" values="82 55;82 51;82 55" dur="1.8s" repeatCount="indefinite" />
            </g>
          </g>

          <!-- Tanah & jalan -->
          <path d="M0 177 C80 169 160 169 240 177 C300 183 350 180 400 174 V240 H0Z" fill="#E8F7F5" />
          <path d="M0 204 C95 190 190 191 280 202 C330 208 365 207 400 201" fill="none" stroke="#D5E4EA" stroke-width="22" stroke-linecap="round" />
          <path d="M0 204 C95 190 190 191 280 202 C330 208 365 207 400 201" fill="none" stroke="#C8FF00" stroke-width="3" stroke-linecap="round" stroke-dasharray="12 12">
            <animate attributeName="stroke-dashoffset" values="0;-24" dur="1s" repeatCount="indefinite" />
          </path>

          <!-- Cleaner berjalan -->
          <g filter="url(#trk-shadow)">
            <g>
              <animateTransform attributeName="transform" type="translate" values="0 0;7 -2;14 0" dur="3.5s" fill="freeze" />
              <circle cx="128" cy="101" r="20" fill="url(#trk-skin)" />
              <path d="M109 99 C108 84 117 77 128 77 C140 77 148 85 148 98 C141 94 135 89 131 86 C125 94 117 98 109 99Z" fill="#26354A" />
              <circle cx="147" cy="102" r="4" fill="#D88C68" />
              <circle cx="122" cy="103" r="2" fill="#0A326B" />
              <circle cx="136" cy="103" r="2" fill="#0A326B" />
              <path d="M124 113Q129 117 135 113" fill="none" stroke="#963F42" stroke-width="1.8" stroke-linecap="round" />
              <rect x="123" y="117" width="12" height="12" rx="3" fill="url(#trk-skin)" />
              <path d="M108 127 C115 123 121 123 129 127 C137 123 144 124 150 128 L160 174 H99Z" fill="url(#trk-uniform)" />
              <path d="M120 126L129 138L138 126" fill="#FFFFFF" />
              <g transform="translate(122 143)">
                <rect width="17" height="17" rx="4" fill="#FFFFFF" />
                <path d="M4 9L8.5 5L13 9" fill="none" stroke="#0A326B" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5 8.5V13H12V8.5" fill="none" stroke="#0A326B" stroke-width="1.6" />
                <path d="M13 3L14 5L16 6L14 7L13 9L12 7L10 6L12 5Z" fill="#C8FF00" />
              </g>
              <path d="M146 130 C154 137 158 146 161 157" fill="none" stroke="#0A326B" stroke-width="11" stroke-linecap="round" />
              <path d="M112 130 C103 137 99 148 96 157" fill="none" stroke="#0A326B" stroke-width="11" stroke-linecap="round" />
              <circle cx="161" cy="159" r="6" fill="url(#trk-skin)" />
              <circle cx="95" cy="159" r="6" fill="url(#trk-skin)" />
              <g transform="translate(84 148)">
                <rect x="0" y="7" width="18" height="22" rx="4" fill="#C8FF00" />
                <path d="M5 8V5 C5 1 13 1 13 5V8" fill="none" stroke="#0A326B" stroke-width="2" />
                <path d="M4 16H14" stroke="#0A326B" stroke-width="2" />
              </g>
              <path d="M104 173H128L124 201H104Z" fill="#0A326B" />
              <path d="M130 173H151L156 201H134Z" fill="#0A326B" />
              <path d="M103 198H126 C129 199 130 204 126 206 H101 C98 204 100 200 103 198Z" fill="#26354A" />
              <path d="M135 198H155 C159 199 160 204 156 206 H133 C131 204 132 200 135 198Z" fill="#26354A" />
              <animateTransform attributeName="transform" type="rotate" values="0 128 173;2 128 173;0 128 173;-2 128 173;0 128 173" dur="1.2s" repeatCount="indefinite" />
            </g>
          </g>

          <!-- Kilau & panah -->
          <g fill="#C8FF00">
            <path d="M205 115L208 122L215 125L208 128L205 135L202 128L195 125L202 122Z">
              <animateTransform attributeName="transform" type="translate" values="0 4;0 -8;0 4" dur="2s" repeatCount="indefinite" />
              <animate attributeName="opacity" values=".3;1;.3" dur="2s" repeatCount="indefinite" />
            </path>
          </g>
          <g fill="#3BBEB8">
            <circle cx="230" cy="150" r="3">
              <animate attributeName="cy" values="150;143;150" dur="2.3s" repeatCount="indefinite" />
            </circle>
            <circle cx="214" cy="95" r="2.5">
              <animate attributeName="cy" values="95;89;95" dur="1.8s" repeatCount="indefinite" />
            </circle>
          </g>
          <g transform="translate(247 168)">
            <path d="M0 0H20" stroke="#8BC53F" stroke-width="3" stroke-linecap="round" />
            <path d="M14 -6L20 0L14 6" fill="none" stroke="#8BC53F" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <animate attributeName="opacity" values=".3;1;.3" dur="1.5s" repeatCount="indefinite" />
          </g>
        </svg>
      </section>

      <!-- Cleaner yang menerima -->
      <section
        v-if="cleaner"
        class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/15"
      >
        <!-- Info: cleaner sedang menuju rumah Anda -->
        <div
          v-if="langkah < 3"
          class="flex items-center gap-2.5 mb-4 rounded-xl border border-(--color-azure) bg-(--color-azure)/8 px-3.5 py-2.5"
        >
          <span class="relative shrink-0 w-2 h-2">
            <span class="absolute inset-0 rounded-full bg-(--color-azure) animate-ping"></span>
            <span class="absolute inset-0 rounded-full bg-(--color-azure)"></span>
          </span>
          <p class="text-[12.5px] font-bold text-(--color-on-surface)">
            {{ cleaner.nama }} sedang menuju rumah Anda
          </p>
        </div>

        <div class="flex items-center gap-4 mb-4">
          <div class="relative shrink-0">
            <CleanerAvatar
              :gender="cleaner.gender ?? undefined"
              :nama="cleaner.nama"
              class="w-16 h-16"
            />
            <span
              class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-(--color-surface-0) flex items-center justify-center"
            >
              <Icon name="check-circle" class="w-4 h-4 text-(--color-azure)" />
            </span>
          </div>

          <div class="flex-1 min-w-0">
            <h3 class="text-[16px] font-extrabold truncate">{{ cleaner.nama }}</h3>
            <p class="text-[12.5px] text-(--color-on-surface-variant)">
              Cleaner {{ cleaner.nama_level }}
            </p>
            <div class="flex items-center gap-2 mt-1 text-[12px] text-(--color-on-surface-variant)">
              <span class="flex items-center gap-0.5">
                <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
                <span class="font-bold text-(--color-on-surface)">
                  {{ nilai(cleaner.rating, cleaner.jumlah_ulasan) }}
                </span>
              </span>
              <span class="w-1 h-1 rounded-full bg-(--color-outline)/50"></span>
              <span>{{ nilai(cleaner.order_selesai) }} tugas selesai</span>
            </div>
          </div>

          <!-- Lacak cleaner: di samping nama. Tanpa ikon, border biru, teks putih. -->
          <button
            v-if="langkah < 3"
            type="button"
            class="shrink-0 self-start rounded-full border border-(--color-azure) bg-(--color-azure) text-white text-[12px] font-bold px-4 py-1.5 active:scale-95 transition-transform"
            @click="bukaLacak"
          >
            Lacak
          </button>
        </div>

        <!-- Satu tombol saja, seperti BisaBelanja: chat langsung dengan cleaner. -->
        <button
          type="button"
          class="w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform"
          @click="keChat"
        >
          <Icon name="chat" class="w-4 h-4" />
          Chat Live dengan Cleaner
        </button>
      </section>

      <!-- Detail pesanan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-5">
          <h2 class="text-[15px] font-display font-extrabold">Detail Pesanan</h2>
          <span
            class="text-[11px] font-bold text-(--color-on-surface-variant) shrink-0"
          >
            {{ nomor }}
          </span>
        </div>

        <div class="flex flex-col gap-5">
          <!-- Layanan -->
          <div class="flex items-start gap-3.5">
            <span
              class="w-10 h-10 flex items-center justify-center shrink-0 text-(--color-on-primary-container)"
            >
              <Icon name="sparkle" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">Layanan</span>
              <h4 class="text-[14px] font-bold">
                Bersih Rumah ({{ pesanan?.durasi_jam }} jam × {{ pesanan?.jumlah_cleaner }})
              </h4>
              <p
                v-if="pesanan?.area.length"
                class="text-[12.5px] text-(--color-on-surface-variant) mt-0.5 leading-snug"
              >
                {{ pesanan.area.join(', ') }}
              </p>
            </div>
          </div>

          <!-- Jadwal -->
          <div v-if="jadwal" class="flex items-start gap-3.5">
            <span
              class="w-10 h-10 flex items-center justify-center shrink-0 text-(--color-on-secondary-container)"
            >
              <Icon name="calendar" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">Jadwal</span>
              <h4 class="text-[14px] font-bold">{{ jadwal.tanggal }}</h4>
              <h4 class="text-[14px] font-bold">{{ jadwal.rentang }}</h4>
            </div>
          </div>

          <!-- Lokasi -->
          <div class="flex items-start gap-3.5">
            <span
              class="w-10 h-10 flex items-center justify-center shrink-0 text-(--color-on-surface)"
            >
              <Icon name="pin" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">Lokasi</span>
              <p class="text-[13px] leading-relaxed">{{ pesanan?.lokasi.alamat }}</p>
              <p
                v-if="pesanan?.catatan"
                class="text-[12.5px] text-(--color-on-surface-variant) mt-1 leading-snug"
              >
                Catatan: {{ pesanan.catatan }}
              </p>
            </div>
          </div>

          <!-- Metode pembayaran: logo asli, bukan sekadar teks -->
          <div class="flex items-center gap-3.5">
            <MetodeBayarIcon :id="metodeIdAman" />
            <div class="min-w-0">
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">Metode Pembayaran</span>
              <h4 class="text-[14px] font-bold">{{ labelMetode(pesanan?.metode ?? 'tunai') }}</h4>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="text-[13px] text-(--color-on-surface-variant)">Total Pembayaran</span>
            <span class="text-[17px] font-extrabold">{{ rp(pesanan?.total ?? 0) }}</span>
          </div>
        </div>
      </section>
    </main>

    <!-- Lacak cleaner: rute menuju rumah Anda, layar penuh -->
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
        <h3 class="text-[16px] font-extrabold flex-1">Lacak Cleaner</h3>
      </div>

      <div ref="lacakEl" class="isolate flex-1 w-full"></div>

      <div class="px-5 py-4 bg-(--color-surface-0) border-t border-(--color-outline)/20">
        <p class="text-[13px] font-bold mb-2">
          {{ cleaner?.nama }} sedang menuju rumah Anda
        </p>
        <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[11.5px] text-(--color-on-surface-variant)">
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-[#63C21C]"></span>Posisi cleaner
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-(--color-azure)"></span>Rumah Anda
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bar-run {
  animation: bar-run 2.4s ease-in-out infinite;
}
@keyframes bar-run {
  0% {
    margin-left: -45%;
  }
  100% {
    margin-left: 100%;
  }
}

/*
 * Hormati pengguna yang mematikan animasi di sistemnya. Ilustrasi pelacakan
 * memakai SMIL, yang tidak bisa dihentikan lewat CSS — itu diurus di script
 * dengan pauseAnimations().
 */
@media (prefers-reduced-motion: reduce) {
  .bar-run {
    animation: none;
  }
}
</style>
