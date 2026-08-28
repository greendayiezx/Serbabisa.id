<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import { useTaskStore } from '@/stores/task'
import { rupiah } from '@/lib/rupiah'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import type { TaskStatus } from '@/types'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const taskStore = useTaskStore()

/*
 * Pemuat berputar, bukan skeleton: isi halaman ini berubah menurut status —
 * ada kartu mitra atau tidak, ada tombol selesai atau tidak — jadi kerangka
 * apa pun yang digambar punya peluang besar salah bentuk. Kerangka yang salah
 * lebih mengganggu daripada tidak ada kerangka.
 *
 * Sebelum ini halaman berdiri kosong sampai datanya datang, tanpa satu pun
 * tanda bahwa ada yang sedang dikerjakan.
 */
const memuat = ref(true)

onMounted(muatTugas)

async function muatTugas() {
  try {
    await ambilTugas()
  } finally {
    // Termasuk saat gagal: indikator harus berhenti, bukan berputar selamanya.
    memuat.value = false
  }
}

async function ambilTugas() {
  const task = await taskStore.fetchTask(Number(route.params.id))

  /*
   * Permintaan penawaran punya halamannya sendiri.
   *
   * Ia bukan pekerjaan yang sedang berjalan — tidak ada mitra, status, atau
   * harga untuk ditampilkan di sini. Yang relevan adalah nomor REQ-, ringkasan
   * kantor, dan langkah berikutnya, dan semua itu sudah ada di halaman
   * konfirmasinya. Jadi dari riwayat tugas, pengguna dibawa ke sana.
   */
  const nomor = task?.nomor_invoice ?? taskStore.currentTask?.nomor_invoice ?? ''
  if (nomor.startsWith('REQ-')) {
    router.replace({ name: 'kantor-permintaan-terkirim', params: { nomor } })
  }
}

const statusChip: Record<TaskStatus, { label: string; class: string }> = {
  pending: { label: 'Menunggu Mitra', class: 'bg-(--color-primary-container) text-(--color-on-primary-container)' },
  accepted: { label: 'Diterima', class: 'bg-transparent text-[#166534] px-0 py-0' },
  in_progress: { label: 'Dikerjakan', class: 'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)' },
  completed: { label: 'Selesai', class: 'bg-(--color-lime) text-[#33430b]' },
  cancelled: { label: 'Dibatalkan', class: 'bg-(--color-surface-container-high) text-(--color-on-surface-variant)' },
}

const stepIndex = computed(() => {
  const order: TaskStatus[] = ['pending', 'accepted', 'in_progress', 'completed']
  const status = taskStore.currentTask?.status
  return status ? order.indexOf(status) : -1
})

const initial = (name: string) => name.trim().charAt(0).toUpperCase()

async function markCompleted() {
  if (!taskStore.currentTask) return
  await taskStore.updateTaskStatus(taskStore.currentTask.id, 'completed')
}
</script>

<template>
  <AppLayout>
    <PemuatBerputar v-if="memuat" label="Memuat tugas…" />

    <div v-else-if="taskStore.currentTask" class="flex flex-col">
      <div class="flex items-center gap-3 px-5 pt-4.5 pb-1">
        <button type="button" aria-label="Kembali" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="kembali">
          <Icon name="arrow-left" class="w-[19px] h-[19px]" />
        </button>
        <h3 class="text-base font-extrabold flex-1 truncate">{{ taskStore.currentTask.judul }}</h3>
        <span class="rounded-full text-xs font-bold px-3 py-1.5 shrink-0" :class="statusChip[taskStore.currentTask.status].class">
          {{ statusChip[taskStore.currentTask.status].label }}
        </span>
      </div>

      <!-- progress stepper -->
      <div v-if="taskStore.currentTask.status !== 'cancelled'" class="flex px-5 pt-5">
        <div v-for="(label, i) in ['Menunggu', 'Diterima', 'Dikerjakan', 'Selesai']" :key="label" class="flex-1 text-center relative">
          <div
            v-if="i > 0"
            class="absolute top-[11px] left-[-50%] w-full h-0.5"
            :class="i <= stepIndex ? 'bg-(--color-lime)' : 'bg-(--color-outline)'"
          ></div>
          <div
            class="relative z-10 w-5.5 h-5.5 rounded-full mx-auto mb-1.5 flex items-center justify-center"
            :class="i < stepIndex ? 'bg-(--color-lime)' : i === stepIndex ? 'bg-(--color-azure) ring-4 ring-(--color-primary-container)' : 'bg-(--color-surface-container-high) border border-(--color-outline)'"
          >
            <Icon v-if="i < stepIndex" name="check" class="w-3 h-3 text-white" />
          </div>
          <span class="text-[10.5px] font-bold" :class="i === stepIndex ? 'text-(--color-on-surface)' : 'text-(--color-on-surface-variant)'">{{ label }}</span>
        </div>
      </div>

      <!-- map placeholder -->
      <div class="mx-5 mt-5 h-37 rounded-(--radius-card) relative overflow-hidden bg-(--color-surface-container) bg-[linear-gradient(var(--color-surface-container-high)_1px,transparent_1px),linear-gradient(90deg,var(--color-surface-container-high)_1px,transparent_1px)] bg-[length:26px_26px]">
        <div class="absolute inset-x-0 top-[58%] h-2.5 bg-(--color-surface-0)"></div>
        <div class="absolute inset-y-0 left-[32%] w-2.5 bg-(--color-surface-0)"></div>
        <Icon name="pin" class="absolute top-[42%] left-[28%] w-7.5 h-7.5 text-(--color-azure)" />
        <span class="absolute bottom-2.5 left-2.5 glass-panel rounded-full text-xs font-bold px-2.5 py-1.5">{{ taskStore.currentTask.lokasi_alamat }}</span>
      </div>

      <!-- info card -->
      <div class="mx-5 mt-4 rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) px-4.5">
        <div class="flex gap-2.5 items-start py-3 border-b border-(--color-outline)">
          <Icon name="layers" class="w-4.5 h-4.5 text-(--color-on-primary-container) mt-0.5" />
          <div><div class="text-[11.5px] text-(--color-on-surface-variant) font-semibold">Deskripsi</div><div class="text-[13.5px] font-semibold mt-0.5">{{ taskStore.currentTask.deskripsi }}</div></div>
        </div>
        <div class="flex gap-2.5 items-start py-3 border-b border-(--color-outline)" v-if="taskStore.currentTask.harga || taskStore.currentTask.budget">
          <Icon name="wallet" class="w-4.5 h-4.5 text-(--color-on-primary-container) mt-0.5" />
          <div><div class="text-[11.5px] text-(--color-on-surface-variant) font-semibold">{{ taskStore.currentTask.harga ? 'Harga' : 'Budget Diajukan' }}</div><div class="text-[13.5px] font-bold mt-0.5">{{ rupiah(taskStore.currentTask.harga ?? taskStore.currentTask.budget) }}</div></div>
        </div>
        <div class="flex gap-2.5 items-start py-3">
          <Icon name="clock" class="w-4.5 h-4.5 text-(--color-on-primary-container) mt-0.5" />
          <div><div class="text-[11.5px] text-(--color-on-surface-variant) font-semibold">Dibuat</div><div class="text-[13.5px] font-bold mt-0.5">{{ new Date(taskStore.currentTask.created_at).toLocaleString('id-ID') }}</div></div>
        </div>
      </div>

      <!-- mitra card -->
      <div v-if="taskStore.currentTask.mitra_id" class="mx-5 mt-3.5 rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4 flex items-center gap-3">
        <div class="w-11.5 h-11.5 rounded-full flex items-center justify-center font-display font-extrabold text-white bg-linear-to-br from-(--color-azure) to-[#0f7fce] shrink-0">
          {{ initial(taskStore.currentTask.mitra?.name ?? 'M') }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 font-extrabold text-sm">
            {{ taskStore.currentTask.mitra?.name ?? 'Mitra' }}
            <Icon name="shield" class="w-3.5 h-3.5 text-(--color-on-secondary-container)" />
          </div>
          <div class="text-xs text-(--color-on-surface-variant) mt-0.5">Terverifikasi</div>
        </div>
        <button
          type="button"
          class="flex items-center gap-1.5 rounded-full border border-(--color-azure) text-(--color-on-primary-container) text-sm font-bold px-3.5 py-2"
          @click="router.push({ name: 'task-chat', params: { id: taskStore.currentTask.id } })"
        >
          <Icon name="chat" class="w-[15px] h-[15px]" />Chat
        </button>
      </div>

      <!-- sticky action -->
      <div class="mt-6 px-5 pb-6 border-t border-(--color-outline) pt-4">
        <button
          v-if="taskStore.currentTask.status === 'in_progress'"
          type="button"
          class="w-full flex items-center justify-center gap-2 rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11"
          @click="markCompleted"
        >
          <Icon name="check-circle" class="w-[17px] h-[17px]" />Tandai Selesai
        </button>
        <p v-else-if="taskStore.currentTask.status === 'pending'" class="text-center text-sm text-(--color-on-surface-variant)">Menunggu Mitra menerima tugas ini.</p>
        <p v-else-if="taskStore.currentTask.status === 'completed'" class="text-center text-sm font-bold text-(--color-on-secondary-container)">Tugas ini sudah selesai. Terima kasih!</p>
        <div v-if="taskStore.currentTask.status !== 'completed' && taskStore.currentTask.status !== 'cancelled'" class="text-center mt-1">
          <button type="button" class="text-sm font-bold text-(--color-error) py-1.5">Ajukan Komplain</button>
        </div>
      </div>
    </div>
    <p v-else class="text-(--color-on-surface-variant) text-center py-10">Memuat tugas...</p>
  </AppLayout>
</template>
