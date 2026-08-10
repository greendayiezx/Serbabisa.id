import { defineStore } from 'pinia'
import { ref } from 'vue'

export type TaskDraftTipe = 'fixed' | 'custom'

export interface TaskDraft {
  tipe: TaskDraftTipe
  category_id: number | null
  judul: string
  deskripsi: string
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  budget: number | null
}

export const useTaskDraftStore = defineStore('taskDraft', () => {
  const step = ref(1)
  const form = ref<TaskDraft>({
    tipe: 'fixed',
    category_id: null,
    judul: '',
    deskripsi: '',
    lokasi_alamat: '',
    lokasi_lat: 0,
    lokasi_lng: 0,
    budget: null,
  })

  function reset() {
    step.value = 1
    form.value = {
      tipe: 'fixed',
      category_id: null,
      judul: '',
      deskripsi: '',
      lokasi_alamat: '',
      lokasi_lat: 0,
      lokasi_lng: 0,
      budget: null,
    }
  }

  return { step, form, reset }
})