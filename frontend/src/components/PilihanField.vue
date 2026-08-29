<script setup lang="ts">
/**
 * Kolom pilihan — pengganti <select> bawaan.
 *
 * Alasannya bukan selera. `<select>` di ponsel membuka daftar milik sistem
 * operasi: gayanya tidak bisa disentuh, tinggi barisnya tidak mengikuti ukuran
 * sentuh yang dipakai halaman ini, dan pilihan yang sedang aktif hanya ditandai
 * seadanya. Panel ini memakai pola yang sudah dipakai pemilih tanggal dan jam —
 * lembar dari bawah, baris besar, centang biru pada yang terpilih.
 *
 * Keterangan opsional per pilihan (`catatan`) dipakai untuk hal yang perlu
 * dijelaskan di tempat memilihnya, bukan di paragraf terpisah yang kerap
 * terlewat — misalnya kenapa "Tidak tahu" adalah jawaban yang sah.
 */
import { computed, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'

export interface OpsiPilihan {
  id: string
  nama: string
  catatan?: string
}

const modelValue = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    opsi: OpsiPilihan[]
    label?: string
    judulPanel?: string
    placeholder?: string
    /** Ikon di sisi kiri pemicu; kosongkan kalau tidak perlu. */
    ikon?: string
  }>(),
  {
    label: '',
    judulPanel: '',
    placeholder: 'Pilih',
    ikon: '',
  },
)

const buka = ref(false)

const terpilih = computed(() => props.opsi.find((o) => o.id === modelValue.value) ?? null)

function pilih(id: string) {
  modelValue.value = id
  buka.value = false
}
</script>

<template>
  <div>
    <span
      v-if="label"
      class="block text-[11.5px] font-medium text-(--color-on-surface-variant) mb-1.5"
    >
      {{ label }}
    </span>

    <button
      type="button"
      class="w-full flex items-center gap-2 rounded-xl bg-(--color-surface-container) border-2 border-transparent px-3.5 py-3 text-left active:scale-[0.99] transition-transform"
      :class="buka ? 'border-(--color-azure)' : ''"
      :aria-expanded="buka"
      @click="buka = true"
    >
      <Icon v-if="ikon" :name="ikon" class="w-4 h-4 shrink-0 text-(--color-on-surface-variant)" />
      <span
        class="flex-1 min-w-0 truncate text-[13px]"
        :class="terpilih ? 'font-bold' : 'text-(--color-on-surface-variant)'"
      >
        {{ terpilih?.nama ?? placeholder }}
      </span>
      <Icon
        name="chevron-down"
        class="w-4 h-4 shrink-0 text-(--color-on-surface-variant) transition-transform"
        :class="buka ? 'rotate-180' : ''"
      />
    </button>

    <Teleport to="body">
      <div v-if="buka" class="fixed inset-0 z-[65] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>

        <div
          class="relative w-full md:w-90 max-h-[75dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)"
        >
          <div
            class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"
          ></div>

          <div class="flex items-center justify-between px-5 py-3.5 shrink-0">
            <h3 class="font-display font-extrabold text-[16px]">
              {{ judulPanel || label || placeholder }}
            </h3>
            <button
              type="button"
              aria-label="Tutup"
              class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
              @click="buka = false"
            >
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>

          <div class="overflow-y-auto px-5 pb-6 flex flex-col gap-2">
            <button
              v-for="o in opsi"
              :key="o.id"
              type="button"
              class="w-full flex items-center gap-3 rounded-2xl border-2 px-4 py-3.5 text-left transition-colors"
              :class="
                o.id === modelValue
                  ? 'border-(--color-azure) bg-(--color-azure)/8'
                  : 'border-(--color-outline)/30 active:bg-(--color-surface-container)'
              "
              @click="pilih(o.id)"
            >
              <span class="flex-1 min-w-0">
                <span class="block text-[14px] font-bold">{{ o.nama }}</span>
                <span
                  v-if="o.catatan"
                  class="block mt-0.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)"
                >
                  {{ o.catatan }}
                </span>
              </span>

              <span
                v-if="o.id === modelValue"
                class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
              >
                <Icon name="check" class="w-3 h-3 text-white" />
              </span>
              <span
                v-else
                class="w-5 h-5 rounded-full border-2 border-(--color-outline) shrink-0"
              ></span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
