<script setup lang="ts">
/**
 * Layar tunggu BisaBersih Kantor — mencari cleaner.
 *
 * Berdiri di antara pembayaran dan halaman Detail Order. Detail Order berbicara
 * seolah pekerjaan sudah punya penanggung jawab (kartu cleaner, tombol telepon,
 * pelacakan), jadi halaman itu baru pantas ditampilkan setelah ada yang benar-
 * benar menerima.
 *
 * Yang memindahkan halaman adalah STATUS DI SERVER, bukan timer di browser:
 * selama `diterima` masih false, layar ini tetap layar tunggu. Kalau tidak ada
 * yang menerima seharian, ia tidak akan berpura-pura sudah dapat orang.
 *
 * Dua tahap: MENCARI lalu KETEMU. Tahap kedua bukan hiasan — perpindahan
 * mendadak dari layar tunggu ke halaman penuh membuat orang kehilangan jejak
 * apa yang baru terjadi. Jeda pendek berisi nama cleanernya memberi tahu hasil
 * penantiannya sebelum layarnya berganti.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import MencariCleanerArt from '@/components/bersih/MencariCleanerArt.vue'
import CleanerDitemukanArt from '@/components/bersih/CleanerDitemukanArt.vue'
import CleanerAvatar from '@/components/bersih/CleanerAvatar.vue'
import { ambilStatusPesananBersih, type StatusPesananBersih } from '@/api/bersih'

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const pesanan = ref<StatusPesananBersih | null>(null)
const galat = ref<string | null>(null)
const menit = ref(0)

/** Cukup renggang supaya tidak membebani server, masih terasa langsung. */
const SELANG_MS = 4000

/**
 * Lama layar "berhasil connect" ditahan sebelum pindah.
 *
 * Cukup untuk membaca nama cleanernya, tidak cukup untuk terasa sebagai
 * halangan menuju detail pesanan.
 */
const JEDA_KETEMU_MS = 2600

type Tahap = 'mencari' | 'ketemu'
const tahap = ref<Tahap>('mencari')

let timer: ReturnType<typeof setTimeout> | null = null
let jamTangan: ReturnType<typeof setInterval> | null = null
let timerPindah: ReturnType<typeof setTimeout> | null = null

function keDetailOrder() {
  router.replace({
    name: 'task-bersih-kantor-detail-order',
    query: { invoice: nomor },
  })
}

async function muat() {
  try {
    pesanan.value = await ambilStatusPesananBersih(nomor)
    galat.value = null
  } catch {
    // Sekali gagal bukan alasan berhenti memantau — jaringan ponsel sering
    // putus sebentar. Pesan hanya muncul kalau belum pernah berhasil.
    if (!pesanan.value) galat.value = 'Gagal memuat status pesanan.'
  }

  if (pesanan.value?.diterima) rayakan()
}

/** Tampilkan layar berhasil sebentar, baru pindah ke detail pesanan. */
function rayakan() {
  if (tahap.value === 'ketemu') return
  tahap.value = 'ketemu'
  if (timer) clearTimeout(timer)
  if (jamTangan) clearInterval(jamTangan)
  timerPindah = setTimeout(keDetailOrder, JEDA_KETEMU_MS)
}

function jadwalkan() {
  if (timer) clearTimeout(timer)
  if (pesanan.value?.diterima) return
  timer = setTimeout(async () => {
    await muat()
    jadwalkan()
  }, SELANG_MS)
}

onMounted(async () => {
  await muat()
  jadwalkan()
  jamTangan = setInterval(() => menit.value++, 60_000)
})

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
  if (jamTangan) clearInterval(jamTangan)
  if (timerPindah) clearTimeout(timerPindah)
})

const alamat = computed(() => pesanan.value?.lokasi.alamat ?? '')
const cleaner = computed(() => pesanan.value?.cleaner ?? null)

/**
 * Setelah beberapa menit tanpa yang menerima, pengguna berhak tahu bahwa
 * menunggu di layar ini tidak wajib — pesanannya tetap berjalan di latar.
 */
const lamaMenunggu = computed(() => menit.value >= 3)
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) flex flex-col">
    <header class="shrink-0">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center">
        <!--
          Tombol kembali disembunyikan di tahap "ketemu": layar itu hanya
          bertahan dua detik dan berujung ke detail pesanan, jadi menawarkan
          jalan keluar di detik terakhir hanya membingungkan.
        -->
        <button
          v-if="tahap === 'mencari'"
          type="button"
          aria-label="Kembali ke daftar tugas"
          class="w-10 h-10 rounded-full flex items-center justify-center active:scale-95 transition-transform"
          @click="router.replace({ name: 'task-list' })"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
      </div>
    </header>

    <main class="flex-1 max-w-[430px] w-full mx-auto px-4 pb-10 flex flex-col justify-center">
      <template v-if="tahap === 'mencari'">
        <MencariCleanerArt class="mt-2" />

        <!-- Bar progres tak tentu: pekerjaan berjalan, tapi lamanya belum bisa dijanjikan. -->
        <div class="mt-6 h-2 rounded-full bg-(--color-outline)/15 overflow-hidden">
          <span class="block h-full w-[45%] rounded-full bg-(--color-azure) bar-jalan"></span>
        </div>

        <p class="mt-4 text-center text-[13px] text-(--color-on-surface-variant) leading-relaxed">
          Pesanan Anda sudah masuk. Kami sedang menghubungi tim cleaner.
        </p>
      </template>

      <template v-else>
        <CleanerDitemukanArt class="mt-2" />

        <!-- Nama orangnya: itulah jawaban yang ditunggu selama layar sebelumnya. -->
        <div
          v-if="cleaner"
          class="mt-5 mx-auto flex items-center gap-3 rounded-2xl bg-(--color-surface-0) px-4 py-3"
        >
          <CleanerAvatar
            :gender="cleaner.gender ?? undefined"
            :nama="cleaner.nama"
            class="w-11 h-11 shrink-0"
          />
          <div class="min-w-0">
            <p class="text-[14px] font-extrabold truncate">{{ cleaner.nama }}</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant)">
              {{ cleaner.nama_level || 'Cleaner' }} · menuju lokasi Anda
            </p>
          </div>
        </div>

        <p class="mt-4 text-center text-[12.5px] text-(--color-on-surface-variant)">
          Membuka detail pesanan&hellip;
        </p>
      </template>

      <section class="mt-6 bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-3">
        <div class="flex items-start justify-between gap-3">
          <span class="text-[12px] text-(--color-on-surface-variant)">Nomor pesanan</span>
          <span class="text-[12.5px] font-extrabold text-right">{{ nomor }}</span>
        </div>
        <div v-if="alamat" class="flex items-start justify-between gap-3">
          <span class="text-[12px] text-(--color-on-surface-variant) shrink-0">Lokasi</span>
          <span class="text-[12.5px] font-bold text-right leading-snug">{{ alamat }}</span>
        </div>
      </section>

      <p v-if="galat" class="mt-4 text-center text-[12px] text-(--color-error)">{{ galat }}</p>

      <template v-if="lamaMenunggu">
        <p class="mt-6 text-center text-[12px] text-(--color-on-surface-variant)">
          Masih dicarikan. Anda tidak perlu menunggu di layar ini — pesanannya
          tetap berjalan, dan statusnya bisa dilihat kapan saja di Tugas Saya.
        </p>
        <button
          type="button"
          class="mt-3 mx-auto px-5 py-2.5 rounded-full border border-(--color-outline)/30 text-[12.5px] font-bold active:scale-95 transition-transform"
          @click="router.replace({ name: 'task-list' })"
        >
          Ke Tugas Saya
        </button>
      </template>
    </main>
  </div>
</template>

<style scoped>
/* Bar tak tentu: bergerak terus karena lama menunggu memang belum diketahui. */
@keyframes jalan {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(230%); }
}

.bar-jalan {
  animation: jalan 1.6s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
  .bar-jalan {
    animation: none;
    width: 100%;
  }
}
</style>
