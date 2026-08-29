import apiClient from './client'

/**
 * Penawaran pemasangan AC.
 *
 * Yang dikirim saat menyetujui hanya PERNYATAAN setuju beserta nama
 * penyetujunya — tidak ada satu pun angka. Totalnya diambil server dari
 * penawaran yang tersimpan; kalau harga ikut dikirim dari sini, siapa pun yang
 * bisa memanggil API ini bisa menyetujui pekerjaan dengan harganya sendiri.
 */

export interface BarisPenawaran {
  nama: string
  kategori?: string
  satuan?: string
  nilai: number
}

export interface SlotJadwal {
  id: string
  tanggal: string
  label: string
  jam: string
}

export interface Penawaran {
  nomor: string
  terbit_pada?: string
  berlaku_sampai?: string
  layanan: string
  durasi?: string
  termasuk: string[]
  tidak_termasuk: string[]
  baris: BarisPenawaran[]
  subtotal: number
  potongan?: number
  nama_potongan?: string
  total: number
  deposit?: number
  jadwal?: SlotJadwal[]
  catatan?: string
  keputusan: 'disetujui' | 'revisi' | null
  /** Dihitung server dari tanggalnya, bukan dipercayakan pada jam perangkat. */
  kedaluwarsa: boolean
}

export interface PenawaranLengkap {
  nomor_permintaan: string
  nomor_pekerjaan: string | null
  lokasi_alamat: string
  penawaran: Penawaran
}

export async function ambilPenawaran(nomor: string): Promise<PenawaranLengkap> {
  const { data } = await apiClient.get<PenawaranLengkap>(`/servis-ac/penawaran/${nomor}`)
  return data
}

export interface IsiSetuju {
  setuju: true
  nama_penyetuju: string
  jabatan?: string
  jadwal_id?: string
  /** PNG data URL dari kanvas tanda tangan. */
  tanda_tangan?: string
}

export interface HasilSetuju {
  nomor_permintaan: string
  nomor_pekerjaan: string
  status_pekerjaan: string
  total: number
  deposit: number
  jadwal_dipilih: SlotJadwal | null
}

export async function setujuiPenawaran(nomor: string, isi: IsiSetuju): Promise<HasilSetuju> {
  const { data } = await apiClient.post<HasilSetuju>(`/servis-ac/penawaran/${nomor}/setujui`, isi)
  return data
}

export interface IsiRevisi {
  kategori: string[]
  alasan?: string
  paket_alternatif?: string
  catatan: string
  per_item?: { item: string; permintaan: string }[]
}

export interface HasilRevisi {
  nomor_permintaan: string
  keputusan: 'revisi'
  jumlah_revisi: number
}

export async function ajukanRevisiPenawaran(nomor: string, isi: IsiRevisi): Promise<HasilRevisi> {
  const { data } = await apiClient.post<HasilRevisi>(`/servis-ac/penawaran/${nomor}/revisi`, isi)
  return data
}
