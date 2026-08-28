import apiClient from './client'

/**
 * Tautan sementara untuk MEMBUKA invoice PDF di penampil peramban.
 *
 * Bukan blob URL: Chrome menolak menampilkan PDF dari blob di sebagian
 * lingkungan (emulasi ponsel di antaranya), dan yang muncul hanya halaman
 * kosong. Yang dipakai adalah URL http bertanda tangan dari server.
 *
 * Server mengembalikan jalur RELATIF; asalnya diambil dari alamat API — kalau
 * dibuat absolut di server, tanda tangannya gagal diverifikasi begitu host yang
 * dipakai berbeda dari APP_URL.
 */
export async function tautanInvoicePdf(nomor: string): Promise<string> {
  const { data } = await apiClient.get<{ url: string }>(`/invoice/${nomor}/tautan`)

  return new URL(data.url, import.meta.env.VITE_API_BASE_URL).href
}
