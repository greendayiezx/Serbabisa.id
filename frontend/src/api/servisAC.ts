import apiClient from './client'

/**
 * Checkout Servis AC.
 *
 * Yang dikirim hanya PILIHAN — tidak ada satu pun harga. Server menghitung
 * ulang tagihannya lewat App\Services\ACTarif.
 */
export interface PayloadAC {
  paket: string
  unit: number
  tipe: string
  kapasitas: string
  terakhir_cuci?: string
  kondisi?: string[]
  rutin?: string | null
  catatan?: string

  tanggal: string
  waktu: string

  /** Kontak yang ditemui teknisi di lokasi; belum tentu pemilik akun. */
  nama_penerima: string
  telepon_penerima: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  metode?: string
  /** Server menghitung ulang potongannya; yang dikirim hanya kodenya. */
  promo_kode?: string
}

export interface RincianACServer {
  paket: string
  nama_paket: string
  harga_per_unit: number
  unit: number
  layanan: number
  biaya_kunjungan: number
  gratis_kunjungan: boolean
  diskon_bundling: number
  total: number
  promo_kode: string | null
  potongan_promo: number
  /** Terisi kalau kode dikirim tapi ditolak server. */
  promo_ditolak: string | null
  total_ditagih: number
}

export interface PesananAC {
  id: number
  nomor_invoice: string | null
  judul: string
  harga: string | number
  rincian: RincianACServer
}

export async function pesanServisAC(payload: PayloadAC): Promise<PesananAC> {
  const { data } = await apiClient.post<PesananAC>('/servis-ac/checkout', payload)
  return data
}
