<script setup lang="ts">
/**
 * Konfirmasi penawaran disetujui.
 *
 * Angka dan nomor pekerjaannya datang lewat query dari halaman persetujuan —
 * bukan dihitung ulang di sini. Yang mengikat adalah apa yang dicatat server
 * saat tombol ditekan, dan halaman ini hanya membacakannya kembali.
 *
 * Timeline-nya menandai satu langkah yang selesai dan sisanya belum: jujur
 * tentang apa yang benar-benar sudah terjadi, bukan mengarang kemajuan yang
 * belum ada.
 */
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'

const route = useRoute()
const router = useRouter()

const nomorPermintaan = String(route.params.nomor ?? '')
const nomorPekerjaan = computed(() => String(route.query.pekerjaan ?? '') || nomorPermintaan)
const total = computed(() => Number(route.query.total ?? 0))
const deposit = computed(() => Number(route.query.deposit ?? 0))

const TAHAP = [
  'Penawaran disetujui',
  'Deposit dibayar',
  'Material disiapkan',
  'Teknisi ditugaskan',
  'Jadwal dikonfirmasi',
  'Pekerjaan berlangsung',
  'Tes fungsi',
  'Serah terima & invoice',
]

/*
 * Pembayaran deposit dan penjadwalan belum punya layarnya sendiri. Daripada
 * memasang tombol yang tidak membawa ke mana-mana — bentuk kebohongan kecil
 * yang paling cepat menghabiskan kepercayaan — keduanya dinyatakan apa adanya.
 */
const pesan = ref<string | null>(null)

function belumTersedia(apa: string) {
  pesan.value = `${apa} belum bisa dilakukan dari aplikasi. Tim kami akan menghubungi Anda lewat nomor yang terdaftar.`
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-16">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Ke daftar tugas"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="router.push({ name: 'task-list' })"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Konfirmasi Persetujuan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-3.5">
      <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
        <span
          class="w-20 h-20 rounded-full bg-(--color-secondary-container) flex items-center justify-center mx-auto mb-4"
        >
          <Icon name="check-circle" class="w-10 h-10 text-(--color-on-secondary-container)" />
        </span>

        <h2 class="text-[19px] font-display font-extrabold leading-tight mb-1.5">
          Penawaran berhasil disetujui
        </h2>
        <p class="text-[12.5px] leading-snug text-(--color-on-surface-variant)">
          Tim BisaBersih akan menyiapkan teknisi dan material untuk pekerjaan Anda.
        </p>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3 pb-4 mb-4 border-b border-(--color-outline)/15">
          <div class="min-w-0">
            <p class="text-[10.5px] uppercase tracking-wider text-(--color-on-surface-variant)">
              No. Pekerjaan
            </p>
            <p class="text-[16px] font-extrabold tracking-wide">{{ nomorPekerjaan }}</p>
          </div>
          <span
            class="shrink-0 px-3 py-1 rounded-full bg-(--color-tertiary-container)/40 text-(--color-on-tertiary-container) text-[11px] font-extrabold"
          >
            Menunggu penjadwalan
          </span>
        </div>

        <div class="flex justify-between gap-3 text-[13px]">
          <span class="text-(--color-on-surface-variant)">Total disetujui</span>
          <span class="font-extrabold">{{ rupiah(total) }}</span>
        </div>
        <div v-if="deposit" class="mt-2 flex justify-between gap-3 text-[13px]">
          <span class="text-(--color-on-surface-variant)">Deposit</span>
          <span class="font-extrabold text-(--color-azure)">{{ rupiah(deposit) }}</span>
        </div>
      </section>

      <div class="grid grid-cols-2 gap-3">
        <button
          v-for="a in [
            { label: 'Bayar Deposit', ikon: 'wallet' },
            { label: 'Pilih Jadwal', ikon: 'calendar' },
          ]"
          :key="a.label"
          type="button"
          class="bg-(--color-surface-0) rounded-2xl p-4 flex flex-col items-center gap-2 active:scale-95 transition-transform"
          @click="belumTersedia(a.label)"
        >
          <span
            class="w-10 h-10 rounded-full bg-(--color-surface-container) flex items-center justify-center"
          >
            <Icon :name="a.ikon" class="w-5 h-5 text-(--color-azure)" />
          </span>
          <span class="text-[12px] font-bold">{{ a.label }}</span>
        </button>
      </div>

      <p
        v-if="pesan"
        role="status"
        class="px-1 text-[12px] leading-snug text-(--color-on-surface-variant)"
      >
        {{ pesan }}
      </p>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-4">Status pekerjaan</h3>

        <ol class="relative flex flex-col gap-4 pl-7">
          <span
            class="absolute left-[9px] top-2 bottom-2 w-0.5 bg-(--color-outline)/30"
            aria-hidden="true"
          ></span>

          <li v-for="(t, i) in TAHAP" :key="t" class="relative flex items-center">
            <span
              class="absolute -left-7 w-5 h-5 rounded-full flex items-center justify-center ring-4 ring-(--color-surface-0)"
              :class="i === 0 ? 'bg-(--color-secondary-container)' : 'bg-(--color-outline)/30'"
            >
              <Icon
                v-if="i === 0"
                name="check"
                class="w-3 h-3 text-(--color-on-secondary-container)"
              />
            </span>
            <span
              class="text-[12.5px]"
              :class="i === 0 ? 'font-extrabold' : 'text-(--color-on-surface-variant)'"
            >
              {{ t }}
            </span>
          </li>
        </ol>
      </section>

      <button
        type="button"
        class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
        @click="router.push({ name: 'task-list' })"
      >
        Lihat di Tugas Saya
        <Icon name="arrow-right" class="w-4 h-4" />
      </button>
    </main>
  </div>
</template>
