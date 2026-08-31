/**
 * Katalog Disinfektan — salinan tampilan dari App\Services\DisinfektanTarif.
 *
 * Angka di sini hanya untuk MENAMPILKAN estimasi; yang menagih tetap server.
 * Kalau keduanya berbeda, yang benar adalah server, dan berkas ini yang harus
 * menyusul.
 *
 * Dua hal yang tidak ada di katalog ini, dan memang tidak boleh ada: satu angka
 * waktu kontak, dan janji "bebas virus". Waktu kontak mengikuti label tiap
 * produk, dan disinfeksi permukaan bukan sterilisasi.
 */

export const PROPERTI = [
  { id: 'rumah', nama: 'Rumah' },
  { id: 'apartemen', nama: 'Apartemen' },
  { id: 'kos', nama: 'Kos' },
  { id: 'kantor', nama: 'Kantor' },
  { id: 'toko', nama: 'Toko / ruko' },
]

export const PROPERTI_USAHA = ['kantor', 'toko']

export const LUAS = [
  { id: '<50', nama: '< 50 m²', catatan: 'Kecil' },
  { id: '50-100', nama: '50–100 m²', catatan: 'Sedang' },
  { id: '101-300', nama: '101–300 m²', catatan: 'Besar' },
  { id: '>300', nama: '> 300 m²', catatan: 'Lewat penawaran' },
]

/** Di atas ini harganya lewat penawaran, bukan harga pasang. */
export const LUAS_PENAWARAN = '>300'

export const KONDISI = [
  { id: 'normal', nama: 'Kondisi normal', catatan: 'Perawatan pencegahan.' },
  { id: 'banyak-orang', nama: 'Banyak orang beraktivitas', catatan: 'Sering dilalui tamu atau pelanggan.' },
  { id: 'setelah-acara', nama: 'Setelah acara', catatan: 'Permukaan tersentuh jauh lebih banyak orang.' },
  { id: 'setelah-sakit', nama: 'Setelah ada yang sakit', catatan: 'Penanganan lebih menyeluruh.' },
  { id: 'sangat-kotor', nama: 'Area sangat kotor', catatan: 'Butuh pembersihan awal lebih lama.' },
]

export const PERHATIAN = [
  { id: 'anak-kecil', nama: 'Ada anak kecil atau bayi' },
  { id: 'hewan-peliharaan', nama: 'Ada hewan peliharaan' },
  { id: 'alergi-bau', nama: 'Ada yang alergi atau sensitif bau' },
  { id: 'makanan-terbuka', nama: 'Ada makanan atau minuman terbuka' },
  { id: 'elektronik-sensitif', nama: 'Ada elektronik sensitif' },
]

/**
 * Penanda yang membuat pesanan DITOLAK, bukan ditambah biayanya.
 *
 * Ditaruh terpisah dari PERHATIAN supaya layar tidak menampilkannya sebagai
 * pilihan setara — jawabannya bukan harga yang lebih tinggi, melainkan
 * penyedia yang berbeda.
 */
export const PERHATIAN_DITOLAK = {
  id: 'cairan-tubuh-berisiko',
  nama: 'Ada darah, cairan tubuh, atau limbah berisiko',
}

export const RUANGAN_TERMASUK = 3
export const TOILET_TERMASUK = 1
export const TARIF_RUANGAN_TAMBAHAN = 20_000
export const TARIF_TOILET_TAMBAHAN = 25_000

export const DASAR: Record<string, Record<string, number>> = {
  hunian: { '<50': 120_000, '50-100': 150_000, '101-300': 300_000 },
  usaha: { '<50': 200_000, '50-100': 250_000, '101-300': 350_000 },
}

export const KONDISI_TAMBAHAN: Record<string, number> = {
  normal: 0,
  'banyak-orang': 30_000,
  'setelah-acara': 30_000,
  'setelah-sakit': 60_000,
  'sangat-kotor': 60_000,
}

export const MULAI_DARI = DASAR.hunian['50-100']
export const MULAI_DARI_KANTOR = DASAR.usaha['101-300']

export const AREA: Record<string, string[]> = {
  hunian: [
    'Gagang pintu',
    'Sakelar lampu',
    'Meja dan kursi',
    'Remote',
    'Pegangan tangga',
    'Permukaan kamar mandi',
    'Dapur',
    'Area ruang keluarga',
  ],
  usaha: [
    'Meja kerja',
    'Keyboard dan mouse',
    'Gagang pintu',
    'Tombol lift',
    'Sakelar',
    'Meja meeting dan kursi',
    'Dispenser dan pantry',
    'Toilet dan resepsionis',
  ],
}

export const TIDAK_TERMASUK = [
  'Membersihkan jamur berat pada dinding',
  'Menghilangkan bau permanen',
  'Membersihkan darah atau cairan tubuh berisiko tinggi',
  'Menangani area medis',
  'Menyemprot makanan atau minuman',
  'Menjamin ruangan bebas virus setelah layanan selesai',
  'Pengasapan ruangan rutin',
]

export const LANGKAH = [
  'Area diperiksa',
  'Permukaan dibersihkan lebih dulu',
  'Permukaan yang sering disentuh ditentukan',
  'Disinfektan diaplikasikan',
  'Waktu kontak dipenuhi sesuai label produk',
  'Area diberi ventilasi',
  'Petugas melakukan pengecekan',
  'Laporan pekerjaan dikirim',
]

export const SEBELUM_PETUGAS_DATANG = [
  'Anak-anak dan hewan peliharaan dipindahkan sementara',
  'Makanan dan minuman disimpan atau ditutup',
  'Beri tahu kalau ada alergi atau sensitivitas bau',
  'Pastikan area punya ventilasi yang cukup',
  'Tunjukkan permukaan elektronik kepada petugas',
  'Kembali ke area setelah waktu aman yang disebutkan petugas',
]

export const APD_PETUGAS = [
  'Sarung tangan',
  'Masker sesuai kebutuhan produk',
  'Pelindung mata bila ada risiko percikan',
  'Pakaian kerja',
]

export function golongan(properti: string): 'hunian' | 'usaha' {
  return PROPERTI_USAHA.includes(properti) ? 'usaha' : 'hunian'
}

export interface RincianDisinfektan {
  baris: { label: string; nilai: number }[]
  total: number
}

export function hitungDisinfektan(
  properti: string,
  luas: string,
  ruangan: number,
  toilet: number,
  kondisi: string,
): RincianDisinfektan {
  const dasar = DASAR[golongan(properti)]?.[luas] ?? 0

  const ruanganTambahan = Math.max(0, ruangan - RUANGAN_TERMASUK)
  const toiletTambahan = Math.max(0, toilet - TOILET_TERMASUK)
  const tambahanKondisi = KONDISI_TAMBAHAN[kondisi] ?? 0

  const baris = [{ label: `Disinfektan ${luas} m²`, nilai: dasar }]

  if (ruanganTambahan > 0) {
    baris.push({
      label: `Ruangan tambahan (${ruanganTambahan})`,
      nilai: ruanganTambahan * TARIF_RUANGAN_TAMBAHAN,
    })
  }
  if (toiletTambahan > 0) {
    baris.push({
      label: `Toilet tambahan (${toiletTambahan})`,
      nilai: toiletTambahan * TARIF_TOILET_TAMBAHAN,
    })
  }
  if (tambahanKondisi > 0) {
    baris.push({
      label: KONDISI.find((k) => k.id === kondisi)?.nama ?? kondisi,
      nilai: tambahanKondisi,
    })
  }

  return { baris, total: baris.reduce((n, b) => n + b.nilai, 0) }
}
