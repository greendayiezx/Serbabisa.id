<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { useLocationStore } from '@/stores/location'
import { useAngkutDraftStore } from '@/stores/angkutDraft'
import logoBni from '@/assets/logo_bni.svg'
import logoBri from '@/assets/logo_bri.svg'
import logoDana from '@/assets/logo_dana.svg'
import BisaAngkutNavbar from '@/components/angkut/BisaAngkutNavbar.vue'
import AngkutLoadingTruck from '@/components/angkut/AngkutLoadingTruck.vue'

const router = useRouter()
const locationStore = useLocationStore()
const angkutDraftStore = useAngkutDraftStore()

const draft = computed(() => angkutDraftStore.draft)
const alamat = computed(() => locationStore.draft?.alamat ?? 'Alamat belum dipilih')

const penerimaName = computed(() => draft.value?.namaPenerima || 'Penerima')
const penerimaPhone = computed(() => draft.value?.teleponPenerima ?? '')

const beratLabel = computed(() => {
  const b = draft.value?.beratTotal
  return typeof b === 'number' && b > 0 ? `${b} kg` : 'Belum diisi'
})

const hargaLayananFormatted = computed(() => `Rp${(draft.value?.total ?? 0).toLocaleString('id-ID')}`)
const hargaPerlindungan = computed(() => draft.value?.protectionPrice ?? 0)
const totalBayar = computed(() => (draft.value?.total ?? 0) + hargaPerlindungan.value)

interface PaymentMethod {
  id: 'gopay' | 'dana' | 'linkaja' | 'bni' | 'bri' | 'cash' | 'card'
  label: string
  desc: string
  bg: string
  icon: string
}

const paymentMethods: PaymentMethod[] = [
  { id: 'gopay', label: 'GoPay', desc: 'Bayar pakai saldo GoPay', bg: 'bg-[#00AED6]', icon: 'gopay' },
  { id: 'dana', label: 'DANA', desc: 'Bayar pakai saldo DANA', bg: 'bg-[#008CEE]', icon: 'dana' },
  { id: 'linkaja', label: 'LinkAja', desc: 'Bayar pakai saldo LinkAja', bg: 'bg-[#ED1C24]', icon: 'linkaja' },
  { id: 'bni', label: 'BNI Virtual Account', desc: 'Transfer Bank BNI 24 jam', bg: 'bg-[#F15A24]', icon: 'bni' },
  { id: 'bri', label: 'BRI Virtual Account', desc: 'Transfer Bank BRI 24 jam', bg: 'bg-[#00529C]', icon: 'bri' },
  { id: 'cash', label: 'Tunai', desc: 'Siapkan uang pas ya, biar gak usah repot kembalian', bg: 'bg-(--color-lime)', icon: 'cash' },
  { id: 'card', label: 'Kartu Kredit/Debit', desc: 'Visa, Mastercard, JCB', bg: 'bg-indigo-500', icon: 'card' },
]

const selectedPayment = ref<PaymentMethod['id']>('cash')
const paymentSheetOpen = ref(false)
const currentPayment = computed(() => paymentMethods.find((p) => p.id === selectedPayment.value)!)

function choosePayment(id: PaymentMethod['id']) {
  selectedPayment.value = id
  paymentSheetOpen.value = false
}


const submitting = ref(false)
const searchingDriver = ref(false)
const searchStep = ref(1)

function kirimPesanan() {
  if (submitting.value) return
  submitting.value = true
  searchingDriver.value = true
  searchStep.value = 1

  setTimeout(() => {
    searchStep.value = 2
  }, 2500)

  setTimeout(() => {
    locationStore.clearDraft()
    angkutDraftStore.clearDraft()
    router.push({ name: 'home' })
  }, 4200)
}

</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-56">
    <!-- Header -->
    <BisaAngkutNavbar />


    <div class="px-5 flex flex-col gap-3.5">
      <!-- Address -->
      <div class="bg-(--color-surface-0) rounded-(--radius-card) shadow-(--shadow-lift) border border-(--color-outline)/40 p-4 flex items-start gap-3">
        <span class="w-6 h-6 flex items-center justify-center shrink-0 mt-0.5">
          <Icon name="pin" class="w-5 h-5 text-(--color-azure)" />
        </span>
        <div class="flex-1 min-w-0 flex flex-col gap-0.5">
          <h3 class="text-sm font-bold text-(--color-on-surface)">{{ penerimaName }}</h3>
          <p v-if="penerimaPhone" class="text-xs font-bold text-black">{{ penerimaPhone }}</p>
          <p class="text-[12.5px] text-(--color-on-surface-variant) mt-0.5">{{ alamat }}</p>
        </div>
      </div>

      <!-- Order details with ultra-premium SVG icons (no circular backgrounds) -->
      <div class="bg-(--color-surface-0) rounded-(--radius-card) shadow-(--shadow-lift) border border-(--color-outline)/40 p-4 flex flex-col gap-4">
        <!-- 1. LAYANAN SVG ICON -->
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-sky-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="1" y="4" width="14" height="11" rx="2" stroke="currentColor" stroke-width="2"/>
              <path d="M15 8h4.5a1.5 1.5 0 011.2.6l2.3 3.1A1.5 1.5 0 0123 12.6V15a1 1 0 01-1 1h-2" stroke="currentColor" stroke-width="2"/>
              <circle cx="6" cy="17" r="2.5" stroke="currentColor" stroke-width="2"/>
              <circle cx="17" cy="17" r="2.5" stroke="currentColor" stroke-width="2"/>
              <path d="M5 8h6M5 11h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" opacity="0.6"/>
            </svg>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide">Layanan</p>
            <p class="text-sm font-bold text-(--color-on-surface) truncate">{{ draft?.vehicleLabel || 'Pickup Bak' }} &middot; {{ draft?.deliveryLabel || 'Instant Hemat' }}</p>
          </div>
        </div>

        <!-- 2. DETAIL BARANG SVG ICON -->
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-amber-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M2 17l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M12 12v10" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2" opacity="0.5"/>
            </svg>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide">Detail Barang</p>
            <p class="text-sm font-semibold text-(--color-on-surface) truncate">{{ draft?.catatan || 'Belum diisi' }}</p>
          </div>
        </div>

        <!-- 3. PERLINDUNGAN SVG ICON -->
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-emerald-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide">Perlindungan</p>
            <p class="text-sm font-semibold text-(--color-on-surface) truncate">
              {{ draft?.protectionLabel || 'Tidak dipilih' }}
              <span v-if="hargaPerlindungan" class="text-(--color-on-surface-variant) font-normal">&middot; Rp{{ hargaPerlindungan.toLocaleString('id-ID') }}</span>
            </p>
          </div>
        </div>

        <!-- 4. UKURAN & BERAT SVG ICON -->
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-indigo-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2v3M9 3.5l1.5 2M15 3.5l-1.5 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <circle cx="12" cy="14" r="7.5" stroke="currentColor" stroke-width="2"/>
              <path d="M12 14l3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <circle cx="12" cy="14" r="1.5" fill="currentColor"/>
            </svg>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide">Ukuran &amp; Berat</p>
            <p class="text-sm font-semibold text-(--color-on-surface)">{{ beratLabel }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom sheet: selected service + payment -->
    <div class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0) rounded-t-3xl shadow-(--shadow-float) border-t border-(--color-outline)/40 px-5 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] flex flex-col gap-3.5">
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 flex items-center justify-center shrink-0">
          <img v-if="draft?.vehicleImage" :src="draft.vehicleImage" alt="" class="w-full h-full object-contain" />
          <Icon v-else name="truck" class="w-6 h-6 text-(--color-on-surface)" />
        </span>
        <div class="flex-1 min-w-0">
          <h3 class="text-sm font-bold text-(--color-on-surface) truncate">{{ draft?.deliveryLabel || 'Instant Hemat' }} &middot; {{ draft?.vehicleLabel || 'Pickup Bak' }}</h3>
          <p class="text-[12px] text-(--color-on-surface-variant)">{{ beratLabel }}</p>
        </div>
        <span class="text-base font-extrabold text-(--color-on-surface) shrink-0">{{ hargaLayananFormatted }}</span>
      </div>

      <div class="border-t border-(--color-outline)/40"></div>

      <!-- 5. PAYMENT METHOD SELECTION BUTTON -->
      <button type="button" class="flex items-center gap-3 -mx-1 px-1 py-0.5 rounded-xl active:bg-(--color-surface-container) transition-colors" @click="paymentSheetOpen = true">
        <span class="w-8 h-8 flex items-center justify-center shrink-0">
          <svg v-if="currentPayment.id === 'gopay'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="12" fill="#00AED6"/>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#FFFFFF"/>
          </svg>
          <img v-else-if="currentPayment.id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
          <svg v-else-if="currentPayment.id === 'linkaja'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="12" fill="#ED1C24"/>
            <path d="M6.5 16.5L12 7.5L17.5 16.5H14.2L12 12.8L9.8 16.5H6.5Z" fill="#FFFFFF"/>
            <circle cx="12" cy="10" r="1.2" fill="#ED1C24"/>
          </svg>
          <img v-else-if="currentPayment.id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
          <img v-else-if="currentPayment.id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
          <svg v-else-if="currentPayment.id === 'cash'" class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
            <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <svg v-else-if="currentPayment.id === 'card'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="5" width="20" height="14" rx="3" fill="#1E293B" stroke="#475569" stroke-width="1"/>
            <circle cx="9" cy="12" r="3" fill="#EB001B"/>
            <circle cx="13" cy="12" r="3" fill="#F79E1B" fill-opacity="0.9"/>
            <rect x="4.5" y="8" width="3" height="2.5" rx="0.5" fill="#FACC15"/>
          </svg>
        </span>
        <div class="flex-1 min-w-0 text-left">
          <p class="text-[13px] font-bold text-(--color-on-surface)">{{ currentPayment.label }}</p>
        </div>
        <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
      </button>

      <button
        type="button"
        class="w-full flex items-center justify-between gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] px-6 py-3.5 min-h-11 shadow-(--shadow-lift) disabled:opacity-50"
        :disabled="submitting"
        @click="kirimPesanan"
      >
        <span>Kirim {{ draft?.deliveryLabel || 'Instant Hemat' }}</span>
        <span class="flex items-center gap-1.5">
          Rp{{ totalBayar.toLocaleString('id-ID') }}
          <Icon name="chevron-right" class="w-4 h-4" />
        </span>
      </button>
    </div>

    <!-- Payment method sheet -->
    <Teleport to="body">
      <div v-if="paymentSheetOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <Transition
          appear
          enter-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          leave-active-class="transition-opacity duration-200"
          leave-to-class="opacity-0"
        >
          <div class="absolute inset-0 bg-black/45" @click="paymentSheetOpen = false"></div>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-y-full"
          leave-active-class="transition-transform duration-200 ease-in"
          leave-to-class="translate-y-full"
        >
          <div class="relative w-full md:w-96 max-h-[80dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
            <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"></div>

            <div class="flex items-center justify-between px-5 py-3.5 border-b border-(--color-outline)/40 shrink-0">
              <h3 class="font-extrabold text-[15px]">Pilih metode pembayaran</h3>
              <button type="button" class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center" @click="paymentSheetOpen = false">
                <Icon name="x" class="w-4 h-4" />
              </button>
            </div>

            <div class="overflow-y-auto flex-1 p-4 flex flex-col gap-1">
              <button
                v-for="method in paymentMethods"
                :key="method.id"
                type="button"
                class="w-full flex items-center gap-3 px-2.5 py-3 rounded-2xl text-left transition-colors"
                :class="selectedPayment === method.id ? 'bg-(--color-primary-container)/50' : 'hover:bg-(--color-surface-container)'"
                @click="choosePayment(method.id)"
              >
                <span class="w-8 h-8 flex items-center justify-center shrink-0">
                  <svg v-if="method.id === 'gopay'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="12" fill="#00AED6"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#FFFFFF"/>
                  </svg>
                  <img v-else-if="method.id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
                  <svg v-else-if="method.id === 'linkaja'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="12" fill="#ED1C24"/>
                    <path d="M6.5 16.5L12 7.5L17.5 16.5H14.2L12 12.8L9.8 16.5H6.5Z" fill="#FFFFFF"/>
                    <circle cx="12" cy="10" r="1.2" fill="#ED1C24"/>
                  </svg>
                  <img v-else-if="method.id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
                  <img v-else-if="method.id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
                  <svg v-else-if="method.id === 'cash'" class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                  <svg v-else-if="method.id === 'card'" class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="5" width="20" height="14" rx="3" fill="#1E293B" stroke="#475569" stroke-width="1"/>
                    <circle cx="9" cy="12" r="3" fill="#EB001B"/>
                    <circle cx="13" cy="12" r="3" fill="#F79E1B" fill-opacity="0.9"/>
                    <rect x="4.5" y="8" width="3" height="2.5" rx="0.5" fill="#FACC15"/>
                  </svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-(--color-on-surface)">{{ method.label }}</p>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">{{ method.desc }}</p>
                </div>
                <span
                  class="w-5 h-5 rounded-full border flex items-center justify-center shrink-0"
                  :class="selectedPayment === method.id ? 'bg-(--color-azure) border-(--color-azure)' : 'border-(--color-outline)'"
                >
                  <Icon v-if="selectedPayment === method.id" name="check" class="w-3 h-3 text-white" />
                </span>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>

    <!-- Fullscreen Navy Loading Screen: Searching Driver with Lottie -->
    <Teleport to="body">
      <Transition
        appear
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-300"
        leave-to-class="opacity-0"
      >
        <div
          v-if="searchingDriver"
          class="fixed inset-0 z-[100] bg-[#0B192C] text-white flex flex-col items-center justify-center p-6 text-center select-none overflow-hidden"
        >
          <!-- Ambient Radial Glow -->
          <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(56,189,248,0.18)_0%,transparent_70%)] pointer-events-none"></div>

          <!-- User Provided Animated SVG Truck Component -->
          <div class="relative z-10 w-full flex items-center justify-center mb-2">
            <AngkutLoadingTruck />
          </div>

          <!-- Status Title & Subtext -->
          <div class="relative z-10 flex flex-col items-center max-w-xs text-center">
            <h2 class="text-xl font-extrabold text-white tracking-tight mb-2">
              {{ searchStep === 1 ? 'Mencari Driver BisaAngkut' : 'Driver Ditemukan!' }}
            </h2>
            <p class="text-sm font-medium leading-snug">
              <span v-if="searchStep === 1" class="text-slate-300">
                Menghubungkan dengan driver terdekat...
              </span>
              <span v-else class="text-[#8BC53F] font-bold flex items-center justify-center gap-1.5">
                <Icon name="check" class="w-4 h-4" /> Driver dalam perjalanan ke lokasi penjemputan
              </span>
            </p>
          </div>



        </div>
      </Transition>
    </Teleport>
  </div>
</template>


