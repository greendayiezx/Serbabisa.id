<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'
import cardBorderImg from '@/assets/card-border.png'
import cardBoxBersihBersihImg from '@/assets/card-box-bersihbersih.png'
import cardBoxAntarBarangImg from '@/assets/card-box-antarbarang.png'
import cardBoxBelanjaImg from '@/assets/card-box-belanja.png'
import categoryAngkutImg from '@/assets/category-angkut.svg'
import categoryAntarBarangImg from '@/assets/category-antar-barang.svg'
import categoryAntarJemputImg from '@/assets/category-antar-jemput.svg'
import categoryBelanjaImg from '@/assets/category-belanja.svg'
import categoryBersihBersihImg from '@/assets/category-bersih-bersih.svg'
import categoryTukangImg from '@/assets/category-tukang.svg'
import categoryBisaApaAjaImg from '@/assets/category-bisa-apa-aja.svg'

// Backend seeds category.ikon as free-text slugs; map them to sprite icon names.
const iconMap: Record<string, string> = {
  car: 'car',
  package: 'package',
  'shopping-cart': 'cart',
  sparkles: 'sparkle',
  truck: 'truck',
  wrench: 'wrench',
}

// Full custom illustrations for categories that have one; others fall back to a sprite icon on a tinted tile.
const categoryImages: Record<string, string> = {
  truck: categoryAngkutImg,
  package: categoryAntarBarangImg,
  car: categoryAntarJemputImg,
  'shopping-cart': categoryBelanjaImg,
  sparkles: categoryBersihBersihImg,
  wrench: categoryTukangImg,
}

const tileTint: Record<string, string> = {
  car: 'bg-(--color-primary-container)',
  package: 'bg-(--color-tertiary-container)',
  'shopping-cart': 'bg-(--color-secondary-container)',
  sparkles: 'bg-(--color-secondary-container)',
  wrench: 'bg-(--color-tertiary-container)',
}

const tileIconColor: Record<string, string> = {
  car: 'text-(--color-on-primary-container)',
  package: 'text-(--color-on-tertiary-container)',
  'shopping-cart': 'text-(--color-on-secondary-container)',
  sparkles: 'text-(--color-on-secondary-container)',
  wrench: 'text-(--color-on-tertiary-container)',
}

const router = useRouter()
const taskStore = useTaskStore()

function pickCategory(slug: string) {
  if (slug === 'bisaangkut') {
    router.push({ name: 'task-angkut-location', query: { category: slug } })
    return
  }
  router.push({ name: 'task-location', query: { category: slug } })
}

function pickCustom() {
  router.push({ name: 'task-location', query: { custom: '1' } })
}

onMounted(async () => {
  await taskStore.fetchCategories()
})

// Illustrative "trending" examples — swap for a real nearby/trending endpoint later.
const trendingTasks = computed(() => [
  {
    icon: 'sparkle',
    badge: 'Bersih-bersih',
    chip: 'chip-lime',
    title: 'Bersihkan Kos 2 Kamar, Jaksel',
    rating: '4.9',
    price: 'Rp45rb/jam',
    bidders: '8 mitra menawar',
    progress: 70,
    art: 'from-white to-white',
    image: cardBoxBersihBersihImg,
  },
  {
    icon: 'package',
    badge: 'Antar Barang',
    chip: 'chip-plain',
    title: 'Kirim Paket ke Bekasi Hari Ini',
    rating: '4.7',
    price: 'Rp28rb',
    bidders: '5 mitra menawar',
    progress: 45,
    art: 'from-white to-white',
    image: cardBoxAntarBarangImg,
  },
  {
    icon: 'cart',
    badge: 'Belanja',
    chip: 'chip-gold',
    title: 'Titip Beli Obat ke Apotek K24',
    rating: '5.0',
    price: 'Rp15rb+',
    bidders: '3 mitra menawar',
    progress: 25,
    art: 'from-white to-white',
    image: cardBoxBelanjaImg,
  },
])
</script>

<template>
  <AppLayout>
    <!-- Unified Hero & Quick Task Container -->
    <div class="relative z-10 mx-4 mt-4 overflow-hidden rounded-(--radius-card) bg-(--color-surface-0) shadow-(--shadow-float)">
      <!-- Illustration, cropped to a compact banner height, stats overlaid on its plain white footer -->
      <div class="relative">
        <img :src="cardBorderImg" alt="Tugasin - Serbabisa.id" class="w-full h-auto block" />

        <div class="absolute inset-x-4 bottom-3 flex justify-between">
          <div><b class="block font-display font-extrabold text-[12px] sm:text-[13px] text-black leading-tight">12.480</b><span class="block text-black/70 text-[8px] sm:text-[9px] font-semibold mt-0.5 whitespace-nowrap">Tugas Selesai</span></div>
          <div><b class="block font-display font-extrabold text-[12px] sm:text-[13px] text-black leading-tight">3.200+</b><span class="block text-black/70 text-[8px] sm:text-[9px] font-semibold mt-0.5 whitespace-nowrap">Mitra Aktif</span></div>
          <div><b class="block font-display font-extrabold text-[12px] sm:text-[13px] text-black leading-tight">4.8★</b><span class="block text-black/70 text-[8px] sm:text-[9px] font-semibold mt-0.5 whitespace-nowrap">Rating Rata-rata</span></div>
        </div>
      </div>

      <!-- Category grid: tap a category to go straight into location picking -->
      <div class="px-5 pt-[22px] pb-5">
        <h4 class="font-extrabold text-[16px] mb-3.5">Mau dibantuin apa hari ini?</h4>

        <div class="grid grid-cols-4 gap-x-2 gap-y-4">
          <button
            v-for="c in taskStore.categories"
            :key="c.id"
            type="button"
            class="flex flex-col items-center gap-1.5 text-center"
            @click="pickCategory(c.slug)"
          >
            <span
              class="w-16 h-16 rounded-2xl overflow-hidden flex items-center justify-center shrink-0"
              :class="categoryImages[c.ikon ?? ''] ? '' : (tileTint[c.ikon ?? ''] || 'bg-(--color-surface-container)')"
            >
              <img v-if="categoryImages[c.ikon ?? '']" :src="categoryImages[c.ikon ?? '']" :alt="c.nama" class="w-full h-full object-contain" />
              <Icon v-else :name="iconMap[c.ikon ?? ''] || 'layers'" class="w-7 h-7" :class="tileIconColor[c.ikon ?? ''] || 'text-(--color-on-surface-variant)'" />
            </span>
            <span class="text-[11px] font-bold leading-tight text-(--color-on-surface)">{{ c.nama }}</span>
          </button>

          <button type="button" class="flex flex-col items-center gap-1.5 text-center" @click="pickCustom">
            <span class="w-16 h-16 rounded-2xl overflow-hidden flex items-center justify-center shrink-0">
              <img :src="categoryBisaApaAjaImg" alt="BisaApaAja" class="w-full h-full object-contain" />
            </span>
            <span class="text-[11px] font-bold leading-tight text-(--color-on-surface)">BisaApaAja</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Trending tasks -->
    <section class="px-5 pt-7 pb-6">
      <div class="flex items-baseline justify-between mb-3.5">
        <h4 class="font-extrabold text-[15.5px]">Lagi Rame Dikerjakan</h4>
        <RouterLink :to="{ name: 'task-list' }" class="text-[12.5px] font-bold text-(--color-on-primary-container)">Lihat Semua</RouterLink>
      </div>

      <div class="flex gap-3 overflow-x-auto no-scrollbar -mx-5 px-5 pb-2">
        <div v-for="task in trendingTasks" :key="task.title" class="shrink-0 w-52">
          <div class="relative h-32 rounded-2xl flex items-center justify-center bg-linear-to-br overflow-hidden" :class="task.art">
            <img v-if="task.image" :src="task.image" :alt="task.badge" class="absolute inset-x-0 top-4 w-full h-[calc(100%+18px)] object-cover object-top z-0" />
            <span
              class="absolute top-1.5 left-1.5 z-10 inline-flex items-center gap-1 rounded-full text-[9.5px] font-extrabold px-1.5 py-0.5 leading-none bg-white text-slate-800 shadow-xs"
            >
              <Icon :name="task.icon" class="w-2.5 h-2.5 text-(--color-azure)" />{{ task.badge }}
            </span>
            <span class="absolute top-1.5 right-1.5 z-10 glass-panel rounded-full text-[9.5px] font-extrabold px-1.5 py-0.5 flex items-center gap-0.5 text-(--color-on-surface) leading-none shadow-xs">
              <Icon name="star" class="w-2.5 h-2.5 fill-(--color-gold) stroke-(--color-gold)" />{{ task.rating }}
            </span>
            <Icon v-if="!task.image" :name="task.icon" class="w-8.5 h-8.5 text-white/90" />
            <span class="absolute bottom-2 right-2 z-10 bg-(--color-surface-0) text-(--color-on-surface) font-extrabold text-xs px-2.5 py-1 rounded-full shadow-xs">{{ task.price }}</span>
          </div>
          <p class="text-[13.5px] font-bold mt-2.5">{{ task.title }}</p>
          <div class="flex justify-between text-[11px] text-(--color-on-surface-variant) mt-2 mb-1">
            <span>{{ task.bidders }}</span>
          </div>
          <div class="h-1.5 rounded-full bg-(--color-surface-container-high) overflow-hidden">
            <div class="h-full rounded-full bg-(--color-lime)" :style="{ width: task.progress + '%' }"></div>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
