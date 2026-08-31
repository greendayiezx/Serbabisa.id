<script setup lang="ts">
/**
 * Perbaiki AC — langkah 2: ke mana teknisi datang dan menemui siapa.
 *
 * Susunannya sengaja sama persis dengan konfirmasi Pasang & Pindah AC: lokasi
 * berpeta di paling atas, ringkasan pekerjaan, lalu data pemesan. Dua alur yang
 * menanyakan hal yang sama tidak boleh menanyakannya dengan dua cara berbeda —
 * yang dipelajari pengguna di satu alur harus berlaku di alur lainnya.
 *
 * Bedanya cuma satu, dan memang harus berbeda: di sini ADA yang ditagih —
 * kunjungan diagnosisnya. Tombolnya menyebut itu, dan angkanya terbaca di
 * footer sebelum ditekan.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import KartuLokasiPeta from '@/components/KartuLokasiPeta.vue'
import { useLocationStore } from '@/stores/location'
import { usePerbaikiACStore } from '@/stores/perbaikiAC'
import { pesanPerbaikanAC } from '@/api/perbaikanAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { KELUHAN_PERBAIKAN, biayaPemeriksaanPerbaikan } from '@/lib/servis-ac/perbaikanAC'
import { MEREK_AC } from '@/lib/servis-ac/hargaFreon'
import { TIPE_AC } from '@/lib/servis-ac/hargaAC'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const perbaikiStore = usePerbaikiACStore()

const draft = computed(() => perbaikiStore.draft)

const namaKeluhan = computed(() =>
  (draft.value?.keluhan ?? [])
    .map((id) => KELUHAN_PERBAIKAN.find((k) => k.id === id)?.nama ?? id)
    .join(', '),
)

const namaUnit = computed(() => {
  const d = draft.value
  if (!d) return ''
  const tipe = TIPE_AC.find((t) => t.id === d.tipe)?.nama ?? d.tipe
  const merek = MEREK_AC.find((m) => m.id === d.merek)?.nama ?? d.merek
  return `${d.unit} unit · ${merek} ${tipe} · ${d.kapasitas} PK`
})

const jumlahFoto = computed(() => Object.keys(draft.value?.foto ?? {}).length)
const biaya = computed(() => biayaPemeriksaanPerbaikan(draft.value?.unit ?? 1))

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const jadwalTeks = computed(() => {
  const d = draft.value
  if (!d?.tanggal) return ''
  const t = new Date(`${d.tanggal}T00:00:00`)
  return `${t.getDate()} ${BULAN[t.getMonth()]} ${t.getFullYear()} · ${d.slot}`
})

/* ────────── Lokasi ────────── */
const alamat = computed(() => locationStore.draft?.alamat ?? '')
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)
const lembarLokasi = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft(l)
  lembarLokasi.value = false
}

/* ────────── Kontak ────────── */
const namaPenerima = ref('')
const telepon = ref('')
const ditandai = ref(false)

onMounted(() => {
  /*
   * Tanpa draf tidak ada yang bisa dipesan — dan menampilkan halaman kosong
   * hanya membuat orang menekan tombol yang pasti gagal. Terjadi kalau URL ini
   * dibuka langsung atau halaman disegarkan.
   */
  if (!perbaikiStore.draft) {
    router.replace({ name: 'servis-ac-perbaiki' })
    return
  }

})

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)

const SLOT_LABEL: Record<string, string> = {
  indoor: 'Unit indoor',
  outdoor: 'Unit outdoor',
  label: 'Label spesifikasi',
  'kode-error': 'Kode error',
  kebocoran: 'Titik bocor',
  lainnya: 'Lainnya',
}

async function kirim() {
  const d = draft.value
  if (!d || memproses.value) return

  if (!alamat.value) {
    galat.value = 'Lokasi servis belum diisi.'
    return
  }
  if (!namaPenerima.value.trim() || !telepon.value.trim()) {
    ditandai.value = true
    galat.value = 'Nama dan nomor telepon belum diisi.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanPerbaikanAC({
      unit: d.unit,
      keluhan: [...d.keluhan],
      menyala: d.menyala,
      mulai_terjadi: d.mulaiTerjadi,
      merek: d.merek,
      tipe: d.tipe,
      kapasitas: d.kapasitas,
      kode_error: d.kodeError || undefined,
      catatan: d.catatan || undefined,
      tanggal: d.tanggal,
      slot: d.slot,
      nama_penerima: namaPenerima.value.trim(),
      telepon_penerima: telepon.value.trim(),
      lokasi_alamat: alamat.value,
      lokasi_lat: lat.value,
      lokasi_lng: lng.value,
      foto: Object.entries(d.foto).map(([id, data]) => ({
        label: SLOT_LABEL[id] ?? id,
        data,
      })),
    })

    const nomor = hasil.nomor_invoice ?? String(hasil.id)
    // Drafnya dibuang: membuka halaman ini lagi harus jadi pesanan baru, bukan
    // mengirim ulang yang sama.
    perbaikiStore.hapus()

    /*
     * Halaman hasil pemeriksaan dipakai bersama dengan Cek & Tambah Freon:
     * keadaannya sama — menunggu teknisi, lalu menjawab rekomendasinya.
     */
    router.replace({ name: 'servis-ac-freon-hasil', params: { nomor } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Lokasi &amp; Data Pemesan</h1>
      </div>
    </header>

    <main v-if="draft" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <KartuLokasiPeta
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        :tersembunyi="lembarLokasi"
        label="Lokasi servis"
        @ubah="lembarLokasi = true"
      />

      <!-- Ringkasan dari langkah sebelumnya -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
          <h2 class="text-[14px] font-display font-extrabold">Pemeriksaan</h2>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="kembali"
          >
            Ubah
          </button>
        </div>

        <div class="flex flex-col gap-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Keluhan</span>
            <span class="font-bold text-right leading-snug">{{ namaKeluhan }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Unit</span>
            <span class="font-bold text-right leading-snug">{{ namaUnit }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Jadwal</span>
            <span class="font-bold text-right leading-snug">{{ jadwalTeks }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Foto terlampir</span>
            <span class="font-bold">{{ jumlahFoto }} foto</span>
          </div>
        </div>
      </section>

      <!--
        Isian kontaknya memakai komponen yang sama polanya dengan Detail
        Penerima di BisaAngkut — kode negara berbendera dan "Pakai detail saya".
      -->
      <KontakPenerima
        v-model:nama="namaPenerima"
        v-model:telepon="telepon"
        judul="Data Pemesan"
        :ditandai="ditandai"
      />



      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">
            Biaya pemeriksaan{{ (draft?.unit ?? 1) > 1 ? ` · ${draft?.unit} unit` : '' }}
          </span>
          <span class="text-[17px] font-extrabold">{{ rupiah(biaya) }}</span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="kirim"
        >
          {{ memproses ? 'Memproses…' : 'Pesan Pemeriksaan' }}
          <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
