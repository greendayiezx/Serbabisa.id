<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { usePesananStore, type Pesanan } from '@/stores/pesanan'
import { formatTanggalInvoice } from '@/lib/invoice'
import InvoiceCetak from '@/components/belanja/InvoiceCetak.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import StatusBayarSkeleton from '@/components/skeleton/StatusBayarSkeleton.vue'

const route = useRoute()
const router = useRouter()
const pesananStore = usePesananStore()

const nomorPesanan = computed(() => String(route.params.nomor ?? ''))

/**
 * Pesanan dibaca dari server, bukan dari localStorage. Cache lokal dipakai
 * lebih dulu supaya halaman tidak kosong sepersekian detik setelah membayar,
 * lalu ditimpa versi server begitu tiba.
 */
const pesanan = ref<Pesanan | null>(pesananStore.cari(nomorPesanan.value))

function cekStatus() {
  if (!pesanan.value) return
  router.push({ name: 'task-belanja-status-pesanan', params: { nomor: pesanan.value.nomor } })
}

/**
 * Buka dialog cetak browser. Dari sana pengguna memilih "Simpan sebagai PDF".
 * Yang tercetak hanya <InvoiceCetak>; sisa antarmuka disembunyikan lewat
 * aturan @media print di bawah.
 */
function cetakInvoice() {
  window.print()
}

function keBeranda() {
  router.push({ name: 'home' })
}

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(async () => {
  try {
    const dariServer = await pesananStore.muat(nomorPesanan.value)
    if (dariServer) pesanan.value = dariServer
  } finally {
    // Skeleton dilepas setelah request selesai — berhasil maupun gagal —
    // supaya halaman tidak menggantung di kondisi memuat kalau server mati.
    tandaiSiap()
  }
})
</script>

<template>
  <StatusBayarSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32 print:min-h-0 print:bg-white print:pb-0">
      <header class="sticky top-0 z-30 bg-(--color-surface-0) print:hidden">
        <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
          <button
            type="button"
            aria-label="Kembali ke beranda"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="keBeranda"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Status Pembayaran</h1>
        </div>
      </header>
  
      <div v-if="!pesanan" class="max-w-[430px] mx-auto px-4 py-16 text-center print:hidden">
        <p class="text-4xl mb-3">🧾</p>
        <p class="text-[14px] font-bold mb-1">Pesanan tidak ditemukan</p>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-5">
          Nomor pesanan ini tidak ada di perangkat kamu.
        </p>
        <button
          type="button"
          class="h-11 px-6 rounded-full bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
          @click="keBeranda"
        >
          Kembali ke beranda
        </button>
      </div>
  
      <template v-else>
        <section class="bg-(--color-surface-0) px-4 pb-8 print:hidden">
          <div class="max-w-[430px] mx-auto">
            <!-- Ilustrasi sementara. Akan diganti animasi Lottie. -->
            <div class="px-6 pt-2">
              <svg viewBox="0 0 400 240" class="w-full h-auto block" xmlns="http://www.w3.org/2000/svg">
                <defs>
                  <linearGradient id="sbBlob" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2196F3" />
                    <stop offset="100%" stop-color="#8BC53F" />
                  </linearGradient>
                  <linearGradient id="sbPhone" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#163F78" />
                    <stop offset="100%" stop-color="#0A326B" />
                  </linearGradient>
                  <linearGradient id="sbSuccess" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#A8E05A" />
                    <stop offset="100%" stop-color="#8BC53F" />
                  </linearGradient>
                  <linearGradient id="sbGold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE98A" />
                    <stop offset="50%" stop-color="#F4C542" />
                    <stop offset="100%" stop-color="#DDAA22" />
                  </linearGradient>
                  <filter id="sbShadow">
                    <feDropShadow dx="0" dy="7" stdDeviation="7" flood-color="#0A326B" flood-opacity=".22" />
                  </filter>
                </defs>
  
                <ellipse cx="200" cy="126" rx="160" ry="104" fill="url(#sbBlob)" opacity="0.35" />
  
                <!-- Bintang & titik dekoratif -->
                <path d="M82 93 L86 103 L96 107 L86 111 L82 121 L78 111 L68 107 L78 103Z" fill="#F4C542" />
                <path d="M315 92 L318 100 L326 103 L318 106 L315 114 L312 106 L304 103 L312 100Z" fill="#F4C542" />
                <circle cx="91" cy="153" r="4" fill="#8BC53F" />
                <circle cx="309" cy="153" r="4" fill="#8BC53F" />
  
                <g filter="url(#sbShadow)">
                  <rect x="130" y="13" width="140" height="214" rx="25" fill="url(#sbPhone)" />
                  <rect x="137" y="25" width="126" height="190" rx="19" fill="#F8FBFF" />
                  <rect x="181" y="18" width="38" height="5" rx="2.5" fill="#061E43" />
  
                  <text
                    x="200"
                    y="52"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="9"
                    font-weight="700"
                    fill="#0A326B"
                  >
                    BisaBelanja
                  </text>
  
                  <circle cx="200" cy="91" r="28" fill="url(#sbSuccess)" />
                  <path
                    d="M185 91 L195 101 L216 79"
                    fill="none"
                    stroke="#FFFFFF"
                    stroke-width="6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M226 69 L229 76 L236 79 L229 82 L226 89 L223 82 L216 79 L223 76Z"
                    fill="url(#sbGold)"
                  />
  
                  <text
                    x="200"
                    y="130"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="13"
                    font-weight="800"
                    fill="#0A326B"
                  >
                    Pembayaran
                  </text>
                  <text
                    x="200"
                    y="147"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="13"
                    font-weight="800"
                    fill="#0A326B"
                  >
                    Berhasil
                  </text>
  
                  <rect x="163" y="159" width="74" height="5" rx="2.5" fill="#E5EDF5" />
                  <rect x="173" y="169" width="54" height="5" rx="2.5" fill="#E5EDF5" />
  
                  <rect x="177" y="187" width="46" height="10" rx="5" fill="#8BC53F" />
                  <circle cx="187" cy="192" r="2.5" fill="#FFFFFF" />
                  <rect x="193" y="190" width="22" height="4" rx="2" fill="#FFFFFF" />
                </g>
  
                <path
                  d="M103 183L108 180 M99 191H106 M108 198L111 193"
                  fill="none"
                  stroke="#8BC53F"
                  stroke-width="3"
                  stroke-linecap="round"
                />
              </svg>
            </div>
  
            <h2 class="text-[20px] font-extrabold text-center mt-4">Pembayaran berhasil</h2>
            <p class="text-[13px] text-(--color-on-surface-variant) text-center mt-1">
              Pesananmu segera kami proses. Terima kasih!
            </p>
          </div>
        </section>
  
        <section class="bg-(--color-surface-0) px-4 py-5 mt-2.5 print:hidden">
          <div class="max-w-[430px] mx-auto flex flex-col gap-3.5">
            <div class="flex items-start gap-3">
              <p class="flex-1 text-[14px] font-extrabold break-all">{{ pesanan.invoice }}</p>
              <button
                type="button"
                class="shrink-0 h-9 px-3.5 rounded-full border border-(--color-azure) text-(--color-azure) text-[12px] font-bold flex items-center gap-1.5 active:scale-95 transition-transform"
                @click="cetakInvoice"
              >
                <Icon name="receipt" class="w-3.5 h-3.5" />
                Unduh PDF
              </button>
            </div>
  
            <div class="flex items-center justify-between gap-3">
              <span class="text-[13px] text-(--color-on-surface-variant)">Metode Pembayaran</span>
              <span class="text-[13px] font-bold text-right">{{ pesanan.metodePembayaran }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-[13px] text-(--color-on-surface-variant)">Tanggal Pembayaran</span>
              <span class="text-[13px] font-bold text-right">{{ formatTanggalInvoice(pesanan.dibuatPada) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Bayar</span>
              <span class="text-[13px] font-bold text-right">{{ rp(pesanan.biaya.totalTagihan) }}</span>
            </div>
          </div>
        </section>
  
        <footer class="fixed bottom-0 w-full z-40 bg-(--color-surface-0) border-t border-(--color-outline)/15 print:hidden">
          <div class="max-w-[430px] mx-auto px-4 py-3.5">
            <button
              type="button"
              class="w-full bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-transform"
              @click="cekStatus"
            >
              Cek Status Pesanan
            </button>
          </div>
        </footer>
        <InvoiceCetak :pesanan="pesanan" />
      </template>
    </div>
  </template>
</template>


<style>
/* Ukuran & margin kertas. Tidak scoped karena @page bersifat dokumen. */
@media print {
  @page {
    size: A4;
    margin: 14mm;
  }

  html,
  body {
    background: #fff;
  }
}
</style>
