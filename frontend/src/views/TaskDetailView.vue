<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'

const route = useRoute()
const taskStore = useTaskStore()

onMounted(() => {
  taskStore.fetchTask(Number(route.params.id))
})
</script>

<template>
  <AppLayout>
    <div v-if="taskStore.currentTask" class="space-y-3">
      <h2 class="text-lg font-semibold">{{ taskStore.currentTask.judul }}</h2>
      <p class="text-sm uppercase tracking-wide text-brand font-medium">
        {{ taskStore.currentTask.status }}
      </p>
      <p class="text-gray-700">{{ taskStore.currentTask.deskripsi }}</p>
      <p class="text-sm text-gray-500">{{ taskStore.currentTask.lokasi_alamat }}</p>
    </div>
    <p v-else class="text-gray-500">Memuat tugas...</p>
  </AppLayout>
</template>
