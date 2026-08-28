import apiClient from './client'

/**
 * Pesan langsung BisaBersih Kantor.
 *
 * Yang dikirim hanya PILIHAN — tidak ada satu pun harga. Server menghitung
 * ulang tagihannya lewat App\Services\KantorTarif, jadi total di layar bisa
 * saja berbeda dengan yang ditagih; yang benar adalah server.
 *
 * Kantor besar ditolak server (422) karena luasnya tidak berbatas dan harus
 * lewat jalur penawaran setelah survei.
 */
export interface PayloadKantor {
  jenis_kantor: string
  paket: string
  frekuensi: string
  workstation: number
  ruang_meeting: number
  toilet: number
  pantry: number
  add_on: string[]
  lainnya?: string
  catatan?: string

  tanggal: string
  waktu: string

  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  metode?: string
  /** Server menghitung ulang potongannya; yang dikirim hanya kodenya. */
  promo_kode?: string
}

export interface RincianKantorServer {
  nama_jenis: string
  nama_paket: string
  label_frekuensi: string
  layanan: number
  penyesuaian_minimum: number
  add_on: number
  subtotal: number
  diskon_frekuensi: number
  total_per_kunjungan: number
  total_per_bulan: number
}

export interface PesananKantor {
  id: number
  nomor_invoice: string | null
  judul: string
  harga: string | number
  rincian: RincianKantorServer
}

export async function pesanKantorLangsung(payload: PayloadKantor): Promise<PesananKantor> {
  const { data } = await apiClient.post<PesananKantor>('/bersih/kantor/checkout', payload)
  return data
}

/* ---------------- Permintaan penawaran ---------------- */

/**
 * Permintaan penawaran mendapat nomornya sendiri (REQ-000001) supaya pelanggan
 * punya satu rujukan saat menanyakan status.
 */
export interface PayloadPermintaanKantor {
  nama_perusahaan: string
  nama_pic: string
  telepon_pic: string
  jenis_kantor: string
  paket?: string
  frekuensi: string
  luas_m2?: number | null
  jumlah_lantai?: number | null
  workstation?: number
  ruang_meeting?: number
  toilet?: number
  pantry?: number
  lainnya?: string
  add_on?: string[]
  catatan?: string
  estimasi?: number | null
  promo_kode?: string | null
  lokasi_alamat: string
  lokasi_lat?: number
  lokasi_lng?: number
  /** PNG data URL dari kanvas tanda tangan. */
  tanda_tangan?: string
}

export interface PermintaanKantor {
  id: number
  nomor: string
  dibuat_pada?: string
  nama_perusahaan: string | null
  nama_pic?: string | null
  telepon_pic?: string | null
  alamat: string
  jenis_layanan: string
  frekuensi: string | null
  estimasi: number | null
  /** null selama tim belum menyusun penawarannya. */
  nomor_penawaran?: string | null
  /** Tahap yang benar-benar sudah dikerjakan tim. */
  tahap?: 'ditinjau' | 'dihubungi' | 'survei'
  bertanda_tangan?: boolean
}

export async function kirimPermintaanKantor(
  payload: PayloadPermintaanKantor,
): Promise<PermintaanKantor> {
  const { data } = await apiClient.post<PermintaanKantor>('/bersih/kantor/permintaan', payload)
  return data
}

export async function ambilPermintaanKantor(nomor: string): Promise<PermintaanKantor> {
  const { data } = await apiClient.get<PermintaanKantor>(`/bersih/kantor/permintaan/${nomor}`)
  return data
}

/**
 * Unduh bukti permintaan sebagai PDF.
 *
 * Lewat apiClient supaya token Sanctum ikut terkirim — tautan `<a>` biasa tidak
 * membawa header Authorization dan akan ditolak server.
 */
export async function unduhPdfPermintaan(nomor: string): Promise<void> {
  const res = await apiClient.get(`/bersih/kantor/permintaan/${nomor}/pdf`, {
    responseType: 'blob',
  })
  const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))

  const a = document.createElement('a')
  a.href = url
  a.download = `Permintaan-${nomor}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()

  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

/**
 * Ambil PDF bukti permintaan sebagai blob, tanpa menyimpannya ke perangkat.
 *
 * Dipakai penampil di dalam aplikasi; `unduhPdfPermintaan` di atas tetap ada
 * untuk saat pengguna memang ingin berkasnya tersimpan.
 */
/**
 * Tautan sementara untuk MEMBUKA PDF di penampil peramban.
 *
 * Berbeda dari mengambil blob: peramban memuat berkasnya sendiri lewat URL http
 * biasa, sehingga penampil PDF bawaannya bekerja. Blob URL ditolak Chrome di
 * sebagian lingkungan (emulasi ponsel di antaranya).
 *
 * Server mengembalikan jalur RELATIF; asalnya diambil dari alamat API.
 */
export async function tautanPdfPermintaan(nomor: string): Promise<string> {
  const { data } = await apiClient.get<{ url: string }>(
    `/bersih/kantor/permintaan/${nomor}/tautan-pdf`,
  )

  return new URL(data.url, import.meta.env.VITE_API_BASE_URL).href
}

export async function ambilPdfPermintaan(nomor: string): Promise<Blob> {
  const res = await apiClient.get(`/bersih/kantor/permintaan/${nomor}/pdf`, {
    responseType: 'blob',
  })
  return new Blob([res.data], { type: 'application/pdf' })
}
