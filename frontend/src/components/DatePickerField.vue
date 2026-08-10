<script setup lang="ts">
import { computed, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'

const modelValue = defineModel<string>({ default: '' })

const today = new Date()
today.setHours(0, 0, 0, 0)

function parseDate(v: string) {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function toIso(d: Date) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const open = ref(false)
const viewMonth = ref(parseDate(modelValue.value) ?? new Date(today))

const monthLabel = computed(() => viewMonth.value.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }))
const weekdays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

interface Cell {
  date: Date
  iso: string
  inMonth: boolean
  isToday: boolean
  isPast: boolean
}

const days = computed<Cell[]>(() => {
  const first = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth(), 1)
  const gridStart = new Date(first)
  gridStart.setDate(first.getDate() - first.getDay())

  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(gridStart)
    d.setDate(gridStart.getDate() + i)
    return {
      date: d,
      iso: toIso(d),
      inMonth: d.getMonth() === viewMonth.value.getMonth(),
      isToday: d.getTime() === today.getTime(),
      isPast: d.getTime() < today.getTime(),
    }
  })
})

const displayLabel = computed(() => {
  const d = parseDate(modelValue.value)
  if (!d) return ''
  return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

function prevMonth() {
  viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() - 1, 1)
}
function nextMonth() {
  viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() + 1, 1)
}

function openPicker() {
  viewMonth.value = parseDate(modelValue.value) ?? new Date(today)
  open.value = true
}

function pick(cell: Cell) {
  if (cell.isPast) return
  modelValue.value = cell.iso
  open.value = false
}

function pickToday() {
  modelValue.value = toIso(today)
  viewMonth.value = new Date(today)
  open.value = false
}
</script>

<template>
  <div>
    <button
      type="button"
      class="w-full flex items-center gap-2.5 bg-(--color-surface-container) rounded-(--radius-input) px-3.5 py-3 text-left"
      @click="openPicker"
    >
      <Icon name="calendar" class="w-[18px] h-[18px] text-(--color-on-surface-variant) shrink-0" />
      <span class="text-sm flex-1 truncate" :class="modelValue ? 'text-(--color-on-surface) font-semibold' : 'text-(--color-on-surface-variant)'">
        {{ displayLabel || 'Pilih tanggal' }}
      </span>
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative w-full md:w-90 bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-(--shadow-float)">
          <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mb-4 md:hidden"></div>

          <div class="flex items-center justify-between mb-4">
            <button type="button" class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform" @click="prevMonth">
              <Icon name="chevron-right" class="w-4 h-4 rotate-180" />
            </button>
            <h3 class="font-extrabold text-[15px] capitalize">{{ monthLabel }}</h3>
            <button type="button" class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform" @click="nextMonth">
              <Icon name="chevron-right" class="w-4 h-4" />
            </button>
          </div>

          <div class="grid grid-cols-7 gap-1 mb-1">
            <span v-for="w in weekdays" :key="w" class="text-center text-[11px] font-bold text-(--color-on-surface-variant) py-1">{{ w }}</span>
          </div>

          <div class="grid grid-cols-7 gap-1">
            <button
              v-for="cell in days"
              :key="cell.iso"
              type="button"
              :disabled="cell.isPast"
              class="aspect-square rounded-full text-[13px] font-bold flex items-center justify-center transition-colors"
              :class="[
                cell.isPast ? 'opacity-30 cursor-not-allowed' : 'active:scale-90',
                cell.iso === modelValue
                  ? 'bg-(--color-azure) text-white'
                  : cell.isToday
                    ? 'border-2 border-(--color-azure) text-(--color-azure)'
                    : cell.inMonth
                      ? 'text-(--color-on-surface) hover:bg-(--color-surface-container)'
                      : 'text-(--color-on-surface-variant)/40',
              ]"
              @click="pick(cell)"
            >
              {{ cell.date.getDate() }}
            </button>
          </div>

          <div class="flex items-center justify-between mt-4 pt-4 border-t border-(--color-outline)/40">
            <button type="button" class="text-[13px] font-bold text-(--color-azure)" @click="pickToday">Hari Ini</button>
            <button type="button" class="text-[13px] font-bold text-(--color-on-surface-variant)" @click="open = false">Tutup</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
