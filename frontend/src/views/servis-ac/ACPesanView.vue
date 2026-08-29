<script setup lang="ts">
/**
 * Pemesanan Servis AC — detail unit, paket, jadwal, dan pembayaran.
 *
 * Satu halaman, bukan dua: jumlah unit menentukan harga, dan harga menentukan
 * promo mana yang bisa dipakai. Memisahkannya berarti pengguna bolak-balik
 * hanya untuk melihat angkanya berubah.
 *
 * Seperti checkout lain di aplikasi ini, yang dikirim hanya PILIHAN. Total di
 * layar adalah estimasi; yang menagih App\Services\ACTarif di server.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useServisACStore } from '@/stores/servisAC'
import { useLocationStore } from '@/stores/location'
import { pesanServisAC } from '@/api/servisAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import {
  DISKON_RUTIN_PERSEN,
  KAPASITAS_AC,
  KONDISI_AC,
  PAKET_AC,
  RUTIN_AC,
  TERAKHIR_CUCI,
  TIPE_AC,
  hitungHargaAC,
} from '@/lib/servis-ac/hargaAC'
import { PROMO_AC, cariPromoAC, hitungPromoAC } from '@/lib/promo/promoAC'

const route = useRoute()
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
const paket = ref('standard')
const catatan = ref('')

/* Jadwal rutin: potongannya untuk kunjungan BERIKUTNYA, bukan yang ini. */
const rutinAktif = ref(false)
const rutin = ref('3-bulan')

onMounted(() => {
  const lokasi = locationStore.draft
  alamatLokal.value = lokasi?.alamat ?? ''
  latLokal.value = lokasi?.lat ?? 0
  lngLokal.value = lokasi?.lng ?? 0

  // Kode dari katalog promo kembali lewat ?promo=, lalu lewat pemeriksaan yang
  // sama dengan pilihan manual.
  const dariKatalog = String(route.query.promo ?? '')
  if (dariKatalog) pilihPromo(dariKatalog.toUpperCase())
})

/**
 * Katalog promo Servis AC. Jumlah unit ikut dikirim karena sebagian promo
 * mensyaratkannya — tanpa itu katalog akan menandai promo bisa dipakai padahal
 * belum.
 */
function keKatalogPromo() {
  router.push({
    name: 'promo-layanan',
    params: { layanan: 'ac' },
    query: {
      dari: '/tasks/new/servis-ac/pesan',
      nilai: String(rincian.value.total),
      unit: String(unit.value),
    },
  })
}

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
    return
  }

  /*
   * "Tidak ada keluhan" meniadakan keluhan lain, dan sebaliknya — memilih
   * keduanya sekaligus membuat catatan untuk teknisi saling bertentangan.
   */
  if (id === 'tidak-ada-keluhan') kondisi.value = []
  else kondisi.value = kondisi.value.filter((k) => k !== 'tidak-ada-keluhan')

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
}

/* ────────── Jadwal ────────── */
const tanggal = ref('')
const waktu = ref('')
const ditandaiJadwal = ref(false)

/* ────────── Harga ────────── */
const rincian = computed(() => hitungHargaAC(paket.value, unit.value))

/* ────────── Promo ────────── */
const promoKode = ref<string | null>(null)
const kodeInput = ref('')
const promoPesan = ref<{ ok: boolean; teks: string } | null>(null)

const promoTerpakai = computed(() => cariPromoAC(promoKode.value))
const hasilPromo = computed(() =>
  hitungPromoAC(promoTerpakai.value, rincian.value.total, unit.value),
)

const semuaPromo = computed(() =>
  PROMO_AC.map((p) => {
    const hasil = hitungPromoAC(p, rincian.value.total, unit.value)
    return {
      promo: p,
      hasil,
      alasan: hasil.alasan ?? (hasil.kurang > 0 ? `Kurang ${rupiah(hasil.kurang)} lagi` : null),
    }
  }),
)


const total = computed(() => rincian.value.total - hasilPromo.value.potongan)

function pilihPromo(kode: string) {
  // Dicari dari SEMUA promo, bukan hanya yang tampil: kode yang diketik sendiri
  // harus tetap dapat alasan penolakan yang benar.
  const d = semuaPromo.value.find((x) => x.promo.kode === kode)
  if (!d) return

  if (!d.hasil.berlaku) {
    promoPesan.value = { ok: false, teks: d.alasan ?? 'Promo ini belum bisa dipakai.' }
    return
  }

  promoKode.value = d.promo.kode
  kodeInput.value = ''
  promoPesan.value = { ok: true, teks: `${d.promo.kode} dipakai — hemat ${rupiah(d.hasil.potongan)}.` }
}

function pakaiKode() {
  const kode = kodeInput.value.trim().toUpperCase()
  if (!kode) return

  if (!cariPromoAC(kode)) {
    promoPesan.value = { ok: false, teks: 'Kode promo tidak ditemukan.' }
    return
  }

  pilihPromo(kode)
}

function lepasPromo() {
  promoKode.value = null
  promoPesan.value = null
}

/* ────────── Metode pembayaran ────────── */
const METODE: MetodeId[] = ['bca', 'mandiri', 'bni', 'gopay', 'ovo', 'qris']
const metodeDipilih = ref<MetodeId>('bca')
const sheetOpen = ref(false)
const metodeLabel = computed(() => LABEL_METODE[metodeDipilih.value] ?? metodeDipilih.value)

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)
const rincianTampil = ref(false)

async function lanjutBayar() {
  if (memproses.value) return

  if (!alamatLokal.value) {
    galat.value = 'Lokasi servis belum diisi.'
    return
  }
  if (!tanggal.value || !waktu.value) {
    galat.value = 'Jadwal kunjungan belum dipilih.'
    ditandaiJadwal.value = true
    document.getElementById('jadwal')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanServisAC({
      paket: paket.value,
      unit: unit.value,
      tipe: tipe.value,
      kapasitas: kapasitas.value,
      terakhir_cuci: terakhirCuci.value,
      kondisi: [...kondisi.value],
      rutin: rutinAktif.value ? rutin.value : null,
      catatan: catatan.value || undefined,
      tanggal: tanggal.value,
      waktu: waktu.value,
      lokasi_alamat: alamatLokal.value,
      lokasi_lat: latLokal.value,
      lokasi_lng: lngLokal.value,
      metode: metodeDipilih.value,
      promo_kode: promoTerpakai.value?.kode,
    })

    const nomor = hasil.nomor_invoice ?? String(hasil.id)
    acStore.nomorTerakhir = nomor
    acStore.hapus()

    if (hasil.rincian?.promo_ditolak) {
      // Pesanan tetap dibuat, tapi tanpa potongan — pengguna harus tahu.
      galat.value = `Promo tidak terpakai: ${hasil.rincian.promo_ditolak}`
    }

    router.replace({ name: 'servis-ac-selesai', params: { nomor } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Servis AC</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Lokasi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-[14px] font-extrabold">Lokasi Servis</h2>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="pemilihTampil = true"
          >
            Ubah
          </button>
        </div>
        <p class="mt-1 text-[13px] leading-snug text-(--color-on-surface-variant)">
          {{ alamatLokal || 'Belum diisi' }}
        </p>
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

      <!-- Jadwal -->
      <section id="jadwal" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="clock" class="w-5 h-5 text-(--color-azure)" />
          Jadwal Kunjungan
        </h2>

        <div class="grid grid-cols-2 gap-3">
          <DatePickerField v-model="tanggal" wajib :ditandai="ditandaiJadwal" />
          <TimePickerField v-model="waktu" wajib :ditandai="ditandaiJadwal" />
        </div>

        <p
          v-if="ditandaiJadwal && (!tanggal || !waktu)"
          class="text-[11.5px] font-semibold text-(--color-error) mt-2.5"
        >
          Pilih tanggal dan waktu kunjungan dulu ya.
        </p>
      </section>

      <!-- Jadwal rutin -->
      <section class="bg-(--color-primary-container)/40 rounded-2xl p-5 border border-(--color-azure)/25">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <h3 class="text-[13.5px] font-extrabold flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px]" data-icon="event_repeat">
                event_repeat
              </span>
              Jadwalkan cuci rutin
            </h3>
            <!--
              Potongannya untuk kunjungan BERIKUTNYA, bukan yang ini. Ditulis
              terang-terangan supaya tidak dikira memotong tagihan sekarang.
            -->
            <p class="mt-1 text-[11.5px] text-(--color-on-surface-variant) leading-snug">
              Diskon {{ DISKON_RUTIN_PERSEN }}% untuk kunjungan berikutnya — tagihan hari ini
              tidak berubah.
            </p>

            <div v-if="rutinAktif" class="mt-3 flex gap-2">
              <button
                v-for="r in RUTIN_AC"
                :key="r.id"
                type="button"
                class="flex-1 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
                :class="
                  rutin === r.id
                    ? 'bg-(--color-azure) border-(--color-azure) text-white'
                    : 'border-(--color-azure)/40 text-(--color-on-primary-container)'
                "
                @click="rutin = r.id"
              >
                {{ r.nama }}
              </button>
            </div>
          </div>

          <button
            type="button"
            role="switch"
            :aria-checked="rutinAktif"
            aria-label="Aktifkan jadwal rutin"
            class="relative w-11 h-6 rounded-full shrink-0 transition-colors"
            :class="rutinAktif ? 'bg-(--color-azure)' : 'bg-(--color-outline)'"
            @click="rutinAktif = !rutinAktif"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white transition-transform"
              :class="rutinAktif ? 'translate-x-5' : ''"
            />
          </button>
        </div>
      </section>

      <!-- Promo -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Kode Promo</h3>

        <div
          v-if="promoTerpakai"
          class="flex items-center gap-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/30 px-3.5 py-2.5"
        >
          <Icon name="check-circle" class="w-4.5 h-4.5 text-(--color-azure) shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-[12px] font-bold truncate">{{ promoTerpakai.kode }}</p>
            <p class="text-[11px] text-(--color-on-surface-variant) truncate">
              {{ promoTerpakai.judul }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 text-[11.5px] font-bold text-(--color-error) active:scale-95 transition-transform"
            @click="lepasPromo"
          >
            Lepas
          </button>
        </div>

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
          Lihat semua promo Servis AC →
        </button>
      </section>

      <!-- Catatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan untuk teknisi</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: AC di lantai 2, rumah pagar hijau"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
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
          <div
            v-if="hasilPromo.potongan"
            class="flex justify-between gap-3 text-[12.5px] text-(--color-error)"
          >
            <span>Promo {{ promoTerpakai?.kode }}</span>
            <span class="font-bold whitespace-nowrap">&minus;{{ rupiah(hasilPromo.potongan) }}</span>
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
            <span class="text-[20px] font-extrabold">{{ rupiah(total) }}</span>
            <Icon
              name="chevron-down"
              class="w-4 h-4 transition-transform"
              :class="rincianTampil ? 'rotate-180' : ''"
            />
          </span>
        </button>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) shrink-0 active:scale-95 transition-transform"
            @click="sheetOpen = true"
          >
            {{ metodeLabel }}
            <Icon name="chevron-down" class="w-3.5 h-3.5" />
          </button>

          <button
            type="button"
            class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="memproses"
            @click="lanjutBayar"
          >
            {{ memproses ? 'Memproses…' : 'Lanjut ke Pembayaran' }}
            <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
          </button>
        </div>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>

    <!-- Sheet metode pembayaran -->
    <Teleport to="body">
      <div v-if="sheetOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/45" @click="sheetOpen = false"></div>

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
      </div>
    </Teleport>
  </div>
</template>
