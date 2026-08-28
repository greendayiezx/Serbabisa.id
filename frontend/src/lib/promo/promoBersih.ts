/**
 * Katalog promo BisaBersih.
 *
 * Ditulis deklaratif dan terpisah dari tampilan supaya nanti bisa dipindah ke
 * tabel `promos` seperti BisaBelanja tanpa menyentuh halaman. Bedanya dengan
 * BisaBelanja: layanan ini belum punya katalog harga di backend, jadi angka di
 * sini masih jadi satu-satunya sumber.
 *
 * Setiap angka di sini dijaga scripts/cek-laba-bersih.ts (npm run cek:laba):
 * perintah itu keluar dengan kode 1 kalau ada promo, bundling, atau paket yang
 * menyisakan laba di bawah ambang sehat. Jangan mengubah nilai di sini tanpa
 * menjalankannya.
 */

export type GrupBersih =
  | 'first-clean'
  | 'langganan'
  | 'bundling'
  | 'musiman'
  | 'cashback'
  | 'referral'

export interface VoucherBersih {
  id: string
  /** Kode yang diketik pengguna saat checkout. */
  kode: string
  judul: string
  /** Minimal nilai transaksi sebelum promo berlaku. */
  minTransaksi: number
  /** Potongan nominal tetap. */
  potongan?: number
  /** Cashback persentase (dipakai bergantian dengan `potongan`). */
  cashbackPersen?: number
  cashbackMaks?: number
  /** Tipe layanan yang dituju, untuk ditampilkan sebagai keterangan. */
  layanan?: string
  /** Masa berlaku cashback dalam hari. */
  berlakuHari?: number
  /** Rentang tanggal untuk promo musiman. */
  periode?: string
  /**
   * Hadiah untuk pihak KEDUA pada promo referral.
   *
   * Promo ini dibayar dua kali: yang diajak dapat `potongan`, pengajak dapat
   * nilai ini. Ditulis sebagai data supaya pemeriksa laba ikut menghitungnya —
   * sebelumnya angkanya mati di dalam skrip, sehingga menaikkan hadiah pengajak
   * tidak akan ketahuan membuat promonya merugi.
   */
  hadiahPengajak?: number
}

export interface Ajakan {
  emoji: string
  judul: string
  baris: string[]
  cta: string
}

export interface GrupVoucher {
  id: GrupBersih
  judul: string
  /** Kenapa promo ini ada — membantu saat menimbang mana yang dipertahankan. */
  tujuan: string
  ajakan: Ajakan
  voucher: VoucherBersih[]
}

/* ------------------------------------------------------------------ *
 * 1. First Clean — akuisisi pengguna baru
 * ------------------------------------------------------------------ */
export const FIRST_CLEAN: GrupVoucher = {
  id: 'first-clean',
  judul: 'Promo Pengguna Baru',
  tujuan: 'Akuisisi — menurunkan hambatan mencoba pertama kali.',
  ajakan: {
    emoji: '🎉',
    judul: 'Rumah Kinclong Pertama Kali?',
    baris: [
      'Coba BisaBersih, diskon sampai Rp60.000!',
      'Cleaner terverifikasi & terlatih',
      'Garansi re-clean gratis 24 jam',
      'Asuransi kerusakan barang',
    ],
    cta: 'Pesan Sekarang',
  },
  voucher: [
    { id: 'baru40', kode: 'BERSIHBARU40', judul: 'Diskon Rp40.000', minTransaksi: 180000, potongan: 40000, layanan: 'General cleaning' },
    { id: 'baru50', kode: 'BERSIHBARU50', judul: 'Diskon Rp50.000', minTransaksi: 250000, potongan: 50000, layanan: 'Deep cleaning' },
    { id: 'baru60', kode: 'BERSIHBARU60', judul: 'Diskon Rp60.000', minTransaksi: 400000, potongan: 60000, layanan: 'Deep + cuci karpet' },
  ],
}

/* ------------------------------------------------------------------ *
 * 4. Musiman — urgensi lewat momen
 * ------------------------------------------------------------------ */
export const MUSIMAN: GrupVoucher = {
  id: 'musiman',
  judul: 'Promo Musiman',
  tujuan: 'Urgensi — memberi alasan memesan sekarang, bukan nanti.',
  ajakan: {
    emoji: '🇮🇩',
    judul: 'Promo Merdeka BisaBersih!',
    baris: [
      'Sambut HUT RI, rumah bebas kotor!',
      'Min. Rp180.000 → potong Rp17.000 (kode MERDEKA17)',
      'Periode 13–18 Agustus 2026.',
    ],
    cta: 'Klaim Sekarang',
  },
  voucher: [
    { id: 'merdeka', kode: 'MERDEKA17', judul: 'HUT RI: Diskon Rp17.000', minTransaksi: 180000, potongan: 17000, periode: '13–18 Agustus' },
    { id: 'ramadan', kode: 'SAHURBERSIH', judul: 'Ramadan: Diskon Rp25.000', minTransaksi: 200000, potongan: 25000, periode: 'Sepanjang Ramadan' },
    { id: 'tahunbaru', kode: 'TAHUNBARU', judul: 'Akhir Tahun: Diskon Rp40.000', minTransaksi: 300000, potongan: 40000, periode: '24 Des – 2 Jan' },
  ],
}

/* ------------------------------------------------------------------ *
 * 5. Cashback — mendorong pesanan berikutnya
 * ------------------------------------------------------------------ */
export const CASHBACK: GrupVoucher = {
  id: 'cashback',
  judul: 'Cashback',
  tujuan: 'Repeat purchase — saldo hanya berguna kalau dipakai memesan lagi.',
  ajakan: {
    emoji: '💰',
    judul: 'Cashback 10%!',
    baris: [
      'Belanja min. Rp200.000, cashback sampai Rp30.000.',
      'Bisa dipakai untuk order berikutnya.',
      'Double BisaPoints untuk pelanggan langganan!',
    ],
    cta: 'Klaim Cashback',
  },
  voucher: [
    { id: 'cb10', kode: 'CASHBACK10', judul: 'Cashback 10%', minTransaksi: 200000, cashbackPersen: 10, cashbackMaks: 30000, berlakuHari: 7 },
    { id: 'cb15', kode: 'CASHBACK15', judul: 'Cashback 15%', minTransaksi: 400000, cashbackPersen: 15, cashbackMaks: 60000, berlakuHari: 14 },
  ],
}

/* ------------------------------------------------------------------ *
 * 6. Referral — pertumbuhan dari mulut ke mulut
 * ------------------------------------------------------------------ */
export const REFERRAL: GrupVoucher = {
  id: 'referral',
  judul: 'Traktiran Teman',
  tujuan: 'Viral — biaya akuisisi dibayar hanya kalau transaksinya jadi.',
  ajakan: {
    emoji: '🤝',
    judul: 'Traktiran Teman!',
    baris: [
      'Teman: diskon Rp30.000 (min. belanja Rp200.000)',
      'Kamu: cashback Rp30.000 setelah pesanannya selesai',
    ],
    cta: 'Bagikan Kode',
  },
  voucher: [
    { id: 'traktir', kode: 'TRAKTIR30', judul: 'Diskon Rp30.000 untuk teman', minTransaksi: 200000, potongan: 30000, hadiahPengajak: 30000 },
  ],
}

export const GRUP_VOUCHER: GrupVoucher[] = [FIRST_CLEAN, MUSIMAN, CASHBACK, REFERRAL]

/* ------------------------------------------------------------------ *
 * 2. Langganan — pendapatan berulang
 * ------------------------------------------------------------------ */
export interface PaketBersih {
  id: string
  nama: string
  frekuensi: string
  hargaBulanan: number
  kunjunganPerBulan: number
  diskonPersen: number
  benefit: string
  /**
   * Berapa deep clean gratis yang ditanggung per bulan. Pecahan berarti
   * berkala: 1/3 = sekali tiap tiga bulan. Ditulis sebagai angka, bukan
   * disimpulkan dari teks benefit, supaya pemeriksa laba tidak menebak.
   */
  deepCleanGratisPerBulan?: number
  unggulan?: boolean
}

export const LANGGANAN_BERSIH: PaketBersih[] = [
  { id: 'harian', nama: 'Harian', frekuensi: '2x/minggu', hargaBulanan: 1500000, kunjunganPerBulan: 8, diskonPersen: 0, benefit: 'Prioritas jadwal' },
  { id: 'mingguan', nama: 'Mingguan', frekuensi: '1x/minggu', hargaBulanan: 850000, kunjunganPerBulan: 4, diskonPersen: 10, benefit: 'Gratis deep clean tiap 3 bulan', deepCleanGratisPerBulan: 1 / 3, unggulan: true },
  { id: 'dua-mingguan', nama: '2 Mingguan', frekuensi: '2x/bulan', hargaBulanan: 500000, kunjunganPerBulan: 2, diskonPersen: 8, benefit: 'Diskon 5% supplies' },
  { id: 'bulanan', nama: 'Bulanan', frekuensi: '1x/bulan', hargaBulanan: 280000, kunjunganPerBulan: 1, diskonPersen: 5, benefit: 'Gratis re-clean' },
]

/** Harga per kunjungan — dasar klaim "lebih hemat" yang bisa diperiksa. */
export function hargaPerKunjungan(paket: PaketBersih): number {
  return Math.round(paket.hargaBulanan / paket.kunjunganPerBulan)
}

/**
 * Hemat sebenarnya dibanding paket Bulanan (harga satuan termahal).
 *
 * Dihitung, bukan ditulis tangan: materi awal menyebut "hemat sampai 40%",
 * padahal dari angkanya sendiri paket Mingguan hanya 24% lebih murah per
 * kunjungan dibanding Bulanan. Angka klaim sebaiknya mengikuti fungsi ini.
 */
export function hematPersen(paket: PaketBersih): number {
  const acuan = LANGGANAN_BERSIH.find((p) => p.id === 'bulanan')
  if (!acuan || paket.id === 'bulanan') return 0
  return Math.round((1 - hargaPerKunjungan(paket) / hargaPerKunjungan(acuan)) * 100)
}

/* ------------------------------------------------------------------ *
 * 3. Bundling — menaikkan nilai per pesanan
 * ------------------------------------------------------------------ */
export interface BundleBersih {
  id: string
  nama: string
  isi: string
  hargaNormal: number
  hargaPromo: number
  bonus?: string
}

export const BUNDLE_BERSIH: BundleBersih[] = [
  { id: 'kinclong-total', nama: 'Kinclong Total', isi: 'General cleaning + cuci karpet', hargaNormal: 500000, hargaPromo: 420000, bonus: 'Sampo karpet premium GRATIS' },
  { id: 'fresh-home', nama: 'Fresh Home', isi: 'Deep clean + poles lantai', hargaNormal: 650000, hargaPromo: 550000 },
  { id: 'move-in-out', nama: 'Move-in/Move-out', isi: 'Deep clean full unit', hargaNormal: 600000, hargaPromo: 510000 },
]

export const hematBundle = (b: BundleBersih) => b.hargaNormal - b.hargaPromo

/* ------------------------------------------------------------------ *
 * BisaPoints
 * ------------------------------------------------------------------ */
export const BISAPOINTS = {
  perRupiah: 1000,
  tukarPoin: 1000,
  tukarNilai: 10000,
  catatan: 'Double points untuk pelanggan langganan.',
}

/* ------------------------------------------------------------------ *
 * 7. Jaminan — bukan diskon, tapi pendorong konversi terbesar
 * ------------------------------------------------------------------ */
export const JAMINAN: string[] = [
  'Re-clean gratis dalam 24 jam jika tidak puas',
  'Asuransi kerusakan barang hingga Rp1 juta',
  'Cleaner terverifikasi (background check + training)',
  'Before-after photo report setiap kunjungan',
  'Garansi 30 hari untuk deep cleaning',
]
