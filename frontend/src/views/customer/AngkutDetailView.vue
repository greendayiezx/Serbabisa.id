<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useAngkutDraftStore } from '@/stores/angkutDraft'
import vehicleMotorBoxImg from '@/assets/vehicle-motor-box.svg'
import vehiclePickupBakImg from '@/assets/vehicle-pickup-bak.svg'
import vehicleBlindVanImg from '@/assets/vehicle-blind-van.svg'

const router = useRouter()
const angkutDraftStore = useAngkutDraftStore()

function goBackOrHome() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push({ name: 'home' })
  }
}

type DeliveryId = 'instant' | 'instant_hemat' | 'sameday'

interface DeliveryOption {
  id: DeliveryId
  label: string
  desc: string
}

interface Vehicle {
  id: string
  label: string
  icon: string
  image?: string
  prices: Record<DeliveryId, number>
}

const deliveryOptions: DeliveryOption[] = [
  { id: 'instant', label: 'Instant', desc: 'Pengiriman paling cepat' },
  { id: 'instant_hemat', label: 'Instant Hemat', desc: 'Cepat dengan harga hemat' },
  { id: 'sameday', label: 'SameDay', desc: 'Diantar di hari yang sama' },
]

const vehicles: Vehicle[] = [
  { id: 'motor_box', label: 'Motor Box', icon: 'motorcycle', image: vehicleMotorBoxImg, prices: { instant: 35000, instant_hemat: 30000, sameday: 25000 } },
  { id: 'pickup_bak', label: 'Pickup Bak', icon: 'truck', image: vehiclePickupBakImg, prices: { instant: 80000, instant_hemat: 70000, sameday: 55000 } },
  { id: 'blind_van', label: 'Blind Van', icon: 'van', image: vehicleBlindVanImg, prices: { instant: 120000, instant_hemat: 105000, sameday: 85000 } },
]

const selectedVehicle = ref('pickup_bak')
const selectedDelivery = ref<DeliveryId>('instant')
const deliveryOpen = ref(true)
const catatan = ref('')
const photos = ref<File[]>([])
const photoInput = ref<HTMLInputElement | null>(null)
const cameraInput = ref<HTMLInputElement | null>(null)
const helperCount = ref(1)
const tanggal = ref('')
const waktu = ref('')

function pickPhotos() {
  photoInput.value?.click()
}

function pickCameraPhoto() {
  cameraInput.value?.click()
}

function onPhotosChange(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files ? Array.from(input.files) : []
  photos.value = [...photos.value, ...files]
  input.value = ''
}

function incHelper() {
  helperCount.value = Math.min(4, helperCount.value + 1)
}

function decHelper() {
  helperCount.value = Math.max(0, helperCount.value - 1)
}

const currentVehicle = computed(() => vehicles.find((v) => v.id === selectedVehicle.value))
const selectedDeliveryOpt = computed(() => deliveryOptions.find((o) => o.id === selectedDelivery.value))

function priceFor(optId: DeliveryId) {
  return currentVehicle.value?.prices[optId] ?? 0
}

function selectDelivery(id: DeliveryId) {
  selectedDelivery.value = id
}

const estimasiTotal = computed(() => {
  return priceFor(selectedDelivery.value) + helperCount.value * 50000
})

function lanjut() {
  angkutDraftStore.setDraft({
    vehicleLabel: currentVehicle.value?.label ?? '',
    vehicleImage: currentVehicle.value?.image,
    deliveryLabel: selectedDeliveryOpt.value?.label ?? '',
    helperCount: helperCount.value,
    tanggal: tanggal.value,
    waktu: waktu.value,
    total: estimasiTotal.value,
  })
  router.push({ name: 'task-angkut-confirm' })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-28">
    <!-- Header: simple bar, no illustration -->
    <div class="flex items-center gap-3 px-5 pt-5 pb-2">
      <button
        type="button"
        class="w-10 h-10 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>
      <h1 class="font-extrabold text-[16px] text-(--color-on-surface)">Detail Pengiriman</h1>
    </div>

    <div class="px-5 py-6 flex flex-col gap-7">
      <!-- Vehicle + delivery service summary, opens the combined bottom sheet -->
      <section class="flex flex-col gap-3.5">
        <h2 class="text-sm font-bold text-(--color-on-surface)">Kendaraan & Layanan</h2>
        <button
          type="button"
          class="w-full flex items-start gap-3 rounded-2xl bg-(--color-surface-0) border border-(--color-outline)/40 shadow-(--shadow-lift) px-4 py-3.5 transition-transform active:scale-[0.99]"
          @click="deliveryOpen = true"
        >
          <span class="w-11 h-11 rounded-full overflow-hidden flex items-center justify-center shrink-0 bg-(--color-surface-container)">
            <img v-if="currentVehicle?.image" :src="currentVehicle.image" :alt="currentVehicle.label" class="w-full h-full object-contain" />
            <Icon v-else-if="currentVehicle" :name="currentVehicle.icon" class="w-6 h-6 text-(--color-azure)" />
          </span>
          <div class="flex flex-col items-start flex-1 min-w-0 overflow-hidden">
            <span class="block w-full text-[12.5px] font-bold text-(--color-on-surface) truncate">{{ currentVehicle?.label }} &middot; {{ selectedDeliveryOpt?.label }}</span>
            <span class="block w-full text-[11px] text-(--color-on-surface-variant) truncate">{{ selectedDeliveryOpt?.desc }}</span>
          </div>
          <div class="flex items-center gap-2 shrink-0 pt-0.5">
            <span class="font-extrabold text-sm text-(--color-on-surface) whitespace-nowrap">Rp{{ priceFor(selectedDelivery).toLocaleString('id-ID') }}</span>
            <Icon name="chevron-down" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
          </div>
        </button>
      </section>

      <!-- Vehicle + delivery bottom sheet -->
      <Teleport to="body">
        <div v-if="deliveryOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
          <Transition
            appear
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
          >
            <div class="absolute inset-0 bg-black/45" @click="deliveryOpen = false"></div>
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
                <h3 class="font-extrabold text-[15px]">Kendaraan & Layanan</h3>
                <button type="button" class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center" @click="deliveryOpen = false">
                  <Icon name="x" class="w-4 h-4" />
                </button>
              </div>

              <div class="overflow-y-auto flex-1 p-4 flex flex-col gap-5">
                <!-- Vehicle -->
                <div>
                  <h4 class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-2.5">Pilih Kendaraan</h4>
                  <div class="flex gap-3 overflow-x-auto no-scrollbar pb-1">
                    <label v-for="v in vehicles" :key="v.id" class="relative shrink-0 w-30 cursor-pointer">
                      <input v-model="selectedVehicle" type="radio" name="vehicle" :value="v.id" class="peer sr-only" />
                      <div
                        class="h-full rounded-2xl border-2 p-3 flex flex-col items-center gap-2 transition-colors"
                        :class="selectedVehicle === v.id ? 'border-(--color-azure) bg-(--color-primary-container)' : 'border-(--color-outline) bg-(--color-surface-0)'"
                      >
                        <span
                          class="w-12 h-12 rounded-full overflow-hidden flex items-center justify-center"
                          :class="v.image ? '' : (selectedVehicle === v.id ? 'bg-white text-(--color-azure)' : 'bg-(--color-surface-container) text-(--color-azure)')"
                        >
                          <img v-if="v.image" :src="v.image" :alt="v.label" class="w-full h-full object-contain" />
                          <Icon v-else :name="v.icon" class="w-6 h-6" />
                        </span>
                        <div class="text-center">
                          <h3 class="text-[12px] font-bold text-(--color-on-surface)">{{ v.label }}</h3>
                          <p class="text-[10px] text-(--color-on-surface-variant) mt-0.5">Mulai Rp{{ (Math.min(v.prices.instant, v.prices.instant_hemat, v.prices.sameday) / 1000).toFixed(0) }}rb</p>
                        </div>
                      </div>
                      <span
                        v-if="selectedVehicle === v.id"
                        class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-(--color-lime) text-[#33430b] flex items-center justify-center shadow-xs"
                      >
                        <Icon name="check" class="w-3 h-3" />
                      </span>
                    </label>
                  </div>
                </div>

                <!-- Delivery speed -->
                <div>
                  <h4 class="text-[11px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-2.5">Pilih Layanan Harga</h4>
                  <div class="flex flex-col gap-2.5">
                    <button
                      v-for="opt in deliveryOptions"
                      :key="opt.id"
                      type="button"
                      class="w-full flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl border-2 text-left transition-colors"
                      :class="selectedDelivery === opt.id ? 'border-(--color-azure) bg-(--color-primary-container)' : 'border-(--color-outline)/40 hover:bg-(--color-surface-container)'"
                      @click="selectDelivery(opt.id)"
                    >
                      <div class="flex flex-col items-start">
                        <span class="text-sm font-bold text-(--color-on-surface)">{{ opt.label }}</span>
                        <span class="text-[11px] text-(--color-on-surface-variant)">{{ opt.desc }}</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-extrabold text-sm text-(--color-on-surface)">Rp{{ priceFor(opt.id).toLocaleString('id-ID') }}</span>
                        <Icon v-if="selectedDelivery === opt.id" name="check" class="w-4 h-4 text-(--color-azure)" />
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Teleport>

      <!-- Load details -->
      <section class="flex flex-col gap-3.5">
        <h2 class="text-sm font-bold text-(--color-on-surface)">Detail Barang Bawaan</h2>
        <textarea
          v-model="catatan"
          placeholder="Contoh: 2 kardus besar, 1 meja kecil, dan 1 koper."
          class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none resize-none min-h-28 placeholder:text-(--color-on-surface-variant)"
        />
        <input ref="photoInput" type="file" accept="image/*" multiple class="hidden" @change="onPhotosChange" />
        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhotosChange" />
        <div class="flex gap-2.5">
          <button
            type="button"
            class="flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-(--radius-input) border-2 border-dashed border-(--color-outline) text-(--color-on-surface-variant) text-sm font-bold active:scale-[0.99] transition-transform"
            @click="pickCameraPhoto"
          >
            <Icon name="camera" class="w-[18px] h-[18px]" />
            Ambil Foto
          </button>
          <button
            type="button"
            class="flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-(--radius-input) border-2 border-dashed border-(--color-outline) text-(--color-on-surface-variant) text-sm font-bold active:scale-[0.99] transition-transform"
            @click="pickPhotos"
          >
            <Icon name="image" class="w-[18px] h-[18px]" />
            Pilih dari Galeri
          </button>
        </div>
        <p v-if="photos.length" class="text-[12px] text-(--color-on-surface-variant)">{{ photos.length }} foto dipilih</p>
      </section>

      <!-- Schedule -->
      <section class="flex flex-col gap-3.5">
        <h2 class="text-sm font-bold text-(--color-on-surface)">Jadwal Pengambilan</h2>
        <div class="grid grid-cols-2 gap-3">
          <DatePickerField v-model="tanggal" />
          <TimePickerField v-model="waktu" />
        </div>
      </section>

      <!-- Manpower -->
      <section class="bg-(--color-surface-0) rounded-(--radius-card) p-4 shadow-(--shadow-lift) border border-(--color-outline)/40 flex flex-col gap-3.5">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-sm font-bold text-(--color-on-surface)">Butuh Helper?</h2>
            <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">Bantuan angkat-angkat</p>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center text-(--color-on-surface-variant) active:scale-90 transition-transform" @click="decHelper">
              <Icon name="minus" class="w-[18px] h-[18px]" />
            </button>
            <span class="font-extrabold text-base text-(--color-on-surface) w-4 text-center">{{ helperCount }}</span>
            <button type="button" class="w-8 h-8 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform" @click="incHelper">
              <Icon name="plus" class="w-[18px] h-[18px]" />
            </button>
          </div>
        </div>
        <div v-if="helperCount > 0" class="bg-(--color-primary-container)/50 rounded-xl p-3 flex gap-2 items-start">
          <Icon name="info" class="w-[18px] h-[18px] text-(--color-azure) shrink-0" />
          <p class="text-[12px] text-(--color-on-primary-container)">+ Rp50.000 / orang</p>
        </div>
      </section>
    </div>

    <!-- Sticky footer -->
    <div class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0) border-t border-(--color-outline)/40 px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] flex items-center justify-between gap-3">
      <div class="flex flex-col">
        <span class="text-[11px] text-(--color-on-surface-variant)">Estimasi Total</span>
        <span class="text-lg font-extrabold text-black">Rp{{ estimasiTotal.toLocaleString('id-ID') }}</span>
      </div>
      <button
        type="button"
        class="flex items-center gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] px-6 py-3.5 min-h-11 shadow-(--shadow-lift)"
        @click="lanjut"
      >
        Lanjut
        <Icon name="chevron-right" class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
