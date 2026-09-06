<script setup lang="ts">
/**
 * BisaKirim — layar tunggu sesudah memesan, sebelum ada kurir.
 *
 * Berdiri sebagai halamannya sendiri, bukan satu kartu di layar status: saat
 * ini belum ada apa pun untuk dilacak, dan menaruhnya di antara rute, rincian
 * biaya, serta kode terima membuat satu-satunya kabar yang penting — belum ada
 * kurir — tenggelam di antara hal-hal yang belum berlaku.
 *
 * Yang dijaga di sini SATU kalimat: kamu belum ditagih apa pun. Layar tunggu
 * yang diam tanpa menyebut itu membuat orang menutup aplikasi karena mengira
 * uangnya sudah terpotong untuk pesanan yang tidak jelas nasibnya.
 *
 * Begitu kurirnya ada, halaman ini berpindah sendiri ke layar status — dengan
 * replace, bukan push: layar tunggu yang sudah lewat tidak boleh bisa didatangi
 * lagi lewat tombol kembali, karena isinya sudah tidak benar.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import MencariKurirArt from '@/components/kirim/MencariKurirArt.vue'
import { ambilKiriman } from '@/api/kirim'
import { pesanError } from '@/api/belanja'

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const galat = ref<string | null>(null)
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
  router.push({ name: 'task-kirim-status', params: { nomor } })
}

async function periksa() {
  try {
    const k = await ambilKiriman(nomor)
    galat.value = null

    // Tahap apa pun selain "mencari" berarti sudah ada yang bisa dilihat.
    if (k.tahap !== 'mencari') {
      router.replace({ name: 'task-kirim-status', params: { nomor } })
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

onMounted(() => {
  if (!nomor) {
    router.replace({ name: 'task-kirim' })
    return
  }

  periksa()
  pewaktu = setInterval(periksa, 6000)
  penghitung = setInterval(() => (detik.value += 1), 1000)
})

onBeforeUnmount(() => {
  if (pewaktu) clearInterval(pewaktu)
  if (penghitung) clearInterval(penghitung)
})
</script>

<template>
  <div class="relative min-h-dvh w-full overflow-hidden bg-(--color-surface-container)">
    <MencariKurirArt />

    <!--
      Teksnya HTML di atas SVG, bukan <text> di dalamnya: teks SVG tidak
      melipat sendiri dan tidak ikut ukuran huruf pilihan pengguna, jadi
      kalimat sepanjang ini akan menembus tepi layar sempit tanpa satu pun
      galat muncul.
    -->
    <div class="relative z-10 min-h-dvh flex flex-col max-w-[430px] mx-auto px-6">
      <div class="pt-[calc(1.25rem+env(safe-area-inset-top))]">
        <span
          class="inline-flex items-center gap-1.5 rounded-full bg-white/85 backdrop-blur px-3 py-1.5 text-[11.5px] font-bold text-(--color-azure) shadow-sm"
        >
          <Icon name="package" class="w-3.5 h-3.5" />
          {{ nomor }}
        </span>
      </div>

      <!-- Bagian tengah dikosongkan supaya gambarnya yang terbaca di situ -->
      <div class="flex-1"></div>

      <div class="pb-[calc(1.75rem+env(safe-area-inset-bottom))] text-center">
        <h1 class="text-[22px] font-display font-extrabold leading-tight text-(--color-on-surface)">
          Mencari kurir terdekat untuk kamu…
        </h1>
        <p class="mt-2 text-[13px] leading-relaxed text-(--color-on-surface-variant)">
          Belum ada kurir yang ditugaskan, dan kamu belum ditagih apa pun.
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
      </div>
    </div>
  </div>
</template>
