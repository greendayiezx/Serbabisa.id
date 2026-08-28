import apiClient from './client'

/**
 * Daftar cleaner BisaBersih.
 *
 * Semuanya data nyata dari server: nama dari akun mitra, level DIHITUNG dari
 * ulasan yang benar-benar diterima, jumlah order dari tugas yang sudah selesai.
 * Tidak ada nilai contoh — kalau belum ada mitra terdaftar, daftarnya memang
 * kosong, dan halaman menyatakannya apa adanya alih-alih menampilkan orang yang
 * tidak ada.
 */

export interface CleanerServer {
  id: string
  nama: string
  /** Menentukan ilustrasi avatar. null = avatar netral berisi inisial. */
  gender: 'pria' | 'wanita' | null
  level: number
  nama_level: string
  /** Sudah termasuk markup platform. */
  harga_per_jam: number
  /** 0 berarti belum ada ulasan sama sekali, bukan nilai sementara. */
  rating: number
  jumlah_ulasan: number
  order_selesai: number
}

export interface Jenjang {
  level: number
  nama: string
  /** Bagian cleaner. */
  tarif: number
  /** Yang dibayar customer. */
  harga: number
  min_ulasan: number
  min_rating: number
}

export interface DaftarCleaner {
  cleaner: CleanerServer[]
  jenjang: Jenjang[]
  markupPerJam: number
  hargaTerendahPerJam: number
}

export async function ambilCleaner(): Promise<DaftarCleaner> {
  const { data } = await apiClient.get('/bersih/cleaner')
  return {
    cleaner: data.cleaner ?? [],
    jenjang: data.jenjang ?? [],
    markupPerJam: data.markup_per_jam ?? 0,
    hargaTerendahPerJam: data.harga_terendah_per_jam ?? 0,
  }
}
