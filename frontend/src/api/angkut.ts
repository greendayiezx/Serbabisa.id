import apiClient from './client'
export { pesanError } from './belanja'

/**
 * Jembatan ke endpoint BisaAngkut. Yang dikirim hanya PILIHAN (id kendaraan/
 * layanan/proteksi + jumlah helper); total dihitung ulang server dari AngkutTarif,
 * jadi harga di layar tidak perlu — dan tidak boleh — dipercaya.
 */
export interface PayloadAngkut {
  vehicle_id: string
  delivery_id: string
  protection_id: string
  helper_count: number
  berat_total: number
  tanggal: string
  waktu: string
  catatan: string
  nama_penerima: string
  telepon_penerima: string
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  patokan?: string
  metode?: string
}

export interface HasilAngkut {
  id: number
  nomor_invoice: string | null
  harga: string | number
}

export async function kirimAngkut(payload: PayloadAngkut): Promise<HasilAngkut> {
  const { data } = await apiClient.post<HasilAngkut>('/angkut/checkout', payload)
  return data
}
