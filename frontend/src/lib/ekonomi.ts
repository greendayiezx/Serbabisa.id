/**
 * Asumsi ekonomi satu pesanan BisaBelanja.
 *
 * Dipusatkan di sini supaya nilai promo bisa diturunkan dari angka, bukan
 * ditebak — dan supaya saat ongkos riil sudah diketahui, cukup satu file yang
 * diubah lalu tes akan langsung menandai promo mana yang jadi merugi.
 */

import { BIAYA_LAYANAN } from './belanja/ongkir'

/**
 * Markup rata-rata per item, dihitung dari katalog (2.884 produk tersedia).
 * Ditulis sebagai konstanta, bukan dihitung ulang tiap render, karena ini
 * dipakai untuk kalibrasi promo — bukan untuk menagih customer.
 */
export const MARKUP_RATA_PER_ITEM = 1327

/**
 * Harga per item untuk skenario TERBURUK: keranjang berisi barang mahal, jadi
 * jumlah itemnya sedikit dan markup yang terkumpul paling kecil. Dipakai
 * sengaja supaya promo aman bahkan untuk keranjang yang paling tidak
 * menguntungkan. Angka ini mendekati rata-rata kategori termahal (daging
 * Rp55.934, frozen Rp45.744).
 */
export const HARGA_ITEM_PESIMIS = 45000

/**
 * Biaya payment gateway, persen dari total tagihan.
 * PLACEHOLDER — QRIS di Indonesia umumnya 0,7%, kartu kredit bisa 2–3%.
 * Ganti dengan angka kontrak Anda yang sebenarnya.
 */
export const MDR_PERSEN = 0.7

/**
 * Ongkos operasional per pesanan di luar kurir (CS, notifikasi, dsb).
 * PLACEHOLDER — ganti dengan angka riil.
 */
export const BIAYA_OPS_PER_PESANAN = 300

/**
 * Ongkir dianggap titipan: yang ditagih ke customer diteruskan penuh ke kurir,
 * jadi tidak menambah laba. Kalau nanti platform mengambil potongan, ubah ini.
 */
export const MARGIN_ONGKIR_PERSEN = 0

/** Laba bersih minimum yang harus tersisa dari tiap pesanan setelah promo. */
export const LABA_MINIMUM_PER_PESANAN = 1000

/** Perkiraan jumlah item untuk sebuah nilai keranjang, versi pesimis. */
export function perkiraanItem(nilaiKeranjang: number): number {
  return Math.max(1, Math.floor(nilaiKeranjang / HARGA_ITEM_PESIMIS))
}

/** Pendapatan kotor platform dari sebuah keranjang, sebelum promo & biaya. */
export function pendapatanKotor(nilaiKeranjang: number, ongkir = 0): number {
  return (
    perkiraanItem(nilaiKeranjang) * MARKUP_RATA_PER_ITEM +
    BIAYA_LAYANAN +
    (ongkir * MARGIN_ONGKIR_PERSEN) / 100
  )
}

/** Laba bersih setelah promo, MDR, dan biaya operasional. */
export function labaBersih(nilaiKeranjang: number, biayaPromo: number, ongkir = 0): number {
  const tagihan = Math.max(0, nilaiKeranjang + BIAYA_LAYANAN + ongkir - biayaPromo)
  const mdr = (tagihan * MDR_PERSEN) / 100
  return pendapatanKotor(nilaiKeranjang, ongkir) - biayaPromo - mdr - BIAYA_OPS_PER_PESANAN
}

/**
 * Nilai promo terbesar yang masih menyisakan LABA_MINIMUM_PER_PESANAN.
 * Inilah plafon yang dipakai untuk menetapkan besaran tiap promo.
 */
export function promoMaksimal(nilaiKeranjang: number, ongkir = 0): number {
  let batas = 0
  // Naik Rp100 selama labanya masih di atas minimum. Cukup untuk kalibrasi,
  // dan lebih jelas dibaca daripada menyelesaikan persamaannya secara aljabar.
  while (labaBersih(nilaiKeranjang, batas + 100, ongkir) >= LABA_MINIMUM_PER_PESANAN) {
    batas += 100
  }
  return batas
}
