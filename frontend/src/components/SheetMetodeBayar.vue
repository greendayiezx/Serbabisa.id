<script setup lang="ts">
/**
 * Lembar pilih metode pembayaran.
 *
 * Diangkat dari layar checkout BisaBelanja supaya BisaKirim memakai lembar yang
 * SAMA, bukan salinannya. Bedanya penting: daftar metode ini menyimpan aturan
 * — mana yang saldonya kurang, mana yang belum diaktivasi — dan dua salinan
 * berarti aturan itu diperbaiki di satu tempat lalu tetap salah di tempat lain,
 * tanpa ada yang memberi tanda.
 *
 * Yang dikirim ke server adalah `id`-nya, bukan labelnya; lihat lib/metodeBayar.
 */
import { computed } from 'vue'
import Icon from '@/components/icons/Icon.vue'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import logoBni from '@/assets/logo_bni.svg'
import logoBri from '@/assets/logo_bri.svg'
import logoDana from '@/assets/logo_dana.svg'
import logoMandiri from '@/assets/logo_mandiri.svg'
import logoOvo from '@/assets/logo_ovo.svg'
import logoQris from '@/assets/logo_qris.svg'
import logoBca from '@/assets/LOGO-BCA.png'
import logoLinkaja from '@/assets/LOGO_LINKAJA.png'
import logoSpay from '@/assets/LOGO-SPAY.png'

const props = defineProps<{
  /**
   * Tagihan yang harus ditutup metode ini.
   *
   * Dipakai untuk menentukan saldo mana yang cukup — jadi lembarnya menolak
   * pilihan yang pasti gagal di gerbang pembayaran, bukan membiarkannya
   * terpilih lalu gagal setelah orang menekan Bayar.
   */
  total: number
}>()

/** Metode terpilih. */
const metode = defineModel<MetodeId>({ required: true })
/** Lembarnya terbuka atau tidak. */
const buka = defineModel<boolean>('buka', { required: true })

interface Metode {
  id: MetodeId
  label: string
  desc?: string
  /** Tidak bisa dipilih (saldo kurang / belum aktivasi). */
  nonaktif?: boolean
  /** Teks aksi di kanan, mis. "Top Up" atau "Aktivasi". */
  aksi?: string
}

/** Saldo dompet internal. Belum ada API-nya, jadi masih 0. */
const SALDO_SERBABISA = 0
const SALDO_GOPAY = 5823

const grupMetode = computed<{ judul: string; daftar: Metode[] }[]>(() => [
  {
    judul: 'Pilihan Pembayaran',
    daftar: [
      {
        id: 'balance',
        label: LABEL_METODE.balance,
        desc:
          SALDO_SERBABISA >= props.total
            ? `Saldo Rp${SALDO_SERBABISA.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_SERBABISA.toLocaleString('id-ID')})`,
        nonaktif: SALDO_SERBABISA < props.total,
        aksi: SALDO_SERBABISA < props.total ? 'Top Up' : undefined,
      },
      {
        id: 'gopay',
        label: LABEL_METODE.gopay,
        desc:
          SALDO_GOPAY >= props.total
            ? `Saldo Rp${SALDO_GOPAY.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_GOPAY.toLocaleString('id-ID')})`,
        nonaktif: SALDO_GOPAY < props.total,
      },
      { id: 'qris', label: LABEL_METODE.qris, desc: 'Scan pakai aplikasi bank atau e-wallet apa pun' },
    ],
  },
  {
    judul: 'E-Wallet',
    daftar: [
      { id: 'ovo', label: LABEL_METODE.ovo, aksi: 'Aktivasi' },
      { id: 'shopeepay', label: LABEL_METODE.shopeepay, aksi: 'Aktivasi' },
      { id: 'dana', label: LABEL_METODE.dana },
      { id: 'linkaja', label: LABEL_METODE.linkaja },
    ],
  },
  {
    judul: 'Virtual Account',
    daftar: [
      { id: 'bca', label: LABEL_METODE.bca, desc: 'Transfer 24 jam' },
      { id: 'bni', label: LABEL_METODE.bni, desc: 'Transfer 24 jam' },
      { id: 'bri', label: LABEL_METODE.bri, desc: 'Transfer 24 jam' },
      { id: 'mandiri', label: LABEL_METODE.mandiri, desc: 'Transfer 24 jam' },
    ],
  },
  {
    judul: 'Bayar di Tempat',
    daftar: [{ id: 'tunai', label: LABEL_METODE.tunai, desc: 'Siapkan uang pas ya, biar gak repot kembalian' }],
  },
])

function pilihMetode(m: Metode) {
  // Pengaman: metode nonaktif tidak boleh terpilih walau tombolnya tertekan.
  if (m.nonaktif || m.aksi === 'Aktivasi') return
  metode.value = m.id
  buka.value = false
}
</script>

<template>
  <Teleport to="body">
    <div v-if="buka" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
      <Transition
        appear
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
      >
        <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
      </Transition>

      <Transition
        appear
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-to-class="translate-y-full"
      >
        <div class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
          <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"></div>

          <div class="flex items-center justify-between px-5 py-3.5 shrink-0">
            <h3 class="font-extrabold text-[17px]">Mau bayar pakai apa?</h3>
            <button
              type="button"
              aria-label="Tutup"
              class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
              @click="buka = false"
            >
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>

          <div class="overflow-y-auto flex-1 pb-6">
            <div v-for="g in grupMetode" :key="g.judul">
              <p class="px-5 pt-4 pb-1.5 text-[13px] font-extrabold text-(--color-on-surface)">{{ g.judul }}</p>
              <button
                v-for="m in g.daftar"
                :key="m.id"
                type="button"
                class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors"
                :class="[
                  m.nonaktif || m.aksi === 'Aktivasi' ? 'cursor-default' : 'active:bg-(--color-surface-container)',
                  metode === m.id ? 'bg-(--color-azure)/8' : '',
                ]"
                @click="pilihMetode(m)"
              >
                <span class="w-9 h-9 flex items-center justify-center shrink-0">
                  <!-- Serbabisa Balance -->
                  <svg v-if="m.id === 'balance'" class="w-8 h-8" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="#1E9BF0" />
                    <rect x="6" y="10" width="20" height="13" rx="3" fill="#fff" />
                    <rect x="6" y="10" width="20" height="4" rx="2" fill="#0B67B0" />
                    <circle cx="21.5" cy="18.5" r="2.5" fill="#8BC53F" />
                  </svg>
                  <svg v-else-if="m.id === 'gopay'" class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                    <rect width="24" height="24" rx="12" fill="#00AED6" />
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#fff" />
                  </svg>
                  <img v-else-if="m.id === 'qris'" :src="logoQris" alt="QRIS" class="h-5 w-auto object-contain" />
                  <img v-else-if="m.id === 'ovo'" :src="logoOvo" alt="OVO" class="h-6 w-auto object-contain" />
                  <img v-else-if="m.id === 'shopeepay'" :src="logoSpay" alt="ShopeePay" class="h-9 w-auto object-contain scale-110" />
                  <img v-else-if="m.id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
                  <img v-else-if="m.id === 'linkaja'" :src="logoLinkaja" alt="LinkAja" class="h-6 w-auto object-contain" />
                  <img v-else-if="m.id === 'bca'" :src="logoBca" alt="BCA" class="h-6 w-auto object-contain" />
                  <img v-else-if="m.id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
                  <img v-else-if="m.id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
                  <img v-else-if="m.id === 'mandiri'" :src="logoMandiri" alt="Mandiri" class="h-6 w-auto object-contain" />
                  <svg v-else class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none">
                    <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2" />
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                    <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                </span>

                <span class="flex-1 min-w-0">
                  <span
                    class="block text-[14px] font-bold truncate"
                    :class="m.nonaktif || m.aksi === 'Aktivasi' ? 'text-(--color-outline)' : 'text-(--color-on-surface)'"
                  >
                    {{ m.label }}
                  </span>
                  <span v-if="m.desc" class="block text-[11.5px] text-(--color-on-surface-variant) truncate">
                    {{ m.desc }}
                  </span>
                </span>

                <span v-if="m.aksi" class="text-[12.5px] font-bold text-(--color-azure) shrink-0">{{ m.aksi }}</span>
                <span
                  v-else-if="metode === m.id"
                  class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
                >
                  <Icon name="check" class="w-3 h-3 text-white" />
                </span>
              </button>
              <div class="h-2 bg-(--color-surface-container)"></div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Teleport>
</template>
