<script setup lang="ts">
/**
 * Pasang & Pindah AC — langkah 2: ke mana teknisi datang dan menemui siapa.
 *
 * Dipisah dari langkah 1 menurut jenis pertanyaannya: yang di sana tentang
 * PEKERJAANNYA, yang di sini tentang alamat dan orangnya. Keduanya dikirim
 * bersama dari halaman ini.
 *
 * Tetap tidak ada yang ditagih. Tombolnya "Ajukan Permintaan Penawaran", bukan
 * "Bayar" — harga pemasangan baru bisa disebut setelah foto diperiksa atau
 * lokasi disurvei.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import { useLocationStore } from '@/stores/location'
import { usePasangACStore } from '@/stores/pasangAC'
import { ajukanPasangAC, type PermintaanPasang } from '@/api/perbaikanAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import {
  JENIS_PEKERJAAN,
  MATERIAL_PASANG,
  PASANG_MULAI,
  PASANG_SAMPAI,
} from '@/lib/servis-ac/perbaikanAC'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const pasangStore = usePasangACStore()

const draft = computed(() => pasangStore.draft)

const namaJenis = computed(
  () =>
    JENIS_PEKERJAAN.find((j) => j.id === draft.value?.jenisPekerjaan)?.nama ??
    draft.value?.jenisPekerjaan ??
    '',
)

const namaMaterial = computed(() =>
  (draft.value?.material ?? [])
    .map((id) => MATERIAL_PASANG.find((m) => m.id === id)?.nama ?? id)
    .join(', '),
)

const jumlahFoto = computed(() => Object.keys(draft.value?.foto ?? {}).length)

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
   * Tanpa draf tidak ada yang bisa diajukan — dan menampilkan halaman kosong
   * hanya membuat orang menekan tombol yang pasti gagal. Terjadi kalau URL ini
   * dibuka langsung atau halaman disegarkan.
   */
  if (!pasangStore.draft) {
    router.replace({ name: 'servis-ac-pasang' })
  }
})

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<PermintaanPasang | null>(null)

const SLOT_LABEL: Record<string, string> = {
  'dinding-indoor': 'Dinding indoor',
  'lokasi-outdoor': 'Lokasi outdoor',
  'jalur-pipa': 'Jalur pipa',
  'stop-kontak': 'Stop kontak',
  pembuangan: 'Pembuangan air',
  akses: 'Akses lokasi',
}

async function ajukan() {
  const d = draft.value
  if (!d || memproses.value) return

  if (!alamat.value) {
    galat.value = 'Lokasi pemasangan belum diisi.'
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
    hasil.value = await ajukanPasangAC({
      jenis_pekerjaan: d.jenisPekerjaan,
      unit: d.unit,
      ketersediaan_unit: d.ketersediaan,
      kebutuhan: d.kebutuhan,
      merek: d.merek,
      kapasitas: d.kapasitas,
      lokasi_indoor: d.lokasiIndoor,
      lokasi_outdoor: d.lokasiOutdoor,
      material: [...d.material],
      cara_penawaran: d.caraPenawaran,
      catatan: d.catatan || undefined,
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

    // Drafnya dibuang setelah terkirim: membukanya lagi harus jadi permintaan
    // baru, bukan mengirim ulang yang sama.
    pasangStore.hapus()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

const LANGKAH_SETELAH = [
  'Foto atau lokasi diverifikasi',
  'Survei dijadwalkan kalau dibutuhkan',
  'Penawaran dikirim ke Anda',
  'Anda menyetujui sebelum pekerjaan dimulai',
]
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">
          {{ hasil ? 'Permintaan Terkirim' : 'Lokasi & Data Pemesan' }}
        </h1>
      </div>
    </header>

    <!-- ============ Setelah permintaan terkirim ============ -->
    <main v-if="hasil" class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-4">
      <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
        <span
          class="w-16 h-16 rounded-full bg-(--color-secondary-container) flex items-center justify-center mx-auto mb-4"
        >
          <Icon name="check-circle" class="w-8 h-8 text-(--color-on-secondary-container)" />
        </span>
        <h2 class="text-[19px] font-display font-extrabold mb-1.5">Permintaan terkirim</h2>
        <p class="text-[13px] leading-snug text-(--color-on-surface-variant)">
          Nomor permintaan Anda
        </p>
        <p class="mt-1 text-[17px] font-extrabold tracking-wide">{{ hasil.nomor }}</p>
      </section>

      <!--
        Kalau server menaikkan pilihannya jadi survei, alasannya dikatakan.
        Jawaban yang berubah tanpa penjelasan terbaca sebagai kesalahan sistem.
      -->
      <section
        v-if="hasil.survei_diwajibkan"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex gap-2.5"
      >
        <Icon name="alert" class="w-5 h-5 shrink-0 text-(--color-azure) mt-0.5" />
        <p class="text-[12.5px] leading-snug text-(--color-on-surface-variant)">
          Pekerjaan ini kami jadwalkan lewat
          <strong class="text-(--color-on-surface)">survei lokasi</strong>, bukan estimasi foto.
          Jarak, ketinggian, dan jalur pipanya tidak bisa dinilai dari gambar — dan penawaran yang
          salah ukur akan berubah di lapangan.
        </p>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Setelah ini</h3>
        <ol class="flex flex-col gap-3">
          <li v-for="(l, i) in LANGKAH_SETELAH" :key="l" class="flex items-start gap-3">
            <span
              class="w-6 h-6 rounded-full bg-(--color-surface-container) text-[11px] font-extrabold flex items-center justify-center shrink-0"
            >
              {{ i + 1 }}
            </span>
            <span class="text-[12.5px] leading-snug pt-0.5">{{ l }}</span>
          </li>
        </ol>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
          Sebagai gambaran awal, paket pemasangan lengkap berada di rentang
          <strong class="text-(--color-on-surface)">
            {{ rupiah(hasil.estimasi_mulai) }}–{{ rupiah(hasil.estimasi_sampai) }}</strong
          >. Angka final ditentukan setelah foto diperiksa atau lokasi disurvei, dan Anda menyetujui
          penawarannya sebelum pekerjaan dimulai.
        </p>
      </section>

      <button
        type="button"
        class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
        @click="router.push({ name: 'task-list' })"
      >
        Lihat di Tugas Saya
        <Icon name="arrow-right" class="w-4 h-4" />
      </button>
    </main>

    <!-- ============ Formulir ============ -->
    <main v-else-if="draft" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Ringkasan dari langkah sebelumnya -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
          <h2 class="text-[14px] font-display font-extrabold">Pekerjaan</h2>
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
            <span class="text-(--color-on-surface-variant)">Jenis</span>
            <span class="font-bold text-right">{{ namaJenis }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Unit</span>
            <span class="font-bold">{{ draft.unit }} unit · {{ draft.kapasitas }} PK</span>
          </div>
          <div v-if="namaMaterial" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Material</span>
            <span class="font-bold text-right leading-snug">{{ namaMaterial }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Foto terlampir</span>
            <span class="font-bold">{{ jumlahFoto }} foto</span>
          </div>
        </div>
      </section>

      <!-- Lokasi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[12px] text-(--color-on-surface-variant)">Lokasi pemasangan</p>
            <p class="mt-0.5 text-[15px] font-display font-extrabold leading-snug">
              {{ alamat || 'Belum diisi' }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 px-4 py-2 rounded-full border-[1.5px] border-(--color-azure) text-(--color-azure) text-[12.5px] font-extrabold active:scale-95 transition-transform"
            @click="lembarLokasi = true"
          >
            Ganti lokasi
          </button>
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

      <p class="px-1 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
        Nomor ini yang dihubungi tim kami untuk menjadwalkan survei atau mengirim penawaran. Boleh
        berbeda dari pemilik akun — misalnya penghuni atau pengurus rumah yang ada di lokasi.
      </p>

      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        judul-peta="Set lokasi pemasangan"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />
    </main>

    <footer
      v-if="!hasil"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">Estimasi paket lengkap</span>
          <span class="text-[15px] font-extrabold">
            {{ rupiah(PASANG_MULAI) }}–{{ rupiah(PASANG_SAMPAI) }}
          </span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="ajukan"
        >
          {{ memproses ? 'Mengirim…' : 'Ajukan Permintaan Penawaran' }}
          <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
