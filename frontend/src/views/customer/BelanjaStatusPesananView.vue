<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { usePesananStore, type StatusPesanan, type Pesanan } from '@/stores/pesanan'
import { rentangJam } from '@/lib/invoice'
import { useSkeleton } from '@/composables/useSkeleton'
import StatusPesananSkeleton from '@/components/skeleton/StatusPesananSkeleton.vue'

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

const LABEL_STATUS: Record<StatusPesanan, { judul: string; catatan: string }> = {
  diproses: { judul: 'Pesanan lagi diproses', catatan: 'Kami sedang mempersiapkan pesananmu' },
  dibelanjakan: { judul: 'Mitra sedang belanja', catatan: 'Barangmu sedang dipilih di gerai' },
  diantar: { judul: 'Pesanan dalam perjalanan', catatan: 'Mitra sedang menuju alamatmu' },
  selesai: { judul: 'Pesanan selesai', catatan: 'Terima kasih sudah belanja' },
}

const status = computed(() => LABEL_STATUS[pesanan.value?.status ?? 'diproses'])
const rincianTerbuka = ref(true)

/** Kode referral statis; pembuatan kode per pengguna belum ada. */
const KODE_REFERRAL = 'BISA15'
const kodeTersalin = ref(false)
const invoiceTersalin = ref(false)

async function salin(teks: string, penanda: 'kode' | 'invoice') {
  try {
    await navigator.clipboard.writeText(teks)
  } catch {
    // Clipboard diblokir (mis. bukan HTTPS) — diamkan, tidak ada yang rusak.
    return
  }
  if (penanda === 'kode') {
    kodeTersalin.value = true
    setTimeout(() => (kodeTersalin.value = false), 1600)
  } else {
    invoiceTersalin.value = true
    setTimeout(() => (invoiceTersalin.value = false), 1600)
  }
}

function kembali() {
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
  <StatusPesananSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
      <header class="sticky top-0 z-30 bg-(--color-surface-0)">
        <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
          <button
            type="button"
            aria-label="Kembali ke beranda"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            @click="kembali"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
          <div class="flex-1 text-center">
            <h1 class="text-[17px] font-extrabold leading-tight">Status Pesanan</h1>
            <p v-if="pesanan" class="text-[11px] text-(--color-on-surface-variant)">#{{ pesanan.nomor }}</p>
          </div>
          <span class="text-[13px] font-bold text-(--color-azure) shrink-0">Bantuan</span>
        </div>
      </header>
  
      <div v-if="!pesanan" class="max-w-[430px] mx-auto px-4 py-16 text-center">
        <p class="text-4xl mb-3">📦</p>
        <p class="text-[14px] font-bold mb-1">Pesanan tidak ditemukan</p>
        <button
          type="button"
          class="mt-4 h-11 px-6 rounded-full bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
          @click="kembali"
        >
          Kembali ke beranda
        </button>
      </div>
  
      <main v-else class="max-w-[430px] mx-auto flex flex-col gap-2.5">
        <!-- Estimasi tiba + ilustrasi mitra belanja (animasi CSS, bukan Lottie). -->
        <section class="relative bg-gradient-to-b from-[#2E9DF7] to-[#1E7FE0] pt-5 overflow-hidden">
          <p class="text-center text-[13px] font-semibold text-white/90">Estimasi tiba</p>
          <p class="text-center text-[30px] font-extrabold text-white leading-tight">
            {{ rentangJam(pesanan.estimasiMulai, pesanan.estimasiSelesai) }}
          </p>
          <svg viewBox="0 0 400 300" class="w-full h-auto block" xmlns="http://www.w3.org/2000/svg">
            <!-- Tanah -->
            <path
              d="M45 260 C120 255 270 255 355 260"
              fill="none"
              stroke="#8BC53F"
              stroke-width="7"
              stroke-linecap="round"
            />
  
            <!-- Troli -->
            <g class="sp-cart">
              <path
                d="M238 125 H354 L338 203 H253 Z"
                fill="#FFFFFF"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linejoin="round"
              />
              <path d="M238 125H354" stroke="#2196F3" stroke-width="9" stroke-linecap="round" />
              <path
                d="M253 203 H338 L347 218 H259"
                fill="none"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <circle cx="272" cy="232" r="11" fill="#0A326B" />
              <circle cx="326" cy="232" r="11" fill="#0A326B" />
              <circle cx="272" cy="232" r="4" fill="#FFFFFF" />
              <circle cx="326" cy="232" r="4" fill="#FFFFFF" />
  
              <!-- Belanjaan -->
              <g class="sp-items">
                <path d="M270 118 H292 L289 155 H273Z" fill="#8BC53F" />
                <path
                  d="M276 119 C276 109 286 109 286 119"
                  fill="none"
                  stroke="#0A326B"
                  stroke-width="4"
                  stroke-linecap="round"
                />
                <rect x="299" y="126" width="28" height="28" rx="3" fill="#F4C542" />
                <path d="M299 137H327" stroke="#DDAA22" stroke-width="3" />
                <path d="M334 124 H346 V154 H332 V130Z" fill="#2196F3" />
                <rect x="335" y="119" width="9" height="7" rx="2" fill="#0A326B" />
              </g>
            </g>
  
            <!-- Orang -->
            <g class="sp-person">
              <g class="sp-leg-back">
                <path d="M180 194 L166 229 L148 252" class="sp-line" />
                <path
                  d="M144 250 C137 251 132 256 132 260 H157 C158 255 153 251 144 250Z"
                  fill="#0A326B"
                />
              </g>
  
              <g class="sp-leg-front">
                <path d="M181 194 L196 229 L211 251" class="sp-line" />
                <path
                  d="M207 248 C214 249 219 253 220 259 H195 C195 254 199 250 207 248Z"
                  fill="#0A326B"
                />
              </g>
  
              <path
                d="M156 112 C159 102 169 97 180 100 L196 106 C205 109 210 119 208 129 L198 178 C196 188 189 196 179 196 H169 C158 194 152 185 153 175 Z"
                class="sp-body"
              />
              <path
                d="M158 143 C171 148 187 148 201 143"
                fill="none"
                stroke="#8BC53F"
                stroke-width="6"
                stroke-linecap="round"
              />
              <path
                d="M177 119 C171 119 168 122 168 126 C168 130 171 132 177 133 C182 134 184 136 184 139 C184 143 181 145 176 145 H170"
                fill="none"
                stroke="#FFFFFF"
                stroke-width="4"
                stroke-linecap="round"
              />
  
              <circle cx="177" cy="78" r="27" class="sp-skin" />
              <path
                d="M151 75 C150 58 162 47 178 48 C193 48 203 58 202 72 C195 65 188 62 178 63 C168 63 160 67 151 75Z"
                fill="#0A326B"
              />
              <circle cx="202" cy="80" r="7" class="sp-skin" />
              <circle cx="190" cy="78" r="2.8" fill="#0A326B" />
              <path
                d="M195 82L199 84L195 86"
                fill="none"
                stroke="#9A5F43"
                stroke-width="2"
                stroke-linecap="round"
              />
              <path
                d="M188 91 Q194 96 199 91"
                fill="none"
                stroke="#0A326B"
                stroke-width="2.5"
                stroke-linecap="round"
              />
  
              <path d="M159 119 L143 153 L170 166" class="sp-line" />
              <path d="M199 119 L216 151 L241 143" class="sp-line" />
              <circle cx="241" cy="143" r="7" class="sp-skin" />
              <path
                d="M241 143 L254 125"
                fill="none"
                stroke="#0A326B"
                stroke-width="7"
                stroke-linecap="round"
              />
            </g>
  
            <!-- Garis gerak -->
            <g fill="none" stroke="#8BC53F" stroke-width="4" stroke-linecap="round" opacity=".8">
              <path d="M67 205H91" />
              <path d="M55 218H74" />
              <path d="M72 232H96" />
            </g>
          </svg>
        </section>
  
        <!-- Referral -->
        <section class="bg-[#1B1749] px-4 py-3.5">
          <div class="flex items-center gap-3">
            <p class="flex-1 min-w-0 text-[12px] font-semibold text-white leading-snug">
              Dapatkan voucher belanja! Ajak teman pakai kode referral kamu:
            </p>
            <button
              type="button"
              class="shrink-0 h-9 px-4 rounded-lg bg-white text-[13px] font-extrabold text-(--color-on-surface) flex items-center gap-2 active:scale-95 transition-transform"
              @click="salin(KODE_REFERRAL, 'kode')"
            >
              {{ kodeTersalin ? 'Tersalin!' : KODE_REFERRAL }}
              <Icon name="send" class="w-3.5 h-3.5 text-(--color-azure)" />
            </button>
          </div>
        </section>
  
        <!-- Status -->
        <section class="bg-(--color-surface-0) px-4 py-4">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="flex items-center gap-1.5 text-left"
              @click="rincianTerbuka = !rincianTerbuka"
            >
              <span class="text-[15px] font-extrabold">{{ status.judul }}</span>
              <Icon
                name="chevron-down"
                class="w-4 h-4 text-(--color-on-surface-variant) transition-transform"
                :class="rincianTerbuka ? '' : '-rotate-90'"
              />
            </button>
            <span
              class="ml-auto shrink-0 text-[11.5px] font-bold text-(--color-azure) bg-(--color-azure)/10 rounded-full px-3 py-1 flex items-center gap-1"
            >
              <Icon name="clock" class="w-3 h-3" />
              25 - 45 menit
            </span>
          </div>
          <p v-if="rincianTerbuka" class="text-[12px] text-(--color-on-surface-variant) mt-1">
            {{ status.catatan }}
          </p>
  
          <div class="mt-3 bg-(--color-secondary-container)/40 rounded-xl p-3.5 flex items-start gap-3">
            <span class="text-lg shrink-0">🛡️</span>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-extrabold">Pesanan Anda Terlindungi Jaminan Garansi</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug mt-0.5">
                Cek pesanan saat tiba. Kalau ada kendala produk, hubungi CS 1x24 jam untuk dana kembali.
              </p>
            </div>
            <Icon name="chevron-right" class="w-4 h-4 text-(--color-outline) shrink-0 mt-1" />
          </div>
        </section>
  
        <!-- Lokasi tujuan -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[16px] font-extrabold mb-3">Lokasi Tujuan</h2>
          <p class="text-[13.5px] font-bold">{{ pesanan.toko || 'Toko belum dipilih' }}</p>
          <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mt-1">{{ pesanan.alamat }}</p>
          <div v-if="pesanan.catatan" class="flex items-start gap-2 mt-3">
            <Icon name="clipboard" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0 mt-0.5" />
            <p class="text-[12.5px] text-(--color-on-surface-variant)">{{ pesanan.catatan }}</p>
          </div>
          <p class="text-[13.5px] font-bold mt-4">Detail Penerima</p>
          <p class="text-[12.5px] text-(--color-on-surface-variant) mt-0.5">
            {{ pesanan.penerima }}<span v-if="pesanan.telepon">, {{ pesanan.telepon }}</span>
          </p>
        </section>
  
        <!-- Detil pesanan -->
        <section class="bg-(--color-surface-0) px-4 py-5">
          <h2 class="text-[16px] font-extrabold mb-4">Detil Pesanan</h2>
  
          <div class="flex items-center justify-between gap-3">
            <span class="text-[13px] text-(--color-on-surface-variant)">Metode Pembayaran</span>
            <span class="text-[13px] font-bold">{{ pesanan.metodePembayaran }}</span>
          </div>
  
          <div class="flex items-center gap-3 mt-3.5 pt-3.5 border-t border-(--color-outline)/12">
            <span class="flex-1 min-w-0 text-[12.5px] font-semibold break-all">{{ pesanan.invoice }}</span>
            <button
              type="button"
              class="shrink-0 text-[12px] font-bold text-(--color-azure) flex items-center gap-1 active:scale-95 transition-transform"
              @click="salin(pesanan.invoice, 'invoice')"
            >
              <Icon name="clipboard" class="w-3.5 h-3.5" />
              {{ invoiceTersalin ? 'Tersalin!' : 'Salin Invoice' }}
            </button>
          </div>
  
          <div class="flex items-center gap-3 mt-3.5 pt-3.5 border-t border-(--color-outline)/12">
            <span class="flex-1 text-[13px] font-bold">Pengiriman</span>
            <span class="text-[12.5px] font-semibold text-(--color-on-surface-variant) flex items-center gap-1">
              <Icon name="clock" class="w-3.5 h-3.5" />
              25 - 45 menit
            </span>
          </div>
  
          <ul class="mt-3 flex flex-col divide-y divide-(--color-outline)/12">
            <li v-for="item in pesanan.items" :key="item.id" class="py-3 flex items-start gap-3">
              <span class="text-[12.5px] font-bold text-(--color-on-surface-variant) shrink-0 w-7">
                {{ item.qty }}x
              </span>
              <span class="flex-1 min-w-0 text-[12.5px] leading-snug">{{ item.nama }}</span>
              <span class="text-[12.5px] font-bold shrink-0">
                {{ item.harga != null ? rp(item.harga * item.qty) : '—' }}
              </span>
            </li>
          </ul>
  
          <div class="mt-3 pt-3.5 border-t border-(--color-outline)/12 flex flex-col gap-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Harga Barang</span>
              <span class="text-[13px] font-bold">{{ rp(pesanan.biaya.totalBarang) }}</span>
            </div>
            <div v-if="pesanan.biaya.potongan" class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Potongan Promo</span>
              <span class="text-[13px] font-bold text-(--color-secondary)">
                &minus;{{ rp(pesanan.biaya.potongan) }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Biaya Layanan</span>
              <span class="text-[13px] font-bold">{{ rp(pesanan.biaya.biayaLayanan) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[13px] text-(--color-on-surface-variant)">Total Ongkir</span>
              <span class="text-[13px] font-bold flex items-center gap-1.5">
                <span
                  v-if="pesanan.biaya.ongkirGratis"
                  class="text-[11.5px] font-semibold text-(--color-on-surface-variant) line-through decoration-(--color-error)"
                >
                  {{ rp(pesanan.biaya.ongkirNormal) }}
                </span>
                <span :class="pesanan.biaya.ongkirGratis ? 'text-(--color-secondary)' : ''">
                  {{ pesanan.biaya.ongkirGratis ? 'Gratis' : rp(pesanan.biaya.ongkir) }}
                </span>
              </span>
            </div>
            <div class="pt-3 mt-1 border-t border-(--color-outline)/12 flex items-center justify-between">
              <span class="text-[14px] font-extrabold">Total</span>
              <span class="text-[15px] font-extrabold">{{ rp(pesanan.biaya.totalTagihan) }}</span>
            </div>
            <p
              v-if="pesanan.biaya.cashback"
              class="text-[11.5px] font-bold text-(--color-secondary) text-right"
            >
              Cashback {{ rp(pesanan.biaya.cashback) }} sudah masuk ke saldomu
            </p>
          </div>
        </section>
      </main>
    </div>
  </template>
</template>

<style scoped>
/*
 * Animasi ilustrasi "mitra sedang belanja".
 *
 * Kelasnya diberi awalan sp- dan ditaruh di <style scoped>, bukan di dalam
 * <defs> SVG. Nama asli seperti .body dan .line akan menjadi CSS global begitu
 * ada di dalam komponen Vue, dan itu cukup untuk mengacaukan seluruh aplikasi.
 */
.sp-body {
  fill: #2196f3;
}
.sp-skin {
  fill: #f2b58d;
}
.sp-line {
  fill: none;
  stroke: #0a326b;
  stroke-width: 8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sp-leg-back {
  transform-box: fill-box;
  transform-origin: 50% 0%;
  animation: sp-leg-back 0.65s ease-in-out infinite alternate;
}
.sp-leg-front {
  transform-box: fill-box;
  transform-origin: 50% 0%;
  animation: sp-leg-front 0.65s ease-in-out infinite alternate;
}
.sp-person {
  transform-box: fill-box;
  transform-origin: 50% 100%;
  animation: sp-walk 0.65s ease-in-out infinite;
}
.sp-cart {
  animation: sp-cart 0.65s ease-in-out infinite alternate;
}
.sp-items {
  animation: sp-items 0.65s ease-in-out infinite alternate;
}

@keyframes sp-leg-back {
  from {
    transform: rotate(18deg);
  }
  to {
    transform: rotate(-18deg);
  }
}
@keyframes sp-leg-front {
  from {
    transform: rotate(-18deg);
  }
  to {
    transform: rotate(18deg);
  }
}
@keyframes sp-walk {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3px);
  }
}
@keyframes sp-cart {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(3px);
  }
}
@keyframes sp-items {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(-2px);
  }
}

/* Animasi ini berjalan tanpa henti — hormati setelan "kurangi gerakan". */
@media (prefers-reduced-motion: reduce) {
  .sp-leg-back,
  .sp-leg-front,
  .sp-person,
  .sp-cart,
  .sp-items {
    animation: none;
  }
}
</style>
