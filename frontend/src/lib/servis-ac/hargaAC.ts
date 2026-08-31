/**
 * Harga Servis AC — salinan tampilan dari App\Services\ACTarif.
 *
 * Angka di sini hanya untuk MENAMPILKAN estimasi; yang menagih tetap server.
 * Kalau keduanya berbeda, yang benar adalah server — dan berkas ini yang harus
 * diperbarui.
 *
 * Kapasitas (PK) dan tipe AC sengaja TIDAK mengubah harga: keduanya direkam
 * untuk teknisi supaya ia datang dengan alat yang benar. Menagihkannya tanpa
 * dasar biaya yang jelas hanya membuat estimasi meleset dari tagihan.
 */

export type PaketACId = 'standard' | 'premium' | 'deep'

export interface PaketAC {
  id: PaketACId
  nama: string
  deskripsi: string
  harga: number
  sorot?: string
}

export const PAKET_AC: PaketAC[] = [
  {
    id: 'standard',
    nama: 'Cuci Standard',
    deskripsi: 'Pembersihan filter, unit indoor & outdoor, serta pengecekan drainase.',
    harga: 100_000,
  },
  {
    id: 'premium',
    nama: 'Cuci Premium',
    deskripsi: 'Cuci menyeluruh dengan perlindungan area kerja ekstra aman.',
    harga: 150_000,
  },
  {
    id: 'deep',
    nama: 'Deep Cleaning AC',
    deskripsi: 'Pembersihan intensif untuk AC sangat kotor atau berbau tidak sedap.',
    harga: 250_000,
    sorot: 'Rekomendasi',
  },
]

/** Sekali per kunjungan, bukan per unit. */
export const BIAYA_KUNJUNGAN = 10_000
export const DISKON_2_UNIT = 20_000
export const MIN_UNIT_DISKON = 2
export const MIN_UNIT_GRATIS_KUNJUNGAN = 3

export const TIPE_AC = [
  { id: 'split', nama: 'Split' },
  { id: 'inverter', nama: 'Inverter' },
  { id: 'cassette', nama: 'Cassette' },
  { id: 'standing', nama: 'Standing' },
  { id: 'tidak-tahu', nama: 'Tidak tahu' },
]

export const KAPASITAS_AC = [
  { id: '0.5', nama: '0.5 PK' },
  { id: '1', nama: '1 PK' },
  { id: '1.5', nama: '1.5 PK' },
  { id: '2', nama: '2 PK' },
  { id: 'tidak-tahu', nama: 'Tidak tahu' },
]

export const TERAKHIR_CUCI = [
  { id: '<3-bulan', nama: '< 3 Bulan' },
  { id: '3-6-bulan', nama: '3–6 Bulan' },
  { id: '>6-bulan', nama: '> 6 Bulan' },
  { id: 'belum-pernah', nama: 'Belum Pernah' },
]

export const KONDISI_AC = [
  { id: 'berbau', nama: 'Berbau' },
  { id: 'kurang-dingin', nama: 'Kurang dingin' },
  { id: 'bocor', nama: 'Bocor air' },
  { id: 'berdebu', nama: 'Sangat berdebu' },
  { id: 'tidak-ada-keluhan', nama: 'Tidak ada keluhan (perawatan rutin)' },
  { id: 'lainnya', nama: 'Lainnya' },
]

export const RUTIN_AC = [
  { id: '3-bulan', nama: 'Tiap 3 Bulan' },
  { id: '6-bulan', nama: 'Tiap 6 Bulan' },
]

/** Diskon jadwal rutin berlaku untuk kunjungan BERIKUTNYA, bukan yang ini. */
export const DISKON_RUTIN_PERSEN = 20

export function cariPaketAC(id: string | null | undefined): PaketAC {
  return PAKET_AC.find((p) => p.id === id) ?? PAKET_AC[0]
}

export interface BarisHargaAC {
  label: string
  nilai: number
  /** Baris potongan ditampilkan dengan tanda minus dan warna berbeda. */
  potongan?: boolean
}

export interface RincianAC {
  baris: BarisHargaAC[]
  layanan: number
  biayaKunjungan: number
  gratisKunjungan: boolean
  diskonBundling: number
  total: number
}

/**
 * Rincian estimasi. Total DITURUNKAN dari daftar barisnya, bukan dihitung
 * terpisah — dua perhitungan paralel untuk angka yang sama adalah cara paling
 * gampang membuat rincian dan total saling berbeda.
 */
export function hitungHargaAC(paketId: string, unit: number): RincianAC {
  const paket = cariPaketAC(paketId)
  const jumlah = Math.max(1, unit || 1)

  const layanan = paket.harga * jumlah
  const gratisKunjungan = jumlah >= MIN_UNIT_GRATIS_KUNJUNGAN
  const biayaKunjungan = gratisKunjungan ? 0 : BIAYA_KUNJUNGAN
  const diskonBundling = jumlah >= MIN_UNIT_DISKON ? DISKON_2_UNIT : 0

  const baris: BarisHargaAC[] = [
    { label: `${paket.nama} (${jumlah} unit)`, nilai: layanan },
  ]
  if (biayaKunjungan) baris.push({ label: 'Biaya kunjungan', nilai: biayaKunjungan })
  if (gratisKunjungan) baris.push({ label: 'Bebas biaya kunjungan (3 unit+)', nilai: 0 })
  if (diskonBundling) {
    baris.push({ label: `Hemat ${jumlah} unit sekaligus`, nilai: diskonBundling, potongan: true })
  }

  return {
    baris,
    layanan,
    biayaKunjungan,
    gratisKunjungan,
    diskonBundling,
    total: layanan + biayaKunjungan - diskonBundling,
  }
}
