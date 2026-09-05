<script setup lang="ts">
/**
 * BisaKirim — status kiriman.
 *
 * Tahapnya datang dari server, dan kartu kurir tidak ditampilkan sebelum
 * benar-benar ada kurir yang ditugaskan.
 *
 * Kode terima paket hanya muncul DI SINI, di layar pemilik pesanan. Kode itu
 * gunanya memastikan paket diserahkan ke orang yang benar; menampilkannya di
 * tempat lain — apalagi mengirimkannya bersama nomor resi — menghapus gunanya.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import LottieIcon from '@/components/LottieIcon.vue'
import KirimStatusSkeleton from '@/components/skeleton/KirimStatusSkeleton.vue'
import animasiMencari from '@/assets/lottie/jemput-mencari-pengemudi.json'
import { ambilKiriman, type Kiriman } from '@/api/kirim'
import { pesanError } from '@/api/belanja'
import { TAHAP_KIRIM, rupiah } from '@/lib/kirim'

const route = useRoute()
const kembali = useKembali()
const nomor = String(route.params.nomor ?? '')

const data = ref<Kiriman | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)
let pewaktu: ReturnType<typeof setInterval> | null = null

const tahap = computed(() => data.value?.tahap ?? 'mencari')
const judul = computed(() => TAHAP_KIRIM[tahap.value] ?? TAHAP_KIRIM.mencari)
const selesai = computed(() => tahap.value === 'selesai')

async function muat() {
  try {
    data.value = await ambilKiriman(nomor)
    if (selesai.value && pewaktu) {
      clearInterval(pewaktu)
      pewaktu = null
    }
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(async () => {
  await muat()
  if (!selesai.value) pewaktu = setInterval(muat, 8000)
})

onBeforeUnmount(() => {
  if (pewaktu) clearInterval(pewaktu)
})
</script>

<template>
  <KirimStatusSkeleton v-if="memuat" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-16">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold">Kiriman</h1>
      </div>
    </header>

    <p
      v-if="galat && !data"
      role="alert"
      class="max-w-[430px] mx-auto px-4 pt-8 text-[13px] font-semibold text-(--color-error)"
    >
      {{ galat }}
    </p>

    <main v-else-if="data" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <section
        v-if="tahap === 'mencari'"
        class="bg-(--color-surface-0) rounded-2xl p-6 text-center"
      >
        <LottieIcon :data="animasiMencari" :size="140" class="mx-auto" />
        <h2 class="mt-2 text-[17px] font-display font-extrabold leading-tight">
          Mencari kurir terdekat untuk kamu…
        </h2>
        <p class="mt-1.5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
          Belum ada kurir yang ditugaskan, dan kamu belum ditagih apa pun.
        </p>
        <p class="mt-3 text-[11.5px] text-(--color-on-surface-variant)">
          Nomor kiriman {{ data.nomor }}
        </p>
      </section>

      <section v-else class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[16px] font-display font-extrabold leading-tight">{{ judul.judul }}</h2>
        <p class="text-[12px] leading-snug text-(--color-on-surface-variant) mt-0.5">
          {{ judul.keterangan }}
        </p>
      </section>

      <!-- Kode terima paket -->
      <section
        v-if="data.kode_terima"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-4"
      >
        <div class="flex-1">
          <p class="text-[12.5px] font-bold">Kode terima paket</p>
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
            Berikan ke penerima. Kurir hanya menyerahkan paket kalau kodenya cocok.
          </p>
        </div>
        <span class="text-[26px] font-display font-extrabold tracking-[0.2em] shrink-0">
          {{ data.kode_terima }}
        </span>
      </section>

      <!-- Rute -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex gap-3">
          <div class="flex flex-col items-center pt-1 shrink-0">
            <span class="w-3 h-3 rounded-full bg-(--color-azure)"></span>
            <span class="w-0.5 flex-1 my-1 bg-(--color-outline)/30"></span>
            <span class="w-3 h-3 rounded-full bg-orange-500"></span>
          </div>
          <div class="flex-1 min-w-0 flex flex-col gap-4">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-azure)">
                Diambil dari
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ data.ambil?.alamat }}</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                {{ data.ambil?.nama }} · {{ data.ambil?.telepon }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-orange-500">
                Diantar ke
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ data.antar?.alamat }}</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                {{ data.antar?.nama }} · {{ data.antar?.telepon }}
              </p>
            </div>
          </div>
        </div>

        <div
          class="mt-4 pt-4 border-t border-(--color-outline)/15 flex items-center justify-between gap-3 text-[12.5px]"
        >
          <span class="text-(--color-on-surface-variant)">
            {{ data.km?.toFixed(1).replace('.', ',') }} km · {{ data.isi }}
          </span>
          <span class="font-extrabold">{{ rupiah(data.total) }}</span>
        </div>
      </section>

      <!-- Rincian -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Rincian biaya</h3>
        <div class="flex flex-col gap-2 text-[13px]">
          <div v-for="b in data.baris" :key="b.label" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
            <span class="font-semibold">{{ rupiah(b.nilai) }}</span>
          </div>
          <div
            v-if="data.potongan > 0"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Voucher {{ data.promo?.kode }}</span>
            <span class="font-semibold">-{{ rupiah(data.potongan) }}</span>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
          <span class="text-[14px] font-extrabold">Total</span>
          <span class="text-[16px] font-extrabold">{{ rupiah(data.total) }}</span>
        </div>

        <p
          v-if="data.proteksi_plafon > 0"
          class="mt-3 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
        >
          Proteksi paket mengganti sampai {{ rupiah(data.proteksi_plafon) }}.
        </p>
      </section>
    </main>
  </div>
</template>
