import apiClient from './client'

/**
 * Jembatan ke endpoint BisaBersih di Laravel.
 *
 * Yang dikirim hanya PILIHAN pengguna — tidak ada satu pun harga. Server
 * menghitung ulang tagihan lewat App\Services\BersihTarif, termasuk menentukan
 * promo pengguna baru dari riwayat pesanan, bukan dari klaim browser. Angka yang
 * dipakai halaman status adalah yang dikembalikan server, bukan yang tadi
 * ditampilkan di layar pemesanan.
 */

export interface PayloadBersih {
  /** Tidak lagi dipilih di halaman pemesanan; server memakai tarif per jam. */
  layanan?: string
  durasi_jam: number
  jumlah_cleaner: number
  add_on: string[]
  frekuensi: string

  /** Kondisi ruangan tidak lagi ditanyakan halaman; server memakai 'normal'. */
  kondisi?: string
  /**
   * Detail properti & akses masuk sudah dihapus dari halaman pemesanan, tapi
   * tetap ada di tipe ini: endpoint masih menerimanya dan data lama memilikinya.
   */
  tipe_properti?: string
  kamar_tidur?: number
  kamar_mandi?: number
  luas_m2?: number
  akses_masuk?: string
  ada_hewan: boolean
  area: string[]
  /** null / kosong = biarkan sistem memilih cleaner tercepat. */
  cleaner_id?: string
  /** Kode promo yang dipilih pengguna. Server memvalidasinya ulang. */
  promo_kode?: string

  tanggal: string
  waktu: string
  catatan?: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  nama_penerima?: string
  telepon_penerima?: string
  metode?: string
}

export interface RincianServer {
  layanan: number
  add_on: number
  perjalanan: number
  subtotal: number
  diskon_frekuensi: number
  nilai_transaksi: number
  promo_kode: string | null
  potongan_promo: number
  total: number
}

export interface PesananBersih {
  id: number
  nomor_invoice: string | null
  /** Bagian kode di URL: SBB-260819-5D9C5H0 → 5D9C5H0 */
  nomor: string
  judul: string
  harga: string | number
  rincian: RincianServer
}

export async function kirimPesananBersih(payload: PayloadBersih): Promise<PesananBersih> {
  const { data } = await apiClient.post<Omit<PesananBersih, 'nomor'>>('/bersih/checkout', payload)
  const invoice = data.nomor_invoice ?? ''
  return { ...data, nomor: invoice.split('-')[2] ?? invoice }
}

/* ---------------- Status pesanan ---------------- */

/**
 * Status pesanan yang sedang berjalan.
 *
 * `diterima` datang dari status DI DATABASE, bukan dari timer di browser:
 * selama belum ada cleaner yang menekan terima, layar tunggu harus tetap
 * layar tunggu.
 */
export interface StatusPesananBersih {
  nomor: string
  task_id: number
  status: 'pending' | 'accepted' | 'in_progress' | 'completed' | 'cancelled'
  diterima: boolean
  judul: string
  deskripsi: string | null
  dijadwalkan_pada: string | null
  durasi_jam: number
  jumlah_cleaner: number
  nama_level: string | null
  area: string[]
  catatan: string | null
  lokasi: {
    alamat: string | null
    lat: number | null
    lng: number | null
    nama_penerima: string | null
    telepon_penerima: string | null
  }
  total: number
  metode: string | null
  cleaner: CleanerPesanan | null
}

export interface CleanerPesanan {
  id: string
  nama: string
  gender: 'pria' | 'wanita' | null
  level: number
  nama_level: string
  harga_per_jam: number
  rating: number
  jumlah_ulasan: number
  order_selesai: number
  /** Hanya terisi setelah pesanan diterima. */
  telepon?: string | null
}

export async function ambilStatusPesananBersih(nomor: string): Promise<StatusPesananBersih> {
  const { data } = await apiClient.get<StatusPesananBersih>(`/bersih/pesanan/${nomor}`)
  return data
}
