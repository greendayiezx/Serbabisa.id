import apiClient from './client'

/**
 * BisaTukang.
 *
 * Yang dikirim layar hanya PILIHAN — kategori, jenis masalah, tipe properti,
 * jadwal. Biaya kunjungannya dihitung server dari katalognya sendiri, dan harga
 * perbaikan tidak pernah dikirim dari sini: angka itu baru ada di penawaran
 * setelah tukang memeriksa, dan mengikat hanya setelah disetujui.
 */

export interface KategoriTukang {
  id: string
  nama: string
  deskripsi: string
  sub: string[]
  kunjungan: number
  perbaikan_mulai: number
  perbaikan_sampai: number
}

export interface KatalogTukang {
  kategori: KategoriTukang[]
  tipe_properti: string[]
  penyediaan_material: string[]
  lokasi_masalah: string[]
  borongan_mulai: number
  borongan_sampai: number
  garansi_hari: number
}

export interface TukangMitra {
  nama: string
  keahlian: string
  tahun: number
  bintang: number
  pekerjaan: number
  telepon_tersamar: boolean
  tiba_menit: number
}

export interface BarisPenawaran {
  nama: string
  kategori: string
  satuan: string
  nilai: number
}

export interface PenawaranTukang {
  baris: BarisPenawaran[]
  subtotal: number
  potongan: number
  total: number
  material_ditanggung: 'tukang' | 'pelanggan'
  garansi_hari: number
  diterbitkan_pada: string
  berlaku_sampai: string | null
  /** Dihitung server: jam perangkat yang salah membuat penawaran hidup terbaca mati. */
  kedaluwarsa: boolean
  keputusan: 'disetujui' | 'revisi' | null
  nama_penyetuju?: string | null
  disetujui_pada?: string | null
  revisi?: { diajukan_pada: string; alasan: string | null; catatan: string }[]
}

/** Tahap pekerjaan; seluruh isi layar status bergantung pada ini. */
export type TahapTukang =
  | 'mencari'
  | 'menuju'
  | 'tiba'
  | 'penawaran'
  | 'dikerjakan'
  | 'selesai'
  | 'batal'

export interface Pekerjaan {
  id: number
  nomor: string
  model_kerja: 'harian' | 'borongan'
  tahap: TahapTukang
  kategori: string | null
  nama_kategori: string | null
  sub_kategori: string | null
  tipe_properti: string | null
  penyediaan_material: string | null
  lokasi_masalah: string | null
  deskripsi_masalah: string | null
  foto: { label: string; jalur: string }[]
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  dijadwalkan_pada: string | null
  jadwal_tipe: string | null
  jadwal_jam: string | null
  biaya_kunjungan: number
  estimasi_mulai: number | null
  estimasi_sampai: number | null
  tukang: TukangMitra | null
  penawaran: PenawaranTukang | null
  garansi_hari: number
  total: number
  tip: number
  metode: string | null
  penilaian: { bintang: number; tag: string[]; ulasan: string | null; dinilai_pada: string } | null
  dibatalkan: boolean
}

/** Isian yang sama untuk kedua model kerja. */
export interface PesananTukang {
  kategori: string
  sub_kategori: string
  tipe_properti: string
  penyediaan_material: string
  lokasi_masalah: string
  deskripsi_masalah: string
  nama_penerima: string
  telepon_penerima: string
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  jadwal_tipe?: string
  jadwal_tanggal?: string | null
  jadwal_jam?: string | null
  metode?: string | null
  foto?: { label: string; data: string }[]
}

export async function katalogTukang(): Promise<KatalogTukang> {
  const { data } = await apiClient.get<KatalogTukang>('/tukang/katalog')
  return data
}

/** Model harian: menagih kunjungannya, dan hanya itu. */
export async function checkoutTukang(payload: PesananTukang): Promise<{ nomor: string }> {
  const { data } = await apiClient.post('/tukang/checkout', payload)
  return data
}

/** Model borongan: tidak menagih apa pun, nomornya REQ-. */
export async function permintaanTukang(
  payload: PesananTukang,
): Promise<{ nomor: string; estimasi_mulai: number; estimasi_sampai: number }> {
  const { data } = await apiClient.post('/tukang/permintaan', payload)
  return data
}

export async function ambilPekerjaan(nomor: string): Promise<Pekerjaan> {
  const { data } = await apiClient.get<Pekerjaan>(`/tukang/${encodeURIComponent(nomor)}`)
  return data
}

/**
 * Setujui penawaran.
 *
 * Totalnya sengaja TIDAK ikut dikirim — server memakai angka yang tersimpan.
 * Kalau layar boleh menyebut totalnya sendiri, harga yang mengikat jadi harga
 * yang kebetulan ada di memori browser.
 */
export async function setujuiPenawaran(
  nomor: string,
  namaPenyetuju: string,
): Promise<{ tahap: string; total: number; garansi_hari: number }> {
  const { data } = await apiClient.post(`/tukang/${encodeURIComponent(nomor)}/penawaran/setujui`, {
    setuju: true,
    nama_penyetuju: namaPenyetuju,
  })
  return data
}

export async function revisiPenawaran(
  nomor: string,
  catatan: string,
  alasan?: string,
): Promise<{ keputusan: string; jumlah_revisi: number }> {
  const { data } = await apiClient.post(`/tukang/${encodeURIComponent(nomor)}/penawaran/revisi`, {
    catatan,
    alasan,
  })
  return data
}

export async function batalTukang(
  nomor: string,
): Promise<{ dibatalkan: boolean; tukang_sudah_jalan: boolean }> {
  const { data } = await apiClient.post(`/tukang/${encodeURIComponent(nomor)}/batal`)
  return data
}

/** Tip untuk tukang; seluruhnya miliknya, tanpa potongan komisi. */
export async function tipTukang(nomor: string, tip: number): Promise<{ tip: number }> {
  const { data } = await apiClient.post(`/tukang/${encodeURIComponent(nomor)}/tip`, { tip })
  return data
}

export async function nilaiTukang(
  nomor: string,
  payload: { bintang: number; tag?: string[]; ulasan?: string },
): Promise<{ penilaian: Pekerjaan['penilaian'] }> {
  const { data } = await apiClient.post(`/tukang/${encodeURIComponent(nomor)}/nilai`, payload)
  return data
}
