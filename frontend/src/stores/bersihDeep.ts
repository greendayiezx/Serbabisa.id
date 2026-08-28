import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Pilihan dari halaman Deep Cleaning, dititipkan ke halaman konfirmasi.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi pemesanan, dan
 * membangkitkannya kembali berhari-hari kemudian akan menampilkan estimasi
 * dengan tarif yang mungkin sudah berubah.
 */
export interface DraftDeep {
  paketId: string
  paketNama: string
  paketDeskripsi: string
  hargaPaket: number
  /** Layanan tambahan yang sudah termasuk paket — tidak boleh dijual lagi. */
  termasuk: string[]
  luasM2: number
  jumlahRuangan: number
  tanggal: string
  waktu: string
}

export const useBersihDeepStore = defineStore('bersihDeep', () => {
  const draft = ref<DraftDeep | null>(null)

  function set(d: DraftDeep) {
    draft.value = d
  }

  function hapus() {
    draft.value = null
  }

  return { draft, set, hapus }
})
