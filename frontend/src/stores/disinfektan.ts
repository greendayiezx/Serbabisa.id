import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Draf pemesanan Disinfektan yang dibawa dari langkah isian ke konfirmasi.
 *
 * Isinya keluaran langkah 1 — apa yang didisinfeksi dan kapan. Lokasi serta
 * data pemesan dikumpulkan di langkah 2 lalu dikirim bersama dari sana, sama
 * seperti alur Servis AC.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi, dan
 * membangkitkannya kembali berhari-hari kemudian akan menampilkan estimasi
 * dengan tarif yang mungkin sudah berubah.
 */
export interface DraftDisinfektan {
  properti: string
  luas: string
  ruangan: number
  toilet: number
  kondisi: string
  perhatian: string[]
  catatan: string
  tanggal: string
  waktu: string
  /**
   * Foto area sebagai data URL, berkunci id slot.
   *
   * Ikut alasan store ini tidak disimpan ke localStorage, dan di sini alasannya
   * lebih kuat lagi: beberapa foto ukuran penuh akan menabrak kuota
   * penyimpanan peramban dan menggagalkan penyimpanan draf lain sekalian.
   */
  foto: Record<string, string>
}

export const useDisinfektanStore = defineStore('disinfektan', () => {
  const draft = ref<DraftDisinfektan | null>(null)

  function set(d: DraftDisinfektan) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, set, hapus }
})
