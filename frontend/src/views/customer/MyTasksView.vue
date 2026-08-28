<script setup lang="ts">
/**
 * Tugas Saya — daftar aktivitas pesanan.
 *
 * Tata letaknya mengikuti layar Aktivitas Gojek: tab keadaan, saringan layanan,
 * lalu kartu berisi waktu pemesanan, gambar layanan, dan tombol pesan ulang.
 *
 * TENTANG "TERJADWAL": tab ini adalah IRISAN dari "Dalam proses", bukan tab
 * terpisah yang mengambil isinya.
 *
 * Versi pertama membuat keempat tab saling lepas — sebuah pesanan hanya boleh
 * muncul di satu tempat — supaya angka di tab tidak dihitung dua kali. Aturan
 * itu gagal begitu diuji dengan data nyata: SETIAP pesanan BisaBersih punya
 * jadwal di masa depan, jadi seluruh pesanan aktif pindah ke "Terjadwal" dan
 * "Dalam proses" kosong melompong. Pengguna yang baru saja memesan membuka
 * layar ini dan tidak menemukan pesanannya di tempat yang wajar dicari.
 *
 * Jadi sekarang: "Dalam proses" memuat semua pesanan yang belum selesai, dan
 * "Terjadwal" menyorot yang jadwalnya masih akan datang. Angka yang tumpang
 * tindih lebih baik daripada pesanan yang hilang dari pandangan.
 */
import { computed, onMounted, ref } from 'vue'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'
import DaftarTugasSkeleton from '@/components/skeleton/DaftarTugasSkeleton.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { rupiah } from '@/lib/rupiah'
import { ruteTugas } from '@/lib/rutePesanan'
import categoryAngkutImg from '@/assets/category-angkut.svg'
import categoryAntarBarangImg from '@/assets/category-antar-barang.svg'
import categoryAntarJemputImg from '@/assets/category-antar-jemput.svg'
import categoryBelanjaImg from '@/assets/category-belanja.svg'
import categoryBersihBersihImg from '@/assets/category-bersih-bersih.svg'
import categoryTukangImg from '@/assets/category-tukang.svg'
import type { Task, TaskStatus } from '@/types'

const taskStore = useTaskStore()

/*
 * Skeleton, bukan indikator berputar: bentuk daftar ini sudah pasti — kartu
 * berulang dengan tinggi yang sama — jadi kerangkanya menahan ruang dan isinya
 * tidak melompat begitu datang.
 */
const { tampil: memuat, tandaiSiap } = useSkeleton()

/**
 * Gagal memuat TIDAK BOLEH tampil sebagai "belum ada tugas".
 *
 * Daftar yang kosong karena jaringan putus dan daftar yang kosong karena
 * pengguna memang belum pernah memesan adalah dua hal berbeda, dan pengguna
 * berhak tahu yang mana.
 */
const galat = ref<string | null>(null)

onMounted(muat)

async function muat() {
  try {
    await taskStore.fetchTasks()
    galat.value = null
    pilihTabAwal()
  } catch {
    galat.value = 'Gagal memuat daftar tugas. Periksa koneksi, lalu coba lagi.'
  } finally {
    // Di `finally`: kegagalan pun mengakhiri pemuatan. Kalau tidak, layar
    // memuat selamanya sementara yang terjadi sebenarnya galat.
    tandaiSiap()
  }
}

/* ------------------------------------------------------------------ *
 * Tab keadaan
 * ------------------------------------------------------------------ */

type Tab = 'riwayat' | 'proses' | 'terjadwal' | 'draf'

const TAB: { id: Tab; label: string }[] = [
  { id: 'riwayat', label: 'Riwayat' },
  { id: 'proses', label: 'Dalam proses' },
  { id: 'terjadwal', label: 'Terjadwal' },
  { id: 'draf', label: 'Draf' },
]

const tabAktif = ref<Tab>('riwayat')

const SELESAI: TaskStatus[] = ['completed', 'cancelled']

/** Permintaan penawaran bernomor REQ- — belum jadi pesanan, jadi masih draf. */
function draf(task: Task): boolean {
  return (task.nomor_invoice ?? '').startsWith('REQ-')
}

function berjadwal(task: Task): boolean {
  if (!task.dijadwalkan_pada) return false
  const waktu = new Date(task.dijadwalkan_pada).getTime()
  return !Number.isNaN(waktu) && waktu > Date.now()
}

/**
 * Apakah sebuah pesanan masuk tab tertentu.
 *
 * Ditulis sebagai pertanyaan per tab, bukan "tab ini milik siapa", justru
 * karena satu pesanan boleh masuk lebih dari satu tab (lihat catatan di atas).
 */
function cocokTab(task: Task, tab: Tab): boolean {
  const selesai = SELESAI.includes(task.status)

  switch (tab) {
    case 'draf':
      return draf(task)
    case 'riwayat':
      return !draf(task) && selesai
    case 'proses':
      return !draf(task) && !selesai
    case 'terjadwal':
      return !draf(task) && !selesai && berjadwal(task)
  }
}

/* ------------------------------------------------------------------ *
 * Layanan: gambar, nama, dan saringan
 * ------------------------------------------------------------------ */

interface Layanan {
  nama: string
  gambar: string
  /** Rute untuk memesan ulang layanan yang sama. */
  rute: string
}

const LAYANAN: Record<string, Layanan> = {
  bisabersih: { nama: 'BisaBersih', gambar: categoryBersihBersihImg, rute: 'task-bersih-detail' },
  bisabelanja: { nama: 'BisaBelanja', gambar: categoryBelanjaImg, rute: 'task-belanja-detail' },
  bisaangkut: { nama: 'BisaAngkut', gambar: categoryAngkutImg, rute: 'task-angkut-location' },
  bisakirim: { nama: 'BisaKirim', gambar: categoryAntarBarangImg, rute: 'home' },
  bisajemput: { nama: 'BisaJemput', gambar: categoryAntarJemputImg, rute: 'home' },
  bisatukang: { nama: 'BisaTukang', gambar: categoryTukangImg, rute: 'home' },
}

/**
 * Slug layanan sebuah tugas.
 *
 * Kategori dulu, judul belakangan: kategori berasal dari basis data dan tidak
 * berubah-ubah, sedangkan judul hanya cadangan untuk tugas custom yang memang
 * tidak berkategori.
 */
function slugLayanan(task: Task): string | null {
  if (task.category?.slug && task.category.slug in LAYANAN) return task.category.slug
  const cocok = Object.entries(LAYANAN).find(([, l]) => task.judul.startsWith(l.nama))
  return cocok?.[0] ?? null
}

function layananDari(task: Task): Layanan | null {
  const slug = slugLayanan(task)
  return slug ? LAYANAN[slug] : null
}

const layananDipilih = ref<string | null>(null)

/** Hanya layanan yang benar-benar pernah dipesan yang jadi tombol saringan. */
const saringanLayanan = computed(() => {
  const ada = new Set<string>()
  for (const t of taskStore.tasks) {
    const slug = slugLayanan(t)
    if (slug) ada.add(slug)
  }
  return [...ada].map((slug) => ({ slug, nama: LAYANAN[slug].nama }))
})

/* ------------------------------------------------------------------ *
 * Saringan status
 * ------------------------------------------------------------------ */

const LABEL_STATUS: Record<TaskStatus, string> = {
  pending: 'Menunggu mitra',
  accepted: 'Diterima mitra',
  in_progress: 'Sedang dikerjakan',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
}

const statusDipilih = ref<TaskStatus | ''>('')

/** Pilihan status mengikuti tab yang sedang dibuka, bukan daftar tetap. */
const pilihanStatus = computed(() => {
  const ada = new Set<TaskStatus>()
  for (const t of taskStore.tasks) {
    if (cocokTab(t, tabAktif.value)) ada.add(t.status)
  }
  return [...ada].map((s) => ({ nilai: s, label: LABEL_STATUS[s] }))
})

/**
 * Tab pembuka dipilih dari isi datanya, bukan ditetapkan mati di "Riwayat".
 *
 * Orang yang punya pesanan berjalan datang ke layar ini untuk pesanan itu —
 * membuka tab riwayat yang kosong membuat halaman terlihat rusak. Yang belum
 * punya pesanan berjalan tetap mendarat di Riwayat seperti tata letak acuannya.
 *
 * Hanya berlaku sekali, sebelum pengguna menyentuh tabnya sendiri.
 */
let tabPernahDipilih = false

function pilihTabAwal() {
  if (tabPernahDipilih) return
  const jumlah = jumlahPerTab.value
  const urutan: Tab[] = ['proses', 'terjadwal', 'riwayat', 'draf']
  tabAktif.value = urutan.find((t) => jumlah[t] > 0) ?? 'riwayat'
}

function gantiTab(tab: Tab) {
  tabPernahDipilih = true
  tabAktif.value = tab
  // Status yang dipilih belum tentu ada di tab baru; menyisakannya membuat
  // daftar tampak kosong tanpa sebab yang terlihat.
  statusDipilih.value = ''
}

/* ------------------------------------------------------------------ *
 * Daftar yang tampil
 * ------------------------------------------------------------------ */

const terlihat = computed(() =>
  taskStore.tasks.filter((t) => {
    if (!cocokTab(t, tabAktif.value)) return false
    if (layananDipilih.value && slugLayanan(t) !== layananDipilih.value) return false
    if (statusDipilih.value && t.status !== statusDipilih.value) return false
    return true
  }),
)

const jumlahPerTab = computed(() => {
  const hitung: Record<Tab, number> = { riwayat: 0, proses: 0, terjadwal: 0, draf: 0 }
  for (const t of taskStore.tasks) {
    for (const tab of TAB) {
      if (cocokTab(t, tab.id)) hitung[tab.id]++
    }
  }
  return hitung
})

/* ------------------------------------------------------------------ *
 * Tampilan kartu
 * ------------------------------------------------------------------ */

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/**
 * "22 Jul, 17:44" — waktu pemesanan dalam WIB.
 *
 * Server menyimpan UTC; konversinya dilakukan di sini, di lapisan tampilan.
 * Tanpa itu pesanan sore hari bisa tercetak sebagai hari sebelumnya.
 */
function waktuPesan(iso: string | null | undefined): string {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'

  const bagian = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(d)
  const ambil = (t: string) => bagian.find((b) => b.type === t)?.value ?? ''

  return `${ambil('day')} ${BULAN[Number(ambil('month')) - 1]}, ${ambil('hour')}:${ambil('minute')}`
}

/** Kode pendek pesanan: SBB-260823-EZBEGNZ → EZBEGNZ. */
function kodePesanan(task: Task): string {
  const invoice = task.nomor_invoice ?? ''
  return invoice.split('-')[2] ?? invoice
}

const RUPA_STATUS: Record<TaskStatus, { ikon: string; warna: string }> = {
  pending: { ikon: 'clock', warna: 'text-(--color-on-surface-variant)' },
  accepted: { ikon: 'check-circle', warna: 'text-(--color-azure)' },
  in_progress: { ikon: 'clock', warna: 'text-(--color-azure)' },
  completed: { ikon: 'check-circle', warna: 'text-[#1E9E4A]' },
  cancelled: { ikon: 'x', warna: 'text-(--color-error)' },
}

/** Pesan ulang hanya masuk akal untuk pesanan yang sudah tuntas. */
function bisaPesanLagi(task: Task): boolean {
  return SELESAI.includes(task.status) && layananDari(task) !== null
}
</script>

<template>
  <AppLayout>
    <div class="pt-5 pb-6">
      <h2 class="px-5 text-lg font-extrabold">Tugas Saya</h2>

      <!-- Tab keadaan -->
      <div
        class="mt-4 px-5 flex items-center gap-5 border-b border-(--color-outline)/40 overflow-x-auto no-scrollbar"
        role="tablist"
      >
        <button
          v-for="t in TAB"
          :key="t.id"
          type="button"
          role="tab"
          :aria-selected="tabAktif === t.id"
          class="shrink-0 pb-2.5 -mb-px border-b-2 text-[13.5px] transition-colors"
          :class="
            tabAktif === t.id
              ? 'border-(--color-azure) text-(--color-on-surface) font-extrabold'
              : 'border-transparent text-(--color-on-surface-variant) font-semibold'
          "
          @click="gantiTab(t.id)"
        >
          {{ t.label }}
          <span v-if="jumlahPerTab[t.id]" class="text-[11.5px] font-bold opacity-60">
            {{ jumlahPerTab[t.id] }}
          </span>
        </button>
      </div>

      <!-- Saringan layanan & status -->
      <div class="mt-3 px-5 flex items-center gap-2 overflow-x-auto no-scrollbar">
        <button
          type="button"
          class="shrink-0 rounded-full px-4 py-2 text-[12.5px] font-bold border transition-colors"
          :class="
            layananDipilih === null
              ? 'bg-(--color-azure) border-(--color-azure) text-white'
              : 'bg-(--color-surface-0) border-(--color-outline) text-(--color-on-surface)'
          "
          @click="layananDipilih = null"
        >
          Semua
        </button>

        <button
          v-for="l in saringanLayanan"
          :key="l.slug"
          type="button"
          class="shrink-0 rounded-full px-4 py-2 text-[12.5px] font-bold border transition-colors"
          :class="
            layananDipilih === l.slug
              ? 'bg-(--color-azure) border-(--color-azure) text-white'
              : 'bg-(--color-surface-0) border-(--color-outline) text-(--color-on-surface)'
          "
          @click="layananDipilih = l.slug"
        >
          {{ l.nama }}
        </button>

        <!--
          <select> asli, bukan menu buatan sendiri: ponsel menampilkannya sebagai
          pemilih bawaan sistem yang sudah bisa diakses papan ketik dan pembaca
          layar, dan tidak ada satu pun perilaku yang perlu ditiru ulang.
        -->
        <div
          v-if="pilihanStatus.length > 1"
          class="shrink-0 relative rounded-full border border-(--color-outline) bg-(--color-surface-0)"
        >
          <select
            v-model="statusDipilih"
            aria-label="Saring menurut status"
            class="appearance-none bg-transparent pl-4 pr-8 py-2 text-[12.5px] font-bold outline-none"
          >
            <option value="">Status</option>
            <option v-for="s in pilihanStatus" :key="s.nilai" :value="s.nilai">{{ s.label }}</option>
          </select>
          <Icon
            name="chevron-down"
            class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-(--color-on-surface-variant)"
          />
        </div>
      </div>

      <div class="px-5 mt-3.5">
        <DaftarTugasSkeleton v-if="memuat" />

        <div v-else-if="galat" class="text-center py-10">
          <Icon name="alert" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
          <p class="text-sm text-(--color-on-surface-variant)">{{ galat }}</p>
          <button
            type="button"
            class="inline-block mt-3 rounded-full bg-(--color-azure) text-white font-bold text-sm px-5 py-2.5 active:scale-95 transition-transform"
            @click="muat"
          >
            Coba Lagi
          </button>
        </div>

        <div v-else class="space-y-3">
          <article
            v-for="task in terlihat"
            :key="task.id"
            class="rounded-(--radius-card) bg-(--color-surface-0) border border-(--color-outline)/60 p-4"
          >
            <p class="text-[11.5px] text-(--color-on-surface-variant)">
              {{ waktuPesan(task.created_at) }}
            </p>

            <div class="mt-2 flex items-start gap-3">
              <!-- Gambar layanan -->
              <div
                class="w-20 h-20 shrink-0 rounded-xl bg-(--color-primary-container) flex items-center justify-center overflow-hidden"
              >
                <img
                  v-if="layananDari(task)"
                  :src="layananDari(task)!.gambar"
                  :alt="layananDari(task)!.nama"
                  class="w-16 h-16 object-contain"
                />
                <Icon v-else name="clipboard" class="w-6 h-6 text-(--color-on-surface-variant)" />
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                  <RouterLink :to="ruteTugas(task)" class="min-w-0 flex-1">
                    <p class="text-[14px] font-extrabold leading-snug line-clamp-2">
                      {{ task.judul }}
                    </p>

                    <!-- Nomor pesanan tepat di bawah judul pengerjaan. -->
                    <p
                      v-if="kodePesanan(task)"
                      class="mt-0.5 text-[11.5px] font-bold tracking-wide text-(--color-on-surface-variant)"
                    >
                      No. {{ kodePesanan(task) }}
                    </p>
                  </RouterLink>

                  <p
                    v-if="task.harga || task.budget"
                    class="shrink-0 text-[13.5px] font-extrabold whitespace-nowrap"
                  >
                    {{ rupiah(task.harga ?? task.budget) }}
                  </p>
                </div>

                <!-- Tujuan. Ikonnya oranye supaya alamat langsung ketemu mata. -->
                <p class="mt-1.5 flex items-start gap-1.5 text-[11.5px] text-(--color-on-surface-variant)">
                  <Icon name="pin" class="w-3.5 h-3.5 shrink-0 mt-px text-[#F97316]" />
                  <span class="line-clamp-1">{{ task.lokasi_alamat }}</span>
                </p>

                <div class="mt-2 flex items-center justify-between gap-3">
                  <span class="flex items-center gap-1.5 text-[12px] font-semibold">
                    <Icon
                      :name="RUPA_STATUS[task.status].ikon"
                      class="w-4 h-4 shrink-0"
                      :class="RUPA_STATUS[task.status].warna"
                    />
                    {{ LABEL_STATUS[task.status] }}
                  </span>

                  <RouterLink
                    v-if="bisaPesanLagi(task)"
                    :to="{ name: layananDari(task)!.rute }"
                    class="shrink-0 rounded-full bg-(--color-azure) text-white text-[12.5px] font-bold px-4 py-2 active:scale-95 transition-transform"
                  >
                    Pesan lagi
                  </RouterLink>
                </div>
              </div>
            </div>
          </article>

          <div v-if="!terlihat.length" class="text-center py-12">
            <Icon name="clipboard" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
            <p class="text-sm text-(--color-on-surface-variant)">
              <template v-if="taskStore.tasks.length">
                Tidak ada pesanan di bagian ini.
              </template>
              <template v-else>Belum ada tugas. Yuk buat yang pertama!</template>
            </p>
            <RouterLink
              v-if="!taskStore.tasks.length"
              :to="{ name: 'home' }"
              class="inline-block mt-3 rounded-full bg-(--color-azure) text-white font-bold text-sm px-5 py-2.5"
            >
              Buat Tugas
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Baris saringan digeser dengan jari; batangnya sendiri tidak perlu terlihat. */
.no-scrollbar {
  scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>
