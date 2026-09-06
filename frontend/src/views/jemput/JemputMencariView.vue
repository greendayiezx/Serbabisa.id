<script setup lang="ts">
/**
 * BisaJemput — layar tunggu sesudah memesan, sebelum ada pengemudi.
 *
 * Halamannya sendiri, sepola dengan BisaKirim: sebelum ada pengemudi belum ada
 * apa pun untuk dilacak, dan menaruh kabar itu di antara rute, rincian biaya,
 * serta tombol berbagi perjalanan membuat satu-satunya hal yang penting
 * tenggelam di antara yang belum berlaku.
 *
 * TOMBOL BATAL ADA DI SINI, bukan hanya di layar perjalanan. Kalimat yang
 * dijanjikan di layar ini — "bisa dibatalkan tanpa biaya" — tidak boleh cuma
 * berupa kalimat: janji yang tombolnya ada di halaman lain sama saja dengan
 * menyuruh orang mencari sendiri jalan keluarnya sambil menunggu.
 *
 * Begitu pengemudinya ada, halaman ini berpindah sendiri ke layar perjalanan —
 * dengan replace, bukan push: layar tunggu yang sudah lewat tidak boleh bisa
 * didatangi lagi lewat tombol kembali, karena isinya sudah tidak benar.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import MencariPengemudiArt from '@/components/jemput/MencariPengemudiArt.vue'
import { ambilPerjalanan, batalkanPerjalanan } from '@/api/jemput'
import { pesanError } from '@/api/belanja'

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const galat = ref<string | null>(null)
const membatalkan = ref(false)
const detik = ref(0)
let pewaktu: ReturnType<typeof setInterval> | null = null
let penghitung: ReturnType<typeof setInterval> | null = null

/** Sudah berapa lama menunggu, dalam bentuk m:dd. */
const lamaMenunggu = computed(() => {
  const m = Math.floor(detik.value / 60)
  const s = detik.value % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

function keDetail() {
  router.push({ name: 'task-jemput-perjalanan', params: { nomor } })
}

function hentikanPewaktu() {
  if (pewaktu) {
    clearInterval(pewaktu)
    pewaktu = null
  }
  if (penghitung) {
    clearInterval(penghitung)
    penghitung = null
  }
}

async function periksa() {
  try {
    const p = await ambilPerjalanan(nomor)
    galat.value = null

    // Tahap apa pun selain "mencari" berarti sudah ada yang bisa dilihat —
    // termasuk "batal", yang penjelasannya ada di layar perjalanan.
    if (p.tahap !== 'mencari') {
      hentikanPewaktu()
      router.replace({ name: 'task-jemput-perjalanan', params: { nomor } })
    }
  } catch (e) {
    /*
     * Galat di sini TIDAK menghentikan penantian. Jaringan yang putus sebentar
     * bukan pesanan yang gagal, dan melempar orang keluar dari layar ini akan
     * menyembunyikan pesanan yang sebenarnya baik-baik saja.
     */
    galat.value = pesanError(e)
  }
}

async function batal() {
  if (membatalkan.value) return
  membatalkan.value = true
  galat.value = null
  try {
    await batalkanPerjalanan(nomor)
    hentikanPewaktu()
    router.replace({ name: 'task-jemput-perjalanan', params: { nomor } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    membatalkan.value = false
  }
}

onMounted(() => {
  if (!nomor) {
    router.replace({ name: 'task-jemput-titik' })
    return
  }

  periksa()
  pewaktu = setInterval(periksa, 6000)
  penghitung = setInterval(() => (detik.value += 1), 1000)
})

onBeforeUnmount(hentikanPewaktu)
</script>

<template>
  <div class="relative min-h-dvh w-full overflow-hidden bg-(--color-surface-container)">
    <MencariPengemudiArt />

    <!--
      Teksnya HTML di atas SVG, bukan <text> di dalamnya: teks SVG tidak melipat
      sendiri dan tidak ikut ukuran huruf pilihan pengguna, jadi kalimat
      sepanjang ini akan menembus tepi layar sempit tanpa satu pun galat.
    -->
    <div class="relative z-10 min-h-dvh flex flex-col max-w-[430px] mx-auto px-6">
      <div class="pt-[calc(1.25rem+env(safe-area-inset-top))]">
        <span
          class="inline-flex items-center gap-1.5 rounded-full bg-white/85 backdrop-blur px-3 py-1.5 text-[11.5px] font-bold text-(--color-azure) shadow-sm"
        >
          <Icon name="car" class="w-3.5 h-3.5" />
          {{ nomor }}
        </span>
      </div>

      <!-- Bagian tengah dikosongkan supaya gambarnya yang terbaca di situ -->
      <div class="flex-1"></div>

      <div class="pb-[calc(1.5rem+env(safe-area-inset-bottom))] text-center">
        <h1 class="text-[22px] font-display font-extrabold leading-tight text-(--color-on-surface)">
          Mencari pengemudi terdekat untuk kamu…
        </h1>
        <p class="mt-2 text-[13px] leading-relaxed text-(--color-on-surface-variant)">
          Belum ada pengemudi yang ditugaskan, dan kamu belum ditagih apa pun.
        </p>

        <p class="mt-3 text-[12px] font-semibold text-(--color-on-surface-variant) tabular-nums">
          Menunggu {{ lamaMenunggu }}
        </p>

        <p v-if="galat" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
          {{ galat }} — masih kami coba lagi.
        </p>

        <button
          type="button"
          class="mt-5 w-full py-3.5 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 shadow-lg active:scale-[0.98] transition-transform"
          @click="keDetail"
        >
          Lihat detail
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>

        <button
          type="button"
          class="mt-2.5 w-full py-3 rounded-full bg-white/80 backdrop-blur text-[13.5px] font-bold text-(--color-on-surface-variant) active:scale-[0.98] transition-transform disabled:opacity-50"
          :disabled="membatalkan"
          @click="batal"
        >
          {{ membatalkan ? 'Membatalkan…' : 'Batalkan, belum ada biaya' }}
        </button>
      </div>
    </div>
  </div>
</template>
