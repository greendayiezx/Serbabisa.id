<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'

const modelValue = defineModel<string>({ default: '' })
const open = ref(false)

function pad(n: number) {
  return String(n).padStart(2, '0')
}

const slots = (() => {
  const list: string[] = []
  for (let h = 6; h <= 21; h++) {
    for (const m of [0, 30]) {
      if (h === 21 && m === 30) continue
      list.push(`${pad(h)}:${pad(m)}`)
    }
  }
  return list
})()

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
      class="w-full flex items-center gap-2.5 bg-(--color-surface-container) rounded-(--radius-input) px-3.5 py-3 text-left"
      @click="open = true"
    >
      <Icon name="clock" class="w-[18px] h-[18px] text-(--color-on-surface-variant) shrink-0" />
      <span class="text-sm flex-1 truncate" :class="modelValue ? 'text-(--color-on-surface) font-semibold' : 'text-(--color-on-surface-variant)'">
        {{ displayLabel || 'Pilih waktu' }}
      </span>
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative w-full md:w-90 max-h-[75dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
          <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 md:hidden"></div>
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-(--color-outline)/40 shrink-0">
            <h3 class="font-extrabold text-[15px]">Pilih Jam Penjemputan</h3>
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
