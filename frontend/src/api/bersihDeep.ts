import apiClient from './client'

/**
 * Checkout BisaBersih Deep Cleaning.
 *
 * Yang dikirim hanya PILIHAN — tidak ada satu pun harga. Server menghitung
 * ulang tagihannya lewat App\Services\DeepTarif, jadi angka di layar hanyalah
 * estimasi; yang benar adalah yang dikembalikan server.
 */
export interface PayloadDeep {
  paket: string
  luas_m2: number
  jumlah_ruangan: number
  add_on: string[]
  catatan?: string

  tanggal: string
  waktu: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  nama_penerima?: string
  telepon_penerima?: string
  metode?: string
  /** Server menghitung ulang potongannya; yang dikirim hanya kodenya. */
  promo_kode?: string
}

export interface BarisAddOnServer {
  id: string
  nama: string
  harga_satuan: number
  qty: number
  satuan: string
  subtotal: number
}

export interface RincianDeepServer {
  paket: string
  nama_paket: string
  luas_m2: number
  jumlah_ruangan: number
  harga_paket: number
  kelebihan_luas: number
  biaya_luas: number
  kelebihan_ruangan: number
  biaya_ruangan: number
  add_on: number
  baris_add_on: BarisAddOnServer[]
  total: number
  jumlah_kru: number
  durasi_jam: number
  promo_kode: string | null
  potongan_promo: number
  /** Terisi kalau kode dikirim tapi ditolak server. */
  promo_ditolak: string | null
  total_ditagih: number
}

export interface PesananDeep {
  id: number
  nomor_invoice: string | null
  judul: string
  harga: string | number
  rincian: RincianDeepServer
}

export async function pesanDeep(payload: PayloadDeep): Promise<PesananDeep> {
  const { data } = await apiClient.post<PesananDeep>('/bersih/deep/checkout', payload)
  return data
}
