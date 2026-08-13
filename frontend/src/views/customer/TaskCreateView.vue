<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTaskStore } from '@/stores/task'
import { useTaskDraftStore } from '@/stores/taskDraft'
import { useLocationStore } from '@/stores/location'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'

const iconMap: Record<string, string> = {
  car: 'car',
  package: 'package',
  'shopping-cart': 'cart',
  sparkles: 'sparkle',
  truck: 'truck',
  wrench: 'wrench',
}

const route = useRoute()
const router = useRouter()
const taskStore = useTaskStore()
const draftStore = useTaskDraftStore()
const locationStore = useLocationStore()

const step = draftStore.step
const form = draftStore.form
const submitting = ref(false)
const error = ref('')

onMounted(async () => {
  await taskStore.fetchCategories()
  const slug = route.query.category as string | undefined
  if (slug) {
    const found = taskStore.categories.find((c) => c.slug === slug)
    if (found) {
      form.category_id = found.id
      form.tipe = 'fixed'
    }
  }
  const location = locationStore.draft
  if (location) {
    form.lokasi_alamat = location.alamat
    form.lokasi_lat = location.lat
    form.lokasi_lng = location.lng
  }
})

function openLocation() {
  router.push({ name: 'task-location' })
}

async function handleSubmit() {
  error.value = ''
  submitting.value = true
  try {
    const task = await taskStore.createTask(form)
    draftStore.reset()
    locationStore.clearDraft()
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
    <div class="flex items-center gap-3 px-5 pt-4.5 pb-1.5">
      <button type="button" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="router.back()">
        <Icon name="arrow-left" class="w-[19px] h-[19px]" />
      </button>
      <h3 class="text-base font-extrabold flex-1">Buat Tugas</h3>
    </div>

    <!-- step strip -->
    <div class="flex items-center px-5 py-4.5">
      <div class="flex items-center gap-2 flex-1">
        <div
          class="w-6.5 h-6.5 rounded-full flex items-center justify-center text-xs font-extrabold shrink-0"
          :class="step > 1 ? 'bg-(--color-lime) text-[#33430b]' : step === 1 ? 'bg-(--color-azure) text-white' : 'bg-(--color-surface-container-high) text-(--color-on-surface-variant)'"
        >
          <Icon v-if="step > 1" name="check" class="w-3.5 h-3.5" />
          <template v-else>1</template>
        </div>
        <span class="text-[11px] font-bold" :class="step === 1 ? 'text-(--color-on-surface)' : 'text-(--color-on-surface-variant)'">Kategori</span>
      </div>
      <div class="flex-1 h-0.5 mx-2" :class="step > 1 ? 'bg-(--color-lime)' : 'bg-(--color-outline)'"></div>
      <div class="flex items-center gap-2 flex-1">
        <div
          class="w-6.5 h-6.5 rounded-full flex items-center justify-center text-xs font-extrabold shrink-0"
          :class="step > 2 ? 'bg-(--color-lime) text-[#33430b]' : step === 2 ? 'bg-(--color-azure) text-white' : 'bg-(--color-surface-container-high) text-(--color-on-surface-variant)'"
        >
          <Icon v-if="step > 2" name="check" class="w-3.5 h-3.5" />
          <template v-else>2</template>
        </div>
        <span class="text-[11px] font-bold" :class="step === 2 ? 'text-(--color-on-surface)' : 'text-(--color-on-surface-variant)'">Detail</span>
      </div>
      <div class="flex-1 h-0.5 mx-2" :class="step > 2 ? 'bg-(--color-lime)' : 'bg-(--color-outline)'"></div>
      <div class="flex items-center gap-2 flex-1">
        <div
          class="w-6.5 h-6.5 rounded-full flex items-center justify-center text-xs font-extrabold shrink-0"
          :class="step === 3 ? 'bg-(--color-azure) text-white' : 'bg-(--color-surface-container-high) text-(--color-on-surface-variant)'"
        >3</div>
        <span class="text-[11px] font-bold" :class="step === 3 ? 'text-(--color-on-surface)' : 'text-(--color-on-surface-variant)'">Konfirmasi</span>
      </div>
    </div>

    <p v-if="error" class="mx-5 mb-3 text-sm text-(--color-error)">{{ error }}</p>

    <!-- Step 1: kategori -->
    <div v-if="step === 1" class="px-5 pb-6 space-y-4">
      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-2xl border py-2.5 min-h-11 font-bold text-sm"
          :class="form.tipe === 'fixed' ? 'border-(--color-azure) text-(--color-on-primary-container) bg-(--color-primary-container)' : 'border-(--color-outline) text-(--color-on-surface-variant)'"
          @click="form.tipe = 'fixed'"
        >
          Kategori Tetap
        </button>
        <button
          type="button"
          class="flex-1 rounded-2xl border py-2.5 min-h-11 font-bold text-sm"
          :class="form.tipe === 'custom' ? 'border-(--color-azure) text-(--color-on-primary-container) bg-(--color-primary-container)' : 'border-(--color-outline) text-(--color-on-surface-variant)'"
          @click="form.tipe = 'custom'"
        >
          Custom Request
        </button>
      </div>

      <div v-if="form.tipe === 'fixed'" class="grid grid-cols-2 gap-2.5">
        <button
          v-for="c in taskStore.categories"
          :key="c.id"
          type="button"
          class="flex items-center gap-2 rounded-2xl border p-3.5 text-left min-h-11 font-bold text-sm"
          :class="form.category_id === c.id ? 'border-(--color-azure) text-(--color-on-primary-container) bg-(--color-primary-container)' : 'border-(--color-outline)'"
          @click="form.category_id = c.id"
        >
          <Icon :name="iconMap[c.ikon ?? ''] || 'layers'" class="w-[18px] h-[18px] shrink-0" />
          {{ c.nama }}
        </button>
      </div>
      <p v-else class="text-sm text-(--color-on-surface-variant)">Ceritakan kebutuhanmu di langkah berikutnya, lengkap dengan budget yang kamu ajukan.</p>

      <button class="w-full rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11" @click="step = 2">Lanjut</button>
    </div>

    <!-- Step 2: detail -->
    <div v-else-if="step === 2" class="px-5 pb-28 space-y-4">
      <span v-if="form.tipe === 'custom'" class="inline-flex items-center gap-1.5 rounded-full text-xs font-bold px-3 py-1.5 bg-(--color-secondary-container) text-(--color-on-secondary-container)">
        <Icon name="layers" class="w-3 h-3" />Custom Request
      </span>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5">Judul Tugas</label>
        <div class="rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3">
          <input v-model="form.judul" placeholder="Contoh: Bantu rakit meja IKEA" class="w-full bg-transparent border-none outline-none text-sm" />
        </div>
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5">Deskripsi</label>
        <textarea
          v-model="form.deskripsi"
          rows="3"
          placeholder="Jelaskan tugasnya sedetail mungkin supaya Mitra paham sebelum menawar"
          class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none resize-none min-h-19.5"
        />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5">Lokasi</label>
        <button
          type="button"
          class="w-full flex items-center gap-2 rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-left min-h-11"
          @click="openLocation"
        >
          <Icon name="pin" class="w-[18px] h-[18px] text-(--color-on-primary-container) shrink-0" />
          <span class="flex-1 text-sm truncate" :class="form.lokasi_alamat ? 'text-(--color-on-surface)' : 'text-(--color-on-surface-variant)'">
            {{ form.lokasi_alamat || 'Ketuk untuk pilih alamat tugas' }}
          </span>
          <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
        </button>
      </div>

      <div v-if="form.tipe === 'custom'">
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5">Budget yang Diajukan</label>
        <div class="flex items-center gap-2 rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3">
          <span class="font-bold text-(--color-on-surface-variant) text-sm">Rp</span>
          <input v-model.number="form.budget" type="number" placeholder="150.000" class="w-full bg-transparent border-none outline-none text-sm" />
        </div>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mt-1.5">Mitra akan mengajukan tawaran di sekitar budget ini.</p>
      </div>

      <div class="flex gap-2 pt-1">
        <button class="flex-1 border border-(--color-outline) rounded-full py-2.5 min-h-11 font-bold text-sm" @click="step = 1">Kembali</button>
        <button class="flex-1 bg-(--color-azure) text-white rounded-full py-2.5 min-h-11 font-bold text-sm" @click="step = 3">Lanjut</button>
      </div>
    </div>

    <!-- Step 3: konfirmasi -->
    <div v-else class="px-5 pb-28 space-y-4">
      <div class="rounded-(--radius-card) border border-(--color-outline) bg-(--color-surface-0) p-4">
        <p class="font-extrabold text-[15px]">{{ form.judul || '(judul belum diisi)' }}</p>
        <p class="text-sm text-(--color-on-surface-variant) mt-1.5">{{ form.deskripsi }}</p>
        <p class="text-sm text-(--color-on-surface-variant) mt-1.5 flex items-center gap-1.5">
          <Icon name="pin" class="w-3.5 h-3.5" />{{ form.lokasi_alamat }}
        </p>
        <p v-if="form.tipe === 'custom' && form.budget" class="text-sm font-bold text-(--color-on-primary-container) mt-2">
          Budget: Rp{{ form.budget.toLocaleString('id-ID') }}
        </p>
      </div>
      <div class="flex gap-2">
        <button class="flex-1 border border-(--color-outline) rounded-full py-2.5 min-h-11 font-bold text-sm" @click="step = 2">Kembali</button>
        <button
          :disabled="submitting"
          class="flex-1 bg-(--color-azure) text-white rounded-full py-2.5 min-h-11 font-bold text-sm disabled:opacity-50"
          @click="handleSubmit"
        >
          {{ submitting ? 'Mengirim...' : 'Konfirmasi & Kirim' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>