import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Pilihan Servis AC yang dibawa antar halaman dalam satu sesi pemesanan.
 *
 * Isinya persis keluaran langkah 1 — yang menentukan harga. Jadwal, promo,
 * catatan, dan data pemesan dikumpulkan di halaman konfirmasi dan langsung
 * dikirim dari sana, jadi tidak perlu singgah di sini.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi, dan
 * membangkitkannya kembali berhari-hari kemudian akan menampilkan estimasi
 * dengan tarif yang mungkin sudah berubah.
 */
export interface DraftAC {
  paket: string
  unit: number
  tipe: string
  kapasitas: string
  terakhirCuci: string
  kondisi: string[]
  alamat: string
  lat: number
  lng: number
}

export const useServisACStore = defineStore('servisAC', () => {
  const draft = ref<DraftAC | null>(null)

  /** Nomor pesanan terakhir, dibaca halaman "Servis Selesai". */
  const nomorTerakhir = ref<string | null>(null)

  function set(d: DraftAC) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, nomorTerakhir, set, hapus }
})
