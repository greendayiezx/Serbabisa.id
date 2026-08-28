<script setup lang="ts">
/**
 * Ringkasan Pemesanan — BisaBersih Deep Cleaning.
 *
 * Layar terakhir sebelum pesanan dibuat, mengikuti pola halaman konfirmasi
 * BisaBersih Kantor: pengguna meninjau seluruh pilihan dan bisa mengubah
 * sebagiannya di tempat, tanpa kembali ke halaman sebelumnya.
 *
 * Bedanya satu: di sini ada LAYANAN TAMBAHAN yang masih bisa dipilih. Yang
 * sudah termasuk paket ditampilkan sebagai "sudah termasuk" dan tidak bisa
 * dibeli lagi — harganya memang sudah ada di dalam harga paket.
 *
 * Seperti checkout lain, halaman ini hanya MENGIRIM PILIHAN. Total di layar
 * adalah estimasi; yang menagih adalah App\Services\DeepTarif di server.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useBersihDeepStore } from '@/stores/bersihDeep'
import { useLocationStore } from '@/stores/location'
import { pesanDeep } from '@/api/bersihDeep'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import bersihArt from '@/assets/category-bersih-bersih.svg'
import {
  ADD_ON_DEEP,
  LUAS_TERMASUK,
  RUANGAN_TERMASUK,
  TARIF_LUAS,
  TARIF_RUANGAN,
  cariPaketDeep,
  hitungHargaDeep,
  type AddOnDeepId,
} from '@/lib/bersih/hargaBersihDeep'
import { PROMO_DEEP, cariPromoDeep, hitungPromoDeep } from '@/lib/promo/promoBersihDeep'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const deepStore = useBersihDeepStore()
const locationStore = useLocationStore()

const draft = computed(() => deepStore.draft)

/**
 * Tanpa pilihan dari langkah sebelumnya, halaman ini tidak punya apa pun untuk
 * dikonfirmasi — kembalikan ke awal alih-alih menampilkan angka kosong.
 */
onMounted(() => {
  if (!draft.value) {
    router.replace({ name: 'task-bersih-deep' })
    return
  }

  luasLokal.value = draft.value.luasM2
  ruanganLokal.value = draft.value.jumlahRuangan
  tanggalLokal.value = draft.value.tanggal
  waktuLokal.value = draft.value.waktu

  const lokasi = locationStore.draft
  alamatLokal.value = lokasi?.alamat ?? ''
  latLokal.value = lokasi?.lat ?? 0
  lngLokal.value = lokasi?.lng ?? 0

  // Kode yang dipilih di katalog promo kembali lewat ?promo=. Diterapkan lewat
  // jalur yang sama dengan pilihan manual, jadi syaratnya tetap diperiksa.
  const dariKatalog = String(route.query.promo ?? '')
  if (dariKatalog) pilihPromo(dariKatalog.toUpperCase())
})

/** Buka katalog promo layanan ini, bawa nilai tagihan supaya bisa disaring. */
function keKatalogPromo() {
  router.push({
    name: 'promo-layanan',
    params: { layanan: 'deep' },
    query: {
      dari: '/tasks/new/bersih/deep/konfirmasi',
      nilai: String(rincian.value.total),
    },
  })
}

const paketAktif = computed(() => cariPaketDeep(draft.value?.paketId))

/* ────────── Yang bisa diubah di halaman ini ────────── */
const luasLokal = ref(LUAS_TERMASUK)
const ruanganLokal = ref(RUANGAN_TERMASUK)
const tanggalLokal = ref('')
const waktuLokal = ref('')
const catatanLokal = ref('')

const editLuas = ref(false)
const editRuangan = ref(false)
const editJadwal = ref(false)
const editCatatan = ref(false)

function tambahRuangan() {
  ruanganLokal.value++
}

function kurangRuangan() {
  if (ruanganLokal.value > 1) ruanganLokal.value--
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
}

/* ────────── Layanan tambahan ────────── */

/**
 * Add-on yang sudah termasuk paket tidak masuk daftar pilihan — ia ditampilkan
 * terpisah sebagai "sudah termasuk". Menawarkannya sebagai pilihan berbayar
 * berarti menjual pekerjaan yang sama dua kali.
 */
const addOnDipilih = ref<AddOnDeepId[]>([])

const addOnBisaDipilih = computed(() =>
  ADD_ON_DEEP.filter((a) => !paketAktif.value.termasuk.includes(a.id)),
)

const addOnTermasuk = computed(() =>
  ADD_ON_DEEP.filter((a) => paketAktif.value.termasuk.includes(a.id)),
)

function togglAddOn(id: AddOnDeepId) {
  const i = addOnDipilih.value.indexOf(id)
  if (i >= 0) addOnDipilih.value.splice(i, 1)
  else addOnDipilih.value.push(id)
}

/** Harga yang benar-benar dibayar untuk satu add-on pada pesanan ini. */
function hargaAddOn(id: AddOnDeepId): number {
  const a = ADD_ON_DEEP.find((x) => x.id === id)
  if (!a) return 0
  return a.harga * (a.perRuangan ? ruanganLokal.value : 1)
}

/* ────────── Harga ────────── */
const rincian = computed(() =>
  hitungHargaDeep({
    paketId: paketAktif.value.id,
    luasM2: luasLokal.value,
    jumlahRuangan: ruanganLokal.value,
    addOn: addOnDipilih.value,
  }),
)

/* ────────── Promo ────────── */

const promoKode = ref<string | null>(null)
const promoTerpakai = computed(() => cariPromoDeep(promoKode.value))
const hasilPromo = computed(() => hitungPromoDeep(promoTerpakai.value, rincian.value.total))

/** Yang dibayar setelah potongan. Server menghitung angka yang sama. */
const total = computed(() => rincian.value.total - hasilPromo.value.potongan)

/**
 * Daftar promo beserta keadaannya pada pesanan ini.
 *
 * Yang belum memenuhi syarat tetap DITAMPILKAN dengan kekurangannya, bukan
 * disembunyikan: pelanggan jadi tahu promonya ada dan berapa lagi yang kurang.
 */
const semuaPromo = computed(() =>
  PROMO_DEEP.map((p) => {
    const hasil = hitungPromoDeep(p, rincian.value.total)
    const cocokPaket = p.kode !== 'PINDAHBERSIH' || paketAktif.value.id !== 'sanitasi_total'

    return {
      promo: p,
      potongan: hasil.potongan,
      kurang: hasil.kurang,
      bisa: hasil.berlaku && cocokPaket,
      alasan: !cocokPaket
        ? 'Hanya untuk paket Move-In & Pasca Renovasi'
        : hasil.kurang > 0
          ? `Kurang ${rupiah(hasil.kurang)} lagi`
          : null,
    }
  }),
)

/** Kode yang diketik sendiri, jalur yang sama dengan halaman kantor. */
const kodeInput = ref('')
const promoPesan = ref<{ ok: boolean; teks: string } | null>(null)

function pakaiKode() {
  const kode = kodeInput.value.trim().toUpperCase()
  if (!kode) return

  const promo = cariPromoDeep(kode)
  if (!promo) {
    promoPesan.value = { ok: false, teks: 'Kode promo tidak ditemukan.' }
    return
  }

  pilihPromo(promo.kode)
}

/**
 * Menerapkan promo, atau menolaknya dengan ALASAN.
 *
 * Kode yang belum memenuhi syarat tidak diam-diam diabaikan: pengguna diberi
 * tahu kurang berapa lagi, atau paket mana yang berlaku.
 */
function pilihPromo(kode: string) {
  // Dicari dari SEMUA promo, bukan hanya yang tampil: kode yang diketik sendiri
  // harus tetap dapat alasan penolakan yang benar.
  const d = semuaPromo.value.find((x) => x.promo.kode === kode)
  if (!d) return

  if (!d.bisa) {
    promoPesan.value = { ok: false, teks: d.alasan ?? 'Promo ini belum bisa dipakai.' }
    return
  }

  promoKode.value = d.promo.kode
  kodeInput.value = ''
  promoPesan.value = { ok: true, teks: `${d.promo.kode} dipakai — hemat ${rupiah(d.potongan)}.` }
}

function lepasPromo() {
  promoKode.value = null
  promoPesan.value = null
}

/** Ada promo yang benar-benar bisa dipakai pada tagihan sekarang? */

/* ────────── Metode pembayaran ────────── */
const METODE: MetodeId[] = ['bca', 'mandiri', 'bni', 'gopay', 'ovo', 'qris']
const metodeDipilih = ref<MetodeId>('bca')
const sheetOpen = ref(false)
const metodeLabel = computed(() => LABEL_METODE[metodeDipilih.value] ?? metodeDipilih.value)

/* ────────── Bayar ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)

/** Terisi kalau pesanan sudah dibuat tapi layar belum berpindah. */
const nomorPesanan = ref<string | null>(null)

function keStatus() {
  if (!nomorPesanan.value) return
  deepStore.hapus()
  router.replace({ name: 'task-bersih-status-bayar', params: { nomor: nomorPesanan.value } })
}

async function bayar() {
  if (memproses.value || !draft.value) return

  if (!alamatLokal.value) {
    galat.value = 'Alamat pengerjaan belum diisi.'
    return
  }
  if (!tanggalLokal.value || !waktuLokal.value) {
    galat.value = 'Jadwal kunjungan belum dipilih.'
    editJadwal.value = true
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanDeep({
      paket: paketAktif.value.id,
      luas_m2: luasLokal.value,
      jumlah_ruangan: ruanganLokal.value,
      add_on: [...addOnDipilih.value],
      catatan: catatanLokal.value || undefined,
      tanggal: tanggalLokal.value,
      waktu: waktuLokal.value,
      lokasi_alamat: alamatLokal.value,
      lokasi_lat: latLokal.value,
      lokasi_lng: lngLokal.value,
      metode: metodeDipilih.value,
      // Hanya kodenya. Potongannya dihitung ulang server — angka di layar ini
      // tidak pernah jadi dasar tagihan.
      promo_kode: promoTerpakai.value?.kode,
    })

    /*
     * Kalau server menolak promonya, pesanan tetap dibuat — tapi dengan harga
     * penuh. Pengguna harus tahu itu sebelum layarnya berganti, bukan
     * menemukannya sendiri di rincian tagihan.
     */
    if (hasil.rincian?.promo_ditolak) {
      nomorPesanan.value = hasil.nomor_invoice ?? String(hasil.id)
      galat.value = `Promo tidak terpakai: ${hasil.rincian.promo_ditolak} Pesanan tetap dibuat dengan harga penuh.`
      return
    }

    deepStore.hapus()

    /*
     * Ke layar status BisaBersih yang sudah ada: ia menanyakan status ke server
     * sampai ada cleaner yang menerima, lalu berganti sendiri jadi layar
     * "pesanan diterima". Tidak perlu layar tunggu baru khusus deep cleaning.
     */
    router.replace({
      name: 'task-bersih-status-bayar',
      params: { nomor: hasil.nomor_invoice ?? String(hasil.id) },
    })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

function formatJadwal(t: string, w: string): string {
  if (!t) return 'Belum dipilih'
  return `${t}${w ? ' · ' + w : ''}`
}
</script>

<template>
  <div v-if="draft" class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-36">
    <!-- Header -->
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Ringkasan Pemesanan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  1) ALAMAT PENGERJAAN                                   ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl overflow-hidden">
        <div class="px-5 pt-4 pb-1 flex items-center justify-between gap-3">
          <h2 class="text-[14px] font-extrabold">Alamat Pengerjaan</h2>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="pemilihTampil = true"
          >
            Ubah
          </button>
        </div>
        <div class="px-5 pb-4">
          <p class="text-[13px] leading-snug text-(--color-on-surface-variant) mt-1">
            {{ alamatLokal || 'Belum diisi' }}
          </p>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  2) PAKET & DETAIL                                      ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl overflow-hidden">
        <div class="flex items-center gap-3.5 px-5 pt-4 pb-3 border-b border-(--color-outline)/8">
          <div class="flex-1 min-w-0">
            <h2 class="text-[16px] font-extrabold truncate">{{ paketAktif.nama }}</h2>
            <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">Deep Cleaning</p>
          </div>
          <div class="w-16 h-16 shrink-0 rounded-xl bg-(--color-primary-container) flex items-center justify-center">
            <img :src="bersihArt" alt="Deep Cleaning" class="w-12 h-12 object-contain" />
          </div>
        </div>

        <!-- Luas -->
        <div class="px-5 py-3.5 border-b border-(--color-outline)/8">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[12.5px] text-(--color-on-surface-variant)">Luas ruangan</span>
            <div class="flex items-center gap-3">
              <span class="text-[13px] font-bold">{{ luasLokal }} m²</span>
              <button
                type="button"
                class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
                @click="editLuas = !editLuas"
              >
                Ubah
              </button>
            </div>
          </div>

          <div v-if="editLuas" class="mt-3">
            <input
              v-model.number="luasLokal"
              type="number"
              min="10"
              max="1000"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-(--color-azure)/40 focus:border-(--color-azure) outline-none"
            />
            <p class="mt-1.5 text-[11.5px] text-(--color-on-surface-variant)">
              Sudah termasuk {{ LUAS_TERMASUK }} m². Kelebihannya
              Rp{{ TARIF_LUAS.toLocaleString('id-ID') }}/m².
            </p>
          </div>
        </div>

        <!-- Jumlah ruangan -->
        <div class="px-5 py-3.5 border-b border-(--color-outline)/8">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[12.5px] text-(--color-on-surface-variant)">Jumlah ruangan</span>
            <div class="flex items-center gap-3">
              <span class="text-[13px] font-bold">{{ ruanganLokal }} ruangan</span>
              <button
                type="button"
                class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
                @click="editRuangan = !editRuangan"
              >
                Ubah
              </button>
            </div>
          </div>

          <div v-if="editRuangan" class="mt-3">
            <div class="flex items-center justify-between bg-(--color-surface-container) rounded-xl p-2">
              <button
                type="button"
                aria-label="Kurangi ruangan"
                class="w-9 h-9 rounded-lg bg-(--color-surface-0) flex items-center justify-center active:scale-95 transition-transform"
                @click="kurangRuangan"
              >
                <Icon name="minus" class="w-4 h-4" />
              </button>
              <span class="text-[16px] font-extrabold">{{ ruanganLokal }}</span>
              <button
                type="button"
                aria-label="Tambah ruangan"
                class="w-9 h-9 rounded-lg bg-(--color-surface-0) flex items-center justify-center active:scale-95 transition-transform"
                @click="tambahRuangan"
              >
                <Icon name="plus" class="w-4 h-4" />
              </button>
            </div>
            <p class="mt-1.5 text-[11.5px] text-(--color-on-surface-variant)">
              Sudah termasuk {{ RUANGAN_TERMASUK }} ruangan. Tambahannya
              Rp{{ TARIF_RUANGAN.toLocaleString('id-ID') }}/ruangan.
            </p>
          </div>
        </div>

        <!-- Jadwal -->
        <div class="px-5 py-3.5 border-b border-(--color-outline)/8">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[12.5px] text-(--color-on-surface-variant)">Jadwal kunjungan</span>
            <div class="flex items-center gap-3">
              <span class="text-[13px] font-bold">{{ formatJadwal(tanggalLokal, waktuLokal) }}</span>
              <button
                type="button"
                class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
                @click="editJadwal = !editJadwal"
              >
                Ubah
              </button>
            </div>
          </div>

          <div v-if="editJadwal" class="mt-3 grid grid-cols-2 gap-3">
            <DatePickerField v-model="tanggalLokal" wajib :ditandai="false" />
            <TimePickerField v-model="waktuLokal" wajib :ditandai="false" />
          </div>
        </div>

        <!-- Catatan -->
        <div class="px-5 py-3.5">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[12.5px] text-(--color-on-surface-variant)">Catatan untuk cleaner</span>
            <button
              type="button"
              class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
              @click="editCatatan = !editCatatan"
            >
              {{ catatanLokal ? 'Ubah' : 'Tambah' }}
            </button>
          </div>

          <p v-if="catatanLokal && !editCatatan" class="mt-1.5 text-[12.5px] leading-snug">
            {{ catatanLokal }}
          </p>

          <textarea
            v-if="editCatatan"
            v-model="catatanLokal"
            rows="3"
            placeholder="Misal: ada hewan peliharaan, kunci dititip satpam"
            class="w-full mt-2 rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-(--color-azure)/40 focus:border-(--color-azure) outline-none resize-none"
          />
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  3) LAYANAN TAMBAHAN                                    ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold">Layanan Tambahan</h3>
        <p class="mt-1 mb-3.5 text-[11.5px] text-(--color-on-surface-variant)">
          Bisa ditambahkan sekarang; harganya masuk ke total di bawah.
        </p>

        <div class="flex flex-col gap-2.5">
          <button
            v-for="a in addOnBisaDipilih"
            :key="a.id"
            type="button"
            class="w-full flex items-center gap-3 p-3.5 rounded-xl border-2 text-left transition-colors"
            :class="
              addOnDipilih.includes(a.id)
                ? 'bg-(--color-primary-container)/40 border-(--color-azure)'
                : 'bg-(--color-surface-0) border-(--color-outline)/40 active:bg-(--color-surface-container)/50'
            "
            :aria-pressed="addOnDipilih.includes(a.id)"
            @click="togglAddOn(a.id)"
          >
            <span
              class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center transition-colors"
              :class="
                addOnDipilih.includes(a.id)
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
              "
            >
              <span class="material-symbols-outlined text-[20px]" :data-icon="a.ikon">{{ a.ikon }}</span>
            </span>

            <span class="flex-1 min-w-0">
              <span class="block text-[13.5px] font-bold">{{ a.nama }}</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">
                Rp{{ a.harga.toLocaleString('id-ID') }} / {{ a.satuan }}
                <template v-if="a.perRuangan">· {{ ruanganLokal }} ruangan</template>
              </span>
            </span>

            <span class="shrink-0 text-right">
              <span class="block text-[12.5px] font-extrabold">
                + {{ rupiah(hargaAddOn(a.id)) }}
              </span>
              <span
                class="mt-1 inline-flex w-5 h-5 rounded-full border-2 items-center justify-center"
                :class="
                  addOnDipilih.includes(a.id)
                    ? 'border-(--color-azure) bg-(--color-azure) text-white'
                    : 'border-(--color-outline)'
                "
              >
                <Icon v-if="addOnDipilih.includes(a.id)" name="check" class="w-3 h-3" />
              </span>
            </span>
          </button>

          <!--
            Yang sudah termasuk paket ditampilkan, tapi tidak bisa dipilih:
            harganya sudah ada di dalam harga paket, dan menawarkannya lagi
            berarti menjual pekerjaan yang sama dua kali.
          -->
          <div
            v-for="a in addOnTermasuk"
            :key="a.id"
            class="flex items-center gap-3 p-3.5 rounded-xl bg-(--color-secondary-container)/40"
          >
            <span
              class="w-10 h-10 shrink-0 rounded-full bg-(--color-secondary-container) text-(--color-on-secondary-container) flex items-center justify-center"
            >
              <Icon name="check" class="w-4 h-4" />
            </span>
            <span class="flex-1 min-w-0">
              <span class="block text-[13.5px] font-bold">{{ a.nama }}</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">
                Sudah termasuk {{ paketAktif.nama }}
              </span>
            </span>
            <span class="shrink-0 text-[12.5px] font-bold text-(--color-on-secondary-container)">Gratis</span>
          </div>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  4) KODE PROMO                                          ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Kode Promo</h3>

        <!-- Terpakai: kotak biru dengan kodenya, sama seperti halaman kantor. -->
        <div
          v-if="promoTerpakai"
          class="flex items-center gap-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/30 px-3.5 py-2.5"
        >
          <Icon name="check-circle" class="w-4.5 h-4.5 text-(--color-azure) shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-[12px] font-bold truncate">{{ promoTerpakai.kode }}</p>
            <p class="text-[11px] text-(--color-on-surface-variant) truncate">{{ promoTerpakai.judul }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 text-[11.5px] font-bold text-(--color-error) active:scale-95 transition-transform"
            @click="lepasPromo"
          >
            Lepas
          </button>
        </div>

        <!-- Belum ada: kolom kode + daftar promo yang tersedia. -->
        <div v-else class="flex gap-2">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-(--color-on-surface-variant)">
              <Icon name="receipt" class="w-4 h-4" />
            </span>
            <input
              v-model="kodeInput"
              type="text"
              placeholder="Masukkan kode promo"
              class="w-full rounded-xl bg-(--color-surface-container) pl-9 pr-3 py-3 text-[13px] font-semibold uppercase border-2 border-transparent focus:border-(--color-azure) outline-none placeholder:normal-case placeholder:font-normal"
              @keyup.enter="pakaiKode"
            />
          </div>
          <button
            type="button"
            class="shrink-0 px-5 rounded-xl bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
            @click="pakaiKode"
          >
            Pakai
          </button>
        </div>

        <p
          v-if="promoPesan"
          class="mt-2 flex items-center gap-1.5 text-[11.5px] font-semibold"
          :class="promoPesan.ok ? 'text-(--color-on-secondary-container)' : 'text-(--color-error)'"
        >
          <Icon :name="promoPesan.ok ? 'check' : 'alert'" class="w-3.5 h-3.5 shrink-0" />
          {{ promoPesan.teks }}
        </p>

        <button
          type="button"
          class="mt-4 text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
          @click="keKatalogPromo"
        >
          Lihat semua promo Deep Cleaning →
        </button>

        <p class="mt-3 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Syarat promo pengguna baru diperiksa server dari riwayat pesanan, jadi
          potongannya bisa saja ditolak meski tampil di sini.
        </p>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  5) RINCIAN HARGA                                       ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Rincian Harga</h3>
        <div class="flex flex-col gap-2 text-[12.5px]">
          <div
            v-for="(b, i) in rincian.baris"
            :key="i"
            class="flex justify-between gap-3 text-(--color-on-surface-variant)"
          >
            <span>{{ b.label }}</span>
            <span class="font-bold text-(--color-on-surface) whitespace-nowrap">{{ rupiah(b.nilai) }}</span>
          </div>

          <div v-if="hasilPromo.potongan" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Promo {{ promoTerpakai?.kode }}</span>
            <span class="font-bold text-(--color-error) whitespace-nowrap">
              &minus;{{ rupiah(hasilPromo.potongan) }}
            </span>
          </div>

          <div class="flex justify-between gap-3 pt-2.5 mt-1 border-t border-(--color-outline)/12">
            <span class="text-[13px] font-extrabold">Total</span>
            <span class="text-[15px] font-extrabold">{{ rupiah(total) }}</span>
          </div>
        </div>

        <p class="mt-3 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Angka ini estimasi. Tagihan akhirnya dihitung ulang server dari pilihan
          yang sama, jadi tidak ada selisih yang datang dari layar ini.
        </p>
      </section>

      <PemilihLokasi
        :tampil="pemilihTampil"
        :alamat="alamatLokal"
        :lat="latLokal || -6.2088"
        :lng="lngLokal || 106.8456"
        judul="Set lokasi pengerjaan"
        label-cari="Cari nama gedung atau alamat"
        @tutup="pemilihTampil = false"
        @pilih="terimaLokasi"
      />
    </main>

    <!-- ╔══════════════════════════════════════════════════════════╗
         ║  FOOTER: Metode bayar + Total + Bayar                    ║
         ╚══════════════════════════════════════════════════════════╝ -->
    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div
        class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex items-center justify-between gap-4 border-t border-(--color-outline)/15"
      >
        <div class="flex flex-col min-w-0">
          <button
            type="button"
            class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
            @click="sheetOpen = true"
          >
            {{ metodeLabel }}
            <Icon name="chevron-down" class="w-3.5 h-3.5" />
          </button>
          <span class="text-[20px] font-extrabold leading-tight truncate">{{ rupiah(total) }}</span>
        </div>
        <button
          type="button"
          class="flex-1 bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-all disabled:opacity-40 disabled:active:scale-100"
          :disabled="memproses"
          @click="bayar"
        >
          {{ memproses ? 'Memproses…' : 'Bayar' }}
        </button>
      </div>

      <div v-if="galat" class="max-w-[430px] mx-auto px-4 pb-3 -mt-1">
        <p role="alert" class="text-[12px] font-semibold text-(--color-error)">{{ galat }}</p>
        <button
          v-if="nomorPesanan"
          type="button"
          class="mt-2 text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
          @click="keStatus"
        >
          Lihat pesanan saya
        </button>
      </div>
    </footer>

    <!-- Sheet metode pembayaran -->
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
          <div
            class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)"
          >
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

            <div class="overflow-y-auto flex-1 pb-6 px-5">
              <div class="flex flex-col gap-2">
                <button
                  v-for="m in METODE"
                  :key="m"
                  type="button"
                  class="w-full flex items-center gap-3 p-3 rounded-xl text-left transition-colors"
                  :class="metodeDipilih === m ? 'bg-(--color-azure)/8' : 'active:bg-(--color-surface-container)'"
                  @click="metodeDipilih = m; sheetOpen = false"
                >
                  <MetodeBayarIcon :id="m" />
                  <span class="flex-1 min-w-0 text-[14px] font-bold truncate">{{ LABEL_METODE[m] }}</span>
                  <span
                    v-if="metodeDipilih === m"
                    class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
                  >
                    <Icon name="check" class="w-3 h-3 text-white" />
                  </span>
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>
  </div>
</template>
