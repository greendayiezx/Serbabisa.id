/**
 * Katalog promo BisaBelanja + perhitungan potongannya.
 *
 * Promo ditulis deklaratif (syarat sebagai data, bukan cabang if) supaya
 * penambahan promo baru tidak menyentuh logika hitung, dan tiap aturan bisa
 * diuji sendiri tanpa merender halaman. Ini aturan uang — salah sedikit berarti
 * salah tagih.
 */

export interface KonteksPromo {
  totalBarang: number
  biayaLayanan: number
  ongkir: number
  /** Transaksi ke berapa (1 = belanja pertama). */
  transaksiKe: number
  /** Waktu pemesanan — dipakai promo Jam Emas, Payday, dan Weekend. */
  waktu: Date
  /** Subtotal per kategori keranjang, untuk promo Bundle. */
  subtotalKategori: Record<string, number>
}

export interface HasilPromo {
  berlaku: boolean
  /** Kenapa belum bisa dipakai. */
  alasan?: string
  /** Kekurangan belanja menuju syarat minimum (0 kalau syaratnya bukan nominal). */
  kurang: number
  potonganBarang: number
  potonganOngkir: number
  /** Cashback untuk transaksi berikutnya — TIDAK memotong tagihan sekarang. */
  cashback: number
}

const NOL: HasilPromo = {
  berlaku: false,
  kurang: 0,
  potonganBarang: 0,
  potonganOngkir: 0,
  cashback: 0,
}

export type GrupPromo =
  | 'tier'
  | 'first-order'
  | 'gratis-ongkir'
  | 'jam-emas'
  | 'payday'
  | 'weekend'
  | 'bundle'
  | 'cashback'
  | 'referral'

export interface Promo {
  id: string
  kode?: string
  judul: string
  ringkas: string
  detail: string[]
  emoji: string
  grup: GrupPromo
  /** Minimal total harga barang. */
  minBelanja: number
  potonganTetap?: number
  gratisOngkir?: boolean
  cashbackPersen?: number
  cashbackMaks?: number
  /** Kuota harian, hanya untuk ditampilkan — belum ada penghitung sisa kuota. */
  kuota?: number
  /**
   * Syarat non-nominal (waktu, tanggal, status user, isi keranjang).
   * Balikan null berarti lolos; string berarti alasan gagal.
   */
  syarat?: (ctx: KonteksPromo) => string | null
  /** Minimal per kategori untuk promo Bundle: kategori-kategori yang dijumlah. */
  kategoriBundle?: string[]
}

export const rp = (n: number) => 'Rp' + n.toLocaleString('id-ID')

/* ---------------- Syarat yang dipakai berulang ---------------- */

export function jamEmasSlot(waktu: Date): 'siang' | 'malam' | null {
  const j = waktu.getHours()
  if (j >= 12 && j < 14) return 'siang'
  if (j >= 18 && j < 20) return 'malam'
  return null
}

/** Payday: 25–27, 28–30, dan 31–1 (tanggal 31 atau tanggal 1). */
function periodePayday(waktu: Date): '25-27' | '28-30' | '31-1' | null {
  const t = waktu.getDate()
  if (t >= 25 && t <= 27) return '25-27'
  if (t >= 28 && t <= 30) return '28-30'
  if (t === 31 || t === 1) return '31-1'
  return null
}

const akhirPekan = (waktu: Date) => waktu.getDay() === 0 || waktu.getDay() === 6

/* ---------------- Katalog promo ---------------- */

export const PROMO: Promo[] = [
  // 1. Diskon bertingkat — tiap nilai dipatok di bawah promoMaksimal() tingkatnya
  { id: 'belanja5', kode: 'BELANJA2', judul: 'Diskon Rp2.000', ringkas: 'Minimal belanja Rp50.000', detail: ['Potongan langsung Rp2.000', 'Berlaku untuk semua kategori'], emoji: '🎫', grup: 'tier', minBelanja: 50000, potonganTetap: 2000 },
  { id: 'belanja10', kode: 'BELANJA3', judul: 'Diskon Rp3.000', ringkas: 'Minimal belanja Rp100.000', detail: ['Potongan langsung Rp3.000', 'Berlaku untuk semua kategori'], emoji: '🎫', grup: 'tier', minBelanja: 100000, potonganTetap: 3000 },
  { id: 'belanja25', kode: 'BELANJA5', judul: 'Diskon Rp5.000', ringkas: 'Minimal belanja Rp200.000', detail: ['Potongan langsung Rp5.000', 'Berlaku untuk semua kategori'], emoji: '🎫', grup: 'tier', minBelanja: 200000, potonganTetap: 5000 },
  { id: 'belanja75', kode: 'BELANJA12', judul: 'Diskon Rp12.000', ringkas: 'Minimal belanja Rp500.000', detail: ['Potongan langsung Rp12.000', 'Untuk belanja borongan'], emoji: '🎫', grup: 'tier', minBelanja: 500000, potonganTetap: 12000 },

  // 10. Pengguna baru
  { id: 'new10', kode: 'NEW2', judul: 'Pengguna Baru: Diskon Rp2.000', ringkas: 'Minimal belanja Rp50.000', detail: ['Khusus transaksi pertama', 'Potongan langsung Rp2.000'], emoji: '🌟', grup: 'first-order', minBelanja: 50000, potonganTetap: 2000, syarat: (c) => (c.transaksiKe === 1 ? null : 'Khusus transaksi pertama') },
  { id: 'new25', kode: 'NEW3', judul: 'Pengguna Baru: Diskon Rp3.000', ringkas: 'Minimal belanja Rp100.000', detail: ['Khusus transaksi pertama', 'Potongan langsung Rp3.000'], emoji: '🌟', grup: 'first-order', minBelanja: 100000, potonganTetap: 3000, syarat: (c) => (c.transaksiKe === 1 ? null : 'Khusus transaksi pertama') },
  { id: 'new40', kode: 'NEW4', judul: 'Pengguna Baru: Diskon Rp4.000', ringkas: 'Minimal belanja Rp150.000', detail: ['Khusus transaksi pertama', 'Potongan langsung Rp4.000'], emoji: '🌟', grup: 'first-order', minBelanja: 150000, potonganTetap: 4000, syarat: (c) => (c.transaksiKe === 1 ? null : 'Khusus transaksi pertama') },

  // 7. Gratis ongkir — minimumnya dinaikkan karena ongkir Rp3.500 sendiri sudah
  // melebihi plafon promo untuk keranjang Rp100.000.
  { id: 'gratis5', kode: 'GRATISONGKIR', judul: 'Gratis Ongkir', ringkas: 'Minimal belanja Rp150.000', detail: ['Ongkir gratis untuk jarak 0–5 km'], emoji: '🚚', grup: 'gratis-ongkir', minBelanja: 150000, gratisOngkir: true },
  { id: 'gratis10', kode: 'GRATIS2', judul: 'Gratis Ongkir + Diskon Rp2.000', ringkas: 'Minimal belanja Rp250.000', detail: ['Ongkir gratis', 'Plus potongan Rp2.000'], emoji: '🚚', grup: 'gratis-ongkir', minBelanja: 250000, potonganTetap: 2000, gratisOngkir: true },
  { id: 'gratis20', kode: 'GRATIS3', judul: 'Gratis Ongkir + Diskon Rp3.000', ringkas: 'Minimal belanja Rp400.000', detail: ['Ongkir gratis', 'Plus potongan Rp3.000'], emoji: '🚚', grup: 'gratis-ongkir', minBelanja: 400000, potonganTetap: 3000, gratisOngkir: true },

  // 2. Jam emas
  { id: 'jam-emas-siang', judul: 'Jam Emas Siang: Diskon Rp3.000', ringkas: 'Jam 12.00–14.00, minimal belanja Rp100.000', detail: ['Potongan Rp3.000', 'Hanya jam 12.00–14.00 WIB', 'Kuota 500 pengguna per hari'], emoji: '⚡', grup: 'jam-emas', minBelanja: 100000, potonganTetap: 3000, kuota: 500, syarat: (c) => (jamEmasSlot(c.waktu) === 'siang' ? null : 'Berlaku jam 12.00–14.00') },
  { id: 'jam-emas-malam', judul: 'Jam Emas Malam: Diskon Rp4.000', ringkas: 'Jam 18.00–20.00, minimal belanja Rp150.000', detail: ['Potongan Rp4.000', 'Hanya jam 18.00–20.00 WIB', 'Kuota 300 pengguna per hari'], emoji: '⚡', grup: 'jam-emas', minBelanja: 150000, potonganTetap: 4000, kuota: 300, syarat: (c) => (jamEmasSlot(c.waktu) === 'malam' ? null : 'Berlaku jam 18.00–20.00') },

  // 8. Payday
  { id: 'payday-1', judul: 'Payday: Diskon Rp4.000', ringkas: 'Tanggal 25–27, minimal belanja Rp150.000', detail: ['Potongan Rp4.000', 'Berlaku tanggal 25–27'], emoji: '💸', grup: 'payday', minBelanja: 150000, potonganTetap: 4000, syarat: (c) => (periodePayday(c.waktu) === '25-27' ? null : 'Berlaku tanggal 25–27') },
  { id: 'payday-2', judul: 'Payday: Diskon Rp5.000', ringkas: 'Tanggal 28–30, minimal belanja Rp200.000', detail: ['Potongan Rp5.000', 'Berlaku tanggal 28–30'], emoji: '💸', grup: 'payday', minBelanja: 200000, potonganTetap: 5000, syarat: (c) => (periodePayday(c.waktu) === '28-30' ? null : 'Berlaku tanggal 28–30') },
  { id: 'payday-3', judul: 'Payday: Diskon Rp7.000', ringkas: 'Tanggal 31 & 1, minimal belanja Rp300.000', detail: ['Potongan Rp7.000', 'Berlaku tanggal 31 dan 1'], emoji: '💸', grup: 'payday', minBelanja: 300000, potonganTetap: 7000, syarat: (c) => (periodePayday(c.waktu) === '31-1' ? null : 'Berlaku tanggal 31 & 1') },

  // 9. Weekend
  { id: 'weekend-15', judul: 'Weekend Super: Diskon Rp2.500', ringkas: 'Sabtu–Minggu, minimal belanja Rp120.000', detail: ['Potongan Rp2.500', 'Hanya Sabtu & Minggu', 'Kuota 1.000 pengguna'], emoji: '🎉', grup: 'weekend', minBelanja: 120000, potonganTetap: 2500, kuota: 1000, syarat: (c) => (akhirPekan(c.waktu) ? null : 'Hanya Sabtu & Minggu') },
  { id: 'weekend-35', judul: 'Weekend Super: Diskon Rp6.000', ringkas: 'Sabtu–Minggu, minimal belanja Rp250.000', detail: ['Potongan Rp6.000', 'Hanya Sabtu & Minggu', 'Kuota 500 pengguna'], emoji: '🎉', grup: 'weekend', minBelanja: 250000, potonganTetap: 6000, kuota: 500, syarat: (c) => (akhirPekan(c.waktu) ? null : 'Hanya Sabtu & Minggu') },

  // 5. Bundle per kategori
  { id: 'bundle-fresh', judul: 'Bundle Fresh: Diskon Rp1.500', ringkas: 'Belanja buah/sayur/daging/seafood minimal Rp75.000', detail: ['Dihitung dari kategori Buah, Sayur, Daging, dan Seafood', 'Potongan Rp1.500'], emoji: '🥬', grup: 'bundle', minBelanja: 75000, potonganTetap: 1500, kategoriBundle: ['buah', 'sayur', 'daging', 'seafood'] },
  { id: 'bundle-grocery', judul: 'Bundle Sembako: Diskon Rp3.000', ringkas: 'Belanja sembako minimal Rp100.000', detail: ['Dihitung dari kategori Bahan Pokok, Telur, Bumbu, dan Susu', 'Potongan Rp3.000'], emoji: '🥛', grup: 'bundle', minBelanja: 100000, potonganTetap: 3000, kategoriBundle: ['pokok', 'telur', 'bumbu', 'susu'] },
  { id: 'bundle-lengkap', judul: 'Belanja Lengkap: Diskon Rp5.000', ringkas: 'Dari 3 kategori berbeda, minimal total Rp200.000', detail: ['Isi keranjang harus dari minimal 3 kategori berbeda', 'Minimal total belanja Rp200.000', 'Potongan Rp5.000'], emoji: '🎁', grup: 'bundle', minBelanja: 200000, potonganTetap: 5000, syarat: (c) => (Object.keys(c.subtotalKategori).length >= 3 ? null : 'Perlu barang dari minimal 3 kategori') },

  // 6. Cashback bertingkat — masuk saldo untuk transaksi berikutnya
  { id: 'cashback5', judul: 'Cashback 2%', ringkas: 'Minimal belanja Rp100.000, maks Rp3.000', detail: ['Cashback 2%, maksimal Rp3.000', 'Masuk ke saldo untuk transaksi berikutnya', 'Berlaku 7 hari'], emoji: '💰', grup: 'cashback', minBelanja: 100000, cashbackPersen: 2, cashbackMaks: 3000 },
  { id: 'cashback7', judul: 'Cashback 2,5%', ringkas: 'Minimal belanja Rp200.000, maks Rp6.000', detail: ['Cashback 2,5%, maksimal Rp6.000', 'Masuk ke saldo untuk transaksi berikutnya', 'Berlaku 14 hari'], emoji: '💰', grup: 'cashback', minBelanja: 200000, cashbackPersen: 2.5, cashbackMaks: 6000 },
  { id: 'cashback10', judul: 'Cashback 3%', ringkas: 'Minimal belanja Rp500.000, maks Rp12.000', detail: ['Cashback 3%, maksimal Rp12.000', 'Masuk ke saldo untuk transaksi berikutnya', 'Berlaku 30 hari'], emoji: '💰', grup: 'cashback', minBelanja: 500000, cashbackPersen: 3, cashbackMaks: 12000 },

  // 4. Referral
  { id: 'traktiran', kode: 'TRAKTIR3', judul: 'Traktiran Teman: Diskon Rp3.000', ringkas: 'Pakai kode teman, minimal belanja Rp200.000', detail: ['Potongan Rp3.000 untuk transaksi pertamamu', 'Yang mengundang dapat cashback Rp2.000 setelah pesanan selesai', 'Minimal belanja Rp200.000'], emoji: '🤝', grup: 'referral', minBelanja: 200000, potonganTetap: 3000, syarat: (c) => (c.transaksiKe === 1 ? null : 'Khusus transaksi pertama') },
]

export const LABEL_GRUP: Record<GrupPromo, string> = {
  tier: 'Diskon Bertingkat',
  'first-order': 'Pengguna Baru',
  'gratis-ongkir': 'Gratis Ongkir + Diskon',
  'jam-emas': 'Flash Sale Jam Emas',
  payday: 'Payday Sale',
  weekend: 'Weekend Super',
  bundle: 'Bundle Hemat',
  cashback: 'Cashback',
  referral: 'Traktiran Teman',
}

/* ---------------- Langganan ---------------- */

export interface Langganan {
  id: 'basic' | 'premium' | 'family'
  nama: string
  harga: number
  /**
   * Potongan NOMINAL per transaksi, bukan persen.
   *
   * Diskon persen dulunya dipakai dan itu keliru secara ekonomi: pendapatan
   * platform adalah markup tetap per item (~Rp1.327), sedangkan diskon persen
   * naik mengikuti nilai keranjang. Akibatnya pelanggan yang paling loyal
   * justru paling merugikan — Family 12x/bulan rugi Rp123.400.
   */
  diskonPerTransaksi: number
  /** Batas berapa kali diskon itu diberikan dalam sebulan. */
  kuotaDiskon: number
  /** Minimal belanja agar ongkir gratis. 0 = tanpa minimum. */
  minOngkirGratis: number
  /** Jatah gratis ongkir per bulan. Selalu berkuota supaya biayanya berbatas. */
  kuotaOngkir: number
  benefit: string[]
}

export const LANGGANAN: Langganan[] = [
  {
    id: 'basic',
    nama: 'Basic',
    harga: 29000,
    diskonPerTransaksi: 0,
    kuotaDiskon: 0,
    minOngkirGratis: 100000,
    kuotaOngkir: 12,
    benefit: [
      'Gratis ongkir 12x/bulan (min. belanja Rp100.000)',
      'Tanpa biaya tambahan di jam sibuk',
    ],
  },
  {
    id: 'premium',
    nama: 'Premium',
    harga: 49000,
    diskonPerTransaksi: 1000,
    kuotaDiskon: 20,
    minOngkirGratis: 75000,
    kuotaOngkir: 20,
    benefit: [
      'Gratis ongkir 20x/bulan (min. belanja Rp75.000)',
      'Potongan Rp1.000 tiap transaksi, sampai 20x/bulan',
      'Prioritas shopper: pesanan dikerjakan lebih dulu',
    ],
  },
  {
    id: 'family',
    nama: 'Family',
    harga: 79000,
    diskonPerTransaksi: 1500,
    kuotaDiskon: 30,
    minOngkirGratis: 0,
    kuotaOngkir: 30,
    benefit: [
      'Gratis ongkir 30x/bulan, tanpa minimum belanja',
      'Potongan Rp1.500 tiap transaksi, sampai 30x/bulan',
      'Dedicated shopper',
    ],
  },
]

/* ---------------- Perhitungan ---------------- */

/** Dasar belanja yang dipakai promo: total keranjang, atau subtotal kategori untuk Bundle. */
export function dasarBelanja(p: Promo, ctx: KonteksPromo): number {
  if (!p.kategoriBundle) return ctx.totalBarang
  return p.kategoriBundle.reduce((s, k) => s + (ctx.subtotalKategori[k] ?? 0), 0)
}

export function hitungPromo(p: Promo, ctx: KonteksPromo): HasilPromo {
  const gagal = p.syarat?.(ctx)
  if (gagal) return { ...NOL, alasan: gagal }

  const dasar = dasarBelanja(p, ctx)
  if (dasar < p.minBelanja) {
    const kurang = p.minBelanja - dasar
    return { ...NOL, kurang, alasan: `Belanja ${rp(kurang)} lagi` }
  }

  return {
    berlaku: true,
    kurang: 0,
    // Potongan tidak boleh melebihi harga barangnya sendiri.
    potonganBarang: Math.min(p.potonganTetap ?? 0, ctx.totalBarang),
    potonganOngkir: p.gratisOngkir ? ctx.ongkir : 0,
    cashback: p.cashbackPersen
      ? Math.min(Math.round((dasar * p.cashbackPersen) / 100), p.cashbackMaks ?? Infinity)
      : 0,
  }
}

/** Total yang benar-benar memotong tagihan (cashback tidak termasuk). */
export function totalHemat(h: HasilPromo): number {
  return h.potonganBarang + h.potonganOngkir
}

export interface PromoBerikutnya {
  promo: Promo
  kurang: number
  /** Nilai yang didapat kalau syaratnya tercapai. */
  nilai: number
}

/**
 * Promo terdekat yang tinggal kurang nominal belanja — dipakai untuk strip
 * "Belanja Rpxx lagi, ada diskon Rpyy!". Promo yang gagal karena syarat waktu
 * atau status user tidak ikut, karena belanja lebih banyak tidak akan membukanya.
 */
export function promoBerikutnya(ctx: KonteksPromo, kecuali?: string | null): PromoBerikutnya | null {
  let terbaik: PromoBerikutnya | null = null
  for (const p of PROMO) {
    if (p.id === kecuali) continue
    if (p.syarat?.(ctx)) continue
    const dasar = dasarBelanja(p, ctx)
    if (dasar >= p.minBelanja) continue
    const kurang = p.minBelanja - dasar
    const nilai =
      (p.potonganTetap ?? 0) +
      (p.gratisOngkir ? ctx.ongkir : 0) +
      (p.cashbackPersen
        ? Math.min(Math.round((p.minBelanja * p.cashbackPersen) / 100), p.cashbackMaks ?? Infinity)
        : 0)
    if (!terbaik || kurang < terbaik.kurang || (kurang === terbaik.kurang && nilai > terbaik.nilai)) {
      terbaik = { promo: p, kurang, nilai }
    }
  }
  return terbaik
}
