import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface BelanjaItem {
  id: string
  nama: string
  catatan: string
  qty: number
  kategori: string
}

export interface BelanjaDraft {
  toko: string
  alamatToko: string
  items: BelanjaItem[]
  budget: number | null
  instruksi: string
}

export const useBelanjaDraftStore = defineStore('belanjaDraft', () => {
  const draft = ref<BelanjaDraft>({
    toko: '',
    alamatToko: '',
    items: [],
    budget: null,
    instruksi: '',
  })

  function setDraft(value: BelanjaDraft) {
    draft.value = value
  }

  function patchDraft(value: Partial<BelanjaDraft>) {
    draft.value = { ...draft.value, ...value }
  }

  function addItem(item: BelanjaItem) {
    draft.value.items.push(item)
  }

  function removeItem(id: string) {
    draft.value.items = draft.value.items.filter((i) => i.id !== id)
  }

  function updateItemQty(id: string, qty: number) {
    const item = draft.value.items.find((i) => i.id === id)
    if (item) item.qty = Math.max(1, qty)
  }

  function clearDraft() {
    draft.value = {
      toko: '',
      alamatToko: '',
      items: [],
      budget: null,
      instruksi: '',
    }
  }

  return { draft, setDraft, patchDraft, addItem, removeItem, updateItemQty, clearDraft }
})
