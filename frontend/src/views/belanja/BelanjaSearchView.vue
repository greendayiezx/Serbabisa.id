<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useBelanjaDraftStore } from '@/stores/belanjaDraft'
import { KATALOG_BY_KATEGORI, adaPromo, hargaFinal, hargaNormal, type KatalogItem } from '@/lib/belanja/belanjaKatalog'

const route = useRoute()
const router = useRouter()
const belanjaDraft = useBelanjaDraftStore()

const KATEGORI_META: Record<string, { label: string; emoji: string }> = {
  buah: { label: 'Buah Segar', emoji: '🍎' },
  sayur: { label: 'Sayur Segar', emoji: '🥦' },
  susu: { label: 'Susu & Olahan', emoji: '🥛' },
  telur: { label: 'Telur, Tahu & Tempe', emoji: '🥚' },
  daging: { label: 'Daging & Unggas', emoji: '🍗' },
  seafood: { label: 'Seafood', emoji: '🦐' },
  snack: { label: 'Snack & Camilan', emoji: '🍿' },
  minuman: { label: 'Minuman', emoji: '🧃' },
  bumbu: { label: 'Bumbu & Bahan Masak', emoji: '🧄' },
  roti: { label: 'Roti & Kue', emoji: '🍞' },
  pokok: { label: 'Bahan Pokok', emoji: '🍚' },
  frozen: { label: 'Frozen Food', emoji: '🧊' },
}

interface Sku extends KatalogItem {
  kategoriId: string
  kategoriLabel: string
  harga: number
  hargaCoret: number
}

/**
 * Semua produk dari seluruh katalog, dijadikan satu daftar SKU unik.
 *
 * Kunci unik = nama + ukuran + harga rak. Harga ikut jadi kunci karena beberapa
 * produk di data sumber punya nama sama tanpa ukuran tapi harganya beda (mis.
 * "Barco Minyak Goreng Kelapa Botol" Rp58.500 & Rp112.000) — itu dua kemasan
 * berbeda, bukan duplikat, jadi tidak boleh digabung. Yang benar-benar kembar
 * (produk sama muncul di dua kategori) hanya disimpan sekali.
 */
const semuaSku = computed<Sku[]>(() => {
  const oleh = new Map<string, Sku>()
  for (const [kategoriId, items] of Object.entries(KATALOG_BY_KATEGORI)) {
    const meta = KATEGORI_META[kategoriId] ?? { label: kategoriId, emoji: '🛒' }
    for (const item of items) {
      const kunci = `${item.nama}|${item.ukuran ?? ''}|${item.hargaDasar}`.toLowerCase()
      if (oleh.has(kunci)) continue
      oleh.set(kunci, {
        ...item,
        kategoriId,
        kategoriLabel: meta.label,
        harga: hargaFinal(item, kategoriId),
        hargaCoret: hargaNormal(item, kategoriId),
      })
    }
  }
  return [...oleh.values()]
})

const PENCARIAN_POPULER = [
  'blueberry', 'roti', 'susu', 'beras', 'ayam',
  'telur', 'pisang', 'indomie', 'minyak', 'kopi',
]

const keyword = ref(String(route.query.q ?? ''))
const inputRef = ref<HTMLInputElement | null>(null)

// Filter
const filterOpen = ref(false)
const filterKategori = ref<string | null>(null)
const sembunyikanHabis = ref(false)
const filterAktif = computed(() => filterKategori.value !== null || sembunyikanHabis.value)

const q = computed(() => keyword.value.trim().toLowerCase())

function cocok(item: Sku, term: string) {
  return (
    item.nama.toLowerCase().includes(term) ||
    item.grup.toLowerCase().includes(term) ||
    item.kategoriLabel.toLowerCase().includes(term) ||
    (item.ukuran?.toLowerCase().includes(term) ?? false)
  )
}

function terapkanFilter(list: Sku[]) {
  return list.filter(
    (i) =>
      (filterKategori.value === null || i.kategoriId === filterKategori.value) &&
      (!sembunyikanHabis.value || !i.habis),
  )
}

/** Hasil utama: cocok di nama produk, yang diawali kata kunci naik ke atas. */
const hasil = computed<Sku[]>(() => {
  if (!q.value) return []
  const term = q.value
  return terapkanFilter(semuaSku.value.filter((i) => i.nama.toLowerCase().includes(term)))
    .sort((a, b) => {
      const aAwal = a.nama.toLowerCase().startsWith(term)
      const bAwal = b.nama.toLowerCase().startsWith(term)
      if (aAwal !== bAwal) return aAwal ? -1 : 1
      // stok ada dulu, baru yang habis
      if (!!a.habis !== !!b.habis) return a.habis ? 1 : -1
      return a.harga - b.harga
    })
    .slice(0, 60)
})

/**
 * Produk terkait: cocok lewat grup/kategori/ukuran tapi namanya sendiri tidak
 * mengandung kata kunci — jadi tidak tumpang tindih dengan hasil utama.
 */
const terkait = computed<Sku[]>(() => {
  if (!q.value) return []
  const term = q.value
  const sudah = new Set(hasil.value.map((i) => i.id))
  const grupHasil = new Set(hasil.value.map((i) => i.grup))
  return terapkanFilter(
    semuaSku.value.filter(
      (i) => !sudah.has(i.id) && !i.nama.toLowerCase().includes(term) && (grupHasil.has(i.grup) || cocok(i, term)),
    ),
  )
    .sort((a, b) => {
      if (!!a.habis !== !!b.habis) return a.habis ? 1 : -1
      return a.harga - b.harga
    })
    .slice(0, 24)
})

// Kata kunci disimpan di URL supaya bisa di-share & tombol back browser jalan.
let tulisUrl: ReturnType<typeof setTimeout> | null = null
watch(keyword, (nilai) => {
  if (tulisUrl) clearTimeout(tulisUrl)
  tulisUrl = setTimeout(() => {
    const bersih = nilai.trim()
    router.replace({ query: bersih ? { q: bersih } : {} })
  }, 350)
})

// Kalau user menekan back/forward, kotak pencarian ikut menyesuaikan.
watch(
  () => route.query.q,
  (nilai) => {
    const s = String(nilai ?? '')
    if (s !== keyword.value.trim()) keyword.value = s
  },
)

function pilihKataKunci(term: string) {
  keyword.value = term
  inputRef.value?.focus()
}

function bersihkan() {
  keyword.value = ''
  inputRef.value?.focus()
}

const batal = useKembali()

function resetFilter() {
  filterKategori.value = null
  sembunyikanHabis.value = false
}

// Keranjang
function itemDiKeranjang(id: string) {
  return belanjaDraft.draft.items.find((i) => i.id === id)
}
function qtyOf(id: string) {
  return itemDiKeranjang(id)?.qty ?? 0
}
function tambah(item: Sku) {
  // Pengaman: item habis tidak boleh masuk keranjang walau tombolnya tertekan.
  if (item.habis) return
  const ada = itemDiKeranjang(item.id)
  if (ada) {
    belanjaDraft.updateItemQty(item.id, ada.qty + 1)
    return
  }
  belanjaDraft.addItem({
    id: item.id,
    nama: item.nama,
    catatan: '',
    qty: 1,
    kategori: item.kategoriId,
    harga: item.harga,
    satuan: item.satuan,
  })
}
function kurang(item: Sku) {
  const ada = itemDiKeranjang(item.id)
  if (!ada) return
  if (ada.qty <= 1) belanjaDraft.removeItem(item.id)
  else belanjaDraft.updateItemQty(item.id, ada.qty - 1)
}

const totalItem = computed(() => belanjaDraft.draft.items.reduce((s, i) => s + i.qty, 0))
const totalHarga = computed(() =>
  belanjaDraft.draft.items.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0),
)

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

onMounted(() => {
  nextTick(() => inputRef.value?.focus())
})
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-32">
    <!-- Kotak pencarian, menempel di atas -->
    <div class="sticky top-0 z-30 bg-(--color-surface) px-4 pt-4 pb-3">
      <div class="max-w-[430px] mx-auto flex items-center gap-3">
        <div
          class="flex-1 flex items-center gap-2.5 bg-(--color-surface-0) border-2 border-(--color-azure) rounded-2xl px-3.5 h-12"
        >
          <Icon name="search" class="w-4.5 h-4.5 text-(--color-on-surface-variant) shrink-0" />
          <input
            ref="inputRef"
            v-model="keyword"
            type="search"
            enterkeyhint="search"
            placeholder="Cari produk di daftar belanja..."
            class="w-full bg-transparent border-none p-0 text-[13.5px] font-semibold text-(--color-on-surface) placeholder:text-(--color-outline) focus:outline-none focus:ring-0"
          />
          <button
            v-if="keyword"
            type="button"
            aria-label="Hapus pencarian"
            class="w-6 h-6 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-90 transition-transform"
            @click="bersihkan"
          >
            <Icon name="x" class="w-3 h-3 text-(--color-on-surface-variant)" />
          </button>
        </div>
        <button
          type="button"
          class="text-[13.5px] font-bold text-(--color-on-surface-variant) shrink-0 active:scale-95 transition-transform"
          aria-label="Kembali"
          @click="batal"
        >
          Batal
        </button>
      </div>
    </div>

    <div class="max-w-[430px] mx-auto px-4">
      <!-- ================= Belum mengetik apa pun ================= -->
      <template v-if="!q">
        <section class="mt-2">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-[15px] font-extrabold">Pencarian Populer</h2>
            <span class="text-[11px] font-bold text-(--color-azure)">{{ semuaSku.length }} produk</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="term in PENCARIAN_POPULER"
              :key="term"
              type="button"
              class="px-3.5 h-8 rounded-full border border-(--color-outline)/30 bg-(--color-surface-0) text-[12px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
              @click="pilihKataKunci(term)"
            >
              {{ term }}
            </button>
          </div>
        </section>
      </template>

      <!-- ================= Ada kata kunci ================= -->
      <template v-else>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-full border text-[12px] font-bold transition-colors"
          :class="
            filterAktif
              ? 'border-(--color-azure) text-(--color-azure) bg-(--color-azure)/10'
              : 'border-(--color-outline)/30 text-(--color-on-surface-variant) bg-(--color-surface-0)'
          "
          @click="filterOpen = true"
        >
          <svg viewBox="0 0 24 24" fill="none" class="w-3.5 h-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h10M18 18h2" />
            <circle cx="16" cy="6" r="2" />
            <circle cx="10" cy="12" r="2" />
            <circle cx="16" cy="18" r="2" />
          </svg>
          Filter
          <span v-if="filterAktif" class="w-1.5 h-1.5 rounded-full bg-(--color-azure)"></span>
        </button>

        <p class="text-[13px] text-(--color-on-surface-variant) mt-4 mb-3">
          Hasil pencarian untuk
          <span class="font-extrabold text-(--color-on-surface)">&ldquo;{{ keyword.trim() }}&rdquo;</span>
        </p>

        <!-- Tidak ketemu -->
        <div
          v-if="!hasil.length"
          class="bg-(--color-surface-0) border border-(--color-outline)/20 rounded-2xl p-6 text-center"
        >
          <p class="text-3xl mb-2">🔍</p>
          <p class="text-[13px] font-bold mb-1">Produk tidak ditemukan</p>
          <p class="text-[11.5px] text-(--color-on-surface-variant)">
            Tidak ada produk bernama &ldquo;{{ keyword.trim() }}&rdquo;{{ filterAktif ? ' dengan filter ini' : '' }}.
            Coba kata kunci lain{{ filterAktif ? ' atau atur ulang filter' : '' }}.
          </p>
          <button
            v-if="filterAktif"
            type="button"
            class="mt-3 h-9 px-4 rounded-full bg-(--color-azure) text-white text-[12px] font-bold active:scale-95 transition-transform"
            @click="resetFilter"
          >
            Atur ulang filter
          </button>
        </div>

        <!-- Hasil utama -->
        <div v-else class="grid grid-cols-3 gap-2.5">
          <div
            v-for="item in hasil"
            :key="item.id"
            class="relative bg-(--color-surface-0) rounded-2xl p-2 border transition-colors"
            :class="[
              qtyOf(item.id) > 0 ? 'border-(--color-azure)' : 'border-(--color-outline)/20',
              item.habis ? 'opacity-60' : '',
            ]"
          >
            <div
              class="relative w-full aspect-square rounded-xl bg-(--color-surface-container) mb-2 flex items-center justify-center overflow-hidden"
            >
              <img v-if="item.image" :src="item.image" :alt="item.nama" class="w-full h-full object-cover" loading="lazy" />
              <span v-else class="text-3xl leading-none">{{ item.emoji }}</span>
            </div>

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
                class="w-6 h-7 rounded-l-full flex items-center justify-center text-(--color-on-surface-variant) active:scale-90 transition-transform"
                :aria-label="`Kurangi ${item.nama}`"
                @click="kurang(item)"
              >
                <Icon name="minus" class="w-3 h-3" />
              </button>
              <span class="text-[11px] font-extrabold min-w-4 text-center text-(--color-azure)">{{ qtyOf(item.id) }}</span>
              <button
                type="button"
                class="w-6 h-7 rounded-r-full flex items-center justify-center text-(--color-azure) active:scale-90 transition-transform"
                :aria-label="`Tambah ${item.nama}`"
                @click="tambah(item)"
              >
                <Icon name="plus" class="w-3 h-3" />
              </button>
            </div>

            <p
              class="text-[12.5px] font-extrabold leading-tight"
              :class="
                item.habis
                  ? 'text-(--color-on-surface-variant)'
                  : adaPromo(item)
                    ? 'text-(--color-error)'
                    : 'text-(--color-on-surface)'
              "
            >
              {{ rp(item.harga) }}
            </p>
            <p
              v-if="adaPromo(item)"
              class="text-[10px] font-semibold text-(--color-on-surface-variant) leading-tight line-through decoration-(--color-error) decoration-[1.5px]"
            >
              {{ rp(item.hargaCoret) }}
            </p>
            <h3 class="text-[10.5px] font-semibold leading-snug line-clamp-2 mt-0.5">{{ item.nama }}</h3>
            <p v-if="item.ukuran" class="text-[9.5px] text-(--color-on-surface-variant) mt-0.5 truncate">
              {{ item.ukuran }}
            </p>
            <p class="text-[9px] text-(--color-outline) mt-0.5 truncate">{{ item.kategoriLabel }}</p>

            <span
              v-if="item.habis"
              class="mt-1 inline-block text-[9px] font-bold text-(--color-error) bg-(--color-error-container) rounded-full px-2 py-0.5"
            >
              Stok kosong
            </span>
          </div>
        </div>

        <!-- Produk terkait -->
        <section v-if="terkait.length" class="mt-8">
          <h2 class="text-[15px] font-extrabold mb-3">Produk Terkait</h2>
          <div class="grid grid-cols-3 gap-2.5">
            <div
              v-for="item in terkait"
              :key="item.id"
              class="relative bg-(--color-surface-0) rounded-2xl p-2 border transition-colors"
              :class="[
                qtyOf(item.id) > 0 ? 'border-(--color-azure)' : 'border-(--color-outline)/20',
                item.habis ? 'opacity-60' : '',
              ]"
            >
              <div
                class="relative w-full aspect-square rounded-xl bg-(--color-surface-container) mb-2 flex items-center justify-center overflow-hidden"
              >
                <img v-if="item.image" :src="item.image" :alt="item.nama" class="w-full h-full object-cover" loading="lazy" />
                <span v-else class="text-3xl leading-none">{{ item.emoji }}</span>
              </div>

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
                  class="w-6 h-7 rounded-l-full flex items-center justify-center text-(--color-on-surface-variant) active:scale-90 transition-transform"
                  :aria-label="`Kurangi ${item.nama}`"
                  @click="kurang(item)"
                >
                  <Icon name="minus" class="w-3 h-3" />
                </button>
                <span class="text-[11px] font-extrabold min-w-4 text-center text-(--color-azure)">{{ qtyOf(item.id) }}</span>
                <button
                  type="button"
                  class="w-6 h-7 rounded-r-full flex items-center justify-center text-(--color-azure) active:scale-90 transition-transform"
                  :aria-label="`Tambah ${item.nama}`"
                  @click="tambah(item)"
                >
                  <Icon name="plus" class="w-3 h-3" />
                </button>
              </div>

              <p
                class="text-[12.5px] font-extrabold leading-tight"
                :class="
                  item.habis
                    ? 'text-(--color-on-surface-variant)'
                    : adaPromo(item)
                      ? 'text-(--color-error)'
                      : 'text-(--color-on-surface)'
                "
              >
                {{ rp(item.harga) }}
              </p>
              <p
                v-if="adaPromo(item)"
                class="text-[10px] font-semibold text-(--color-on-surface-variant) leading-tight line-through decoration-(--color-error) decoration-[1.5px]"
              >
                {{ rp(item.hargaCoret) }}
              </p>
              <h3 class="text-[10.5px] font-semibold leading-snug line-clamp-2 mt-0.5">{{ item.nama }}</h3>
              <p v-if="item.ukuran" class="text-[9.5px] text-(--color-on-surface-variant) mt-0.5 truncate">
                {{ item.ukuran }}
              </p>
              <p class="text-[9px] text-(--color-outline) mt-0.5 truncate">{{ item.kategoriLabel }}</p>

              <span
                v-if="item.habis"
                class="mt-1 inline-block text-[9px] font-bold text-(--color-error) bg-(--color-error-container) rounded-full px-2 py-0.5"
              >
                Stok kosong
              </span>
            </div>
          </div>
        </section>
      </template>
    </div>

    <!-- Ringkasan keranjang mengambang, pola sama dengan halaman kategori -->
    <div v-if="totalItem > 0" class="fixed bottom-5 inset-x-0 z-40 px-4 pointer-events-none">
      <div
        class="max-w-[430px] mx-auto bg-(--color-on-surface) text-white rounded-3xl p-3.5 flex items-center justify-between gap-3 shadow-(--shadow-float) pointer-events-auto"
      >
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
            <span
              class="absolute -top-1 -right-1 min-w-5 h-5 px-1 bg-(--color-azure) rounded-full flex items-center justify-center text-[10px] font-bold border-2 border-(--color-on-surface)"
            >
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
          @click="router.push({ name: 'task-belanja-detail' })"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Sheet filter -->
    <div v-if="filterOpen" class="fixed inset-0 z-50 flex items-end bg-black/30" @click.self="filterOpen = false">
      <div class="w-full max-w-[430px] mx-auto bg-(--color-surface) rounded-t-3xl p-5 pb-8">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-[15px] font-extrabold">Filter</h3>
          <button
            type="button"
            aria-label="Tutup filter"
            class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
            @click="filterOpen = false"
          >
            <Icon name="x" class="w-3.5 h-3.5" />
          </button>
        </div>

        <p class="text-[11px] font-bold uppercase tracking-wide text-(--color-on-surface-variant) mb-2">Kategori</p>
        <div class="flex flex-wrap gap-2 mb-5">
          <button
            type="button"
            class="px-3.5 h-8 rounded-full border text-[12px] font-semibold transition-colors"
            :class="
              filterKategori === null
                ? 'border-(--color-azure) bg-(--color-azure) text-white'
                : 'border-(--color-outline)/30 bg-(--color-surface-0) text-(--color-on-surface-variant)'
            "
            @click="filterKategori = null"
          >
            Semua
          </button>
          <button
            v-for="(meta, id) in KATEGORI_META"
            :key="id"
            type="button"
            class="px-3.5 h-8 rounded-full border text-[12px] font-semibold transition-colors"
            :class="
              filterKategori === id
                ? 'border-(--color-azure) bg-(--color-azure) text-white'
                : 'border-(--color-outline)/30 bg-(--color-surface-0) text-(--color-on-surface-variant)'
            "
            @click="filterKategori = id"
          >
            {{ meta.emoji }} {{ meta.label }}
          </button>
        </div>

        <label class="flex items-center justify-between py-2 cursor-pointer">
          <span class="text-[13px] font-semibold">Sembunyikan stok kosong</span>
          <input v-model="sembunyikanHabis" type="checkbox" class="w-5 h-5 accent-(--color-azure)" />
        </label>

        <div class="flex gap-2.5 mt-5">
          <button
            type="button"
            class="flex-1 h-11 rounded-full border border-(--color-outline)/30 text-[13px] font-bold active:scale-95 transition-transform"
            @click="resetFilter"
          >
            Atur ulang
          </button>
          <button
            type="button"
            class="flex-1 h-11 rounded-full bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
            @click="filterOpen = false"
          >
            Terapkan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* type="search" memunculkan tombol clear bawaan browser — kita sudah punya
   tombol sendiri, jadi yang bawaan disembunyikan supaya tidak dobel. */
input[type='search']::-webkit-search-cancel-button,
input[type='search']::-webkit-search-decoration {
  -webkit-appearance: none;
  appearance: none;
}
</style>
