<script setup lang="ts">
/**
 * Katalog promo satu layanan.
 *
 * Satu halaman untuk beberapa layanan, bukan satu berkas per layanan: yang
 * berbeda hanya KATALOGNYA, sementara susunan halaman, cara menandai promo yang
 * belum memenuhi syarat, dan cara mengembalikan kode terpilih persis sama.
 * Menyalinnya per layanan berarti tiga tempat yang harus diperbaiki tiap kali
 * salah satunya berubah.
 *
 * Layanan diambil dari `:layanan` di URL. Konteks tagihan datang lewat query —
 * `nilai` (total sekarang) dan `unit` (khusus Servis AC) — supaya halaman ini
 * bisa menunjukkan promo mana yang SUDAH bisa dipakai, bukan sekadar mendaftar.
 *
 * Promo yang dipilih dikembalikan lewat query `?promo=KODE` ke halaman asal;
 * halaman itu yang memutuskan menerapkannya. Dengan begitu halaman ini tidak
 * perlu tahu store mana pun.
 */
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'
import { PROMO_DEEP, hitungPromoDeep } from '@/lib/promo/promoBersihDeep'
import { PROMO_AC, hitungPromoAC } from '@/lib/promo/promoAC'

const route = useRoute()
const router = useRouter()

interface KartuPromo {
  kode: string
  judul: string
  ringkas: string
  minTransaksi: number
  syarat: string[]
  /** Potongan pada tagihan yang sedang disusun. */
  potongan: number
  bisa: boolean
  alasan: string | null
}

const layanan = computed(() => String(route.params.layanan ?? 'deep'))

const JUDUL: Record<string, string> = {
  deep: 'Promo Deep Cleaning',
  ac: 'Promo Servis AC',
}

const judulHalaman = computed(() => JUDUL[layanan.value] ?? 'Promo')

/**
 * Nilai tagihan yang sedang disusun.
 *
 * Kalau tidak dikirim, halaman ini TIDAK menebak: semua promo ditampilkan tanpa
 * penanda "bisa dipakai", dan syaratnya diperiksa di halaman pemesanan.
 */
const nilai = computed(() => {
  const n = Number(route.query.nilai)
  return Number.isFinite(n) && n > 0 ? n : null
})

const unit = computed(() => {
  const n = Number(route.query.unit)
  return Number.isFinite(n) && n > 0 ? n : 1
})

const kodeDipakai = computed(() => String(route.query.promo ?? '').toUpperCase() || null)

const daftar = computed<KartuPromo[]>(() => {
  const n = nilai.value

  if (layanan.value === 'ac') {
    return PROMO_AC.map((p) => {
      const hasil = hitungPromoAC(p, n ?? p.minTransaksi, unit.value)
      return {
        kode: p.kode,
        judul: p.judul,
        ringkas: p.ringkas,
        minTransaksi: p.minTransaksi,
        syarat: p.syarat,
        potongan: hasil.potongan,
        bisa: n === null ? true : hasil.berlaku,
        alasan:
          n === null
            ? null
            : (hasil.alasan ?? (hasil.kurang > 0 ? `Kurang ${rupiah(hasil.kurang)} lagi` : null)),
      }
    })
  }

  return PROMO_DEEP.map((p) => {
    const hasil = hitungPromoDeep(p, n ?? p.minTransaksi)
    return {
      kode: p.kode,
      judul: p.judul,
      ringkas: p.ringkas,
      minTransaksi: p.minTransaksi,
      syarat: p.syarat,
      potongan: hasil.potongan,
      bisa: n === null ? true : hasil.berlaku,
      alasan: n === null || hasil.kurang === 0 ? null : `Kurang ${rupiah(hasil.kurang)} lagi`,
    }
  })
})

const adaYangBisa = computed(() => daftar.value.some((d) => d.bisa))

/** Halaman asal, dibawa lewat `?dari=`. */
function kembali(promo?: string) {
  const dari = String(route.query.dari ?? '')

  if (dari.startsWith('/')) {
    router.replace(promo ? `${dari}?promo=${encodeURIComponent(promo)}` : dari)
    return
  }

  router.back()
}

function pakai(d: KartuPromo) {
  // Penjagaan kedua: tombolnya sudah dinonaktifkan, tapi promo yang belum
  // memenuhi syarat tidak boleh masuk lewat jalan lain.
  if (!d.bisa) return
  kembali(d.kode)
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
          @click="kembali()"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">{{ judulHalaman }}</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <p v-if="nilai" class="text-[12px] text-(--color-on-surface-variant) leading-snug">
        Dihitung untuk tagihan {{ rupiah(nilai) }}
        <template v-if="layanan === 'ac'"> · {{ unit }} unit</template>.
        Promo yang belum memenuhi syarat tetap ditampilkan beserta kekurangannya.
      </p>

      <p
        v-if="nilai && !adaYangBisa"
        class="rounded-2xl bg-(--color-surface-0) p-4 text-[12.5px] leading-snug text-(--color-on-surface-variant)"
      >
        Belum ada promo yang bisa dipakai untuk tagihan sebesar ini. Tambah unit atau
        layanan, lalu kembali ke sini.
      </p>

      <article
        v-for="d in daftar"
        :key="d.kode"
        class="rounded-2xl bg-(--color-surface-0) p-5 border-2 transition-colors"
        :class="kodeDipakai === d.kode ? 'border-(--color-azure)' : 'border-transparent'"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[14px] font-extrabold tracking-wide">{{ d.kode }}</p>
            <p class="text-[12.5px] font-bold text-(--color-on-surface) mt-0.5">{{ d.judul }}</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant) leading-snug mt-0.5">
              {{ d.ringkas }}
            </p>
          </div>

          <span
            class="shrink-0 text-[13px] font-extrabold whitespace-nowrap"
            :class="d.bisa ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
          >
            {{ d.potongan ? `Hemat ${rupiah(d.potongan)}` : rupiah(0) }}
          </span>
        </div>

        <!-- Syarat ditulis lengkap: di sinilah tempatnya, bukan di layar pemesanan. -->
        <ul class="mt-3 flex flex-col gap-1.5">
          <li
            v-for="(s, i) in d.syarat"
            :key="i"
            class="flex items-start gap-2 text-[11.5px] leading-snug text-(--color-on-surface-variant)"
          >
            <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-on-surface-variant)" />
            {{ s }}
          </li>
        </ul>

        <p v-if="d.alasan" class="mt-3 text-[11.5px] font-semibold text-(--color-error)">
          {{ d.alasan }}
        </p>

        <button
          type="button"
          :disabled="!d.bisa"
          class="mt-4 w-full h-11 rounded-full text-[13.5px] font-extrabold transition-transform active:scale-[0.98] disabled:opacity-40 disabled:active:scale-100"
          :class="
            d.bisa
              ? 'bg-(--color-azure) text-white'
              : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
          "
          @click="pakai(d)"
        >
          {{ kodeDipakai === d.kode ? 'Dipakai' : d.bisa ? 'Pakai Promo' : 'Belum memenuhi syarat' }}
        </button>
      </article>
    </main>
  </div>
</template>
