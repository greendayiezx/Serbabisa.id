import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export interface BelanjaItem {
  id: string
  nama: string
  catatan: string
  qty: number
  kategori: string
  /** Harga satuan final (sudah termasuk markup). Kosong untuk item ketik manual. */
  harga?: number
  /** Satuan jual: kg, sisir, pcs, pack, cup. */
  satuan?: string
}

export interface BelanjaDraft {
  toko: string
  alamatToko: string
  items: BelanjaItem[]
  budget: number | null
  instruksi: string
}

const STORAGE_KEY = 'tugasin_belanja_draft'

function draftKosong(): BelanjaDraft {
  return { toko: '', alamatToko: '', items: [], budget: null, instruksi: '' }
}

/**
 * Draft dibaca ulang dari localStorage saat store dibuat, supaya keranjang tidak
 * hilang kalau halaman di-refresh atau dibuka lewat URL langsung.
 *
 * Isinya divalidasi per field, bukan langsung dipakai: data di localStorage bisa
 * berasal dari versi aplikasi yang lebih lama atau diubah manual, dan satu field
 * yang bentuknya salah cukup untuk membuat halaman keranjang error.
 */
function muatDraft(): BelanjaDraft {
  const kosong = draftKosong()
  try {
    const mentah = localStorage.getItem(STORAGE_KEY)
    if (!mentah) return kosong
    const data = JSON.parse(mentah) as Partial<BelanjaDraft>
    if (!data || typeof data !== 'object') return kosong

    const items = Array.isArray(data.items)
      ? data.items
          .filter(
            (i): i is BelanjaItem =>
              !!i && typeof i.id === 'string' && typeof i.nama === 'string' && Number.isFinite(i.qty),
          )
          .map((i) => ({
            id: i.id,
            nama: i.nama,
            catatan: typeof i.catatan === 'string' ? i.catatan : '',
            qty: Math.max(1, Math.floor(i.qty)),
            kategori: typeof i.kategori === 'string' ? i.kategori : 'lainnya',
            ...(Number.isFinite(i.harga) ? { harga: i.harga } : {}),
            ...(typeof i.satuan === 'string' ? { satuan: i.satuan } : {}),
          }))
      : []

    return {
      toko: typeof data.toko === 'string' ? data.toko : '',
      alamatToko: typeof data.alamatToko === 'string' ? data.alamatToko : '',
      items,
      budget: Number.isFinite(data.budget) ? (data.budget as number) : null,
      instruksi: typeof data.instruksi === 'string' ? data.instruksi : '',
    }
  } catch {
    // JSON rusak / localStorage diblokir (mode privat) — mulai dari draft kosong.
    return kosong
  }
}

export const useBelanjaDraftStore = defineStore('belanjaDraft', () => {
  const draft = ref<BelanjaDraft>(muatDraft())

  // Setiap perubahan langsung ditulis balik; deep karena qty item berubah di tempat.
  watch(
    draft,
    (nilai) => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(nilai))
      } catch {
        // Kuota penuh atau storage diblokir — draft tetap jalan di memori.
      }
    },
    { deep: true },
  )

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
    draft.value = draftKosong()
    try {
      localStorage.removeItem(STORAGE_KEY)
    } catch {
      // Diabaikan: draft di memori sudah dikosongkan.
    }
  }

  return { draft, setDraft, patchDraft, addItem, removeItem, updateItemQty, clearDraft }
})
