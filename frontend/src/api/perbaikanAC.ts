import apiClient from './client'

/**
 * Perbaikan & Pasang AC.
 *
 * Dua jalur dengan sifat uang berbeda, dan tipenya sengaja dipisah supaya
 * perbedaan itu terlihat dari kodenya:
 *
 * - Perbaikan MENAGIH kunjungan diagnosisnya, jadi ia mengembalikan pesanan
 *   dengan nomor invoice dan rincian biaya.
 * - Pasang/pindah TIDAK menagih apa pun; ia mengembalikan nomor permintaan
 *   penawaran (REQ-) dan rentang estimasi, bukan harga.
 */

export interface FotoLampiran {
  label: string
  /** data:image/png;base64,… atau image/jpeg. Server memeriksa isinya. */
  data: string
}

export interface PayloadPerbaikan {
  unit: number
  keluhan: string[]
  menyala: boolean
  mulai_terjadi: string

  merek: string
  tipe: string
  kapasitas: string
  kode_error?: string
  catatan?: string

  tanggal: string
  slot: string

  nama_penerima: string
  telepon_penerima: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  metode?: string

  foto?: FotoLampiran[]
}

export interface RincianPemeriksaan {
  unit: number
  biaya_pemeriksaan: number
  biaya_unit_tambahan: number
  total: number
}

export interface PesananPerbaikan {
  id: number
  nomor_invoice: string | null
  judul: string
  harga: string | number
  rincian: RincianPemeriksaan
}

export async function pesanPerbaikanAC(payload: PayloadPerbaikan): Promise<PesananPerbaikan> {
  const { data } = await apiClient.post<PesananPerbaikan>('/servis-ac/perbaikan/checkout', payload)
  return data
}

export interface PayloadPasang {
  jenis_pekerjaan: string
  unit: number
  ketersediaan_unit: string
  kebutuhan: string

  merek?: string
  kapasitas: string

  lokasi_indoor: string
  lokasi_outdoor: string
  material?: string[]

  cara_penawaran: string
  catatan?: string

  nama_penerima: string
  telepon_penerima: string

  lokasi_alamat: string
  lokasi_lat?: number
  lokasi_lng?: number

  foto?: FotoLampiran[]
}

export interface PermintaanPasang {
  id: number
  nomor: string
  cara_penawaran: string
  /** Server menaikkan estimasi foto jadi survei untuk pekerjaan tertentu. */
  survei_diwajibkan: boolean
  estimasi_mulai: number
  estimasi_sampai: number
}

export async function ajukanPasangAC(payload: PayloadPasang): Promise<PermintaanPasang> {
  const { data } = await apiClient.post<PermintaanPasang>('/servis-ac/pasang/permintaan', payload)
  return data
}
