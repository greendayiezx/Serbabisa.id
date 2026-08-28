<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { reverseGeocode } from '@/lib/geocode'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PromoBersihArt from '@/components/bersih/PromoBersihArt.vue'
import CleanerAvatar from '@/components/bersih/CleanerAvatar.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useLocationStore } from '@/stores/location'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'
import {
  ADD_ON,
  DURASI_REKOMENDASI,
  FREKUENSI,
  PILIHAN_DURASI,
  TARIF_TERENDAH_PER_JAM,
  hitungHarga,
} from '@/lib/bersih/hargaBersih'
import { FIRST_CLEAN } from '@/lib/promo/promoBersih'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import { usePromoBersihStore } from '@/stores/promoBersih'
import { useCleanerBersihStore } from '@/stores/cleanerBersih'
import { kirimPesananBersih } from '@/api/bersih'
import { pesanError } from '@/api/belanja'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const locationStore = useLocationStore()
const authStore = useAuthStore()
const promoStore = usePromoBersihStore()

/* ---------------- Pilihan pengguna ---------------- */
const adaHewan = ref(false)

const AREA = ['Ruang Tamu', 'Kamar Tidur', 'Kamar Mandi', 'Dapur', 'Lainnya']
const areaDipilih = ref<string[]>(AREA.filter((a) => a !== 'Lainnya'))
/** Isian bebas saat "Lainnya" dicentang. */
const areaLainnya = ref('')

/**
 * Cleaner: 'cepat' = siapa pun yang tersedia lebih dulu.
 *
 * Pilihan cleaner disimpan di store (bertahan lewat localStorage) supaya tetap
 * terbawa saat pengguna membuka halaman daftar lengkap ("Lihat Semua") lalu
 * kembali — komponen ini di-unmount saat navigasi, jadi ref lokal tidak cukup.
 * Kalau sudah ada pilihan tersimpan, mode langsung dibuka di "Pilih Sendiri".
 */
const cleanerStore = useCleanerBersihStore()
const modeCleaner = ref<'cepat' | 'pilih'>(cleanerStore.dipilih ? 'pilih' : 'cepat')
const cleanerDipilih = computed({
  get: () => cleanerStore.dipilih,
  set: (v) => cleanerStore.set(v),
})

/**
 * Pratinjau cleaner di halaman ini: tiga teratas saja, biar ringkas — sisanya
 * dibuka lewat "Lihat Semua". Kalau pilihan pengguna ada di luar tiga teratas,
 * kartunya tetap ikut ditampilkan supaya pilihan yang aktif selalu terlihat.
 */
const cleanerRingkas = computed(() => {
  const semua = cleanerStore.daftar
  const teratas = semua.slice(0, 3)
  const terpilih = semua.find((c) => c.id === cleanerDipilih.value)
  return terpilih && !teratas.includes(terpilih) ? [...teratas, terpilih] : teratas
})

/**
 * Harga per jam yang dipakai menghitung tagihan — SUDAH termasuk markup
 * platform. Kalau pengguna tidak memilih cleaner tertentu, yang berlaku adalah
 * tarif level terendah: sistem baru menentukan orangnya setelah pesanan masuk,
 * jadi menagih tarif level tinggi di muka tidak jujur.
 *
 * Angkanya diambil dari server; konstanta lokal hanya jaring pengaman saat
 * daftar belum sempat termuat, dan server tetap menghitung ulang tagihannya.
 */
const hargaPerJam = computed(() => {
  if (modeCleaner.value === 'pilih') {
    const c = cleanerStore.daftar.find((x) => x.id === cleanerDipilih.value)
    if (c) return c.harga_per_jam
  }
  return cleanerStore.hargaTerendahPerJam || TARIF_TERENDAH_PER_JAM
})

/**
 * Tampilan angka performa cleaner.
 *
 * Selama `pembanding` masih nol (belum ada ulasan), yang tampil adalah "-"
 * alih-alih angka nol — supaya tidak terbaca sebagai penilaian buruk.
 */
function nilai(angka: number, pembanding = angka) {
  return pembanding > 0 ? String(angka) : '-'
}

const addOnDipilih = ref<string[]>([])
const jumlahCleaner = ref(1)
const durasiJam = ref(3)
const frekuensiId = ref('sekali')
const catatan = ref('')

/* ---------------- Jadwal ---------------- */

const NAMA_BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

/** Kepala kolom kalender. Pekan dimulai Senin, bukan Minggu. */
const KEPALA_HARI = ['S', 'S', 'R', 'K', 'J', 'S', 'M']

/** Jam kerja cleaner. Di luar ini tidak ada mitra yang bisa ditugaskan. */
const JAM_MIN = 6
const JAM_MAKS = 21

const hariIni = new Date()
const kunciTanggal = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const tanggalDipilih = ref(kunciTanggal(hariIni))
const bulanTampil = ref(new Date(hariIni.getFullYear(), hariIni.getMonth(), 1))

/**
 * Waktu mulai dipilih lewat TimePickerField (panel bottom-sheet), tiap jam penuh
 * dalam jam kerja cleaner (JAM_MIN..JAM_MAKS). Formatnya "HH:MM", default 10.00.
 */
const jamDipilih = ref('10:00')

const judulBulan = computed(
  () => `${NAMA_BULAN[bulanTampil.value.getMonth()]} ${bulanTampil.value.getFullYear()}`,
)

/**
 * Sel kalender satu bulan penuh.
 *
 * Bulan tidak selalu mulai hari Senin, jadi sel kosong di depan dipakai supaya
 * tiap tanggal jatuh di kolom harinya yang benar. `(getDay() + 6) % 7` menggeser
 * penomoran bawaan JavaScript (Minggu = 0) ke pekan yang dimulai Senin.
 */
const selKalender = computed(() => {
  const y = bulanTampil.value.getFullYear()
  const m = bulanTampil.value.getMonth()
  const geser = (new Date(y, m, 1).getDay() + 6) % 7
  const jumlahHari = new Date(y, m + 1, 0).getDate()

  const sel: Array<{ iso: string; tanggal: number; lampau: boolean } | null> = Array(geser).fill(null)
  const batas = kunciTanggal(hariIni)

  for (let t = 1; t <= jumlahHari; t++) {
    const iso = kunciTanggal(new Date(y, m, t))
    sel.push({ iso, tanggal: t, lampau: iso < batas })
  }
  return sel
})

/** Bulan lalu tidak bisa dibuka kalau seluruh tanggalnya sudah lewat. */
const bisaMundur = computed(() => {
  const b = bulanTampil.value
  return b.getFullYear() > hariIni.getFullYear() || b.getMonth() > hariIni.getMonth()
})

function pindahBulan(delta: number) {
  if (delta < 0 && !bisaMundur.value) return
  const b = bulanTampil.value
  bulanTampil.value = new Date(b.getFullYear(), b.getMonth() + delta, 1)
}

/* ---------------- Promo ---------------- */

/**
 * Promo yang dipakai halaman ini.
 *
 * Kalau pengguna sudah memilih promo di halaman promo, ITU yang dipakai — data
 * aslinya (kode, minimum, nilai) dibaca dari katalog, bukan ditulis ulang di
 * sini. Kalau belum memilih, promo pengguna baru terbesar yang layak dipasang
 * otomatis sebagai penawaran awal.
 */
const promoPilihan = computed(() => promoStore.voucher())

const promoOtomatis = computed(() => {
  if (promoPilihan.value) return null
  return (
    [...FIRST_CLEAN.voucher]
      .filter((v) => nilaiSebelumPromo.value >= v.minTransaksi)
      .sort((a, b) => (b.potongan ?? 0) - (a.potongan ?? 0))[0] ?? null
  )
})

const promoTerpakai = computed(() => promoPilihan.value ?? promoOtomatis.value)

/** Kurang berapa lagi supaya promo pilihan bisa dipakai. Nol berarti sudah bisa. */
const kurangUntukPromo = computed(() => {
  const v = promoPilihan.value
  if (!v) return 0
  return Math.max(0, v.minTransaksi - nilaiSebelumPromo.value)
})

/** Cashback tidak memotong tagihan sekarang — ia jadi saldo setelah selesai. */
const potonganAktif = computed(() => {
  const v = promoTerpakai.value
  if (!v || kurangUntukPromo.value > 0) return 0
  return v.potongan ?? 0
})

const cashbackAktif = computed(() => {
  const v = promoTerpakai.value
  if (!v?.cashbackPersen || kurangUntukPromo.value > 0) return 0
  return Math.min(
    Math.round((nilaiSebelumPromo.value * v.cashbackPersen) / 100),
    v.cashbackMaks ?? Infinity,
  )
})

function keHalamanPromo() {
  // Jalur halaman ini dibawa serta supaya tombol kembali di halaman promo
  // memulangkan pengguna ke sini — lengkap dengan pilihan layanannya — bukan
  // ke halaman layanan yang membuat isian pesanan terlihat hilang.
  router.push({ name: 'task-bersih-promo', query: { dari: route.fullPath } })
}

const konfig = computed(() => ({
  hargaPerJam: hargaPerJam.value,
  // Kondisi ruangan tidak lagi ditanyakan; mesin harga tetap menerimanya supaya
  // bisa dihidupkan lagi tanpa mengubah rumus.
  kondisiId: 'normal',
  durasiJam: durasiJam.value,
  jumlahCleaner: jumlahCleaner.value,
  addOnDipilih: addOnDipilih.value,
  frekuensiId: frekuensiId.value,
}))

/** Nilai transaksi sebelum promo — dasar pengujian syarat minimum. */
const nilaiSebelumPromo = computed(() => hitungHarga(konfig.value).nilaiTransaksi)

const rincian = computed(() => hitungHarga(konfig.value, potonganAktif.value))

const frekuensiAktif = computed(() => FREKUENSI.find((f) => f.id === frekuensiId.value) ?? FREKUENSI[0])

/* ---------------- Aksi ---------------- */
function toggle(daftar: string[], nilai: string) {
  const i = daftar.indexOf(nilai)
  if (i >= 0) daftar.splice(i, 1)
  else daftar.push(nilai)
}

/**
 * Daftar area yang dikirim ke server.
 *
 * "Lainnya" bukan nama area — ia digantikan isian bebas pengguna. Kalau
 * dicentang tapi kolomnya kosong, pilihan itu diabaikan supaya mitra tidak
 * menerima instruksi bernama "Lainnya" yang tidak berarti apa-apa.
 */
const areaTerkirim = computed(() => {
  const dasar = areaDipilih.value.filter((a) => a !== 'Lainnya')
  const bebas = areaLainnya.value.trim()
  if (areaDipilih.value.includes('Lainnya') && bebas) dasar.push(bebas)
  return dasar
})

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

const kembali = useKembali()

/* ---------------- Peta lokasi (embed + pemilih) ---------------- */
/**
 * Sama seperti AngkutConfirmView: peta pratinjau statis tampil langsung di
 * halaman ini, dan penyuntingan pin dibuka sebagai overlay layar penuh — jadi
 * lokasi pengerjaan bisa diubah tanpa berpindah ke halaman pemilih lokasi.
 * Titik jatuh ke pusat Jakarta kalau belum ada alamat yang dipilih.
 */
const alamat = computed(() => locationStore.draft?.alamat ?? '')
const alamatTitle = computed(() => alamat.value.split(',')[0] ?? alamat.value)
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)

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

const pickerOpen = ref(false)
const pickerMapEl = ref<HTMLDivElement | null>(null)
let pickerMap: L.Map | null = null
let pickerMarker: L.Marker | null = null
let pickerResizeObserver: ResizeObserver | null = null

/*
 * Penerjemahan koordinat memakai lib bersama: ia meminta nama TEMPAT (gedung,
 * mal, kantor) lebih dulu, baru alamatnya — salinan lokal yang dulu ada di sini
 * hanya pernah mengembalikan nama jalan.
 */

// Cegah Leaflet menyisakan peta usang yang masih terikat ke node DOM (misalnya
// setelah HMR menukar komponen atau pemilih dibuka dua kali sebelum ditutup).
function resetStaleLeafletContainer(el: HTMLElement) {
  if ((el as unknown as { _leaflet_id?: number })._leaflet_id) {
    el.innerHTML = ''
    delete (el as unknown as { _leaflet_id?: number })._leaflet_id
  }
}

// Penjaga re-entry: openPicker() menunggu nextTick() sebelum menyentuh DOM, jadi
// panggilan kedua (mis. ketukan ganda) tidak boleh membuat dua instance peta.
let openingPicker = false

async function openPicker() {
  if (openingPicker) return
  openingPicker = true
  try {
    pickerOpen.value = true
    pickerResizeObserver?.disconnect()
    pickerResizeObserver = null
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
    pickerMarker = L.marker(center, { icon: pinIcon, draggable: true, autoPan: true }).addTo(thisMap)

    thisMap.on('click', (e: L.LeafletMouseEvent) => {
      pickerMarker?.setLatLng(e.latlng)
      thisMap.panTo(e.latlng, { animate: true, duration: 0.3 })
    })

    pickerResizeObserver = new ResizeObserver(() => thisMap.invalidateSize())
    pickerResizeObserver.observe(pickerMapEl.value)
    requestAnimationFrame(() => thisMap.invalidateSize())
  } finally {
    openingPicker = false
  }
}

function closePicker() {
  pickerOpen.value = false
  pickerResizeObserver?.disconnect()
  pickerResizeObserver = null
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

onMounted(async () => {
  await nextTick()
  initMap()
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  if (map) {
    map.remove()
    map = null
  }
  closePicker()
})

/** Buka daftar lengkap cleaner. */
function lihatSemuaCleaner() {
  router.push({ name: 'task-bersih-cleaner' })
}

// Daftar cleaner ditarik sekali saat halaman dibuka. Kegagalannya tidak
// menghalangi pemesanan: tanpa pilihan, tarif level terendah yang berlaku.
onMounted(() => {
  void cleanerStore.muat()
})

const memproses = ref(false)
const galat = ref<string | null>(null)

/* ---------------- Metode pembayaran ---------------- */
/**
 * Metode dipilih di sini (bukan lagi dipaku 'tunai'), lalu dikirim ke server.
 * Daftarnya memakai LABEL_METODE bersama supaya teksnya konsisten dengan
 * BisaBelanja dan halaman status.
 */
const metodeDipilih = ref<MetodeId>('tunai')
const sheetMetodeOpen = ref(false)
const metodeAktif = computed(() => LABEL_METODE[metodeDipilih.value])

const grupMetode: { judul: string; daftar: { id: MetodeId; desc?: string }[] }[] = [
  { judul: 'Bayar di Tempat', daftar: [{ id: 'tunai', desc: 'Bayar tunai ke cleaner setelah selesai' }] },
  {
    judul: 'Dompet Digital',
    daftar: [
      { id: 'balance', desc: 'Saldo Serbabisa' },
      { id: 'gopay' },
      { id: 'ovo' },
      { id: 'dana' },
      { id: 'shopeepay' },
      { id: 'qris', desc: 'Scan pakai aplikasi bank atau e-wallet apa pun' },
    ],
  },
  {
    judul: 'Virtual Account',
    daftar: [{ id: 'bca' }, { id: 'bni' }, { id: 'bri' }, { id: 'mandiri' }],
  },
]

function pilihMetode(id: MetodeId) {
  metodeDipilih.value = id
  sheetMetodeOpen.value = false
}

/**
 * Pesan & bayar.
 *
 * Yang dikirim hanya PILIHAN — tidak ada satu pun harga. Server menghitung
 * ulang tagihannya sendiri dan menentukan sendiri apakah promo pengguna baru
 * berhak dipakai (dari riwayat pesanan, bukan dari klaim browser). Karena itu
 * total di layar bisa saja berbeda dengan yang ditagih; yang benar adalah
 * server, dan halaman status membaca angka dari responsnya.
 */
async function pesan() {
  if (memproses.value) return

  if (!areaTerkirim.value.length) {
    galat.value = 'Pilih minimal satu area yang mau dibersihkan.'
    return
  }
  const lokasi = locationStore.draft
  if (!lokasi?.alamat) {
    galat.value = 'Pilih alamat dulu ya.'
    openPicker()
    return
  }

  memproses.value = true
  galat.value = null
  try {
    const pesanan = await kirimPesananBersih({
      durasi_jam: durasiJam.value,
      jumlah_cleaner: jumlahCleaner.value,
      add_on: [...addOnDipilih.value],
      frekuensi: frekuensiId.value,

      ada_hewan: adaHewan.value,
      area: areaTerkirim.value,
      cleaner_id: modeCleaner.value === 'pilih' ? cleanerDipilih.value ?? undefined : undefined,
      promo_kode: promoTerpakai.value && kurangUntukPromo.value === 0 ? promoTerpakai.value.kode : undefined,

      tanggal: tanggalDipilih.value,
      waktu: jamDipilih.value,
      catatan: catatan.value || undefined,

      lokasi_alamat: lokasi.alamat,
      lokasi_lat: lokasi.lat,
      lokasi_lng: lokasi.lng,
      nama_penerima: authStore.user?.name ?? undefined,
      telepon_penerima: authStore.user?.phone ?? undefined,
      metode: metodeDipilih.value,
    })

    router.push({ name: 'task-bersih-status-bayar', params: { nomor: pesanan.nomor } })
  } catch (e) {
    // Halaman sengaja tidak direset: pengguna harus bisa memperbaiki pilihannya
    // lalu memesan ulang tanpa mengisi form dari awal.
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-64">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/40">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Bersih Rumah</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-7">

      <!-- Lokasi pengerjaan: peta langsung bisa diubah di sini (tanpa pindah halaman).
           `isolate` mengurung z-index internal Leaflet (pane & marker bisa mencapai
           ~600) supaya tidak menembus sheet metode pembayaran yang teleport ke body. -->
      <section class="isolate bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/40 overflow-hidden">
        <button type="button" class="block w-full text-left" @click="openPicker">
          <div
            ref="mapEl"
            class="w-full h-40 bg-(--color-surface-container) pointer-events-none"
            :style="{ visibility: pickerOpen ? 'hidden' : 'visible' }"
          ></div>
        </button>
        <div class="p-4 flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 min-w-0">
            <Icon name="pin" class="w-5 h-5 text-(--color-azure) shrink-0 mt-0.5" />
            <div class="min-w-0">
              <p class="text-[13px] font-bold truncate">
                {{ alamatTitle || 'Alamat belum dipilih' }}
              </p>
              <p class="text-[11px] text-(--color-on-surface-variant) line-clamp-2">
                {{ alamat || 'Ketuk peta untuk menandai lokasi pengerjaan' }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 text-[12px] font-semibold text-(--color-azure) px-3 py-1 rounded-full border border-(--color-azure) active:scale-95 transition-transform"
            @click="openPicker"
          >
            Ubah
          </button>
        </div>
      </section>

      <!--
        Kartu promo digambar penuh sebagai SVG (PromoBersihArt), jadi judul,
        kode, dan syaratnya hidup di dalam gambar. Peringatan "kurang sekian
        lagi" sengaja DI LUAR kartu: itu keadaan yang berubah mengikuti isi
        pesanan, dan warna error tidak terbaca di atas gradien kartu.
      -->
      <div v-if="promoTerpakai" class="-mt-3 flex flex-col gap-2">
        <PromoBersihArt
          :judul="promoTerpakai.judul"
          :kode="promoTerpakai.kode"
          :min-transaksi="promoTerpakai.minTransaksi"
          :catatan="promoTerpakai.periode"
        />

        <p v-if="kurangUntukPromo > 0" class="text-[12px] font-bold text-(--color-error) px-1">
          Tambah {{ rp(kurangUntukPromo) }} lagi supaya promo ini bisa dipakai.
        </p>
        <p
          v-else-if="cashbackAktif"
          class="text-[12px] font-semibold text-(--color-on-secondary-container) px-1"
        >
          Cashback {{ rp(cashbackAktif) }} masuk saldo setelah pesanan selesai.
        </p>

        <button
          type="button"
          class="self-start text-[12px] font-bold text-(--color-azure) underline px-1 active:scale-95 transition-transform"
          @click="keHalamanPromo"
        >
          {{ promoPilihan ? 'Ganti promo' : 'Lihat promo lain' }}
        </button>
      </div>

      <!-- Pilih cleaner -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">Pilih Cleaner</h2>

        <div class="flex gap-4 mb-3">
          <button
            v-for="m in [
              { id: 'cepat' as const, label: 'Cleaner Tercepat' },
              { id: 'pilih' as const, label: 'Pilih Sendiri' },
            ]"
            :key="m.id"
            type="button"
            class="flex-1 py-2 text-[13px] font-semibold text-center border-b-2 transition-colors"
            :class="
              modeCleaner === m.id
                ? 'border-(--color-azure) text-(--color-azure)'
                : 'border-(--color-outline)/40 text-(--color-on-surface-variant)'
            "
            :aria-pressed="modeCleaner === m.id"
            @click="modeCleaner = m.id"
          >
            {{ m.label }}
          </button>
        </div>

        <p
          v-if="modeCleaner === 'cepat'"
          class="bg-(--color-surface-container) rounded-2xl p-4 text-[12.5px] text-(--color-on-surface-variant) leading-snug"
        >
          Kami carikan cleaner terverifikasi yang paling cepat tersedia di jadwal kamu. Biasanya
          dapat lebih cepat daripada memilih sendiri.
        </p>

        <p
          v-else-if="cleanerStore.memuat && !cleanerStore.daftar.length"
          class="bg-(--color-surface-container) rounded-2xl p-4 text-[12.5px] text-(--color-on-surface-variant)"
        >
          Memuat daftar cleaner&hellip;
        </p>

        <!-- Tidak ada mitra terdaftar: dinyatakan apa adanya, bukan diisi
             nama-nama contoh. Pesanan tetap bisa dibuat lewat "Cleaner
             Tercepat". -->
        <div
          v-else-if="!cleanerStore.daftar.length"
          class="bg-(--color-surface-container) rounded-2xl p-4 flex flex-col items-start gap-2"
        >
          <p class="text-[12.5px] text-(--color-on-surface-variant) leading-snug">
            Belum ada cleaner yang bisa dipilih sendiri di area kamu. Pilih
            <strong>Cleaner Tercepat</strong> — kami carikan begitu ada yang tersedia.
          </p>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) underline px-1 active:scale-95 transition-transform"
            @click="modeCleaner = 'cepat'"
          >
            Pakai Cleaner Tercepat
          </button>
        </div>

        <div v-else class="flex flex-col gap-2.5">
          <button
            v-for="c in cleanerRingkas"
            :key="c.id"
            type="button"
            class="flex items-center gap-3.5 p-4 rounded-2xl border-2 text-left transition-colors active:scale-[0.99]"
            :class="
              cleanerDipilih === c.id
                ? 'border-(--color-azure) bg-(--color-primary-container)'
                : 'border-(--color-outline)/40 bg-(--color-surface-0)'
            "
            :aria-pressed="cleanerDipilih === c.id"
            @click="cleanerDipilih = cleanerDipilih === c.id ? null : c.id"
          >
            <CleanerAvatar :gender="c.gender ?? undefined" :nama="c.nama" class="w-12 h-12 shrink-0" />
            <span class="min-w-0">
              <span class="text-[13px] font-extrabold truncate block">{{ c.nama }}</span>
              <!-- Bintang, level, dan jumlah order ditandai "-" selama belum ada
                   ulasan sungguhan: nol bukan nilai buruk, hanya belum dinilai. -->
              <span class="flex items-center gap-1 text-[11.5px]">
                <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
                <span class="font-bold">{{ nilai(c.rating, c.jumlah_ulasan) }}</span>
                <span v-if="c.jumlah_ulasan > 0" class="text-(--color-on-surface-variant)">
                  ({{ c.jumlah_ulasan }} ulasan)
                </span>
              </span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug">
                Level {{ nilai(c.level, c.jumlah_ulasan) }} &middot;
                {{ rp(c.harga_per_jam) }}/jam
              </span>
            </span>
          </button>

          <!-- Buka daftar lengkap cleaner -->
          <button
            type="button"
            class="flex items-center justify-center gap-1.5 py-2.5 text-[13px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="lihatSemuaCleaner"
          >
            Lihat Semua
            <Icon name="chevron-down" class="w-4 h-4" />
          </button>
        </div>
      </section>


      <!-- Area & checklist -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">Area &amp; Checklist</h2>
        <div class="bg-(--color-surface-0) border border-(--color-outline)/40 rounded-2xl p-4">
          <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3 flex items-start gap-1.5">
            <Icon name="info" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
            Pekerjaan mencakup menyapu, mengepel, lap permukaan, dan buang sampah.
          </p>
          <div class="flex flex-col gap-2.5">
            <label v-for="a in AREA" :key="a" class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                :checked="areaDipilih.includes(a)"
                class="sr-only peer"
                @change="toggle(areaDipilih, a)"
              />
              <!-- Kotak centang digambar sendiri: bawaan browser memakai warna
                   aksen biru yang menabrak warna pilihan lain di halaman ini. -->
              <span
                class="w-5 h-5 rounded-md border-2 border-(--color-outline) flex items-center justify-center shrink-0 transition-colors bg-white"
              >
                <Icon
                  v-if="areaDipilih.includes(a)"
                  name="check"
                  class="w-3.5 h-3.5 text-(--color-on-surface)"
                />
              </span>
              <span class="text-[13px]">{{ a }}</span>
            </label>

            <input
              v-if="areaDipilih.includes('Lainnya')"
              v-model="areaLainnya"
              type="text"
              maxlength="120"
              placeholder="Area lain yang mau dibersihkan"
              aria-label="Area lainnya"
              class="ml-8 bg-(--color-surface-container) rounded-xl px-3 py-2 text-[13px] border border-(--color-outline)/40 focus:border-(--color-azure) focus:outline-none"
            />
          </div>

          <label class="flex items-center justify-between mt-3 pt-3 border-t border-(--color-outline)/40 cursor-pointer">
            <span class="text-[12.5px] font-semibold">Ada hewan peliharaan?</span>
            <input v-model="adaHewan" type="checkbox" class="sr-only peer" />
            <span
              class="w-11 h-6 rounded-full relative transition-colors bg-(--color-outline)/60 peer-checked:bg-(--color-azure) after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-transform peer-checked:after:translate-x-5"
            ></span>
          </label>
        </div>
      </section>



      <!-- Add-on -->
      <section>
        <div class="flex items-baseline justify-between mb-3">
          <h2 class="text-[15px] font-display font-extrabold">Layanan Tambahan</h2>
          <span class="text-[11.5px] text-(--color-on-surface-variant)">Opsional</span>
        </div>
        <div class="flex flex-col gap-2.5">
          <button
            v-for="a in ADD_ON"
            :key="a.id"
            type="button"
            class="flex items-center justify-between p-4 rounded-2xl border transition-colors active:scale-[0.99]"
            :class="
              addOnDipilih.includes(a.id)
                ? 'border-(--color-azure) bg-(--color-primary-container)'
                : 'border-(--color-outline)/40 bg-(--color-surface-0)'
            "
            :aria-pressed="addOnDipilih.includes(a.id)"
            @click="toggle(addOnDipilih, a.id)"
          >
            <span class="text-left">
              <span class="block text-[13px] font-bold">{{ a.nama }}</span>
              <span class="block text-[11.5px] font-semibold text-(--color-azure)">+{{ rp(a.harga) }}</span>
            </span>
            <span
              class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
              :class="
                addOnDipilih.includes(a.id)
                  ? 'bg-(--color-azure) text-white'
                  : 'border border-(--color-outline)'
              "
            >
              <Icon :name="addOnDipilih.includes(a.id) ? 'check' : 'plus'" class="w-3.5 h-3.5" />
            </span>
          </button>
        </div>
      </section>

      <!-- Durasi -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-2.5">Durasi</h2>
        <div class="flex flex-wrap gap-2.5">
            <button
              v-for="j in PILIHAN_DURASI"
              :key="j"
              type="button"
              class="px-4 py-2 rounded-full text-[12.5px] font-semibold border flex items-center gap-1.5 transition-colors active:scale-95"
              :class="
                durasiJam === j
                  ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
                  : 'border-(--color-outline)/60 text-(--color-on-surface-variant)'
              "
              @click="durasiJam = j"
            >
              <Icon v-if="j === DURASI_REKOMENDASI" name="star" class="w-3.5 h-3.5" />
              {{ j }} Jam<template v-if="j === DURASI_REKOMENDASI"> · Rekomendasi</template>
            </button>
          </div>
          <p class="text-[11.5px] text-(--color-on-surface-variant) mt-2">
            {{ DURASI_REKOMENDASI }} jam cukup untuk rumah ukuran umum. Pilih lebih lama kalau
            rumahnya besar atau lama tidak dibersihkan.
          </p>
      </section>

      <!-- Jadwal -->
      <section class="flex flex-col gap-4">
        <h2 class="text-[15px] font-display font-extrabold">
          Kapan Anda membutuhkan layanan? <span class="text-(--color-error)">*</span>
        </h2>

        <div class="bg-(--color-surface-0) border border-(--color-outline)/40 rounded-2xl p-4">
          <div class="flex items-center justify-between mb-3">
            <button
              type="button"
              class="w-8 h-8 rounded-full flex items-center justify-center active:scale-90 transition-transform disabled:opacity-30"
              :disabled="!bisaMundur"
              aria-label="Bulan sebelumnya"
              @click="pindahBulan(-1)"
            >
              <Icon name="chevron-left" class="w-4 h-4" />
            </button>
            <p class="text-[14px] font-display font-extrabold">{{ judulBulan }}</p>
            <button
              type="button"
              class="w-8 h-8 rounded-full flex items-center justify-center active:scale-90 transition-transform"
              aria-label="Bulan berikutnya"
              @click="pindahBulan(1)"
            >
              <Icon name="chevron-right" class="w-4 h-4" />
            </button>
          </div>

          <div class="grid grid-cols-7 gap-y-1 text-center">
            <span
              v-for="(h, i) in KEPALA_HARI"
              :key="`kepala-${i}`"
              class="text-[11px] font-bold text-(--color-on-surface-variant) py-1"
            >
              {{ h }}
            </span>

            <template v-for="(sel, i) in selKalender" :key="sel?.iso ?? `kosong-${i}`">
              <!-- Sel kosong menjaga tanggal tetap jatuh di kolom harinya. -->
              <span v-if="!sel"></span>
              <button
                v-else
                type="button"
                class="h-9 w-9 mx-auto rounded-lg text-[13px] font-semibold transition-colors disabled:cursor-not-allowed"
                :class="
                  tanggalDipilih === sel.iso
                    ? 'bg-(--color-azure) text-white font-extrabold'
                    : sel.lampau
                      ? 'text-(--color-on-surface-variant)/40'
                      : 'text-(--color-on-surface) hover:bg-(--color-surface-container)'
                "
                :disabled="sel.lampau"
                :aria-pressed="tanggalDipilih === sel.iso"
                @click="tanggalDipilih = sel.iso"
              >
                {{ sel.tanggal }}
              </button>
            </template>
          </div>
        </div>

        <h2 class="text-[15px] font-display font-extrabold">
          Pukul berapa Anda membutuhkan layanan? <span class="text-(--color-error)">*</span>
        </h2>

        <TimePickerField
          v-model="jamDipilih"
          title="Pilih Jam Layanan"
          :min-hour="JAM_MIN"
          :max-hour="JAM_MAKS"
          :step-minutes="60"
        />
        <p class="text-[11.5px] text-(--color-on-surface-variant) -mt-2">
          Layanan tersedia pukul {{ JAM_MIN }}.00–{{ JAM_MAKS }}.00.
        </p>

        <div>
          <h3 class="text-[13px] font-bold mb-2.5">Frekuensi</h3>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="f in FREKUENSI"
              :key="f.id"
              type="button"
              class="px-4 py-2 rounded-full text-[12.5px] font-semibold border flex items-center gap-1.5 transition-colors active:scale-95"
              :class="
                frekuensiId === f.id
                  ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
                  : 'border-(--color-outline)/60 text-(--color-on-surface-variant)'
              "
              @click="frekuensiId = f.id"
            >
              {{ f.label }}
              <span
                v-if="f.diskon > 0"
                class="text-[10px] bg-(--color-lime) text-[#33430b] px-1.5 py-0.5 rounded font-bold"
              >
                Diskon {{ Math.round(f.diskon * 100) }}%
              </span>
            </button>
          </div>
        </div>
      </section>

      <!-- Catatan -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-2.5">Catatan Khusus</h2>
        <textarea
          v-model="catatan"
          rows="3"
          maxlength="500"
          placeholder="Contoh: Tolong fokus di area dapur ya."
          aria-label="Catatan khusus"
          class="w-full bg-(--color-surface-container) rounded-xl p-3 text-[13px] resize-none border border-(--color-outline)/40 focus:border-(--color-azure) focus:outline-none"
        ></textarea>
      </section>

    </main>

    <!-- Ringkasan & CTA -->
    <footer
      class="fixed bottom-0 inset-x-0 z-40 rounded-t-2xl bg-(--color-surface-0) border-t border-(--color-outline)/40 shadow-[0_-10px_40px_rgba(0,0,0,0.10)]"
    >
      <div class="max-w-[430px] mx-auto">
        <div class="px-4 py-3.5 border-b border-(--color-outline)/40">
          <h3 class="text-[13px] font-bold mb-2.5">Ringkasan Pembayaran</h3>
          <div class="flex flex-col gap-1.5 text-[12.5px] text-(--color-on-surface-variant)">
            <div class="flex justify-between gap-3">
              <span class="truncate">Bersih Rumah ({{ durasiJam }} jam × {{ jumlahCleaner }})</span>
              <span class="shrink-0">{{ rp(rincian.layanan) }}</span>
            </div>
            <div v-if="rincian.addOn" class="flex justify-between gap-3">
              <span>Layanan tambahan</span>
              <span class="shrink-0">{{ rp(rincian.addOn) }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span>Biaya perjalanan</span>
              <span class="shrink-0">{{ rp(rincian.perjalanan) }}</span>
            </div>
            <div v-if="rincian.diskonFrekuensi" class="flex justify-between gap-3 text-(--color-secondary)">
              <span>Diskon {{ frekuensiAktif.label.toLowerCase() }}</span>
              <span class="shrink-0">−{{ rp(rincian.diskonFrekuensi) }}</span>
            </div>
            <div v-if="rincian.potonganPromo" class="flex justify-between gap-3 text-(--color-secondary)">
              <span>Promo ({{ promoTerpakai?.kode }})</span>
              <span class="shrink-0">−{{ rp(rincian.potonganPromo) }}</span>
            </div>
          </div>
        </div>

        <!-- Metode pembayaran: di atas nominal, seperti BisaBelanja -->
        <button
          type="button"
          class="w-full flex items-center gap-3 px-4 py-3 border-b border-(--color-outline)/40 active:bg-(--color-surface-container) transition-colors"
          @click="sheetMetodeOpen = true"
        >
          <MetodeBayarIcon :id="metodeDipilih" />
          <span class="flex-1 min-w-0 text-left">
            <span class="block text-[11px] text-(--color-on-surface-variant)">Metode Pembayaran</span>
            <span class="block text-[13px] font-bold truncate">{{ metodeAktif }}</span>
          </span>
          <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
        </button>

        <p v-if="galat" role="alert" class="px-4 pt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>

        <div class="px-4 py-3.5 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Total Tagihan</p>
            <p class="text-[22px] font-display font-extrabold text-(--color-azure) leading-tight">
              {{ rp(rincian.total) }}
            </p>
          </div>
          <button
            type="button"
            :disabled="memproses"
            class="shrink-0 bg-(--color-azure) text-white text-[14px] font-bold px-7 py-3.5 rounded-full active:scale-95 transition-transform disabled:opacity-40"
            @click="pesan"
          >
            Pesan &amp; Bayar
          </button>
        </div>
      </div>
    </footer>

    <!-- Sheet metode pembayaran, naik dari bawah -->
    <Teleport to="body">
      <div v-if="sheetMetodeOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <Transition
          appear
          enter-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          leave-active-class="transition-opacity duration-200"
          leave-to-class="opacity-0"
        >
          <div class="absolute inset-0 bg-black/45" @click="sheetMetodeOpen = false"></div>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-y-full"
          leave-active-class="transition-transform duration-200 ease-in"
          leave-to-class="translate-y-full"
        >
          <div class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
            <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"></div>

            <div class="flex items-center justify-between px-5 py-3.5 shrink-0">
              <h3 class="font-extrabold text-[17px]">Mau bayar pakai apa?</h3>
              <button
                type="button"
                aria-label="Tutup"
                class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
                @click="sheetMetodeOpen = false"
              >
                <Icon name="x" class="w-4 h-4" />
              </button>
            </div>

            <div class="overflow-y-auto flex-1 pb-6">
              <div v-for="g in grupMetode" :key="g.judul">
                <p class="px-5 pt-4 pb-1.5 text-[13px] font-extrabold text-(--color-on-surface)">{{ g.judul }}</p>
                <button
                  v-for="m in g.daftar"
                  :key="m.id"
                  type="button"
                  class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors active:bg-(--color-surface-container)"
                  :class="metodeDipilih === m.id ? 'bg-(--color-azure)/8' : ''"
                  @click="pilihMetode(m.id)"
                >
                  <MetodeBayarIcon :id="m.id" />
                  <span class="flex-1 min-w-0">
                    <span class="block text-[14px] font-bold truncate text-(--color-on-surface)">{{ LABEL_METODE[m.id] }}</span>
                    <span v-if="m.desc" class="block text-[11.5px] text-(--color-on-surface-variant) truncate">{{ m.desc }}</span>
                  </span>
                  <span
                    v-if="metodeDipilih === m.id"
                    class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
                  >
                    <Icon name="check" class="w-3 h-3 text-white" />
                  </span>
                </button>
                <div class="h-2 bg-(--color-surface-container)"></div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>

    <!-- Pemilih pin layar penuh — tetap di halaman ini, tidak berpindah rute -->
    <div v-if="pickerOpen" class="fixed inset-0 z-50 flex flex-col bg-(--color-surface)">
      <div class="flex items-center gap-3 px-5 py-4 bg-(--color-surface-0) border-b border-(--color-outline)">
        <button
          type="button"
          class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
          @click="closePicker"
        >
          <Icon name="arrow-left" class="w-[19px] h-[19px]" />
        </button>
        <h3 class="text-base font-extrabold flex-1">Pilih di Peta</h3>
      </div>

      <div ref="pickerMapEl" class="flex-1 w-full"></div>

      <div class="px-5 py-4 bg-(--color-surface-0) border-t border-(--color-outline)">
        <p class="text-center text-xs text-(--color-on-surface-variant) mb-3">
          Geser peta lalu ketuk untuk menandai lokasi pengerjaan
        </p>
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
