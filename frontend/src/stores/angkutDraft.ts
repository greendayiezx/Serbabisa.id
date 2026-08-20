import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface AngkutDraft {
  /** Id pilihan yang dikirim ke server untuk dihitung ulang (bukan harga/label). */
  vehicleId: string
  deliveryId: string
  protectionId?: string
  vehicleLabel: string
  vehicleImage?: string
  deliveryLabel: string
  helperCount: number
  tanggal: string
  waktu: string
  beratTotal: number | null
  total: number
  catatan?: string
  namaPenerima?: string
  teleponPenerima?: string
  protectionLabel?: string
  protectionPrice?: number
}

export const useAngkutDraftStore = defineStore('angkutDraft', () => {
  const draft = ref<AngkutDraft | null>(null)

  function setDraft(value: AngkutDraft) {
    draft.value = value
  }

  function patchDraft(value: Partial<AngkutDraft>) {
    if (!draft.value) return
    draft.value = { ...draft.value, ...value }
  }

  function clearDraft() {
    draft.value = null
  }

  return { draft, setDraft, patchDraft, clearDraft }
})
