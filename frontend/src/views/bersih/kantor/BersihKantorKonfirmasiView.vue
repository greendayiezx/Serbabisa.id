<script setup lang="ts">
/**
 * Ringkasan Pemesanan — BisaBersih Kantor.
 *
 * Halaman konfirmasi terakhir sebelum pesanan dibuat. Desainnya mengikuti pola
 * "Ringkasan Pemesanan": pengguna meninjau semua pilihan dan bisa mengedit
 * bagian-bagian tertentu secara inline (jenis kantor, luas, workstation,
 * frekuensi, jadwal, alamat) tanpa kembali ke halaman sebelumnya.
 *
 * Foto paket ditampilkan di bagian atas bersama nama paket, lalu tiap bagian
 * editable diberi tombol "Ubah" yang membuka editor inline.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { usePenawaranKantorStore } from '@/stores/penawaranKantor'
import { usePromoBersihKantorStore } from '@/stores/promoBersihKantor'
import { pesanKantorLangsung } from '@/api/bersihKantor'
import { unggahFotoTugas } from '@/api/taskFoto'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import bersihKantorImg from '@/assets/BersihKantor.png'
import {
  ADD_ON_KANTOR,
  JENIS_KANTOR,
  PAKET_KANTOR,
  FREKUENSI_KANTOR,
  hitungHargaKantor,
  type PaketKantorId,
  type JenisKantorId,
} from '@/lib/bersih/hargaBersihKantor'
import {
  hitungPromoKantor,
  semuaVoucherKantor,
  type VoucherKantor,
} from '@/lib/promo/promoBersihKantor'

const router = useRouter()
const kembali = useKembali()
const penawaranStore = usePenawaranKantorStore()
const promoStore = usePromoBersihKantorStore()

const draft = computed(() => penawaranStore.draft)
const pesanan = computed(() => penawaranStore.pesanan)

/**
 * Tanpa isian dari langkah sebelumnya, halaman ini tidak punya apa pun untuk
 * dikonfirmasi — kembalikan ke awal alih-alih menampilkan angka kosong.
 */
onMounted(() => {
  if (!draft.value || !pesanan.value) {
    router.replace({ name: 'task-bersih-kantor' })
  }
})

/* ────────── Editable state (lokal, diubah langsung di halaman ini) ────────── */
const editJenis = ref(false)
const editLuas = ref(false)
const editWorkstation = ref(false)
const editFrekuensi = ref(false)
const editJadwal = ref(false)
/**
 * Pemilih lokasi layar penuh.
 *
 * Menggantikan kotak teks: alamat kantor yang diketik bebas tidak punya
 * koordinat, sedangkan kru butuh titiknya untuk sampai ke sana. `mulaiDariCari`
 * membedakan dua pintu masuk — "Ubah" membuka petanya, sementara "Edit" di
 * dalam peta langsung ke pencarian.
 */
const pemilihTampil = ref(false)
const pemilihMulaiDariCari = ref(false)

function bukaPeta() {
  pemilihMulaiDariCari.value = false
  pemilihTampil.value = true
}

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  alamatLokal.value = l.alamat
  latLokal.value = l.lat
  lngLokal.value = l.lng
  pemilihTampil.value = false

  // Draf sesi ikut diperbarui: halaman lain yang membacanya tidak boleh
  // tertinggal memakai alamat sebelum diedit.
  if (pesanan.value) {
    penawaranStore.setPesanan({ ...pesanan.value, alamat: l.alamat, lat: l.lat, lng: l.lng })
  }
}
const editCatatan = ref(false)

/** Jenis kantor yang bisa diubah. */
const jenisIdLokal = ref<JenisKantorId>('sedang')
/** Luas bisa diketik ulang. */
const luasLokal = ref<number>(150)
/** Workstation bisa diubah. */
const workstationLokal = ref<number>(0)
/** Frekuensi bisa diubah. */
const frekuensiIdLokal = ref<string>('2x-minggu')
/** Jadwal bisa diubah. */
const tanggalLokal = ref<string>('')
const waktuLokal = ref<string>('')
/** Alamat bisa diubah. */
const alamatLokal = ref<string>('')

/**
 * Koordinat titik pengerjaan.
 *
 * Disimpan terpisah dari teks alamatnya: pengguna bisa saja menggeser pin ke
 * gedung sebelah tanpa mengubah nama jalannya, dan yang dipakai kru adalah
 * titiknya — bukan kalimatnya.
 */
const latLokal = ref<number>(0)
const lngLokal = ref<number>(0)
/** Catatan bisa diubah. */
const catatanLokal = ref<string>('')

/** Inisialisasi dari store saat mount. */
onMounted(() => {
  if (pesanan.value) {
    jenisIdLokal.value = (pesanan.value.jenisId as JenisKantorId) || 'sedang'
    luasLokal.value = pesanan.value.luasM2 ?? jenisAktif.value.luasAcuan
    workstationLokal.value = pesanan.value.workstation ?? 0
    frekuensiIdLokal.value = pesanan.value.frekuensiId || '2x-minggu'
    tanggalLokal.value = pesanan.value.tanggal || ''
    waktuLokal.value = pesanan.value.waktu || ''
    alamatLokal.value = pesanan.value.alamat || ''
    latLokal.value = pesanan.value.lat ?? 0
    lngLokal.value = pesanan.value.lng ?? 0
    catatanLokal.value = pesanan.value.catatan || ''
  }
})

/* ────────── Turunan pilihan (computed dari state lokal) ────────── */
const paketAktif = computed(
  () => PAKET_KANTOR.find((p) => p.id === draft.value?.paketId) ?? PAKET_KANTOR[0],
)
const jenisAktif = computed(
  () => JENIS_KANTOR.find((j) => j.id === jenisIdLokal.value) ?? JENIS_KANTOR[1],
)
const frekuensiAktif = computed(
  () => FREKUENSI_KANTOR.find((f) => f.id === frekuensiIdLokal.value) ?? FREKUENSI_KANTOR[0],
)
const addOnTerpilih = computed(() =>
  ADD_ON_KANTOR.filter((a) => draft.value?.addOnId.includes(a.id)),
)

/** Luas untuk hitungan: pakai isian lokal, atau luas acuan jenis kantornya. */
const luasHitung = computed(() => {
  const l = luasLokal.value
  return l && l > 0 ? l : jenisAktif.value.luasAcuan
})

const rincian = computed(() =>
  hitungHargaKantor({
    paketId: (draft.value?.paketId ?? 'professional') as PaketKantorId,
    luasM2: luasHitung.value,
    jumlahLantai: pesanan.value?.jumlahLantai ?? 1,
    workstation: workstationLokal.value,
    ruangMeeting: pesanan.value?.ruangMeeting ?? 0,
    toilet: draft.value?.toilet ?? 2,
    pantry: draft.value?.pantry ?? 1,
    addOnDipilih: draft.value?.addOnId ?? [],
    frekuensiId: frekuensiIdLokal.value,
  }),
)

/* ────────── Promo ────────── */
const promoTerpakai = computed(() => promoStore.voucher())
const hasilPromo = computed(() =>
  hitungPromoKantor(promoTerpakai.value, rincian.value.totalPerKunjungan),
)
const total = computed(() => rincian.value.totalPerKunjungan - hasilPromo.value.potongan)

const kodeInput = ref('')
const promoPesan = ref<{ ok: boolean; teks: string } | null>(null)

function pakaiKode() {
  const kode = kodeInput.value.trim().toUpperCase()
  if (!kode) return
  const v: VoucherKantor | undefined = semuaVoucherKantor().find((x) => x.kode === kode)
  if (!v) {
    promoPesan.value = { ok: false, teks: 'Kode promo tidak ditemukan.' }
    return
  }
  const hasil = hitungPromoKantor(v, rincian.value.totalPerKunjungan)
  if (hasil.kurang > 0) {
    promoPesan.value = {
      ok: false,
      teks: `Kurang ${rupiah(hasil.kurang)} lagi untuk memakai ${v.kode}.`,
    }
    return
  }
  promoStore.dipilih = v.id
  promoPesan.value = { ok: true, teks: `Promo ${v.kode} berhasil digunakan!` }
  kodeInput.value = ''
}

function lepasPromo() {
  promoStore.lepas()
  promoPesan.value = null
}

/**
 * Promo yang BISA dipakai pada tagihan ini saja.
 *
 * Halaman ini langkah terakhir sebelum bayar; menampilkan promo yang belum
 * terjangkau di sini hanya menggoda pengguna menaikkan pesanan saat ia justru
 * hendak menyelesaikannya. Katalog lengkapnya tetap ada di halaman promo, dan
 * di sana yang belum memenuhi syarat ditandai apa adanya.
 *
 * Diurutkan dari potongan terbesar — itu yang paling ingin dilihat lebih dulu.
 */
const daftarPromo = computed(() => {
  const nilai = rincian.value.totalPerKunjungan

  return semuaVoucherKantor()
    .map((voucher) => ({ voucher, hasil: hitungPromoKantor(voucher, nilai) }))
    .filter(({ hasil }) => hasil.kurang === 0)
    .sort((a, b) => b.hasil.potongan - a.hasil.potongan)
})

const adaPromoBisaDipakai = computed(() => daftarPromo.value.length > 0)

function manfaat(v: VoucherKantor, potongan: number): string {
  if (potongan > 0) return 'Hemat ' + rupiah(potongan)
  if (v.bonus) return v.bonus
  return v.ringkas
}

function pilihPromo(v: VoucherKantor, kurang: number) {
  if (kurang > 0) return

  promoStore.dipilih = v.id
  promoPesan.value = { ok: true, teks: `Promo ${v.kode} berhasil digunakan!` }
}

watch(
  () => hasilPromo.value.kurang,
  (kurang) => {
    if (kurang > 0 && promoTerpakai.value) {
      const kode = promoTerpakai.value.kode
      promoStore.lepas()
      promoPesan.value = {
        ok: false,
        teks: `${kode} dilepas karena tagihannya turun di bawah syarat minimum.`,
      }
    }
  },
)

/* ────────── Metode pembayaran ────────── */
const METODE: MetodeId[] = ['bca', 'mandiri', 'bni', 'gopay', 'ovo', 'qris']
const metodeDipilih = ref<MetodeId>('bca')
const sheetOpen = ref(false)

const metodeLabel = computed(() => LABEL_METODE[metodeDipilih.value] ?? metodeDipilih.value)

/* ────────── Bayar ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)
const peringatanFoto = ref<string | null>(null)

async function bayar() {
  if (memproses.value || !draft.value || !pesanan.value) return

  memproses.value = true
  galat.value = null
  peringatanFoto.value = null

  try {
    const p = pesanan.value
    const d = draft.value
    const hasil = await pesanKantorLangsung({
      jenis_kantor: jenisIdLokal.value,
      paket: d.paketId,
      frekuensi: frekuensiIdLokal.value,
      workstation: workstationLokal.value,
      ruang_meeting: p.ruangMeeting,
      toilet: d.toilet,
      pantry: d.pantry,
      add_on: [...d.addOnId],
      lainnya: d.lainnya || undefined,
      catatan: catatanLokal.value || undefined,
      tanggal: tanggalLokal.value,
      waktu: waktuLokal.value,
      lokasi_alamat: alamatLokal.value,
      // Titik hasil pemilih peta, bukan titik awal draf: kalau pengguna
      // menggeser pin ke gedung sebelah, ke sanalah kru harus datang.
      lokasi_lat: latLokal.value || p.lat,
      lokasi_lng: lngLokal.value || p.lng,
      metode: metodeDipilih.value,
      // Hanya kodenya. Potongannya dihitung ulang server — angka di layar ini
      // tidak pernah jadi dasar tagihan.
      promo_kode: promoTerpakai.value?.kode,
    })

    if (p.foto.length) {
      try {
        await unggahFotoTugas(hasil.id, p.foto)
      } catch (e) {
        peringatanFoto.value = `Pesanan terkirim, tapi fotonya gagal diunggah: ${pesanError(e)}`
      }
    }

    if (peringatanFoto.value) return

    /*
     * Ke layar tunggu dulu, bukan langsung ke Detail Order.
     *
     * Detail Order berbicara seolah pekerjaan sudah punya penanggung jawab —
     * kartu cleaner, tombol telepon, pelacakan. Halaman itu baru pantas
     * ditampilkan setelah benar-benar ada yang menerima, dan layar tunggu yang
     * memantau serta memindahkannya.
     */
    router.replace({
      name: 'task-bersih-kantor-mencari',
      params: { nomor: hasil.nomor_invoice || String(hasil.id) },
    })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

/* ────────── Helpers ────────── */
function formatTanggalWaktu(t: string, w: string): string {
  if (!t) return 'Belum dipilih'
  return `${t}${w ? ' · ' + w : ''}`
}
</script>

<template>
  <div v-if="draft && pesanan" class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-36">
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
        <div class="px-5 pt-4 pb-1">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-[14px] font-extrabold">Alamat Pengerjaan</h2>
            <button
              type="button"
              class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
              @click="bukaPeta"
            >
              Ubah
            </button>
          </div>
        </div>

        <div class="px-5 pb-4">
          <p class="text-[13px] leading-snug text-(--color-on-surface-variant) mt-1">
            {{ alamatLokal || 'Belum diisi' }}
          </p>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  2) PAKET & DETAIL — dengan foto                       ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl overflow-hidden">
        <!-- Header paket + foto -->
        <div class="flex items-center gap-3.5 px-5 pt-4 pb-3 border-b border-(--color-outline)/8">
          <div class="flex-1 min-w-0">
            <h2 class="text-[16px] font-extrabold truncate">{{ paketAktif.nama }}</h2>
            <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">Bersih Kantor</p>
          </div>
          <img
            :src="bersihKantorImg"
            alt="Paket Bersih Kantor"
            class="w-16 h-16 rounded-xl object-cover shrink-0 shadow-sm"
          />
        </div>

        <!-- Baris detail: jenis kantor -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-(--color-outline)/6">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Jenis Kantor</p>
            <p class="text-[13.5px] font-bold mt-0.5">{{ jenisAktif.nama }}</p>
          </div>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            @click="editJenis = !editJenis"
          >
            Ubah
          </button>
        </div>

        <!-- Inline editor: Jenis -->
        <div v-if="editJenis" class="px-5 py-3 bg-(--color-surface-container)/40 border-b border-(--color-outline)/6">
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="j in JENIS_KANTOR"
              :key="j.id"
              type="button"
              class="rounded-xl py-2.5 px-2 text-center text-[11.5px] font-bold border-2 transition-all active:scale-[0.97]"
              :class="
                jenisIdLokal === j.id
                  ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
                  : 'border-(--color-outline)/20 text-(--color-on-surface-variant) bg-(--color-surface-0)'
              "
              @click="jenisIdLokal = j.id; luasLokal = j.luasAcuan; editJenis = false"
            >
              {{ j.nama }}
            </button>
          </div>
        </div>

        <!-- Baris detail: Luas -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-(--color-outline)/6">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Luas</p>
            <p class="text-[13.5px] font-bold mt-0.5">{{ luasHitung }} m²</p>
          </div>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            @click="editLuas = !editLuas"
          >
            Ubah
          </button>
        </div>

        <!-- Inline editor: Luas -->
        <div v-if="editLuas" class="px-5 py-3 bg-(--color-surface-container)/40 border-b border-(--color-outline)/6">
          <div class="flex items-center gap-2">
            <input
              v-model.number="luasLokal"
              type="number"
              min="1"
              inputmode="numeric"
              placeholder="150"
              class="flex-1 rounded-xl bg-(--color-surface-0) px-3.5 py-2.5 text-[13px] font-bold border-2 border-(--color-azure)/30 focus:border-(--color-azure) outline-none"
            />
            <span class="text-[12px] text-(--color-on-surface-variant) font-semibold shrink-0">m²</span>
            <button
              type="button"
              class="px-4 py-2 rounded-full bg-(--color-azure) text-white text-[12px] font-bold active:scale-95 transition-transform shrink-0"
              @click="editLuas = false"
            >
              OK
            </button>
          </div>
        </div>

        <!-- Baris detail: Workstation -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-(--color-outline)/6">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Workstation</p>
            <p class="text-[13.5px] font-bold mt-0.5">{{ workstationLokal }} workstation</p>
          </div>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            @click="editWorkstation = !editWorkstation"
          >
            Ubah
          </button>
        </div>

        <!-- Inline editor: Workstation -->
        <div v-if="editWorkstation" class="px-5 py-3 bg-(--color-surface-container)/40 border-b border-(--color-outline)/6">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="w-9 h-9 rounded-full bg-(--color-surface-0) text-(--color-on-surface-variant) flex items-center justify-center active:scale-90 transition-transform border border-(--color-outline)/20 disabled:opacity-35"
              :disabled="workstationLokal <= 0"
              @click="workstationLokal = Math.max(0, workstationLokal - 1)"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="text-[16px] font-extrabold w-8 text-center">{{ workstationLokal }}</span>
            <button
              type="button"
              class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform"
              @click="workstationLokal++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
            <button
              type="button"
              class="ml-auto px-4 py-2 rounded-full bg-(--color-azure) text-white text-[12px] font-bold active:scale-95 transition-transform shrink-0"
              @click="editWorkstation = false"
            >
              OK
            </button>
          </div>
        </div>

        <!-- Baris detail: Frekuensi -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-(--color-outline)/6">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Frekuensi</p>
            <p class="text-[13.5px] font-bold mt-0.5">{{ frekuensiAktif.label }}</p>
          </div>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            @click="editFrekuensi = !editFrekuensi"
          >
            Ubah
          </button>
        </div>

        <!-- Inline editor: Frekuensi -->
        <div v-if="editFrekuensi" class="px-5 py-3 bg-(--color-surface-container)/40 border-b border-(--color-outline)/6">
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="f in FREKUENSI_KANTOR"
              :key="f.id"
              type="button"
              class="rounded-full py-2 px-2 text-center text-[11.5px] font-bold border-2 transition-all active:scale-[0.97]"
              :class="
                frekuensiIdLokal === f.id
                  ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
                  : 'border-(--color-outline)/20 text-(--color-on-surface-variant) bg-(--color-surface-0)'
              "
              @click="frekuensiIdLokal = f.id; editFrekuensi = false"
            >
              {{ f.label }}
            </button>
          </div>
        </div>

        <!-- Baris detail: Jadwal -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-(--color-outline)/6">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Jadwal Pengerjaan</p>
            <p class="text-[13.5px] font-bold mt-0.5">
              {{ formatTanggalWaktu(tanggalLokal, waktuLokal) }}
            </p>
          </div>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            @click="editJadwal = !editJadwal"
          >
            Ubah
          </button>
        </div>

        <!-- Inline editor: Jadwal -->
        <div v-if="editJadwal" class="px-5 py-3 bg-(--color-surface-container)/40 border-b border-(--color-outline)/6">
          <div class="grid grid-cols-2 gap-3">
            <DatePickerField v-model="tanggalLokal" wajib :ditandai="false" />
            <TimePickerField v-model="waktuLokal" wajib :ditandai="false" />
          </div>
          <button
            type="button"
            class="mt-2.5 px-4 py-2 rounded-full bg-(--color-azure) text-white text-[12px] font-bold active:scale-95 transition-transform"
            @click="editJadwal = false"
          >
            Simpan
          </button>
        </div>

        <!-- Catatan -->
        <div class="px-5 py-3">
          <button
            type="button"
            class="text-[12.5px] text-(--color-on-surface-variant) active:scale-95 transition-transform flex items-center gap-1.5"
            @click="editCatatan = !editCatatan"
          >
            <Icon name="receipt" class="w-3.5 h-3.5" />
            {{ catatanLokal ? 'Lihat / Ubah Catatan' : 'Tambahkan Catatan' }}
          </button>
          <div v-if="editCatatan" class="mt-2">
            <textarea
              v-model="catatanLokal"
              rows="2"
              maxlength="500"
              placeholder="Catatan untuk tim cleaner..."
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 text-[13px] border border-(--color-outline)/30 focus:border-(--color-azure) outline-none resize-none"
            />
          </div>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  3) LAYANAN TAMBAHAN                                   ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section v-if="addOnTerpilih.length" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Layanan Tambahan</h3>
        <div class="flex flex-col gap-2.5">
          <div
            v-for="a in addOnTerpilih"
            :key="a.id"
            class="flex items-center justify-between gap-3"
          >
            <span class="flex items-center gap-2 min-w-0">
              <Icon name="check-circle" class="w-4 h-4 text-(--color-azure) shrink-0" />
              <span class="text-[12.5px] truncate">{{ a.nama }}</span>
            </span>
            <span class="text-[12.5px] font-bold shrink-0">{{ rupiah(a.harga) }}</span>
          </div>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  4) KODE PROMO                                         ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Kode Promo</h3>

        <div v-if="promoTerpakai" class="flex items-center gap-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/30 px-3.5 py-2.5">
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

        <div v-if="!promoTerpakai" class="mt-4 flex flex-col gap-2">
          <p
            v-if="!adaPromoBisaDipakai"
            class="text-[11.5px] text-(--color-on-surface-variant) leading-snug"
          >
            Belum ada promo yang bisa dipakai untuk tagihan ini. Lihat katalog lengkapnya
            untuk tahu syarat minimumnya.
          </p>

          <button
            v-for="{ voucher: v, hasil } in daftarPromo"
            :key="v.id"
            type="button"
            class="flex items-center gap-3 rounded-xl border border-(--color-azure)/30 bg-(--color-azure)/5 px-3.5 py-2.5 text-left transition-colors active:scale-[0.99]"
            @click="pilihPromo(v, hasil.kurang)"
          >
            <Icon name="receipt" class="w-4 h-4 shrink-0 text-(--color-azure)" />
            <span class="flex-1 min-w-0">
              <span class="block text-[12px] font-bold truncate">{{ v.kode }}</span>
              <span class="block text-[11px] text-(--color-on-surface-variant) truncate">
                {{ v.judul }}
              </span>
            </span>
            <span class="shrink-0 text-[11.5px] font-bold text-(--color-azure)">
              {{ manfaat(v, hasil.potongan) }}
            </span>
          </button>
        </div>

        <button
          type="button"
          class="mt-3 text-[12px] font-bold text-(--color-azure) active:scale-95 transition-transform"
          @click="
            router.push({
              name: 'task-bersih-kantor-promo',
              query: {
                dari: '/tasks/new/bersih/kantor/konfirmasi',
                nilai: String(rincian.totalPerKunjungan),
              },
            })
          "
        >
          Lihat semua promo kantor →
        </button>
      </section>



      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  6) RINCIAN HARGA                                      ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Rincian Harga</h3>
        <div class="flex flex-col gap-2 text-[12.5px] text-(--color-on-surface-variant)">
          <div class="flex justify-between gap-3">
            <span>Harga layanan</span>
            <span>{{ rupiah(rincian.layanan) }}</span>
          </div>
          <div v-if="hasilPromo.potongan" class="flex justify-between gap-3">
            <span>Diskon</span>
            <span class="text-(--color-error) font-bold">&minus;{{ rupiah(hasilPromo.potongan) }}</span>
          </div>
          <div v-if="rincian.addOn" class="flex justify-between gap-3">
            <span>Biaya Tambahan</span>
            <span>{{ rupiah(rincian.addOn) }}</span>
          </div>
          <div v-if="rincian.diskonFrekuensi" class="flex justify-between gap-3 text-(--color-on-secondary-container)">
            <span>Diskon langganan {{ frekuensiAktif.label.toLowerCase() }}</span>
            <span>&minus;{{ rupiah(rincian.diskonFrekuensi) }}</span>
          </div>
        </div>
      </section>




      <div
        v-if="peringatanFoto"
        class="rounded-2xl bg-(--color-error-container) text-(--color-on-error-container) p-4 flex items-start gap-2.5"
      >
        <Icon name="alert" class="w-4.5 h-4.5 shrink-0 mt-0.5" />
        <p class="text-[12.5px] leading-snug">{{ peringatanFoto }}</p>
      </div>
      <PemilihLokasi
        :tampil="pemilihTampil"
        :alamat="alamatLokal"
        :lat="latLokal || -6.2088"
        :lng="lngLokal || 106.8456"
        :mulai-dari-cari="pemilihMulaiDariCari"
        judul="Set lokasi pengerjaan"
        label-cari="Cari lokasi tujuan"
        @tutup="pemilihTampil = false"
        @pilih="terimaLokasi"
      />
    </main>

    <!-- ╔══════════════════════════════════════════════════════════╗
         ║  FOOTER: Metode bayar + Total + Bayar                   ║
         ╚══════════════════════════════════════════════════════════╝ -->
    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex items-center justify-between gap-4 border-t border-(--color-outline)/15">
        <div class="flex flex-col min-w-0">
          <button
            type="button"
            class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
            @click="sheetOpen = true"
          >
            {{ metodeLabel }}
            <Icon name="chevron-down" class="w-3.5 h-3.5" />
          </button>
          <span class="text-[20px] font-extrabold leading-tight truncate">
            {{ rupiah(total) }}
          </span>
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
      <p
        v-if="galat"
        role="alert"
        class="max-w-[430px] mx-auto px-4 pb-3 -mt-1 text-[12px] font-semibold text-(--color-error)"
      >
        {{ galat }}
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
