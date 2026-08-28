<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { KATALOG_BY_KATEGORI } from '@/lib/belanja/belanjaKatalog'

/**
 * Pintu masuk pencarian. Kotaknya sengaja bukan <input> — mengetik di sini
 * dulunya membuka dropdown yang sempit; sekarang seluruh alur pencarian punya
 * halamannya sendiri (task-belanja-cari) supaya hasilnya muat satu layar penuh.
 */
const router = useRouter()

const jumlahProduk = computed(() =>
  Object.values(KATALOG_BY_KATEGORI).reduce((total, items) => total + items.length, 0),
)

function bukaPencarian() {
  router.push({ name: 'task-belanja-cari' })
}
</script>

<template>
  <div class="relative z-30 w-full max-w-[430px] mx-auto px-4 pt-3 pb-1">
    <button
      type="button"
      class="w-full bg-white rounded-2xl border-2 border-slate-200 shadow-[0_4px_16px_rgba(11,25,44,0.08)] flex items-center gap-2.5 px-4 h-12 text-left active:scale-[0.99] transition-transform"
      @click="bukaPencarian"
    >
      <Icon name="search" class="w-5 h-5 text-(--color-azure) shrink-0" />
      <span class="flex-1 text-[13.5px] font-semibold text-slate-400 truncate">
        Cari dari {{ jumlahProduk.toLocaleString('id-ID') }} produk...
      </span>
      <Icon name="chevron-right" class="w-4 h-4 text-slate-300 shrink-0" />
    </button>
  </div>
</template>
