import apiClient from './client'

/**
 * Penawaran BisaBersih Kantor.
 *
 * Yang bisa dilakukan pelanggan hanya empat: melihat, menyetujui, meminta
 * perubahan, dan mengunduh PDF. Menyusun isinya adalah pekerjaan tim sales —
 * tidak ada endpoint untuk itu di sini.
 */

export interface PaketPenawaran {
  id: number
  kode: string
  nama: string
  ringkas: string | null
  isi: string[]
  harga_per_kunjungan: number
  kunjungan_per_bulan: number
  harga_bulanan: number
  disarankan: boolean
}

export interface BarisScope {
  area: string
  pekerjaan: string
  frekuensi: string
}

export interface RevisiPenawaran {
  permintaan: string[]
  catatan: string | null
  tanggal: string
}

export type StatusPenawaran = 'ditinjau' | 'survei' | 'dikirim' | 'revisi' | 'disetujui' | 'kedaluwarsa'

export interface Penawaran {
  nomor: string
  task_id: number | null
  status: StatusPenawaran
  nama_perusahaan: string
  nama_pic: string | null
  telepon_pic: string | null
  alamat: string
  ringkasan: string
  tanggal: string | null
  berlaku_sampai: string | null
  kedaluwarsa: boolean
  paket_dipilih_id: number | null
  disetujui_pada: string | null
  scope: BarisScope[]
  biaya_tambahan: string[]
  pengecualian: string[]
  paket: PaketPenawaran[]
  revisi: RevisiPenawaran[]
  /** Pilihan perubahan yang bisa diminta, dari server. */
  pilihan_revisi: Record<string, string>
}

export async function ambilPenawaran(nomor: string): Promise<Penawaran> {
  const { data } = await apiClient.get<Penawaran>(`/penawaran/${nomor}`)
  return data
}

export async function daftarPenawaran(): Promise<Penawaran[]> {
  const { data } = await apiClient.get<{ penawaran: Penawaran[] }>('/penawaran')
  return data.penawaran ?? []
}

export async function setujuiPenawaran(nomor: string, paketId: number): Promise<Penawaran> {
  const { data } = await apiClient.post<Penawaran>(`/penawaran/${nomor}/setujui`, { paket_id: paketId })
  return data
}

export async function ajukanRevisi(
  nomor: string,
  permintaan: string[],
  catatan?: string,
): Promise<Penawaran> {
  const { data } = await apiClient.post<Penawaran>(`/penawaran/${nomor}/revisi`, {
    permintaan,
    catatan,
  })
  return data
}

/**
 * Unduh PDF penawaran.
 *
 * Diambil lewat apiClient supaya token Sanctum ikut terkirim — tautan `<a>`
 * biasa tidak membawa header Authorization, jadi servernya akan menolak.
 * Berkasnya lalu diserahkan ke browser lewat objek URL sementara.
 */
export async function unduhPdfPenawaran(nomor: string): Promise<void> {
  const res = await apiClient.get(`/penawaran/${nomor}/pdf`, { responseType: 'blob' })
  const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))

  const a = document.createElement('a')
  a.href = url
  a.download = `Penawaran-${nomor}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()

  // Dilepas setelah browser sempat memulai unduhannya.
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}
