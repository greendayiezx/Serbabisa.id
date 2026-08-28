<script setup lang="ts">
/**
 * Konfirmasi permintaan penawaran BisaBersih Kantor.
 *
 * Satu hal yang paling penting di layar ini: NOMOR PERMINTAAN. Itulah rujukan
 * tunggal yang dipakai pelanggan saat menanyakan status, jadi ia ditampilkan
 * besar dan bisa disalin sekali ketuk.
 *
 * Halaman ini membaca ulang datanya dari server memakai nomor di URL, bukan
 * mengandalkan state yang dibawa dari halaman sebelumnya — supaya tautannya
 * tetap berguna kalau dibuka lagi nanti atau dibagikan ke rekan.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PermintaanTerkirimArt from '@/components/bersih/PermintaanTerkirimArt.vue'
import { rupiah } from '@/lib/rupiah'
import { pesanError } from '@/api/belanja'
import { ambilPermintaanKantor, tautanPdfPermintaan, type PermintaanKantor } from '@/api/bersihKantor'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const nomor = String(route.params.nomor ?? '')

/**
 * Halaman ini punya dua kedatangan: baru saja mengirim, atau membuka riwayat
 * dari "Tugas Saya". Isinya sama; yang berbeda hanya kalimat pembukanya —
 * "berhasil dikirim" tidak masuk akal untuk permintaan minggu lalu.
 */
const baruDikirim = route.query.baru === '1'

const permintaan = ref<PermintaanKantor | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)

onMounted(async () => {
  void siapkanTautan()
  try {
    permintaan.value = await ambilPermintaanKantor(nomor)
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
})

/* ---------------- Salin nomor ---------------- */
const tersalin = ref(false)

async function salinNomor() {
  try {
    await navigator.clipboard.writeText(nomor)
    tersalin.value = true
    setTimeout(() => (tersalin.value = false), 2000)
  } catch {
    // Clipboard bisa diblokir (konteks non-HTTPS, izin ditolak). Nomornya
    // tetap terbaca di layar, jadi kegagalan ini tidak perlu diributkan.
  }
}

/* ---------------- Langkah berikutnya ---------------- */

/**
 * Empat langkah tetap; yang berpindah hanya penandanya.
 *
 * Posisinya ditentukan TAHAP DI SERVER — apa yang benar-benar sudah dikerjakan
 * tim — bukan waktu yang berlalu. Kalau belum ada yang menghubungi PIC,
 * langkah itu memang belum boleh tercentang.
 */
const LANGKAH = [
  {
    judul: 'Peninjauan detail',
    isi: 'Tim BisaBersih sedang meninjau kebutuhan kantormu.',
  },
  {
    judul: 'Tim menghubungi PIC',
    isi: 'Lewat nomor WhatsApp yang kamu isi, maksimal 1 hari kerja.',
  },
  {
    judul: 'Survei lokasi bila perlu',
    isi: 'Untuk kantor bertingkat atau berarea luas, agar penawarannya akurat.',
  },
  {
    judul: 'Penawaran dikirim',
    isi: 'Berisi tiga pilihan paket yang bisa kamu bandingkan.',
  },
]

/**
 * Langkah keberapa yang sedang berjalan.
 *
 * Langkah terakhir tidak digerakkan tim lewat tombol: ia tercapai sendiri
 * begitu dokumen penawarannya benar-benar ada.
 */
const langkahAktif = computed(() => {
  if (permintaan.value?.nomor_penawaran) return 3
  return { ditinjau: 0, dihubungi: 1, survei: 2 }[permintaan.value?.tahap ?? 'ditinjau'] ?? 0
})

/** Judul di dalam ilustrasi — pendek, karena teks SVG tidak membungkus baris. */
const judulArt = computed(() => {
  if (nomorPenawaran.value) return 'Penawaran Sudah Siap'
  return baruDikirim ? 'Permintaan Berhasil Dikirim' : 'Permintaan Penawaran'
})

const subjudulArt = computed(() =>
  nomorPenawaran.value ? 'Tiga pilihan paket menunggu' : 'Sedang ditinjau oleh tim terkait',
)

/** Penawaran hanya bisa dibuka setelah tim menyusunnya. */
const nomorPenawaran = computed(() => permintaan.value?.nomor_penawaran ?? null)

function kePenawaran() {
  if (!nomorPenawaran.value) return
  router.push({ name: 'penawaran', params: { nomor: nomorPenawaran.value } })
}

function keChat() {
  if (!permintaan.value) return
  router.push({ name: 'task-chat', params: { id: permintaan.value.id } })
}

/**
 * Tautan ke bukti permintaan, disiapkan LEBIH DULU saat halaman dibuka.
 *
 * Sengaja bukan window.open: membuka jendela kosong lalu mengisi alamatnya
 * setelah await ternyata rapuh — bisa gagal karena pemblokir popup, ekstensi,
 * atau handle jendela yang dilepas peramban, dan yang tersisa hanya tab
 * about:blank. Tautan <a> biasa yang benar-benar diketuk pengguna tidak pernah
 * dianggap popup dan tidak butuh handle apa pun.
 *
 * Isinya URL http bertanda tangan, bukan blob URL — Chrome menolak menampilkan
 * PDF dari blob di sebagian lingkungan, termasuk emulasi ponsel.
 */
const tautanPdf = ref<string | null>(null)

async function siapkanTautan() {
  try {
    tautanPdf.value = await tautanPdfPermintaan(nomor)
  } catch {
    // Dibiarkan null: tombolnya tampil nonaktif, bukan menjanjikan sesuatu
    // yang tidak bisa dibuka.
    tautanPdf.value = null
  }
}

/**
 * Tanda tangan URL punya masa berlaku. Halaman yang dibiarkan terbuka lama
 * akan memegang tautan basi, jadi disegarkan tiap kali tabnya dilihat lagi.
 */
function onTampak() {
  if (document.visibilityState === 'visible') void siapkanTautan()
}

onMounted(() => document.addEventListener('visibilitychange', onTampak))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', onTampak))


</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
    <!-- Tombol kembali di ujung kiri atas -->
    <button
      type="button"
      aria-label="Kembali"
      class="absolute top-4 left-4 z-40 w-10 h-10 rounded-full bg-white/90 text-slate-800 flex items-center justify-center shadow-md backdrop-blur-xs transition-transform active:scale-95"
      @click="kembali"
    >
      <Icon name="arrow-left" class="w-5 h-5 text-slate-800" />
    </button>

    <main class="max-w-[430px] mx-auto px-4 pt-14 flex flex-col gap-4">
      <h1 class="sr-only">{{ judulArt }}</h1>

      <!-- Tanda berhasil -->
      <section class="flex flex-col items-center text-center pb-1">
        <PermintaanTerkirimArt :judul="judulArt" :subjudul="subjudulArt" class="max-w-[360px]" />
      </section>

      <!-- Nomor permintaan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5 text-center">
        <p class="text-[11.5px] text-(--color-on-surface-variant)">Nomor permintaan</p>
        <button
          type="button"
          class="mt-1 inline-flex items-center gap-2 active:scale-95 transition-transform"
          :aria-label="`Salin nomor permintaan ${nomor}`"
          @click="salinNomor"
        >
          <span class="text-[24px] font-display font-extrabold tracking-wide">{{ nomor }}</span>
          <Icon
            :name="tersalin ? 'check' : 'clipboard'"
            class="w-4.5 h-4.5"
            :class="tersalin ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
          />
        </button>
        <p class="mt-1 text-[11px] text-(--color-on-surface-variant)">
          {{ tersalin ? 'Nomor tersalin.' : 'Ketuk untuk menyalin.' }}
        </p>
      </section>

      <!-- Ringkasan permintaan -->
      <section v-if="permintaan" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Ringkasan Permintaan</h3>
        <dl class="flex flex-col gap-2 text-[12.5px]">
          <div class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">Perusahaan:</dt>
            <dd class="flex-1 min-w-0">{{ permintaan.nama_perusahaan }}</dd>
          </div>
          <div v-if="permintaan.nama_pic" class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">PIC:</dt>
            <dd class="flex-1 min-w-0">{{ permintaan.nama_pic }}</dd>
          </div>
          <div v-if="permintaan.telepon_pic" class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">No. Telp:</dt>
            <dd class="flex-1 min-w-0">
              <!-- Bisa langsung ditelepon dari ponsel. -->
              <a :href="`tel:${permintaan.telepon_pic}`" class="text-(--color-azure) underline underline-offset-2">
                {{ permintaan.telepon_pic }}
              </a>
            </dd>
          </div>
          <div class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">Alamat:</dt>
            <dd class="flex-1 min-w-0 leading-snug">{{ permintaan.alamat }}</dd>
          </div>
          <div class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">Layanan:</dt>
            <dd class="flex-1 min-w-0">{{ permintaan.jenis_layanan }}</dd>
          </div>
          <div v-if="permintaan.frekuensi" class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">Frekuensi:</dt>
            <dd class="flex-1 min-w-0">{{ permintaan.frekuensi }}</dd>
          </div>
          <div v-if="permintaan.estimasi" class="flex gap-2">
            <dt class="w-28 shrink-0 font-bold">Estimasi awal:</dt>
            <dd class="flex-1 min-w-0">
              {{ rupiah(permintaan.estimasi) }}
              <span class="text-(--color-on-surface-variant)">per kunjungan</span>
            </dd>
          </div>
        </dl>

        <!-- Berkasnya berisi ringkasan yang sama persis dengan daftar di atas,
             jadi tombolnya diletakkan di sini, bukan berdiri sendiri di bawah. -->
        <a
          v-if="tautanPdf"
          :href="tautanPdf"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-4 w-full h-11 rounded-full border border-(--color-outline)/40 text-[13px] font-bold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
        >
          <Icon name="receipt" class="w-4 h-4" />
          Lihat Bukti Permintaan (PDF)
        </a>
        <span
          v-else
          class="mt-4 w-full h-11 rounded-full border border-(--color-outline)/40 text-[13px] font-bold flex items-center justify-center gap-2 opacity-50"
        >
          <Icon name="receipt" class="w-4 h-4" />
          Menyiapkan berkas&hellip;
        </span>
      </section>

      <p v-else-if="memuat" class="text-[13px] text-(--color-on-surface-variant)">
        Memuat ringkasan&hellip;
      </p>
      <p v-else-if="galat" class="text-[12.5px] text-(--color-error)">{{ galat }}</p>

      <!-- Langkah berikutnya -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-4">Langkah Selanjutnya</h3>

        <ol class="relative flex flex-col gap-5 pl-6 border-l-2 border-(--color-outline)/20 ml-1.5">
          <li v-for="(l, i) in LANGKAH" :key="l.judul" class="relative">
            <span
              class="absolute -left-[31px] top-1 w-4 h-4 rounded-full border-2 border-(--color-surface-0) flex items-center justify-center"
              :class="
                i < langkahAktif
                  ? 'bg-(--color-azure)'
                  : i === langkahAktif
                    ? 'bg-(--color-azure) shadow-[0_0_0_4px_rgba(30,155,240,0.2)]'
                    : 'bg-(--color-outline)/35'
              "
            >
              <Icon v-if="i < langkahAktif" name="check" class="w-2.5 h-2.5 text-white" />
            </span>
            <h4
              class="text-[13px] font-bold"
              :class="i <= langkahAktif ? '' : 'text-(--color-on-surface-variant)'"
            >
              {{ l.judul }}
            </h4>
            <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mt-0.5">
              {{ l.isi }}
            </p>
          </li>
        </ol>
      </section>

      <!-- Penawaran sudah siap -->
      <button
        v-if="nomorPenawaran"
        type="button"
        class="w-full rounded-2xl bg-(--color-secondary-container) text-(--color-on-secondary-container) p-4 flex items-center gap-3 text-left active:scale-[0.99] transition-transform"
        @click="kePenawaran"
      >
        <Icon name="receipt" class="w-5 h-5 shrink-0" />
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] font-bold">Penawaranmu sudah tersedia</span>
          <span class="block text-[11.5px]">Nomor {{ nomorPenawaran }} — lihat tiga pilihan paket.</span>
        </span>
        <Icon name="chevron-right" class="w-4 h-4 shrink-0" />
      </button>

      <!-- Aksi -->
      <div class="flex flex-col gap-2.5 mt-1">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-bold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="keChat"
        >
          <Icon name="chat" class="w-4.5 h-4.5" />
          Chat Customer Service
        </button>
      </div>
    </main>
  </div>
</template>
