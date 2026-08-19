<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import KatalogSkeleton from '@/components/belanja/KatalogSkeleton.vue'
import { useBelanjaDraftStore } from '@/stores/belanjaDraft'
import { KATALOG_BY_KATEGORI, adaPromo, hargaFinal, hargaNormal, type KatalogItem } from '@/lib/belanjaKatalog'
import { useSkeleton } from '@/composables/useSkeleton'
import KategoriSkeleton from '@/components/skeleton/KategoriSkeleton.vue'

const route = useRoute()
const router = useRouter()
const belanjaDraft = useBelanjaDraftStore()

const kategoriId = computed(() => String(route.params.kategori ?? ''))

const KATEGORI_LABEL: Record<string, string> = {
  buah: 'Buah Segar',
  sayur: 'Sayur Segar',
  susu: 'Susu & Olahan',
  telur: 'Telur, Tahu & Tempe',
  daging: 'Daging & Unggas',
  seafood: 'Seafood',
  snack: 'Snack & Camilan',
  minuman: 'Minuman',
  bumbu: 'Bumbu & Bahan Masak',
  roti: 'Roti & Kue',
  pokok: 'Bahan Pokok',
  frozen: 'Frozen Food',
}

const judul = computed(() => KATEGORI_LABEL[kategoriId.value] ?? 'Kategori')
const katalog = computed<KatalogItem[]>(() => KATALOG_BY_KATEGORI[kategoriId.value] ?? [])

const keyword = ref('')

// ---- sub-filter chips -------------------------------------------------------
const FILTER_SEMUA = '__semua__'
const FILTER_FAVORIT = '__favorit__'
const FILTER_KEYWORD_PREFIX = '__kw__'

const filterAktif = ref(FILTER_SEMUA)

/** Daftar unik nama grup, urutan mengikuti kemunculan pertama di katalog. */
const daftarGrup = computed(() => {
  const seen = new Set<string>()
  const out: string[] = []
  for (const item of katalog.value) {
    if (!seen.has(item.grup)) {
      seen.add(item.grup)
      out.push(item.grup)
    }
  }
  return out
})

/**
 * Sub-filter tambahan berbasis keyword per kategori.
 * Key = prefix + keyword, cocokkan ke nama produk (case-insensitive).
 */
const KEYWORD_FILTERS: Record<string, { label: string; keyword: string }[]> = {
  buah: [
    { label: 'Pisang', keyword: 'pisang' },
    { label: 'Jeruk', keyword: 'jeruk' },
    { label: 'Apel', keyword: 'apel' },
    { label: 'Pear', keyword: 'pear' },
    { label: 'Mangga', keyword: 'mangga' },
    { label: 'Anggur', keyword: 'anggur' },
    { label: 'Melon', keyword: 'melon' },
    { label: 'Semangka', keyword: 'semangka' },
    { label: 'Stroberi', keyword: 'strawberry' },
    { label: 'Kiwi', keyword: 'kiwi' },
    { label: 'Durian', keyword: 'durian' },
    { label: 'Kurma', keyword: 'kurma' },
    { label: 'Buah Naga', keyword: 'naga' },
    { label: 'Alpukat', keyword: 'alpukat' },
    { label: 'Nanas', keyword: 'nanas' },
    { label: 'Kelapa', keyword: 'kelapa' },
    { label: 'Salak', keyword: 'salak' },
    { label: 'Jambu', keyword: 'jambu' },
    { label: 'Lemon', keyword: 'lemon' },
    { label: 'Sirsak', keyword: 'sirsak' },
    { label: 'Manggis', keyword: 'manggis' },
    { label: 'Blueberry', keyword: 'blueberry' },
  ],
  snack: [
    { label: 'Popcorn', keyword: 'popcorn' },
    { label: 'Rumput Laut', keyword: 'seaweed' },
    { label: 'Kacang', keyword: 'kacang' },
    { label: 'Almond & Cashew', keyword: 'almond' },
    { label: 'Keripik', keyword: 'keripik' },
    { label: 'Chitato & Lays', keyword: 'chitato' },
    { label: 'Pringles', keyword: 'pringles' },
    { label: 'Brownies & Cookies', keyword: 'brownies' },
    { label: 'Basreng & Mie Lidi', keyword: 'basreng' },
  ],
  minuman: [
    { label: 'AQUA', keyword: 'aqua' },
    { label: 'Le Minerale', keyword: 'le minerale' },
    { label: 'Pristine', keyword: 'pristine' },
    { label: 'Evian', keyword: 'evian' },
    { label: 'Teh Botol', keyword: 'sosro' },
    { label: 'Teh Pucuk', keyword: 'teh pucuk' },
    { label: 'Coca-Cola', keyword: 'coca-cola' },
    { label: 'Sprite & Fanta', keyword: 'sprite' },
    { label: 'Pocari Sweat', keyword: 'pocari' },
    { label: 'Kopi & Susu', keyword: 'milo' },
    { label: 'Kelapa', keyword: 'kelapa' },
    { label: 'Kombucha', keyword: 'kombucha' },
  ],
  pokok: [
    { label: 'Indomie', keyword: 'indomie' },
    { label: 'Mie Sedaap', keyword: 'sedaap' },
    { label: 'Samyang', keyword: 'samyang' },
    { label: 'Pop Mie', keyword: 'pop mie' },
    { label: 'Beras Porang', keyword: 'porang' },
    { label: 'Beras Organik', keyword: 'organik' },
    { label: 'Gula Aren', keyword: 'gula aren' },
    { label: 'Garam', keyword: 'garam' },
    { label: 'Minyak Goreng', keyword: 'minyak goreng' },
    { label: 'Olive Oil', keyword: 'olive oil' },
    { label: 'Margarin', keyword: 'margarin' },
    { label: 'Spaghetti', keyword: 'spaghetti' },
  ],
  bumbu: [
    { label: 'Santan', keyword: 'santan' },
    { label: 'Kaldu Bubuk', keyword: 'kaldu' },
    { label: 'Bamboe', keyword: 'bamboe' },
    { label: 'Bumbu Racik', keyword: 'racik' },
    { label: 'Kecap Bango', keyword: 'bango' },
    { label: 'Saus Saori', keyword: 'saori' },
    { label: 'Sambal', keyword: 'sambal' },
    { label: 'Mayo & Dressing', keyword: 'dressing' },
    { label: 'Tepung Bumbu', keyword: 'tepung' },
    { label: 'Kerupuk', keyword: 'kerupuk' },
    { label: 'Nori', keyword: 'nori' },
    { label: 'Sarden & Tuna', keyword: 'sard' },
  ],
  roti: [
    { label: 'Sari Roti', keyword: 'sari roti' },
    { label: 'Roti Tawar', keyword: 'tawar' },
    { label: 'Croissant', keyword: 'croissant' },
    { label: 'Bagel', keyword: 'bagel' },
    { label: 'Egg Tart', keyword: 'egg tart' },
    { label: 'Paratha', keyword: 'paratha' },
    { label: 'Mochi & Tteok', keyword: 'mochi' },
    { label: 'Cheesecake', keyword: 'cheesecake' },
    { label: 'Muffin', keyword: 'muffin' },
    { label: 'Tepung Terigu', keyword: 'terigu' },
    { label: 'Ragi & Pengembang', keyword: 'ragi' },
    { label: 'Cokelat & Kakao', keyword: 'coco' },
  ],
}

const filterChips = computed(() => {
  const chips: { key: string; label: string }[] = [
    { key: FILTER_SEMUA, label: 'Semua Kategori' },
    { key: FILTER_FAVORIT, label: 'Produk Favorit' },
  ]
  // Grup-based filters
  for (const g of daftarGrup.value) {
    chips.push({ key: g, label: g })
  }
  // Keyword-based filters (fruit types etc.)
  const kwFilters = KEYWORD_FILTERS[kategoriId.value]
  if (kwFilters) {
    for (const kw of kwFilters) {
      chips.push({ key: FILTER_KEYWORD_PREFIX + kw.keyword, label: kw.label })
    }
  }
  return chips
})

function pilihFilter(key: string) {
  filterAktif.value = key
  // Scroll active chip into view
  nextTick(() => {
    const container = chipContainer.value
    if (!container) return
    const active = container.querySelector('.chip-active') as HTMLElement | null
    if (active) {
      active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
    }
    updateArrows()
  })
}

// ---- scroll arrows ----------------------------------------------------------
const chipContainer = ref<HTMLElement | null>(null)
const showLeftArrow = ref(false)
const showRightArrow = ref(true)

function updateArrows() {
  const el = chipContainer.value
  if (!el) return
  showLeftArrow.value = el.scrollLeft > 8
  showRightArrow.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8
}

function scrollChips(direction: 'left' | 'right') {
  const el = chipContainer.value
  if (!el) return
  const scrollAmount = 180
  el.scrollBy({ left: direction === 'right' ? scrollAmount : -scrollAmount, behavior: 'smooth' })
  setTimeout(updateArrows, 350)
}

onMounted(() => {
  mulaiRenderBertahap()
  nextTick(updateArrows)
  chipContainer.value?.addEventListener('scroll', updateArrows, { passive: true })
})

onBeforeUnmount(() => {
  if (framePending) cancelAnimationFrame(framePending)
  chipContainer.value?.removeEventListener('scroll', updateArrows)
})

// Reset filter when kategori changes
watch(kategoriId, () => {
  mulaiRenderBertahap()
  filterAktif.value = FILTER_SEMUA
  nextTick(updateArrows)
})

// ---- pencarian + filter gabungan -------------------------------------------
const hasil = computed(() => {
  let pool = katalog.value

  // 1. Terapkan sub-filter terlebih dahulu
  if (filterAktif.value === FILTER_FAVORIT) {
    const idsInCart = new Set(belanjaDraft.draft.items.map((i) => i.id))
    pool = pool.filter((i) => idsInCart.has(i.id))
  } else if (filterAktif.value.startsWith(FILTER_KEYWORD_PREFIX)) {
    const kw = filterAktif.value.slice(FILTER_KEYWORD_PREFIX.length).toLowerCase()
    pool = pool.filter((i) => i.nama.toLowerCase().includes(kw))
  } else if (filterAktif.value !== FILTER_SEMUA) {
    pool = pool.filter((i) => i.grup === filterAktif.value)
  }

  // 2. Terapkan pencarian keyword
  const q = keyword.value.trim().toLowerCase()
  if (!q) return pool
  const cocokNama = pool.filter((i) => i.nama.toLowerCase().includes(q))
  if (cocokNama.length) return cocokNama
  return pool.filter((i) => i.grup.toLowerCase().includes(q))
})

/** Hasil pencarian dikelompokkan per grup, urutan grup mengikuti katalog. */
const grupTampil = computed(() => {
  const map = new Map<string, KatalogItem[]>()
  for (const item of hasil.value) {
    const list = map.get(item.grup) ?? []
    list.push(item)
    map.set(item.grup, list)
  }
  return Array.from(map, ([nama, items]) => ({ nama, items }))
})

/**
 * Render grup secara bertahap, 2 grup per frame.
 *
 * Menggambar seluruh katalog sekaligus memblokir ~4 detik di CPU kelas menengah
 * (diukur pada Bahan Pokok, 565 produk) — layar kosong selama itu. Dengan
 * dipecah, grup pertama tampil hampir seketika dan sisanya menyusul sambil
 * skeleton mengisi ruang di bawahnya.
 */
const GRUP_PER_FRAME = 2
const grupSiap = ref(GRUP_PER_FRAME)
let framePending = 0

const grupTerlihat = computed(() => grupTampil.value.slice(0, grupSiap.value))
const masihMemuat = computed(() => grupSiap.value < grupTampil.value.length)

function mulaiRenderBertahap() {
  if (framePending) cancelAnimationFrame(framePending)
  grupSiap.value = GRUP_PER_FRAME
  const lanjut = () => {
    if (grupSiap.value >= grupTampil.value.length) {
      framePending = 0
      return
    }
    grupSiap.value += GRUP_PER_FRAME
    framePending = requestAnimationFrame(lanjut)
  }
  framePending = requestAnimationFrame(lanjut)
}

// ---- keranjang -------------------------------------------------------------
function itemDiKeranjang(katalogId: string) {
  return belanjaDraft.draft.items.find((i) => i.id === katalogId)
}

function qtyOf(katalogId: string) {
  return itemDiKeranjang(katalogId)?.qty ?? 0
}

function tambah(item: KatalogItem) {
  // Pengaman: item habis tidak boleh masuk keranjang walau tombolnya tertekan.
  if (item.habis) return
  const existing = itemDiKeranjang(item.id)
  if (existing) {
    belanjaDraft.updateItemQty(item.id, existing.qty + 1)
    return
  }
  belanjaDraft.addItem({
    id: item.id,
    nama: item.nama,
    catatan: '',
    qty: 1,
    kategori: kategoriId.value,
    harga: hargaFinal(item, kategoriId.value),
    satuan: item.satuan,
  })
}

function kurang(item: KatalogItem) {
  const existing = itemDiKeranjang(item.id)
  if (!existing) return
  if (existing.qty <= 1) {
    belanjaDraft.removeItem(item.id)
  } else {
    belanjaDraft.updateItemQty(item.id, existing.qty - 1)
  }
}

const totalItem = computed(() => belanjaDraft.draft.items.reduce((s, i) => s + i.qty, 0))

const totalHarga = computed(() =>
  belanjaDraft.draft.items.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0),
)

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

function kembali() {
  if (window.history.state?.back) router.back()
  else router.push({ name: 'task-belanja-detail' })
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
  <KategoriSkeleton v-if="skelTampil" />
  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-40 overflow-x-hidden">
      <!-- Top app bar -->
      <header class="sticky top-0 z-40 glass-panel border-b border-(--color-outline)/30">
        <div class="max-w-[430px] mx-auto flex items-center gap-2 px-4 h-16">
          <button
            type="button"
            class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            aria-label="Kembali"
            @click="kembali"
          >
            <Icon name="arrow-left" class="w-5 h-5 text-(--color-on-surface)" />
          </button>
          <h1 class="font-display font-extrabold text-[17px] flex-1 text-center truncate">{{ judul }}</h1>
          <span class="w-10 h-10 shrink-0"></span>
        </div>
      </header>
  
      <main class="max-w-[430px] mx-auto px-4 pt-4">
        <!-- Search -->
        <div class="relative mb-4">
          <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <Icon name="search" class="w-4.5 h-4.5 text-(--color-on-surface-variant)" />
          </span>
          <input
            v-model="keyword"
            type="text"
            :placeholder="`Cari di ${judul.toLowerCase()}...`"
            class="w-full pl-11 pr-10 py-3 bg-(--color-surface-container) rounded-full outline-none text-sm text-(--color-on-surface) placeholder:text-(--color-on-surface-variant)"
          />
          <button
            v-if="keyword"
            type="button"
            class="absolute inset-y-0 right-0 pr-4 flex items-center"
            aria-label="Hapus pencarian"
            @click="keyword = ''"
          >
            <Icon name="x" class="w-4 h-4 text-(--color-on-surface-variant)" />
          </button>
        </div>
  
        <!-- Sub-filter chips with scroll arrows -->
        <div class="relative mb-4 -mx-4">
          <!-- Left arrow -->
          <transition name="fade">
            <button
              v-if="showLeftArrow"
              type="button"
              class="absolute left-0 top-0 bottom-0 z-10 w-8 flex items-center justify-center bg-gradient-to-r from-(--color-surface) via-(--color-surface)/90 to-transparent"
              aria-label="Geser filter ke kiri"
              @click="scrollChips('left')"
            >
              <Icon name="chevron-left" class="w-4.5 h-4.5 text-(--color-on-surface)" />
            </button>
          </transition>
  
          <!-- Chip container -->
          <div
            ref="chipContainer"
            class="flex items-center gap-2 overflow-x-auto no-scrollbar px-4"
          >
            <button
              v-for="chip in filterChips"
              :key="chip.key"
              type="button"
              class="shrink-0 px-4 py-2 rounded-full text-[12px] font-semibold border transition-all duration-200 whitespace-nowrap active:scale-95"
              :class="[
                filterAktif === chip.key
                  ? 'bg-(--color-azure) text-white border-(--color-azure) shadow-sm chip-active'
                  : 'bg-(--color-surface-0) text-(--color-on-surface-variant) border-(--color-outline)/30 hover:border-(--color-azure)/50',
              ]"
              @click="pilihFilter(chip.key)"
            >
              {{ chip.label }}
            </button>
          </div>
  
          <!-- Right arrow -->
          <transition name="fade">
            <button
              v-if="showRightArrow"
              type="button"
              class="absolute right-0 top-0 bottom-0 z-10 w-8 flex items-center justify-center bg-gradient-to-l from-(--color-surface) via-(--color-surface)/90 to-transparent"
              aria-label="Geser filter ke kanan"
              @click="scrollChips('right')"
            >
              <Icon name="chevron-right" class="w-4.5 h-4.5 text-(--color-on-surface)" />
            </button>
          </transition>
        </div>
  
        <!-- Katalog belum tersedia -->
        <div v-if="!katalog.length" class="text-center py-16">
          <p class="text-[13px] text-(--color-on-surface-variant)">
            Katalog untuk kategori ini belum tersedia.
          </p>
        </div>
  
        <!-- Tidak ada hasil pencarian -->
        <div v-else-if="!hasil.length" class="text-center py-16">
          <p class="text-[13px] text-(--color-on-surface-variant)">
            Tidak ada hasil untuk &ldquo;{{ keyword }}&rdquo;.
          </p>
        </div>
  
        <!-- Grid produk per grup, digambar bertahap -->
        <div v-else class="flex flex-col gap-6">
          <section v-for="grup in grupTerlihat" :key="grup.nama">
            <h2 class="text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-2.5">
              {{ grup.nama }}
            </h2>
            <div class="grid grid-cols-3 gap-2.5">
              <div
                v-for="item in grup.items"
                :key="item.id"
                class="relative bg-(--color-surface-0) rounded-2xl p-2 border transition-colors"
                :class="[
                  qtyOf(item.id) > 0 ? 'border-(--color-azure)' : 'border-(--color-outline)/20',
                  item.habis ? 'opacity-60' : '',
                ]"
              >
                <!-- visual + tombol tambah mengambang di kanan atas -->
                <div class="relative w-full aspect-square rounded-xl bg-(--color-surface-container) mb-2 flex items-center justify-center overflow-hidden">
                  <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.nama"
                    class="w-full h-full object-cover"
                    loading="lazy"
                  />
                  <span v-else class="text-3xl leading-none">{{ item.emoji }}</span>
                </div>
  
                <!-- Tombol +/stepper: menumpuk di pojok kanan atas kartu, di luar
                     kotak gambar supaya tidak ikut terpotong overflow-hidden. -->
                <button
                  v-if="!item.habis && qtyOf(item.id) === 0"
                  type="button"
                  class="absolute top-1 right-1 z-10 w-7 h-7 rounded-full bg-(--color-surface-0) text-(--color-azure) border border-(--color-outline)/25 shadow-sm flex items-center justify-center active:scale-90 transition-transform"
                  :aria-label="`Tambah ${item.nama}`"
                  @click="tambah(item)"
                >
                  <Icon name="plus" class="w-3.5 h-3.5" />
                </button>
  
                <div
                  v-else-if="!item.habis"
                  class="absolute top-1 right-1 z-10 flex items-center bg-(--color-surface-0) border border-(--color-azure) rounded-full shadow-sm"
                >
                  <button
                    type="button"
                    class="w-6 h-7 rounded-l-full flex items-center justify-center text-(--color-on-surface) active:scale-90 transition-transform"
                    :aria-label="`Kurangi ${item.nama}`"
                    @click="kurang(item)"
                  >
                    <Icon name="minus" class="w-3 h-3" />
                  </button>
                  <span class="text-[11px] font-bold min-w-[14px] text-center">{{ qtyOf(item.id) }}</span>
                  <button
                    type="button"
                    class="w-6 h-7 rounded-r-full flex items-center justify-center text-(--color-azure) active:scale-90 transition-transform"
                    :aria-label="`Tambah ${item.nama}`"
                    @click="tambah(item)"
                  >
                    <Icon name="plus" class="w-3 h-3" />
                  </button>
                </div>
  
                <!-- harga di atas nama, mengikuti pola katalog belanja.
                     Kalau item sedang promo: harga promo di atas, harga normal
                     di bawahnya dicoret garis merah. -->
                <p class="text-[12.5px] font-extrabold leading-tight" :class="item.habis ? 'text-(--color-on-surface-variant)' : adaPromo(item) ? 'text-(--color-error)' : 'text-(--color-on-surface)'">
                  {{ rp(hargaFinal(item, kategoriId)) }}
                </p>
                <p
                  v-if="adaPromo(item)"
                  class="text-[10px] font-semibold text-(--color-on-surface-variant) leading-tight line-through decoration-(--color-error) decoration-[1.5px]"
                >
                  {{ rp(hargaNormal(item, kategoriId)) }}
                </p>
                <h3 class="text-[10.5px] font-semibold text-(--color-on-surface) leading-snug line-clamp-2 mt-0.5">
                  {{ item.nama }}
                </h3>
                <p v-if="item.ukuran" class="text-[9.5px] text-(--color-on-surface-variant) mt-0.5 truncate">
                  {{ item.ukuran }}
                </p>
  
                <span
                  v-if="item.habis"
                  class="mt-1 inline-block text-[9px] font-bold text-(--color-error) bg-(--color-error-container) rounded-full px-2 py-0.5"
                >
                  Stok kosong
                </span>
              </div>
            </div>
          </section>
  
          <!-- Ruang untuk grup yang belum tergambar, supaya tinggi halaman tidak melompat -->
          <section v-if="masihMemuat">
            <Skeleton class="h-3 w-28 mb-2.5" />
            <KatalogSkeleton :jumlah="9" />
          </section>
        </div>
      </main>
  
      <!-- Ringkasan keranjang mengambang -->
      <div v-if="totalItem > 0" class="fixed bottom-5 inset-x-0 z-50 px-4 pointer-events-none">
        <div class="max-w-[430px] mx-auto bg-(--color-on-surface) text-white rounded-3xl p-3.5 flex items-center justify-between gap-3 shadow-(--shadow-float) pointer-events-auto">
          <!-- Ikon keranjang membuka halaman keranjang supaya isinya bisa ditinjau
               & diubah tanpa harus kembali ke halaman daftar belanja. -->
          <button
            type="button"
            aria-label="Lihat keranjang"
            class="flex items-center gap-3 min-w-0 text-left active:scale-95 transition-transform"
            @click="router.push({ name: 'task-belanja-keranjang' })"
          >
            <span class="w-11 h-11 rounded-full bg-white/15 flex items-center justify-center shrink-0 relative">
              <Icon name="cart" class="w-5 h-5 text-white" />
              <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 bg-(--color-azure) rounded-full flex items-center justify-center text-[10px] font-bold border-2 border-(--color-on-surface)">
                {{ totalItem }}
              </span>
            </span>
            <span class="min-w-0">
              <span class="block text-[10.5px] text-white/70 leading-none mb-1">Total Belanja</span>
              <span class="block text-[15px] font-extrabold leading-none truncate">{{ rp(totalHarga) }}</span>
            </span>
          </button>
          <button
            type="button"
            class="bg-(--color-azure) text-white px-5 h-11 rounded-full text-sm font-bold flex items-center gap-1.5 shrink-0 active:scale-95 transition-transform"
            @click="kembali"
          >
            Lanjut
            <Icon name="arrow-right" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </template>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
