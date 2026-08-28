<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useBelanjaDraftStore, type BelanjaItem } from '@/stores/belanjaDraft'

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
  lainnya: { label: 'Lainnya', emoji: '📝' },
}

const items = computed(() => belanjaDraft.draft.items)

/** Item dikelompokkan per kategori supaya mudah ditelusuri saat daftarnya panjang. */
const grup = computed(() => {
  const peta = new Map<string, BelanjaItem[]>()
  for (const item of items.value) {
    if (!peta.has(item.kategori)) peta.set(item.kategori, [])
    peta.get(item.kategori)!.push(item)
  }
  return [...peta.entries()].map(([kategori, daftar]) => ({
    kategori,
    label: KATEGORI_META[kategori]?.label ?? kategori,
    emoji: KATEGORI_META[kategori]?.emoji ?? '🛒',
    daftar,
    qty: daftar.reduce((s, i) => s + i.qty, 0),
    subtotal: daftar.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0),
  }))
})

const totalItem = computed(() => items.value.reduce((s, i) => s + i.qty, 0))
const totalHarga = computed(() => items.value.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0))

/** Item ketik manual belum punya harga — harganya dikonfirmasi mitra saat belanja. */
const tanpaHarga = computed(() => items.value.filter((i) => i.harga == null))

function tambah(item: BelanjaItem) {
  belanjaDraft.updateItemQty(item.id, item.qty + 1)
}

function kurang(item: BelanjaItem) {
  if (item.qty <= 1) belanjaDraft.removeItem(item.id)
  else belanjaDraft.updateItemQty(item.id, item.qty - 1)
}

function hapus(item: BelanjaItem) {
  belanjaDraft.removeItem(item.id)
}

const kembali = useKembali()

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) pb-36">
    <!-- Header -->
    <header
      class="sticky top-0 z-30 bg-(--color-surface-0)/95 backdrop-blur-xl border-b border-(--color-outline)/10"
    >
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Keranjang</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4">
      <!-- Kosong -->
      <div v-if="!totalItem" class="text-center py-16">
        <p class="text-5xl mb-3">🛒</p>
        <p class="text-[14px] font-bold mb-1">Keranjang masih kosong</p>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-5">
          Pilih kategori atau cari produk untuk mulai belanja.
        </p>
        <button
          type="button"
          class="h-11 px-6 rounded-full bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
          @click="router.push({ name: 'task-belanja-cari' })"
        >
          Cari produk
        </button>
      </div>

      <template v-else>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-3">
          <span class="font-extrabold text-(--color-on-surface)">{{ totalItem }} barang</span>
          dari {{ grup.length }} kategori
        </p>

        <section
          v-for="g in grup"
          :key="g.kategori"
          class="bg-(--color-surface-0) rounded-2xl border border-(--color-outline)/15 p-4 mb-3"
        >
          <div class="flex items-center gap-2 mb-3">
            <span class="text-lg leading-none">{{ g.emoji }}</span>
            <h2 class="flex-1 text-[13px] font-extrabold truncate text-black">{{ g.label }}</h2>
            <span class="text-[11px] font-bold text-black">{{ g.qty }} item</span>
          </div>

          <ul class="flex flex-col divide-y divide-(--color-outline)/10">
            <li v-for="item in g.daftar" :key="item.id" class="py-3 first:pt-0 last:pb-0">
              <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-bold leading-snug text-black">{{ item.nama }}</p>
                  <p v-if="item.catatan" class="text-[11px] text-(--color-outline) truncate mt-0.5">
                    {{ item.catatan }}
                  </p>
                  <p v-if="item.harga != null" class="text-[11.5px] text-(--color-on-surface-variant) mt-1">
                    {{ rp(item.harga) }}<span v-if="item.satuan"> / {{ item.satuan }}</span>
                    <span class="mx-1">&times;</span>{{ item.qty }}
                    <span class="font-extrabold text-black">= {{ rp(item.harga * item.qty) }}</span>
                  </p>
                  <p v-else class="text-[11px] text-(--color-on-surface-variant) mt-1 italic">
                    Harga dikonfirmasi mitra saat belanja
                  </p>
                </div>

                <div class="flex flex-col items-end gap-2 shrink-0">
                  <div class="flex items-center bg-white border-2 border-white rounded-full p-0.5 shadow-xs">
                    <button
                      type="button"
                      :aria-label="`Kurangi ${item.nama}`"
                      class="w-7 h-7 rounded-full flex items-center justify-center text-black active:scale-90 transition-transform"
                      @click="kurang(item)"
                    >
                      <Icon name="minus" class="w-3.5 h-3.5" />
                    </button>
                    <span class="text-[13px] font-extrabold min-w-6 text-center text-black">{{ item.qty }}</span>
                    <button
                      type="button"
                      :aria-label="`Tambah ${item.nama}`"
                      class="w-7 h-7 rounded-full flex items-center justify-center text-(--color-azure) active:scale-90 transition-transform"
                      @click="tambah(item)"
                    >
                      <Icon name="plus" class="w-3.5 h-3.5" />
                    </button>
                  </div>
                  <button
                    type="button"
                    :aria-label="`Hapus ${item.nama}`"
                    class="text-[11px] font-bold text-(--color-error) flex items-center gap-1 active:scale-95 transition-transform"
                    @click="hapus(item)"
                  >
                    <Icon name="trash" class="w-3 h-3" />
                    Hapus
                  </button>
                </div>
              </div>
            </li>
          </ul>

          <div
            v-if="g.subtotal > 0"
            class="mt-3 pt-3 border-t border-(--color-outline)/10 flex items-center justify-between"
          >
            <span class="text-[11.5px] text-(--color-on-surface-variant)">Subtotal {{ g.label }}</span>
            <span class="text-[13px] font-extrabold">{{ rp(g.subtotal) }}</span>
          </div>
        </section>

        <div
          v-if="tanpaHarga.length"
          class="flex items-start gap-2 bg-(--color-azure)/8 p-3 rounded-xl mt-1"
        >
          <Icon name="info" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
          <p class="text-[11px] text-(--color-on-surface-variant) leading-relaxed">
            {{ tanpaHarga.length }} barang ditulis manual dan belum punya harga, jadi belum masuk hitungan
            total. Mitra akan mengonfirmasi harganya saat belanja.
          </p>
        </div>
      </template>
    </main>

    <!-- Total & lanjut -->
    <footer
      v-if="totalItem > 0"
      class="fixed bottom-0 w-full z-40 bg-white/95 backdrop-blur-xl border-t border-(--color-surface-variant) shadow-[0_-20px_60px_rgba(0,0,0,0.08)] rounded-t-[1.5rem]"
    >
      <div class="max-w-[430px] mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex flex-col min-w-0">
          <span class="text-[11px] font-medium text-(--color-outline)">Total {{ totalItem }} barang</span>
          <span class="text-[20px] font-bold leading-tight truncate">{{ rp(totalHarga) }}</span>
        </div>
        <button
          type="button"
          class="flex-1 bg-(--color-azure) text-white rounded-full py-3.5 text-[14px] font-bold shadow-md shadow-(--color-azure)/20 active:scale-95 transition-all flex items-center justify-center gap-2"
          @click="router.push({ name: 'task-belanja-detail' })"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </footer>
  </div>
</template>
