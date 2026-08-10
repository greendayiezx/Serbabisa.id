import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface AngkutDraft {
  vehicleLabel: string
  vehicleImage?: string
  deliveryLabel: string
  helperCount: number
  tanggal: string
  waktu: string
  total: number
}

export const useAngkutDraftStore = defineStore('angkutDraft', () => {
  const draft = ref<AngkutDraft | null>(null)

  function setDraft(value: AngkutDraft) {
    draft.value = value
  }

  function clearDraft() {
    draft.value = null
  }

  return { draft, setDraft, clearDraft }
})
