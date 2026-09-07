import apiClient from './client'
import type { PilihanJemput } from '@/lib/jemput'

/**
 * BisaJemput.
 *
 * Yang dikirim hanya koordinat dan pilihan — tidak ada jarak, tidak ada tarif.
 * Server menghitung ulang jaraknya dari titik jemput ke tujuan, dan menagih
 * dari hitungannya sendiri.
 */

export interface HasilEstimasi {
  km: number
  /**
   * Titik-titik rute jalan sebagai [lat, lng] — dari perhitungan yang SAMA
   * dengan yang dipakai menagih. Null kalau layanan rute tidak terjangkau;
   * saat itu km-nya perkiraan garis lurus dan layar mengatakannya.
   */
  geometri: [number, number][] | null
  lewat_jalan: boolean
  sibuk: string | null
  sibuk_alasan: string | null
  sibuk_pengali: number
  perjalanan_pertama: boolean
  pilihan: PilihanJemput[]
}

export async function estimasiJemput(payload: {
  jemput_lat: number
  jemput_lng: number
  tujuan_lat: number
  tujuan_lng: number
}): Promise<HasilEstimasi> {
  const { data } = await apiClient.post<HasilEstimasi>('/jemput/estimasi', payload)
  return data
}

export interface PayloadJemput {
  tipe: string
  varian: string
  /** Wajib true. Server menolak pesanan tanpa ini — bukan sekadar aturan layar. */
  titik_jemput_dikonfirmasi: true
  jemput_alamat: string
  jemput_lat: number
  jemput_lng: number
  jemput_catatan?: string
  tujuan_alamat: string
  tujuan_lat: number
  tujuan_lng: number
  penumpang: number
  metode: string
  kode_promo?: string
  untuk_orang_lain?: boolean
  nama_penumpang?: string
  telepon_penumpang?: string
  dijadwalkan?: boolean
  jadwal_pada?: string
  catatan?: string
}

export interface PesananJemput {
  id: number
  nomor_invoice: string
  harga: string | number
  rincian: { tarif: number; potongan: number; total: number }
}

export async function pesanJemput(payload: PayloadJemput): Promise<PesananJemput> {
  const { data } = await apiClient.post<PesananJemput>('/jemput/checkout', payload)
  return data
}

export interface Pengemudi {
  nama: string
  kendaraan: string
  plat: string
  warna: string
  bintang: number
  perjalanan: number
  telepon_tersamar: boolean
  tiba_menit: number

  /*
   * Posisi pengemudi, DARI SERVER dan boleh tidak ada.
   *
   * Layar penantian menggambar kendaraannya di peta dan menyebut sisa
   * jaraknya. Angka itu tidak boleh dikarang di sini: orang menakar kapan
   * harus turun ke lobi berdasarkan angka tersebut. Kalau server belum tahu
   * posisinya, layar tidak menyebut jarak sama sekali.
   */
  lat?: number
  lng?: number
  /**
   * Rute pengemudi menuju titik jemput, sudah [lat,lng] dan lewat jalan.
   *
   * Hanya ada selama menjemput. Kosong berarti layanan rute tidak menjawab,
   * dan layar menarik garis lurus putus-putus — bentuk yang berbeda supaya
   * tidak terbaca sebagai jalan yang sungguh dilewati.
   */
  rute?: [number, number][] | null
  /** Sedang menuju titik jemput atau tujuan. */
  menuju?: 'jemput' | 'tujuan'
  jarak_km?: number
}

export interface Perjalanan {
  id: number
  nomor: string
  tahap: string
  label: string | null
  /** Teks lencana varian dari server: 'CEPAT', 'HEMAT', 'COMFORT', … */
  label_varian: string | null
  kelas: string | null
  km: number | null
  menit: number | null
  geometri: [number, number][] | null
  lewat_jalan: boolean
  penumpang: number
  untuk_orang_lain: boolean
  nama_penumpang: string | null
  telepon_penumpang: string | null
  jemput: { alamat: string; lat: number; lng: number; catatan: string | null } | null
  tujuan: { alamat: string; lat: number; lng: number } | null
  baris: { label: string; nilai: number }[]
  tarif: number
  potongan: number
  total: number
  /** Tip yang sudah diberikan; menambah tagihan, bukan tarif. */
  tip: number
  promo: { kode: string; nama: string; potongan: number } | null
  metode: string | null
  sibuk: string | null
  sibuk_alasan: string | null
  dijadwalkan_pada: string | null
  pengemudi: Pengemudi | null
  penilaian: {
    bintang: number
    tag: string[]
    ulasan: string | null
    tip: number
    dinilai_pada: string
  } | null
}

export async function ambilPerjalanan(nomor: string): Promise<Perjalanan> {
  const { data } = await apiClient.get<Perjalanan>(`/jemput/${encodeURIComponent(nomor)}`)
  return data
}

export async function batalkanPerjalanan(
  nomor: string,
): Promise<{ tahap: string; pengemudi_sudah_jalan: boolean }> {
  const { data } = await apiClient.post(`/jemput/${encodeURIComponent(nomor)}/batal`)
  return data
}

export async function nilaiPerjalanan(
  nomor: string,
  payload: { bintang: number; tag?: string[]; ulasan?: string; tip?: number },
): Promise<unknown> {
  const { data } = await apiClient.post(`/jemput/${encodeURIComponent(nomor)}/nilai`, payload)
  return data
}

export interface VoucherJemput {
  kode: string
  nama: string
  deskripsi: string
  jenis: 'akuisisi' | 'berulang'
  minimum: number
  terpakai: boolean
}

/**
 * Katalog promo tanpa angka potongan.
 *
 * Dipakai halaman promo yang bisa dibuka dari mana saja — termasuk dari
 * perjalanan yang sedang berlangsung, tempat tidak ada tarif yang bisa
 * dijadikan dasar hitungan. Angka rupiahnya muncul di layar pemesanan, tempat
 * tarifnya sudah diketahui.
 */
export async function voucherJemput(): Promise<{
  perjalanan_pertama: boolean
  jumlah: number
  voucher: VoucherJemput[]
}> {
  const { data } = await apiClient.get('/jemput/voucher')
  return data
}

/** Tip untuk pengemudi selama perjalanan; seluruhnya milik pengemudi. */
export async function tipPengemudi(nomor: string, tip: number): Promise<{ tip: number }> {
  const { data } = await apiClient.post(`/jemput/${encodeURIComponent(nomor)}/tip`, { tip })
  return data
}
