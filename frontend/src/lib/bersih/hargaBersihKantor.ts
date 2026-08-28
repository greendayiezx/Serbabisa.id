/**
 * Mesin harga BisaBersih — layanan Bersih Kantor.
 *
 * BEDA MENDASAR dengan Bersih Rumah: rumah ditagih per JAM per cleaner, kantor
 * ditagih per KUNJUNGAN berdasarkan luas area dan jumlah fasilitas. Karena itu
 * modulnya dipisah, bukan menumpang hitungHarga() di hargaBersih.ts — rumusnya
 * memang tidak sama.
 *
 * Angka di sini menghasilkan ESTIMASI, bukan tagihan final. Harga kantor pada
 * praktiknya dikunci setelah survei (luas sebenarnya, tingkat kekotoran, akses
 * gedung), jadi halaman kantor memakai alur "Minta Penawaran", bukan checkout
 * langsung.
 */

/* ---------------- Tarif dasar ---------------- */

/** Tarif per meter persegi per kunjungan. */
export const TARIF_PER_M2 = 1_200

/** Tagihan minimum sekali kunjungan — kru tetap harus berangkat. */
export const MINIMUM_KUNJUNGAN = 250_000

/** Tarif per unit fasilitas per kunjungan. */
export const TARIF_WORKSTATION = 3_000
export const TARIF_RUANG_MEETING = 25_000
export const TARIF_TOILET = 35_000
export const TARIF_PANTRY = 30_000

/** Lantai kedua dan seterusnya menambah waktu mobilisasi kru. */
export const TARIF_LANTAI_TAMBAHAN = 50_000

/**
 * Bagian biaya nyata dari harga layanan (upah kru + bahan pembersih).
 * Sisanya margin platform. Dipakai pemeriksa laba, tidak ditampilkan.
 */
export const RASIO_BIAYA_LAYANAN = 0.62

/* ---------------- Paket ---------------- */

export type PaketKantorId = 'basic' | 'professional' | 'executive'

/* ---------------- Jenis kantor ---------------- */

export type JenisKantorId = 'kecil' | 'sedang' | 'besar'

export interface JenisKantor {
  id: JenisKantorId
  nama: string
  /** Rentang luas yang diwakili, ditampilkan ke pengguna. */
  rentang: string
  catatan: string
  ikon: string
  /**
   * Luas yang dipakai menghitung ESTIMASI.
   *
   * Halaman tidak lagi menanyakan luas persisnya, jadi angka ini mewakili
   * rentangnya — batas atas untuk dua jenis pertama, dan angka wakil untuk
   * "di atas 150 m²" yang memang tidak berbatas. Karena itu hasilnya perkiraan,
   * bukan tagihan: luas sebenarnya diukur saat survei sebelum penawaran resmi.
   */
  luasAcuan: number
  unggulan?: boolean
}

export const JENIS_KANTOR: JenisKantor[] = [
  {
    id: 'kecil',
    nama: 'Small Office',
    rentang: 'Sampai 50 m²',
    catatan: 'Cocok untuk startup',
    ikon: 'business',
    luasAcuan: 50,
  },
  {
    id: 'sedang',
    nama: 'Medium Office',
    rentang: '51 – 150 m²',
    catatan: 'Paling banyak dipilih',
    ikon: 'grid',
    luasAcuan: 150,
    unggulan: true,
  },
  {
    id: 'besar',
    nama: 'Large Office',
    rentang: 'Di atas 150 m²',
    catatan: 'Cakupan satu lantai penuh',
    ikon: 'layers',
    luasAcuan: 250,
  },
]

export interface PaketKantor {
  id: PaketKantorId
  nama: string
  ikon: string
  ringkas: string
  /** Pengali tarif dasar: paket lebih tinggi = cakupan & standar lebih ketat. */
  pengali: number
  /** Rincian yang termasuk, ditampilkan saat paket dipilih. */
  termasuk: string[]
  unggulan?: boolean
}

export const PAKET_KANTOR: PaketKantor[] = [
  {
    id: 'basic',
    nama: 'Basic',
    ikon: 'sparkle',
    ringkas: 'Pembersihan umum, 1x seminggu.',
    pengali: 1,
    termasuk: [
      'Penyapuan & pengepelan area umum',
      'Pembersihan meja & permukaan kerja',
      'Pengosongan tempat sampah',
      'Pembersihan toilet dasar',
    ],
  },
  {
    id: 'professional',
    nama: 'Professional',
    ikon: 'check-circle',
    ringkas: 'Pembersihan lengkap, 2–5x seminggu, checklist digital, disinfektan.',
    pengali: 1.15,
    unggulan: true,
    termasuk: [
      'Penyapuan & pengepelan area umum',
      'Pembersihan meja & permukaan kerja',
      'Pengosongan tempat sampah',
      'Pembersihan toilet dasar',
      'Checklist digital & disinfektan',
    ],
  },
  {
    id: 'executive',
    nama: 'Executive',
    ikon: 'star',
    ringkas: 'Tim khusus, deep clean bulanan, supervisor, garansi re-clean.',
    pengali: 1.4,
    termasuk: [
      'Semua cakupan paket Professional',
      'Tim khusus & supervisor di lokasi',
      'Deep cleaning menyeluruh tiap bulan',
      'Garansi re-clean 24 jam',
      'Prioritas penjadwalan',
    ],
  },
]

/* ---------------- Frekuensi ---------------- */

export interface FrekuensiKantor {
  id: string
  label: string
  /** Diskon per kunjungan: makin sering, makin murah per kedatangan. */
  diskon: number
  /** Perkiraan kunjungan per bulan — untuk menghitung biaya bulanan. */
  kunjunganPerBulan: number
}

/**
 * Diskon langganan MELEKAT di harga, bukan kode promo terpisah.
 *
 * Angkanya sengaja sama persis dengan TABEL_LANGGANAN di promoBersihKantor.ts —
 * halaman promo hanya menjelaskan manfaat ini, tidak memberi kode tambahan.
 * Kalau dibuat sebagai kode, tagihan terpotong dua kali untuk manfaat yang sama.
 */
export const FREKUENSI_KANTOR: FrekuensiKantor[] = [
  { id: 'sekali', label: 'Sekali', diskon: 0, kunjunganPerBulan: 1 },
  { id: 'mingguan', label: '1x / Minggu', diskon: 0.08, kunjunganPerBulan: 4 },
  { id: '2x-minggu', label: '2x / Minggu', diskon: 0.1, kunjunganPerBulan: 8 },
  { id: '3x-minggu', label: '3x / Minggu', diskon: 0.12, kunjunganPerBulan: 12 },
  { id: 'harian', label: 'Setiap Hari Kerja', diskon: 0.15, kunjunganPerBulan: 22 },
]

/* ---------------- Layanan tambahan ---------------- */

export interface AddOnKantor {
  id: string
  nama: string
  deskripsi: string
  harga: number
  /** Biaya nyata mengerjakannya — untuk pemeriksa laba, tidak ditampilkan. */
  biaya: number
}

export const ADD_ON_KANTOR: AddOnKantor[] = [
  { id: 'kaca-gedung', nama: 'Cuci Kaca Gedung', deskripsi: 'Pembersihan kaca eksterior/interior', harga: 150_000, biaya: 85_000 },
  { id: 'karpet', nama: 'Cuci Karpet', deskripsi: 'Vakum dan sampo karpet kantor', harga: 120_000, biaya: 70_000 },
  { id: 'poles-lantai', nama: 'Poles Lantai', deskripsi: 'Marmer, granit, atau teraso', harga: 200_000, biaya: 120_000 },
  { id: 'high-dusting', nama: 'High Dusting', deskripsi: 'Pembersihan area tinggi & sulit dijangkau', harga: 100_000, biaya: 55_000 },
  { id: 'deep-toilet', nama: 'Deep Cleaning Toilet/Pantry', deskripsi: 'Pembersihan menyeluruh kerak & noda', harga: 150_000, biaya: 85_000 },
  { id: 'disinfeksi', nama: 'Disinfeksi Ruangan', deskripsi: 'Fogging disinfektan anti-virus', harga: 250_000, biaya: 140_000 },
]

/* ---------------- Perhitungan ---------------- */

export interface KonfigKantor {
  paketId: PaketKantorId
  luasM2: number
  jumlahLantai: number
  workstation: number
  ruangMeeting: number
  toilet: number
  pantry: number
  addOnDipilih: string[]
  frekuensiId: string
}

export interface RincianKantor {
  /** Harga jasa dasar (luas + fasilitas + lantai), sudah dikali paket. */
  layanan: number
  /** Ditagih karena hasil hitung di bawah tagihan minimum. */
  penyesuaianMinimum: number
  addOn: number
  subtotal: number
  diskonFrekuensi: number
  /** Estimasi tagihan sekali kedatangan. */
  totalPerKunjungan: number
  /** Estimasi tagihan sebulan pada frekuensi terpilih. */
  totalPerBulan: number
  /** Biaya nyata — untuk pemeriksa laba, tidak ditampilkan ke pengguna. */
  biaya: number
}

const cari = <T extends { id: string }>(daftar: T[], id: string, bawaan: T): T =>
  daftar.find((x) => x.id === id) ?? bawaan

export function hitungHargaKantor(k: KonfigKantor): RincianKantor {
  const paket = PAKET_KANTOR.find((p) => p.id === k.paketId) ?? PAKET_KANTOR[0]
  const frekuensi = cari(FREKUENSI_KANTOR, k.frekuensiId, FREKUENSI_KANTOR[0])

  const dariLuas = Math.max(0, k.luasM2) * TARIF_PER_M2
  const dariFasilitas =
    Math.max(0, k.workstation) * TARIF_WORKSTATION +
    Math.max(0, k.ruangMeeting) * TARIF_RUANG_MEETING +
    Math.max(0, k.toilet) * TARIF_TOILET +
    Math.max(0, k.pantry) * TARIF_PANTRY
  const dariLantai = Math.max(0, k.jumlahLantai - 1) * TARIF_LANTAI_TAMBAHAN

  const dasar = Math.round((dariLuas + dariFasilitas + dariLantai) * paket.pengali)

  // Tagihan minimum: kru tetap berangkat walau areanya kecil.
  const layanan = Math.max(dasar, MINIMUM_KUNJUNGAN)
  const penyesuaianMinimum = layanan - dasar

  const terpilih = ADD_ON_KANTOR.filter((a) => k.addOnDipilih.includes(a.id))
  const addOn = terpilih.reduce((s, a) => s + a.harga, 0)

  const subtotal = layanan + addOn
  const diskonFrekuensi = Math.round(layanan * frekuensi.diskon)
  const totalPerKunjungan = subtotal - diskonFrekuensi

  const biaya = Math.round(
    layanan * RASIO_BIAYA_LAYANAN + terpilih.reduce((s, a) => s + a.biaya, 0),
  )

  return {
    layanan,
    penyesuaianMinimum,
    addOn,
    subtotal,
    diskonFrekuensi,
    totalPerKunjungan,
    totalPerBulan: totalPerKunjungan * frekuensi.kunjunganPerBulan,
    biaya,
  }
}
