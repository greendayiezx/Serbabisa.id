<script setup lang="ts">
import { onMounted } from 'vue'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'

const taskStore = useTaskStore()

onMounted(() => {
  taskStore.fetchCategories()
})
</script>

<template>
  <AppLayout>
    <h2 class="text-lg font-semibold mb-3">Kategori Jasa</h2>
    <div class="grid grid-cols-2 gap-3">
      <RouterLink
        v-for="category in taskStore.categories"
        :key="category.id"
        :to="{ name: 'task-create', query: { category: category.slug } }"
        class="rounded-xl border border-gray-200 bg-white p-4 hover:border-brand transition-colors"
      >
        <p class="font-medium">{{ category.nama }}</p>
        <p class="text-sm text-gray-500 mt-1">{{ category.deskripsi }}</p>
      </RouterLink>
    </div>
  </AppLayout>
</template>
