import apiClient from './client'

/**
 * Cek & Tambah Freon.
 *
 * Checkout hanya memesan PEMERIKSAAN — tidak ada harga yang dikirim. Pekerjaan
 * lanjutan baru masuk tagihan lewat `setujuiDiagnosa`, dan nominalnya dihitung
 * ulang server dari katalog pekerjaan.
 */
export interface PayloadFreon {
  unit: number
  keluhan: string[]
  menyala: boolean
  tipe: string
  kapasitas: string
  merek: string
  jenis_freon: string
  catatan?: string

  tanggal: string
  slot: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  metode?: string
  promo_kode?: string
}

export interface RincianFreonServer {
  unit: number
  biaya_pemeriksaan: number
  biaya_unit_tambahan: number
  total: number
  promo_kode: string | null
  potongan_promo: number
  promo_ditolak: string | null
  total_ditagih: number
}

export interface PesananFreon {
  id: number
  nomor_invoice: string | null
  judul: string
  rincian: RincianFreonServer
}

export interface HasilKeputusan {
  nomor: string
  keputusan: 'disetujui' | 'ditolak'
}

export async function pesanPemeriksaanFreon(payload: PayloadFreon): Promise<PesananFreon> {
  const { data } = await apiClient.post<PesananFreon>('/servis-ac/freon/checkout', payload)
  return data
}

export async function setujuiDiagnosa(nomor: string): Promise<HasilKeputusan> {
  const { data } = await apiClient.post<HasilKeputusan>(
    `/servis-ac/freon/${encodeURIComponent(nomor)}/setujui`,
  )
  return data
}

export async function tolakDiagnosa(nomor: string): Promise<HasilKeputusan> {
  const { data } = await apiClient.post<HasilKeputusan>(
    `/servis-ac/freon/${encodeURIComponent(nomor)}/tolak`,
  )
  return data
}
