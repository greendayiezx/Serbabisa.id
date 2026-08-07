<script setup lang="ts">
import { onMounted } from 'vue'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'

const taskStore = useTaskStore()

onMounted(() => {
  taskStore.fetchTasks({ status: 'pending' })
})
</script>

<template>
  <AppLayout>
    <h2 class="text-lg font-semibold mb-3">Tugas di Sekitar Anda</h2>
    <div class="space-y-2">
      <RouterLink
        v-for="task in taskStore.tasks"
        :key="task.id"
        :to="{ name: 'task-detail', params: { id: task.id } }"
        class="block rounded-xl border border-gray-200 bg-white p-4"
      >
        <p class="font-medium">{{ task.judul }}</p>
        <p class="text-sm text-gray-500 mt-1">{{ task.lokasi_alamat }}</p>
        <p class="text-sm text-brand font-medium mt-1" v-if="task.harga">
          Rp {{ task.harga.toLocaleString('id-ID') }}
        </p>
      </RouterLink>
      <p v-if="!taskStore.tasks.length" class="text-gray-500 text-sm">Belum ada tugas tersedia.</p>
    </div>
  </AppLayout>
</template>
