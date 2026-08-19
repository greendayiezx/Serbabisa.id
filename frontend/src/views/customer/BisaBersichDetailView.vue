<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useLocationStore } from '@/stores/location'
import { useSkeleton } from '@/composables/useSkeleton'
import Skeleton from '@/components/ui/Skeleton.vue'
import Icon from '@/components/icons/Icon.vue'

const router = useRouter()
const locationStore = useLocationStore()

interface Service {
  id: string
  label: string
  desc: string
}

const services: Service[] = [
  { id: 'house', label: 'House Cleaning', desc: 'Perkaman dan peralatan rumah' },
  { id: 'office', label: 'Office Cleaning', desc: 'Ruang kerja rapi & bersih' },
  { id: 'deep', label: 'Deep Cleaning', desc: 'Pembersihan menyeluruh' },
  { id: 'ac', label: 'AC Maintenance', desc: 'Service & bersihkan AC' },
  { id: 'disinfect', label: 'Disinfectant', desc: 'Desinfeksi permukaan' },
  { id: 'ironing', label: 'Ironing', desc: 'Setrika pakaian' },
]

const selectedService = ref<string | null>(null)

// Lokasi yang dipilih di halaman Location sebelumnya disimpan di draft store,
// jadi di sini cukup baca untuk ditampilkan sebagai "jembatan" ke halaman berikutnya.
const selectedLocation = computed(() => locationStore.draft)

function handleServiceSelect(id: string) {
  selectedService.value = id
}

function handleLanjut() {
  if (!selectedService.value) return
  router.push({
    name: 'task-create',
    query: { category: 'bisabersich', service: selectedService.value },
  })
}

// TopAppBar: scroll-hide (samakan dengan pola di LocationView)
const topAppBar = ref<HTMLElement | null>(null)
let lastScrollTop = 0
const scrollThreshold = 50

function onScroll() {
  if (!topAppBar.value) return
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop
  if (scrollTop > scrollThreshold) {
    topAppBar.value.style.transform = scrollTop > lastScrollTop ? 'translateY(-100%)' : 'translateY(0)'
  } else {
    topAppBar.value.style.transform = 'translateY(0)'
  }
  lastScrollTop = scrollTop <= 0 ? 0 : scrollTop
}

function goBack() {
  router.push({ name: 'task-location', query: { category: 'bisabersich' } })
}

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
})

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => tandaiSiap())))
</script>

<template>
  <div v-if="skelTampil" class="min-h-dvh w-full bg-(--color-surface) pb-24">
    <div class="h-56 w-full">
      <Skeleton class="h-full w-full rounded-b-[2rem]" />
    </div>
    <div class="mx-4 mt-4 space-y-3">
      <Skeleton class="h-14 w-full" />
      <div class="grid grid-cols-3 gap-3">
        <Skeleton class="h-20 w-full" v-for="i in 6" :key="i" />
      </div>
      <Skeleton class="h-8 w-40" />
      <div class="space-y-3">
        <Skeleton class="h-20 w-full" v-for="i in 3" :key="'c' + i" />
      </div>
      <Skeleton class="h-40 w-full" />
    </div>
  </div>

  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) font-body-md overflow-x-hidden">
      <!-- TopAppBar: fixed, hides on scroll down -->
      <header
        ref="topAppBar"
        class="fixed top-0 w-full z-50 bg-white/85 backdrop-blur-xl border-b border-white/20 shadow-sm transition-transform duration-300 translate-y-0"
      >
        <div class="flex items-center px-4 pt-4 pb-2.5">
          <button
            type="button"
            aria-label="Kembali"
            class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
            @click="goBack"
          >
            <Icon name="arrow-left" class="w-[19px] h-[19px]" />
          </button>
          <h1 class="flex-1 font-display-lg text-headline-lg-mobile text-center text-(--color-azure)">
            BisaBersich
          </h1>
          <div class="w-9"></div>
        </div>
      </header>

      <main class="pt-[56px] flex flex-col gap-5">
        <!-- Hero Section -->
        <section class="relative mx-4 mt-1 rounded-[2rem] overflow-hidden shadow-[0_20px_60px_rgba(0,0,0,0.10)] group">
          <img
            alt="Clean Home Hero Banner"
            class="w-full h-[200px] object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
            src="https://lh3.googleusercontent.com/aida-public/AB6AXuAug0RL3fedrwmBwP9aaLYfp1cx80r3fve20LvRXUnpYDuinNXpSa97KYzkZglha7fCq0NETgO3Pxi8ZwM-yhaYpkFf0NdH376O7z08of2wnFzW5EzfeNNCPQiWrseVVlBFTsCe3a6_hnz9hjKWQwxSKl4rAE3-go3doQxGMiXWRTkPafZqFMjCsvsbFv-CszoNpLR06KqfkNjQoZIGfcvABzlopC8IK88xPofcIKeCXdHdui0LFaDW"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-deep-onyx/70 to-transparent z-10 rounded-[2rem] pointer-events-none"></div>
          <div class="absolute bottom-0 left-0 w-full p-5 z-20">
            <div
              class="inline-block bg-(--color-lime) text-[#33430b] font-label-lg px-3 py-1 rounded-full mb-2 shadow-sm animate-pulse"
            >
              Promo 20% Off for First Order
            </div>
            <h2 class="font-title-lg text-title-lg text-white mb-1">Spotless Home, Instantly.</h2>
            <p class="font-body-md text-white/90">Professional cleaning at your fingertips.</p>
          </div>
        </section>

        <!-- Lokasi yang dipilih -->
        <section v-if="selectedLocation" class="mx-4">
          <div
            class="flex items-start gap-3 rounded-2xl bg-(--color-surface-0) border border-(--color-outline)/10 p-4 shadow-(--shadow-lift)"
          >
            <span class="w-10 h-10 rounded-full bg-(--color-primary-container) flex items-center justify-center shrink-0">
              <Icon name="pin" class="w-[18px] h-[18px] text-(--color-on-primary-container)" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1">
                Lokasi Tujuan
              </p>
              <p class="text-sm font-bold text-(--color-on-surface) leading-snug break-words">
                {{ selectedLocation.alamat }}
              </p>
            </div>
          </div>
        </section>

        <!-- Service Categories (Grid) -->
        <section class="mx-4">
          <div class="flex justify-between items-end mb-3">
            <h3 class="font-title-lg text-title-lg text-(--color-on-surface)">Services</h3>
            <a
              class="font-label-lg text-(--color-azure) hover:underline"
              href="#"
              @click.prevent
            >See all</a>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <button
              v-for="s in services"
              :key="s.id"
              type="button"
              class="flex flex-col items-center justify-center p-4 bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)] transition-all active:scale-95 border border-(--color-outline)/20 group"
              :class="selectedService === s.id ? 'border-2 border-(--color-azure) bg-(--color-primary-container)' : ''"
              @click="handleServiceSelect(s.id)"
            >
              <span
                class="w-12 h-12 rounded-full flex items-center justify-center mb-2 transition-colors"
                :class="
                  selectedService === s.id
                    ? 'bg-(--color-azure) text-white'
                    : 'bg-(--color-primary-container) group-hover:bg-(--color-azure) group-hover:text-white'
                "
              >
                <Icon v-if="s.id === 'house'" name="home" class="w-6 h-6 text-(--color-on-primary-container)" />
                <Icon v-else-if="s.id === 'office'" name="business" class="w-6 h-6 text-(--color-on-primary-container)" />
                <Icon v-else-if="s.id === 'deep'" name="sparkle" class="w-6 h-6 text-(--color-on-primary-container)" />
                <svg
                  v-else-if="s.id === 'ac'"
                  viewBox="0 0 24 24"
                  class="w-6 h-6 text-(--color-on-primary-container)"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M12 14l9-5-9-5-9 5 9 5z" />
                  <path d="M12 19l9-5-9-5-9 5 9 5z" />
                  <line x1="12" y1="15" x2="12" y2="7" />
                </svg>
                <svg
                  v-else-if="s.id === 'disinfect'"
                  viewBox="0 0 24 24"
                  class="w-6 h-6 text-(--color-on-primary-container)"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M16.5 9.5l-7-7 3-3 7 7 3 3z" />
                  <path d="M12 6l-6 6 6 6" />
                  <path d="M8 12l4 4 4-4" />
                </svg>
                <svg
                  v-else-if="s.id === 'ironing'"
                  viewBox="0 0 24 24"
                  class="w-6 h-6 text-(--color-on-primary-container)"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <rect x="3" y="11" width="18" height="10" rx="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                  <circle cx="12" cy="5" r="2" />
                </svg>
                <Icon v-else name="layers" class="w-6 h-6 text-(--color-on-primary-container)" />
              </span>
              <span class="font-label-sm text-center text-(--color-on-surface) text-[11px] leading-tight">
                {{ s.label }}
              </span>
            </button>
          </div>
        </section>

        <!-- Special Section: Why BisaBersich? -->
        <section class="mx-4 bg-(--color-surface-container-low) py-8 my-4 rounded-[2rem]">
          <h3 class="font-title-lg text-title-lg text-(--color-on-surface) mb-6 text-center">
            Why BisaBersich?
          </h3>
          <div class="flex flex-col gap-4">
            <!-- Card 1 -->
            <div class="bg-(--color-surface-0) p-5 rounded-2xl shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-10 h-10 rounded-full bg-(--color-secondary-container) flex-shrink-0 flex items-center justify-center">
                <Icon name="shield" class="w-5 h-5 text-(--color-on-secondary-container)" />
              </div>
              <div>
                <h4 class="font-label-lg text-(--color-on-surface) mb-1">Verified Cleaners</h4>
                <p class="font-body-md text-(--color-on-surface-variant) text-sm">
                  Every partner is background-checked and professionally trained for top-tier service.
                </p>
              </div>
            </div>
            <!-- Card 2 -->
            <div class="bg-(--color-surface-0) p-5 rounded-2xl shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-10 h-10 rounded-full bg-(--color-secondary-container) flex-shrink-0 flex items-center justify-center">
                <Icon name="sparkle" class="w-5 h-5 text-(--color-on-secondary-container)" />
              </div>
              <div>
                <h4 class="font-label-lg text-(--color-on-surface) mb-1">Eco-Friendly Supplies</h4>
                <p class="font-body-md text-(--color-on-surface-variant) text-sm">
                  We use safe, sustainable cleaning products that are tough on dirt but gentle on your home.
                </p>
              </div>
            </div>
            <!-- Card 3 -->
            <div class="bg-(--color-surface-0) p-5 rounded-2xl shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-10 h-10 rounded-full bg-(--color-secondary-container) flex-shrink-0 flex items-center justify-center">
                <Icon name="check-circle" class="w-5 h-5 text-(--color-on-secondary-container)" />
              </div>
              <div>
                <h4 class="font-label-lg text-(--color-on-surface) mb-1">24h Satisfaction Guarantee</h4>
                <p class="font-body-md text-(--color-on-surface-variant) text-sm">
                  Not happy with the clean? Let us know within 24 hours and we'll re-clean for free.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Subscription Banner -->
        <section class="mx-4 mb-6 relative rounded-[2rem] overflow-hidden bg-(--color-azure) shadow-[0_20px_60px_rgba(0,0,0,0.15)] flex flex-col items-center text-center p-6 justify-center min-h-[160px]">
          <div class="absolute top-0 right-0 w-32 h-32 bg-(--color-lime)/30 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3"></div>
          <div class="absolute bottom-0 left-0 w-24 h-24 bg-(--color-lime)/20 rounded-full blur-2xl translate-y-1/2 -translate-x-1/2"></div>
          <div class="relative z-10 flex flex-col items-center">
            <Icon name="calendar" class="w-8 h-8 text-(--color-lime) mb-2" />
            <h3 class="font-title-lg text-title-lg text-white mb-2">Subscription Plans</h3>
            <p class="font-body-md text-white/90 mb-4 max-w-[250px]">
              Schedule weekly or bi-weekly cleans and lock in huge savings.
            </p>
            <div
              class="inline-block bg-(--color-lime) text-[#33430b] font-label-lg px-4 py-2 rounded-full font-bold shadow-md hover:bg-white transition-colors cursor-pointer active:scale-95"
            >
              Save up to 20%
            </div>
          </div>
        </section>
      </main>

      <!-- Sticky footer CTA -->
      <footer
        class="fixed bottom-0 inset-x-0 z-30 bg-(--color-surface-0)/90 backdrop-blur-xl border-t border-(--color-outline)/40 pb-[env(safe-area-inset-bottom)]"
      >
        <div class="max-w-[430px] mx-auto px-4 py-3 flex gap-3">
          <button
            type="button"
            :disabled="!selectedService"
            class="flex-1 bg-(--color-azure) text-white rounded-full py-3.5 text-[14px] font-bold shadow-md shadow-(--color-azure)/20 hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed min-h-11"
            @click="handleLanjut"
          >
            Lanjut
            <Icon name="arrow-right" class="w-4 h-4" />
          </button>
        </div>
      </footer>

      <!-- BottomNavBar -->
      <nav
        class="fixed bottom-0 w-full z-50 bg-(--color-surface-0)/85 backdrop-blur-xl border-t border-white/20 shadow-[0_-20px_60px_rgba(0,0,0,0.10)] flex justify-around items-center h-20 px-4 pb-[env(safe-area-inset-bottom)]"
      >
        <button class="flex flex-col items-center justify-center bg-(--color-primary-container) text-(--color-on-primary-container) rounded-xl px-3 py-1 active:scale-90 transition-transform duration-200">
          <Icon name="home" class="w-5 h-5 mb-1" />
          <span class="font-label-sm text-label-sm">Home</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center text-(--color-on-surface-variant) hover:bg-(--color-surface-container)/60 transition-colors rounded-xl px-3 py-1 active:scale-90"
          @click="router.push({ name: 'task-list' })"
        >
          <Icon name="clipboard" class="w-5 h-5 mb-1" />
          <span class="font-label-sm text-label-sm">Tasks</span>
        </button>
        <button
          type="button"
          class="flex flex-col items-center justify-center text-(--color-on-surface-variant) hover:bg-(--color-surface-container)/60 transition-colors rounded-xl px-3 py-1 active:scale-90"
          @click="router.push({ name: 'profile' })"
        >
          <Icon name="user" class="w-5 h-5 mb-1" />
          <span class="font-label-sm text-label-sm">Profile</span>
        </button>
      </nav>
    </div>
  </template>
</template>

<style scoped>
/* The hero headline/subtitle live on a transparent-to-dark gradient, so force
   them legible without relying on the upstream page's text color. */
.hero-headline {
  color: #ffffff;
}
</style>
