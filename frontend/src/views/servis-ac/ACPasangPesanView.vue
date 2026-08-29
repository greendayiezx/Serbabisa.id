<script setup lang="ts">
/**
 * Pasang & Pindah AC — permintaan penawaran.
 *
 * Halaman ini TIDAK menagih apa pun, dan tombolnya bukan "Bayar" melainkan
 * "Ajukan Permintaan Penawaran". Harga pemasangan bergerak menurut panjang
 * pipa, jalur kabel, bracket, ketinggian, dan akses lokasi — hal-hal yang tidak
 * bisa dibaca dari formulir. Menagih di muka berarti menagih tebakan, lalu
 * menaikkannya belakangan ketika kenyataannya terlihat.
 *
 * Yang ditampilkan cuma RENTANG, dan disebut sebagai rentang.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PilihanField from '@/components/PilihanField.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import UnggahFoto, { type SlotFoto } from '@/components/servis-ac/UnggahFoto.vue'
import { useLocationStore } from '@/stores/location'
import { useAuthStore } from '@/stores/auth'
import { ajukanPasangAC, type PermintaanPasang } from '@/api/perbaikanAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { KAPASITAS_AC } from '@/lib/servis-ac/hargaAC'
import { MEREK_AC } from '@/lib/servis-ac/hargaFreon'
import {
  CARA_PENAWARAN,
  JENIS_PEKERJAAN,
  KEBUTUHAN,
  KETERSEDIAAN_UNIT,
  LOKASI_INDOOR,
  LOKASI_OUTDOOR,
  MATERIAL_PASANG,
  PASANG_MULAI,
  PASANG_SAMPAI,
  WAJIB_SURVEI,
} from '@/lib/servis-ac/perbaikanAC'

const route = useRoute()
const kembali = useKembali()
const locationStore = useLocationStore()
const authStore = useAuthStore()

/* ────────── Pilihan ────────── */
const jenisPekerjaan = ref('pasang-baru')
const unit = ref(1)
const ketersediaan = ref('sudah-ada')
const kebutuhan = ref('jasa-saja')
const merek = ref('daikin')
const kapasitas = ref('1')
const lokasiIndoor = ref('kamar-tidur')
const lokasiOutdoor = ref('dinding-luar')
const material = ref<string[]>([])
const caraPenawaran = ref('estimasi-foto')
const catatan = ref('')

function toggleMaterial(id: string) {
  const i = material.value.indexOf(id)
  if (i >= 0) material.value.splice(i, 1)
  else material.value.push(id)
}

/**
 * Sebagian pekerjaan tidak bisa dinilai dari foto — foto tidak menunjukkan
 * jarak, ketinggian, maupun jalur pipa. Server menaikkannya jadi survei; layar
 * mengatakannya lebih dulu supaya tidak terasa seperti jawabannya diubah
 * diam-diam.
 */
const wajibSurvei = computed(() => WAJIB_SURVEI.includes(jenisPekerjaan.value))

/* ────────── Lampiran ────────── */
const SLOT_FOTO: SlotFoto[] = [
  { id: 'dinding-indoor', label: 'Dinding indoor' },
  { id: 'lokasi-outdoor', label: 'Lokasi outdoor' },
  { id: 'jalur-pipa', label: 'Jalur pipa' },
  { id: 'stop-kontak', label: 'Stop kontak' },
  { id: 'pembuangan', label: 'Pembuangan air' },
  { id: 'akses', label: 'Akses lokasi' },
]
const foto = ref<Record<string, string>>({})

/* ────────── Kontak & lokasi ────────── */
const namaPenerima = ref('')
const telepon = ref('')

const alamat = computed(() => locationStore.draft?.alamat ?? '')
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)
const lembarLokasi = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft(l)
  lembarLokasi.value = false
}

onMounted(() => {
  namaPenerima.value = authStore.user?.name ?? ''
  telepon.value = authStore.user?.phone ?? ''

  // Kartu "Pindah AC" di halaman masuk sudah menyatakan jenis pekerjaannya;
  // menanyakannya lagi di sini hanya mengulang ketukan yang sudah dilakukan.
  const dari = String(route.query.jenis ?? '')
  if (JENIS_PEKERJAAN.some((j) => j.id === dari)) jenisPekerjaan.value = dari
})

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<PermintaanPasang | null>(null)

async function ajukan() {
  if (memproses.value) return

  if (!alamat.value) {
    galat.value = 'Lokasi pemasangan belum diisi.'
    return
  }
  if (!namaPenerima.value.trim() || !telepon.value.trim()) {
    galat.value = 'Nama dan nomor telepon belum diisi.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    hasil.value = await ajukanPasangAC({
      jenis_pekerjaan: jenisPekerjaan.value,
      unit: unit.value,
      ketersediaan_unit: ketersediaan.value,
      kebutuhan: kebutuhan.value,
      merek: merek.value,
      kapasitas: kapasitas.value,
      lokasi_indoor: lokasiIndoor.value,
      lokasi_outdoor: lokasiOutdoor.value,
      material: [...material.value],
      cara_penawaran: caraPenawaran.value,
      catatan: catatan.value || undefined,
      nama_penerima: namaPenerima.value.trim(),
      telepon_penerima: telepon.value.trim(),
      lokasi_alamat: alamat.value,
      lokasi_lat: lat.value,
      lokasi_lng: lng.value,
      foto: Object.entries(foto.value).map(([id, data]) => ({
        label: SLOT_FOTO.find((s) => s.id === id)?.label ?? id,
        data,
      })),
    })
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Pasang &amp; Pindah AC</h1>
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
          Pekerjaan ini kami jadwalkan lewat <strong class="text-(--color-on-surface)">survei
          lokasi</strong>, bukan estimasi foto. Jarak, ketinggian, dan jalur pipanya tidak bisa
          dinilai dari gambar — dan penawaran yang salah ukur akan berubah di lapangan.
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
    </main>

    <!-- ============ Formulir ============ -->
    <main v-else class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
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

      <!-- 1. Jenis pekerjaan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          1. Jenis Pekerjaan
        </h2>

        <PilihanField
          v-model="jenisPekerjaan"
          judul-panel="Jenis pemasangan"
          ikon="wrench"
          :opsi="JENIS_PEKERJAAN"
        />

        <div class="flex items-center justify-between gap-3 mt-5">
          <p class="text-[13.5px] font-bold">Jumlah unit</p>
          <div class="flex items-center gap-3">
            <button
              type="button"
              aria-label="Kurangi unit"
              class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="unit <= 1"
              @click="unit--"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="w-6 text-center text-[15px] font-extrabold">{{ unit }}</span>
            <button
              type="button"
              aria-label="Tambah unit"
              class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="unit >= 20"
              @click="unit++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </section>

      <!-- 2. Detail AC -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          2. Detail AC
        </h2>

        <PilihanField
          v-model="ketersediaan"
          label="Apakah unit AC sudah tersedia?"
          judul-panel="Ketersediaan unit"
          ikon="package"
          :opsi="KETERSEDIAAN_UNIT"
        />

        <div class="grid grid-cols-2 gap-4 mt-4">
          <PilihanField v-model="merek" label="Merek" ikon="bookmark" :opsi="MEREK_AC" />
          <PilihanField
            v-model="kapasitas"
            label="Kapasitas"
            judul-panel="Kapasitas AC (PK)"
            ikon="gauge"
            :opsi="KAPASITAS_AC"
          />
        </div>

        <div class="mt-4">
          <PilihanField
            v-model="kebutuhan"
            label="Kebutuhan Anda"
            judul-panel="Kebutuhan Anda"
            ikon="clipboard"
            :opsi="KEBUTUHAN"
          />
        </div>
      </section>

      <!-- 3. Lokasi pemasangan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          3. Titik Pemasangan
        </h2>
        <div class="grid grid-cols-2 gap-4">
          <PilihanField
            v-model="lokasiIndoor"
            label="Indoor dipasang di"
            judul-panel="Lokasi unit indoor"
            ikon="home"
            :opsi="LOKASI_INDOOR"
          />
          <PilihanField
            v-model="lokasiOutdoor"
            label="Outdoor dipasang di"
            judul-panel="Lokasi unit outdoor"
            ikon="business"
            :opsi="LOKASI_OUTDOOR"
          />
        </div>
      </section>

      <!-- 4. Material -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-1">
          4. Material yang Dibutuhkan
        </h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Boleh dilewati kalau belum tahu — teknisi akan memastikannya. Material dihitung terpisah
          di penawaran, misalnya pipa per meter.
        </p>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="m in MATERIAL_PASANG"
            :key="m.id"
            type="button"
            class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
            :class="
              material.includes(m.id)
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            :aria-pressed="material.includes(m.id)"
            @click="toggleMaterial(m.id)"
          >
            {{ m.nama }}
          </button>
        </div>
      </section>

      <!-- 5. Foto -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-1">
          5. Foto Lokasi
        </h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Makin lengkap fotonya, makin akurat penawarannya — dan makin kecil kemungkinan harganya
          berubah di lapangan.
        </p>
        <UnggahFoto v-model="foto" :slot="SLOT_FOTO" />
      </section>

      <!-- 6. Cara penawaran -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          6. Cara Mendapat Penawaran
        </h2>
        <PilihanField
          v-model="caraPenawaran"
          judul-panel="Bagaimana Anda ingin menerima penawaran?"
          ikon="chat"
          :opsi="CARA_PENAWARAN"
        />

        <div
          v-if="wajibSurvei"
          class="mt-3 flex gap-2 rounded-xl bg-(--color-primary-container)/40 p-3.5"
        >
          <Icon name="alert" class="w-4 h-4 shrink-0 text-(--color-azure) mt-0.5" />
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            Pekerjaan ini selalu lewat survei lokasi, apa pun yang dipilih di atas. Jarak,
            ketinggian, dan jalur pipanya tidak bisa dinilai dari foto.
          </p>
        </div>
      </section>

      <!-- 7. Kontak -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          7. Data Pemesan
        </h2>

        <label class="block mb-3">
          <span class="text-[12.5px] font-bold">Nama lengkap</span>
          <input
            v-model="namaPenerima"
            type="text"
            class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
          />
        </label>

        <label class="block">
          <span class="text-[12.5px] font-bold">Nomor telepon</span>
          <input
            v-model="telepon"
            type="tel"
            inputmode="tel"
            class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
          />
        </label>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: rumah lantai 3, outdoor rencana di atap"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>

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

    <!--
      Tombolnya "Ajukan Permintaan Penawaran", bukan "Bayar". Yang dikirim
      memang permintaan — tidak ada tagihan yang dibuat di sini.
    -->
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
