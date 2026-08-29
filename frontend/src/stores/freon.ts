import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Isian "Cek & Tambah Freon", dititipkan dari layar form ke layar ringkasan.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi pemesanan.
 */
export interface DraftFreon {
  unit: number
  keluhan: string[]
  menyala: boolean
  tipe: string
  kapasitas: string
  merek: string
  jenisFreon: string
  catatan: string
  tanggal: string
  slot: string
}

export const useFreonStore = defineStore('freon', () => {
  const draft = ref<DraftFreon | null>(null)

  function set(d: DraftFreon) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, set, hapus }
})
