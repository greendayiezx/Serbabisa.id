<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTaskStore } from '@/stores/task'
import AppLayout from '@/layouts/AppLayout.vue'

const route = useRoute()
const router = useRouter()
const taskStore = useTaskStore()

const step = ref(1)
const form = ref({
  tipe: 'fixed' as 'fixed' | 'custom',
  category_id: null as number | null,
  judul: '',
  deskripsi: '',
  lokasi_alamat: '',
  lokasi_lat: 0,
  lokasi_lng: 0,
  budget: null as number | null,
})
const submitting = ref(false)
const error = ref('')

onMounted(async () => {
  await taskStore.fetchCategories()
  const slug = route.query.category as string | undefined
  if (slug) {
    const found = taskStore.categories.find((c) => c.slug === slug)
    if (found) {
      form.value.category_id = found.id
      form.value.tipe = 'fixed'
    }
  }

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      form.value.lokasi_lat = pos.coords.latitude
      form.value.lokasi_lng = pos.coords.longitude
    })
  }
})

async function handleSubmit() {
  error.value = ''
  submitting.value = true
  try {
    const task = await taskStore.createTask(form.value)
    router.push({ name: 'task-detail', params: { id: task.id } })
  } catch (err: any) {
    error.value = err.response?.data?.message ?? 'Gagal membuat tugas.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppLayout>
    <h2 class="text-lg font-semibold mb-4">Buat Tugas — Langkah {{ step }}/3</h2>

    <p v-if="error" class="text-sm text-red-600 mb-3">{{ error }}</p>

    <div v-if="step === 1" class="space-y-3">
      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-lg border py-2 min-h-11"
          :class="form.tipe === 'fixed' ? 'border-brand text-brand bg-brand/5' : 'border-gray-300'"
          @click="form.tipe = 'fixed'"
        >
          Kategori Tetap
        </button>
        <button
          type="button"
          class="flex-1 rounded-lg border py-2 min-h-11"
          :class="form.tipe === 'custom' ? 'border-brand text-brand bg-brand/5' : 'border-gray-300'"
          @click="form.tipe = 'custom'"
        >
          Custom Request
        </button>
      </div>

      <div v-if="form.tipe === 'fixed'" class="grid grid-cols-2 gap-2">
        <button
          v-for="c in taskStore.categories"
          :key="c.id"
          type="button"
          class="rounded-lg border p-3 text-left min-h-11"
          :class="form.category_id === c.id ? 'border-brand text-brand bg-brand/5' : 'border-gray-300'"
          @click="form.category_id = c.id"
        >
          {{ c.nama }}
        </button>
      </div>

      <button
        class="w-full bg-brand text-white rounded-lg py-2.5 font-medium min-h-11"
        @click="step = 2"
      >
        Lanjut
      </button>
    </div>

    <div v-else-if="step === 2" class="space-y-3">
      <div>
        <label class="block text-sm font-medium mb-1">Judul Tugas</label>
        <input v-model="form.judul" class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Deskripsi</label>
        <textarea v-model="form.deskripsi" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Alamat Lokasi</label>
        <input v-model="form.lokasi_alamat" class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>
      <div v-if="form.tipe === 'custom'">
        <label class="block text-sm font-medium mb-1">Budget (Rp)</label>
        <input v-model.number="form.budget" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>
      <div class="flex gap-2">
        <button class="flex-1 border border-gray-300 rounded-lg py-2.5 min-h-11" @click="step = 1">Kembali</button>
        <button class="flex-1 bg-brand text-white rounded-lg py-2.5 font-medium min-h-11" @click="step = 3">Lanjut</button>
      </div>
    </div>

    <div v-else class="space-y-3">
      <div class="rounded-lg border border-gray-200 p-4 bg-white">
        <p class="font-medium">{{ form.judul }}</p>
        <p class="text-sm text-gray-600 mt-1">{{ form.deskripsi }}</p>
        <p class="text-sm text-gray-500 mt-1">{{ form.lokasi_alamat }}</p>
      </div>
      <div class="flex gap-2">
        <button class="flex-1 border border-gray-300 rounded-lg py-2.5 min-h-11" @click="step = 2">Kembali</button>
        <button
          :disabled="submitting"
          class="flex-1 bg-brand text-white rounded-lg py-2.5 font-medium min-h-11 disabled:opacity-50"
          @click="handleSubmit"
        >
          {{ submitting ? 'Mengirim...' : 'Konfirmasi & Kirim' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>
