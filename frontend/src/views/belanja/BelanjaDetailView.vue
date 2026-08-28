<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import BisaBelanjaNavbar from '@/components/belanja/BisaBelanjaNavbar.vue'
import BisaBelanjaSearch from '@/components/belanja/BisaBelanjaSearch.vue'
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
import imgBuah from '@/assets/BuahSegar.jpg'
import imgSayur from '@/assets/SayurSegar.jpg'
import imgSusu from '@/assets/SusuOlahan.jpg'
import imgDaging from '@/assets/DagingUnggas.jpg'
import imgSeafood from '@/assets/Seafood.jpg'
import imgSnack from '@/assets/SnackCemilan.jpg'
import imgMinuman from '@/assets/Minuman.jpg'
import imgBumbu from '@/assets/BumbuBahanMasak.jpg'
import imgRoti from '@/assets/RotiKue.jpg'
import imgFrozen from '@/assets/FrozenFood.jpg'
import imgPokok from '@/assets/BahanPokok.jpg'
import imgTelur from '@/assets/Telur, Tahu & Tempe.jpg'
import { KATALOG_BY_KATEGORI } from '@/lib/belanja/belanjaKatalog'
import { useSkeleton } from '@/composables/useSkeleton'
import BelanjaDetailSkeleton from '@/components/skeleton/BelanjaDetailSkeleton.vue'

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
  image?: string
}

const kategoriList: Kategori[] = [
  { id: 'buah', label: 'Buah Segar', emoji: '🍎', image: imgBuah },
  { id: 'sayur', label: 'Sayur Segar', emoji: '🥦', image: imgSayur },
  { id: 'susu', label: 'Susu & Olahan', emoji: '🥛', image: imgSusu },
  { id: 'telur', label: 'Telur, Tahu & Tempe', emoji: '🥚', image: imgTelur },
  { id: 'daging', label: 'Daging & Unggas', emoji: '🍗', image: imgDaging },
  { id: 'seafood', label: 'Seafood', emoji: '🦐', image: imgSeafood },
  { id: 'snack', label: 'Snack & Camilan', emoji: '🍿', image: imgSnack },
  { id: 'minuman', label: 'Minuman', emoji: '🧃', image: imgMinuman },
  { id: 'bumbu', label: 'Bumbu & Bahan Masak', emoji: '🧄', image: imgBumbu },
  { id: 'roti', label: 'Roti & Kue', emoji: '🍞', image: imgRoti },
  { id: 'pokok', label: 'Bahan Pokok', emoji: '🍚', image: imgPokok },
  { id: 'frozen', label: 'Frozen Food', emoji: '🧊', image: imgFrozen },
  // Tanpa katalog: sengaja, supaya toggleKategori() membuka form ketik manual
  // untuk barang yang tidak ada di daftar produk.
  { id: 'lainnya', label: 'Lainnya', emoji: '📝' },
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
  { name: 'Lainnya', logo: '' },
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
const newItemQty = ref(1)

/**
 * Kategori yang sudah punya katalog produk dibuka sebagai halaman tersendiri
 * (pilih dari daftar + harga). Sisanya tetap memakai form ketik manual inline
 * sampai katalognya tersedia.
 */
function toggleKategori(id: string) {
  if (KATALOG_BY_KATEGORI[id]?.length) {
    router.push({ name: 'task-belanja-kategori', params: { kategori: id } })
    return
  }
  activeKategori.value = activeKategori.value === id ? null : id
  newItemNama.value = ''
  newItemCatatan.value = ''
  newItemQty.value = 1
}

let itemCounter = 0

function addItemToKategori(kategoriId: string) {
  if (!newItemNama.value.trim()) return
  const item: BelanjaItem = {
    id: `item-${Date.now()}-${itemCounter++}`,
    nama: newItemNama.value.trim(),
    catatan: newItemCatatan.value.trim(),
    qty: newItemQty.value,
    kategori: kategoriId,
  }
  belanjaDraft.addItem(item)
  newItemNama.value = ''
  newItemCatatan.value = ''
  newItemQty.value = 1
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

/**
 * Total harga barang yang sudah dimasukkan customer. Menggantikan input budget
 * manual — angkanya sekarang dihitung dari isi keranjang, bukan diketik sendiri.
 * Item ketik manual (lewat menu Lainnya) belum punya harga, jadi dihitung 0 dan
 * dijelaskan lewat `itemTanpaHarga`.
 */
const totalHarga = computed(() =>
  belanjaDraft.draft.items.reduce((sum, i) => sum + (i.harga ?? 0) * i.qty, 0),
)

const totalDisplay = computed(() => 'Rp' + totalHarga.value.toLocaleString('id-ID'))

const itemTanpaHarga = computed(() => belanjaDraft.draft.items.filter((i) => i.harga == null).length)

function onBudgetInput(e: Event) {
  const target = e.target as HTMLInputElement
  const raw = target.value.replace(/\D/g, '')
  belanjaDraft.patchDraft({ budget: raw ? parseInt(raw) : null })
}

function handleLanjut() {
  router.push({ name: 'task-belanja-checkout' })
}

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
onMounted(() => requestAnimationFrame(() => requestAnimationFrame(() => tandaiSiap())))
</script>

<template>
  <BelanjaDetailSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-32 overflow-x-hidden">
      <BisaBelanjaNavbar :step="2" />
      <BisaBelanjaSearch />
  
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
                      v-if="store.logo"
                      :src="store.logo"
                      :alt="store.name"
                      class="h-10 w-auto max-w-[120px] object-contain bg-white block"
                    />
                    <Icon v-else name="grid" class="w-5 h-5 text-slate-400" />
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
  
          </div>
  
          <!-- Category Grid (Astro-style) -->
          <div class="grid grid-cols-4 gap-2 mb-4">
            <button
              v-for="kat in kategoriList"
              :key="kat.id"
              type="button"
              class="relative flex flex-col items-center justify-center rounded-xl p-2.5 transition-all duration-200 active:scale-95 border-2 bg-transparent"
              :class="[
                activeKategori === kat.id
                  ? 'border-(--color-azure) shadow-md ring-1 ring-(--color-azure)/30'
                  : 'border-slate-100 hover:border-slate-200 hover:shadow-sm',
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
              <img
                v-if="kat.image"
                :src="kat.image"
                :alt="kat.label"
                class="w-14 h-14 object-contain mb-1"
              />
              <span v-else class="text-3xl leading-none mb-1">{{ kat.emoji }}</span>
              <span class="text-[9.5px] font-semibold text-(--color-on-surface) leading-tight text-center line-clamp-2">{{ kat.label }}</span>
            </button>
          </div>
  
          <!-- Expanded Category: item list + add form -->
          <div
            v-if="activeKategori"
            class="border border-(--color-outline)/15 rounded-xl p-4 bg-(--color-surface-container)/40 space-y-3 animate-in fade-in slide-in-from-top-2 duration-200"
          >
            <div class="flex items-center gap-2 mb-1">
              <img v-if="kategoriList.find(k => k.id === activeKategori)?.image" :src="kategoriList.find(k => k.id === activeKategori)?.image" :alt="kategoriList.find(k => k.id === activeKategori)?.label" class="w-8 h-8 object-contain" />
              <span v-else class="text-xl">{{ kategoriList.find(k => k.id === activeKategori)?.emoji }}</span>
              <h3 class="text-[13px] font-bold text-(--color-on-surface)">
                {{ kategoriList.find(k => k.id === activeKategori)?.label }}
              </h3>
            </div>
  
            <!-- Existing items in this category -->
            <ul v-if="getItemsByKategori(activeKategori).length" class="space-y-2">
              <li
                v-for="item in getItemsByKategori(activeKategori)"
                :key="item.id"
                class="flex items-center justify-between p-3 bg-white border border-white rounded-xl"
              >
                <div class="flex flex-col flex-1 min-w-0 mr-3">
                  <span class="text-[13px] font-semibold text-(--color-on-surface) truncate">{{ item.nama }}</span>
                  <span v-if="item.catatan" class="text-[11px] text-(--color-outline) truncate">{{ item.catatan }}</span>
                </div>
                <div class="flex items-center gap-1 bg-white border-2 border-white rounded-full p-0.5 shrink-0">
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
                class="w-full bg-white border border-white rounded-xl px-3.5 py-2.5 text-[13px] text-(--color-on-surface) focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 transition-all"
                @keyup.enter="addItemToKategori(activeKategori!)"
              />
              <input
                v-model="newItemCatatan"
                type="text"
                placeholder="Catatan (opsional, cth: Full Cream)"
                class="w-full bg-white border border-white rounded-xl px-3.5 py-2.5 text-[13px] text-(--color-on-surface) focus:ring-1 focus:ring-(--color-azure) focus:border-(--color-azure) focus:outline-none placeholder:text-(--color-on-surface-variant)/50 transition-all"
                @keyup.enter="addItemToKategori(activeKategori!)"
              />
  
              <!-- Jumlah barang: barang ketik manual tidak punya stepper di kartu
                   katalog, jadi qty-nya ditentukan di sini sebelum ditambahkan. -->
              <div class="flex items-center justify-between bg-white border border-white rounded-xl px-3.5 py-2">
                <span class="text-[13px] font-semibold text-(--color-on-surface)">Jumlah</span>
                <div class="flex items-center gap-1 bg-white border-2 border-white rounded-full p-0.5">
                  <button
                    type="button"
                    aria-label="Kurangi jumlah"
                    class="w-7 h-7 flex items-center justify-center text-(--color-on-surface) rounded-full active:scale-90 transition-transform disabled:opacity-40"
                    :disabled="newItemQty <= 1"
                    @click="newItemQty = Math.max(1, newItemQty - 1)"
                  >
                    <Icon name="minus" class="w-3.5 h-3.5" />
                  </button>
                  <span class="text-[13px] font-bold min-w-[24px] text-center text-(--color-on-surface)">{{ newItemQty }}</span>
                  <button
                    type="button"
                    aria-label="Tambah jumlah"
                    class="w-7 h-7 flex items-center justify-center text-(--color-azure) rounded-full active:scale-90 transition-transform"
                    @click="newItemQty = newItemQty + 1"
                  >
                    <Icon name="plus" class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
  
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
  
          <!-- Empty state -->
          <div v-if="totalItems === 0 && !activeKategori" class="text-center py-6">
            <p class="text-[12px] text-(--color-outline)">Pilih kategori di atas untuk mulai menambah barang belanja</p>
          </div>
        </section>
  
  
        <!-- ========== ESTIMASI BUDGET (khusus menu Lainnya) ==========
             Barang ketik manual tidak punya harga katalog, jadi total di footer
             tidak bisa mencerminkannya. Section ini muncul hanya saat menu
             Lainnya dibuka supaya customer bisa memberi ancar-ancar budget. -->
        <section
          v-if="activeKategori === 'lainnya'"
          class="bg-(--color-surface-0) rounded-2xl shadow-[0_4px_20px_rgba(0,0,0,0.05)] border border-(--color-outline)/10 p-5"
        >
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
              Barang yang ditulis lewat menu Lainnya belum punya harga, jadi tidak masuk hitungan total. Isi ancar-ancar budgetnya di sini supaya mitra tahu batas belanjanya. <strong>Biaya jasa dan ongkir dihitung terpisah</strong> pada halaman berikutnya.
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
          <div class="flex items-center gap-2.5 min-w-0">
            <!-- Ikon keranjang duduk di samping angka total, bukan di navbar:
                 di sinilah jumlah & harga barang dibaca, jadi jalan menuju rincian
                 keranjang ada tepat di sebelahnya. -->
            <button
              type="button"
              aria-label="Lihat keranjang"
              class="relative w-10 h-10 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
              @click="router.push({ name: 'task-belanja-keranjang' })"
            >
              <Icon name="cart" class="w-5 h-5 text-(--color-on-surface)" />
              <span
                v-if="totalItems > 0"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-(--color-azure) text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white"
              >
                {{ totalItems }}
              </span>
            </button>
            <div class="flex flex-col min-w-0">
              <span class="text-[11px] font-medium text-(--color-outline)">
                Total {{ totalItems }} barang
                <span v-if="itemTanpaHarga" class="text-(--color-azure)">&bull; {{ itemTanpaHarga }} tanpa harga</span>
              </span>
              <span class="text-[20px] font-bold text-(--color-on-surface) leading-tight truncate">{{ totalDisplay }}</span>
            </div>
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
</template>
