<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'

const modelValue = defineModel<string>({ default: '' })

const props = withDefaults(
  defineProps<{
    /** Judul di kepala panel. */
    title?: string
    /** Placeholder saat belum ada pilihan. */
    placeholder?: string
    /** Jam paling awal yang bisa dipilih (inklusif). */
    minHour?: number
    /** Jam paling akhir yang bisa dipilih (inklusif, pada menit :00). */
    maxHour?: number
    /** Jarak antar slot dalam menit (30 = tiap setengah jam, 60 = tiap jam). */
    stepMinutes?: number
    /** Isian wajib. Bersama `ditandai`, menentukan garis merah. */
    wajib?: boolean
    /** Sudah pernah gagal divalidasi — tepinya memerah selama masih kosong. */
    ditandai?: boolean
  }>(),
  {
    title: 'Pilih Jam Penjemputan',
    placeholder: 'Pilih waktu',
    minHour: 6,
    maxHour: 21,
    stepMinutes: 30,
  },
)

/**
 * Garis merah baru muncul setelah pengguna menekan "Lanjut" dan isian ini
 * memang masih kosong. Halaman yang baru dibuka tidak boleh langsung merah:
 * pengguna belum melakukan apa pun yang salah.
 */
const kosongDanDitandai = computed(
  () => props.wajib === true && props.ditandai === true && !modelValue.value,
)

const open = ref(false)

function pad(n: number) {
  return String(n).padStart(2, '0')
}

/** Slot dibangun dari total menit supaya rentang & langkahnya selalu konsisten. */
const slots = computed(() => {
  const list: string[] = []
  for (let t = props.minHour * 60; t <= props.maxHour * 60; t += props.stepMinutes) {
    list.push(`${pad(Math.floor(t / 60))}:${pad(t % 60)}`)
  }
  return list
})

const displayLabel = computed(() => modelValue.value || '')

function pick(slot: string) {
  modelValue.value = slot
  open.value = false
}
</script>

<template>
  <div>
    <button
      type="button"
      class="w-full flex items-center gap-2.5 bg-white border-2 rounded-2xl px-4 py-3.5 text-left active:scale-[0.99] transition-transform cursor-pointer shadow-xs"
      :class="kosongDanDitandai ? 'border-(--color-error)' : 'border-white'"
      @click="open = true"
    >
      <Icon name="clock" class="w-5 h-5 text-(--color-on-surface-variant) shrink-0" />
      <span class="text-[15px] flex-1 truncate" :class="modelValue ? 'text-(--color-on-surface) font-extrabold' : 'text-(--color-on-surface-variant)'">
        {{ displayLabel || placeholder }}
      </span>
      <Icon name="chevron-down" class="w-5 h-5 text-(--color-on-surface-variant) shrink-0" />
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative w-full md:w-90 max-h-[75dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
          <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 md:hidden"></div>
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-(--color-outline)/40 shrink-0">
            <h3 class="font-extrabold text-[15px]">{{ title }}</h3>
            <button type="button" class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform" @click="open = false">
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>
          <div class="grid grid-cols-3 gap-2.5 p-5 overflow-y-auto">
            <button
              v-for="slot in slots"
              :key="slot"
              type="button"
              class="rounded-xl py-3 text-[13.5px] font-bold border-2 transition-colors"
              :class="slot === modelValue ? 'bg-(--color-azure) border-(--color-azure) text-white' : 'border-(--color-outline) text-(--color-on-surface) active:bg-(--color-surface-container)'"
              @click="pick(slot)"
            >
              {{ slot }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
