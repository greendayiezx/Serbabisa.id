<script setup lang="ts">
/**
 * BisaTukang — pemesanan.
 *
 * Dua langkah saja: apa masalahnya, lalu kapan dan ke mana tukangnya datang.
 * Langkah ketiga di penanda kemajuan — estimasi RAB — sengaja BUKAN bagian
 * formulir ini: angkanya baru ada setelah tukang melihat sendiri kerusakannya.
 * Ditampilkan justru supaya itu terbaca, bukan supaya diisi.
 *
 * Katalog keahlian dan biaya kunjungannya DATANG DARI SERVER. Sebelumnya
 * keduanya tetapan di store, yang berarti tarif yang dilihat pemesan tidak bisa
 * diperbarui tanpa merilis ulang aplikasi.
 *
 * Sesudah dipesan, layar ini selesai tugasnya: yang melacak pekerjaannya
 * TukangStatusView, dan isinya digerakkan tahap dari server — bukan pewaktu di
 * browser.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import IkonKategoriTukang from '@/components/tukang/IkonKategoriTukang.vue'
import { useKembali } from '@/composables/useKembali'
import { useAuthStore } from '@/stores/auth'
import { useLocationStore } from '@/stores/location'
import { useTukangStore, MODEL_KERJA, PENYEDIAAN_MATERIAL, TIPE_PROPERTI } from '@/stores/tukang'
import { katalogTukang, checkoutTukang, permintaanTukang, type KatalogTukang } from '@/api/tukang'
import { pesanError } from '@/api/belanja'

const router = useRouter()
const kembali = useKembali()
const auth = useAuthStore()
const locationStore = useLocationStore()
const tukangStore = useTukangStore()

const addressLabel = computed(
  () => locationStore.draft?.alamat ?? 'Jl. Sudirman No. 123, Jakarta Pusat',
)

/**
 * Tiga langkah pemesanan yang ditunjukkan di kepala.
 *
 * Langkah ketiga belum bisa dibuka dari formulir — estimasinya baru ada setelah
 * tukang memeriksa. Ditampilkan supaya terbaca bahwa yang diisi sekarang bukan
 * harga, melainkan bahan untuk menghitungnya.
 */
const LANGKAH_PESAN = ['Detail masalah', 'Jadwal & lokasi', 'Estimasi RAB']

const fase = ref(1)

/* ────────── Katalog ────────── */
const katalog = ref<KatalogTukang | null>(null)
const memuat = ref(true)
const galatKatalog = ref<string | null>(null)

const kategori = computed(() => katalog.value?.kategori ?? [])
const kategoriTerpilih = computed(
  () => kategori.value.find((k) => k.id === tukangStore.selectedCategoryId) ?? kategori.value[0] ?? null,
)

/*
 * Biaya kunjungan yang ditampilkan diambil dari katalog server, bukan dihitung
 * ulang di sini. Dua tempat yang menghitung angka yang sama pada akhirnya akan
 * berbeda, dan yang salah selalu yang dilihat pemesan.
 */
const biayaKunjungan = computed(() => kategoriTerpilih.value?.kunjungan ?? 0)

function formatRupiah(n: number) {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}

async function muatKatalog() {
  try {
    const k = await katalogTukang()
    katalog.value = k

    // Kategori dan jenis masalah yang tersimpan dari kunjungan sebelumnya bisa
    // saja sudah tidak ada di katalog; dikembalikan ke pilihan pertama daripada
    // mengirim pilihan yang pasti ditolak server.
    const ada = k.kategori.find((x) => x.id === tukangStore.selectedCategoryId) ?? k.kategori[0]
    if (ada) {
      tukangStore.selectedCategoryId = ada.id
      if (!ada.sub.includes(tukangStore.selectedSubCategory)) {
        tukangStore.selectedSubCategory = ada.sub[0]
      }
    }
    if (!k.lokasi_masalah.includes(tukangStore.lokasiMasalah)) {
      tukangStore.lokasiMasalah = k.lokasi_masalah[0]
    }
  } catch (e) {
    galatKatalog.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

function pilihKategori(id: string) {
  tukangStore.selectedCategoryId = id
  const k = kategori.value.find((x) => x.id === id)
  if (k) tukangStore.selectedSubCategory = k.sub[0]
}

/* ────────── Kontak di lokasi ────────── */
const namaPenerima = ref('')
const telepon = ref('')

/* ────────── Kelengkapan ────────── */
const boleh1 = computed(
  () =>
    !!kategoriTerpilih.value &&
    !!tukangStore.selectedSubCategory &&
    tukangStore.deskripsiMasalah.trim().length >= 8,
)

const boleh2 = computed(() => namaPenerima.value.trim().length >= 2 && telepon.value.trim().length >= 8)

/* ────────── Foto ────────── */
const MAKS_FOTO = 5
const KOTAK_FOTO = 4
const fileInput = ref<HTMLInputElement | null>(null)

/** Foto disimpan sebagai data URL: itu yang diterima endpoint-nya. */
const foto = ref<{ label: string; data: string }[]>([])

const bolehTambahFoto = computed(() => foto.value.length < MAKS_FOTO)
const kotakKosong = computed(() =>
  Math.max(0, KOTAK_FOTO - foto.value.length - (bolehTambahFoto.value ? 1 : 0)),
)

function triggerUpload() {
  fileInput.value?.click()
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const berkas = target.files?.[0]
  target.value = ''
  if (!berkas || !bolehTambahFoto.value) return

  const pembaca = new FileReader()
  pembaca.onload = () => {
    if (typeof pembaca.result === 'string') {
      foto.value.push({ label: `Area rusak ${foto.value.length + 1}`, data: pembaca.result })
    }
  }
  pembaca.readAsDataURL(berkas)
}

/* ────────── Kirim ────────── */
const mengirim = ref(false)
const galatKirim = ref<string | null>(null)

function isian() {
  const l = locationStore.draft
  return {
    kategori: tukangStore.selectedCategoryId,
    sub_kategori: tukangStore.selectedSubCategory,
    tipe_properti: tukangStore.tipeProperti,
    penyediaan_material: tukangStore.penyediaanMaterial,
    lokasi_masalah: tukangStore.lokasiMasalah,
    deskripsi_masalah: tukangStore.deskripsiMasalah.trim(),
    nama_penerima: namaPenerima.value.trim(),
    telepon_penerima: telepon.value.trim(),
    lokasi_alamat: l?.alamat ?? addressLabel.value,
    lokasi_lat: l?.lat ?? -6.2088,
    lokasi_lng: l?.lng ?? 106.8456,
    foto: foto.value.length ? foto.value : undefined,
  }
}

/** Tanggal jadwal; "secepatnya" tidak punya tanggal, dan itu memang sengaja. */
function tanggalJadwal(): string | null {
  const t = tukangStore.jadwalTipe
  if (t === 'hari_ini') return new Date().toISOString().split('T')[0]
  if (t === 'besok') {
    const b = new Date()
    b.setDate(b.getDate() + 1)
    return b.toISOString().split('T')[0]
  }
  return null
}

async function pesan() {
  if (mengirim.value || !boleh2.value) return

  mengirim.value = true
  galatKirim.value = null
  try {
    const dasar = isian()
    const hasil =
      tukangStore.modelKerja === 'borongan'
        ? await permintaanTukang({ ...dasar, jadwal_jam: tukangStore.jadwalJam })
        : await checkoutTukang({
            ...dasar,
            jadwal_tipe: tukangStore.jadwalTipe,
            jadwal_tanggal: tanggalJadwal(),
            jadwal_jam: tukangStore.jadwalTipe === 'secepatnya' ? null : tukangStore.jadwalJam,
          })

    router.replace({ name: 'task-tukang-status', params: { nomor: hasil.nomor } })
  } catch (e) {
    galatKirim.value = pesanError(e)
  } finally {
    mengirim.value = false
  }
}

function lanjut() {
  fase.value = 2
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function mundur() {
  if (fase.value > 1) {
    fase.value -= 1
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }
  kembali()
}

onMounted(() => {
  namaPenerima.value = auth.user?.name ?? ''
  telepon.value = auth.user?.phone ?? ''
  muatKatalog()
})
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!-- ── Kepala ── -->
    <header
      class="sticky top-0 z-50 bg-(--color-surface-0)/90 backdrop-blur-md border-b border-(--color-outline)/12"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3">
        <div class="flex items-center gap-3">
          <button
            type="button"
            aria-label="Kembali"
            class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="mundur"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <div class="flex-1 min-w-0">
            <h1 class="text-[15px] font-display font-extrabold leading-tight text-(--color-azure)">
              Pesan BisaTukang
            </h1>
            <p class="text-[11px] text-(--color-on-surface-variant) truncate">{{ addressLabel }}</p>
          </div>
        </div>
      </div>

      <div class="bg-(--color-surface-container-high) px-4 py-3">
        <div class="max-w-[430px] mx-auto relative">
          <!-- Garis penghubung, di belakang bulatan langkah -->
          <div class="absolute top-3.5 left-8 right-8 h-0.5 -translate-y-1/2 bg-(--color-outline)/25"></div>
          <div
            class="absolute top-3.5 left-8 h-0.5 -translate-y-1/2 bg-(--color-azure) transition-[width]"
            :style="{ width: `calc((100% - 4rem) * ${(fase - 1) / (LANGKAH_PESAN.length - 1)})` }"
          ></div>

          <div class="relative flex items-start justify-between">
            <div v-for="(l, i) in LANGKAH_PESAN" :key="l" class="flex flex-col items-center gap-1 w-16">
              <span
                class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-extrabold transition-colors"
                :class="
                  i + 1 === fase
                    ? 'bg-(--color-azure) text-white ring-4 ring-(--color-azure)/20'
                    : i + 1 < fase
                      ? 'bg-(--color-azure) text-white'
                      : 'bg-(--color-surface-0) text-(--color-on-surface-variant)'
                "
              >
                {{ i + 1 }}
              </span>
              <span
                class="text-[10px] leading-tight text-center font-semibold"
                :class="i + 1 === fase ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
              >
                {{ l }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4">
      <p
        v-if="galatKatalog"
        role="alert"
        class="rounded-2xl bg-(--color-error-container) text-(--color-on-error-container) px-4 py-3 text-[12.5px] font-semibold"
      >
        {{ galatKatalog }}
      </p>

      <!-- Katalog belum sampai: rangka, bukan daftar kosong yang terbaca
           seolah tidak ada layanannya. -->
      <div v-else-if="memuat" class="flex flex-col gap-4 animate-pulse">
        <div class="h-12 rounded-2xl bg-(--color-surface-0)"></div>
        <div class="grid grid-cols-3 gap-2.5">
          <div v-for="n in 9" :key="n" class="h-24 rounded-2xl bg-(--color-surface-0)"></div>
        </div>
        <div class="h-28 rounded-2xl bg-(--color-surface-0)"></div>
      </div>

      <!-- ═══════════ LANGKAH 1 — MASALAH ═══════════ -->
      <section v-else-if="fase === 1" class="flex flex-col gap-5">
        <!-- ── Model penanganan ── -->
        <div>
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-[14px] font-display font-extrabold">Model penanganan</h3>
            <span
              class="rounded-full px-2.5 py-1 text-[10.5px] font-extrabold flex items-center gap-1 shrink-0"
              :class="
                tukangStore.modelKerja === 'harian'
                  ? 'bg-(--color-lime) text-(--color-on-surface)'
                  : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
              "
            >
              <Icon name="sparkle" class="w-3.5 h-3.5" />
              {{ tukangStore.modelKerja === 'harian' ? 'Gercep' : 'Survei gratis' }}
            </span>
          </div>

          <div class="mt-2.5 grid grid-cols-2 gap-1 p-1 rounded-2xl bg-(--color-surface-container-high)">
            <button
              v-for="m in MODEL_KERJA"
              :key="m.id"
              type="button"
              class="py-2.5 px-2 rounded-xl text-center transition-colors"
              :class="
                tukangStore.modelKerja === m.id
                  ? 'bg-(--color-azure) text-white shadow-sm'
                  : 'text-(--color-on-surface-variant)'
              "
              :aria-pressed="tukangStore.modelKerja === m.id"
              @click="tukangStore.modelKerja = m.id"
            >
              <span class="block text-[13px] font-extrabold leading-tight">{{ m.nama }}</span>
              <span
                class="block text-[10px] leading-tight mt-0.5"
                :class="tukangStore.modelKerja === m.id ? 'text-white/85' : ''"
              >
                {{ m.tempo }}
              </span>
            </button>
          </div>
        </div>

        <!-- ── Kategori kerusakan ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Kategori kerusakan</h3>
          <div class="mt-2.5 grid grid-cols-3 gap-2.5">
            <button
              v-for="k in kategori"
              :key="k.id"
              type="button"
              class="flex flex-col items-center justify-start p-3 rounded-2xl bg-(--color-surface-0) text-center transition-all active:scale-[0.97]"
              :class="
                tukangStore.selectedCategoryId === k.id
                  ? 'border-2 border-(--color-azure) shadow-[0_6px_18px_rgba(30,155,240,0.18)]'
                  : 'border border-(--color-outline)/20'
              "
              :aria-pressed="tukangStore.selectedCategoryId === k.id"
              @click="pilihKategori(k.id)"
            >
              <span
                class="w-10 h-10 rounded-full flex items-center justify-center p-1.5 mb-1.5"
                :class="
                  tukangStore.selectedCategoryId === k.id
                    ? 'bg-(--color-azure)/12'
                    : 'bg-(--color-surface-container)'
                "
              >
                <IkonKategoriTukang :id="k.id" />
              </span>
              <span
                class="text-[11.5px] font-extrabold leading-tight"
                :class="tukangStore.selectedCategoryId === k.id ? 'text-(--color-azure)' : ''"
              >
                {{ k.nama }}
              </span>
            </button>
          </div>
        </div>

        <!-- ── Jenis masalah ── -->
        <div
          v-if="kategoriTerpilih"
          class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/15 p-4"
        >
          <h3 class="text-[13.5px] font-display font-extrabold mb-2.5">Jenis masalah</h3>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="sub in kategoriTerpilih.sub"
              :key="sub"
              type="button"
              class="px-3 py-2 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tukangStore.selectedSubCategory === sub
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
              "
              @click="tukangStore.selectedSubCategory = sub"
            >
              {{ sub }}
            </button>
          </div>
        </div>

        <!-- ── Tipe properti ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Tipe properti</h3>
          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="t in TIPE_PROPERTI"
              :key="t.id"
              type="button"
              class="px-3.5 py-2 rounded-full text-[12.5px] font-bold flex items-center gap-1.5 transition-colors active:scale-95"
              :class="
                tukangStore.tipeProperti === t.id
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
              "
              :aria-pressed="tukangStore.tipeProperti === t.id"
              @click="tukangStore.tipeProperti = t.id"
            >
              <Icon :name="t.ikon" class="w-4 h-4" />
              {{ t.nama }}
            </button>
          </div>
        </div>

        <!-- ── Deskripsi ── -->
        <div>
          <div class="flex items-baseline justify-between gap-3">
            <label for="deskripsi-kerusakan" class="text-[14px] font-display font-extrabold">
              Deskripsi kerusakan
            </label>
            <span class="text-[11px] text-(--color-on-surface-variant) shrink-0">Wajib diisi</span>
          </div>
          <textarea
            id="deskripsi-kerusakan"
            v-model="tukangStore.deskripsiMasalah"
            rows="3"
            class="mt-2 w-full rounded-2xl bg-(--color-surface-0) px-3.5 py-3 text-[13px] border-2 border-(--color-outline)/20 focus:border-(--color-azure) outline-none resize-none transition-colors"
            placeholder="Contoh: stop kontak kamar mandi memercik dan MCB turun tiap water heater dinyalakan…"
          ></textarea>
          <p
            class="mt-1.5 flex items-start gap-1.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)"
          >
            <Icon name="info" class="w-4 h-4 shrink-0 text-(--color-azure)" />
            Makin jelas ceritanya, makin dekat estimasi tukang ke harga akhirnya.
          </p>
        </div>

        <!-- ── Foto ── -->
        <div>
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-[14px] font-display font-extrabold">Foto area rusak</h3>
            <span class="text-[11px] font-bold text-(--color-azure) shrink-0">
              {{ foto.length }}/{{ MAKS_FOTO }} terunggah
            </span>
          </div>

          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileChange"
          />

          <div class="mt-2.5 grid grid-cols-4 gap-2.5">
            <div
              v-for="(f, idx) in foto"
              :key="idx"
              class="relative aspect-square rounded-xl overflow-hidden border border-(--color-outline)/20"
            >
              <img :src="f.data" alt="" class="w-full h-full object-cover" />
              <button
                type="button"
                aria-label="Hapus foto"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-(--color-on-surface)/75 text-white flex items-center justify-center"
                @click="foto.splice(idx, 1)"
              >
                <Icon name="x" class="w-3 h-3" />
              </button>
            </div>

            <button
              v-if="bolehTambahFoto"
              type="button"
              class="aspect-square rounded-xl border-2 border-dashed border-(--color-azure)/50 bg-(--color-azure)/6 text-(--color-azure) flex flex-col items-center justify-center gap-1 active:scale-95 transition-transform"
              @click="triggerUpload"
            >
              <Icon name="camera" class="w-5 h-5" />
              <span class="text-[10px] font-bold">Tambah</span>
            </button>

            <!-- Kotak sisa: penanda tempat, sengaja tidak bisa diketuk -->
            <div
              v-for="n in kotakKosong"
              :key="`kosong-${n}`"
              aria-hidden="true"
              class="aspect-square rounded-xl border border-dashed border-(--color-outline)/30 bg-(--color-surface-0) flex items-center justify-center text-(--color-outline)/40"
            >
              <Icon name="image" class="w-5 h-5" />
            </div>
          </div>
        </div>

        <!-- ── Penyediaan material ── -->
        <div>
          <h3 class="text-[14px] font-display font-extrabold">Penyediaan material</h3>
          <p class="mt-0.5 text-[11.5px] text-(--color-on-surface-variant)">
            Ditanya sekarang supaya tidak jadi selisih paham di akhir.
          </p>
          <div class="mt-2.5 flex flex-col gap-2">
            <label
              v-for="m in PENYEDIAAN_MATERIAL"
              :key="m.id"
              class="flex items-start gap-3 p-3.5 rounded-2xl bg-(--color-surface-0) cursor-pointer transition-colors"
              :class="
                tukangStore.penyediaanMaterial === m.id
                  ? 'border-2 border-(--color-azure)'
                  : 'border border-(--color-outline)/20'
              "
            >
              <input
                v-model="tukangStore.penyediaanMaterial"
                type="radio"
                name="penyediaan-material"
                :value="m.id"
                class="mt-0.5 h-4 w-4 shrink-0 accent-(--color-azure)"
              />
              <span class="min-w-0">
                <span class="block text-[13px] font-extrabold leading-tight">{{ m.nama }}</span>
                <span class="block mt-0.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                  {{ m.ringkas }}
                </span>
              </span>
            </label>
          </div>
        </div>

        <!-- ── Lokasi di properti ── -->
        <div v-if="katalog">
          <h3 class="text-[14px] font-display font-extrabold">Bagian mana?</h3>
          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="loc in katalog.lokasi_masalah"
              :key="loc"
              type="button"
              class="px-3 py-2 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tukangStore.lokasiMasalah === loc
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
              "
              @click="tukangStore.lokasiMasalah = loc"
            >
              {{ loc }}
            </button>
          </div>
        </div>
      </section>

      <!-- ═══════════ LANGKAH 2 — JADWAL & LOKASI ═══════════ -->
      <section v-else class="flex flex-col gap-4">
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-5">
          <h3 class="text-[15px] font-display font-extrabold mb-1">Kapan tukang datang?</h3>
          <p class="text-[12px] text-(--color-on-surface-variant) mb-4">
            Pilih waktu kunjungan yang paling pas buat kamu.
          </p>

          <div class="flex flex-col gap-2.5">
            <button
              type="button"
              class="w-full p-3.5 rounded-2xl border text-left flex items-center justify-between transition-all"
              :class="
                tukangStore.jadwalTipe === 'secepatnya'
                  ? 'bg-(--color-azure) text-white border-(--color-azure) shadow-[0_8px_18px_rgba(30,155,240,0.3)]'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
              @click="tukangStore.jadwalTipe = 'secepatnya'"
            >
              <span class="flex items-center gap-2.5">
                <Icon name="sparkle" class="w-6 h-6" />
                <span>
                  <span class="block text-[13.5px] font-bold">Datang secepatnya</span>
                  <span class="block text-[11px] opacity-80">Tukang berangkat begitu ada yang siap</span>
                </span>
              </span>
              <Icon name="chevron-right" class="w-5 h-5" />
            </button>

            <div
              class="p-3.5 rounded-2xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'hari_ini'
                  ? 'bg-(--color-azure)/6 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px]"
                @click="tukangStore.jadwalTipe = 'hari_ini'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Hari ini
                </span>
                <span class="text-[11px] text-(--color-azure)">Pilih jam</span>
              </button>
              <div v-if="tukangStore.jadwalTipe === 'hari_ini'" class="grid grid-cols-3 gap-2 mt-3">
                <button
                  v-for="jam in ['14:00-16:00', '16:00-18:00', '18:00-20:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/25'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>

            <div
              class="p-3.5 rounded-2xl border transition-all"
              :class="
                tukangStore.jadwalTipe === 'besok'
                  ? 'bg-(--color-azure)/6 border-(--color-azure)'
                  : 'bg-(--color-surface-container) border-(--color-outline)/12'
              "
            >
              <button
                type="button"
                class="w-full flex items-center justify-between font-bold text-[13.5px]"
                @click="tukangStore.jadwalTipe = 'besok'"
              >
                <span class="flex items-center gap-2">
                  <Icon name="calendar" class="w-5 h-5 text-(--color-azure)" />
                  Besok
                </span>
                <span class="text-[11px] text-(--color-azure)">Pilih jam</span>
              </button>
              <div v-if="tukangStore.jadwalTipe === 'besok'" class="grid grid-cols-2 gap-2 mt-3">
                <button
                  v-for="jam in ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00']"
                  :key="jam"
                  type="button"
                  class="py-1.5 px-2 rounded-lg border text-[11.5px] font-semibold text-center"
                  :class="
                    tukangStore.jadwalJam === jam
                      ? 'bg-(--color-azure) text-white border-(--color-azure)'
                      : 'bg-(--color-surface-0) border-(--color-outline)/25'
                  "
                  @click="tukangStore.jadwalJam = jam"
                >
                  {{ jam }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Alamat tujuan, bisa diubah tanpa kehilangan isian formulir -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4">
          <div class="flex items-start gap-3">
            <span
              class="w-9 h-9 rounded-full bg-(--color-azure)/10 text-(--color-azure) flex items-center justify-center shrink-0"
            >
              <Icon name="pin" class="w-4.5 h-4.5" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-on-surface-variant)">
                Tukang datang ke
              </p>
              <p class="mt-0.5 text-[13px] font-semibold leading-snug">{{ addressLabel }}</p>
            </div>
            <RouterLink
              :to="{ name: 'task-location', query: { category: 'bisatukang' } }"
              class="shrink-0 text-[12.5px] font-extrabold text-(--color-azure)"
            >
              Ubah
            </RouterLink>
          </div>
        </div>

        <!--
          Siapa yang ditemui tukang di lokasi.
          Bukan basa-basi: tukang yang sampai di depan pagar tanpa nomor yang
          bisa dihubungi berdiri di sana sampai menyerah.
        -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4">
          <h3 class="text-[13.5px] font-display font-extrabold">Kontak di lokasi</h3>
          <div class="mt-3 flex flex-col gap-2.5">
            <label class="block">
              <span class="text-[11.5px] font-semibold text-(--color-on-surface-variant)">Nama</span>
              <input
                v-model="namaPenerima"
                type="text"
                autocomplete="name"
                class="mt-1 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
                placeholder="Nama yang ditemui tukang"
              />
            </label>
            <label class="block">
              <span class="text-[11.5px] font-semibold text-(--color-on-surface-variant)">Nomor HP</span>
              <input
                v-model="telepon"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                class="mt-1 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
                placeholder="08…"
              />
            </label>
          </div>
        </div>

        <!-- Biaya: satu angka untuk harian, rentang untuk borongan -->
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/12 p-4">
          <template v-if="tukangStore.modelKerja === 'harian'">
            <div class="flex items-center justify-between">
              <span class="text-[13px] font-semibold text-(--color-on-surface-variant)">
                Biaya kunjungan
              </span>
              <span class="text-[14px] font-extrabold">{{ formatRupiah(biayaKunjungan) }}</span>
            </div>
            <p class="mt-2 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
              Biaya perbaikan &amp; material dihitung setelah tukang memeriksa, dan
              <span class="font-semibold text-(--color-on-surface)">disetujui dulu sebelum dikerjakan.</span>
            </p>
          </template>
          <template v-else>
            <div class="flex items-center justify-between">
              <span class="text-[13px] font-semibold text-(--color-on-surface-variant)">Survei</span>
              <span class="text-[14px] font-extrabold text-(--color-on-secondary-container)">Gratis</span>
            </div>
            <p class="mt-2 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
              Kamu belum ditagih apa pun. RAB rinci menyusul setelah lokasinya dilihat, dan baru
              mengikat kalau kamu setujui.
            </p>
          </template>
        </div>

        <p
          v-if="galatKirim"
          role="alert"
          class="rounded-2xl bg-(--color-error-container) text-(--color-on-error-container) px-4 py-3 text-[12.5px] font-semibold"
        >
          {{ galatKirim }}
        </p>
      </section>
    </main>

    <!-- ── Bilah aksi ── -->
    <div
      v-if="!memuat && !galatKatalog"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/95 backdrop-blur-md border-t border-(--color-outline)/12"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
        <button
          v-if="fase === 1"
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!boleh1"
          @click="lanjut"
        >
          Lanjut ke jadwal
          <Icon name="arrow-right" class="w-4.5 h-4.5" />
        </button>
        <button
          v-else
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!boleh2 || mengirim"
          @click="pesan"
        >
          {{
            mengirim
              ? 'Mengirim…'
              : tukangStore.modelKerja === 'borongan'
                ? 'Jadwalkan survei'
                : `Pesan · ${formatRupiah(biayaKunjungan)}`
          }}
        </button>
      </div>
    </div>
  </div>
</template>
