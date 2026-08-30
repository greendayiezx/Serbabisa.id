import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Draf permintaan pemasangan AC yang dibawa dari langkah 1 ke langkah 2.
 *
 * Isinya persis keluaran langkah 1 — apa yang dikerjakan. Lokasi dan data
 * pemesan dikumpulkan di langkah 2 dan langsung dikirim dari sana, jadi tidak
 * perlu singgah di sini.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi, dan foto yang
 * ikut di dalamnya bisa berukuran megabita — kuota penyimpanan peramban akan
 * habis sebelum drafnya berguna.
 */
export interface DraftPasang {
  jenisPekerjaan: string
  unit: number
  ketersediaan: string
  kebutuhan: string
  merek: string
  kapasitas: string
  lokasiIndoor: string
  lokasiOutdoor: string
  material: string[]
  caraPenawaran: string
  catatan: string
  /** Peta id slot foto ke data URL-nya. */
  foto: Record<string, string>
}

export const usePasangACStore = defineStore('pasangAC', () => {
  const draft = ref<DraftPasang | null>(null)

  function set(d: DraftPasang) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, set, hapus }
})
