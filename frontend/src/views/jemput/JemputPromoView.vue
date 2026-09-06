<script setup lang="ts">
/**
 * BisaJemput — katalog promo yang berdiri sendiri.
 *
 * Berbeda dari halaman voucher di layar pemesanan: yang itu menghitung potongan
 * rupiah dari tarif yang SEDANG dipilih, jadi ia hanya berarti kalau ada
 * perjalanan yang sedang disusun. Halaman ini dibuka dari mana saja — termasuk
 * dari perjalanan yang sedang berlangsung — dan di situ tidak ada tarif yang
 * bisa dijadikan dasar hitungan.
 *
 * Karena itu di sini TIDAK ADA ANGKA POTONGAN, hanya syaratnya. Angka rupiah
 * yang ditulis tanpa tarif adalah janji yang belum tentu berlaku untuk
 * perjalanan berikutnya, dan yang membacanya baru tahu saat menutup pesanan.
 */
import { onMounted, ref } from 'vue'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { voucherJemput, type VoucherJemput } from '@/api/jemput'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/jemput'

const kembali = useKembali()

const daftar = ref<VoucherJemput[]>([])
const perjalananPertama = ref(false)
const memuat = ref(true)
const galat = ref<string | null>(null)

onMounted(async () => {
  try {
    const h = await voucherJemput()
    daftar.value = h.voucher
    perjalananPertama.value = h.perjalanan_pertama
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
})

async function salin(kode: string) {
  try {
    await navigator.clipboard.writeText(kode)
    tersalin.value = kode
    setTimeout(() => (tersalin.value = null), 2000)
  } catch {
    // Papan klip ditolak peramban. Kodenya tetap terbaca di layar.
  }
}

const tersalin = ref<string | null>(null)
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-16">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Promo BisaJemput</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3">
      <p
        v-if="galat"
        role="alert"
        class="text-[13px] font-semibold text-(--color-error) text-center py-6"
      >
        {{ galat }}
      </p>

      <div v-else-if="memuat" class="flex flex-col gap-3">
        <div
          v-for="i in 4"
          :key="i"
          class="h-24 rounded-2xl bg-(--color-surface-0) animate-pulse"
        ></div>
      </div>

      <template v-else>
        <p class="text-[12px] leading-relaxed text-(--color-on-surface-variant) px-1">
          Potongannya dihitung saat kamu memilih kendaraan — di layar itu angkanya muncul lengkap
          beserta sisa tarif yang dibayar.
        </p>

        <article
          v-for="v in daftar"
          :key="v.kode"
          class="bg-(--color-surface-0) rounded-2xl p-4"
          :class="v.terpakai ? 'opacity-60' : ''"
        >
          <div class="flex items-start gap-3">
            <span
              class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
              :class="
                v.jenis === 'akuisisi'
                  ? 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
                  : 'bg-(--color-azure)/10 text-(--color-azure)'
              "
            >
              <Icon name="sparkle" class="w-5 h-5" />
            </span>

            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-display font-extrabold leading-tight">{{ v.nama }}</p>
              <p class="text-[12px] leading-snug text-(--color-on-surface-variant) mt-1">
                {{ v.deskripsi }}
              </p>
              <p class="text-[11.5px] text-(--color-on-surface-variant) mt-1.5">
                Minimal tarif {{ rupiah(v.minimum) }}
              </p>
            </div>
          </div>

          <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex items-center gap-2">
            <button
              type="button"
              class="flex-1 rounded-xl bg-(--color-surface-container) px-3 py-2.5 flex items-center justify-between gap-2 text-left active:scale-[0.99] transition-transform"
              @click="salin(v.kode)"
            >
              <span class="text-[13px] font-extrabold tracking-wide">{{ v.kode }}</span>
              <Icon
                :name="tersalin === v.kode ? 'check' : 'copy'"
                class="w-3.5 h-3.5 text-(--color-on-surface-variant)"
              />
            </button>

            <!--
              Promo sekali seumur hidup yang sudah lewat tetap ditampilkan
              beserta keterangannya. Menyembunyikannya membuat orang mengira
              promonya tidak pernah ada.
            -->
            <span
              v-if="v.terpakai"
              class="text-[11.5px] font-semibold text-(--color-on-surface-variant) shrink-0"
            >
              Sudah dipakai
            </span>
          </div>
        </article>
      </template>
    </main>
  </div>
</template>
