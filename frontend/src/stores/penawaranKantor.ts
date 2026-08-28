import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Spesifikasi kantor yang dibawa dari halaman pemesanan ke form penawaran.
 *
 * Halaman pemesanan di-unmount saat berpindah rute, jadi pilihannya harus
 * dititipkan di sini — kalau tidak, form penawaran mulai dari kosong dan
 * pengguna mengisi ulang hal yang baru saja dipilihnya.
 *
 * Sengaja TIDAK disimpan ke localStorage: ini draf satu sesi pemesanan, dan
 * membangkitkannya kembali berhari-hari kemudian akan menampilkan estimasi
 * dengan tarif yang mungkin sudah berubah.
 */
export interface DraftPenawaranKantor {
  jenisId: string
  jenisNama: string
  jenisRentang: string
  luasAcuan: number
  paketId: string
  paketNama: string
  frekuensiId: string
  frekuensiLabel: string
  workstation: number
  ruangMeeting: number
  toilet: number
  pantry: number
  lainnya: string
  /** Nama add-on, untuk ditampilkan ke pengguna. */
  addOn: string[]
  /** ID add-on, untuk dikirim ke server. */
  addOnId: string[]
  catatan: string
  /** Estimasi per kunjungan setelah promo, hanya untuk ditampilkan ulang. */
  estimasi: number
  promoKode: string | null
}

/**
 * Isian lengkap dari halaman "Pesan Sekarang", dititipkan ke halaman
 * "Konfirmasi & Bayar".
 *
 * Foto ikut di sini sebagai File[] — sengaja store ini di memori (bukan
 * localStorage), jadi berkasnya aman disimpan sementara dalam satu sesi.
 * `lat`/`lng` ikut supaya konfirmasi tidak perlu membaca ulang location store.
 */
export interface DraftPesananKantor {
  namaPerusahaan: string
  namaPic: string
  whatsapp: string
  alamat: string
  lat: number
  lng: number
  jenisId: string
  luasM2: number | null
  jumlahLantai: number | null
  ruangMeeting: number
  workstation: number
  frekuensiId: string
  catatan: string
  tanggal: string
  waktu: string
  foto: File[]
}

export const usePenawaranKantorStore = defineStore('penawaranKantor', () => {
  const draft = ref<DraftPenawaranKantor | null>(null)
  const pesanan = ref<DraftPesananKantor | null>(null)

  function set(d: DraftPenawaranKantor) {
    draft.value = d
  }

  function setPesanan(d: DraftPesananKantor) {
    pesanan.value = d
  }

  function hapus() {
    draft.value = null
    pesanan.value = null
  }

  return { draft, pesanan, set, setPesanan, hapus }
})
