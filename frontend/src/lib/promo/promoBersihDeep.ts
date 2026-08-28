/**
 * Promo BisaBersih Deep Cleaning — katalog tampilan.
 *
 * Salinan dari App\Services\PromoDeep; yang menghitung potongan sesungguhnya
 * adalah server. Bidang di sini hanya yang dibutuhkan untuk MENAMPILKAN dan
 * mengestimasi. DeepPromoTest menjaga keduanya tetap sama.
 *
 * DUA PROMO YANG DIUSULKAN TAPI TIDAK ADA DI SINI, beserta alasannya:
 *
 * - DEEPOFFICE100 / DEEPOFFICE250 ("khusus Deep Cleaning Kantor"). Deep
 *   cleaning di aplikasi ini adalah layanan RUMAH: paketnya Move-In, Pasca
 *   Renovasi, dan Sanitasi Total, dan checkoutnya tidak punya jenis kantor.
 *   Kebersihan kantor punya alurnya sendiri (BisaBersih Kantor) beserta katalog
 *   promonya sendiri. Kode kantor di sini tidak akan pernah bisa dipakai.
 *
 * - DEEPFRESH ("Deep Cleaning + Vacuum Sofa, hemat Rp75.000"). Deep cleaning
 *   belum punya layanan tambahan vacuum sofa — yang ada scrubbing lantai, sedot
 *   tungau kasur, dan fogging. Promonya baru masuk akal setelah layanannya ada.
 *
 * - DEEP30 (min Rp400.000) juga tidak dimasukkan: pesanan deep cleaning
 *   termurah pun sudah Rp625.000, jadi DEEP60 selalu lebih menguntungkan dan
 *   DEEP30 tidak akan pernah dipilih siapa pun.
 */

export interface PromoDeep {
  kode: string
  judul: string
  ringkas: string
  minTransaksi: number
  potongan: number
  /** Hanya untuk pesanan BisaBersih pertama pengguna. */
  penggunaBaru?: boolean
  syarat: string[]
}

export const PROMO_DEEP: PromoDeep[] = [
  {
    kode: 'DEEPBARU50',
    judul: 'Pertama kali Deep Cleaning',
    ringkas: 'Potongan Rp50.000 untuk pesanan BisaBersih pertama.',
    minTransaksi: 400_000,
    potongan: 50_000,
    penggunaBaru: true,
    syarat: [
      'Hanya untuk pesanan BisaBersih pertama dari akun ini.',
      'Berlaku untuk layanan Deep Cleaning.',
      'Maksimal satu voucher per transaksi.',
      'Tidak dapat digabung dengan promo lain.',
    ],
  },
  {
    kode: 'DEEP60',
    judul: 'Hemat Rp60.000',
    ringkas: 'Untuk transaksi mulai Rp600.000.',
    minTransaksi: 600_000,
    potongan: 60_000,
    syarat: [
      'Berlaku untuk semua pelanggan.',
      'Maksimal satu voucher per transaksi.',
      'Tidak berlaku untuk biaya parkir, material khusus, atau pekerjaan di luar scope.',
      'Tidak dapat digabung dengan promo lain.',
    ],
  },
  {
    kode: 'DEEP100',
    judul: 'Hemat Rp100.000',
    ringkas: 'Untuk area luas, transaksi mulai Rp1.000.000.',
    minTransaksi: 1_000_000,
    potongan: 100_000,
    syarat: [
      'Berlaku untuk semua pelanggan.',
      'Maksimal satu voucher per transaksi.',
      'Tidak berlaku untuk biaya parkir, material khusus, atau pekerjaan di luar scope.',
      'Tidak dapat digabung dengan promo lain.',
    ],
  },
  {
    kode: 'PINDAHBERSIH',
    judul: 'Pindahan & Pasca Renovasi',
    ringkas: 'Potongan Rp150.000 untuk transaksi mulai Rp1.000.000.',
    minTransaksi: 1_000_000,
    potongan: 150_000,
    syarat: [
      'Berlaku untuk paket Move-In dan Pasca Renovasi.',
      'TIDAK termasuk: angkut puing, noda semen berat, pekerjaan di ketinggian, angkut furnitur besar.',
      'Maksimal satu voucher per transaksi.',
      'Tidak dapat digabung dengan promo lain.',
    ],
  },
]

export interface HasilPromoDeep {
  potongan: number
  /** Kurang berapa lagi supaya promo bisa dipakai. Nol berarti sudah bisa. */
  kurang: number
  berlaku: boolean
}

export function cariPromoDeep(kode: string | null | undefined): PromoDeep | null {
  if (!kode) return null
  const k = kode.trim().toUpperCase()
  return PROMO_DEEP.find((p) => p.kode === k) ?? null
}

/**
 * Estimasi potongan untuk ditampilkan.
 *
 * Syarat "pengguna baru" TIDAK diperiksa di sini — browser tidak tahu riwayat
 * pesanan, dan menebaknya hanya akan menampilkan potongan yang kemudian ditolak
 * server. Itu diperiksa di server, dan alasannya dikembalikan apa adanya.
 */
export function hitungPromoDeep(promo: PromoDeep | null, nilaiTransaksi: number): HasilPromoDeep {
  if (!promo) return { potongan: 0, kurang: 0, berlaku: false }

  const kurang = Math.max(0, promo.minTransaksi - nilaiTransaksi)
  if (kurang > 0) return { potongan: 0, kurang, berlaku: false }

  return { potongan: Math.min(promo.potongan, nilaiTransaksi), kurang: 0, berlaku: true }
}
