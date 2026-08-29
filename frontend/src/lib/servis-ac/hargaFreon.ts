/**
 * Harga "Cek & Tambah Freon" — salinan tampilan dari App\Services\FreonTarif.
 *
 * Yang dijual di muka hanya PEMERIKSAAN. Harga pekerjaan lanjutan ada di sini
 * untuk ditampilkan pada layar hasil pemeriksaan; yang menagih tetap server.
 */

export const BIAYA_PEMERIKSAAN = 50_000
export const BIAYA_UNIT_TAMBAHAN = 25_000

export interface PekerjaanFreon {
  id: string
  nama: string
  harga: number
  satuan: string
}

export const PEKERJAAN_FREON: PekerjaanFreon[] = [
  { id: 'perbaikan-bocor', nama: 'Perbaikan titik kebocoran (las/flare)', harga: 150_000, satuan: 'titik' },
  { id: 'vakum', nama: 'Vakum sistem', harga: 100_000, satuan: 'unit' },
  { id: 'freon-r32', nama: 'Pengisian freon R32 (full)', harga: 300_000, satuan: 'unit' },
  { id: 'freon-r410a', nama: 'Pengisian freon R410A (full)', harga: 350_000, satuan: 'unit' },
  { id: 'freon-r22', nama: 'Pengisian freon R22 (full)', harga: 250_000, satuan: 'unit' },
  { id: 'ganti-kapasitor', nama: 'Ganti kapasitor', harga: 175_000, satuan: 'unit' },
]

export const KELUHAN_FREON = [
  { id: 'kurang-dingin', nama: 'AC kurang dingin' },
  { id: 'tidak-dingin', nama: 'AC tidak dingin sama sekali' },
  { id: 'bunga-es', nama: 'Keluar bunga es' },
  { id: 'pernah-bocor', nama: 'Pernah bocor' },
  { id: 'freon-lama', nama: 'Isi freon > 6 bulan lalu' },
  { id: 'hanya-cek', nama: 'Hanya ingin pemeriksaan' },
  { id: 'tidak-tahu', nama: 'Tidak tahu penyebabnya' },
]

export const MEREK_AC = [
  { id: 'daikin', nama: 'Daikin' },
  { id: 'panasonic', nama: 'Panasonic' },
  { id: 'sharp', nama: 'Sharp' },
  { id: 'lg', nama: 'LG' },
  { id: 'samsung', nama: 'Samsung' },
  { id: 'polytron', nama: 'Polytron' },
  { id: 'lainnya', nama: 'Lainnya' },
]

/**
 * "Tidak tahu" adalah pilihan yang sah.
 *
 * Memaksa pelanggan menebak R32/R410A/R22 hanya menghasilkan data yang salah;
 * teknisi tetap membaca label unitnya di lokasi.
 */
export const JENIS_FREON = [
  { id: 'r32', nama: 'R32' },
  { id: 'r410a', nama: 'R410A' },
  { id: 'r22', nama: 'R22' },
  { id: 'tidak-tahu', nama: 'Tidak tahu' },
]

export const SLOT_FREON = ['09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-17:00']

export function cariPekerjaan(id: string): PekerjaanFreon | null {
  return PEKERJAAN_FREON.find((p) => p.id === id) ?? null
}

export interface RincianPemeriksaan {
  unit: number
  biayaPemeriksaan: number
  biayaUnitTambahan: number
  total: number
}

export function hitungPemeriksaan(unit: number): RincianPemeriksaan {
  const jumlah = Math.max(1, unit || 1)
  const tambahan = (jumlah - 1) * BIAYA_UNIT_TAMBAHAN

  return {
    unit: jumlah,
    biayaPemeriksaan: BIAYA_PEMERIKSAAN,
    biayaUnitTambahan: tambahan,
    total: BIAYA_PEMERIKSAAN + tambahan,
  }
}

export interface RincianRekomendasi {
  baris: PekerjaanFreon[]
  subtotal: number
  kreditPemeriksaan: number
  total: number
}

/**
 * Rekomendasi teknisi beserta kredit biaya pemeriksaan.
 *
 * Pemeriksaan yang sudah dibayar dipotong dari total — bukan ditagih dua kali.
 */
export function hitungRekomendasi(pekerjaan: string[], biayaPemeriksaan: number): RincianRekomendasi {
  const baris = pekerjaan.map(cariPekerjaan).filter((p): p is PekerjaanFreon => p !== null)
  const subtotal = baris.reduce((jml, b) => jml + b.harga, 0)
  const kredit = Math.min(biayaPemeriksaan, subtotal)

  return { baris, subtotal, kreditPemeriksaan: kredit, total: subtotal - kredit }
}
