import apiClient from './client'

/**
 * Permintaan penawaran BisaBersih Kantor — sisi tim.
 *
 * Tahap digerakkan tim lewat endpoint ini, bukan berjalan sendiri karena waktu
 * berlalu. Halaman pelanggan menampilkan apa yang benar-benar sudah dikerjakan.
 */
export type TahapPermintaan = 'ditinjau' | 'dihubungi' | 'survei'

export interface PermintaanAdmin {
  nomor: string
  task_id: number
  tahap: TahapPermintaan
  label_tahap: string
  nama_perusahaan: string
  nama_pic: string | null
  telepon_pic: string | null
  alamat: string
  jenis_kantor: string | null
  frekuensi: string | null
  estimasi: number | null
  bertanda_tangan: boolean
  dibuat_pada: string | null
  nomor_penawaran: string | null
  pelanggan: { id: number; name: string; email: string } | null
}

export interface DaftarPermintaanAdmin {
  permintaan: PermintaanAdmin[]
  tahap: Record<TahapPermintaan, string>
  jumlah: Record<TahapPermintaan, number>
}

export async function daftarPermintaanAdmin(
  tahap?: TahapPermintaan,
): Promise<DaftarPermintaanAdmin> {
  const { data } = await apiClient.get<DaftarPermintaanAdmin>('/admin/permintaan', {
    params: tahap ? { tahap } : undefined,
  })
  return data
}

export async function majukanTahap(
  nomor: string,
  tahap: TahapPermintaan,
  catatan?: string,
): Promise<PermintaanAdmin> {
  const { data } = await apiClient.patch<PermintaanAdmin>(`/admin/permintaan/${nomor}/tahap`, {
    tahap,
    catatan,
  })
  return data
}
