<script setup lang="ts">
/**
 * Form "Minta Penawaran" — BisaBersih Kantor.
 *
 * Halaman pemesanan berhenti di ESTIMASI; di sinilah datanya dilengkapi supaya
 * tim bisa menyusun penawaran resmi. Luas area dan jumlah lantai ditanyakan
 * lagi di sini justru karena halaman sebelumnya hanya memakai rentang jenis
 * kantor — angka pastinya baru relevan saat penawaran, dan tanpa itu kantor
 * 800 m² akan diperlakukan sama dengan 250 m².
 *
 * Yang dikirim: satu task berjenis 'custom' berisi ringkasan lengkap, lalu
 * fotonya menyusul lewat endpoint terpisah.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import TandaWajib from '@/components/TandaWajib.vue'
import TandaTanganPad from '@/components/TandaTanganPad.vue'
import { useLocationStore } from '@/stores/location'
import { useTaskStore } from '@/stores/task'
import { usePenawaranKantorStore } from '@/stores/penawaranKantor'
import { unggahFotoTugas } from '@/api/taskFoto'
import { kirimPermintaanKantor, unduhPdfPermintaan } from '@/api/bersihKantor'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { FREKUENSI_KANTOR, JENIS_KANTOR } from '@/lib/bersih/hargaBersihKantor'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const taskStore = useTaskStore()
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
const foto = ref<File[]>([])
const fotoInput = ref<HTMLInputElement | null>(null)

/** PNG data URL dari kotak tanda tangan. Kosong = belum ditandatangani. */
const tandaTangan = ref('')

/**
 * Isian diisi awal dari pilihan di halaman pemesanan.
 *
 * Luas area sengaja DIBIARKAN KOSONG, bukan diisi luas acuan: angka acuan itu
 * batas atas rentang, dan menuliskannya sebagai jawaban pengguna akan membuat
 * penawaran disusun dari angka yang tidak pernah mereka sebutkan.
 */
onMounted(() => {
  // Daftar kategori bisa saja belum termuat kalau halaman ini dibuka langsung.
  if (!taskStore.categories.length) void taskStore.fetchCategories()

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

const kurang = computed(() => {
  const daftar: string[] = []
  if (!namaPerusahaan.value.trim()) daftar.push('nama perusahaan')
  if (!namaPic.value.trim()) daftar.push('nama PIC')
  if (!whatsapp.value.trim()) daftar.push('nomor WhatsApp')
  if (!alamat.value.trim()) daftar.push('alamat kantor')
  if (!tandaTangan.value) daftar.push('tanda tangan')
  return daftar
})

/** Merah hanya muncul setelah tombol kirim ditekan dan isiannya masih kosong. */
function tepi(kosong: boolean) {
  return ditandai.value && kosong
    ? 'border-(--color-error)'
    : 'border-transparent focus-within:border-(--color-azure)'
}

/* ---------------- Kirim ---------------- */
const memproses = ref(false)
const galat = ref<string | null>(null)
const peringatanFoto = ref<string | null>(null)
const peringatanPdf = ref(false)

async function kirim() {
  if (memproses.value) return

  if (kurang.value.length) {
    ditandai.value = true
    galat.value = `Lengkapi dulu: ${kurang.value.join(', ')}.`
    return
  }

  memproses.value = true
  galat.value = null
  peringatanFoto.value = null

  try {
    const lokasi = locationStore.draft
    const d = draft.value

    // Dikirim ke endpoint permintaan, bukan POST /tasks umum: di sanalah nomor
    // REQ- diterbitkan dan spesifikasinya disimpan sebagai data terstruktur.
    const permintaan = await kirimPermintaanKantor({
      nama_perusahaan: namaPerusahaan.value.trim(),
      nama_pic: namaPic.value.trim(),
      telepon_pic: whatsapp.value.trim(),
      jenis_kantor: jenisId.value,
      paket: d?.paketId,
      frekuensi: frekuensiId.value,
      luas_m2: luasM2.value,
      jumlah_lantai: jumlahLantai.value,
      workstation: workstation.value ?? 0,
      ruang_meeting: ruangMeeting.value ?? 0,
      toilet: d?.toilet ?? 0,
      pantry: d?.pantry ?? 0,
      lainnya: d?.lainnya || undefined,
      add_on: d?.addOnId ?? [],
      catatan: catatanKhusus.value.trim() || undefined,
      estimasi: d?.estimasi ?? null,
      promo_kode: d?.promoKode ?? null,
      lokasi_alamat: alamat.value.trim(),
      // Koordinat dipakai apa adanya dari alamat yang dipilih sebelumnya.
      // Kalau pengguna mengetik alamat lain di sini, titiknya baru dipastikan
      // saat survei — itulah gunanya penawaran resmi.
      lokasi_lat: lokasi?.lat ?? 0,
      lokasi_lng: lokasi?.lng ?? 0,
      tanda_tangan: tandaTangan.value || undefined,
    })

    if (foto.value.length) {
      try {
        await unggahFotoTugas(permintaan.id, foto.value)
      } catch (e) {
        // Permintaannya sudah tercatat — kegagalan foto tidak boleh
        // membatalkannya, tapi juga tidak boleh disembunyikan.
        peringatanFoto.value = `Permintaan terkirim, tapi fotonya gagal diunggah: ${pesanError(e)}`
      }
    }

    penawaranStore.hapus()

    // Bukti permintaan diunduh sebelum berpindah halaman. Kegagalannya tidak
    // membatalkan apa pun — permintaannya sudah tercatat, dan PDF-nya masih
    // bisa diunduh lagi dari halaman konfirmasi.
    try {
      await unduhPdfPermintaan(permintaan.nomor)
    } catch {
      peringatanPdf.value = true
    }

    if (peringatanFoto.value) return
    router.replace({
      name: 'kantor-permintaan-terkirim',
      params: { nomor: permintaan.nomor },
      // Menandai kedatangan langsung dari form. Tanpa ini, riwayat lama yang
      // dibuka dari "Tugas Saya" ikut berbunyi "berhasil dikirim".
      query: { baru: '1' },
    })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

/**
 * Nomor sales diambil dari konfigurasi, bukan ditulis di dalam kode.
 *
 * Kalau belum diisi, tombolnya tidak ditampilkan sama sekali — tombol yang
 * membuka WhatsApp ke nomor karangan lebih buruk daripada tidak ada tombol.
 */
const NOMOR_SALES = String(import.meta.env.VITE_WA_SALES ?? '').replace(/[^0-9]/g, '')
const tautanWa = computed(() => {
  if (!NOMOR_SALES) return null
  const pesan = encodeURIComponent(
    `Halo, saya ingin bertanya soal penawaran BisaBersih Kantor untuk ${namaPerusahaan.value.trim() || 'kantor kami'}.`,
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Minta Penawaran</h1>
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
            <strong>{{ rupiah(draft.estimasi) }}</strong> per kunjungan — harga final dikunci
            setelah survei.
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
            Luas dan jumlah lantai dipakai menyusun penawaran resmi — halaman sebelumnya hanya
            memakai rentang jenis kantor untuk estimasi kasar.
          </p>
        </div>
      </section>

      <!-- Tanda tangan pengaju -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-1 flex items-center gap-2">
          <Icon name="edit" class="w-5 h-5 text-(--color-azure)" />
          Tanda Tangan <TandaWajib />
        </h2>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-3 leading-snug">
          Tanda tangan {{ namaPic || 'PIC' }} sebagai pengaju permintaan ini.
        </p>

        <TandaTanganPad v-model="tandaTangan" :ditandai="ditandai" />
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

      <!-- Permintaan tercatat, fotonya tidak: dinyatakan apa adanya. -->
      <div
        v-if="peringatanPdf"
        class="rounded-2xl bg-(--color-tertiary-container) text-(--color-on-tertiary-container) p-4 flex items-start gap-2.5"
      >
        <Icon name="info" class="w-4.5 h-4.5 shrink-0 mt-0.5" />
        <p class="text-[12.5px] leading-snug">
          Permintaan terkirim, tapi berkas PDF-nya gagal diunduh. Kamu bisa mengunduhnya lagi dari
          halaman konfirmasi.
        </p>
      </div>

      <div
        v-if="peringatanFoto"
        class="rounded-2xl bg-(--color-error-container) text-(--color-on-error-container) p-4 flex items-start gap-2.5"
      >
        <Icon name="alert" class="w-4.5 h-4.5 shrink-0 mt-0.5" />
        <p class="text-[12.5px] leading-snug">{{ peringatanFoto }}</p>
      </div>
    </main>

    <!-- Aksi -->
    <div
      class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0)/95 backdrop-blur-sm border-t border-(--color-outline)/15"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex flex-col gap-2.5">
        <button
          type="button"
          class="w-full h-13 py-3.5 rounded-full bg-(--color-azure) text-white font-bold text-[14.5px] active:scale-[0.98] transition-transform disabled:opacity-60"
          :disabled="memproses"
          @click="kirim"
        >
          {{ memproses ? 'Mengirim & menyiapkan PDF…' : 'Kirim Permintaan Penawaran' }}
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
