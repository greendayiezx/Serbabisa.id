<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { LANGGANAN_BERSIH, hargaPerKunjungan, hematPersen } from '@/lib/promo/promoBersih'

const router = useRouter()

const paket = computed(() =>
  LANGGANAN_BERSIH.map((p) => ({
    ...p,
    perKunjungan: hargaPerKunjungan(p),
    hemat: hematPersen(p),
  })),
)

const dipilih = ref<string | null>(LANGGANAN_BERSIH.find((p) => p.unggulan)?.id ?? null)

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

const kembali = useKembali()

function mulai() {
  router.push({
    name: 'task-bersih-detail',
    query: { category: 'bisabersih', paket: dipilih.value ?? undefined },
  })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Paket Langganan Hemat</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <div>
        <h2 class="font-display font-extrabold text-[15px] text-(--color-on-surface) mb-1">
          Bersih rutin, tanpa dipikirkan
        </h2>
        <p class="text-[12px] text-(--color-on-surface-variant) leading-snug">
          Jadwal otomatis tiap minggu, reminder H-1 lewat WhatsApp, dan harga per kunjungan yang lebih murah.
        </p>
      </div>

      <!-- Daftar paket -->
      <div class="flex flex-col gap-3">
        <button
          v-for="p in paket"
          :key="p.id"
          type="button"
          class="relative w-full text-left p-4 rounded-2xl overflow-hidden border-2 transition-colors active:scale-[0.99]"
          :class="[
            p.unggulan
              ? 'bg-(--color-azure) shadow-[0_10px_30px_rgba(30,155,240,0.25)]'
              : 'bg-(--color-surface-0) shadow-(--shadow-lift)',
            dipilih === p.id
              ? p.unggulan
                ? 'border-(--color-lime)'
                : 'border-(--color-azure)'
              : 'border-transparent',
          ]"
          :aria-pressed="dipilih === p.id"
          @click="dipilih = p.id"
        >
          <span
            v-if="p.unggulan"
            class="absolute top-0 right-0 bg-(--color-lime) text-[#33430b] text-[10.5px] font-bold px-2.5 py-1 rounded-bl-2xl"
          >
            Best Seller
          </span>

          <h3
            class="font-display font-extrabold text-[15px] mb-0.5 flex items-center gap-1.5"
            :class="p.unggulan ? 'text-white' : 'text-(--color-on-surface)'"
          >
            {{ p.nama }}
            <Icon
              v-if="dipilih === p.id"
              name="check-circle"
              class="w-4 h-4 shrink-0"
              :class="p.unggulan ? 'text-(--color-lime)' : 'text-(--color-azure)'"
            />
          </h3>
          <p
            class="text-[12px] mb-2.5"
            :class="p.unggulan ? 'text-white/85' : 'text-(--color-on-surface-variant)'"
          >
            {{ rp(p.hargaBulanan) }}/bulan · {{ p.frekuensi }} ·
            {{ rp(p.perKunjungan) }}/kunjungan
          </p>

          <span
            v-if="p.unggulan"
            class="inline-block bg-(--color-lime) text-[#33430b] text-[12px] font-bold px-3 py-1.5 rounded-full"
          >
            {{ p.benefit }}
          </span>
          <p v-else class="text-[12.5px] font-semibold text-(--color-azure)">{{ p.benefit }}</p>

          <p
            v-if="p.hemat > 0"
            class="text-[11.5px] mt-2"
            :class="p.unggulan ? 'text-white/85' : 'text-(--color-on-surface-variant)'"
          >
            Hemat {{ p.hemat }}% per kunjungan dibanding paket Bulanan
          </p>
        </button>
      </div>
    </main>

    <!-- CTA -->
    <footer
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/90 backdrop-blur-xl border-t border-(--color-outline)/40 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
    >
      <div class="max-w-[430px] mx-auto px-4">
        <button
          type="button"
          :disabled="!dipilih"
          class="w-full bg-(--color-azure) text-white rounded-full py-3.5 text-[14px] font-bold active:scale-95 transition-transform disabled:opacity-40 disabled:active:scale-100"
          @click="mulai"
        >
          Mulai Langganan
        </button>
      </div>
    </footer>
  </div>
</template>
