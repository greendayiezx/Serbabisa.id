import apiClient from './client'
import type { BelanjaItem } from '@/stores/belanjaDraft'
import type { Pesanan, RincianBiaya, StatusPesanan } from '@/stores/pesanan'
import { labelMetode } from '@/lib/metodeBayar'

/**
 * Jembatan ke endpoint BisaBelanja di Laravel.
 *
 * Yang dikirim ke server sengaja minimal: daftar SKU + qty, jarak, dan pilihan
 * promo. Harga TIDAK ikut dikirim untuk item berkatalog — server menghitung
 * ulang semuanya dari `products.harga_jual` dan mengevaluasi ulang promonya.
 * Kalau angka di layar berbeda dengan hasil hitungan server, yang benar adalah
 * server; itu sebabnya halaman status membaca nota dari respons, bukan dari
 * angka yang tadi ditampilkan di checkout.
 */

export interface ItemCheckout {
  /** Id katalog frontend = `products.sku`. Kosong untuk item ketik manual. */
  sku?: string
  nama: string
  kategori?: string
  satuan?: string
  /** Hanya dipakai untuk item tanpa SKU; item berkatalog memakai harga DB. */
  harga?: number
  qty: number
  catatan?: string
}

export interface PayloadCheckout {
  items: ItemCheckout[]
  jarak_km: number
  promo_slug?: string | null
  subscription_id?: number | null
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  toko?: string
  catatan?: string
  nama_penerima?: string
  telepon_penerima?: string
  metode?: string
}

/** Bentuk respons Task dari API. Hanya field yang dipakai UI yang dideklarasikan. */
interface TaskApi {
  id: number
  nomor_invoice: string | null
  toko: string | null
  lokasi_alamat: string | null
  catatan: string | null
  nama_penerima: string | null
  telepon_penerima: string | null
  fulfillment_status: string | null
  created_at: string
  category?: { slug: string; nama: string } | null
  items?: Array<{
    sku: string | null
    nama: string
    kategori: string | null
    satuan: string | null
    harga_satuan: string | number | null
    qty: number
    catatan: string | null
  }>
  payment?: {
    jumlah: string | number
    subtotal_barang: string | number
    ongkir: string | number
    ongkir_normal: string | number
    potongan: string | number
    cashback: string | number
    service_fee: string | number
    metode: string | null
  } | null
}

/** Laravel mengirim decimal sebagai string ("12500.00"); jadikan angka. */
const n = (v: string | number | null | undefined): number => {
  const x = typeof v === 'string' ? Number.parseFloat(v) : v
  return Number.isFinite(x) ? (x as number) : 0
}

const STATUS_DIKENAL: StatusPesanan[] = ['diproses', 'dibelanjakan', 'diantar', 'selesai']

/** Menit estimasi tiba. Server belum punya kolom ETA, jadi masih dihitung di sini. */
const ETA_MULAI_MENIT = 25
const ETA_SELESAI_MENIT = 45

/** Ubah Task dari API jadi bentuk Pesanan yang dipakai halaman status & nota. */
export function kePesanan(t: TaskApi): Pesanan {
  const dibuat = new Date(t.created_at)
  const invoice = t.nomor_invoice ?? ''
  const bayar = t.payment ?? null

  const ongkir = n(bayar?.ongkir)
  const ongkirNormal = n(bayar?.ongkir_normal)

  const biaya: RincianBiaya = {
    totalBarang: n(bayar?.subtotal_barang),
    potongan: n(bayar?.potongan),
    biayaLayanan: n(bayar?.service_fee),
    ongkir,
    ongkirGratis: ongkirNormal > 0 && ongkir === 0,
    ongkirNormal,
    cashback: n(bayar?.cashback),
    totalTagihan: n(bayar?.jumlah),
  }

  const items: BelanjaItem[] = (t.items ?? []).map((i, idx) => ({
    // Item ketik manual tidak punya SKU; dipakaikan id semu agar tetap unik
    // sebagai key daftar.
    id: i.sku ?? `manual-${t.id}-${idx}`,
    nama: i.nama,
    catatan: i.catatan ?? '',
    qty: i.qty,
    kategori: i.kategori ?? 'lainnya',
    harga: i.harga_satuan === null ? undefined : n(i.harga_satuan),
    satuan: i.satuan ?? undefined,
  }))

  const status = t.fulfillment_status as StatusPesanan
  return {
    id: t.id,
    invoice,
    // Bagian kode di URL: SBB-260819-5D9C5H0 → 5D9C5H0
    nomor: invoice.split('-')[2] ?? invoice,
    dibuatPada: dibuat.toISOString(),
    estimasiMulai: new Date(dibuat.getTime() + ETA_MULAI_MENIT * 60000).toISOString(),
    estimasiSelesai: new Date(dibuat.getTime() + ETA_SELESAI_MENIT * 60000).toISOString(),
    metodePembayaran: labelMetode(bayar?.metode),
    // Nama layanan datang dari relasi kategori, bukan disimpulkan dari judul:
    // halaman status dipakai bersama semua layanan, dan judul boleh berubah.
    layanan: t.category?.nama ?? 'Serbabisa',
    status: STATUS_DIKENAL.includes(status) ? status : 'diproses',
    toko: t.toko ?? '',
    alamat: t.lokasi_alamat ?? '',
    penerima: t.nama_penerima ?? '',
    telepon: t.telepon_penerima ?? '',
    catatan: t.catatan ?? '',
    items,
    biaya,
  }
}

export async function kirimCheckout(payload: PayloadCheckout): Promise<Pesanan> {
  const { data } = await apiClient.post<TaskApi>('/belanja/checkout', payload)
  return kePesanan(data)
}

export async function ambilPesanan(nomor: string): Promise<Pesanan> {
  const { data } = await apiClient.get<TaskApi>(`/belanja/orders/${encodeURIComponent(nomor)}`)
  return kePesanan(data)
}

export async function ambilRiwayat(): Promise<Pesanan[]> {
  const { data } = await apiClient.get<TaskApi[]>('/belanja/orders')
  return data.map(kePesanan)
}

/** Tandai pesanan selesai — di sinilah cashback masuk saldo lewat WalletLedger. */
export async function selesaikanPesanan(taskId: number): Promise<Pesanan> {
  const { data } = await apiClient.patch<TaskApi>(`/belanja/orders/${taskId}/selesai`)
  return kePesanan(data)
}

/**
 * Pesan error yang layak ditampilkan. Laravel mengirim 422 dengan `errors`
 * per-field saat promo ditolak — itu yang paling berguna buat pengguna,
 * bukan "Request failed with status code 422".
 */
export function pesanError(e: unknown): string {
  const err = e as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err?.response?.data
  const pertama = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
  if (pertama) return pertama
  if (data?.message) return data.message
  if (err?.response?.status === 401) return 'Sesi kamu habis. Masuk lagi ya.'
  return 'Gagal menghubungi server. Cek koneksi kamu lalu coba lagi.'
}
