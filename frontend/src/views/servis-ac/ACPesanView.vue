<script setup lang="ts">
/**
 * Pemesanan Servis AC — langkah 1: lokasi, detail unit, dan paket.
 *
 * Jadwal, promo, catatan, dan data pemesan pindah ke halaman konfirmasi.
 * Pemisahannya mengikuti apa yang menentukan harga: semua yang di halaman ini
 * mengubah angka di footer, sementara yang di halaman berikutnya tidak.
 *
 * Peta tampil langsung sebagai pratinjau dan penyuntingannya dibuka sebagai
 * overlay layar penuh — pola yang sama dengan BisaBersih Rumah dan Kantor,
 * supaya alamat bisa diubah tanpa meninggalkan halaman dan kehilangan isian.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import { useServisACStore } from '@/stores/servisAC'
import { useLocationStore } from '@/stores/location'
import { TILE_OPTIONS, TILE_URL } from '@/lib/mapTiles'
import { rupiah } from '@/lib/rupiah'
import {
  KAPASITAS_AC,
  KONDISI_AC,
  PAKET_AC,
  TERAKHIR_CUCI,
  TIPE_AC,
  hitungHargaAC,
} from '@/lib/servis-ac/hargaAC'

const router = useRouter()
const kembali = useKembali()
const acStore = useServisACStore()
const locationStore = useLocationStore()

/* ────────── Detail AC ────────── */
const unit = ref(1)
const tipe = ref('split')
const kapasitas = ref('1')
const terakhirCuci = ref('3-6-bulan')
const kondisi = ref<string[]>([])
const kondisiLainnya = ref('')
const paket = ref('standard')

/*
 * Isian dipulihkan dari draf kalau pengguna kembali dari halaman konfirmasi —
 * mengulang dari nol setiap kali tombol kembali ditekan adalah cara tercepat
 * membuat orang berhenti memesan.
 */
onMounted(async () => {
  const d = acStore.draft
  if (d) {
    unit.value = d.unit
    tipe.value = d.tipe
    kapasitas.value = d.kapasitas
    terakhirCuci.value = d.terakhirCuci
    kondisi.value = [...d.kondisi]
    kondisiLainnya.value = d.kondisiLainnya || ''
    paket.value = d.paket
  }

  const lokasi = locationStore.draft
  alamatLokal.value = d?.alamat || lokasi?.alamat || ''
  latLokal.value = d?.lat || lokasi?.lat || 0
  lngLokal.value = d?.lng || lokasi?.lng || 0

  await nextTick()
  initPeta()
})

function tambahUnit() {
  if (unit.value < 20) unit.value++
}

function kurangUnit() {
  if (unit.value > 1) unit.value--
}

function toggleKondisi(id: string) {
  const i = kondisi.value.indexOf(id)
  if (i >= 0) {
    kondisi.value.splice(i, 1)
    if (id === 'lainnya') kondisiLainnya.value = ''
    return
  }

  /*
   * "Tidak ada keluhan" meniadakan keluhan lain, dan sebaliknya — memilih
   * keduanya sekaligus membuat catatan untuk teknisi saling bertentangan.
   */
  if (id === 'tidak-ada-keluhan') {
    kondisi.value = []
    kondisiLainnya.value = ''
  } else {
    kondisi.value = kondisi.value.filter((k) => k !== 'tidak-ada-keluhan')
  }

  kondisi.value.push(id)
}

/* ────────── Alamat ────────── */
const alamatLokal = ref('')
const latLokal = ref(0)
const lngLokal = ref(0)
const pemilihTampil = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  alamatLokal.value = l.alamat
  latLokal.value = l.lat
  lngLokal.value = l.lng
  locationStore.setDraft({ alamat: l.alamat, lat: l.lat, lng: l.lng })
  pemilihTampil.value = false

  const titik: L.LatLngTuple = [l.lat, l.lng]
  peta?.setView(titik, peta.getZoom())
  penanda?.setLatLng(titik)
}

/*
 * Peta pratinjau: hanya untuk dilihat. Interaksinya dimatikan supaya gerakan
 * jari saat menggulung halaman tidak berubah jadi menggeser peta — pinnya
 * dipindahkan lewat overlay PemilihLokasi, tempat pencariannya ada.
 */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let penanda: L.Marker | null = null
let pengamat: ResizeObserver | null = null

const pinIcon = L.divIcon({
  className: '',
  html: `<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>`,
  iconSize: [40, 40],
  iconAnchor: [20, 40],
})

function initPeta() {
  if (!petaEl.value) return

  const titik: L.LatLngTuple = [latLokal.value || -6.2088, lngLokal.value || 106.8456]
  peta = L.map(petaEl.value, {
    center: titik,
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
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  penanda = L.marker(titik, { icon: pinIcon }).addTo(peta)

  pengamat = new ResizeObserver(() => peta?.invalidateSize())
  pengamat.observe(petaEl.value)
  requestAnimationFrame(() => peta?.invalidateSize())
}

onBeforeUnmount(() => {
  pengamat?.disconnect()
  peta?.remove()
  peta = null
})

/* ────────── Harga ────────── */
const rincian = computed(() => hitungHargaAC(paket.value, unit.value))

const rincianTampil = ref(false)
const galat = ref<string | null>(null)

/**
 * Lanjut ke konfirmasi.
 *
 * Pilihan disimpan ke store, bukan dikirim lewat query: isinya majemuk
 * (kondisi bisa lebih dari satu) dan tidak ada gunanya terbaca di URL.
 */
function lanjut() {
  if (!alamatLokal.value) {
    galat.value = 'Lokasi servis belum diisi.'
    return
  }

  galat.value = null
  acStore.set({
    paket: paket.value,
    unit: unit.value,
    tipe: tipe.value,
    kapasitas: kapasitas.value,
    terakhirCuci: terakhirCuci.value,
    kondisi: [...kondisi.value],
    kondisiLainnya: kondisi.value.includes('lainnya') ? kondisiLainnya.value.trim() : '',
    alamat: alamatLokal.value,
    lat: latLokal.value,
    lng: lngLokal.value,
  })

  router.push({ name: 'servis-ac-konfirmasi' })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-40">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-left text-[17px] font-extrabold pr-10">Cuci AC</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!--
        Lokasi: peta pratinjau, pinnya disunting lewat overlay.

        isolate wajib. Panel Leaflet punya z-index sendiri (ubin 400, penanda
        600) dan tanpa stacking context sendiri ia naik menembus elemen lain di
        halaman — termasuk footer yang melayang.
      -->
      <section
        class="isolate bg-(--color-surface-0) rounded-2xl overflow-hidden border border-(--color-outline)/25"
      >
        <button
          type="button"
          aria-label="Ubah lokasi servis"
          class="relative block w-full text-left"
          @click="pemilihTampil = true"
        >
          <div
            ref="petaEl"
            class="w-full h-36 bg-(--color-surface-container) pointer-events-none"
            :style="{ visibility: pemilihTampil ? 'hidden' : 'visible' }"
          ></div>
        </button>

        <div class="p-4 flex items-start justify-between gap-3">
          <div class="flex items-start gap-2.5 min-w-0">
            <Icon name="pin" class="w-5 h-5 text-(--color-azure) shrink-0 mt-0.5" />
            <div class="min-w-0">
              <p class="text-[11px] text-(--color-on-surface-variant)">Lokasi Servis</p>
              <p class="text-[13px] font-bold leading-snug">
                {{ alamatLokal || 'Alamat belum dipilih' }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 text-[12px] font-bold text-(--color-azure) px-3 py-1 rounded-full border border-(--color-azure) active:scale-95 transition-transform"
            @click="pemilihTampil = true"
          >
            Ubah
          </button>
        </div>
      </section>

      <!-- Detail AC -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4">Detail AC</h2>

        <div class="flex items-center justify-between gap-3 pb-5 mb-5 border-b border-(--color-outline)/15">
          <div class="min-w-0">
            <p class="text-[13.5px] font-bold">Jumlah unit AC</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
              2 unit hemat Rp20.000 · 3 unit bebas biaya kunjungan
            </p>
          </div>
          <div class="flex items-center gap-3 bg-(--color-surface-container) rounded-full p-1 shrink-0">
            <button
              type="button"
              aria-label="Kurangi unit"
              class="w-9 h-9 rounded-full bg-(--color-surface-0) flex items-center justify-center active:scale-95 transition-transform"
              @click="kurangUnit"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="w-5 text-center text-[17px] font-extrabold">{{ unit }}</span>
            <button
              type="button"
              aria-label="Tambah unit"
              class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-95 transition-transform"
              @click="tambahUnit"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <p class="text-[12.5px] font-bold mb-2.5">Tipe AC</p>
        <div class="flex flex-wrap gap-2 mb-5">
          <button
            v-for="t in TIPE_AC"
            :key="t.id"
            type="button"
            class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
            :class="
              tipe === t.id
                ? 'bg-(--color-primary-container) border-(--color-azure) text-(--color-on-primary-container)'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            @click="tipe = t.id"
          >
            {{ t.nama }}
          </button>
        </div>

        <p class="text-[12.5px] font-bold mb-2.5">Kapasitas AC (PK)</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="k in KAPASITAS_AC"
            :key="k.id"
            type="button"
            class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
            :class="
              kapasitas === k.id
                ? 'bg-(--color-primary-container) border-(--color-azure) text-(--color-on-primary-container)'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            @click="kapasitas = k.id"
          >
            {{ k.nama }}
          </button>
        </div>

        <!--
          Tipe dan kapasitas TIDAK mengubah harga; keduanya dicatat supaya
          teknisi datang dengan alat yang benar.
        -->
        <p class="mt-3 text-[11px] text-(--color-on-surface-variant) leading-snug">
          Tipe dan kapasitas tidak mengubah harga — keduanya dicatat supaya
          teknisi membawa alat yang sesuai.
        </p>
      </section>

      <!-- Riwayat & kondisi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <p class="text-[12.5px] font-bold mb-2.5">Kapan terakhir AC dicuci?</p>
        <div class="grid grid-cols-2 gap-2.5 pb-5 mb-5 border-b border-(--color-outline)/15">
          <button
            v-for="t in TERAKHIR_CUCI"
            :key="t.id"
            type="button"
            class="p-3 rounded-xl border text-[12.5px] font-semibold transition-colors"
            :class="
              terakhirCuci === t.id
                ? 'bg-(--color-primary-container) border-(--color-azure) text-(--color-on-primary-container)'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            @click="terakhirCuci = t.id"
          >
            {{ t.nama }}
          </button>
        </div>

        <p class="text-[12.5px] font-bold mb-2.5">Kondisi AC (boleh lebih dari satu)</p>
        <div class="flex flex-col gap-2">
          <button
            v-for="k in KONDISI_AC"
            :key="k.id"
            type="button"
            class="flex items-center gap-3 p-3 rounded-xl border text-left transition-colors"
            :class="
              kondisi.includes(k.id)
                ? 'bg-(--color-primary-container)/40 border-(--color-azure)'
                : 'border-(--color-outline)/40'
            "
            :aria-pressed="kondisi.includes(k.id)"
            @click="toggleKondisi(k.id)"
          >
            <span
              class="w-5 h-5 rounded-md border-2 shrink-0 flex items-center justify-center"
              :class="
                kondisi.includes(k.id)
                  ? 'border-(--color-azure) bg-(--color-azure) text-white'
                  : 'border-(--color-outline)'
              "
            >
              <Icon v-if="kondisi.includes(k.id)" name="check" class="w-3 h-3" />
            </span>
            <span class="text-[13px]">{{ k.nama }}</span>
          </button>

          <div v-if="kondisi.includes('lainnya')" class="mt-1">
            <label class="block">
              <span class="sr-only">Kondisi AC lainnya</span>
              <input
                v-model="kondisiLainnya"
                type="text"
                placeholder="Tuliskan kondisi AC lainnya..."
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none transition-colors"
              />
            </label>
          </div>
        </div>
      </section>

      <!-- Paket -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-3">Pilih Paket</h2>

        <div class="flex flex-col gap-3">
          <button
            v-for="p in PAKET_AC"
            :key="p.id"
            type="button"
            class="relative overflow-hidden text-left bg-(--color-surface-0) rounded-2xl p-5 border-2 transition-all"
            :class="
              paket === p.id
                ? 'border-(--color-azure) shadow-[0_10px_30px_rgba(30,155,240,0.15)]'
                : 'border-transparent'
            "
            :aria-pressed="paket === p.id"
            @click="paket = p.id"
          >
            <span
              v-if="p.sorot"
              class="absolute top-0 right-0 bg-(--color-secondary-container) text-(--color-on-secondary-container) text-[10px] font-extrabold px-3 py-1 rounded-bl-lg uppercase tracking-wide"
            >
              {{ p.sorot }}
            </span>

            <span class="flex justify-between items-start gap-3 pr-20">
              <span class="text-[14px] font-extrabold">{{ p.nama }}</span>
            </span>
            <span class="block mt-2 text-[15px] font-extrabold text-(--color-azure)">
              {{ rupiah(p.harga) }}
              <span class="text-[11px] font-medium text-(--color-on-surface-variant)">/ unit</span>
            </span>
            <span class="block mt-2 text-[12.5px] leading-snug text-(--color-on-surface-variant)">
              {{ p.deskripsi }}
            </span>

            <span
              class="absolute bottom-5 right-5 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors"
              :class="
                paket === p.id
                  ? 'border-(--color-azure) bg-(--color-azure) text-white'
                  : 'border-(--color-outline)'
              "
            >
              <Icon v-if="paket === p.id" name="check" class="w-3.5 h-3.5" />
            </span>
          </button>
        </div>
      </section>

      <PemilihLokasi
        :tampil="pemilihTampil"
        :alamat="alamatLokal"
        :lat="latLokal || -6.2088"
        :lng="lngLokal || 106.8456"
        judul="Set lokasi servis"
        label-cari="Cari nama gedung atau alamat"
        @tutup="pemilihTampil = false"
        @pilih="terimaLokasi"
      />
    </main>

    <!-- Ringkasan & aksi -->
    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) rounded-t-2xl shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div v-if="rincianTampil" class="mb-3 pb-3 border-b border-(--color-outline)/20 flex flex-col gap-1.5">
          <div
            v-for="(b, i) in rincian.baris"
            :key="i"
            class="flex justify-between gap-3 text-[12.5px]"
            :class="b.potongan ? 'text-(--color-on-secondary-container)' : 'text-(--color-on-surface-variant)'"
          >
            <span>{{ b.label }}</span>
            <span class="font-bold whitespace-nowrap">
              <template v-if="b.potongan">&minus;</template>{{ rupiah(b.nilai) }}
            </span>
          </div>
        </div>

        <button
          type="button"
          class="w-full flex items-center justify-between gap-3 mb-3"
          :aria-expanded="rincianTampil"
          @click="rincianTampil = !rincianTampil"
        >
          <span class="text-[13px] font-bold">Total Estimasi</span>
          <span class="flex items-center gap-1.5 text-(--color-azure)">
            <span class="text-[20px] font-extrabold">{{ rupiah(rincian.total) }}</span>
            <Icon
              name="chevron-down"
              class="w-4 h-4 transition-transform"
              :class="rincianTampil ? 'rotate-180' : ''"
            />
          </span>
        </button>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="lanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>

  </div>
</template>
