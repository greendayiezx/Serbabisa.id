<script setup lang="ts">
/**
 * Promo BisaBersih Kantor — halaman terpisah dari promo rumah.
 *
 * Katalognya beda mekanika (minimum transaksi bertingkat, kontrak, referral
 * antar perusahaan), jadi tidak menumpang BersihPromoView.
 *
 * Manfaat yang BUKAN kode — diskon langganan, survei gratis, program loyalitas —
 * ditampilkan sebagai informasi di bawah, bukan sebagai voucher yang bisa
 * "dipakai". Diskon langganan khususnya sudah melekat di harga; menawarkannya
 * lagi sebagai kode berarti memotong dua kali.
 */
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { usePromoBersihKantorStore } from '@/stores/promoBersihKantor'
import {
  GRUP_VOUCHER_KANTOR,
  PROGRAM_LOYALITAS,
  SURVEI_GRATIS,
  SYARAT_LANGGANAN,
  TABEL_LANGGANAN,
  semuaVoucherKantor,
  type VoucherKantor,
} from '@/lib/promo/promoBersihKantor'

const route = useRoute()
const router = useRouter()
const promoStore = usePromoBersihKantorStore()

/** Empat promo peluncuran awal, ditonjolkan di atas. */
const unggulan = computed(() => semuaVoucherKantor().filter((v) => v.unggulan))

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/** Nilai promo diringkas jadi satu baris yang bisa dibaca cepat. */
function nilaiVoucher(v: VoucherKantor): string {
  if (v.potongan && v.hadiahPengajak) {
    return `Potong ${rp(v.potongan)} + voucher ${rp(v.hadiahPengajak)} untuk pengajak`
  }
  if (v.diskonPersen) return `Diskon ${v.diskonPersen}% (maks ${rp(v.diskonMaks ?? 0)})`
  if (v.potongan) return `Potong ${rp(v.potongan)}`
  return ''
}

/**
 * Nilai tagihan yang sedang disusun, dibawa lewat query `?nilai=`.
 *
 * Tanpa itu halaman ini tidak tahu tagihan pengguna, jadi tidak boleh menebak:
 * kalau nilainya tidak dikirim, semua promo dibiarkan bisa dipilih dan syarat
 * minimumnya diperiksa di halaman pemesanan seperti sebelumnya.
 */
const nilaiTransaksi = computed(() => {
  const n = Number(route.query.nilai)
  return Number.isFinite(n) && n > 0 ? n : null
})

const tahuNilai = computed(() => nilaiTransaksi.value !== null)

/** Kekurangan untuk memakai satu voucher. 0 = sudah bisa dipakai. */
function kurangUntuk(v: VoucherKantor): number {
  if (!tahuNilai.value) return 0
  return Math.max(0, v.minTransaksi - nilaiTransaksi.value!)
}

function bisaDipakai(v: VoucherKantor): boolean {
  return kurangUntuk(v) === 0
}

/**
 * Kembali ke tempat asal.
 *
 * Jalur divalidasi: harus relatif ("/..."), dan bukan "//" yang justru
 * mengarah ke host lain.
 */
function kembali() {
  const dari = route.query.dari
  if (typeof dari === 'string' && dari.startsWith('/') && !dari.startsWith('//')) {
    router.push(dari)
    return
  }
  router.push({ name: 'task-bersih-kantor' })
}

/** Pakai promo lalu kembali memesan. Ketuk lagi untuk melepasnya. */
function pakai(v: VoucherKantor) {
  // Penjagaan kedua: tombolnya sudah dinonaktifkan, tapi promo yang belum
  // memenuhi syarat tidak boleh bisa masuk lewat jalan lain.
  if (!bisaDipakai(v)) return

  promoStore.pilih(v.id)
  if (promoStore.dipilih === v.id) kembali()
}

/* ---------------- Salin kode ---------------- */
const kodeTersalin = ref<string | null>(null)

async function salin(v: VoucherKantor) {
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

/* ---------------- Syarat yang bisa dibuka-tutup ---------------- */
const syaratTerbuka = ref<string | null>(null)
function toggleSyarat(id: string) {
  syaratTerbuka.value = syaratTerbuka.value === id ? null : id
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Promo Kantor</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-6">
      <!-- Promo yang sedang dipakai -->
      <div
        v-if="promoStore.voucher()"
        class="flex items-center gap-3 rounded-2xl border border-(--color-azure) bg-(--color-azure)/8 px-4 py-3"
      >
        <Icon name="check-circle" class="w-5 h-5 text-(--color-azure) shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-[12.5px] font-bold truncate">
            {{ promoStore.voucher()?.judul }} sedang dipakai
          </p>
          <p class="text-[11px] text-(--color-on-surface-variant)">
            Kode {{ promoStore.voucher()?.kode }}
          </p>
        </div>
        <button
          type="button"
          class="shrink-0 text-[12px] font-bold text-(--color-error) px-3 py-1 rounded-full border border-(--color-error) active:scale-95 transition-transform"
          @click="promoStore.lepas()"
        >
          Lepas
        </button>
      </div>

      <!-- Empat promo peluncuran -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-1">Promo BisaBersih Kantor</h2>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-3 leading-snug">
          Empat promo utama: menarik pelanggan baru, menaikkan transaksi pertama, menjaga
          langganan, dan tumbuh lewat referral.
        </p>
        <div class="grid grid-cols-2 gap-2.5">
          <button
            v-for="v in unggulan"
            :key="v.id"
            type="button"
            :disabled="!bisaDipakai(v)"
            class="rounded-2xl p-3.5 text-left border-2 bg-(--color-surface-0) transition-all"
            :class="
              promoStore.dipilih === v.id
                ? 'border-(--color-azure) bg-(--color-primary-container) active:scale-[0.98]'
                : 'border-(--color-outline)/20'
            "
            @click="pakai(v)"
          >
            <span
              class="inline-block text-[10px] font-extrabold tracking-wide bg-(--color-surface-container) rounded px-1.5 py-0.5"
            >
              {{ v.kode }}
            </span>
            <span class="block text-[12.5px] font-bold mt-1.5 leading-snug">{{ v.judul }}</span>
            <span
              class="block text-[11px] font-bold mt-1"
              :class="bisaDipakai(v) ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
            >
              {{ bisaDipakai(v) ? nilaiVoucher(v) : `Kurang ${rp(kurangUntuk(v))}` }}
            </span>
          </button>
        </div>
      </section>

      <!-- Katalog lengkap per grup -->
      <section v-for="grup in GRUP_VOUCHER_KANTOR" :key="grup.id">
        <h2 class="text-[14px] font-display font-extrabold">{{ grup.judul }}</h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-2.5 leading-snug">
          {{ grup.tujuan }}
        </p>

        <div class="flex flex-col gap-2.5">
          <div
            v-for="v in grup.voucher"
            :key="v.id"
            class="rounded-2xl border-2 p-4 bg-(--color-surface-0) transition-colors"
            :class="promoStore.dipilih === v.id ? 'border-(--color-azure)' : 'border-(--color-outline)/20'"
          >
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <h3 class="text-[13.5px] font-extrabold leading-snug">{{ v.judul }}</h3>
                <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5 leading-snug">
                  {{ v.ringkas }}
                </p>
                <p class="text-[12px] font-bold text-(--color-azure) mt-1.5">{{ nilaiVoucher(v) }}</p>
                <p
                  class="text-[11px] mt-0.5"
                  :class="bisaDipakai(v) ? 'text-(--color-on-surface-variant)' : 'font-semibold text-(--color-error)'"
                >
                  Min. transaksi {{ rp(v.minTransaksi) }}
                  <template v-if="!bisaDipakai(v)"> · kurang {{ rp(kurangUntuk(v)) }}</template>
                  <template v-if="v.periode"> · {{ v.periode }}</template>
                  <template v-if="v.kuota"> · kuota {{ v.kuota }} kantor</template>
                </p>
                <p v-if="v.bonus" class="text-[11.5px] font-bold text-(--color-on-secondary-container) mt-1">
                  {{ v.bonus }}
                </p>
              </div>

              <button
                type="button"
                class="shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-dashed border-(--color-azure)/60 bg-(--color-primary-container) active:scale-95 transition-transform"
                :aria-label="`Salin kode ${v.kode}`"
                @click="salin(v)"
              >
                <span class="text-[10.5px] font-extrabold tracking-wide text-(--color-on-primary-container)">
                  {{ kodeTersalin === v.id ? 'Tersalin!' : v.kode }}
                </span>
                <Icon
                  :name="kodeTersalin === v.id ? 'check' : 'receipt'"
                  class="w-3.5 h-3.5 text-(--color-on-primary-container)"
                />
              </button>
            </div>

            <!-- Syarat -->
            <button
              type="button"
              class="mt-2.5 flex items-center gap-1 text-[11.5px] font-bold text-(--color-on-surface-variant) active:scale-95 transition-transform"
              @click="toggleSyarat(v.id)"
            >
              Syarat &amp; ketentuan
              <Icon
                name="chevron-down"
                class="w-3.5 h-3.5 transition-transform"
                :class="syaratTerbuka === v.id ? 'rotate-180' : ''"
              />
            </button>
            <ul v-if="syaratTerbuka === v.id" class="mt-1.5 flex flex-col gap-1">
              <li
                v-for="(s, i) in v.syarat"
                :key="i"
                class="flex items-start gap-1.5 text-[11px] text-(--color-on-surface-variant) leading-snug"
              >
                <span class="mt-1.5 w-1 h-1 rounded-full bg-(--color-outline) shrink-0"></span>
                <span>{{ s }}</span>
              </li>
            </ul>

            <button
              type="button"
              :disabled="!bisaDipakai(v)"
              class="mt-3 w-full rounded-full py-2.5 text-[12.5px] font-bold transition-colors"
              :class="
                !bisaDipakai(v)
                  ? 'bg-(--color-surface-container) text-(--color-on-surface-variant) cursor-not-allowed'
                  : promoStore.dipilih === v.id
                    ? 'bg-(--color-lime) text-[#33430b] active:scale-[0.98]'
                    : 'bg-(--color-azure) text-white active:scale-[0.98]'
              "
              @click="pakai(v)"
            >
              <template v-if="!bisaDipakai(v)">
                Belum memenuhi minimum transaksi
              </template>
              <template v-else>
                {{ promoStore.dipilih === v.id ? 'Dipakai · ketuk untuk lepas' : 'Pakai promo ini' }}
              </template>
            </button>
          </div>
        </div>
      </section>

      <!-- Diskon langganan: melekat di harga, bukan kode -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold">Diskon Langganan</h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5 mb-3 leading-snug">
          Sudah otomatis terpakai begitu memilih frekuensi di halaman pemesanan — tidak perlu
          kode, dan tidak digabung dengan voucher di atas.
        </p>
        <div class="flex flex-col divide-y divide-(--color-outline)/12">
          <div
            v-for="t in TABEL_LANGGANAN"
            :key="t.label"
            class="flex items-center justify-between py-2.5"
          >
            <span class="text-[12.5px]">{{ t.label }}</span>
            <span class="text-[13px] font-extrabold text-(--color-azure)">{{ t.diskon }}</span>
          </div>
        </div>
        <ul class="mt-3 flex flex-col gap-1">
          <li
            v-for="(s, i) in SYARAT_LANGGANAN"
            :key="i"
            class="flex items-start gap-1.5 text-[11px] text-(--color-on-surface-variant) leading-snug"
          >
            <span class="mt-1.5 w-1 h-1 rounded-full bg-(--color-outline) shrink-0"></span>
            <span>{{ s }}</span>
          </li>
        </ul>
      </section>

      <!-- Survei gratis -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start gap-3">
          <span
            class="w-10 h-10 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) flex items-center justify-center shrink-0"
          >
            <Icon name="clipboard" class="w-5 h-5" />
          </span>
          <div class="min-w-0">
            <h2 class="text-[14px] font-display font-extrabold">{{ SURVEI_GRATIS.judul }}</h2>
            <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5 leading-snug">
              {{ SURVEI_GRATIS.ringkas }}
            </p>
          </div>
        </div>
        <ul class="mt-3 flex flex-col gap-1.5">
          <li
            v-for="(m, i) in SURVEI_GRATIS.manfaat"
            :key="i"
            class="flex items-start gap-2 text-[12px] text-(--color-on-surface-variant)"
          >
            <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-azure)" />
            <span class="leading-snug">{{ m }}</span>
          </li>
        </ul>
      </section>

      <!-- Program pelanggan lama -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-2.5">Untuk Pelanggan Lama</h2>
        <ul class="flex flex-col gap-1.5">
          <li
            v-for="(p, i) in PROGRAM_LOYALITAS"
            :key="i"
            class="flex items-start gap-2 text-[12px] text-(--color-on-surface-variant)"
          >
            <Icon name="star" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-gold)" />
            <span class="leading-snug">{{ p }}</span>
          </li>
        </ul>
      </section>

      <p class="text-[11px] text-(--color-on-surface-variant) leading-snug text-center px-2">
        Maksimal satu voucher per invoice. Harga normal, nilai diskon, dan harga setelah promo
        selalu ditampilkan sebelum kamu mengirim permintaan.
      </p>
    </main>
  </div>
</template>
