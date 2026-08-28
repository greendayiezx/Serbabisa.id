/**
 * Mesin harga BisaBersih — layanan Bersih Rumah.
 *
 * Semua angka di halaman pemesanan berasal dari sini, bukan ditulis di
 * template, supaya scripts/cek-laba-bersih.ts bisa memvonis apakah kombinasi
 * termurah masih menyisakan laba setelah promo.
 */


/**
 * Tarif dasar bila cleaner belum dipilih.
 *
 * Pesanan tanpa pilihan cleaner dihargai pada level TERENDAH: pengguna tidak
 * boleh ditagih tarif cleaner senior untuk penugasan yang belum tentu jatuh ke
 * mereka. Nilai sebenarnya datang dari server (`harga_terendah_per_jam`);
 * angka ini hanya bekal awal sebelum daftar cleaner tiba.
 */
export const TARIF_TERENDAH_PER_JAM = 50_000

/** Bagian platform dari tiap jam kerja — satu-satunya sumber laba jasa ini. */
export const MARKUP_PER_JAM = 10_000

/** Ongkos datang ke lokasi. Ditagih sekali per pesanan. */
export const BIAYA_PERJALANAN = 20_000

/** Bagian ongkos perjalanan yang benar-benar keluar dari kas. */
export const RASIO_BIAYA_PERJALANAN = 0.75

/**
 * Harga per jam yang dibayar customer di tiap level cleaner.
 *
 * SUMBER KEBENARANNYA ada di server (App\Services\LevelCleaner); daftar ini
 * salinan supaya `npm run cek:laba` bisa jalan tanpa menyalakan backend.
 * BersihCheckoutTest menjaga keduanya tetap sama — kalau tarif di server
 * diubah tanpa mengubah daftar ini, test itu gagal.
 */
export const HARGA_PER_JAM_LEVEL = [50_000, 60_000, 70_000, 80_000]

/**
 * Tarif tertinggi = kasus TERBURUK bagi laba.
 *
 * Untuk nilai transaksi yang sama, tarif per jam yang lebih tinggi berarti jam
 * kerjanya lebih sedikit, dan markup platform (per jam) ikut mengecil. Jadi
 * pemeriksaan promo memakai angka ini, bukan tarif termurah.
 */
export const HARGA_PER_JAM_TERTINGGI = Math.max(...HARGA_PER_JAM_LEVEL)

/** Bonus sekali bayar tiap cleaner naik satu tingkat. */
export const BONUS_NAIK_LEVEL = 100_000

/* ---------------- Jenis layanan ---------------- */
export interface JenisLayanan {
  id: string
  nama: string
  deskripsi: string
  /** Pengali tarif: deep cleaning lebih berat per jamnya. */
  pengali: number
  unggulan?: boolean
}

/**
 * Jenis layanan (General / Deep / Move In-Out) dihapus dari halaman pemesanan.
 *
 * Harga kini ditentukan level cleaner, bukan jenis pekerjaan, sehingga pengali
 * layanan tidak lagi punya tempat dalam rumus.
 */

/* ---------------- Properti & kondisi ---------------- */
export const TIPE_PROPERTI = ['Rumah', 'Apartemen', 'Kos', 'Kantor'] as const

export interface Kondisi {
  id: string
  label: string
  /** Ruangan yang lebih kotor makan waktu & tenaga lebih banyak. */
  pengali: number
}

export const KONDISI: Kondisi[] = [
  { id: 'normal', label: 'Normal', pengali: 1 },
  { id: 'cukup', label: 'Cukup Kotor', pengali: 1.15 },
  { id: 'sangat', label: 'Sangat Kotor', pengali: 1.3 },
]

/* ---------------- Layanan tambahan ---------------- */
export interface AddOn {
  id: string
  nama: string
  harga: number
  /** Biaya nyata mengerjakannya — dipakai pemeriksa laba, bukan ditampilkan. */
  biaya: number
}

export const ADD_ON: AddOn[] = [
  { id: 'kaca', nama: 'Cuci Kaca', harga: 30_000, biaya: 16_000 },
  { id: 'sofa', nama: 'Vacuum Sofa', harga: 50_000, biaya: 26_000 },
  { id: 'kulkas', nama: 'Bersihkan Kulkas', harga: 35_000, biaya: 18_000 },
]

/* ---------------- Frekuensi ---------------- */
export interface Frekuensi {
  id: string
  label: string
  /** Diskon berulang, bukan paket langganan bulanan. */
  diskon: number
}

export const FREKUENSI: Frekuensi[] = [
  { id: 'sekali', label: 'Sekali panggil', diskon: 0 },
  { id: 'mingguan', label: 'Mingguan', diskon: 0.15 },
  { id: 'bulanan', label: 'Bulanan', diskon: 0.05 },
]

/* ---------------- Durasi ---------------- */
export const PILIHAN_DURASI = [2, 3, 4]

/**
 * Durasi yang disarankan.
 *
 * Dulu dihitung dari jumlah kamar dan luas bangunan. Halaman pemesanan tidak
 * lagi menanyakan itu, jadi sarannya jadi angka tetap: 3 jam cukup untuk rumah
 * ukuran umum. Kalau detail properti dikembalikan, hitungannya bisa dihidupkan
 * lagi dari riwayat git.
 */
export const DURASI_REKOMENDASI = 3

/* ---------------- Cleaner ---------------- */
/**
 * Daftar cleaner TIDAK ada di berkas ini.
 *
 * Level, rating, dan jumlah order harus berasal dari data nyata — ulasan yang
 * benar-benar diberikan customer. Daftar contoh apa pun di sini akan selalu
 * berbeda dari kenyataan begitu ada mitra sungguhan. Ambil lewat
 * `ambilCleaner()` di api/cleaner.ts; tipenya ada di sana (CleanerServer).
 */

/* ---------------- Perhitungan ---------------- */
export interface KonfigBersih {
  /**
   * Harga per jam cleaner yang dipilih — SUDAH termasuk markup platform.
   * Datang dari server (`harga_per_jam`), bukan dihitung ulang di browser.
   */
  hargaPerJam: number
  kondisiId: string
  durasiJam: number
  jumlahCleaner: number
  addOnDipilih: string[]
  frekuensiId: string
}

export interface RincianHarga {
  layanan: number
  /** Bagian cleaner dari harga jasa. */
  upahCleaner: number
  /** Bagian platform — satu-satunya laba dari jasa ini. */
  markupPlatform: number
  addOn: number
  perjalanan: number
  subtotal: number
  diskonFrekuensi: number
  /** Nilai yang dipakai menguji syarat minimum promo. */
  nilaiTransaksi: number
  potonganPromo: number
  total: number
  /** Biaya nyata — untuk pemeriksa laba, tidak ditampilkan ke pengguna. */
  biaya: number
}

const cari = <T extends { id: string }>(daftar: T[], id: string, bawaan: T): T =>
  daftar.find((x) => x.id === id) ?? bawaan

export function hitungHarga(k: KonfigBersih, potonganPromo = 0): RincianHarga {
  const kondisi = cari(KONDISI, k.kondisiId, KONDISI[0])
  const frekuensi = cari(FREKUENSI, k.frekuensiId, FREKUENSI[0])

  const jamOrang = k.durasiJam * k.jumlahCleaner
  const hargaLayanan = Math.round(k.hargaPerJam * jamOrang * kondisi.pengali)
  const markupPlatform = MARKUP_PER_JAM * jamOrang
  const upahCleaner = hargaLayanan - markupPlatform
  const hargaAddOn = ADD_ON.filter((a) => k.addOnDipilih.includes(a.id)).reduce((s, a) => s + a.harga, 0)

  const subtotal = hargaLayanan + hargaAddOn + BIAYA_PERJALANAN
  const diskonFrekuensi = Math.round(hargaLayanan * frekuensi.diskon)
  const nilaiTransaksi = subtotal - diskonFrekuensi
  const potongan = Math.min(potonganPromo, nilaiTransaksi)

  // Biaya nyata platform: upah yang diteruskan ke cleaner, biaya tiap add-on,
  // dan sebagian ongkos perjalanan.
  const biayaCleaner = upahCleaner
  const biayaAddOn = ADD_ON.filter((a) => k.addOnDipilih.includes(a.id)).reduce((s, a) => s + a.biaya, 0)
  const biayaPerjalanan = BIAYA_PERJALANAN * 0.75

  return {
    layanan: hargaLayanan,
    upahCleaner,
    markupPlatform,
    addOn: hargaAddOn,
    perjalanan: BIAYA_PERJALANAN,
    subtotal,
    diskonFrekuensi,
    nilaiTransaksi,
    potonganPromo: potongan,
    total: nilaiTransaksi - potongan,
    biaya: Math.round(biayaCleaner + biayaAddOn + biayaPerjalanan),
  }
}
