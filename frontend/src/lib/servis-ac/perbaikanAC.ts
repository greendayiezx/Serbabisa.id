/**
 * Katalog pilihan Perbaikan & Pasang AC — salinan tampilan dari
 * App\Services\PerbaikanTarif.
 *
 * Angka di sini hanya untuk MENAMPILKAN. Yang menagih tetap server, dan kalau
 * keduanya berbeda, yang benar adalah server — berkas ini yang harus menyusul.
 */

/** Kunjungan diagnosis; sama dengan pemeriksaan freon karena kerjanya sama. */
export const BIAYA_PEMERIKSAAN_PERBAIKAN = 50_000
export const BIAYA_UNIT_TAMBAHAN_PERBAIKAN = 25_000

/**
 * Pemasangan disebut sebagai RENTANG, bukan satu angka.
 *
 * Paket lengkap bergerak di antara keduanya tergantung panjang pipa, jalur
 * kabel, bracket, ketinggian, dan akses lokasi. Satu angka tunggal akan dibaca
 * sebagai harga pasti, dan penawaran yang datang kemudian terasa seperti harga
 * naik.
 */
export const PASANG_MULAI = 890_000
export const PASANG_SAMPAI = 1_500_000

export const KELUHAN_PERBAIKAN = [
  { id: 'tidak-dingin', nama: 'Tidak dingin' },
  { id: 'kurang-dingin', nama: 'Kurang dingin' },
  { id: 'bocor', nama: 'Bocor' },
  { id: 'berisik', nama: 'Berisik' },
  { id: 'mati-total', nama: 'Mati total' },
  { id: 'tidak-bisa-menyala', nama: 'Tidak bisa menyala' },
  { id: 'outdoor-tidak-berputar', nama: 'Outdoor tidak berputar' },
  { id: 'mengeluarkan-bau', nama: 'Mengeluarkan bau' },
  { id: 'kode-error', nama: 'Muncul kode error' },
  { id: 'lainnya', nama: 'Masalah lainnya' },
]

export const MULAI_TERJADI = [
  { id: 'hari-ini', nama: 'Hari ini' },
  { id: '1-7-hari', nama: '1–7 hari' },
  { id: 'lebih-1-minggu', nama: 'Lebih dari 1 minggu' },
  { id: 'tidak-tahu', nama: 'Tidak tahu' },
]

export const JENIS_PEKERJAAN = [
  { id: 'pasang-baru', nama: 'Pasang AC baru' },
  { id: 'bongkar-pasang', nama: 'Bongkar dan pasang kembali' },
  { id: 'pindah-lokasi', nama: 'Pindah lokasi AC', catatan: 'Selalu lewat survei lokasi.' },
  { id: 'ganti-unit', nama: 'Ganti unit AC lama' },
  { id: 'beberapa-unit', nama: 'Pasang beberapa unit', catatan: 'Selalu lewat survei lokasi.' },
]

/** Pekerjaan yang tidak bisa dinilai dari foto — server menaikkannya ke survei. */
export const WAJIB_SURVEI = ['pindah-lokasi', 'beberapa-unit']

export const KETERSEDIAAN_UNIT = [
  { id: 'sudah-ada', nama: 'Ya, unitnya sudah ada' },
  { id: 'butuh-rekomendasi', nama: 'Belum, butuh rekomendasi unit' },
]

export const KEBUTUHAN = [
  { id: 'jasa-saja', nama: 'Hanya jasa pemasangan' },
  { id: 'jasa-material', nama: 'Jasa + material' },
  { id: 'rekomendasi-unit', nama: 'Minta rekomendasi dan harga unit' },
]

export const LOKASI_INDOOR = [
  { id: 'kamar-tidur', nama: 'Kamar tidur' },
  { id: 'ruang-tamu', nama: 'Ruang tamu' },
  { id: 'ruang-kantor', nama: 'Ruang kantor' },
  { id: 'toko', nama: 'Toko' },
  { id: 'lainnya', nama: 'Lainnya' },
]

export const LOKASI_OUTDOOR = [
  { id: 'balkon', nama: 'Balkon' },
  { id: 'dinding-luar', nama: 'Dinding luar' },
  { id: 'atap', nama: 'Atap' },
  { id: 'lantai', nama: 'Lantai' },
  { id: 'area-khusus', nama: 'Area khusus' },
  { id: 'tidak-tahu', nama: 'Tidak tahu' },
]

export const MATERIAL_PASANG = [
  { id: 'pipa-tambahan', nama: 'Pipa tambahan' },
  { id: 'kabel-tambahan', nama: 'Kabel tambahan' },
  { id: 'bracket-outdoor', nama: 'Bracket outdoor' },
  { id: 'selang-pembuangan', nama: 'Selang pembuangan' },
  { id: 'stop-kontak', nama: 'Steker atau stop kontak' },
  { id: 'bobok-tembok', nama: 'Bobok tembok' },
  { id: 'penutup-jalur-pipa', nama: 'Penutup jalur pipa' },
  { id: 'tangga-alat-khusus', nama: 'Tangga atau alat khusus' },
]

export const CARA_PENAWARAN = [
  {
    id: 'estimasi-foto',
    nama: 'Estimasi berdasarkan foto',
    catatan: 'Paling cepat, untuk pemasangan yang sederhana.',
  },
  {
    id: 'survei-lokasi',
    nama: 'Survei lokasi gratis',
    catatan: 'Paling akurat, untuk jalur panjang atau lokasi sulit.',
  },
  { id: 'konsultasi', nama: 'Hubungi saya untuk konsultasi' },
]

export function biayaPemeriksaanPerbaikan(unit: number): number {
  const jumlah = Math.max(1, unit)
  return BIAYA_PEMERIKSAAN_PERBAIKAN + (jumlah - 1) * BIAYA_UNIT_TAMBAHAN_PERBAIKAN
}
