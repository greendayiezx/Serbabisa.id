import apiClient from './client'

/**
 * Checkout Disinfektan.
 *
 * Yang dikirim hanya PILIHAN — tidak ada satu pun harga. Server menghitung
 * ulang tagihannya lewat App\Services\DisinfektanTarif, dan menolak dua hal
 * yang memang tidak boleh jadi pesanan: area dengan cairan tubuh berisiko, dan
 * luas di atas 300 m² yang harus lewat penawaran.
 */

export interface PayloadDisinfektan {
  properti: string
  luas: string
  ruangan: number
  toilet: number
  kondisi: string
  perhatian?: string[]
  catatan?: string
  /** Foto area sebagai data URL; server memeriksa angka ajaibnya, bukan labelnya. */
  foto?: { label: string; data: string }[]

  tanggal: string
  waktu: string

  nama_penerima: string
  telepon_penerima: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  metode?: string
}

export interface RincianDisinfektanServer {
  properti: string
  golongan: string
  luas: string
  ruangan: number
  toilet: number
  kondisi: string
  baris: { label: string; nilai: number }[]
  total: number
}

export interface PesananDisinfektan {
  id: number
  nomor_invoice: string | null
  judul: string
  harga: string | number
  rincian: RincianDisinfektanServer
}

export async function pesanDisinfektan(
  payload: PayloadDisinfektan,
): Promise<PesananDisinfektan> {
  const { data } = await apiClient.post<PesananDisinfektan>(
    '/bersih/disinfektan/checkout',
    payload,
  )
  return data
}

export interface PayloadPermintaanDisinfektan {
  properti: string
  luas: string
  ruangan: number
  toilet: number
  kondisi: string
  frekuensi?: string
  catatan?: string
  nama_penerima: string
  telepon_penerima: string
  lokasi_alamat: string
  lokasi_lat?: number
  lokasi_lng?: number
}

export interface PermintaanDisinfektan {
  id: number
  nomor: string
  luas: string
}

export async function ajukanPenawaranDisinfektan(
  payload: PayloadPermintaanDisinfektan,
): Promise<PermintaanDisinfektan> {
  const { data } = await apiClient.post<PermintaanDisinfektan>(
    '/bersih/disinfektan/permintaan',
    payload,
  )
  return data
}

/* ────────── Laporan pekerjaan ────────── */

export interface FotoLaporan {
  label: string
  jalur: string
  url: string
}

/**
 * Produk yang benar-benar dipakai di lokasi, beserta waktu kontak DARI LABEL
 * PRODUK ITU — bukan satu angka yang berlaku untuk semua produk.
 */
export interface ProdukLaporan {
  nama: string
  bahan_aktif: string
  konsentrasi: string
  waktu_kontak: string
  catatan?: string
  registrasi: string | null
}

export interface IsiLaporanDisinfektan {
  nomor: string
  selesai_pada: string
  petugas: string
  produk: ProdukLaporan
  metode: string
  area_dikerjakan: string[]
  ventilasi_menit: number
  aman_dimasuki_pada: string
  catatan: string | null
  sebelum: FotoLaporan[]
  sesudah: FotoLaporan[]
}

export interface LaporanDisinfektan {
  id: number
  nomor: string
  status: string | null
  dijadwalkan_pada: string | null
  alamat: string | null
  properti: string | null
  luas: string | null
  ruangan: number | null
  toilet: number | null
  area: string[]
  foto_pesanan: FotoLaporan[]
  /** Null selama petugas belum menutup pekerjaannya. */
  laporan: IsiLaporanDisinfektan | null
}

export async function ambilLaporanDisinfektan(nomor: string): Promise<LaporanDisinfektan> {
  const { data } = await apiClient.get<LaporanDisinfektan>(
    `/bersih/disinfektan/laporan/${encodeURIComponent(nomor)}`,
  )
  return data
}
