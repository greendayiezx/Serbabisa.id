import apiClient from './client'

/**
 * Langganan BisaBelanja.
 *
 * Statusnya WAJIB datang dari server. Sebelumnya paket aktif hanya ditandai di
 * localStorage, sehingga checkout memotong harga untuk langganan yang tidak
 * diketahui backend — layar menampilkan Rp44.000, server menagih Rp49.000.
 * Sekarang `subscription_id` dari sinilah yang dikirim saat checkout, dan
 * potongannya dihitung ulang server dari kuota yang benar-benar tersisa.
 */

export type KodePaket = 'basic' | 'premium' | 'family'

export interface LanggananAktif {
  id: number
  status: string
  berakhir_pada: string | null
  sisa_kuota_diskon: number
  sisa_kuota_ongkir: number
  plan: {
    id: number
    kode: KodePaket
    nama: string
    harga: string | number
    diskon_per_transaksi: string | number
    kuota_diskon: number
    kuota_ongkir: number
    min_ongkir_gratis: string | number
  }
}

interface ResponsIndex {
  paket: unknown[]
  aktif: LanggananAktif | null
}

export async function ambilLangganan(): Promise<LanggananAktif | null> {
  const { data } = await apiClient.get<ResponsIndex>('/belanja/langganan')
  return data.aktif
}

export async function berlangganan(kode: KodePaket): Promise<LanggananAktif> {
  const { data } = await apiClient.post<LanggananAktif>('/belanja/langganan', { kode })
  return data
}

export async function berhentiLangganan(): Promise<void> {
  await apiClient.delete('/belanja/langganan')
}
