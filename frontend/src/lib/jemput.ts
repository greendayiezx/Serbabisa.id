/**
 * Katalog tampilan BisaJemput.
 *
 * Tidak ada satu pun angka tarif di berkas ini, dan itu disengaja. Tarif
 * BisaJemput bergantung jarak, waktu tempuh, dan jam sibuk — tiga hal yang
 * hanya server yang tahu dengan benar. Salinan tarif di sisi klien akan selalu
 * ketinggalan, dan yang ketinggalan itu muncul sebagai harga di layar yang
 * berbeda dari tagihan.
 *
 * Yang ada di sini hanya label: nama pembayaran, tag penilaian, dan kalimat
 * tiap tahap perjalanan.
 */

export interface PilihanJemput {
  tipe: string
  varian: string
  kelas: 'motor' | 'mobil'
  label: string
  label_varian: string
  catatan: string
  keterangan: string
  kapasitas: number
  fitur: string[]
  km: number
  menit: number
  jemput_menit: [number, number]
  baris: { label: string; nilai: number }[]
  tarif: number
  sibuk: string | null
  sibuk_alasan: string | null
  sibuk_pengali: number
  komisi: number
  promo: PromoJemput[]
  promo_terbaik: PromoJemput | null
  tarif_setelah_promo: number
}

export interface PromoJemput {
  kode: string
  nama: string
  jenis: 'akuisisi' | 'berulang'
  persen: number
  maks: number
  minimum: number
  deskripsi: string
  potongan: number
  bisa_dipakai: boolean
  alasan: string | null
}

export interface TitikJemput {
  alamat: string
  lat: number
  lng: number
  catatan?: string | null
}

export const METODE_BAYAR = [
  { id: 'gopay', nama: 'GoPay', ikon: 'wallet' },
  { id: 'ovo', nama: 'OVO', ikon: 'wallet' },
  { id: 'dana', nama: 'DANA', ikon: 'wallet' },
  { id: 'va', nama: 'Virtual Account', ikon: 'card' },
  { id: 'tunai', nama: 'Tunai', ikon: 'receipt' },
]

/** Urutan tahap. Layar perjalanan menggantungkan seluruh isinya pada ini. */
export const TAHAP = ['mencari', 'dijemput', 'tiba', 'jalan', 'selesai'] as const
export type Tahap = (typeof TAHAP)[number] | 'batal'

export const JUDUL_TAHAP: Record<string, { judul: string; keterangan: string }> = {
  mencari: {
    judul: 'Mencari pengemudi terdekat',
    keterangan: 'Belum ada pengemudi yang ditugaskan. Kamu belum ditagih apa pun.',
  },
  dijemput: {
    judul: 'Pengemudi menuju titik jemput',
    keterangan: 'Tunggu di titik yang sudah kamu konfirmasi.',
  },
  tiba: {
    judul: 'Pengemudi sudah tiba',
    keterangan: 'Cocokkan pelat nomornya sebelum naik.',
  },
  jalan: {
    judul: 'Dalam perjalanan',
    keterangan: 'Perjalanan sedang berlangsung.',
  },
  selesai: {
    judul: 'Perjalanan selesai',
    keterangan: 'Terima kasih sudah naik BisaJemput.',
  },
  batal: {
    judul: 'Perjalanan dibatalkan',
    keterangan: 'Perjalanan ini sudah dibatalkan.',
  },
}

export const TAG_PENILAIAN = [
  'Ramah',
  'Kendaraan bersih',
  'Menyetir hati-hati',
  'Tepat waktu',
  'Tahu jalan',
  'Bantu bawa barang',
]

export function rupiah(n: number): string {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}

/** "3-6 menit" dari rentang yang dikirim server. */
export function rentangMenit([a, b]: [number, number]): string {
  return a === b ? `${a} menit` : `${a}-${b} menit`
}
