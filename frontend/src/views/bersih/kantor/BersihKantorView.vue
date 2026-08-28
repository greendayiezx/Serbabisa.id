<script setup lang="ts">
/**
 * Pemesanan BisaBersih — Bersih Kantor.
 *
 * Berbeda dari Bersih Rumah yang ditagih per jam per cleaner, kantor dihitung
 * per KUNJUNGAN dari luas area dan jumlah fasilitas (lihat lib/hargaBersihKantor).
 *
 * Halaman ini berhenti di "Minta Penawaran", BUKAN checkout langsung. Alasannya
 * jujur: server (App\Services\BersihTarif) hanya mengenal rumus per jam milik
 * Bersih Rumah, jadi menagih langsung akan membebankan angka yang berbeda dari
 * yang tampil di layar. Angka di sini disebut ESTIMASI, dan permintaan dikirim
 * sebagai task agar tim menindaklanjuti dengan penawaran resmi.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PromoKantorArt from '@/components/bersih/PromoKantorArt.vue'
import LanggananKantorArt from '@/components/bersih/LanggananKantorArt.vue'
import bersihKantorImg from '@/assets/BersihKantor.png'
import { useLocationStore } from '@/stores/location'
import { useTaskStore } from '@/stores/task'
import { TILE_URL, TILE_OPTIONS } from '@/lib/mapTiles'
import {
  ADD_ON_KANTOR,
  FREKUENSI_KANTOR,
  JENIS_KANTOR,
  PAKET_KANTOR,
  hitungHargaKantor,
  type JenisKantorId,
  type PaketKantorId,
} from '@/lib/bersih/hargaBersihKantor'
import { hitungPromoKantor } from '@/lib/promo/promoBersihKantor'
import { usePromoBersihKantorStore } from '@/stores/promoBersihKantor'
import { usePenawaranKantorStore } from '@/stores/penawaranKantor'


const router = useRouter()
const route = useRoute()
const kembali = useKembali()
const promoStore = usePromoBersihKantorStore()
const locationStore = useLocationStore()
const taskStore = useTaskStore()
const penawaranStore = usePenawaranKantorStore()

/* ---------------- Scroll Promo Carousel ---------------- */
const promoContainer = ref<HTMLDivElement | null>(null)
function scrollPromo(arah: 'kiri' | 'kanan') {
  if (!promoContainer.value) return
  const jarak = arah === 'kiri' ? -270 : 270
  promoContainer.value.scrollBy({ left: jarak, behavior: 'smooth' })
}

/* ---------------- Pilihan pengguna ---------------- */
const jenisId = ref<JenisKantorId>('sedang')
const paketId = ref<PaketKantorId>('professional')
const workstation = ref(0)
const ruangMeeting = ref(0)
const toilet = ref(0)
const pantry = ref(0)
const frekuensiId = ref('2x-minggu')
const addOnDipilih = ref<string[]>([])
const catatan = ref('')

/**
 * "Lainnya": area di luar daftar fasilitas.
 *
 * Tidak ikut menghitung harga — luasnya belum diketahui, dan menaksir sendiri
 * berarti menampilkan angka yang tidak berdasar. Isinya diteruskan ke tim
 * penawaran lewat ringkasan spesifikasi.
 */
const lainnyaAktif = ref(false)
const lainnyaTeks = ref('')

const jenisAktif = computed(() => JENIS_KANTOR.find((j) => j.id === jenisId.value) ?? JENIS_KANTOR[1])
const paketAktif = computed(() => PAKET_KANTOR.find((p) => p.id === paketId.value) ?? PAKET_KANTOR[0])
const frekuensiAktif = computed(
  () => FREKUENSI_KANTOR.find((f) => f.id === frekuensiId.value) ?? FREKUENSI_KANTOR[0],
)

/**
 * Pencacah fasilitas dirakit di sini, bukan di template: menulis closure di
 * dalam v-for membuat tipenya tidak terperiksa dan nilainya mudah basi.
 */
const fasilitas = computed(() => [
  { label: 'Workstation / Meja Kerja', nilai: workstation.value, set: (v: number) => (workstation.value = v) },
  { label: 'Ruang Meeting', nilai: ruangMeeting.value, set: (v: number) => (ruangMeeting.value = v) },
  { label: 'Toilet', nilai: toilet.value, set: (v: number) => (toilet.value = v) },
  { label: 'Pantry', nilai: pantry.value, set: (v: number) => (pantry.value = v) },
])

function toggleAddOn(id: string) {
  const i = addOnDipilih.value.indexOf(id)
  if (i >= 0) addOnDipilih.value.splice(i, 1)
  else addOnDipilih.value.push(id)
}

const rincian = computed(() =>
  hitungHargaKantor({
    paketId: paketId.value,
    // Luas tidak lagi ditanyakan; yang dipakai adalah luas acuan jenis kantor.
    luasM2: jenisAktif.value.luasAcuan,
    // Jumlah lantai juga tidak ditanyakan lagi — dihitung satu lantai, dan
    // lantai tambahan masuk ke penawaran resmi setelah survei.
    jumlahLantai: 1,
    workstation: workstation.value,
    ruangMeeting: ruangMeeting.value,
    toilet: toilet.value,
    pantry: pantry.value,
    addOnDipilih: addOnDipilih.value,
    frekuensiId: frekuensiId.value,
  }),
)

/* ---------------- Promo ---------------- */
/**
 * Promo dipilih di halaman promo kantor, bukan di sini. Diskon langganan TIDAK
 * ikut di sini — ia sudah dipotong sebagai diskon frekuensi di dalam rincian,
 * jadi menghitungnya lagi berarti memotong dua kali.
 */
const promoTerpakai = computed(() => promoStore.voucher())
const hasilPromo = computed(() =>
  hitungPromoKantor(promoTerpakai.value, rincian.value.totalPerKunjungan),
)
const totalSetelahPromo = computed(
  () => rincian.value.totalPerKunjungan - hasilPromo.value.potongan,
)

const rincianTerbuka = ref(false)

function rp(n: number) {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}

/* ---------------- Peta lokasi (embed + pemilih) ---------------- */
/**
 * Pola yang sama dengan Bersih Rumah: peta pratinjau tampil langsung, dan
 * penyuntingan pin dibuka sebagai overlay layar penuh — supaya alamat bisa
 * diubah tanpa meninggalkan halaman dan kehilangan isian form.
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

/*
 * Penyuntingan lokasi memakai PemilihLokasi — komponen yang sama dengan halaman
 * konfirmasi. Sebelumnya halaman ini punya pemilihnya sendiri: pin bisa diseret,
 * tapi TIDAK ADA pencarian, jadi alamat hanya bisa ditemukan dengan menggeser
 * peta secara manual. Menyalin layar carinya ke sini berarti dua tempat yang
 * harus diperbaiki tiap kali pencariannya berubah.
 */
const pickerOpen = ref(false)

function openPicker() {
  pickerOpen.value = true
}

/** Titik terpilih: simpan ke draf lokasi dan geser peta pratinjau ke sana. */
function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft({ alamat: l.alamat, lat: l.lat, lng: l.lng })
  const titik: L.LatLngTuple = [l.lat, l.lng]
  map?.setView(titik, map.getZoom())
  mapMarker?.setLatLng(titik)
  pickerOpen.value = false
}

onMounted(async () => {
  workstation.value = 0
  ruangMeeting.value = 0
  toilet.value = 0
  pantry.value = 0
  await nextTick()
  initMap()
  void taskStore.fetchCategories()
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  if (map) {
    map.remove()
    map = null
  }
  pickerOpen.value = false
})

/* ---------------- Pesan langsung ---------------- */

/**
 * Kantor besar tidak bisa dipesan langsung.
 *
 * Halaman hanya tahu "di atas 150 m²", jadi menagihnya dari satu angka wakil
 * berarti menagih terlalu murah untuk kantor yang jauh lebih luas. Server
 * menolaknya juga (422) — ini hanya supaya pengguna tidak menabrak dinding itu.
 */
const bisaPesanLangsung = computed(() => jenisId.value !== 'besar')

/**
 * Tombol "Pesan Sekarang" membawa pengguna ke halaman pesan khusus,
 * mirip seperti alur "Minta Penawaran" tetapi untuk checkout langsung.
 *
 * Pilihan di halaman ini dititipkan ke store supaya halaman pesan
 * tidak menanyakan ulang hal yang sudah dipilih.
 */
function bukaSheetPesan() {
  const lokasi = locationStore.draft
  if (!lokasi?.alamat) {
    galat.value = 'Pilih alamat kantor dulu ya.'
    openPicker()
    return
  }

  penawaranStore.set({
    jenisId: jenisAktif.value.id,
    jenisNama: jenisAktif.value.nama,
    jenisRentang: jenisAktif.value.rentang,
    luasAcuan: jenisAktif.value.luasAcuan,
    paketId: paketAktif.value.id,
    paketNama: paketAktif.value.nama,
    frekuensiId: frekuensiId.value,
    frekuensiLabel: frekuensiAktif.value.label,
    workstation: workstation.value,
    ruangMeeting: ruangMeeting.value,
    toilet: toilet.value,
    pantry: pantry.value,
    lainnya: lainnyaAktif.value ? lainnyaTeks.value.trim() : '',
    addOn: ADD_ON_KANTOR.filter((a) => addOnDipilih.value.includes(a.id)).map((a) => a.nama),
    addOnId: [...addOnDipilih.value],
    catatan: catatan.value.trim(),
    estimasi: totalSetelahPromo.value,
    promoKode: promoTerpakai.value?.kode ?? null,
  })

  router.push({ name: 'task-bersih-kantor-pesan' })
}


/* ---------------- Minta penawaran ---------------- */

/**
 * Promo yang dipilih bisa jadi tidak layak lagi setelah tagihan berubah — mis.
 * pengguna melepas add-on. Dilepas sendiri, dengan pemberitahuan sekali.
 */
watch(
  () => hasilPromo.value.kurang,
  (kurang) => {
    if (kurang > 0 && promoTerpakai.value) {
      const kode = promoTerpakai.value.kode
      promoStore.lepas()
      galat.value = `Promo ${kode} dilepas: tagihannya turun di bawah minimum transaksi.`
    }
  },
)

const memproses = ref(false)
const galat = ref<string | null>(null)

/**
 * Lanjut ke form penawaran.
 *
 * Tugasnya TIDAK dibuat di sini lagi: penawaran butuh nama perusahaan, PIC, dan
 * nomor yang bisa dihubungi — mengirim permintaan tanpa itu berarti tim tidak
 * punya siapa pun untuk dihubungi. Pilihan di halaman ini dititipkan ke store
 * supaya formnya tidak menanyakan ulang hal yang sudah dipilih.
 */
function mintaPenawaran() {
  const lokasi = locationStore.draft
  if (!lokasi?.alamat) {
    galat.value = 'Pilih alamat kantor dulu ya.'
    openPicker()
    return
  }

  penawaranStore.set({
    jenisId: jenisAktif.value.id,
    jenisNama: jenisAktif.value.nama,
    jenisRentang: jenisAktif.value.rentang,
    luasAcuan: jenisAktif.value.luasAcuan,
    paketId: paketAktif.value.id,
    paketNama: paketAktif.value.nama,
    frekuensiId: frekuensiId.value,
    frekuensiLabel: frekuensiAktif.value.label,
    workstation: workstation.value,
    ruangMeeting: ruangMeeting.value,
    toilet: toilet.value,
    pantry: pantry.value,
    lainnya: lainnyaAktif.value ? lainnyaTeks.value.trim() : '',
    addOn: ADD_ON_KANTOR.filter((a) => addOnDipilih.value.includes(a.id)).map((a) => a.nama),
    addOnId: [...addOnDipilih.value],
    catatan: catatan.value.trim(),
    estimasi: totalSetelahPromo.value,
    promoKode: promoTerpakai.value?.kode ?? null,
  })

  router.push({ name: 'task-bersih-kantor-penawaran' })
}

/* ---------------- Tautan ---------------- */
function kePromo() {
  // Jalur halaman ini dibawa serta supaya tombol kembali di halaman promo
  // memulangkan pengguna ke sini lengkap dengan isian yang sudah diisi.
  router.push({
    name: 'task-bersih-kantor-promo',
    query: {
      dari: route.fullPath,
      // Tanpa ini halaman promo tidak tahu tagihannya, jadi tidak bisa
      // menonaktifkan promo yang belum memenuhi minimum.
      nilai: String(rincian.value.totalPerKunjungan),
    },
  })
}
function keLangganan() {
  router.push({ name: 'task-bersih-langganan' })
}

const KEUNGGULAN = [
  { ikon: 'shield', label: 'Cleaner Terverifikasi' },
  { ikon: 'sparkle', label: 'Ramah Lingkungan' },
  { ikon: 'check-circle', label: 'Asuransi Terjamin' },
  { ikon: 'users', label: 'Dukungan Korporat' },
]
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-56">
    <!-- Tombol kembali melayang -->
    <button
      type="button"
      aria-label="Kembali"
      class="absolute top-4 left-4 z-40 w-10 h-10 rounded-full bg-white/90 text-slate-800 flex items-center justify-center shadow-md backdrop-blur-xs transition-transform active:scale-95"
      @click="kembali"
    >
      <Icon name="arrow-left" class="w-5 h-5 text-slate-800" />
    </button>

    <main class="max-w-[430px] mx-auto px-4 flex flex-col gap-6">
      <!-- Gambar kepala sengaja penuh lebar: -mx-4 membatalkan padding <main>. -->
      <div class="relative -mx-4 overflow-hidden bg-[#0a2342] rounded-b-3xl">
        <img :src="bersihKantorImg" alt="BisaKantor" class="w-full h-auto block object-cover" />
      </div>

      <!-- Lokasi kantor & Maps (overlapping di belakang/bawah gambar) -->
      <div class="-mt-14 relative z-20">
        <section class="isolate bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/40 overflow-hidden shadow-(--shadow-float)">
          <button type="button" class="relative block w-full text-left overflow-hidden" @click="openPicker">
            <img :src="bersihKantorImg" alt="BisaKantor" class="absolute inset-0 w-full h-full object-cover opacity-20 pointer-events-none" />
            <div
              ref="mapEl"
              class="relative z-10 w-full h-36 bg-transparent pointer-events-none"
              :style="{ visibility: pickerOpen ? 'hidden' : 'visible' }"
            ></div>
          </button>
          <div class="p-4 flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
              <Icon name="pin" class="w-5 h-5 text-(--color-azure) shrink-0 mt-0.5" />
              <div class="min-w-0">
                <p class="text-[11px] text-(--color-on-surface-variant)">Lokasi Kantor</p>
                <p class="text-[13px] font-bold truncate">
                  {{ alamatTitle || 'Alamat belum dipilih' }}
                </p>
                <p class="text-[11px] text-(--color-on-surface-variant) line-clamp-2">
                  {{ alamat || 'Ketuk peta untuk menandai lokasi kantor' }}
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
      </div>

      <!-- Promo & langganan -->
      <section class="relative">
        <div class="relative group">
          <!-- Floating arrow controls on sides -->
          <button
            type="button"
            aria-label="Scroll promo ke kiri"
            class="absolute left-1 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white/90 shadow-md backdrop-blur-xs border border-black/5 flex items-center justify-center text-gray-700 hover:bg-white active:scale-90 transition-all opacity-80 hover:opacity-100"
            @click="scrollPromo('kiri')"
          >
            <Icon name="chevron-left" class="w-4.5 h-4.5" />
          </button>
          <button
            type="button"
            aria-label="Scroll promo ke kanan"
            class="absolute right-1 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white/90 shadow-md backdrop-blur-xs border border-black/5 flex items-center justify-center text-gray-700 hover:bg-white active:scale-90 transition-all opacity-80 hover:opacity-100"
            @click="scrollPromo('kanan')"
          >
            <Icon name="chevron-right" class="w-4.5 h-4.5" />
          </button>

          <div
            ref="promoContainer"
            class="-mx-4 px-4 flex gap-3 overflow-x-auto no-scrollbar pb-1 scroll-smooth"
          >
            <button
              type="button"
              class="shrink-0 w-[260px] text-left active:scale-[0.98] transition-transform"
              @click="kePromo"
            >
              <PromoKantorArt />
            </button>
            <button
              type="button"
              class="shrink-0 w-[260px] text-left active:scale-[0.98] transition-transform"
              @click="keLangganan"
            >
              <LanggananKantorArt />
            </button>
          </div>
        </div>
      </section>

      <!-- Keunggulan -->
      <div class="grid grid-cols-4 gap-2">
        <div
          v-for="k in KEUNGGULAN"
          :key="k.label"
          class="flex flex-col items-center text-center gap-1.5 p-3 rounded-xl bg-(--color-surface-0) border border-(--color-outline)/20"
        >
          <Icon :name="k.ikon" class="w-5 h-5 text-(--color-azure)" />
          <p class="text-[9.5px] font-semibold leading-tight">{{ k.label }}</p>
        </div>
      </div>

      <!-- Jaminan -->
      <div class="flex items-start gap-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/20 px-4 py-3">
        <Icon name="shield" class="w-5 h-5 text-(--color-azure) shrink-0 mt-0.5" />
        <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug">
          Setiap layanan dilindungi asuransi &amp; garansi kepuasan 100%.
        </p>
      </div>

      <!-- 1. Paket -->
      <!-- 1. Jenis kantor -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">1. Pilih Jenis Kantor</h2>
        <div class="grid grid-cols-3 gap-2.5">
          <button
            v-for="j in JENIS_KANTOR"
            :key="j.id"
            type="button"
            class="relative flex flex-col items-start gap-1 p-3.5 rounded-2xl border-2 text-left transition-all active:scale-[0.98]"
            :class="
              jenisId === j.id
                ? 'border-(--color-azure) bg-(--color-primary-container) shadow-[0_10px_28px_rgba(30,155,240,0.20)]'
                : 'border-(--color-outline)/20 bg-(--color-surface-0)'
            "
            :aria-pressed="jenisId === j.id"
            @click="jenisId = j.id"
          >
            <span
              v-if="j.unggulan"
              class="absolute -top-2 -right-1.5 rounded-full bg-(--color-gold) text-[8.5px] font-extrabold text-[#3f3000] px-1.5 py-0.5"
            >
              TERLARIS
            </span>
            <Icon
              :name="j.ikon"
              class="w-6 h-6 mb-0.5"
              :class="jenisId === j.id ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
            />
            <span class="block text-[12px] font-extrabold leading-tight">{{ j.nama }}</span>
            <span class="block text-[10.5px] text-(--color-on-surface-variant) leading-tight">
              {{ j.rentang }}
            </span>
            <span class="block text-[10px] text-(--color-azure) font-semibold leading-tight">
              {{ j.catatan }}
            </span>
          </button>
        </div>
      </section>

      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">2. Pilih Paket</h2>
        <div class="flex flex-col gap-2.5">
          <button
            v-for="p in PAKET_KANTOR"
            :key="p.id"
            type="button"
            class="relative flex items-start gap-3.5 p-4 rounded-2xl border-2 text-left transition-all active:scale-[0.99]"
            :class="
              paketId === p.id
                ? 'border-(--color-azure) bg-(--color-primary-container) shadow-[0_10px_28px_rgba(30,155,240,0.20)]'
                : 'border-(--color-outline)/20 bg-(--color-surface-0)'
            "
            :aria-pressed="paketId === p.id"
            @click="paketId = p.id"
          >
            <span
              v-if="p.unggulan"
              class="absolute -top-2 right-3 rounded-full bg-(--color-gold) text-[9.5px] font-extrabold text-[#3f3000] px-2 py-0.5"
            >
              REKOMENDASI
            </span>
            <span
              class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
              :class="
                paketId === p.id
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-surface-container) text-(--color-azure)'
              "
            >
              <Icon :name="p.ikon" class="w-5 h-5" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-[14px] font-extrabold">{{ p.nama }}</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug mt-0.5">
                {{ p.ringkas }}
              </span>
            </span>
          </button>
        </div>

        <!-- Cakupan paket terpilih -->
        <div class="mt-3 rounded-2xl bg-(--color-surface-0) p-4">
          <h3 class="text-[12.5px] font-bold mb-2">
            Termasuk dalam paket {{ paketAktif.nama }}:
          </h3>
          <ul class="flex flex-col gap-1.5">
            <li
              v-for="t in paketAktif.termasuk"
              :key="t"
              class="flex items-start gap-2 text-[12px] text-(--color-on-surface-variant)"
            >
              <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-azure)" />
              <span class="leading-snug">{{ t }}</span>
            </li>
          </ul>
        </div>
      </section>

      <!-- 2. Detail area -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4">3. Detail Area</h2>

        <!-- Pencacah fasilitas -->
        <div class="flex flex-col divide-y divide-(--color-outline)/12">
          <div
            v-for="f in fasilitas"
            :key="f.label"
            class="flex items-center justify-between gap-3 py-3"
          >
            <span class="text-[13px] font-semibold min-w-0">{{ f.label }}</span>
            <span class="flex items-center gap-3 shrink-0">
              <button
                type="button"
                :aria-label="`Kurangi ${f.label}`"
                class="w-9 h-9 rounded-full bg-(--color-surface-container) text-(--color-on-surface-variant) flex items-center justify-center active:scale-90 transition-transform disabled:opacity-35"
                :disabled="f.nilai <= 0"
                @click="f.set(Math.max(0, f.nilai - 1))"
              >
                <Icon name="minus" class="w-4 h-4" />
              </button>
              <span class="text-[14px] font-extrabold w-6 text-center">{{ f.nilai }}</span>
              <button
                type="button"
                :aria-label="`Tambah ${f.label}`"
                class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform"
                @click="f.set(f.nilai + 1)"
              >
                <Icon name="plus" class="w-4 h-4" />
              </button>
            </span>
          </div>

          <!-- Lainnya: area di luar daftar. Tidak menghitung harga; isinya
               diteruskan ke tim penawaran. -->
          <div class="flex items-center justify-between gap-3 py-3">
            <span class="min-w-0">
              <span class="block text-[13px] font-semibold">Lainnya</span>
              <span class="block text-[11px] text-(--color-on-surface-variant) leading-snug">
                Area lain di luar daftar di atas
              </span>
            </span>
            <button
              type="button"
              role="switch"
              :aria-checked="lainnyaAktif"
              aria-label="Ada area lainnya"
              class="relative w-11 h-6 rounded-full shrink-0 transition-colors"
              :class="lainnyaAktif ? 'bg-(--color-azure)' : 'bg-(--color-outline)/35'"
              @click="lainnyaAktif = !lainnyaAktif"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform"
                :class="lainnyaAktif ? 'translate-x-5' : ''"
              ></span>
            </button>
          </div>
        </div>

        <label v-if="lainnyaAktif" class="block mt-1">
          <span class="sr-only">Sebutkan area lainnya</span>
          <input
            v-model="lainnyaTeks"
            type="text"
            placeholder="Mis. gudang, mushola, area parkir"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 text-[13.5px] border border-(--color-outline)/30 focus:border-(--color-azure) focus:outline-none"
          />
          <span class="block text-[11px] text-(--color-on-surface-variant) mt-1.5 leading-snug">
            Belum menambah estimasi — luasnya dihitung saat survei sebelum penawaran resmi.
          </span>
        </label>
      </section>


      <!-- 4. Layanan tambahan -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">4. Layanan Tambahan</h2>
        <div class="flex flex-col gap-2.5">
          <button
            v-for="a in ADD_ON_KANTOR"
            :key="a.id"
            type="button"
            class="flex items-start gap-3 p-4 rounded-2xl border-2 text-left transition-colors active:scale-[0.99]"
            :class="
              addOnDipilih.includes(a.id)
                ? 'border-(--color-azure) bg-(--color-primary-container)'
                : 'border-(--color-outline)/20 bg-(--color-surface-0)'
            "
            :aria-pressed="addOnDipilih.includes(a.id)"
            @click="toggleAddOn(a.id)"
          >
            <span
              class="w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0 mt-0.5"
              :class="
                addOnDipilih.includes(a.id)
                  ? 'bg-(--color-azure) border-(--color-azure)'
                  : 'border-(--color-outline)'
              "
            >
              <Icon v-if="addOnDipilih.includes(a.id)" name="check" class="w-3 h-3 text-white" />
            </span>
            <span class="flex-1 min-w-0">
              <span class="block text-[13px] font-bold">{{ a.nama }}</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug mt-0.5">
                {{ a.deskripsi }}
              </span>
            </span>
            <span class="text-[12.5px] font-bold shrink-0">{{ rp(a.harga) }}</span>
          </button>
        </div>
      </section>

      <!-- Catatan -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">Catatan untuk Tim</h2>
        <textarea
          v-model="catatan"
          rows="3"
          maxlength="500"
          placeholder="Mis. akses lift barang hanya sampai jam 18.00, ada ruang server yang tidak boleh disentuh."
          class="w-full rounded-2xl bg-(--color-surface-0) px-4 py-3 text-[13px] border border-(--color-outline)/30 focus:border-(--color-azure) focus:outline-none resize-none"
        ></textarea>
      </section>
    </main>

    <!-- Ringkasan & CTA -->
    <footer
      class="fixed bottom-0 inset-x-0 z-40 rounded-t-2xl bg-(--color-surface-0) border-t border-(--color-outline)/40 shadow-[0_-10px_40px_rgba(0,0,0,0.10)]"
    >
      <div class="max-w-[430px] mx-auto">
        <!-- Rincian, bisa dibuka-tutup -->
        <div v-if="rincianTerbuka" class="px-4 pt-3.5 flex flex-col gap-1.5 text-[12.5px] text-(--color-on-surface-variant)">
          <div class="flex justify-between gap-3">
            <span class="truncate">Layanan ({{ paketAktif.nama }}, {{ jenisAktif.nama }})</span>
            <span class="shrink-0">{{ rp(rincian.layanan) }}</span>
          </div>
          <p v-if="rincian.penyesuaianMinimum > 0" class="text-[11px] leading-snug -mt-0.5">
            Sudah termasuk penyesuaian ke tagihan minimum {{ rp(rincian.layanan) }}.
          </p>
          <div v-if="rincian.addOn" class="flex justify-between gap-3">
            <span>Layanan tambahan</span>
            <span class="shrink-0">{{ rp(rincian.addOn) }}</span>
          </div>
          <div
            v-if="rincian.diskonFrekuensi"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Diskon langganan {{ frekuensiAktif.label.toLowerCase() }}</span>
            <span class="shrink-0">&minus;{{ rp(rincian.diskonFrekuensi) }}</span>
          </div>
          <div class="flex justify-between gap-3 font-semibold text-(--color-on-surface)">
            <span>Harga normal</span>
            <span class="shrink-0">{{ rp(rincian.totalPerKunjungan) }}</span>
          </div>
          <div
            v-if="hasilPromo.potongan"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Promo ({{ promoTerpakai?.kode }})</span>
            <span class="shrink-0">&minus;{{ rp(hasilPromo.potongan) }}</span>
          </div>
          <div class="flex justify-between gap-3 pt-1.5">
            <span>Estimasi sebulan ({{ frekuensiAktif.kunjunganPerBulan }}x kunjungan)</span>
            <span class="shrink-0 font-bold text-(--color-on-surface)">
              {{ rp(totalSetelahPromo * frekuensiAktif.kunjunganPerBulan) }}
            </span>
          </div>
        </div>


        <p v-if="galat" role="alert" class="px-4 pt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>

        <div class="px-4 py-3.5">
          <div class="flex items-center justify-between gap-3 mb-3">
            <div class="min-w-0">
              <p class="text-[11px] text-(--color-on-surface-variant)">
                Total Estimasi (per kedatangan)
              </p>
              <p class="flex items-baseline gap-2 min-w-0">
                <span class="text-[22px] font-display font-extrabold text-(--color-azure) leading-tight">
                  {{ rp(totalSetelahPromo) }}
                </span>
                <span
                  v-if="hasilPromo.potongan"
                  class="text-[12px] text-(--color-on-surface-variant) line-through shrink-0"
                >
                  {{ rp(rincian.totalPerKunjungan) }}
                </span>
              </p>
            </div>
            <button
              type="button"
              class="shrink-0 text-[12.5px] font-bold text-(--color-azure) underline underline-offset-4 decoration-(--color-azure)/30"
              @click="rincianTerbuka = !rincianTerbuka"
            >
              {{ rincianTerbuka ? 'Tutup Rincian' : 'Lihat Rincian' }}
            </button>
          </div>

          <div class="flex flex-col gap-2.5">
            <!-- Jalur cepat: harga paket sudah bisa ditentukan dari data. -->
            <button
              v-if="bisaPesanLangsung"
              type="button"
              :disabled="memproses"
              class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-bold flex flex-col items-center justify-center leading-tight active:scale-95 transition-transform disabled:opacity-40"
              @click="bukaSheetPesan"
            >
              <span class="flex items-center gap-2">
                <Icon name="check-circle" class="w-4 h-4" />
                Pesan Sekarang
              </span>
            </button>

            <!-- Jalur penawaran: kebutuhan khusus atau kantor besar. -->
            <button
              type="button"
              :disabled="memproses"
              class="w-full h-12 rounded-full text-[14px] font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform disabled:opacity-40"
              :class="
                bisaPesanLangsung
                  ? 'border-2 border-(--color-azure) text-(--color-azure)'
                  : 'bg-(--color-azure) text-white'
              "
              @click="mintaPenawaran"
            >
              <Icon name="receipt" class="w-4 h-4" />
              Kirim Penawaran
            </button>
          </div>

          <p class="text-[10.5px] text-(--color-on-surface-variant) text-center mt-2.5 leading-snug">
            Butuh layanan kebersihan sesuai kebutuhan kantor? Kirim detail kantormu dan dapatkan
            penawaran profesional dari tim BisaBersih.
          </p>
        </div>
      </div>
    </footer>


    <!--
      Peta pratinjau disembunyikan selama pemilih terbuka (lihat `visibility`
      di atas): wadah Leaflet ber-z-index auto, jadi pane-nya menumpuk di
      konteks yang sama dengan lembar pemilih dan bisa tergambar menembusnya.
    -->
    <PemilihLokasi
      :tampil="pickerOpen"
      :alamat="alamat"
      :lat="lat"
      :lng="lng"
      judul="Set lokasi kantor"
      label-cari="Cari nama gedung atau alamat"
      @tutup="pickerOpen = false"
      @pilih="terimaLokasi"
    />
  </div>
</template>
