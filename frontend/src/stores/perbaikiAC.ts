import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Draf permintaan perbaikan AC yang dibawa dari langkah 1 ke langkah 2.
 *
 * Isinya keluaran langkah 1 — apa yang rusak dan kapan teknisi datang. Lokasi
 * dan data pemesan dikumpulkan di langkah 2 lalu dikirim bersama dari sana.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi, dan foto di
 * dalamnya bisa berukuran megabita — kuota penyimpanan peramban akan habis
 * sebelum drafnya berguna.
 */
export interface DraftPerbaiki {
  keluhan: string[]
  menyala: boolean
  mulaiTerjadi: string
  unit: number
  merek: string
  tipe: string
  kapasitas: string
  kodeError: string
  catatan: string
  tanggal: string
  slot: string
  /** Peta id slot foto ke data URL-nya. */
  foto: Record<string, string>
}

export const usePerbaikiACStore = defineStore('perbaikiAC', () => {
  const draft = ref<DraftPerbaiki | null>(null)

  function set(d: DraftPerbaiki) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, set, hapus }
})
