/**
 * Promo Servis AC — katalog tampilan.
 *
 * Salinan dari App\Services\PromoAC; yang menghitung potongan sesungguhnya
 * adalah server. ACPromoTest menjaga keduanya tetap sama.
 */

export interface PromoAC {
  kode: string
  judul: string
  ringkas: string
  minTransaksi: number
  /** Potongan nominal tetap. */
  potongan?: number
  /** Diskon persentase — selalu berpasangan dengan diskonMaks. */
  diskonPersen?: number
  diskonMaks?: number
  /** Jumlah unit minimum. */
  minUnit?: number
  /** Hanya untuk pesanan Servis AC pertama; diperiksa server dari riwayat. */
  penggunaBaru?: boolean
  /**
   * Layanan yang berhak memakainya.
   *
   * Tanpa penanda ini, kode pemeriksaan freon bisa dipasang pada pesanan cuci
   * AC — dan potongannya baru ditolak server setelah pesanan terbuat.
   */
  layanan?: 'cuci' | 'freon'
  syarat: string[]
}

export const PROMO_AC: PromoAC[] = [
  {
    kode: 'CEKAC20',
    judul: 'Pemeriksaan freon lebih murah',
    ringkas: 'Potongan Rp20.000 untuk pemeriksaan pertama.',
    minTransaksi: 50_000,
    potongan: 20_000,
    penggunaBaru: true,
    layanan: 'freon',
    syarat: [
      'Hanya untuk pemeriksaan Cek & Tambah Freon.',
      'Hanya untuk pesanan Servis AC pertama dari akun ini.',
      'Biaya pemeriksaan tetap dipotong dari total servis kalau pengerjaan dilanjutkan.',
      'Maksimal satu voucher per transaksi.',
    ],
  },
  {
    kode: 'ACBARU25',
    judul: 'Pertama kali servis AC',
    ringkas: 'Potongan Rp25.000 untuk pesanan Servis AC pertama.',
    minTransaksi: 100_000,
    potongan: 25_000,
    penggunaBaru: true,
    syarat: [
      'Hanya untuk pesanan Servis AC pertama dari akun ini.',
      'Berlaku untuk semua paket cuci AC.',
      'Maksimal satu voucher per transaksi.',
      'Tidak dapat digabung dengan promo lain.',
    ],
  },
  {
    kode: 'GERCEPAC',
    judul: 'Cuci AC diskon 20%',
    ringkas: 'Potongan 20%, maksimal Rp50.000.',
    minTransaksi: 100_000,
    diskonPersen: 20,
    diskonMaks: 50_000,
    syarat: [
      'Berlaku untuk semua paket cuci AC.',
      'Potongan dibatasi Rp50.000, berapa pun nilai transaksinya.',
      'Maksimal satu voucher per transaksi.',
    ],
  },
  {
    kode: 'ACHEMAT2',
    judul: 'Cuci 2 unit lebih hemat',
    ringkas: 'Potongan Rp30.000 untuk minimal 2 unit.',
    minTransaksi: 200_000,
    potongan: 30_000,
    minUnit: 2,
    syarat: [
      'Minimal 2 unit AC dalam satu kunjungan.',
      'Digabung dengan potongan bundling yang sudah otomatis.',
      'Maksimal satu voucher per transaksi.',
    ],
  },
  {
    kode: 'ACHEMAT3',
    judul: 'Cuci 3 unit sekaligus',
    ringkas: 'Potongan Rp50.000 untuk minimal 3 unit.',
    // 3 unit paket termurah bertagihan Rp280.000 setelah potongan bundling;
    // minimum yang lebih tinggi membuat promo ini tidak berlaku pada kasus
    // yang ia tuju.
    minTransaksi: 250_000,
    potongan: 50_000,
    minUnit: 3,
    syarat: [
      'Minimal 3 unit AC dalam satu kunjungan.',
      'Sudah termasuk bebas biaya kunjungan untuk 3 unit atau lebih.',
      'Maksimal satu voucher per transaksi.',
    ],
  },
]

export interface HasilPromoAC {
  potongan: number
  kurang: number
  berlaku: boolean
  /** Alasan kalau belum bisa dipakai. */
  alasan: string | null
}

export function cariPromoAC(kode: string | null | undefined): PromoAC | null {
  if (!kode) return null
  const k = kode.trim().toUpperCase()
  return PROMO_AC.find((p) => p.kode === k) ?? null
}

export function hitungPromoAC(
  promo: PromoAC | null,
  nilaiTransaksi: number,
  unit: number,
  layanan: 'cuci' | 'freon' = 'cuci',
): HasilPromoAC {
  if (!promo) return { potongan: 0, kurang: 0, berlaku: false, alasan: null }

  if (promo.layanan && promo.layanan !== layanan) {
    return {
      potongan: 0,
      kurang: 0,
      berlaku: false,
      alasan:
        promo.layanan === 'freon'
          ? 'Hanya untuk pemeriksaan freon'
          : 'Hanya untuk cuci AC',
    }
  }

  const kurang = Math.max(0, promo.minTransaksi - nilaiTransaksi)
  if (kurang > 0) {
    return { potongan: 0, kurang, berlaku: false, alasan: null }
  }

  if (promo.minUnit && unit < promo.minUnit) {
    return {
      potongan: 0,
      kurang: 0,
      berlaku: false,
      alasan: `Butuh minimal ${promo.minUnit} unit AC`,
    }
  }

  let potongan = promo.potongan ?? 0
  if (promo.diskonPersen) {
    const kasar = Math.round((nilaiTransaksi * promo.diskonPersen) / 100)
    potongan = Math.min(kasar, promo.diskonMaks ?? kasar)
  }

  return {
    potongan: Math.min(potongan, nilaiTransaksi),
    kurang: 0,
    berlaku: potongan > 0,
    alasan: null,
  }
}
