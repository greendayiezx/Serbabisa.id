<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '@/api/client'
import AppLayout from '@/layouts/AppLayout.vue'

interface DashboardMetrics {
  total_users: number
  total_mitra_pending_verifikasi: number
  tasks_posted_this_week: number
  tasks_completed_this_week: number
  disputes_open: number
}

const metrics = ref<DashboardMetrics | null>(null)

onMounted(async () => {
  const { data } = await apiClient.get<DashboardMetrics>('/admin/dashboard')
  metrics.value = data
})
</script>

<template>
  <AppLayout>
    <h2 class="text-lg font-semibold mb-3">Dashboard Admin</h2>
    <div v-if="metrics" class="grid grid-cols-2 gap-3">
      <div class="rounded-xl border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-500">Total Pengguna</p>
        <p class="text-2xl font-semibold">{{ metrics.total_users }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-500">Mitra Menunggu Verifikasi</p>
        <p class="text-2xl font-semibold">{{ metrics.total_mitra_pending_verifikasi }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-500">Tugas Minggu Ini</p>
        <p class="text-2xl font-semibold">{{ metrics.tasks_posted_this_week }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4">
        <p class="text-sm text-gray-500">Dispute Terbuka</p>
        <p class="text-2xl font-semibold">{{ metrics.disputes_open }}</p>
      </div>
    </div>
  </AppLayout>
</template>
