<script setup lang="ts">
/**
 * Cek & Tambah Freon — isian pemeriksaan.
 *
 * Layar ini TIDAK menjual pengisian freon. AC yang kurang dingin belum tentu
 * kekurangan freon: bisa filter kotor, kapasitor lemah, atau pipa bocor. Dan
 * karena freon berada di sistem tertutup, tekanan yang turun berarti ada yang
 * bocor — bukan sekadar "habis". Jadi yang dipesan di sini pemeriksaannya, dan
 * pengisian baru ditawarkan setelah teknisi tahu penyebabnya.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import { useFreonStore } from '@/stores/freon'
import { useLocationStore } from '@/stores/location'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import { rupiah } from '@/lib/rupiah'
import { KAPASITAS_AC, TIPE_AC } from '@/lib/servis-ac/hargaAC'
import {
  JENIS_FREON,
  KELUHAN_FREON,
  MEREK_AC,
  SLOT_FREON,
  hitungPemeriksaan,
} from '@/lib/servis-ac/hargaFreon'

const router = useRouter()
const kembali = useKembali()
const freonStore = useFreonStore()
const locationStore = useLocationStore()

/* ────────── Keluhan ────────── */
const keluhan = ref<string[]>([])
const menyala = ref(true)

function toggleKeluhan(id: string) {
  const i = keluhan.value.indexOf(id)
  if (i >= 0) {
    keluhan.value.splice(i, 1)
    return
  }

  // "Hanya ingin pemeriksaan" dan "tidak tahu" meniadakan keluhan lain —
  // memilih keduanya sekaligus membuat catatan untuk teknisi bertentangan.
  if (id === 'hanya-cek' || id === 'tidak-tahu') keluhan.value = []
  else keluhan.value = keluhan.value.filter((k) => k !== 'hanya-cek' && k !== 'tidak-tahu')

  keluhan.value.push(id)
}

/* ────────── Detail unit ────────── */
const unit = ref(1)
const tipe = ref('split')
const kapasitas = ref('1')
const merek = ref('daikin')
const jenisFreon = ref('tidak-tahu')
const catatan = ref('')

function tambahUnit() {
  if (unit.value < 10) unit.value++
}

function kurangUnit() {
  if (unit.value > 1) unit.value--
}

/* ────────── Jadwal ────────── */
const tanggal = ref('')
const slot = ref('')
const ditandai = ref(false)

onMounted(() => {
  const d = freonStore.draft
  if (!d) return

  // Kembali dari layar ringkasan: isian sebelumnya dipulihkan.
  unit.value = d.unit
  keluhan.value = [...d.keluhan]
  menyala.value = d.menyala
  tipe.value = d.tipe
  kapasitas.value = d.kapasitas
  merek.value = d.merek
  jenisFreon.value = d.jenisFreon
  catatan.value = d.catatan
  tanggal.value = d.tanggal
  slot.value = d.slot
})

const rincian = computed(() => hitungPemeriksaan(unit.value))

const alamat = computed(() => locationStore.draft?.alamat ?? '')
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)

/*
 * "Ganti lokasi" membuka lembar pilihan, bukan berpindah halaman. Berpindah
 * berarti meninggalkan form — dan isian keluhan, unit, serta jadwal ikut
 * ditinggalkan bersamanya.
 */
const lembarLokasi = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft(l)
  lembarLokasi.value = false
}

const galat = ref<string | null>(null)

function lanjut() {
  if (!keluhan.value.length) {
    galat.value = 'Pilih dulu keluhan AC-nya, minimal satu.'
    return
  }
  if (!tanggal.value || !slot.value) {
    galat.value = 'Pilih tanggal dan waktu kunjungan dulu ya.'
    ditandai.value = true
    document.getElementById('jadwal')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  galat.value = null
  freonStore.set({
    unit: unit.value,
    keluhan: [...keluhan.value],
    menyala: menyala.value,
    tipe: tipe.value,
    kapasitas: kapasitas.value,
    merek: merek.value,
    jenisFreon: jenisFreon.value,
    catatan: catatan.value,
    tanggal: tanggal.value,
    slot: slot.value,
  })

  router.push({ name: 'servis-ac-freon-ringkasan' })
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Cek &amp; Tambah Freon</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!--
        Dikatakan di muka, bukan di halaman tagihan: biaya pemeriksaan kembali
        ke pelanggan kalau pekerjaannya jadi dilanjutkan.
      -->
      <div class="flex gap-2 px-1">
        <Icon name="alert" class="w-4 h-4 shrink-0 text-(--color-azure) mt-0.5" />
        <p class="text-[12px] leading-snug text-(--color-on-surface-variant)">
          Biaya pemeriksaan dipotong dari total servis kalau Anda melanjutkan pengerjaan pada
          kunjungan yang sama.
        </p>
      </div>

      <!--
        Lokasi di paling atas, sebelum keluhan: ia yang menentukan teknisi mana
        yang bisa datang, dan alamat yang salah membuat seluruh isian di
        bawahnya sia-sia.
      -->
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

      <!-- 1. Keluhan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-3">
          1. Keluhan AC
        </h2>

        <p class="text-[13px] font-bold mb-2.5">Apa yang terjadi pada AC Anda?</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="k in KELUHAN_FREON"
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
          <p class="text-[13px] font-bold mb-2.5">Apakah AC masih bisa menyala?</p>
          <div class="flex gap-3">
            <button
              v-for="p in [true, false]"
              :key="String(p)"
              type="button"
              class="flex-1 py-3 rounded-xl border text-[13px] font-bold transition-colors"
              :class="
                menyala === p
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/50 text-(--color-on-surface)'
              "
              @click="menyala = p"
            >
              {{ p ? 'Ya' : 'Tidak' }}
            </button>
          </div>
        </div>
      </section>

      <!-- 2. Detail unit -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          2. Detail Unit
        </h2>

        <div class="flex items-center justify-between gap-3 pb-5 mb-5 border-b border-(--color-outline)/15">
          <span class="text-[13px] font-bold">Jumlah unit</span>
          <div class="flex items-center gap-3 bg-(--color-surface-container) rounded-full p-1">
            <button
              type="button"
              aria-label="Kurangi unit"
              class="w-9 h-9 rounded-full bg-(--color-surface-0) flex items-center justify-center active:scale-95 transition-transform"
              @click="kurangUnit"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="w-5 text-center text-[16px] font-extrabold">{{ unit }}</span>
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

        <div class="grid grid-cols-2 gap-4">
          <label class="block">
            <span class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5">
              Tipe AC
            </span>
            <select
              v-model="tipe"
              class="w-full rounded-xl bg-(--color-surface-container) px-3 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            >
              <option v-for="t in TIPE_AC" :key="t.id" :value="t.id">{{ t.nama }}</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5">
              Kapasitas
            </span>
            <select
              v-model="kapasitas"
              class="w-full rounded-xl bg-(--color-surface-container) px-3 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            >
              <option v-for="k in KAPASITAS_AC" :key="k.id" :value="k.id">{{ k.nama }}</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5">
              Merek AC
            </span>
            <select
              v-model="merek"
              class="w-full rounded-xl bg-(--color-surface-container) px-3 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            >
              <option v-for="m in MEREK_AC" :key="m.id" :value="m.id">{{ m.nama }}</option>
            </select>
          </label>

          <label class="block">
            <span class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5">
              Jenis freon
            </span>
            <select
              v-model="jenisFreon"
              class="w-full rounded-xl bg-(--color-surface-container) px-3 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            >
              <option v-for="f in JENIS_FREON" :key="f.id" :value="f.id">{{ f.nama }}</option>
            </select>
          </label>
        </div>

        <!--
          "Tidak tahu" bukan jawaban malas: menebak jenis freon menghasilkan data
          yang salah, dan teknisi tetap membaca label unitnya di lokasi.
        -->
        <p class="mt-3 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Tidak tahu jenis freonnya? Pilih "Tidak tahu" — teknisi akan membaca label
          unitnya di lokasi.
        </p>

        <label class="block mt-4">
          <span class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5">
            Catatan untuk teknisi
          </span>
          <textarea
            v-model="catatan"
            rows="3"
            placeholder="Misal: unit outdoor di balkon lantai 2"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
          />
        </label>
      </section>

      <!-- 3. Jadwal -->
      <section id="jadwal" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          3. Pilih Jadwal
        </h2>

        <DatePickerField v-model="tanggal" wajib :ditandai="ditandai" />

        <p class="mt-4 mb-2 text-[11.5px] font-medium text-(--color-on-surface-variant)">
          Rentang waktu kunjungan
        </p>
        <!--
          Rentang dua jam, bukan jam persis: teknisi berpindah antar lokasi dan
          menjanjikan menit yang tepat hanya membuat janji yang sering meleset.
        -->
        <div class="grid grid-cols-2 gap-2.5">
          <button
            v-for="s in SLOT_FREON"
            :key="s"
            type="button"
            class="py-2.5 rounded-lg border text-[12.5px] font-bold transition-colors"
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
          <span class="text-[17px] font-extrabold">{{ rupiah(rincian.total) }}</span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="lanjut"
        >
          Konfirmasi Jadwal
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
