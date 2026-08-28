import {
  BIAYA_PERJALANAN,
  HARGA_PER_JAM_TERTINGGI,
  MARKUP_PER_JAM,
  RASIO_BIAYA_PERJALANAN,
} from './hargaBersih'

/**
 * Model biaya BisaBersih.
 *
 * Semua angka diturunkan dari materi paket Mingguan yang jadi acuan: pendapatan
 * Rp850.000/bulan dengan biaya cleaner Rp400.000 (4 kunjungan) dan supplies
 * Rp50.000. Dari situ didapat biaya per kunjungan dan rasio biaya terhadap
 * pendapatan yang dipakai untuk menilai promo satuan.
 *
 * PENTING — dua model pendapatan yang berbeda hidup berdampingan:
 *
 * 1. BERSIH RUMAH (pesanan per jam). Pendapatan platform HANYA markup tetap
 *    Rp10.000 tiap jam kerja; sisanya diteruskan ke cleaner. Marjinnya tidak
 *    proporsional terhadap nilai transaksi, jadi `marjinKotor()` TIDAK boleh
 *    dipakai di sini — gunakan `marjinBersihRumah()`.
 *
 * 2. BUNDLING & LANGGANAN (harga paket tetap). Masih memakai rasio biaya di
 *    bawah ini, karena harganya ditetapkan sebagai produk, bukan dihitung dari
 *    jam kerja cleaner.
 *
 * Rasio lama dipertahankan untuk (2), tapi memakainya pada (1) akan
 * melebih-lebihkan laba berkali lipat — pada transaksi Rp170.000 rasio itu
 * mengklaim marjin Rp80.000, padahal kenyataannya Rp23.750–35.000.
 */

/** Upah cleaner per kunjungan: Rp400.000 / 4 kunjungan. */
export const BIAYA_CLEANER_PER_KUNJUNGAN = 100_000

/** Supplies per kunjungan: Rp50.000 / 4 kunjungan. */
export const BIAYA_SUPPLIES_PER_KUNJUNGAN = 12_500

export const BIAYA_PER_KUNJUNGAN = BIAYA_CLEANER_PER_KUNJUNGAN + BIAYA_SUPPLIES_PER_KUNJUNGAN

/**
 * Rasio biaya terhadap nilai transaksi = 450.000 / 850.000.
 *
 * Dipakai untuk transaksi satuan yang nilainya bebas (promo voucher & bundling),
 * karena upah cleaner untuk pekerjaan besar ikut naik sebanding.
 */
export const RASIO_BIAYA = 450_000 / 850_000

/** Marjin kotor sebelum promo, sebagai pecahan dari nilai transaksi. */
export const MARJIN_KOTOR = 1 - RASIO_BIAYA

/**
 * Laba minimum yang masih dianggap sehat per transaksi.
 *
 * Lebih besar dari BisaBelanja (Rp1.000) karena nilai transaksi kebersihan jauh
 * lebih besar dan sekali kunjungan memakan waktu berjam-jam — promo yang hanya
 * menyisakan ribuan rupiah tidak sepadan dengan risiko komplain & re-clean.
 */
export const LABA_MINIMUM = 20_000

/**
 * Biaya sebenarnya memberi satu deep clean gratis.
 *
 * BUKAN harga jualnya. Menghitung hadiah dengan harga jual melebih-lebihkan
 * kerugian: yang benar-benar keluar dari kas adalah upah cleaner + supplies.
 * Harga jual deep clean diambil dari bundling Move-in/Move-out (Rp510.000).
 *
 * Catatan kapasitas: kalau cleaner sedang penuh, memberi deep clean gratis
 * berarti menolak pesanan berbayar, sehingga biaya efektifnya mendekati harga
 * jual. Lihat BIAYA_PELUANG_DEEP_CLEAN.
 */
export const HARGA_JUAL_DEEP_CLEAN = 510_000
export const BIAYA_DEEP_CLEAN = Math.round(HARGA_JUAL_DEEP_CLEAN * RASIO_BIAYA)

/** Biaya deep clean gratis saat kapasitas cleaner penuh (kehilangan penjualan). */
export const BIAYA_PELUANG_DEEP_CLEAN = HARGA_JUAL_DEEP_CLEAN

/** BisaPoints: 1 poin tiap Rp1.000, 100 poin = voucher Rp10.000. */
export const POIN_PER_RUPIAH = 1_000
export const POIN_UNTUK_VOUCHER = 1_000
export const NILAI_VOUCHER_POIN = 10_000

/**
 * Nilai BisaPoints sebagai pecahan dari transaksi.
 *
 * Rp1.000 belanja → 1 poin. 1.000 poin → voucher Rp10.000.
 * Jadi Rp1.000.000 belanja menghasilkan voucher Rp10.000 = 1% dari transaksi.
 */
export const RASIO_POIN = NILAI_VOUCHER_POIN / (POIN_UNTUK_VOUCHER * POIN_PER_RUPIAH)

/**
 * Marjin kotor untuk produk berharga TETAP (bundling & langganan).
 *
 * Jangan dipakai untuk pesanan Bersih Rumah per jam — lihat
 * `marjinBersihRumah()`.
 */
export function marjinKotor(nilaiTransaksi: number): number {
  return nilaiTransaksi * MARJIN_KOTOR
}

/**
 * Marjin platform pada satu pesanan Bersih Rumah, KASUS TERBURUK.
 *
 * Pendapatannya dua sumber saja:
 * - markup Rp10.000 tiap jam kerja (jam × jumlah cleaner);
 * - sisa ongkos perjalanan yang tidak habis terpakai.
 *
 * Karena markup melekat pada JAM, bukan pada rupiah, nilai transaksi yang sama
 * bisa menghasilkan marjin berbeda tergantung tarif cleanernya. Yang dihitung
 * di sini sengaja yang paling kecil: tarif tertinggi → jam paling sedikit →
 * markup paling tipis. Promo yang lolos di sini aman di semua level.
 */
export function marjinBersihRumah(
  nilaiTransaksi: number,
  hargaPerJam = HARGA_PER_JAM_TERTINGGI,
): number {
  const jamOrang = Math.max(0, nilaiTransaksi - BIAYA_PERJALANAN) / hargaPerJam
  const marjinPerjalanan = BIAYA_PERJALANAN * (1 - RASIO_BIAYA_PERJALANAN)

  return MARKUP_PER_JAM * jamOrang + marjinPerjalanan
}

/** Potongan maksimum yang masih sehat untuk pesanan Bersih Rumah per jam. */
export function potonganMaksimalBersihRumah(nilaiTransaksi: number, ikutPoin = false): number {
  const poin = ikutPoin ? nilaiTransaksi * RASIO_POIN : 0

  return Math.max(0, marjinBersihRumah(nilaiTransaksi) - poin - LABA_MINIMUM)
}

/**
 * Potongan maksimum yang masih menyisakan LABA_MINIMUM pada nilai transaksi
 * tertentu. Dipakai untuk menyarankan angka promo, bukan menebaknya.
 */
export function potonganMaksimal(nilaiTransaksi: number, ikutPoin = false): number {
  const poin = ikutPoin ? nilaiTransaksi * RASIO_POIN : 0
  return Math.max(0, marjinKotor(nilaiTransaksi) - poin - LABA_MINIMUM)
}
