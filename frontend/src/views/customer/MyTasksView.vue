<script setup lang="ts">
import { onMounted } from 'vue'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'
import type { TaskStatus } from '@/types'

const taskStore = useTaskStore()

onMounted(() => {
  taskStore.fetchTasks()
})

const statusChip: Record<TaskStatus, { label: string; class: string }> = {
  pending: { label: 'Menunggu Mitra', class: 'bg-(--color-primary-container) text-(--color-on-primary-container)' },
  accepted: { label: 'Diterima', class: 'bg-(--color-secondary-container) text-(--color-on-secondary-container)' },
  in_progress: { label: 'Dikerjakan', class: 'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)' },
  completed: { label: 'Selesai', class: 'bg-(--color-lime) text-[#33430b]' },
  cancelled: { label: 'Dibatalkan', class: 'bg-(--color-surface-container-high) text-(--color-on-surface-variant)' },
}
</script>

<template>
  <AppLayout>
    <div class="px-5 pt-5 pb-6">
      <h2 class="text-lg font-extrabold mb-4">Tugas Saya</h2>

      <div class="space-y-3">
        <RouterLink
          v-for="task in taskStore.tasks"
          :key="task.id"
          :to="{ name: 'task-detail', params: { id: task.id } }"
          class="block rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="font-bold text-sm flex-1">{{ task.judul }}</p>
            <span class="rounded-full text-[11px] font-bold px-2.5 py-1 shrink-0" :class="statusChip[task.status].class">
              {{ statusChip[task.status].label }}
            </span>
          </div>
          <p class="text-xs text-(--color-on-surface-variant) mt-2 flex items-center gap-1.5">
            <Icon name="pin" class="w-3.5 h-3.5" />{{ task.lokasi_alamat }}
          </p>
          <p v-if="task.harga || task.budget" class="text-sm font-bold text-(--color-on-primary-container) mt-1.5">
            Rp{{ (task.harga ?? task.budget ?? 0).toLocaleString('id-ID') }}
          </p>
        </RouterLink>

        <div v-if="!taskStore.tasks.length" class="text-center py-10">
          <Icon name="clipboard" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
          <p class="text-sm text-(--color-on-surface-variant)">Belum ada tugas. Yuk buat yang pertama!</p>
          <RouterLink :to="{ name: 'home' }" class="inline-block mt-3 rounded-full bg-(--color-azure) text-white font-bold text-sm px-5 py-2.5">Buat Tugas</RouterLink>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
