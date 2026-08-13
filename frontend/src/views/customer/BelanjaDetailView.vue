<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import BisaBelanjaNavbar from '@/components/belanja/BisaBelanjaNavbar.vue'
import { useBelanjaDraftStore, type BelanjaItem } from '@/stores/belanjaDraft'
import { useLocationStore } from '@/stores/location'
import logoAlfamart from '@/assets/LOGO-AFLAMART.png'
import logoIndomaret from '@/assets/LOGO-INDOMART.png'
import logoAeon from '@/assets/LOGO-AEON.png'
import logoHypermart from '@/assets/LOGO-HYPERMART.png'
import logoFamilymart from '@/assets/LOGO-FAMILYMART.png'
import logoSuperindo from '@/assets/LOGO-SUPERINDO.png'
import logoTransmart from '@/assets/LOGO-TRANSMART.png'
import logoLawson from '@/assets/LOGO-LAWSON.png'
import logoCircleK from '@/assets/LOGO-CIRCLEK.png'

const router = useRouter()
const belanjaDraft = useBelanjaDraftStore()
const locationStore = useLocationStore()

// Pre-fill alamat from location draft
if (locationStore.draft) {
  belanjaDraft.patchDraft({ alamatToko: locationStore.draft.alamat })
}

interface Kategori {
  id: string
  label: string
  emoji: string
  bgClass: string
}

const kategoriList: Kategori[] = [
  { id: 'buah', label: 'Buah Segar', emoji: '🍎', bgClass: 'bg-[#E8F5E9]' },
  { id: 'sayur', label: 'Sayur Segar', emoji: '🥦', bgClass: 'bg-[#F1F8E9]' },
  { id: 'susu', label: 'Susu & Olahan', emoji: '🥛', bgClass: 'bg-[#E3F2FD]' },
  { id: 'telur', label: 'Telur, Tahu & Tempe', emoji: '🥚', bgClass: 'bg-[#FFF8E1]' },
  { id: 'daging', label: 'Daging & Unggas', emoji: '🍗', bgClass: 'bg-[#FCE4EC]' },
  { id: 'seafood', label: 'Seafood', emoji: '🦐', bgClass: 'bg-[#E0F7FA]' },
  { id: 'snack', label: 'Snack & Camilan', emoji: '🍿', bgClass: 'bg-[#FFF3E0]' },
  { id: 'minuman', label: 'Minuman', emoji: '🧃', bgClass: 'bg-[#E8EAF6]' },
  { id: 'bumbu', label: 'Bumbu & Bahan Masak', emoji: '🧄', bgClass: 'bg-[#EFEBE9]' },
  { id: 'roti', label: 'Roti & Kue', emoji: '🍞', bgClass: 'bg-[#FBE9E7]' },
  { id: 'pokok', label: 'Bahan Pokok', emoji: '🍚', bgClass: 'bg-[#F3E5F5]' },
  { id: 'frozen', label: 'Frozen Food', emoji: '🧊', bgClass: 'bg-[#E1F5FE]' },
]

interface StoreOption {
  name: string
  logo: string
}

const storeOptions: StoreOption[] = [
  { name: 'Indomaret', logo: logoIndomaret },
  { name: 'Alfamart', logo: logoAlfamart },
  { name: 'AEON', logo: logoAeon },
  { name: 'Hypermart', logo: logoHypermart },
  { name: 'FamilyMart', logo: logoFamilymart },
  { name: 'Superindo', logo: logoSuperindo },
  { name: 'Transmart', logo: logoTransmart },
  { name: 'Lawson', logo: logoLawson },
  { name: 'Circle K', logo: logoCircleK },
]

const showStoreDropdown = ref(false)

function selectStore(store: StoreOption) {
  belanjaDraft.patchDraft({ toko: store.name })
  showStoreDropdown.value = false
}

// Ejaan alternatif yang umum diketik user, dipetakan ke logo yang sama.
const storeAliases: Record<string, string[]> = {
  Indomaret: ['indomaret', 'indomart'],
  Alfamart: ['alfamart', 'alfa'],
  AEON: ['aeon'],
  Hypermart: ['hypermart', 'hipermart'],
  FamilyMart: ['familymart', 'family mart'],
  Superindo: ['superindo', 'super indo'],
  Transmart: ['transmart', 'trans mart'],
  Lawson: ['lawson'],
  'Circle K': ['circle k', 'circlek'],
}

function getStoreLogo(name: string): string | null {
  if (!name) return null
  const n = name.trim().toLowerCase()
  if (!n) return null
  for (const store of storeOptions) {
    const keys = storeAliases[store.name] ?? [store.name.toLowerCase()]
    if (keys.some((k) => n.includes(k))) return store.logo
  }
  return null
}

function onStoreInputBlur() {
  window.setTimeout(() => {
    showStoreDropdown.value = false
  }, 150)
}

const activeKategori = ref<string | null>(null)
const newItemNama = ref('')
const newItemCatatan = ref('')

function toggleKategori(id: string) {
  activeKategori.value = activeKategori.value === id ? null : id
  newItemNama.value = ''
  newItemCatatan.value = ''
}

let itemCounter = 0

function addItemToKategori(kategoriId: string) {
  if (!newItemNama.value.trim()) return
  const item: BelanjaItem = {
    id: `item-${Date.now()}-${itemCounter++}`,
    nama: newItemNama.value.trim(),
    catatan: newItemCatatan.value.trim(),
    qty: 1,
    kategori: kategoriId,
  }
  belanjaDraft.addItem(item)
  newItemNama.value = ''
  newItemCatatan.value = ''
}

function removeItem(id: string) {
  belanjaDraft.removeItem(id)
}

function incrementQty(id: string) {
  const item = belanjaDraft.draft.items.find((i) => i.id === id)
  if (item) belanjaDraft.updateItemQty(id, item.qty + 1)
}

function decrementQty(id: string) {
  const item = belanjaDraft.draft.items.find((i) => i.id === id)
  if (item) {
    if (item.qty <= 1) {
      removeItem(id)
    } else {
      belanjaDraft.updateItemQty(id, item.qty - 1)
    }
  }
}

function getItemsByKategori(kategoriId: string) {
  return belanjaDraft.draft.items.filter((i) => i.kategori === kategoriId)
}

function getKategoriItemCount(kategoriId: string) {
  return belanjaDraft.draft.items.filter((i) => i.kategori === kategoriId).reduce((sum, i) => sum + i.qty, 0)
}

const totalItems = computed(() => belanjaDraft.draft.items.reduce((sum, i) => sum + i.qty, 0))

const budgetDisplay = computed(() => {
  const b = belanjaDraft.draft.budget
  if (!b) return 'Rp 0'
  return 'Rp ' + b.toLocaleString('id-ID')
})


function onBudgetInput(e: Event) {
  const target = e.target as HTMLInputElement
  const raw = target.value.replace(/\D/g, '')
  belanjaDraft.patchDraft({ budget: raw ? parseInt(raw) : null })
}

function handleLanjut() {
  // For now navigate home (next step: confirm page)
  router.push({ name: 'home' })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-32 overflow-x-hidden">
    <BisaBelanjaNavbar :step="2" />

    <main class="max-w-[430px] mx-auto px-4 pt-5 space-y-5">
      <!-- ========== LOKASI BELANJA ========== -->
      <section class="bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-(--color-outline)/10 p-5 relative z-20">
        <!-- Decorative blob -->
        <div class="absolute -top-8 -right-8 w-32 h-32 bg-(--color-azure)/8 rounded-full blur-2xl pointer-events-none"></div>

        <div class="flex items-center gap-2 mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            width="26"
            height="26"
            class="shrink-0"
          >
            <!-- Location Shopping Icon -->
            <path
              d="M16 3
                 C10.5 3 6 7.5 6 13
                 C6 20.5 16 29 16 29
                 C16 29 26 20.5 26 13
                 C26 7.5 21.5 3 16 3Z"
              fill="#0A326B"
            />
            <!-- Shopping bag -->
            <path
              d="M11 12
                 H21
                 L20 22
                 H12Z"
              fill="#8BC53F"
            />
            <!-- Bag handle -->
            <path
              d="M13 12
                 C13 9 14.2 8 16 8
                 C17.8 8 19 9 19 12"
              fill="none"
              stroke="#8BC53F"
              stroke-width="2"
              stroke-linecap="round"
            />
            <!-- Small bag detail -->
            <path
              d="M14 16H18"
              stroke="#0A326B"
              stroke-width="1.8"
              stroke-linecap="round"
            />
          </svg>
          <h2 class="text-[14px] font-bold text-(--color-on-surface)">Lokasi Belanja</h2>
        </div>

        <div class="space-y-3">
          <!-- Nama Toko dengan Input Warna Putih & Dropdown Logo Populer -->
          <div class="relative">
            <div
              class="relative bg-white rounded-xl border-2 border-slate-200 focus-within:border-(--color-azure) transition-colors flex items-center pr-3 cursor-pointer shadow-xs"
              @click="showStoreDropdown = true"
            >
              <!-- Image Logo Preview dari file assets jika toko Indomaret / Alfamart -->
              <div v-if="getStoreLogo(belanjaDraft.draft.toko)" class="pl-3.5 pt-0.5 shrink-0 flex items-center bg-white">
                <img
                  :src="getStoreLogo(belanjaDraft.draft.toko)!"
                  :alt="belanjaDraft.draft.toko"
                  class="h-8 w-auto max-w-[110px] object-contain bg-white block"
                />
              </div>
              <div class="flex-1 min-w-0">
                <label class="block px-4 pt-2 text-[11px] font-bold text-(--color-outline)">Nama Toko / Supermarket</label>
                <input
                  v-model="belanjaDraft.draft.toko"
                  type="text"
                  placeholder="Pilih atau ketik nama toko..."
                  class="w-full bg-transparent border-none px-4 pb-2.5 pt-0.5 text-sm text-(--color-on-surface) font-semibold focus:ring-0 focus:outline-none placeholder:text-(--color-on-surface-variant)/50"
                  @focus="showStoreDropdown = true"
                  @blur="onStoreInputBlur"
                />
              </div>
              <button
                type="button"
                class="p-1.5 text-(--color-outline) hover:text-(--color-on-surface) transition-colors"
                @click.stop="showStoreDropdown = !showStoreDropdown"
              >
                <Icon name="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': showStoreDropdown }" />
              </button>
            </div>

            <!-- Dropdown Pilihan Toko Populer -->
            <div
              v-if="showStoreDropdown"
              class="absolute z-30 top-[58px] left-0 right-0 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 max-h-[320px] overflow-y-auto animate-in fade-in slide-in-from-top-1 duration-150"
            >
              <div class="px-3.5 py-1.5 text-[10.5px] font-bold text-(--color-outline) uppercase tracking-wider">
                Pilih Toko Populer
              </div>

              <button
                v-for="store in storeOptions"
                :key="store.name"
                type="button"
                class="w-full flex items-center gap-4 px-4 py-3.5 hover:bg-slate-50 transition-colors text-left border-b border-slate-100 last:border-b-0 cursor-pointer"
                @mousedown.prevent="selectStore(store)"
              >
                <div class="w-28 h-10 flex items-center justify-center bg-white shrink-0">
                  <img
                    :src="store.logo"
                    :alt="store.name"
                    class="h-10 w-auto max-w-[120px] object-contain bg-white block"
                  />
                </div>
                <span class="text-[14.5px] font-bold text-(--color-on-surface)">{{ store.name }}</span>
                <span
                  v-if="belanjaDraft.draft.toko.trim().toLowerCase() === store.name.toLowerCase()"
                  class="ml-auto text-(--color-azure)"
                >
                  <Icon name="check" class="w-5 h-5" />
                </span>
              </button>
            </div>
          </div>

          <!-- Alamat (Background Abu-abu & Icon Orange di Kiri) -->
          <div class="relative bg-(--color-surface-container) rounded-xl border-b-2 border-(--color-outline)/20 flex items-center pl-3.5 pr-3">
            <Icon name="pin" class="w-5 h-5 text-orange-500 shrink-0" />
            <div class="flex-1 min-w-0">
              <label class="block px-3 pt-2 text-[11px] font-bold text-(--color-outline)">Alamat</label>
              <input
                v-model="belanjaDraft.draft.alamatToko"
                type="text"
                disabled
                placeholder="Pilih lokasi di peta..."
                class="w-full bg-transparent border-none px-3 pb-2.5 pt-0.5 text-sm text-(--color-on-surface) font-semibold focus:ring-0 focus:outline-none placeholder:text-(--color-on-surface-variant)/50 cursor-not-allowed"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- ========== DAFTAR BELANJA (ASTRO-STYLE) ========== -->
      <section class="bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-(--color-outline)/10 p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 32 32"
              width="26"
              height="26"
              class="shrink-0"
            >
              <!-- Shopping List Icon -->
              <rect
                x="6"
                y="4"
                width="20"
                height="25"
                rx="4"
                fill="#0A326B"
              />
              <!-- Header -->
              <rect
                x="10"
                y="8"
                width="12"
                height="3"
                rx="1.5"
                fill="#8BC53F"
              />
              <!-- Check 1 -->
              <path
                d="M10 15L12 17L15 14"
                fill="none"
                stroke="#8BC53F"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M17 15H22"
                stroke="#FFFFFF"
                stroke-width="2"
                stroke-linecap="round"
              />
              <!-- Check 2 -->
              <path
                d="M10 21L12 23L15 20"
                fill="none"
                stroke="#8BC53F"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <path
                d="M17 21H22"
                stroke="#FFFFFF"
                stroke-width="2"
                stroke-linecap="round"
              />
              <!-- Bottom accent -->
              <rect
                x="10"
                y="25"
                width="12"
                height="2"
                rx="1"
                fill="#8BC53F"
              />
            </svg>
            <h2 class="text-[14px] font-bold text-(--color-on-surface)">Daftar Belanja</h2>
          </div>
          <span
            v-if="totalItems > 0"
            class="text-[11px] font-bold text-(--color-azure) bg-(--color-azure)/10 px-2.5 py-1 rounded-full"
          >
            {{ totalItems }} Item
          </span>
        </div>

        <!-- Category Grid (Astro-style) -->
        <div class="grid grid-cols-4 gap-2 mb-4">
          <button
            v-for="kat in kategoriList"
            :key="kat.id"
            type="button"
            class="relative flex flex-col items-center justify-center rounded-xl p-2.5 transition-all duration-200 active:scale-95 border-2"
            :class="[
              kat.bgClass,
              activeKategori === kat.id
                ? 'border-(--color-azure) shadow-md ring-1 ring-(--color-azure)/30'
                : 'border-transparent hover:shadow-sm',
            ]"
            @click="toggleKategori(kat.id)"
          >
            <!-- Item count badge -->
            <span
              v-if="getKategoriItemCount(kat.id) > 0"
              class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-(--color-azure) text-white text-[10px] font-bold flex items-center justify-center shadow-sm z-10"
            >
              {{ getKategoriItemCount(kat.id) }}
            </span>
            <span class="text-2xl leading-none mb-1">{{ kat.emoji }}</span>
            <span class="text-[9.5px] font-semibold text-(--color-on-surface) leading-tight text-center line-clamp-2">{{ kat.label }}</span>
          </button>
        </div>

        <!-- Expanded Category: item list + add form -->
        <div
          v-if="activeKategori"
          class="border border-(--color-outline)/15 rounded-xl p-4 bg-(--color-surface-container)/40 space-y-3 animate-in fade-in slide-in-from-top-2 duration-200"
        >
          <div class="flex items-center gap-2 mb-1">
            <span class="text-lg">{{ kategoriList.find(k => k.id === activeKategori)?.emoji }}</span>
            <h3 class="text-[13px] font-bold text-(--color-on-surface)">
              {{ kategoriList.find(k => k.id === activeKategori)?.label }}
            </h3>
          </div>

          <!-- Existing items in this category -->
          <ul v-if="getItemsByKategori(activeKategori).length" class="space-y-2">
            <li
              v-for="item in getItemsByKategori(activeKategori)"
              :key="item.id"
              class="flex items-center justify-between p-3 bg-(--color-surface-0) border border-(--color-outline)/15 rounded-xl"
            >
              <div class="flex flex-col flex-1 min-w-0 mr-3">
                <span class="text-[13px] font-semibold text-(--color-on-surface) truncate">{{ item.nama }}</span>
                <span v-if="item.catatan" class="text-[11px] text-(--color-outline) truncate">{{ item.catatan }}</span>
              </div>
              <div class="flex items-center gap-1 bg-(--color-surface-container) rounded-full p-0.5 shrink-0">
                <button
                  type="button"
                  class="w-7 h-7 flex items-center justify-center text-(--color-on-surface) hover:bg-(--color-surface-variant) rounded-full transition-colors"
                  @click="decrementQty(item.id)"
                >
                  <Icon name="minus" class="w-3.5 h-3.5" />
                </button>
                <span class="text-[13px] font-bold min-w-[20px] text-center text-(--color-on-surface)">{{ item.qty }}</span>
                <button
                  type="button"
                  class="w-7 h-7 flex items-center justify-center text-(--color-azure) hover:bg-(--color-surface-variant) rounded-full transition-colors"
                  @click="incrementQty(item.id)"
                >
                  <Icon name="plus" class="w-3.5 h-3.5" />
                </button>
              </div>
            </li>
          </ul>

          <!-- Add item form -->
          <div class="space-y-2">
            <input
              v-model="newItemNama"
              type="text"
              :placeholder="`Nama barang (cth: Susu UHT 1L)`"
              class="w-full bg-(--color-surface-0) border border-(--color-outline)/20 rounded-xl px-3.5 py-2.5 text-[13px] text-(--color-on-surface) focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 transition-all"
              @keyup.enter="addItemToKategori(activeKategori!)"
            />
            <input
              v-model="newItemCatatan"
              type="text"
              placeholder="Catatan (opsional, cth: Full Cream)"
              class="w-full bg-(--color-surface-0) border border-(--color-outline)/20 rounded-xl px-3.5 py-2.5 text-[13px] text-(--color-on-surface) focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 transition-all"
              @keyup.enter="addItemToKategori(activeKategori!)"
            />
            <button
              type="button"
              class="w-full py-2.5 border-2 border-dashed border-(--color-azure)/40 text-(--color-azure) rounded-xl text-[13px] font-bold hover:bg-(--color-azure)/5 active:scale-[0.98] transition-all flex items-center justify-center gap-1.5"
              @click="addItemToKategori(activeKategori!)"
            >
              <Icon name="plus" class="w-4 h-4" />
              Tambah Barang
            </button>
          </div>
        </div>

        <!-- Global item summary -->
        <div v-if="totalItems > 0 && !activeKategori" class="space-y-2 mt-2">
          <template v-for="kat in kategoriList" :key="kat.id">
            <div v-if="getItemsByKategori(kat.id).length" class="border border-(--color-outline)/10 rounded-xl p-3 bg-(--color-surface-container)/30">
              <button
                type="button"
                class="flex items-center gap-2 w-full text-left mb-2"
                @click="toggleKategori(kat.id)"
              >
                <span class="text-base">{{ kat.emoji }}</span>
                <span class="text-[12px] font-bold text-(--color-on-surface) flex-1">{{ kat.label }}</span>
                <span class="text-[11px] font-semibold text-(--color-azure)">{{ getKategoriItemCount(kat.id) }} item</span>
                <Icon name="chevron-right" class="w-3.5 h-3.5 text-(--color-outline)" />
              </button>
              <ul class="space-y-1.5">
                <li
                  v-for="item in getItemsByKategori(kat.id)"
                  :key="item.id"
                  class="flex items-center justify-between px-2 py-1.5 rounded-lg"
                >
                  <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="w-5 h-5 rounded-full bg-(--color-azure)/10 text-(--color-azure) text-[10px] font-bold flex items-center justify-center shrink-0">{{ item.qty }}</span>
                    <span class="text-[12px] text-(--color-on-surface) truncate">{{ item.nama }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </template>
        </div>

        <!-- Empty state -->
        <div v-if="totalItems === 0 && !activeKategori" class="text-center py-6">
          <p class="text-[12px] text-(--color-outline)">Pilih kategori di atas untuk mulai menambah barang belanja</p>
        </div>
      </section>

      <!-- ========== ESTIMASI BUDGET ========== -->
      <section class="bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-(--color-outline)/10 p-5">
        <div class="flex items-center gap-2 mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            width="26"
            height="26"
            class="shrink-0"
          >
            <!-- Estimasi Budget Barang / Calculator Icon -->
            <!-- Calculator body -->
            <rect
              x="6"
              y="3"
              width="20"
              height="26"
              rx="4"
              fill="#0A326B"
            />
            <!-- Display -->
            <rect
              x="10"
              y="7"
              width="12"
              height="5"
              rx="1.5"
              fill="#FFFFFF"
            />
            <!-- Display amount -->
            <path
              d="M13 9.5H19"
              stroke="#8BC53F"
              stroke-width="2"
              stroke-linecap="round"
            />
            <!-- Calculator buttons -->
            <rect x="10" y="15" width="4" height="3" rx="1" fill="#8BC53F" />
            <rect x="16" y="15" width="4" height="3" rx="1" fill="#FFFFFF" />
            <rect x="10" y="20" width="4" height="3" rx="1" fill="#FFFFFF" />
            <rect x="16" y="20" width="4" height="3" rx="1" fill="#8BC53F" />
            <!-- Currency symbol -->
            <path
              d="M22 15
                 C24 15 25 16 25 17
                 C25 18.5 24 19 22 19
                 C20 19 19 20 19 21
                 C19 22.5 20.5 23 22 23"
              fill="none"
              stroke="#8BC53F"
              stroke-width="1.8"
              stroke-linecap="round"
            />
          </svg>
          <h2 class="text-[14px] font-bold text-(--color-on-surface)">Estimasi Budget Barang</h2>
        </div>

        <div class="relative bg-white rounded-xl border-2 border-slate-200 focus-within:border-(--color-azure) transition-colors flex items-center px-4 shadow-xs">
          <span class="text-[14px] font-bold text-(--color-on-surface) mr-2">Rp</span>
          <input
            type="text"
            inputmode="numeric"
            :value="belanjaDraft.draft.budget?.toLocaleString('id-ID') ?? ''"
            placeholder="0"
            class="w-full bg-transparent border-none py-3.5 text-[20px] font-bold text-(--color-on-surface) focus:ring-0 focus:outline-none placeholder:text-(--color-on-surface-variant)/40"
            @input="onBudgetInput"
          />
        </div>

        <div class="flex items-start gap-2 mt-3 bg-(--color-azure)/8 p-3 rounded-xl">
          <Icon name="info" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
          <p class="text-[11px] text-(--color-on-surface-variant) leading-relaxed">
            Ini adalah estimasi total harga barang yang akan dibeli. <strong>Biaya jasa dan ongkir akan dihitung terpisah</strong> pada halaman berikutnya.
          </p>
        </div>
      </section>

      <!-- ========== INSTRUKSI KHUSUS ========== -->
      <section class="bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-(--color-outline)/10 p-5">
        <div class="flex items-center gap-2 mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 32 32"
            width="26"
            height="26"
            class="shrink-0"
          >
            <!-- Instruksi Khusus / Clipboard + Pencil Icon -->
            <!-- Clipboard -->
            <rect
              x="6"
              y="5"
              width="20"
              height="24"
              rx="4"
              fill="#0A326B"
            />
            <!-- Clipboard top -->
            <rect
              x="11"
              y="3"
              width="10"
              height="6"
              rx="2.5"
              fill="#8BC53F"
            />
            <!-- Text lines -->
            <rect
              x="10"
              y="12"
              width="10"
              height="2.5"
              rx="1.25"
              fill="#FFFFFF"
            />
            <rect
              x="10"
              y="17"
              width="8"
              height="2.5"
              rx="1.25"
              fill="#FFFFFF"
            />
            <rect
              x="10"
              y="22"
              width="6"
              height="2.5"
              rx="1.25"
              fill="#8BC53F"
            />
            <!-- Pencil -->
            <g transform="rotate(-42 21 21)">
              <rect
                x="19"
                y="14"
                width="5"
                height="13"
                rx="1"
                fill="#8BC53F"
              />
              <!-- Pencil tip -->
              <path
                d="M19 27L21.5 30L24 27Z"
                fill="#F0C48A"
              />
              <!-- Pencil tip point -->
              <path
                d="M21.5 30L21.5 28.5"
                stroke="#0A326B"
                stroke-width="1.2"
                stroke-linecap="round"
              />
              <!-- Pencil highlight -->
              <path
                d="M20.5 16V25"
                stroke="#DDF59A"
                stroke-width="1"
                stroke-linecap="round"
              />
            </g>
          </svg>
          <h2 class="text-[14px] font-bold text-(--color-on-surface)">Instruksi Khusus (Opsional)</h2>
        </div>

        <textarea
          v-model="belanjaDraft.draft.instruksi"
          placeholder="Cth: Tolong cari telur yang expired datenya masih lama ya, jangan yang retak."
          rows="3"
          class="w-full bg-white border-2 border-slate-200 rounded-xl px-4 py-3 text-[13px] text-(--color-on-surface) font-semibold focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 resize-none transition-all shadow-xs"
        ></textarea>
      </section>
    </main>

    <!-- ========== STICKY BOTTOM BAR ========== -->
    <footer class="fixed bottom-0 w-full z-50 bg-white/95 backdrop-blur-xl border-t border-(--color-surface-variant) shadow-[0_-20px_60px_rgba(0,0,0,0.08)] rounded-t-[1.5rem]">
      <div class="max-w-[430px] mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex flex-col">
          <span class="text-[11px] font-medium text-(--color-outline)">Estimasi Total</span>
          <span class="text-[20px] font-bold text-(--color-on-surface) leading-tight">{{ budgetDisplay }}</span>
        </div>
        <button
          type="button"
          class="flex-1 bg-(--color-azure) text-white rounded-full py-3.5 text-[14px] font-bold shadow-md shadow-(--color-azure)/20 hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2"
          @click="handleLanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </footer>
  </div>
</template>
