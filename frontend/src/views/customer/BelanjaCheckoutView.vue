<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import BisaBelanjaGaransiArt from '@/components/belanja/BisaBelanjaGaransiArt.vue'
import BisaBelanjaLanggananArt from '@/components/belanja/BisaBelanjaLanggananArt.vue'
import { useBelanjaDraftStore, type BelanjaItem } from '@/stores/belanjaDraft'
import { useLocationStore } from '@/stores/location'
import { useAuthStore } from '@/stores/auth'
import { BIAYA_LAYANAN, labelJarak, ongkirUntuk } from '@/lib/ongkir'
import { usePromoStore } from '@/stores/promo'
import { usePesananStore } from '@/stores/pesanan'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import { kirimCheckout, pesanError, type ItemCheckout } from '@/api/belanja'
import { LANGGANAN, PROMO, hitungPromo, promoBerikutnya, type KonteksPromo } from '@/lib/promo'
import { KATALOG_BY_KATEGORI, adaPromo, hargaFinal, hargaNormal } from '@/lib/belanjaKatalog'
import logoBni from '@/assets/logo_bni.svg'
import logoBri from '@/assets/logo_bri.svg'
import logoDana from '@/assets/logo_dana.svg'
import logoMandiri from '@/assets/logo_mandiri.svg'
import logoOvo from '@/assets/logo_ovo.svg'
import logoQris from '@/assets/logo_qris.svg'
import logoBca from '@/assets/LOGO-BCA.png'
import logoLinkaja from '@/assets/LOGO_LINKAJA.png'
import logoSpay from '@/assets/LOGO-SPAY.png'
import { useSkeleton } from '@/composables/useSkeleton'
import BelanjaCheckoutSkeleton from '@/components/skeleton/BelanjaCheckoutSkeleton.vue'

const router = useRouter()
const belanjaDraft = useBelanjaDraftStore()
const locationStore = useLocationStore()
const authStore = useAuthStore()

const items = computed(() => belanjaDraft.draft.items)
const totalItem = computed(() => items.value.reduce((s, i) => s + i.qty, 0))
const totalBarang = computed(() => items.value.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0))
const itemTanpaHarga = computed(() => items.value.filter((i) => i.harga == null).length)

/**
 * Jarak toko → alamat antar. Toko masih dipilih lewat nama saja (tidak ada
 * koordinat gerai di data), jadi jaraknya belum bisa dihitung dari peta dan
 * dipakai nilai sementara ini. Begitu gerai punya lat/lng, ganti baris ini
 * dengan jarakKm(koordinatGerai, locationStore.draft).
 */
const JARAK_SEMENTARA_KM = 4.2
const jarak = computed(() => JARAK_SEMENTARA_KM)

const ongkirDasar = computed(() => ongkirUntuk(jarak.value))

/* ---------------- Promo & langganan ---------------- */

const promoStore = usePromoStore()

const subtotalKategori = computed(() => {
  const peta: Record<string, number> = {}
  for (const i of items.value) {
    peta[i.kategori] = (peta[i.kategori] ?? 0) + (i.harga ?? 0) * i.qty
  }
  return peta
})

const konteksPromo = computed<KonteksPromo>(() => ({
  totalBarang: totalBarang.value,
  biayaLayanan: BIAYA_LAYANAN,
  ongkir: ongkirDasar.value,
  transaksiKe: promoStore.state.transaksiSelesai + 1,
  waktu: new Date(),
  subtotalKategori: subtotalKategori.value,
}))

const promoAktif = computed(() => PROMO.find((p) => p.id === promoStore.state.dipilih) ?? null)

/**
 * Potongan promo. Kalau promo yang tersimpan ternyata sudah tidak memenuhi
 * syarat (mis. isi keranjang dikurangi sampai di bawah minimum), hasilnya
 * otomatis nol — jadi tagihan tidak pernah memakai potongan yang tak berlaku.
 */
const potongan = computed(() =>
  promoAktif.value
    ? hitungPromo(promoAktif.value, konteksPromo.value)
    : { berlaku: false, kurang: 0, potonganBarang: 0, potonganOngkir: 0, cashback: 0 },
)

const promoBerlaku = computed(() => potongan.value.berlaku)

/** Strip "belanja sekian lagi" di atas harga. */
const berikutnya = computed(() => promoBerikutnya(konteksPromo.value, promoStore.state.dipilih))

/* Langganan: diskon persen di tiap transaksi + aturan gratis ongkirnya sendiri. */
const paket = computed(() => LANGGANAN.find((l) => l.id === promoStore.state.langganan) ?? null)

/** Potongan langganan berupa nominal tetap per transaksi, berkuota bulanan. */
const diskonLangganan = computed(() => {
  const l = paket.value
  if (!l || !l.diskonPerTransaksi) return 0
  if (promoStore.state.diskonTerpakai >= l.kuotaDiskon) return 0
  return Math.min(l.diskonPerTransaksi, totalBarang.value)
})

/**
 * Gratis ongkir dari langganan. Tiap paket punya minimum belanja dan jatah
 * bulanan sendiri, jadi biayanya selalu berbatas.
 */
const ongkirGratisLangganan = computed(() => {
  const l = paket.value
  if (!l) return false
  if (totalBarang.value < l.minOngkirGratis) return false
  if (promoStore.state.ongkirGratisTerpakai >= l.kuotaOngkir) return false
  return true
})

const ongkirGratis = computed(
  () => ongkirGratisLangganan.value || potongan.value.potonganOngkir >= ongkirDasar.value,
)

const biayaOngkir = computed(() => (ongkirGratis.value ? 0 : ongkirDasar.value))
const potonganBarang = computed(() => potongan.value.potonganBarang + diskonLangganan.value)
const hargaBarangAkhir = computed(() => Math.max(0, totalBarang.value - potonganBarang.value))
const cashbackDidapat = computed(() => potongan.value.cashback)

const totalTagihan = computed(() =>
  Math.max(0, hargaBarangAkhir.value + BIAYA_LAYANAN + biayaOngkir.value),
)

/** Yang benar-benar memotong tagihan — cashback tidak termasuk karena masuk saldo. */
const totalHemat = computed(
  () => potonganBarang.value + (ongkirGratis.value ? ongkirDasar.value : 0),
)

function bukaPromo() {
  router.push({ name: 'task-belanja-promo' })
}

function bukaLangganan() {
  router.push({ name: 'task-belanja-langganan' })
}

/* ---------------- Rekomendasi ---------------- */

/**
 * "Mungkin kamu tertarik juga" diambil dari kategori yang sudah ada di
 * keranjang, jadi sarannya nyambung dengan belanjaannya. Barang yang sudah
 * masuk keranjang dan yang stoknya kosong dikeluarkan; yang sedang promo
 * didahulukan.
 */
const rekomendasi = computed(() => {
  const sudahAda = new Set(items.value.map((i) => i.id))
  const kategoriKeranjang = Object.keys(subtotalKategori.value)
  const sumber = kategoriKeranjang.length
    ? kategoriKeranjang
    : ['snack', 'minuman', 'roti', 'buah']

  const kandidat = sumber.flatMap((kat) =>
    (KATALOG_BY_KATEGORI[kat] ?? [])
      .filter((k) => !k.habis && !sudahAda.has(k.id))
      .map((k) => ({
        id: k.id,
        nama: k.nama,
        emoji: k.emoji,
        image: k.image,
        kategori: kat,
        satuan: k.satuan,
        harga: hargaFinal(k, kat),
        hargaCoret: hargaNormal(k, kat),
        promo: adaPromo(k),
      })),
  )

  return kandidat
    .sort((a, b) => {
      if (a.promo !== b.promo) return a.promo ? -1 : 1
      return a.harga - b.harga
    })
    .slice(0, 12)
})

type Rekomendasi = (typeof rekomendasi.value)[number]

function tambahRekomendasi(r: Rekomendasi) {
  const ada = items.value.find((i) => i.id === r.id)
  if (ada) {
    belanjaDraft.updateItemQty(r.id, ada.qty + 1)
    return
  }
  belanjaDraft.addItem({
    id: r.id,
    nama: r.nama,
    catatan: '',
    qty: 1,
    kategori: r.kategori,
    harga: r.harga,
    satuan: r.satuan,
  })
}


const alamat = computed(() => locationStore.draft?.alamat ?? 'Alamat belum dipilih')
const lokasiTerpilih = computed(() => !!locationStore.draft?.alamat)
const namaPenerima = computed(() => authStore.user?.name ?? 'Penerima')

const catatanOpen = ref(false)
const pesananStore = usePesananStore()
/** Kunci tombol Bayar supaya satu ketukan tidak membuat dua pesanan. */
const memproses = ref(false)
/** Pesan penolakan dari server (promo hangus, stok, sesi habis). */
const galatBayar = ref<string | null>(null)

function tambah(item: BelanjaItem) {
  belanjaDraft.updateItemQty(item.id, item.qty + 1)
}
function kurang(item: BelanjaItem) {
  if (item.qty <= 1) belanjaDraft.removeItem(item.id)
  else belanjaDraft.updateItemQty(item.id, item.qty - 1)
}

function ubahAlamat() {
  router.push({ name: 'task-location', query: { category: 'bisabelanja' } })
}

/**
 * Buka Google Maps langsung dari halaman checkout (tab baru) berdasarkan
 * koordinat/alamat yang sudah dipilih, tanpa kembali ke halaman pemilih lokasi.
 */
function bukaDiMaps() {
  const d = locationStore.draft
  if (!d?.alamat) return
  let url: string
  if (d.lat !== 0 && d.lng !== 0) {
    url = `https://www.google.com/maps/search/?api=1&query=${d.lat},${d.lng}`
  } else {
    url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(d.alamat)}`
  }
  window.open(url, '_blank', 'noopener,noreferrer')
}

function kembali() {
  if (window.history.state?.back) router.back()
  else router.push({ name: 'task-belanja-detail' })
}

/* ---------------- Metode pembayaran ---------------- */

interface Metode {
  id: MetodeId
  label: string
  desc?: string
  /** Tidak bisa dipilih (saldo kurang / belum aktivasi). */
  nonaktif?: boolean
  /** Teks aksi di kanan, mis. "Top Up" atau "Aktivasi". */
  aksi?: string
}

/** Saldo dompet internal. Belum ada API-nya, jadi masih 0. */
const SALDO_SERBABISA = 0
const SALDO_GOPAY = 5823

const grupMetode = computed<{ judul: string; daftar: Metode[] }[]>(() => [
  {
    judul: 'Pilihan Pembayaran',
    daftar: [
      {
        id: 'balance',
        label: LABEL_METODE.balance,
        desc:
          SALDO_SERBABISA >= totalTagihan.value
            ? `Saldo Rp${SALDO_SERBABISA.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_SERBABISA.toLocaleString('id-ID')})`,
        nonaktif: SALDO_SERBABISA < totalTagihan.value,
        aksi: SALDO_SERBABISA < totalTagihan.value ? 'Top Up' : undefined,
      },
      {
        id: 'gopay',
        label: LABEL_METODE.gopay,
        desc:
          SALDO_GOPAY >= totalTagihan.value
            ? `Saldo Rp${SALDO_GOPAY.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_GOPAY.toLocaleString('id-ID')})`,
        nonaktif: SALDO_GOPAY < totalTagihan.value,
      },
      { id: 'qris', label: LABEL_METODE.qris, desc: 'Scan pakai aplikasi bank atau e-wallet apa pun' },
    ],
  },
  {
    judul: 'E-Wallet',
    daftar: [
      { id: 'ovo', label: LABEL_METODE.ovo, aksi: 'Aktivasi' },
      { id: 'shopeepay', label: LABEL_METODE.shopeepay, aksi: 'Aktivasi' },
      { id: 'dana', label: LABEL_METODE.dana },
      { id: 'linkaja', label: LABEL_METODE.linkaja },
    ],
  },
  {
    judul: 'Virtual Account',
    daftar: [
      { id: 'bca', label: LABEL_METODE.bca, desc: 'Transfer 24 jam' },
      { id: 'bni', label: LABEL_METODE.bni, desc: 'Transfer 24 jam' },
      { id: 'bri', label: LABEL_METODE.bri, desc: 'Transfer 24 jam' },
      { id: 'mandiri', label: LABEL_METODE.mandiri, desc: 'Transfer 24 jam' },
    ],
  },
  {
    judul: 'Bayar di Tempat',
    daftar: [{ id: 'tunai', label: LABEL_METODE.tunai, desc: 'Siapkan uang pas ya, biar gak repot kembalian' }],
  },
])

const semuaMetode = computed(() => grupMetode.value.flatMap((g) => g.daftar))
const metodeDipilih = ref<MetodeId>('tunai')
const metodeAktif = computed(() => semuaMetode.value.find((m) => m.id === metodeDipilih.value))
const sheetOpen = ref(false)

function pilihMetode(m: Metode) {
  // Pengaman: metode nonaktif tidak boleh terpilih walau tombolnya tertekan.
  if (m.nonaktif || m.aksi === 'Aktivasi') return
  metodeDipilih.value = m.id
  sheetOpen.value = false
}

/**
 * Buat pesanan dari draft saat ini, lalu pindah ke halaman status pembayaran.
 *
 * Semua angka disalin ke objek pesanan, bukan dihitung ulang di halaman status:
 * tarif dan promo bisa berubah kapan saja, sedangkan nota harus tetap
 * menampilkan angka yang benar-benar dibayar saat itu.
 */
async function bayar() {
  if (!totalItem.value || memproses.value) return

  // Server menolak pesanan tanpa koordinat antar, dan memang seharusnya begitu:
  // mitra tidak bisa mengantar ke "Alamat belum dipilih". Sebelum checkout
  // tersambung ke backend, pesanan seperti ini diam-diam tetap dibuat di
  // localStorage. Sekarang dicegat di sini supaya pengguna diantar ke pemilih
  // alamat, bukan disodori pesan error dari validator.
  if (!lokasiTerpilih.value) {
    galatBayar.value = 'Pilih alamat pengantaran dulu ya.'
    ubahAlamat()
    return
  }

  memproses.value = true
  galatBayar.value = null

  // Untuk item berkatalog cukup kirim SKU + qty: server memakai harga dari
  // tabel products, bukan harga yang dikirim browser, supaya tidak bisa
  // dimanipulasi. Harga hanya ikut untuk item "Lainnya" yang diketik manual
  // dan memang tidak punya padanan di katalog.
  const barang: ItemCheckout[] = items.value.map((i) => {
    const manual = i.harga == null
    return {
      sku: manual ? undefined : i.id,
      nama: i.nama,
      kategori: i.kategori,
      satuan: i.satuan,
      qty: i.qty,
      catatan: i.catatan || undefined,
    }
  })

  const lokasi = locationStore.draft

  try {
    const pesanan = await kirimCheckout({
      items: barang,
      jarak_km: jarak.value,
      // Slug promo, bukan id numerik: id baris DB bisa berbeda antar lingkungan,
      // slug-nya tidak.
      promo_slug: promoStore.state.dipilih,
      // Tanpa ini server tidak tahu ada langganan dan menagih harga penuh,
      // padahal layar sudah menampilkan harga yang sudah dipotong.
      subscription_id: promoStore.state.langgananId,
      lokasi_alamat: lokasi?.alamat ?? '',
      lokasi_lat: lokasi?.lat ?? 0,
      lokasi_lng: lokasi?.lng ?? 0,
      toko: belanjaDraft.draft.toko || undefined,
      catatan: belanjaDraft.draft.instruksi || undefined,
      nama_penerima: namaPenerima.value,
      telepon_penerima: authStore.user?.phone ?? undefined,
      metode: metodeDipilih.value,
    })

    // Nota yang disimpan adalah hasil hitungan SERVER, bukan angka di layar.
    // Kalau keduanya berbeda (promo keburu habis kuotanya, harga katalog
    // berubah), yang tampil di halaman status adalah yang benar-benar ditagih.
    pesananStore.simpan(pesanan)

    promoStore.catatTransaksi(pesanan.biaya.cashback)
    if (ongkirGratisLangganan.value) promoStore.pakaiOngkirGratis()
    if (diskonLangganan.value) promoStore.pakaiDiskon()
    promoStore.pilih(null)
    belanjaDraft.clearDraft()

    router.push({ name: 'task-belanja-status-bayar', params: { nomor: pesanan.nomor } })
  } catch (e) {
    // Keranjang sengaja TIDAK dikosongkan saat gagal — pengguna harus bisa
    // memperbaiki promonya lalu membayar ulang tanpa menyusun keranjang lagi.
    galatBayar.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(() => {
  // Status langganan ditarik dari server: kalau paketnya sudah habis masa
  // berlakunya atau dibatalkan di perangkat lain, potongan di layar ikut hilang
  // sebelum pengguna sempat menekan Bayar.
  promoStore.muatLangganan()
  requestAnimationFrame(() => requestAnimationFrame(() => tandaiSiap()))
})
</script>

<template>
  <BelanjaCheckoutSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-40">
      <!-- Header -->
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
          <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Keranjang</h1>
        </div>
      </header>
  
      <main class="max-w-[430px] mx-auto flex flex-col gap-2.5 pt-2.5">
        <!-- Alamat pengiriman -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[16px] font-extrabold mb-3">Alamat Pengiriman</h2>
          <div class="border border-(--color-outline)/20 rounded-2xl p-3.5 flex items-start gap-3">
            <Icon name="pin" class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0">
              <p class="text-[13.5px] font-extrabold truncate">{{ namaPenerima }}</p>
              <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mt-0.5">{{ alamat }}</p>
            </div>
            <button
              v-if="lokasiTerpilih"
              type="button"
              aria-label="Buka di Maps"
              class="w-8 h-8 flex items-center justify-center shrink-0 active:scale-90 transition-colors text-(--color-azure)"
              @click.stop="bukaDiMaps"
            >
              <Icon name="external-link" class="w-4 h-4" />
            </button>
            <button
              type="button"
              aria-label="Ubah alamat"
              class="w-8 h-8 flex items-center justify-center shrink-0 active:scale-90 transition-transform"
              @click="ubahAlamat"
            >
              <Icon name="edit" class="w-4 h-4 text-(--color-on-surface-variant)" />
            </button>
          </div>
  
        </section>
  
        <!-- Estimasi pengiriman -->
        <section class="bg-(--color-surface-0) px-4 py-4 flex items-center gap-2">
          <h2 class="flex-1 text-[15px] font-extrabold">Pengiriman 45 &ndash; 60 Menit</h2>
          <Icon name="clock" class="w-4 h-4 text-(--color-on-surface-variant)" />
        </section>
  
        <!-- Daftar barang -->
        <section class="bg-(--color-surface-0) px-4 py-2">
          <div v-if="!totalItem" class="py-8 text-center">
            <p class="text-[13px] font-bold mb-1">Keranjang kosong</p>
            <p class="text-[12px] text-(--color-on-surface-variant)">Tambahkan barang dulu sebelum membayar.</p>
          </div>
          <ul v-else class="flex flex-col divide-y divide-(--color-outline)/10">
            <li v-for="item in items" :key="item.id" class="py-3.5 flex items-center gap-3">
              <div class="flex-1 min-w-0">
                <p class="text-[13.5px] font-bold leading-snug">{{ item.nama }}</p>
                <p v-if="item.catatan" class="text-[11px] text-(--color-outline) truncate mt-0.5">{{ item.catatan }}</p>
                <p v-if="item.harga != null" class="text-[13px] font-semibold text-(--color-on-surface-variant) mt-1">
                  {{ rp(item.harga) }}
                </p>
                <p v-else class="text-[11px] italic text-(--color-on-surface-variant) mt-1">
                  Harga dikonfirmasi mitra saat belanja
                </p>
              </div>
              <div class="flex items-center bg-(--color-azure) rounded-xl shrink-0">
                <button
                  type="button"
                  :aria-label="`Kurangi ${item.nama}`"
                  class="w-9 h-9 flex items-center justify-center text-white active:scale-90 transition-transform"
                  @click="kurang(item)"
                >
                  <Icon name="minus" class="w-3.5 h-3.5" />
                </button>
                <span class="text-[14px] font-extrabold text-white min-w-6 text-center">{{ item.qty }}</span>
                <button
                  type="button"
                  :aria-label="`Tambah ${item.nama}`"
                  class="w-9 h-9 flex items-center justify-center text-white active:scale-90 transition-transform"
                  @click="tambah(item)"
                >
                  <Icon name="plus" class="w-3.5 h-3.5" />
                </button>
              </div>
            </li>
          </ul>
        </section>
  
        <!-- Catatan -->
        <section class="bg-(--color-surface-0) px-4 py-4">
          <button
            v-if="!catatanOpen && !belanjaDraft.draft.instruksi"
            type="button"
            class="flex items-center gap-2.5 text-black active:scale-95 transition-transform"
            @click="catatanOpen = true"
          >
            <Icon name="clipboard" class="w-5 h-5 text-black" />
            <span class="text-[14px] font-bold text-black">Tambah Catatan</span>
          </button>
          <div v-else>
            <label class="block text-[13px] font-bold mb-2 text-black">Catatan untuk mitra</label>
            <textarea
              v-model="belanjaDraft.draft.instruksi"
              rows="2"
              placeholder="Cth: Kalau stok habis, ganti merek lain yang mirip ya."
              class="w-full bg-(--color-surface-container) border border-(--color-outline)/20 rounded-xl px-3.5 py-2.5 text-[13px] text-black focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 resize-none"
            ></textarea>
          </div>
        </section>
  
        <!-- Promo -->
        <section class="bg-(--color-surface-0) px-4 py-4">
          <button
            type="button"
            class="w-full flex items-center gap-3 text-left active:opacity-70 transition-opacity"
            @click="bukaPromo"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 64 64"
              class="w-10 h-10 shrink-0"
            >
              <defs>
                <linearGradient id="promo-gold" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFD95A"/>
                  <stop offset="100%" stop-color="#E9B72E"/>
                </linearGradient>
              </defs>
  
              <!-- Promo Tag -->
              <path
                d="M12 18V10C12 7.2 14.2 5 17 5H30L53 28L30 51L9 30C6.5 27.5 6.5 23.5 9 21L12 18Z"
                fill="#2196F3"/>
  
              <path
                d="M17 11H28L48 31L30 49L13 32C11.5 30.5 11.5 28 13 26.5L17 23V11Z"
                fill="url(#promo-gold)"/>
  
              <!-- Tag hole -->
              <circle cx="20" cy="16" r="3.5" fill="#1478E8"/>
  
              <!-- Percent symbol -->
              <path
                d="M26 38L39 25"
                stroke="#0A326B"
                stroke-width="4"
                stroke-linecap="round"/>
  
              <circle cx="27" cy="27" r="3.5" fill="#0A326B"/>
              <circle cx="39" cy="38" r="3.5" fill="#0A326B"/>
  
              <!-- Lime brand spark -->
              <path
                d="M8 48L12 45M6 54H11M14 57L16 53"
                stroke="#8BC53F"
                stroke-width="3"
                stroke-linecap="round"/>
            </svg>
            <span class="flex-1 min-w-0">
              <span class="block text-[13.5px] font-extrabold truncate">
                {{ promoBerlaku ? promoAktif?.judul : 'Pakai promo lebih hemat' }}
              </span>
              <span
                class="block text-[11.5px] truncate"
                :class="promoBerlaku ? 'text-(--color-azure) font-bold' : 'text-(--color-on-surface-variant)'"
              >
                {{ promoBerlaku ? `Hemat ${rp(totalHemat)}` : 'Lihat promo yang bisa dipakai' }}
              </span>
            </span>
            <Icon name="chevron-right" class="w-4 h-4 text-(--color-outline) shrink-0" />
          </button>
  
          <template v-if="paket">
            <svg
              v-if="paket.id === 'family'"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 380 48"
              width="100%"
              class="mt-3 w-full h-auto block"
            >
              <defs>
                <!-- CYAN BACKGROUND -->
                <linearGradient id="cyanBg" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#E6FFFD"/>
                  <stop offset="35%" stop-color="#BDEDEA"/>
                  <stop offset="70%" stop-color="#73D4CF"/>
                  <stop offset="100%" stop-color="#3BBEB8"/>
                </linearGradient>

                <!-- CYAN CROWN -->
                <linearGradient id="cyanCrown" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#E9FFFD"/>
                  <stop offset="30%" stop-color="#8DE0DB"/>
                  <stop offset="55%" stop-color="#3BBEB8"/>
                  <stop offset="100%" stop-color="#168F8A"/>
                </linearGradient>

                <!-- SHIMMER -->
                <linearGradient id="cyanShine" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                  <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".85"/>
                  <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                </linearGradient>

                <filter id="cyanShadow"
                        x="-30%"
                        y="-30%"
                        width="160%"
                        height="160%">
                  <feDropShadow
                    dx="0"
                    dy="1.5"
                    stdDeviation="1.5"
                    flood-color="#167F7A"
                    flood-opacity=".25"/>
                </filter>

                <clipPath id="cyanCard">
                  <rect x="0" y="0" width="380" height="48" rx="12"/>
                </clipPath>
              </defs>

              <!-- CYAN CARD -->
              <rect
                x="0"
                y="0"
                width="380"
                height="48"
                rx="12"
                fill="url(#cyanBg)"/>

              <!-- Soft cyan glow -->
              <ellipse
                cx="40"
                cy="24"
                rx="34"
                ry="20"
                fill="#FFFFFF"
                opacity=".22">
                <animate
                  attributeName="opacity"
                  values=".12;.35;.12"
                  dur="2.2s"
                  repeatCount="indefinite"/>
              </ellipse>

              <!-- FAMILY CROWN -->
              <g
                transform="translate(12 13)"
                filter="url(#cyanShadow)">
                <animateTransform
                  attributeName="transform"
                  type="translate"
                  values="12 13;12 11.5;12 13"
                  dur="1.8s"
                  repeatCount="indefinite"/>

                <!-- Crown -->
                <path
                  d="M0 4
                     L3 12
                     H15
                     L18 4
                     L13 8
                     L9 1
                     L5 8
                     Z"
                  fill="url(#cyanCrown)"
                  stroke="#168F8A"
                  stroke-width="1"
                  stroke-linejoin="round"/>

                <!-- Crown band -->
                <path
                  d="M3 12
                     H15
                     L14 15
                     H4
                     Z"
                  fill="#249F9A"/>

                <!-- Crown highlight -->
                <path
                  d="M3 5
                     L5 9
                     L7 7
                     L9 3
                     L11 7
                     L13 9
                     L15 5"
                  fill="none"
                  stroke="#E9FFFD"
                  stroke-width="1.4"
                  stroke-linecap="round"/>

                <!-- Crown jewels -->
                <circle cx="3" cy="5" r="1" fill="#FFFFFF"/>
                <circle cx="9" cy="3" r="1.3" fill="#FFFFFF"/>
                <circle cx="15" cy="5" r="1" fill="#FFFFFF"/>

                <!-- Sparkle -->
                <path
                  d="M21 1
                     L22 4
                     L25 5
                     L22 6
                     L21 9
                     L20 6
                     L17 5
                     L20 4Z"
                  fill="#FFFFFF">
                  <animate
                    attributeName="opacity"
                    values=".2;1;.2"
                    dur="1.4s"
                    repeatCount="indefinite"/>
                </path>
              </g>

              <!-- MOVING SHIMMER -->
              <g
                clip-path="url(#cyanCard)"
                opacity=".3">
                <rect
                  x="-80"
                  y="0"
                  width="55"
                  height="48"
                  fill="url(#cyanShine)"
                  transform="skewX(-20)">
                  <animate
                    attributeName="x"
                    from="-100"
                    to="430"
                    dur="3.2s"
                    repeatCount="indefinite"/>
                </rect>
              </g>

              <!-- TEXT -->
              <text
                x="38"
                y="29"
                font-family="Arial, Helvetica, sans-serif"
                font-size="11.5px"
                font-weight="700"
                fill="#146F6B">
                BisaBelanja {{ paket.nama }} aktif &mdash; hemat {{ rp(diskonLangganan) }}{{
                  ongkirGratisLangganan ? ' + ongkir gratis' : ''
                }}
              </text>

              <!-- Small cyan sparkle -->
              <path
                d="M365 10
                   L366 13
                   L369 14
                   L366 15
                   L365 18
                   L364 15
                   L361 14
                   L364 13Z"
                fill="#FFFFFF"
                opacity=".75">
                <animate
                  attributeName="opacity"
                  values=".25;1;.25"
                  dur="1.7s"
                  repeatCount="indefinite"/>
              </path>
            </svg>

            <svg
              v-else-if="paket.id === 'premium'"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 380 48"
              width="100%"
              class="mt-3 w-full h-auto block"
            >
              <defs>
                <!-- GOLD BACKGROUND -->
                <linearGradient id="premiumGoldBg" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFF8D6"/>
                  <stop offset="30%" stop-color="#FFE9A3"/>
                  <stop offset="65%" stop-color="#F8D35A"/>
                  <stop offset="100%" stop-color="#E7B832"/>
                </linearGradient>

                <!-- PREMIUM CROWN -->
                <linearGradient id="premiumGold" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFF3A6"/>
                  <stop offset="35%" stop-color="#FFD84D"/>
                  <stop offset="65%" stop-color="#F0B82E"/>
                  <stop offset="100%" stop-color="#C98A16"/>
                </linearGradient>

                <!-- SHIMMER -->
                <linearGradient id="goldShine" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                  <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".8"/>
                  <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                </linearGradient>

                <filter id="goldShadow"
                        x="-30%"
                        y="-30%"
                        width="160%"
                        height="160%">
                  <feDropShadow
                    dx="0"
                    dy="1.5"
                    stdDeviation="1.5"
                    flood-color="#9A6A12"
                    flood-opacity=".28"/>
                </filter>

                <clipPath id="goldCard">
                  <rect x="0" y="0" width="380" height="48" rx="12"/>
                </clipPath>
              </defs>

              <!-- GOLD CARD -->
              <rect
                x="0"
                y="0"
                width="380"
                height="48"
                rx="12"
                fill="url(#premiumGoldBg)"/>

              <!-- Subtle premium glow -->
              <ellipse
                cx="40"
                cy="24"
                rx="34"
                ry="20"
                fill="#FFFFFF"
                opacity=".18">
                <animate
                  attributeName="opacity"
                  values=".12;.30;.12"
                  dur="2s"
                  repeatCount="indefinite"/>
              </ellipse>

              <!-- PREMIUM CROWN -->
              <g
                transform="translate(12 13)"
                filter="url(#goldShadow)">
                <!-- Floating animation -->
                <animateTransform
                  attributeName="transform"
                  type="translate"
                  values="12 13;12 11.5;12 13"
                  dur="1.8s"
                  repeatCount="indefinite"/>

                <!-- Crown -->
                <path
                  d="M0 4
                     L3 12
                     H15
                     L18 4
                     L13 8
                     L9 1
                     L5 8
                     Z"
                  fill="url(#premiumGold)"
                  stroke="#C98A16"
                  stroke-width="1"
                  stroke-linejoin="round"/>

                <!-- Crown band -->
                <path
                  d="M3 12
                     H15
                     L14 15
                     H4
                     Z"
                  fill="#D99C1C"/>

                <!-- Crown highlight -->
                <path
                  d="M3 5
                     L5 9
                     L7 7
                     L9 3
                     L11 7
                     L13 9
                     L15 5"
                  fill="none"
                  stroke="#FFF8CE"
                  stroke-width="1.4"
                  stroke-linecap="round"/>

                <!-- Crown jewels -->
                <circle cx="3" cy="5" r="1" fill="#FFFFFF"/>
                <circle cx="9" cy="3" r="1.3" fill="#FFFFFF"/>
                <circle cx="15" cy="5" r="1" fill="#FFFFFF"/>

                <!-- Premium sparkle -->
                <path
                  d="M21 1
                     L22 4
                     L25 5
                     L22 6
                     L21 9
                     L20 6
                     L17 5
                     L20 4Z"
                  fill="#FFFFFF">
                  <animate
                    attributeName="opacity"
                    values=".2;1;.2"
                    dur="1.4s"
                    repeatCount="indefinite"/>
                </path>
              </g>

              <!-- MOVING GOLD SHIMMER -->
              <g
                clip-path="url(#goldCard)"
                opacity=".3">
                <rect
                  x="-80"
                  y="0"
                  width="55"
                  height="48"
                  fill="url(#goldShine)"
                  transform="skewX(-20)">
                  <animate
                    attributeName="x"
                    from="-100"
                    to="430"
                    dur="3s"
                    repeatCount="indefinite"/>
                </rect>
              </g>

              <!-- TEXT -->
              <text
                x="38"
                y="29"
                font-family="Arial, Helvetica, sans-serif"
                font-size="11.5px"
                font-weight="600"
                fill="#7A4B00">
                BisaBelanja {{ paket.nama }} aktif &mdash; hemat {{ rp(diskonLangganan) }}{{
                  ongkirGratisLangganan ? ' + ongkir gratis' : ''
                }}
              </text>

              <!-- Small gold sparkle -->
              <path
                d="M365 10
                   L366 13
                   L369 14
                   L366 15
                   L365 18
                   L364 15
                   L361 14
                   L364 13Z"
                fill="#FFFFFF"
                opacity=".75">
                <animate
                  attributeName="opacity"
                  values=".25;1;.25"
                  dur="1.7s"
                  repeatCount="indefinite"/>
              </path>
            </svg>

            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              width="100%"
              viewBox="0 0 380 48"
              class="mt-3 w-full h-auto block"
            >
              <defs>
                <!-- Platinum Background -->
                <linearGradient id="platinumBg" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#F8FAFC"/>
                  <stop offset="45%" stop-color="#E5E7EB"/>
                  <stop offset="100%" stop-color="#D1D5DB"/>
                </linearGradient>

                <!-- Crown Platinum -->
                <linearGradient id="platinumCrown" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFFFFF"/>
                  <stop offset="25%" stop-color="#E5E7EB"/>
                  <stop offset="50%" stop-color="#F9FAFB"/>
                  <stop offset="75%" stop-color="#CBD5E1"/>
                  <stop offset="100%" stop-color="#94A3B8"/>
                </linearGradient>

                <!-- Shimmer -->
                <linearGradient id="shine" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                  <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".9"/>
                  <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                </linearGradient>

                <filter id="shadow">
                  <feDropShadow
                    dx="0"
                    dy="1"
                    stdDeviation="1.2"
                    flood-color="#64748B"
                    flood-opacity=".25"/>
                </filter>

                <clipPath id="cardClip">
                  <rect x="0" y="0" width="380" height="48" rx="12"/>
                </clipPath>
              </defs>

              <!-- PLATINUM CARD -->
              <rect
                x="0"
                y="0"
                width="380"
                height="48"
                rx="12"
                fill="url(#platinumBg)"/>

              <!-- Subtle platinum glow -->
              <ellipse
                cx="42"
                cy="24"
                rx="32"
                ry="20"
                fill="#FFFFFF"
                opacity=".28">
                <animate
                  attributeName="opacity"
                  values=".18;.38;.18"
                  dur="2.2s"
                  repeatCount="indefinite"/>
              </ellipse>

              <!-- CROWN -->
              <g
                transform="translate(12 13)"
                filter="url(#shadow)">
                <!-- Floating animation -->
                <animateTransform
                  attributeName="transform"
                  type="translate"
                  values="12 13;12 11.5;12 13"
                  dur="1.8s"
                  repeatCount="indefinite"/>

                <!-- Crown -->
                <path
                  d="M0 4
                     L3 12
                     H15
                     L18 4
                     L13 8
                     L9 1
                     L5 8
                     Z"
                  fill="url(#platinumCrown)"
                  stroke="#94A3B8"
                  stroke-width="1"
                  stroke-linejoin="round"/>

                <!-- Crown Band -->
                <path
                  d="M3 12
                     H15
                     L14 15
                     H4
                     Z"
                  fill="#CBD5E1"/>

                <!-- Crown Shine -->
                <path
                  d="M3 5
                     L5 9
                     L7 7
                     L9 3
                     L11 7
                     L13 9
                     L15 5"
                  fill="none"
                  stroke="#FFFFFF"
                  stroke-width="1.3"
                  stroke-linecap="round"
                  opacity=".85"/>

                <!-- Jewels -->
                <circle cx="3" cy="5" r="1" fill="#FFFFFF"/>
                <circle cx="9" cy="3" r="1.3" fill="#FFFFFF"/>
                <circle cx="15" cy="5" r="1" fill="#FFFFFF"/>

                <!-- Sparkle -->
                <g fill="#FFFFFF">
                  <path
                    d="M20 1
                       L21 4
                       L24 5
                       L21 6
                       L20 9
                       L19 6
                       L16 5
                       L19 4Z">
                    <animate
                      attributeName="opacity"
                      values=".2;1;.2"
                      dur="1.4s"
                      repeatCount="indefinite"/>
                  </path>
                </g>
              </g>

              <!-- MOVING SHIMMER -->
              <g clip-path="url(#cardClip)" opacity=".35">
                <rect
                  x="-80"
                  y="0"
                  width="55"
                  height="48"
                  fill="url(#shine)"
                  transform="skewX(-20)">
                  <animate
                    attributeName="x"
                    from="-100"
                    to="430"
                    dur="3.2s"
                    repeatCount="indefinite"/>
                </rect>
              </g>

              <!-- TEXT -->
              <text
                x="38"
                y="29"
                font-family="Arial, Helvetica, sans-serif"
                font-size="11.5px"
                font-weight="600"
                fill="#92400E">
                BisaBelanja {{ paket.nama }} aktif &mdash; hemat {{ rp(diskonLangganan) }}{{
                  ongkirGratisLangganan ? ' + ongkir gratis' : ''
                }}
              </text>

              <!-- Tiny platinum sparkle -->
              <path
                d="M366 10
                   L367 13
                   L370 14
                   L367 15
                   L366 18
                   L365 15
                   L362 14
                   L365 13Z"
                fill="#FFFFFF"
                opacity=".7">
                <animate
                  attributeName="opacity"
                  values=".3;1;.3"
                  dur="1.8s"
                  repeatCount="indefinite"/>
              </path>
            </svg>
          </template>
        </section>
  
        <!-- Langganan -->
        <section class="px-4">
          <button
            type="button"
            aria-label="Lihat paket langganan"
            class="block w-full active:scale-[0.99] transition-transform"
            @click="bukaLangganan"
          >
            <BisaBelanjaLanggananArt />
          </button>
        </section>
  
        <!-- Jaminan -->
        <section class="px-4">
          <BisaBelanjaGaransiArt />
        </section>
  
        <!-- Rincian biaya -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Harga Barang</span>
              <span class="text-[13px] font-bold flex items-center gap-1.5">
                <span
                  v-if="potongan.potonganBarang"
                  class="text-[11.5px] font-semibold text-(--color-on-surface-variant) line-through decoration-(--color-error)"
                >
                  {{ rp(totalBarang) }}
                </span>
                {{ rp(hargaBarangAkhir) }}
              </span>
            </div>
  
            <div v-if="diskonLangganan" class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">
                Diskon langganan {{ paket?.nama }}
                <span class="text-(--color-outline)">(potongan tetap)</span>
              </span>
              <span class="text-[13px] font-bold text-(--color-secondary)">&minus;{{ rp(diskonLangganan) }}</span>
            </div>
  
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Biaya Layanan</span>
              <span class="text-[13px] font-bold">{{ rp(BIAYA_LAYANAN) }}</span>
            </div>
  
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">
                Total Ongkir
                <span class="text-(--color-outline)">({{ labelJarak(jarak) }})</span>
              </span>
              <span class="text-[13px] font-bold flex items-center gap-1.5">
                <span
                  v-if="ongkirGratis"
                  class="text-[11.5px] font-semibold text-(--color-on-surface-variant) line-through decoration-(--color-error)"
                >
                  {{ rp(ongkirDasar) }}
                </span>
                <span :class="ongkirGratis ? 'text-(--color-secondary)' : ''">
                  {{ ongkirGratis ? 'Gratis' : rp(biayaOngkir) }}
                </span>
              </span>
            </div>
  
            <div class="border-t border-(--color-outline)/15 pt-2.5 flex items-center justify-between">
              <span class="text-[14px] font-extrabold">Total Tagihan</span>
              <span class="text-[15px] font-extrabold">{{ rp(totalTagihan) }}</span>
            </div>
  
            <p v-if="totalHemat" class="text-[11.5px] font-bold text-(--color-secondary) text-right">
              Kamu hemat {{ rp(totalHemat) }}
            </p>
  
            <!-- Cashback tidak memotong tagihan sekarang: masuk saldo untuk order berikutnya -->
            <div
              v-if="cashbackDidapat"
              class="flex items-start gap-2 bg-(--color-secondary-container)/60 p-3 rounded-xl mt-1"
            >
              <span class="text-sm shrink-0">💰</span>
              <p class="text-[11px] text-(--color-on-surface-variant) leading-relaxed">
                Kamu dapat cashback <span class="font-extrabold text-(--color-on-surface)">{{ rp(cashbackDidapat) }}</span>
                yang masuk ke saldo dan bisa dipakai untuk belanja berikutnya.
              </p>
            </div>
          </div>
  
          <div v-if="itemTanpaHarga" class="flex items-start gap-2 bg-(--color-azure)/8 p-3 rounded-xl mt-3.5">
            <Icon name="info" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
            <p class="text-[11px] text-(--color-on-surface-variant) leading-relaxed">
              {{ itemTanpaHarga }} barang ditulis manual dan belum punya harga, jadi belum masuk Total Harga
              Barang. Mitra akan mengonfirmasi harganya saat belanja.
            </p>
          </div>
        </section>
  
        <!-- Mungkin kamu tertarik juga -->
        <section v-if="rekomendasi.length" class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[15px] font-extrabold mb-1">Mungkin kamu tertarik juga!</h2>
          <p v-if="berikutnya" class="text-[11.5px] text-(--color-on-surface-variant) mb-3">
            Tambah {{ rp(berikutnya.kurang) }} lagi untuk membuka {{ berikutnya.promo.judul }}
          </p>
          <div class="flex gap-2.5 overflow-x-auto -mx-4 px-4 pb-1">
            <div
              v-for="r in rekomendasi"
              :key="r.id"
              class="relative w-[112px] shrink-0 bg-(--color-surface-0) rounded-2xl p-2 border border-(--color-outline)/20"
            >
              <div class="w-full aspect-square rounded-xl bg-(--color-surface-container) mb-2 flex items-center justify-center overflow-hidden">
                <img v-if="r.image" :src="r.image" :alt="r.nama" class="w-full h-full object-cover" loading="lazy" />
                <span v-else class="text-3xl leading-none">{{ r.emoji }}</span>
              </div>
              <button
                type="button"
                :aria-label="`Tambah ${r.nama}`"
                class="absolute top-1 right-1 z-10 w-7 h-7 rounded-full bg-(--color-surface-0) text-(--color-azure) border border-(--color-outline)/25 shadow-sm flex items-center justify-center active:scale-90 transition-transform"
                @click="tambahRekomendasi(r)"
              >
                <Icon name="plus" class="w-3.5 h-3.5" />
              </button>
              <p
                class="text-[12px] font-extrabold leading-tight"
                :class="r.promo ? 'text-(--color-error)' : 'text-(--color-on-surface)'"
              >
                {{ rp(r.harga) }}
              </p>
              <p
                v-if="r.promo"
                class="text-[9.5px] font-semibold text-(--color-on-surface-variant) line-through decoration-(--color-error)"
              >
                {{ rp(r.hargaCoret) }}
              </p>
              <h3 class="text-[10px] font-semibold leading-snug line-clamp-2 mt-0.5">{{ r.nama }}</h3>
            </div>
          </div>
        </section>
      </main>
  
      <!-- Bar bayar -->
      <footer class="fixed bottom-0 w-full z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
        <!-- Strip promo: duduk tepat di atas harga, dorongan menuju promo terdekat -->
        <button
          v-if="berikutnya"
          type="button"
          class="w-full bg-(--color-azure) text-left active:opacity-90 transition-opacity"
          @click="bukaPromo"
        >
          <div class="max-w-[430px] mx-auto px-4 py-2.5 flex items-center gap-2.5">
            <svg
              viewBox="0 0 500 500"
              xmlns="http://www.w3.org/2000/svg"
              class="w-7 h-7 shrink-0"
            >
              <defs>
                <linearGradient id="chk-starGold" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFE894"/>
                  <stop offset="50%" stop-color="#FFC633"/>
                  <stop offset="100%" stop-color="#F09A00"/>
                </linearGradient>
                <linearGradient id="chk-starRim" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFF5CC"/>
                  <stop offset="100%" stop-color="#E08900"/>
                </linearGradient>
                <linearGradient id="chk-starLime" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#B6F14C"/>
                  <stop offset="100%" stop-color="#63C61C"/>
                </linearGradient>
                <linearGradient id="chk-starOrange" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0%" stop-color="#FFC061"/>
                  <stop offset="100%" stop-color="#FF8A00"/>
                </linearGradient>
                <filter id="chk-starShadow" x="-30%" y="-30%" width="160%" height="160%">
                  <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0B2E5C" flood-opacity="0.3"/>
                </filter>
              </defs>
  
              <!-- Sparkles -->
              <g filter="url(#chk-starShadow)">
                <path fill="url(#chk-starLime)" transform="translate(70,120) rotate(-12) scale(0.8)" d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                <path fill="url(#chk-starOrange)" transform="translate(430,140) rotate(15) scale(0.75)" d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                <circle cx="80" cy="400" r="14" fill="url(#chk-starOrange)"/>
                <circle cx="420" cy="380" r="12" fill="url(#chk-starLime)"/>
              </g>
  
              <!-- 3D Star -->
              <g filter="url(#chk-starShadow)">
                <path d="M 250,45 L 312,172 L 452,192 L 351,291 L 375,431 L 250,365 L 125,431 L 149,291 L 48,192 L 188,172 Z"
                      fill="url(#chk-starGold)" 
                      stroke="url(#chk-starRim)" 
                      stroke-width="12" 
                      stroke-linejoin="round"/>
                <path d="M 250,45 L 250,250 L 188,172 Z" fill="#FFFFFF" opacity="0.35"/>
                <path d="M 250,45 L 250,250 L 312,172 Z" fill="#FFFFFF" opacity="0.15"/>
                <path d="M 48,192 L 250,250 L 149,291 Z" fill="#FFFFFF" opacity="0.25"/>
                <path d="M 452,192 L 250,250 L 351,291 Z" fill="#000000" opacity="0.12"/>
                <path d="M 375,431 L 250,250 L 250,365 Z" fill="#000000" opacity="0.15"/>
                <path d="M 125,431 L 250,250 L 250,365 Z" fill="#FFFFFF" opacity="0.2"/>
                <path d="M 175,130 Q 250,80 325,130" fill="none" stroke="#FFFFFF" stroke-width="12" stroke-linecap="round" opacity="0.45"/>
              </g>
            </svg>
            <p class="flex-1 min-w-0 text-[12.5px] font-extrabold text-white truncate">
              Belanja {{ rp(berikutnya.kurang) }} lagi, ada diskon {{ rp(berikutnya.nilai) }}!
            </p>
            <Icon name="chevron-right" class="w-4 h-4 text-white/80 shrink-0" />
          </div>
        </button>
  
        <div class="max-w-[430px] mx-auto px-4 py-3.5 flex items-center justify-between gap-4 border-t border-(--color-outline)/15">
          <div class="flex flex-col min-w-0">
            <button
              type="button"
              class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
              @click="sheetOpen = true"
            >
              {{ metodeAktif?.label ?? 'Pilih Metode Pembayaran' }}
              <Icon name="chevron-down" class="w-3.5 h-3.5" />
            </button>
            <span class="flex items-center gap-2 min-w-0">
              <span class="text-[20px] font-extrabold leading-tight truncate">{{ rp(totalTagihan) }}</span>
              <span
                v-if="totalHemat"
                class="shrink-0 text-[10.5px] font-bold text-(--color-secondary) bg-(--color-secondary-container) rounded-full px-2 py-0.5"
              >
                Hemat {{ rp(totalHemat) }}
              </span>
            </span>
          </div>
          <button
            type="button"
            :disabled="!totalItem || memproses"
            class="flex-1 bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-all disabled:opacity-40 disabled:active:scale-100"
            @click="bayar"
          >
            {{ memproses ? 'Memproses…' : 'Bayar' }}
          </button>
        </div>
        <p
          v-if="galatBayar"
          role="alert"
          class="max-w-[430px] mx-auto px-4 pb-3 -mt-1 text-[12px] font-semibold text-(--color-error)"
        >
          {{ galatBayar }}
        </p>
      </footer>
  
      <!-- Sheet metode pembayaran, naik dari bawah -->
      <Teleport to="body">
        <div v-if="sheetOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
          <Transition
            appear
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
          >
            <div class="absolute inset-0 bg-black/45" @click="sheetOpen = false"></div>
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
                  @click="sheetOpen = false"
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
                    class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors"
                    :class="[
                      m.nonaktif || m.aksi === 'Aktivasi' ? 'cursor-default' : 'active:bg-(--color-surface-container)',
                      metodeDipilih === m.id ? 'bg-(--color-azure)/8' : '',
                    ]"
                    @click="pilihMetode(m)"
                  >
                    <span class="w-9 h-9 flex items-center justify-center shrink-0">
                      <!-- Serbabisa Balance -->
                      <svg v-if="m.id === 'balance'" class="w-8 h-8" viewBox="0 0 32 32" fill="none">
                        <rect width="32" height="32" rx="8" fill="#1E9BF0" />
                        <rect x="6" y="10" width="20" height="13" rx="3" fill="#fff" />
                        <rect x="6" y="10" width="20" height="4" rx="2" fill="#0B67B0" />
                        <circle cx="21.5" cy="18.5" r="2.5" fill="#8BC53F" />
                      </svg>
                      <svg v-else-if="m.id === 'gopay'" class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                        <rect width="24" height="24" rx="12" fill="#00AED6" />
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#fff" />
                      </svg>
                      <img v-else-if="m.id === 'qris'" :src="logoQris" alt="QRIS" class="h-5 w-auto object-contain" />
                      <img v-else-if="m.id === 'ovo'" :src="logoOvo" alt="OVO" class="h-6 w-auto object-contain" />
                      <img v-else-if="m.id === 'shopeepay'" :src="logoSpay" alt="ShopeePay" class="h-9 w-auto object-contain scale-110" />
                      <img v-else-if="m.id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
                      <img v-else-if="m.id === 'linkaja'" :src="logoLinkaja" alt="LinkAja" class="h-6 w-auto object-contain" />
                      <img v-else-if="m.id === 'bca'" :src="logoBca" alt="BCA" class="h-6 w-auto object-contain" />
                      <img v-else-if="m.id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
                      <img v-else-if="m.id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
                      <img v-else-if="m.id === 'mandiri'" :src="logoMandiri" alt="Mandiri" class="h-6 w-auto object-contain" />
                      <svg v-else class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none">
                        <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2" />
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                        <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                      </svg>
                    </span>
  
                    <span class="flex-1 min-w-0">
                      <span
                        class="block text-[14px] font-bold truncate"
                        :class="m.nonaktif || m.aksi === 'Aktivasi' ? 'text-(--color-outline)' : 'text-(--color-on-surface)'"
                      >
                        {{ m.label }}
                      </span>
                      <span v-if="m.desc" class="block text-[11.5px] text-(--color-on-surface-variant) truncate">
                        {{ m.desc }}
                      </span>
                    </span>
  
                    <span v-if="m.aksi" class="text-[12.5px] font-bold text-(--color-azure) shrink-0">{{ m.aksi }}</span>
                    <span
                      v-else-if="metodeDipilih === m.id"
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
    </div>
  </template>
</template>
