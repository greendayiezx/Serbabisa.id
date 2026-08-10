<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '@/api/client'
import AdminLayout from '@/layouts/AdminLayout.vue'
import Icon from '@/components/icons/Icon.vue'

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

// Illustrative rows — swap for GET /admin/users and a disputes endpoint once those UIs exist.
const recentUsers = [
  { name: 'Rani Kusuma', role: 'Customer', status: 'Terverifikasi', statusClass: 'chip-lime', date: '07 Agu 2026', action: 'Detail' },
  { name: 'Budi Santoso', role: 'Mitra', status: 'Menunggu', statusClass: 'chip-gold', date: '06 Agu 2026', action: 'Verifikasi' },
  { name: 'Sari Dewi', role: 'Mitra', status: 'Terverifikasi', statusClass: 'chip-lime', date: '05 Agu 2026', action: 'Detail' },
  { name: 'Andi Wijaya', role: 'Customer', status: 'Terverifikasi', statusClass: 'chip-lime', date: '05 Agu 2026', action: 'Detail' },
  { name: 'Doni Prasetyo', role: 'Mitra', status: 'Ditolak', statusClass: 'chip-error', date: '04 Agu 2026', action: 'Detail' },
]

const disputes = [
  { title: 'Rakit Meja IKEA', reporter: 'Rani Kusuma', status: 'Ditinjau', statusClass: 'chip-gold', reason: 'Mitra belum menyelesaikan tugas sesuai waktu yang disepakati…' },
  { title: 'Antar Paket ke Bekasi', reporter: 'Andi Wijaya', status: 'Baru', statusClass: 'chip-error', reason: 'Barang diterima dalam kondisi rusak, minta klarifikasi mitra…' },
]

function initial(name: string) {
  return name.trim().charAt(0).toUpperCase()
}
</script>

<template>
  <AdminLayout>
    <div v-if="metrics" class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-6.5">
      <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4">
        <div class="flex justify-between items-start">
          <span class="text-xs font-bold text-(--color-on-surface-variant)">Total Pengguna</span>
          <div class="w-7.5 h-7.5 rounded-[10px] flex items-center justify-center bg-(--color-primary-container)">
            <Icon name="users" class="w-4 h-4 text-(--color-on-primary-container)" />
          </div>
        </div>
        <div class="font-display text-[26px] font-extrabold mt-2 tabular-nums">{{ metrics.total_users }}</div>
        <div class="text-[11.5px] font-bold mt-1.5 text-(--color-on-secondary-container)">Terdaftar di platform</div>
      </div>

      <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4">
        <div class="flex justify-between items-start">
          <span class="text-xs font-bold text-(--color-on-surface-variant)">Mitra Menunggu Verifikasi</span>
          <div class="w-7.5 h-7.5 rounded-[10px] flex items-center justify-center bg-(--color-tertiary-container)">
            <Icon name="shield" class="w-4 h-4 text-(--color-on-tertiary-container)" />
          </div>
        </div>
        <div class="font-display text-[26px] font-extrabold mt-2 tabular-nums">{{ metrics.total_mitra_pending_verifikasi }}</div>
        <div class="text-[11.5px] font-bold mt-1.5 text-(--color-on-tertiary-container)">Perlu ditinjau</div>
      </div>

      <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4">
        <div class="flex justify-between items-start">
          <span class="text-xs font-bold text-(--color-on-surface-variant)">Tugas Minggu Ini</span>
          <div class="w-7.5 h-7.5 rounded-[10px] flex items-center justify-center bg-(--color-secondary-container)">
            <Icon name="clipboard" class="w-4 h-4 text-(--color-on-secondary-container)" />
          </div>
        </div>
        <div class="font-display text-[26px] font-extrabold mt-2 tabular-nums">{{ metrics.tasks_posted_this_week }}</div>
        <div class="text-[11.5px] font-bold mt-1.5 text-(--color-on-secondary-container)">{{ metrics.tasks_completed_this_week }} selesai</div>
      </div>

      <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4">
        <div class="flex justify-between items-start">
          <span class="text-xs font-bold text-(--color-on-surface-variant)">Dispute Terbuka</span>
          <div class="w-7.5 h-7.5 rounded-[10px] flex items-center justify-center bg-(--color-error-container)">
            <Icon name="alert" class="w-4 h-4 text-(--color-on-error-container)" />
          </div>
        </div>
        <div class="font-display text-[26px] font-extrabold mt-2 tabular-nums">{{ metrics.disputes_open }}</div>
        <div class="text-[11.5px] font-bold mt-1.5 text-(--color-error)">Perlu ditangani</div>
      </div>
    </div>

    <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4.5 md:p-5 mb-5.5">
      <div class="flex items-baseline justify-between mb-3">
        <h4 class="font-extrabold text-[15px]">Pengguna Terbaru</h4>
        <span class="text-xs text-(--color-on-surface-variant)">Diurutkan berdasarkan tanggal daftar</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse min-w-160">
          <thead>
            <tr>
              <th class="text-left text-[11px] font-bold uppercase tracking-wide text-(--color-on-surface-variant) pb-2.5 border-b border-(--color-outline)">Nama</th>
              <th class="text-left text-[11px] font-bold uppercase tracking-wide text-(--color-on-surface-variant) pb-2.5 border-b border-(--color-outline)">Role</th>
              <th class="text-left text-[11px] font-bold uppercase tracking-wide text-(--color-on-surface-variant) pb-2.5 border-b border-(--color-outline)">Status Verifikasi</th>
              <th class="text-left text-[11px] font-bold uppercase tracking-wide text-(--color-on-surface-variant) pb-2.5 border-b border-(--color-outline)">Tanggal Daftar</th>
              <th class="border-b border-(--color-outline)"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in recentUsers" :key="u.name">
              <td class="py-3 border-b border-(--color-outline)">
                <div class="flex items-center gap-2.5 font-bold text-[13.5px]">
                  <div class="w-8.5 h-8.5 rounded-full flex items-center justify-center font-display font-extrabold text-white text-xs bg-linear-to-br from-(--color-azure) to-[#0f7fce]">{{ initial(u.name) }}</div>
                  {{ u.name }}
                </div>
              </td>
              <td class="py-3 border-b border-(--color-outline)">
                <span
                  class="rounded-full text-xs font-bold px-2.5 py-1"
                  :class="u.role === 'Customer' ? 'bg-(--color-primary-container) text-(--color-on-primary-container)' : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'"
                >{{ u.role }}</span>
              </td>
              <td class="py-3 border-b border-(--color-outline)">
                <span
                  class="rounded-full text-xs font-bold px-2.5 py-1"
                  :class="{
                    'bg-(--color-lime) text-[#33430b]': u.statusClass === 'chip-lime',
                    'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)': u.statusClass === 'chip-gold',
                    'bg-(--color-error-container) text-(--color-on-error-container)': u.statusClass === 'chip-error',
                  }"
                >{{ u.status }}</span>
              </td>
              <td class="py-3 border-b border-(--color-outline) text-[13.5px] tabular-nums">{{ u.date }}</td>
              <td class="py-3 border-b border-(--color-outline) text-right">
                <button
                  type="button"
                  class="rounded-full text-xs font-bold px-3.5 py-2 border"
                  :class="u.action === 'Verifikasi' ? 'bg-(--color-azure) text-white border-transparent' : 'border-(--color-azure) text-(--color-on-primary-container)'"
                >{{ u.action }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4.5 md:p-5">
      <div class="flex items-baseline justify-between mb-3">
        <h4 class="font-extrabold text-[15px]">Dispute Aktif</h4>
        <span class="text-xs text-(--color-on-surface-variant)">{{ disputes.length }} kasus</span>
      </div>
      <div class="flex flex-col gap-2.5">
        <div v-for="d in disputes" :key="d.title" class="flex items-center gap-3.5 rounded-2xl border border-(--color-outline) p-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <b class="text-[13.5px]">{{ d.title }}</b>
              <span
                class="rounded-full text-xs font-bold px-2.5 py-1"
                :class="d.statusClass === 'chip-gold' ? 'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)' : 'bg-(--color-error-container) text-(--color-on-error-container)'"
              >{{ d.status }}</span>
            </div>
            <div class="text-[11.5px] text-(--color-on-surface-variant)">Dilaporkan oleh {{ d.reporter }}</div>
            <p class="text-xs text-(--color-on-surface-variant) mt-1 truncate">"{{ d.reason }}"</p>
          </div>
          <button type="button" class="rounded-full text-xs font-bold px-3.5 py-2 border border-(--color-azure) text-(--color-on-primary-container) shrink-0">Tangani</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
