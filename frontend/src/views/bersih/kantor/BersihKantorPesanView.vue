<script setup lang="ts">
/**
 * Halaman "Pesan Sekarang" — BisaBersih Kantor.
 *
 * Struktur halaman mengikuti pola BersihKantorPenawaranView: form satu layar
 * dengan ringkasan pilihan, data perusahaan, detail lokasi, jadwal, dan foto.
 *
 * Perbedaan utama: di sini pesanan LANGSUNG dibuat lewat `pesanKantorLangsung`,
 * sementara di Penawaran pesanan dibuat sebagai task 'custom' yang masih perlu
 * survei dan penawaran resmi.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import TandaWajib from '@/components/TandaWajib.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useLocationStore } from '@/stores/location'
import { usePenawaranKantorStore } from '@/stores/penawaranKantor'
import { rupiah } from '@/lib/rupiah'
import { FREKUENSI_KANTOR, JENIS_KANTOR } from '@/lib/bersih/hargaBersihKantor'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const penawaranStore = usePenawaranKantorStore()

const draft = computed(() => penawaranStore.draft)

/* ---------------- Isian ---------------- */
const namaPerusahaan = ref('')
const namaPic = ref('')
const whatsapp = ref('')
const alamat = ref('')
const jenisId = ref<string>('sedang')
const luasM2 = ref<number | null>(null)
const jumlahLantai = ref<number | null>(1)
const ruangMeeting = ref<number | null>(0)
const workstation = ref<number | null>(0)
const frekuensiId = ref('2x-minggu')
const catatanKhusus = ref('')
const tanggal = ref('')
const waktu = ref('')
const foto = ref<File[]>([])
const fotoInput = ref<HTMLInputElement | null>(null)

/** Isian diisi awal dari pilihan di halaman pemesanan. */
onMounted(() => {
  const d = draft.value
  if (d) {
    jenisId.value = d.jenisId
    frekuensiId.value = d.frekuensiId
    ruangMeeting.value = d.ruangMeeting
    workstation.value = d.workstation
    catatanKhusus.value = d.catatan
  }
  alamat.value = locationStore.draft?.alamat ?? ''
})



/* ---------------- Foto ---------------- */
const MAKS_FOTO = 6

function pilihFoto() {
  fotoInput.value?.click()
}

function onFotoChange(e: Event) {
  const input = e.target as HTMLInputElement
  const dipilih = input.files ? Array.from(input.files) : []
  foto.value = [...foto.value, ...dipilih].slice(0, MAKS_FOTO)
  input.value = ''
}

function hapusFoto(i: number) {
  foto.value.splice(i, 1)
}

/* ---------------- Validasi ---------------- */
const ditandai = ref(false)
const ditandaiJadwal = ref(false)

const kurang = computed(() => {
  const daftar: string[] = []
  if (!namaPerusahaan.value.trim()) daftar.push('nama perusahaan')
  if (!namaPic.value.trim()) daftar.push('nama PIC')
  if (!whatsapp.value.trim()) daftar.push('nomor WhatsApp')
  if (!alamat.value.trim()) daftar.push('alamat kantor')
  if (!tanggal.value) daftar.push('tanggal kunjungan')
  if (!waktu.value) daftar.push('jam kunjungan')
  return daftar
})

/** Merah hanya muncul setelah tombol kirim ditekan dan isiannya masih kosong. */
function tepi(kosong: boolean) {
  return ditandai.value && kosong
    ? 'border-(--color-error)'
    : 'border-transparent focus-within:border-(--color-azure)'
}

/* ---------------- Lanjut ke konfirmasi ---------------- */
const galat = ref<string | null>(null)

/**
 * Halaman ini tidak lagi membuat pesanan langsung. Isian dititipkan ke store,
 * lalu pengguna meninjau ringkasan, promo, metode bayar, dan rincian harga di
 * halaman Konfirmasi & Bayar sebelum pesanan benar-benar dibuat.
 */
function lanjut() {
  if (kurang.value.length) {
    ditandai.value = true
    ditandaiJadwal.value = true
    galat.value = `Lengkapi dulu: ${kurang.value.join(', ')}.`
    return
  }

  const lokasi = locationStore.draft
  if (!lokasi?.alamat) {
    galat.value = 'Pilih alamat kantor dulu ya.'
    return
  }

  galat.value = null
  penawaranStore.setPesanan({
    namaPerusahaan: namaPerusahaan.value.trim(),
    namaPic: namaPic.value.trim(),
    whatsapp: whatsapp.value.trim(),
    alamat: alamat.value.trim(),
    lat: lokasi.lat,
    lng: lokasi.lng,
    jenisId: jenisId.value,
    luasM2: luasM2.value,
    jumlahLantai: jumlahLantai.value,
    ruangMeeting: ruangMeeting.value ?? 0,
    workstation: workstation.value ?? 0,
    frekuensiId: frekuensiId.value,
    catatan: catatanKhusus.value.trim(),
    tanggal: tanggal.value,
    waktu: waktu.value,
    foto: [...foto.value],
  })

  router.push({ name: 'task-bersih-kantor-konfirmasi' })
}

/**
 * Nomor sales diambil dari konfigurasi.
 */
const NOMOR_SALES = String(import.meta.env.VITE_WA_SALES ?? '').replace(/[^0-9]/g, '')
const tautanWa = computed(() => {
  if (!NOMOR_SALES) return null
  const pesan = encodeURIComponent(
    `Halo, saya ingin memesan BisaBersih Kantor untuk ${namaPerusahaan.value.trim() || 'kantor kami'}.`,
  )
  return `https://wa.me/${NOMOR_SALES}?text=${pesan}`
})
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-44">
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Pesan Sekarang</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <!-- Ringkasan pilihan dari halaman sebelumnya -->
      <section
        v-if="draft"
        class="rounded-2xl bg-(--color-primary-container) text-(--color-on-primary-container) p-4"
      >
        <div class="flex items-start gap-3">
          <Icon name="info" class="w-5 h-5 shrink-0 mt-0.5" />
          <p class="text-[12.5px] leading-snug">
            Berdasarkan pilihanmu: <strong>{{ draft.paketNama }}</strong> ·
            {{ draft.jenisNama }} · {{ draft.frekuensiLabel }}. Estimasi aplikasi
            <strong>{{ rupiah(draft.estimasi) }}</strong> per kunjungan — tagihan dihitung ulang
            server saat pesanan dibuat.
          </p>
        </div>
      </section>

      <!-- Data perusahaan & PIC -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="business" class="w-5 h-5 text-(--color-azure)" />
          Data Perusahaan &amp; PIC
        </h2>

        <div class="flex flex-col gap-3.5">
          <label class="block">
            <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
              Nama Perusahaan <TandaWajib />
            </span>
            <input
              v-model="namaPerusahaan"
              type="text"
              placeholder="PT Maju Mundur"
              aria-required="true"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 outline-none"
              :class="tepi(!namaPerusahaan.trim())"
            />
          </label>

          <label class="block">
            <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
              Nama PIC <TandaWajib />
            </span>
            <input
              v-model="namaPic"
              type="text"
              placeholder="Budi Santoso"
              aria-required="true"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 outline-none"
              :class="tepi(!namaPic.trim())"
            />
          </label>

          <label class="block">
            <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
              Nomor WhatsApp <TandaWajib />
            </span>
            <input
              v-model="whatsapp"
              type="tel"
              inputmode="tel"
              placeholder="0812..."
              aria-required="true"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 outline-none"
              :class="tepi(!whatsapp.trim())"
            />
          </label>
        </div>
      </section>

      <!-- Detail lokasi & area -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="pin" class="w-5 h-5 text-(--color-azure)" />
          Detail Lokasi &amp; Area
        </h2>

        <div class="flex flex-col gap-3.5">
          <label class="block">
            <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
              Alamat Kantor <TandaWajib />
            </span>
            <textarea
              v-model="alamat"
              rows="3"
              placeholder="Masukkan alamat lengkap"
              aria-required="true"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 outline-none resize-none"
              :class="tepi(!alamat.trim())"
            />
          </label>

          <label class="block">
            <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
              Jenis Kantor
            </span>
            <select
              v-model="jenisId"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            >
              <option v-for="j in JENIS_KANTOR" :key="j.id" :value="j.id">
                {{ j.nama }} — {{ j.rentang }}
              </option>
            </select>
          </label>

          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
                Luas Area (m²)
              </span>
              <input
                v-model.number="luasM2"
                type="number"
                min="0"
                inputmode="numeric"
                placeholder="0"
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              />
            </label>
            <label class="block">
              <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
                Jumlah Lantai
              </span>
              <input
                v-model.number="jumlahLantai"
                type="number"
                min="1"
                inputmode="numeric"
                placeholder="1"
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              />
            </label>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
                Ruang Meeting
              </span>
              <input
                v-model.number="ruangMeeting"
                type="number"
                min="0"
                inputmode="numeric"
                placeholder="0"
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              />
            </label>
            <label class="block">
              <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
                Workstation
              </span>
              <input
                v-model.number="workstation"
                type="number"
                min="0"
                inputmode="numeric"
                placeholder="0"
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              />
            </label>
          </div>

          <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug">
            Luas dan jumlah lantai membantu menghitung tagihan — angka finalnya dihitung ulang server.
          </p>
        </div>
      </section>

      <!-- Jadwal kunjungan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
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
          Pilih tanggal dan jam kunjungannya dulu ya.
        </p>

        <p class="text-[11.5px] text-(--color-on-surface-variant) mt-2.5 leading-snug">
          Kru akan datang sesuai jadwal yang dipilih. Pastikan ada penanggung jawab di lokasi.
        </p>
      </section>

      <!-- Kebutuhan layanan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="sparkle" class="w-5 h-5 text-(--color-azure)" />
          Kebutuhan Layanan
        </h2>

        <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-2">
          Frekuensi Layanan
        </span>
        <div class="grid grid-cols-2 gap-2.5">
          <button
            v-for="f in FREKUENSI_KANTOR"
            :key="f.id"
            type="button"
            class="rounded-full py-2.5 px-2 text-center text-[12.5px] font-bold border-2 transition-colors active:scale-[0.98]"
            :class="
              frekuensiId === f.id
                ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
                : 'border-(--color-outline)/25 text-(--color-on-surface-variant)'
            "
            :aria-pressed="frekuensiId === f.id"
            @click="frekuensiId = f.id"
          >
            {{ f.label }}
          </button>
        </div>

        <label class="block mt-4">
          <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
            Catatan Khusus
          </span>
          <textarea
            v-model="catatanKhusus"
            rows="3"
            placeholder="Kebutuhan spesifik lainnya..."
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[14px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
          />
        </label>
      </section>

      <!-- Foto area -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="camera" class="w-5 h-5 text-(--color-azure)" />
          Foto Area <span class="text-[12px] font-semibold text-(--color-on-surface-variant)">(opsional)</span>
        </h2>

        <input
          ref="fotoInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="hidden"
          @change="onFotoChange"
        />

        <button
          v-if="foto.length < MAKS_FOTO"
          type="button"
          class="w-full rounded-2xl border-2 border-dashed border-(--color-outline)/50 py-7 flex flex-col items-center justify-center gap-1.5 active:scale-[0.99] transition-transform"
          @click="pilihFoto"
        >
          <Icon name="image" class="w-7 h-7 text-(--color-on-surface-variant)" />
          <span class="text-[12.5px] text-(--color-on-surface-variant)">
            Ketuk untuk pilih foto area kerja
          </span>
        </button>

        <ul v-if="foto.length" class="flex flex-col gap-2 mt-3">
          <li
            v-for="(f, i) in foto"
            :key="`${f.name}-${i}`"
            class="flex items-center gap-2.5 rounded-xl bg-(--color-surface-container) px-3 py-2.5"
          >
            <Icon name="image" class="w-4 h-4 shrink-0 text-(--color-on-surface-variant)" />
            <span class="flex-1 min-w-0 text-[12.5px] truncate">{{ f.name }}</span>
            <button
              type="button"
              :aria-label="`Hapus ${f.name}`"
              class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 active:scale-90 transition-transform"
              @click="hapusFoto(i)"
            >
              <Icon name="trash" class="w-4 h-4 text-(--color-on-surface-variant)" />
            </button>
          </li>
        </ul>

        <p class="text-[11.5px] text-(--color-on-surface-variant) mt-2.5 leading-snug">
          Maksimal {{ MAKS_FOTO }} foto, masing-masing 5 MB. JPG, PNG, atau WebP.
        </p>
      </section>

      <p v-if="galat" class="flex items-start gap-2 text-[12.5px] font-semibold text-(--color-error)">
        <Icon name="alert" class="w-4 h-4 shrink-0 mt-0.5" />{{ galat }}
      </p>
    </main>

    <!-- Aksi -->
    <div
      class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0)/95 backdrop-blur-sm border-t border-(--color-outline)/15"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex flex-col gap-2.5">
        <button
          type="button"
          class="w-full h-13 py-3.5 rounded-full bg-(--color-azure) text-white font-bold text-[14.5px] active:scale-[0.98] transition-transform flex items-center justify-center gap-2"
          @click="lanjut"
        >
          Konfirmasi &amp; Pesan
          <Icon name="arrow-right" class="w-4.5 h-4.5" />
        </button>

        <a
          v-if="tautanWa"
          :href="tautanWa"
          target="_blank"
          rel="noopener noreferrer"
          class="w-full py-3 rounded-full border border-(--color-outline)/40 text-(--color-on-surface) font-bold text-[13.5px] flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
        >
          <Icon name="chat" class="w-4.5 h-4.5 text-(--color-lime-dark, --color-secondary)" />
          Chat Sales via WhatsApp
        </a>
      </div>
    </div>
  </div>
</template>
