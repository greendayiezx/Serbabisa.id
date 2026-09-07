import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * BisaTukang — konsep pesanan dan tetapan tampilannya.
 *
 * KATALOGNYA TIDAK DI SINI. Kategori, jenis masalah, biaya kunjungan, dan
 * penawaran semuanya datang dari server (lihat src/api/tukang.ts). Berkas ini
 * dulu menyimpan salinannya sendiri lengkap dengan harga — yang berarti tarif
 * di layar tidak pernah bisa diperbarui tanpa merilis ulang aplikasi, dan dua
 * daftar yang sama pelan-pelan jadi berbeda isinya.
 *
 * Yang tinggal di sini hanya dua macam: teks dan ikon yang memang urusan
 * tampilan, dan isian formulir yang belum dikirim ke mana-mana.
 */

/**
 * Dua model kerja BisaTukang.
 *
 * Bedanya bukan besar kecilnya pekerjaan, melainkan CARA HARGANYA DITENTUKAN:
 * harian dihitung per jam/hari dan bisa langsung dipesan, borongan disurvei
 * dulu lalu diikat RAB. Menggabungkan keduanya jadi satu alur membuat salah
 * satunya selalu terasa salah — yang mau pasang lampu dipaksa menunggu survei,
 * yang mau renovasi total dikasih tarif per jam.
 */
export interface ModelKerja {
  id: 'harian' | 'borongan'
  nama: string
  lencana: string
  tempo: string
  ringkas: string
  cakupan: string[]
  ajakan: string
}

export const MODEL_KERJA: ModelKerja[] = [
  {
    id: 'harian',
    nama: 'Tukang Harian',
    lencana: 'Pesan instan',
    tempo: 'Perbaikan cepat, 1–2 jam',
    ringkas:
      'Pekerjaan cepat: pasang lampu, cat dinding, keramik lepas, ganti kran. Beres hitungan jam.',
    cakupan: [
      'Cat dinding, pintu, jendela, dan plafon',
      'Ganti aksesoris listrik & pipa bocor',
      'Bongkar-pasang keramik & kran',
    ],
    ajakan: 'Pesan tukang harian',
  },
  {
    id: 'borongan',
    nama: 'Borongan Full Service',
    lencana: 'Survei gratis',
    tempo: 'Proyek renovasi skala besar',
    ringkas:
      'Perbaikan bangunan secara borongan untuk rumah, kantor, ruko, dan apartemen.',
    cakupan: [
      'Survei + jasa + material + pengawasan',
      'Renovasi total & dak beton bocor',
      'RAB rinci sebelum dikerjakan',
    ],
    ajakan: 'Jadwalkan survei borongan',
  },
]

/** Tipe properti; memengaruhi akses dan lama pengerjaan, bukan tarif dasarnya. */
export const TIPE_PROPERTI = [
  { id: 'rumah', nama: 'Rumah tinggal', ikon: 'home' },
  { id: 'ruko', nama: 'Ruko/Toko', ikon: 'store' },
  { id: 'apartemen', nama: 'Apartemen/Kos', ikon: 'building' },
  { id: 'kantor', nama: 'Kantor', ikon: 'briefcase' },
] as const

export type TipePropertiId = (typeof TIPE_PROPERTI)[number]['id']

/**
 * Siapa yang menyediakan material.
 *
 * Ditanyakan di awal karena inilah yang paling sering jadi selisih paham di
 * akhir: pemesan mengira harga sudah termasuk barang, tukang mengira tidak.
 */
export const PENYEDIAAN_MATERIAL = [
  {
    id: 'pelanggan',
    nama: 'Material disiapkan pelanggan',
    ringkas: 'Kran, kabel, cat, atau keramiknya sudah kamu beli sendiri.',
  },
  {
    id: 'tukang',
    nama: 'Tukang yang membelikan',
    ringkas: 'Struk belanja ditunjukkan; harga material masuk rincian akhir.',
  },
] as const

export type PenyediaanMaterialId = (typeof PENYEDIAAN_MATERIAL)[number]['id']

/** Janji layanan yang membedakan BisaTukang; dipakai di beranda layanan. */
export const KEUNGGULAN_TUKANG = [
  {
    ikon: 'wallet',
    judul: 'Harga transparan',
    ringkas: 'Rincian biaya disetujui dulu, tidak ada tambahan diam-diam.',
  },
  {
    ikon: 'clipboard',
    judul: 'Bertanggung jawab',
    ringkas: 'Pengerjaan terjadwal dan kemajuannya dilaporkan.',
  },
  {
    ikon: 'shield',
    judul: 'Bergaransi',
    ringkas: 'Diperbaiki ulang gratis kalau hasilnya belum sesuai.',
  },
  {
    ikon: 'check-circle',
    judul: 'Tukang terverifikasi',
    ringkas: 'Uji keahlian dan pemeriksaan identitas sebelum diterima.',
  },
]

/**
 * CONTOH tukang pilihan — data mitra sungguhan belum ada endpoint-nya.
 *
 * Nama, rating, dan jumlah ulasan di sini karangan, sama seperti sisa alur
 * BisaTukang yang masih sepenuhnya di sisi klien. Harus diganti data server
 * sebelum layar ini dilihat pemakai sungguhan: profil mitra yang tidak ada
 * adalah bukti sosial palsu, bukan sekadar tempat sementara.
 */
export const TUKANG_PILIHAN = [
  {
    nama: 'Madrohim',
    keahlian: 'Keramik & dinding',
    tahun: 7,
    bintang: 4.95,
    ulasan: 142,
  },
  {
    nama: 'Suhendar',
    keahlian: 'Listrik & MCB',
    tahun: 10,
    bintang: 4.98,
    ulasan: 215,
  },
  {
    nama: 'Danang',
    keahlian: 'Atap & waterproofing',
    tahun: 5,
    bintang: 4.92,
    ulasan: 98,
  },
]

export const useTukangStore = defineStore('tukang', () => {
  // Isian formulir pemesanan; hidup sampai pesanannya terkirim.
  const modelKerja = ref<ModelKerja['id']>('harian')
  const selectedCategoryId = ref<string>('listrik')
  const selectedSubCategory = ref<string>('')
  const tipeProperti = ref<TipePropertiId>('rumah')
  const penyediaanMaterial = ref<PenyediaanMaterialId>('tukang')
  const lokasiMasalah = ref<string>('Dalam rumah')
  const deskripsiMasalah = ref<string>('')

  const jadwalTipe = ref<'secepatnya' | 'hari_ini' | 'besok'>('secepatnya')
  const jadwalJam = ref<string>('14:00-16:00')

  /**
   * Ganti kategori dari luar formulir (mis. dari beranda layanan).
   *
   * Jenis masalahnya DIKOSONGKAN, bukan ditebak: daftar sub-kategori datang
   * dari server, dan menebak isinya di sini berarti mengirim pilihan yang
   * dijamin ditolak. Layar pemesanan yang mengisinya begitu katalog sampai.
   */
  function setCategory(id: string) {
    selectedCategoryId.value = id
    selectedSubCategory.value = ''
  }

  return {
    modelKerja,
    selectedCategoryId,
    selectedSubCategory,
    tipeProperti,
    penyediaanMaterial,
    lokasiMasalah,
    deskripsiMasalah,
    jadwalTipe,
    jadwalJam,
    setCategory,
  }
})
