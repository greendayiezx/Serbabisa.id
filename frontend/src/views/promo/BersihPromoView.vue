<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import { usePromoBersihStore } from '@/stores/promoBersih'
import Icon from '@/components/icons/Icon.vue'
import {
  GRUP_VOUCHER,
  JAMINAN,
  type VoucherBersih,
} from '@/lib/promo/promoBersih'

const route = useRoute()
const router = useRouter()
const promoStore = usePromoBersihStore()

/**
 * Pakai promo ini lalu lanjut memesan.
 *
 * Pilihannya disimpan di store (bertahan lewat localStorage), bukan dikirim
 * sebagai query — halaman pemesanan bisa dibuka dari mana saja dan tetap tahu
 * promo mana yang sedang dipakai.
 */
function pakai(id: string) {
  promoStore.pilih(id)
  if (promoStore.dipilih !== id) return

  // Kalau pengguna datang dari halaman pemesanan, kembalikan ke sana persis —
  // termasuk pilihan layanan yang sedang dikerjakan. Kalau datang dari tempat
  // lain, mulai pesanan baru dengan layanan bawaan.
  const dari = route.query.dari
  if (typeof dari === 'string' && dari.startsWith('/') && !dari.startsWith('//')) {
    router.push(dari)
    return
  }
  router.push({ name: 'task-bersih-rumah', query: { layanan: 'general' } })
}

const kembali = useKembali()

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/**
 * Salin kode voucher.
 *
 * Kode promo hanya berguna kalau bisa ditempel di checkout, dan mengetik ulang
 * "BERSIHBARU40" di layar ponsel gampang salah. Penanda "Tersalin" dipegang per
 * id supaya hanya kode yang diketuk yang berubah.
 */
const kodeTersalin = ref<string | null>(null)

async function salin(v: VoucherBersih) {
  try {
    await navigator.clipboard.writeText(v.kode)
  } catch {
    // Clipboard bisa ditolak (izin/konteks tidak aman). Penanda tetap tampil
    // supaya ketukan terasa direspons; kodenya toh sudah terbaca di layar.
  }
  kodeTersalin.value = v.id
  window.setTimeout(() => {
    if (kodeTersalin.value === v.id) kodeTersalin.value = null
  }, 1600)
}

/** Ringkas nilai voucher jadi satu baris angka yang bisa dibaca cepat. */
function nilaiVoucher(v: VoucherBersih): string {
  if (v.potongan) return `Potong ${rp(v.potongan)}`
  if (v.cashbackPersen) return `Cashback ${v.cashbackPersen}% (maks ${rp(v.cashbackMaks ?? 0)})`
  return ''
}

function keDetail() {
  router.push({ name: 'task-bersih-detail', query: { category: 'bisabersih' } })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Promo BisaBersih</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-6">
      <!-- ================= Voucher per grup ================= -->
      <section v-for="grup in GRUP_VOUCHER" :key="grup.id">
        <h2 class="text-[13px] font-extrabold mb-2.5">{{ grup.judul }}</h2>

        <!-- Ajakan: kalimat penjualannya, bukan sekadar daftar angka -->
        <!-- Ajakan: SVG khusus untuk first-clean, div standar untuk grup lain -->
        <svg
          v-if="grup.id === 'first-clean'"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 400 340"
          width="100%"
          class="w-full h-auto block mb-2.5 rounded-2xl cursor-pointer active:scale-[0.99] transition-transform"
          @click="keDetail"
        >
          <defs>
            <!-- Azure background -->
            <linearGradient id="azureBg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#1E9BF0"/>
              <stop offset="100%" stop-color="#0A6FD6"/>
            </linearGradient>

            <!-- Lime -->
            <linearGradient id="lime" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#AAEE00"/>
              <stop offset="100%" stop-color="#7CBD00"/>
            </linearGradient>

            <!-- Gold -->
            <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#FFD600"/>
              <stop offset="100%" stop-color="#E09E00"/>
            </linearGradient>

            <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
              <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#063B78" flood-opacity=".3"/>
            </filter>
          </defs>

          <!-- CARD -->
          <rect x="0" y="0" width="400" height="340" rx="18" fill="url(#azureBg)"/>

          <!-- DECORATIVE BACKGROUND -->
          <circle cx="365" cy="40" r="65" fill="#FFFFFF" opacity=".07"/>
          <circle cx="20" cy="315" r="75" fill="#8BC53F" opacity=".09"/>

          <!-- Sparkles -->
          <path d="M355 70 L358 78 L366 81 L358 84 L355 92 L352 84 L344 81 L352 78Z" fill="#AAEE00" opacity=".95"/>
          <circle cx="330" cy="48" r="3.5" fill="#FFFFFF" opacity=".8"/>

          <!-- TOP ICON -->
          <g transform="translate(16 16)">
            <circle cx="24" cy="24" r="24" fill="#FFFFFF" opacity=".2"/>
            <path d="M11 26 L24 15 L37 26" fill="none" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 25V38H34V25" fill="#FFFFFF"/>
            <rect x="21" y="30" width="6" height="8" rx="1" fill="#1E9BF0"/>
            <path d="M38 10 L40 15 L45 17 L40 19 L38 24 L36 19 L31 17 L36 15Z" fill="#AAEE00"/>
          </g>

          <!-- TITLE & SUBTITLE -->
          <text x="74" y="34" font-family="Outfit, Inter, system-ui, sans-serif" font-size="19" font-weight="900" fill="#FFFFFF">
            Rumah Kinclong Pertama Kali?
          </text>
          <text x="74" y="59" font-family="Inter, system-ui, sans-serif" font-size="14.5" font-weight="800" fill="#FFEC94">
            Coba layanan BisaBersih hari ini
          </text>

          <!-- BENEFITS LIST -->
          <!-- Benefit 1 -->
          <g transform="translate(18 88)">
            <circle cx="13" cy="13" r="13" fill="url(#gold)"/>
            <path d="M7.5 13L11 16.5L18.5 8.5" fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="36" y="19" font-family="Inter, system-ui, sans-serif" font-size="15.5" font-weight="800" fill="#FFFFFF">
              Diskon sampai Rp60.000!
            </text>
          </g>

          <!-- Benefit 2 -->
          <g transform="translate(18 132)">
            <circle cx="13" cy="13" r="13" fill="#FFFFFF"/>
            <circle cx="13" cy="9" r="3.5" fill="#1E9BF0"/>
            <path d="M6.5 19.5 C6.5 14 19.5 14 19.5 19.5" fill="none" stroke="#1E9BF0" stroke-width="3" stroke-linecap="round"/>
            <text x="36" y="19" font-family="Inter, system-ui, sans-serif" font-size="15.5" font-weight="800" fill="#FFFFFF">
              Cleaner terverifikasi &amp; terlatih
            </text>
          </g>

          <!-- Benefit 3 -->
          <g transform="translate(18 176)">
            <circle cx="13" cy="13" r="13" fill="#FFFFFF"/>
            <path d="M13 5 L19.5 8.5 V13 C19.5 17.5 16.2 19.8 13 22 C9.8 19.8 6.5 17.5 6.5 13 V8.5Z" fill="#1E9BF0"/>
            <path d="M9.5 13L11.8 15.3L16.2 10.7" fill="none" stroke="#AAEE00" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="36" y="19" font-family="Inter, system-ui, sans-serif" font-size="15.5" font-weight="800" fill="#FFFFFF">
              Garansi re-clean gratis 24 jam
            </text>
          </g>

          <!-- Benefit 4 -->
          <g transform="translate(18 220)">
            <circle cx="13" cy="13" r="13" fill="#FFFFFF"/>
            <path d="M13 5 L19.5 8.5 V13 C19.5 17.5 16.2 19.8 13 22 C9.8 19.8 6.5 17.5 6.5 13 V8.5Z" fill="url(#lime)"/>
            <path d="M9 13 L13 9.5 L17 13 V17.5 H9Z" fill="#FFFFFF"/>
            <text x="36" y="19" font-family="Inter, system-ui, sans-serif" font-size="15.5" font-weight="800" fill="#FFFFFF">
              Asuransi kerusakan barang
            </text>
          </g>

          <!-- CTA BUTTON -->
          <g filter="url(#softShadow)">
            <rect x="16" y="270" width="368" height="52" rx="14" fill="#FFFFFF"/>
            <text x="200" y="303" text-anchor="middle" font-family="Outfit, Inter, system-ui, sans-serif" font-size="16" font-weight="900" fill="#0A326B">
              Pesan Sekarang
            </text>
            <path d="M288 296H305 M299 290L305 296L299 302" fill="none" stroke="#7CBD00" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
          </g>
        </svg>

        <!-- BisaPoints -->
        <svg
          v-if="grup.id === 'first-clean'"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 400 132"
          width="100%"
          class="w-full h-auto block mb-2.5 rounded-2xl"
        >
          <defs>
            <!-- BRAND GRADIENT -->
            <linearGradient id="brandBg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#0A326B"/>
              <stop offset="42%" stop-color="#1478E8"/>
              <stop offset="72%" stop-color="#3BBEB8"/>
              <stop offset="100%" stop-color="#C8FF00"/>
            </linearGradient>

            <!-- Lime -->
            <linearGradient id="lime" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#E0FF72"/>
              <stop offset="100%" stop-color="#8BC53F"/>
            </linearGradient>

            <!-- Gold -->
            <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#FFF2A6"/>
              <stop offset="45%" stop-color="#F4C542"/>
              <stop offset="100%" stop-color="#D99E16"/>
            </linearGradient>

            <!-- Animated shine -->
            <linearGradient id="shine" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
              <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".55"/>
              <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
            </linearGradient>

            <filter id="shadow" x="-20%" y="-30%" width="140%" height="160%">
              <feDropShadow dx="0" dy="3" stdDeviation="4" flood-color="#062A5A" flood-opacity=".22"/>
            </filter>

            <clipPath id="cardClip">
              <rect x="0" y="0" width="400" height="132" rx="16"/>
            </clipPath>
          </defs>

          <!-- CARD -->
          <rect x="0" y="0" width="400" height="132" rx="16" fill="url(#brandBg)" filter="url(#shadow)"/>

          <!-- DECORATIVE BRAND BLOBS -->
          <circle cx="370" cy="15" r="55" fill="#C8FF00" opacity=".12">
            <animate attributeName="r" values="50;58;50" dur="3s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".08;.18;.08" dur="3s" repeatCount="indefinite"/>
          </circle>

          <circle cx="20" cy="120" r="48" fill="#F4C542" opacity=".10">
            <animate attributeName="cy" values="120;116;120" dur="3.5s" repeatCount="indefinite"/>
          </circle>

          <!-- ANIMATED SHIMMER -->
          <g clip-path="url(#cardClip)" opacity=".28">
            <rect x="-100" y="-20" width="55" height="180" fill="url(#shine)" transform="rotate(18 0 0)">
              <animate attributeName="x" from="-120" to="470" dur="3.5s" repeatCount="indefinite"/>
            </rect>
          </g>

          <!-- TROPHY -->
          <g transform="translate(14 14)" filter="url(#shadow)">
            <!-- Floating -->
            <animateTransform attributeName="transform" type="translate" values="14 14;14 11.5;14 14" dur="2s" repeatCount="indefinite"/>

            <!-- Icon circle -->
            <circle cx="20" cy="20" r="20" fill="#FFFFFF" opacity=".18"/>

            <!-- Trophy -->
            <path d="M12 10 H28 V17 C28 23 24.5 27 20 27 C15.5 27 12 23 12 17 Z" fill="url(#gold)"/>

            <!-- Handles -->
            <path d="M12 13H8 C7 13 7 15 8 17 C9 20 11 21 14 21" fill="none" stroke="#E2B52F" stroke-width="3" stroke-linecap="round"/>
            <path d="M28 13H32 C33 13 33 15 32 17 C31 20 29 21 26 21" fill="none" stroke="#E2B52F" stroke-width="3" stroke-linecap="round"/>

            <!-- Stem -->
            <path d="M20 27V33" stroke="#E2B52F" stroke-width="3" stroke-linecap="round"/>

            <!-- Base -->
            <path d="M14 35H26" stroke="#E2B52F" stroke-width="4" stroke-linecap="round"/>

            <!-- Trophy highlight -->
            <path d="M16 12V18" stroke="#FFFBE0" stroke-width="2" stroke-linecap="round"/>

            <!-- Sparkle -->
            <path d="M36 5 L38 10 L43 12 L38 14 L36 19 L34 14 L29 12 L34 10Z" fill="#C8FF00">
              <animate attributeName="opacity" values=".25;1;.25" dur="1.4s" repeatCount="indefinite"/>
              <animateTransform attributeName="transform" type="scale" values="1;1.18;1" dur="1.4s" repeatCount="indefinite" additive="sum"/>
            </path>
          </g>

          <!-- TITLE -->
          <text x="62" y="30" font-family="Arial, Helvetica, sans-serif" font-size="13px" font-weight="800" fill="#FFFFFF">
            BisaPoints
          </text>

          <!-- DESCRIPTION -->
          <text x="14" y="65" font-family="Arial, Helvetica, sans-serif" font-size="12px" font-weight="500" fill="#FFFFFF">
            1 poin tiap Rp1.000 transaksi · 1.000 poin = voucher Rp10.000
          </text>

          <!-- SUBSCRIPTION BADGE -->
          <g transform="translate(14 82)">
            <rect x="0" y="0" width="372" height="34" rx="10" fill="#FFFFFF" opacity=".18"/>

            <!-- Double points circle -->
            <circle cx="19" cy="17" r="10" fill="url(#lime)">
              <animate attributeName="r" values="9;10.5;9" dur="1.8s" repeatCount="indefinite"/>
            </circle>

            <text x="19" y="21" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="11px" font-weight="800" fill="#0A326B">
              2×
            </text>

            <text x="38" y="21" font-family="Arial, Helvetica, sans-serif" font-size="12px" font-weight="600" fill="#FFFFFF">
              Double points untuk pelanggan langganan.
            </text>
          </g>

          <!-- SMALL FLOATING SPARKLES -->
          <circle cx="345" cy="105" r="3" fill="#C8FF00">
            <animate attributeName="cy" values="105;101;105" dur="2s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".4;1;.4" dur="2s" repeatCount="indefinite"/>
          </circle>

          <circle cx="365" cy="93" r="2" fill="#F4C542">
            <animate attributeName="opacity" values=".2;1;.2" dur="1.5s" repeatCount="indefinite"/>
          </circle>
        </svg>

        <div
          v-else
          class="rounded-2xl p-4 mb-2.5 bg-(--color-surface-0) border border-(--color-outline)/40"
        >
          <p class="font-display font-extrabold text-[14px] mb-1.5 text-(--color-on-surface)">
            {{ grup.ajakan.judul }}
          </p>
          <ul class="flex flex-col gap-1">
            <li
              v-for="(baris, i) in grup.ajakan.baris"
              :key="i"
              class="text-[12px] leading-snug flex items-start gap-1.5 text-(--color-on-surface-variant)"
            >
              <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-azure)" />
              <span>{{ baris }}</span>
            </li>
          </ul>
          <button
            type="button"
            class="mt-3 inline-block text-[12px] font-bold px-4 py-2 rounded-full active:scale-95 transition-transform bg-(--color-azure) text-white"
            @click="keDetail"
          >
            {{ grup.ajakan.cta }}
          </button>
        </div>

        <!-- Kode -->
        <div class="flex flex-col gap-2">
          <div
            v-for="v in grup.voucher"
            :key="v.id"
            class="bg-(--color-surface-0) rounded-2xl border-2 p-3.5 transition-colors"
            :class="promoStore.dipilih === v.id ? 'border-(--color-azure)' : 'border-(--color-outline)/40'"
          >
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <h3 class="text-[13px] font-extrabold leading-snug">{{ v.judul }}</h3>
                <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                  Min. transaksi {{ rp(v.minTransaksi) }}
                  <template v-if="v.layanan"> · {{ v.layanan }}</template>
                  <template v-if="v.periode"> · {{ v.periode }}</template>
                  <template v-if="v.berlakuHari"> · berlaku {{ v.berlakuHari }} hari</template>
                </p>
                <p class="text-[12px] font-bold text-(--color-azure) mt-1">{{ nilaiVoucher(v) }}</p>
              </div>

              <button
                type="button"
                class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-dashed border-(--color-azure)/60 bg-(--color-primary-container) active:scale-95 transition-transform"
                :aria-label="`Salin kode ${v.kode}`"
                @click="salin(v)"
              >
                <span class="text-[11px] font-extrabold tracking-wide text-(--color-on-primary-container)">
                  {{ kodeTersalin === v.id ? 'Tersalin!' : v.kode }}
                </span>
                <Icon
                  :name="kodeTersalin === v.id ? 'check' : 'receipt'"
                  class="w-3.5 h-3.5 text-(--color-on-primary-container)"
                />
              </button>
            </div>

            <button
              type="button"
              class="mt-2.5 w-full rounded-full py-2 text-[12px] font-bold transition-colors active:scale-[0.98]"
              :class="
                promoStore.dipilih === v.id
                  ? 'bg-(--color-lime) text-[#33430b]'
                  : 'bg-(--color-azure) text-white'
              "
              @click="pakai(v.id)"
            >
              {{ promoStore.dipilih === v.id ? 'Dipakai · ketuk untuk lepas' : 'Pakai promo ini' }}
            </button>
          </div>
        </div>


      </section>



      <!-- ================= Jaminan ================= -->
      <section>
        <h2 class="text-[13px] font-extrabold mb-2.5">Jaminan Kinclong 100%</h2>
        <div class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/40 p-4">
          <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mb-3">
            Bukan diskon, tapi bagian yang paling menentukan orang jadi memesan atau tidak.
          </p>
          <ul class="flex flex-col gap-2">
            <li v-for="(j, i) in JAMINAN" :key="i" class="flex items-start gap-2">
              <Icon name="check-circle" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-azure)" />
              <span class="text-[12.5px] leading-snug">{{ j }}</span>
            </li>
          </ul>
          <button
            type="button"
            class="mt-3.5 w-full bg-(--color-azure) text-white rounded-full py-3 text-[13px] font-bold active:scale-95 transition-transform"
            @click="keDetail"
          >
            Pesan Sekarang
          </button>
        </div>
      </section>
    </main>
  </div>
</template>
