import apiClient from './client'
import type { PilihanKirim } from '@/lib/kirim'

/**
 * BisaKirim.
 *
 * Yang dikirim hanya koordinat dan pilihan. Server menghitung ulang jaraknya,
 * menolak paket yang tidak muat kendaraannya, dan menolak isi kiriman yang
 * memang tidak bisa diantar — tiga hal yang tidak boleh cuma hidup di layar.
 */

export interface HasilEstimasiKirim {
  km: number
  geometri: [number, number][] | null
  lewat_jalan: boolean
  kiriman_pertama: boolean
  proteksi_plafon: number
  pilihan: PilihanKirim[]
}

export async function estimasiKirim(payload: {
  ambil_lat: number
  ambil_lng: number
  antar_lat: number
  antar_lng: number
  ukuran: string
  nilai_barang?: number
}): Promise<HasilEstimasiKirim> {
  const { data } = await apiClient.post<HasilEstimasiKirim>('/kirim/estimasi', payload)
  return data
}

export interface PayloadKirim {
  kendaraan: string
  ukuran: string
  isi: string
  /** Pernyataan isi terlarang. Satu pun yang terisi membuat server menolak. */
  dilarang?: string[]
  nilai_barang?: number
  pakai_kode_terima?: boolean

  ambil_alamat: string
  ambil_lat: number
  ambil_lng: number
  ambil_nama: string
  ambil_telepon: string
  ambil_catatan?: string

  antar_alamat: string
  antar_lat: number
  antar_lng: number
  antar_nama: string
  antar_telepon: string
  antar_catatan?: string

  metode: string
  kode_promo?: string
}

export interface PesananKirim {
  id: number
  nomor_invoice: string
  harga: string | number
  kode_terima: string | null
  rincian: { ongkir: number; premi: number; potongan: number; total: number }
}

export async function pesanKirim(payload: PayloadKirim): Promise<PesananKirim> {
  const { data } = await apiClient.post<PesananKirim>('/kirim/checkout', payload)
  return data
}

export interface Kiriman {
  id: number
  nomor: string
  tahap: string
  label: string | null
  ukuran: string | null
  isi: string | null
  km: number | null
  geometri: [number, number][] | null
  ambil: { alamat: string; lat: number; lng: number; nama: string; telepon: string; catatan: string | null } | null
  antar: { alamat: string; lat: number; lng: number; nama: string; telepon: string; catatan: string | null } | null
  baris: { label: string; nilai: number }[]
  ongkir: number
  premi_proteksi: number
  proteksi_plafon: number
  potongan: number
  total: number
  promo: { kode: string; nama: string; potongan: number } | null
  metode: string | null
  kode_terima: string | null
  kurir: unknown | null
}

export async function ambilKiriman(nomor: string): Promise<Kiriman> {
  const { data } = await apiClient.get<Kiriman>(`/kirim/${encodeURIComponent(nomor)}`)
  return data
}
