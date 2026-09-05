/**
 * Katalog tampilan BisaKirim.
 *
 * Tidak ada satu pun angka ongkir di sini. Ongkir bergantung jarak jalan, dan
 * jarak jalan hanya server yang tahu — salinan tarif di klien akan menampilkan
 * angka yang berbeda dari yang ditagih.
 *
 * Yang ada di sini label: golongan ukuran paket, pernyataan isi yang dilarang,
 * dan kalimat tiap tahap.
 */

export interface PilihanKirim {
  kendaraan: 'motor' | 'mobil'
  label: string
  catatan: string
  estimasi: string
  maks_berat: number
  maks_sisi_cm: number
  km: number
  baris: { label: string; nilai: number }[]
  ongkir: number
  premi: number
  total: number
  sanggup: boolean
  alasan: string | null
  komisi: number
  promo: PromoKirim[]
  promo_terbaik: PromoKirim | null
  total_setelah_promo: number
}

export interface PromoKirim {
  kode: string
  nama: string
  jenis: 'akuisisi' | 'berulang'
  persen: number
  maks: number
  minimum: number
  deskripsi: string
  potongan: number
  bisa_dipakai: boolean
  alasan: string | null
}

export interface TitikKirim {
  alamat: string
  lat: number
  lng: number
  nama?: string
  telepon?: string
  catatan?: string | null
}

/** Golongan ukuran. Angkanya batas atas golongan, bukan berat paket persisnya. */
export const UKURAN = [
  { id: 'dokumen', label: 'Dokumen', berat: 1, sisi: 35, contoh: 'Surat, berkas, map' },
  { id: 'kecil', label: 'Kecil', berat: 5, sisi: 40, contoh: 'Kotak sepatu, makanan' },
  { id: 'sedang', label: 'Sedang', berat: 20, sisi: 50, contoh: 'Kardus sedang, galon' },
  { id: 'besar', label: 'Besar', berat: 100, sisi: 150, contoh: 'Kardus besar, sepeda lipat' },
]

/**
 * Isi kiriman yang tidak diterima.
 *
 * Ditampilkan sebagai pernyataan yang harus dibaca pengirim, bukan disembunyikan
 * di syarat dan ketentuan: yang tidak dibaca lebih dulu akan ditemukan kurir di
 * lokasi, dan saat itu semua orang sudah kehilangan waktu.
 */
export const DILARANG = [
  { id: 'uang-tunai', label: 'Uang tunai atau surat berharga' },
  { id: 'barang-mudah-meledak', label: 'Barang mudah meledak' },
  { id: 'cairan-mudah-terbakar', label: 'Cairan mudah terbakar' },
  { id: 'hewan-hidup', label: 'Hewan hidup' },
  { id: 'barang-terlarang', label: 'Barang yang dilarang undang-undang' },
]

/** Hal yang benar-benar bisa dilakukan layanan ini — tanpa klaim yang mengada. */
export const KEUNGGULAN = [
  {
    judul: 'Proteksi paket',
    isi: 'Ganti rugi sampai nilai barang yang kamu daftarkan.',
    ikon: 'shield',
  },
  {
    judul: 'Kode terima paket',
    isi: 'Kurir hanya menyerahkan ke orang yang tahu kodenya.',
    ikon: 'clipboard',
  },
  {
    judul: 'Ambil di tempat',
    isi: 'Kurir menjemput paketnya, kamu tidak perlu ke mana-mana.',
    ikon: 'motorcycle',
  },
]

export const TAHAP_KIRIM: Record<string, { judul: string; keterangan: string }> = {
  mencari: {
    judul: 'Mencari kurir terdekat',
    keterangan: 'Belum ada kurir yang ditugaskan. Kamu belum ditagih apa pun.',
  },
  menjemput: { judul: 'Kurir menuju titik ambil', keterangan: 'Siapkan paketnya, ya.' },
  diantar: { judul: 'Paket sedang diantar', keterangan: 'Kurir dalam perjalanan ke penerima.' },
  selesai: { judul: 'Paket sudah sampai', keterangan: 'Terima kasih sudah pakai BisaKirim.' },
}

export function rupiah(n: number): string {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}
