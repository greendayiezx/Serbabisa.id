<script setup lang="ts">
import { onMounted } from 'vue'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'

const taskStore = useTaskStore()

onMounted(() => {
  taskStore.fetchTasks({ status: 'pending' })
})
</script>

<template>
  <AppLayout>
    <div class="px-5 pt-5 pb-6">
      <h2 class="text-lg font-extrabold mb-4">Tugas di Sekitar Anda</h2>
      <div class="space-y-3">
        <RouterLink
          v-for="task in taskStore.tasks"
          :key="task.id"
          :to="{ name: 'task-detail', params: { id: task.id } }"
          class="block rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4"
        >
          <p class="font-bold text-sm">{{ task.judul }}</p>
          <p class="text-xs text-(--color-on-surface-variant) mt-2 flex items-center gap-1.5">
            <Icon name="pin" class="w-3.5 h-3.5" />{{ task.lokasi_alamat }}
          </p>
          <p v-if="task.harga" class="text-sm font-bold text-(--color-on-primary-container) mt-1.5">
            Rp{{ task.harga.toLocaleString('id-ID') }}
          </p>
        </RouterLink>
        <div v-if="!taskStore.tasks.length" class="text-center py-10">
          <Icon name="clipboard" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
          <p class="text-sm text-(--color-on-surface-variant)">Belum ada tugas tersedia di sekitarmu.</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
