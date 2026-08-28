/**
 * Katalog promo BisaBersih — Kantor.
 *
 * Dipisah dari promoBersih.ts (yang melayani rumah) karena mekanikanya berbeda:
 * kantor bermain di minimum transaksi bertingkat, kontrak, dan referral antar
 * perusahaan — bukan promo pengguna baru perorangan.
 *
 * Dua aturan yang dijaga di sini:
 *
 * 1. Setiap promo persentase WAJIB punya batas maksimal (`diskonMaks`). Tagihan
 *    kantor mengikuti luas dan frekuensi, jadi persentase tanpa batas bisa
 *    menembus margin pada kontrak besar.
 * 2. Diskon langganan TIDAK ada di daftar ini. Ia sudah menjadi bagian harga
 *    (lihat FREKUENSI_KANTOR di hargaBersihKantor.ts); menaruhnya lagi sebagai
 *    kode berarti memotong dua kali untuk manfaat yang sama.
 */

export type GrupPromoKantor =
  | 'akuisisi'
  | 'kontrak'
  | 'nilai-transaksi'
  | 'add-on'
  | 'referral'
  | 'musiman'

export interface VoucherKantor {
  id: string
  /** Kode yang dipakai saat memesan. */
  kode: string
  judul: string
  ringkas: string
  /** Minimal nilai transaksi sebelum promo berlaku. */
  minTransaksi: number
  /** Potongan nominal tetap. */
  potongan?: number
  /** Diskon persentase — selalu berpasangan dengan diskonMaks. */
  diskonPersen?: number
  /** Batas atas diskon persentase. Wajib ada kalau diskonPersen dipakai. */
  diskonMaks?: number
  /** Manfaat non-uang (bonus layanan), ditampilkan apa adanya. */
  bonus?: string
  /** Hadiah untuk perusahaan pengajak pada promo referral. */
  hadiahPengajak?: number
  periode?: string
  kuota?: number
  syarat: string[]
  /** Bagian dari empat promo peluncuran awal. */
  unggulan?: boolean
  /**
   * Hanya untuk pesanan BisaBersih PERTAMA pengguna.
   *
   * Diperiksa ulang di server (PromoKantor::penggunaBaru) dari riwayat pesanan,
   * bukan dari tanggal daftar akun - jadi pelanggan lama yang baru pertama kali
   * mencoba layanan kebersihan tetap dihitung baru.
   */
  penggunaBaru?: boolean
}

export interface GrupVoucherKantor {
  id: GrupPromoKantor
  judul: string
  /** Kenapa promo ini ada — membantu menimbang mana yang dipertahankan. */
  tujuan: string
  voucher: VoucherKantor[]
}

/* ------------------------------------------------------------------ *
 * 1. Akuisisi — menarik perusahaan mencoba pertama kali
 * ------------------------------------------------------------------ */
const AKUISISI: GrupVoucherKantor = {
  id: 'akuisisi',
  judul: 'Kantor Baru',
  tujuan: 'Menurunkan hambatan mencoba, dengan nilai potongan yang tetap terkendali.',
  voucher: [
    {
      id: 'bisabaru',
      kode: 'BISABARU',
      judul: 'Pertama kali pakai BisaBersih',
      ringkas: 'Diskon 15% untuk pesanan pertama, maksimal Rp100.000.',
      minTransaksi: 500_000,
      diskonPersen: 15,
      diskonMaks: 100_000,
      penggunaBaru: true,
      unggulan: true,
      syarat: [
        'Hanya untuk pesanan BisaBersih pertama dari akun ini.',
        'Berlaku untuk semua layanan BisaBersih, termasuk kantor.',
        'Potongan dibatasi Rp100.000, berapa pun nilai transaksinya.',
        'Tidak dapat digabung dengan promo lain.',
      ],
    },
    {
      id: 'kantorbaru',
      kode: 'KANTORBARU',
      judul: 'Kantor baru pakai BisaBersih',
      ringkas: 'Potongan langsung untuk pemesanan pertama perusahaan.',
      minTransaksi: 500_000,
      potongan: 100_000,
      penggunaBaru: true,
      unggulan: true,
      syarat: [
        'Berlaku untuk pelanggan bisnis baru.',
        'Maksimal satu kali penggunaan.',
        'Tidak dapat digabung dengan promo lain.',
        'Berlaku untuk paket Basic, Professional, atau deep cleaning.',
      ],
    },
    {
      id: 'tryoffic20',
      kode: 'TRYOFFIC20',
      judul: 'Trial Office Cleaning',
      ringkas: 'Coba dulu sebelum berlangganan — diskon 20%, dibatasi Rp150.000.',
      minTransaksi: 500_000,
      diskonPersen: 20,
      diskonMaks: 150_000,
      syarat: [
        'Sekali pakai per perusahaan.',
        'Batas potongan Rp150.000, berapa pun nilai transaksinya.',
        'Setelah pekerjaan selesai, tersedia penawaran lanjut ke paket bulanan.',
      ],
    },
    {
      id: 'newoffice15',
      kode: 'NEWOFFICE15',
      judul: 'Promo New Office',
      ringkas: 'Move in cleaning untuk kantor atau cabang baru.',
      minTransaksi: 500_000,
      diskonPersen: 15,
      diskonMaks: 300_000,
      syarat: [
        'Termasuk: debu ringan, lantai, kaca bagian dalam, pantry, toilet, meja & area kerja kosong.',
        'TIDAK termasuk: sisa semen berat, sisa cat atau lem, pekerjaan di ketinggian, angkut puing renovasi.',
        'Bukan layanan post-renovation cleaning berat.',
      ],
    },
  ],
}

/* ------------------------------------------------------------------ *
 * 2. Kontrak — pendapatan berulang tanpa diskon besar per transaksi
 * ------------------------------------------------------------------ */
const KONTRAK: GrupVoucherKantor = {
  id: 'kontrak',
  judul: 'Kontrak Berjangka',
  tujuan: 'Mengunci pendapatan beberapa bulan ke depan dengan potongan sedang.',
  voucher: [
    {
      id: 'office3bulan',
      kode: 'OFFICE3BULAN',
      judul: 'Kontrak 3 Bulan',
      ringkas: 'Diskon 10% setiap kunjungan, plus bonus deep cleaning.',
      minTransaksi: 1_000_000,
      diskonPersen: 10,
      diskonMaks: 250_000,
      bonus: 'Bonus 1x deep cleaning toilet atau pantry',
      unggulan: true,
      syarat: [
        'Kontrak minimal 3 bulan berjalan.',
        'Bonus deep cleaning diberikan sekali selama masa kontrak.',
        'Jadwal layanan dibuat tetap.',
      ],
    },
  ],
}

/* ------------------------------------------------------------------ *
 * 3. Nilai transaksi — mendorong paket yang lebih besar
 * ------------------------------------------------------------------ */
const NILAI_TRANSAKSI: GrupVoucherKantor = {
  id: 'nilai-transaksi',
  judul: 'Potongan Bertingkat',
  tujuan: 'Lebih mudah dikontrol daripada diskon merata untuk semua pesanan.',
  voucher: [
    {
      id: 'office50',
      kode: 'OFFICE50',
      judul: 'Potongan Rp50.000',
      ringkas: 'Untuk transaksi mulai Rp500.000.',
      minTransaksi: 500_000,
      potongan: 50_000,
      syarat: ['Maksimal satu voucher per invoice.', 'Berlaku untuk layanan utama.'],
    },
    {
      id: 'office100',
      kode: 'OFFICE100',
      judul: 'Potongan Rp100.000',
      ringkas: 'Untuk transaksi mulai Rp1.000.000.',
      minTransaksi: 1_000_000,
      potongan: 100_000,
      syarat: ['Maksimal satu voucher per invoice.', 'Berlaku untuk layanan utama.'],
    },
    /*
     * OFFICE200 (min Rp2.000.000) dan OFFICE500 (min Rp5.000.000) DIHAPUS.
     *
     * Tagihan tertinggi yang bisa dicapai mesin harga — Large Office, paket
     * Executive, dengan seluruh add-on sekaligus — hanya Rp2.160.000. OFFICE500
     * karena itu tidak pernah bisa dipakai, dan OFFICE200 hanya terjangkau pada
     * 4% kombinasi yang semuanya menuntut pelanggan memborong add-on.
     *
     * Promo yang tak terjangkau bukan promo: ia hanya memenuhi katalog dan
     * membuat pengguna bertanya-tanya. Kalau tarifnya naik kelak, keduanya bisa
     * dihidupkan lagi — scripts/cek-jangkauan-promo-kantor.ts yang memberi tahu.
     */
  ],
}

/* ------------------------------------------------------------------ *
 * 4. Add-on — menaikkan nilai pesanan tanpa memangkas layanan utama
 * ------------------------------------------------------------------ */
const ADD_ON: GrupVoucherKantor = {
  id: 'add-on',
  judul: 'Bundling Layanan Tambahan',
  tujuan: 'Menambah nilai pesanan tanpa menurunkan harga layanan utama.',
  voucher: [
    {
      id: 'sofavac',
      kode: 'SOFAVAC',
      judul: 'Professional + Vacuum Sofa',
      ringkas: 'Potongan Rp50.000 saat mengambil paket Professional.',
      minTransaksi: 500_000,
      potongan: 50_000,
      syarat: ['Wajib memilih paket Professional.', 'Berlaku sekali per invoice.'],
    },
    {
      id: 'pantrytoilet',
      kode: 'PANTRYTOILET',
      judul: 'Deep Cleaning Toilet + Pantry',
      ringkas: 'Harga bundling, hemat Rp100.000.',
      minTransaksi: 700_000,
      potongan: 100_000,
      syarat: ['Kedua add-on harus diambil bersamaan.', 'Berlaku sekali per invoice.'],
    },
  ],
}

/* ------------------------------------------------------------------ *
 * 5. Referral — tumbuh lewat rekomendasi antar perusahaan
 * ------------------------------------------------------------------ */
const REFERRAL: GrupVoucherKantor = {
  id: 'referral',
  judul: 'Referral Perusahaan',
  tujuan: 'Pertumbuhan lewat rekomendasi, dibayar hanya setelah transaksi selesai.',
  voucher: [
    {
      id: 'referalkantor',
      kode: 'REFERALKANTOR',
      judul: 'Ajak kantor lain, sama-sama dapat',
      ringkas: 'Perusahaan baru hemat Rp150.000, pengajak dapat voucher Rp150.000.',
      minTransaksi: 750_000,
      potongan: 150_000,
      hadiahPengajak: 150_000,
      unggulan: true,
      syarat: [
        'Perusahaan baru harus menyelesaikan transaksi minimal Rp750.000.',
        'Voucher pengajak cair setelah transaksi selesai dan tidak ada komplain aktif.',
        'Maksimal 5 referral berhasil per perusahaan per bulan.',
      ],
    },
  ],
}

/* ------------------------------------------------------------------ *
 * 6. Musiman — dibatasi kuota dan tanggal
 * ------------------------------------------------------------------ */
const MUSIMAN: GrupVoucherKantor = {
  id: 'musiman',
  judul: 'Promo Musiman',
  tujuan: 'Mengikuti kalender bisnis, dengan kuota supaya biayanya terukur.',
  voucher: [
    {
      id: 'merdekakantor',
      kode: 'MERDEKAKANTOR',
      judul: 'Merdeka Kantor',
      ringkas: 'Potongan Rp178.000 untuk transaksi mulai Rp1.000.000.',
      minTransaksi: 1_000_000,
      potongan: 178_000,
      periode: '1–31 Agustus',
      kuota: 50,
      syarat: ['Hanya untuk 50 kantor pertama.', 'Maksimal satu voucher per invoice.'],
    },
  ],
}

export const GRUP_VOUCHER_KANTOR: GrupVoucherKantor[] = [
  AKUISISI,
  KONTRAK,
  NILAI_TRANSAKSI,
  ADD_ON,
  REFERRAL,
  MUSIMAN,
]

/** Semua voucher dalam satu larik datar. */
export function semuaVoucherKantor(): VoucherKantor[] {
  return GRUP_VOUCHER_KANTOR.flatMap((g) => g.voucher)
}

export function cariVoucherKantor(id: string | null): VoucherKantor | null {
  if (!id) return null
  return semuaVoucherKantor().find((v) => v.id === id) ?? null
}

/* ------------------------------------------------------------------ *
 * Manfaat tanpa kode — ditampilkan sebagai informasi, bukan voucher
 * ------------------------------------------------------------------ */

/** Diskon langganan sudah menjadi bagian harga, bukan kode terpisah. */
export const TABEL_LANGGANAN = [
  { label: '1x / minggu', diskon: '8%' },
  { label: '2x / minggu', diskon: '10%' },
  { label: '3x / minggu', diskon: '12%' },
  { label: 'Setiap hari kerja', diskon: '15%' },
]

export const SYARAT_LANGGANAN = [
  'Minimal berlangganan 1 bulan.',
  'Jadwal layanan dibuat tetap.',
  'Perubahan jadwal maksimal H-1.',
  'Diskon tidak berlaku untuk add-on tertentu seperti poles lantai atau cuci karpet.',
]

export const SURVEI_GRATIS = {
  judul: 'Gratis Survey Kantor',
  ringkas: 'Untuk luas kantor mulai 100 m², tanpa biaya konsultasi.',
  manfaat: [
    'Rekomendasi jumlah cleaner.',
    'Estimasi durasi pengerjaan.',
    'Rincian area yang dikerjakan.',
    'Penawaran sesuai kebutuhan.',
  ],
}

export const PROGRAM_LOYALITAS = [
  'Transaksi ke-3: voucher Rp100.000.',
  'Transaksi ke-6: upgrade checklist layanan.',
  'Kontrak aktif: prioritas jadwal & harga add-on khusus.',
]

/* ------------------------------------------------------------------ *
 * Perhitungan
 * ------------------------------------------------------------------ */

export interface HasilPromoKantor {
  /** Potongan yang benar-benar berlaku pada nilai transaksi ini. */
  potongan: number
  /** Kurang berapa lagi supaya promo bisa dipakai. Nol berarti sudah bisa. */
  kurang: number
  berlaku: boolean
}

/**
 * Hitung potongan sebuah voucher terhadap nilai transaksi.
 *
 * Persentase selalu dijepit oleh `diskonMaks`, dan potongan tidak pernah
 * melebihi nilai transaksinya sendiri.
 */
export function hitungPromoKantor(
  voucher: VoucherKantor | null,
  nilaiTransaksi: number,
): HasilPromoKantor {
  if (!voucher) return { potongan: 0, kurang: 0, berlaku: false }

  const kurang = Math.max(0, voucher.minTransaksi - nilaiTransaksi)
  if (kurang > 0) return { potongan: 0, kurang, berlaku: false }

  let potongan = voucher.potongan ?? 0
  if (voucher.diskonPersen) {
    const kasar = Math.round((nilaiTransaksi * voucher.diskonPersen) / 100)
    potongan = Math.min(kasar, voucher.diskonMaks ?? kasar)
  }

  return { potongan: Math.min(potongan, nilaiTransaksi), kurang: 0, berlaku: potongan > 0 }
}
