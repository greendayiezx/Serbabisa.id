<script setup lang="ts">
/**
 * Perbaiki AC — permintaan diagnosis.
 *
 * Yang ditagih di muka HANYA kunjungan teknisi. Halaman ini tidak pernah
 * menyebut harga perbaikan, karena harga itu belum ada: kerusakan yang sama
 * bisa berarti kapasitor lemah atau kompresor mati, dan selisihnya berlipat.
 * Menyebut angka sekarang berarti menebak, lalu menagih tebakan itu.
 *
 * Setelah teknisi memeriksa, rekomendasinya muncul di halaman hasil — layar
 * yang sama dengan Cek & Tambah Freon, karena persetujuannya bekerja persis
 * sama.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PilihanField from '@/components/PilihanField.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import UnggahFoto, { type SlotFoto } from '@/components/servis-ac/UnggahFoto.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import { useLocationStore } from '@/stores/location'
import { useAuthStore } from '@/stores/auth'
import { pesanPerbaikanAC } from '@/api/perbaikanAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { KAPASITAS_AC, TIPE_AC } from '@/lib/servis-ac/hargaAC'
import { MEREK_AC, SLOT_FREON } from '@/lib/servis-ac/hargaFreon'
import {
  KELUHAN_PERBAIKAN,
  MULAI_TERJADI,
  biayaPemeriksaanPerbaikan,
} from '@/lib/servis-ac/perbaikanAC'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const authStore = useAuthStore()

/* ────────── Keluhan ────────── */
const keluhan = ref<string[]>([])
const menyala = ref(true)
const mulaiTerjadi = ref('1-7-hari')

function toggleKeluhan(id: string) {
  const i = keluhan.value.indexOf(id)
  if (i >= 0) {
    keluhan.value.splice(i, 1)
    return
  }
  keluhan.value.push(id)
}

/* ────────── Detail unit ────────── */
const unit = ref(1)
const merek = ref('daikin')
const tipe = ref('split')
const kapasitas = ref('1')
const kodeError = ref('')
const catatan = ref('')

const munculKodeError = computed(() => keluhan.value.includes('kode-error'))

/* ────────── Lampiran ────────── */
const SLOT_FOTO: SlotFoto[] = [
  { id: 'indoor', label: 'Unit indoor' },
  { id: 'outdoor', label: 'Unit outdoor' },
  { id: 'label', label: 'Label spesifikasi' },
  { id: 'kode-error', label: 'Kode error' },
  { id: 'kebocoran', label: 'Titik bocor' },
  { id: 'lainnya', label: 'Lainnya' },
]
const foto = ref<Record<string, string>>({})

/* ────────── Jadwal & kontak ────────── */
const tanggal = ref('')
const slot = ref('')
const namaPenerima = ref('')
const telepon = ref('')

/* ────────── Lokasi ────────── */
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
})

const biaya = computed(() => biayaPemeriksaanPerbaikan(unit.value))

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)

async function kirim() {
  if (memproses.value) return

  if (!keluhan.value.length) {
    galat.value = 'Pilih dulu masalah AC-nya, minimal satu.'
    return
  }
  if (!alamat.value) {
    galat.value = 'Lokasi servis belum diisi.'
    return
  }
  if (!namaPenerima.value.trim() || !telepon.value.trim()) {
    galat.value = 'Nama dan nomor telepon belum diisi.'
    return
  }
  if (!tanggal.value || !slot.value) {
    galat.value = 'Jadwal kunjungan belum dipilih.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanPerbaikanAC({
      unit: unit.value,
      keluhan: [...keluhan.value],
      menyala: menyala.value,
      mulai_terjadi: mulaiTerjadi.value,
      merek: merek.value,
      tipe: tipe.value,
      kapasitas: kapasitas.value,
      kode_error: munculKodeError.value ? kodeError.value || undefined : undefined,
      catatan: catatan.value || undefined,
      tanggal: tanggal.value,
      slot: slot.value,
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

    const nomor = hasil.nomor_invoice ?? String(hasil.id)
    // Halaman hasil pemeriksaan dipakai bersama dengan Cek & Tambah Freon:
    // keadaannya sama — menunggu teknisi, lalu menjawab rekomendasinya.
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Perbaiki AC</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Lokasi di paling atas: ia yang menentukan teknisi mana yang datang. -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[12px] text-(--color-on-surface-variant)">Lokasi servis</p>
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

      <div class="flex gap-2 px-1 -mt-0.5">
        <Icon name="alert" class="w-4 h-4 shrink-0 text-(--color-azure) mt-0.5" />
        <p class="text-[12px] leading-snug text-(--color-on-surface-variant)">
          Biaya pemeriksaan dipotong dari total servis kalau Anda melanjutkan perbaikan pada
          kunjungan yang sama.
        </p>
      </div>

      <!-- 1. Masalah -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-3">
          1. Masalah AC
        </h2>
        <h3 class="text-[14px] font-bold mb-1">Apa masalah AC Anda?</h3>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3">
          Pilih satu atau lebih yang paling sesuai.
        </p>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="k in KELUHAN_PERBAIKAN"
            :key="k.id"
            type="button"
            class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
            :class="
              keluhan.includes(k.id)
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            :aria-pressed="keluhan.includes(k.id)"
            @click="toggleKeluhan(k.id)"
          >
            {{ k.nama }}
          </button>
        </div>

        <div class="mt-5 pt-5 border-t border-(--color-outline)/15">
          <h3 class="text-[13px] font-bold mb-2.5">Apakah AC masih bisa menyala?</h3>
          <div class="flex gap-2">
            <button
              v-for="p in [
                { v: true, n: 'Ya' },
                { v: false, n: 'Tidak' },
              ]"
              :key="String(p.v)"
              type="button"
              class="flex-1 py-3 rounded-xl border text-[13px] font-bold transition-colors"
              :class="
                menyala === p.v
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/50 text-(--color-on-surface)'
              "
              @click="menyala = p.v"
            >
              {{ p.n }}
            </button>
          </div>
        </div>

        <div class="mt-5">
          <PilihanField
            v-model="mulaiTerjadi"
            label="Kapan masalah mulai terjadi?"
            judul-panel="Kapan masalah mulai terjadi?"
            ikon="clock"
            :opsi="MULAI_TERJADI"
          />
        </div>
      </section>

      <!-- 2. Detail unit -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          2. Detail Unit
        </h2>

        <div class="flex items-center justify-between gap-3 pb-5 mb-5 border-b border-(--color-outline)/15">
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
              :disabled="unit >= 10"
              @click="unit++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <PilihanField v-model="merek" label="Merek AC" ikon="bookmark" :opsi="MEREK_AC" />
          <PilihanField v-model="tipe" label="Tipe AC" ikon="grid" :opsi="TIPE_AC" />
          <PilihanField
            v-model="kapasitas"
            label="Kapasitas"
            judul-panel="Kapasitas AC (PK)"
            ikon="gauge"
            :opsi="KAPASITAS_AC"
          />
        </div>

        <label v-if="munculKodeError" class="block mt-4">
          <span class="text-[12.5px] font-bold">Kode error yang muncul</span>
          <input
            v-model="kodeError"
            type="text"
            placeholder="Misal: E5, F1, H6"
            class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] uppercase border-2 border-transparent focus:border-(--color-azure) outline-none placeholder:normal-case"
          />
        </label>
      </section>

      <!-- 3. Lampiran -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-1">
          3. Foto (opsional)
        </h2>
        <!--
          Opsional, dan memang tidak dipaksa: pemeriksaan tetap jalan tanpa
          foto. Gunanya membantu teknisi membawa alat dan suku cadang yang
          benar sejak kunjungan pertama.
        -->
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Membantu teknisi membawa alat yang tepat sejak kunjungan pertama.
        </p>
        <UnggahFoto v-model="foto" :slot="SLOT_FOTO" />
      </section>

      <!-- 4. Jadwal -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          4. Jadwal Kunjungan
        </h2>
        <DatePickerField v-model="tanggal" wajib />

        <p class="mt-4 mb-2 text-[12.5px] font-bold">Rentang waktu kunjungan</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="s in SLOT_FREON"
            :key="s"
            type="button"
            class="py-3 rounded-xl border text-[12.5px] font-bold transition-colors"
            :class="
              slot === s
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50 text-(--color-on-surface)'
            "
            @click="slot = s"
          >
            {{ s }}
          </button>
        </div>
      </section>

      <!-- 5. Data pemesan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-1">
          5. Data Pemesan
        </h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Yang akan ditemui teknisi di lokasi. Boleh berbeda dari pemilik akun.
        </p>

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

      <!-- Catatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan untuk teknisi</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: unit outdoor di balkon lantai 2"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>

      <!-- Apa yang termasuk -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Pemeriksaan teknisi termasuk</h3>
        <ul class="flex flex-col gap-2">
          <li
            v-for="t in [
              'Pemeriksaan awal keluhan',
              'Pengecekan kondisi unit indoor & outdoor',
              'Estimasi perbaikan',
              'Rincian material kalau dibutuhkan',
            ]"
            :key="t"
            class="flex items-start gap-2 text-[12.5px] text-(--color-on-surface-variant)"
          >
            <Icon name="check-circle" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-azure)" />
            {{ t }}
          </li>
        </ul>
      </section>

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
            Biaya pemeriksaan{{ unit > 1 ? ` · ${unit} unit` : '' }}
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
