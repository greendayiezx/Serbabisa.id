import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface CategoryItem {
  id: string
  nama: string
  ikon: string
  deskripsi: string
  subKategori: string[]
  biayaKunjunganMin: number
  biayaKunjunganMax: number
  biayaPerbaikanMin: number
  biayaPerbaikanMax: number
}

export const KATEGORI_TUKANG: CategoryItem[] = [
  {
    id: 'listrik',
    nama: 'Listrik',
    ikon: 'bolt',
    deskripsi: 'Perbaikan instalasi listrik, stop kontak, MCB & pencahayaan',
    subKategori: [
      'Listrik mati total',
      'MCB sering turun',
      'Stop kontak rusak',
      'Instalasi lampu baru',
      'Kipas angin tidak menyala',
      'Water heater bermasalah',
      'Instalasi baru',
      'Pindah instalasi',
      'Ganti kabel',
      'Panel listrik',
      'Lainnya',
    ],
    biayaKunjunganMin: 30000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 100000,
    biayaPerbaikanMax: 350000,
  },
  {
    id: 'pipa',
    nama: 'Pipa & Plumbing',
    ikon: 'water_drop',
    deskripsi: 'Atasi kebocoran pipa, keran rusak & saluran mampet',
    subKategori: [
      'Pipa bocor',
      'Keran rusak',
      'Saluran mampet',
      'Toilet mampet',
      'Instalasi pipa baru',
      'Water heater bocor',
      'Tangki air bermasalah',
      'Pompa air rusak',
      'Saringan air',
      'Drainase',
      'Lainnya',
    ],
    biayaKunjunganMin: 30000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 120000,
    biayaPerbaikanMax: 400000,
  },
  {
    id: 'ac',
    nama: 'AC & Pendingin',
    ikon: 'ac_unit',
    deskripsi: 'Cuci AC, isi freon, bongkar pasang & servis rutin',
    subKategori: [
      'AC tidak dingin',
      'AC bocor',
      'AC berisik',
      'AC bau',
      'Cuci AC rutin',
      'Tambah freon',
      'Pasang AC baru',
      'Bongkar pasang AC',
      'Servis AC',
      'Ganti sparepart AC',
      'Lainnya',
    ],
    biayaKunjunganMin: 350000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 150000,
    biayaPerbaikanMax: 500000,
  },
  {
    id: 'bangunan',
    nama: 'Bangunan & Renovasi',
    ikon: 'hammer',
    deskripsi: 'Perbaikan atap bocor, tembok retak & plafon rusak',
    subKategori: [
      'Perbaikan atap bocor',
      'Perbaikan dinding retak',
      'Lantai rusak',
      'Plafon rusak',
      'Renovasi kamar',
      'Renovasi dapur',
      'Renovasi kamar mandi',
      'Tambah ruangan',
      'Ganti genteng',
      'Waterproofing',
      'Lainnya',
    ],
    biayaKunjunganMin: 50000,
    biayaKunjunganMax: 75000,
    biayaPerbaikanMin: 200000,
    biayaPerbaikanMax: 800000,
  },
  {
    id: 'pintu',
    nama: 'Pintu, Jendela, Kunci',
    ikon: 'door_front',
    deskripsi: 'Servis engsel, ganti silinder kunci & rolling door',
    subKategori: [
      'Pintu rusak',
      'Jendela rusak',
      'Kunci pintu rusak',
      'Ganti kunci',
      'Instalasi kunci baru',
      'Rolling door rusak',
      'Kusen rusak',
      'Engsel rusak',
      'Lainnya',
    ],
    biayaKunjunganMin: 30000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 100000,
    biayaPerbaikanMax: 300000,
  },
  {
    id: 'cat',
    nama: 'Cat & Dinding',
    ikon: 'format_paint',
    deskripsi: 'Pengecatan interior/eksterior, waterproofing & plamir',
    subKategori: [
      'Cat ulang ruangan',
      'Perbaikan dinding',
      'Tembok berjamur',
      'Cat eksterior',
      'Cat interior',
      'Wallpaper',
      'Plamir dinding',
      'Lainnya',
    ],
    biayaKunjunganMin: 30000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 150000,
    biayaPerbaikanMax: 450000,
  },
  {
    id: 'elektronik',
    nama: 'Elektronik Rumah Tangga',
    ikon: 'tv',
    deskripsi: 'Perbaikan kulkas, mesin cuci, TV & alat rumah tangga',
    subKategori: [
      'Kulkas tidak dingin',
      'Mesin cuci rusak',
      'TV tidak menyala',
      'Microwave rusak',
      'Rice cooker rusak',
      'Water heater rusak',
      'Kompor listrik rusak',
      'Lainnya',
    ],
    biayaKunjunganMin: 35000,
    biayaKunjunganMax: 60000,
    biayaPerbaikanMin: 120000,
    biayaPerbaikanMax: 400000,
  },
  {
    id: 'renovasi',
    nama: 'Renovasi Rumah',
    ikon: 'home_work',
    deskripsi: 'Renovasi fasad, garasi, teras & penambahan lantai',
    subKategori: [
      'Renovasi total',
      'Tambah lantai',
      'Renovasi fasad',
      'Renovasi taman',
      'Renovasi garasi',
      'Renovasi teras',
      'Lainnya',
    ],
    biayaKunjunganMin: 50000,
    biayaKunjunganMax: 100000,
    biayaPerbaikanMin: 300000,
    biayaPerbaikanMax: 1000000,
  },
  {
    id: 'lainnya',
    nama: 'Lainnya',
    ikon: 'build',
    deskripsi: 'Bantuan tukang serbabisa untuk berbagai perbaikan custom',
    subKategori: ['Perbaikan Custom', 'Bantuan Umum', 'Lainnya'],
    biayaKunjunganMin: 30000,
    biayaKunjunganMax: 50000,
    biayaPerbaikanMin: 100000,
    biayaPerbaikanMax: 300000,
  },
]

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
  // App Mode (Pelanggan / Sisi Tukang)
  const appMode = ref<'customer' | 'tukang'>('customer')

  // Customer Journey State
  const customerStep = ref<number>(1) // 1 to 13
  const selectedCategoryId = ref<string>('listrik')
  const selectedSubCategory = ref<string>('Stop kontak rusak')
  const deskripsiMasalah = ref<string>('')
  const uploadedPhotos = ref<string[]>([])
  const uploadedVideos = ref<string[]>([])
  const lokasiMasalah = ref<string>('Dalam rumah')
  const lokasiMasalahLainnya = ref<string>('')
  const kondisiAkses = ref<string>('Mudah dijangkau')

  // Model kerja, tipe properti, dan penyediaan material
  const modelKerja = ref<ModelKerja['id']>('harian')
  const tipeProperti = ref<TipePropertiId>('rumah')
  const penyediaanMaterial = ref<PenyediaanMaterialId>('tukang')

  // Schedule State
  const jadwalTipe = ref<'secepatnya' | 'hari_ini' | 'besok' | 'lainnya'>('secepatnya')
  const jadwalJam = ref<string>('14:00-16:00')
  const jadwalTanggal = ref<string>(new Date().toISOString().split('T')[0])

  // Matching & Tukang Info
  const tukangInfo = ref({
    nama: 'Ahmad Fauzi',
    rating: 4.8,
    jobsCount: 356,
    spesialis: 'Listrik & AC',
    phone: '0812-3456-7890',
    etaMinutes: 25,
    distanceKm: 2.3,
    statusMessage: 'Tukang sedang menuju kamu',
    badges: ['Terverifikasi', 'Berpengalaman', 'Top Rated'],
  })

  // Diagnosis Data
  const diagnosisData = ref({
    masalah: 'Stop kontak rusak dan MCB lemah',
    rekomendasi: ['Ganti stop kontak', 'Ganti MCB 6A', 'Jasa perbaikan'],
    materialItems: [
      { nama: 'Material stop kontak', harga: 50000 },
      { nama: 'Material MCB 6A', harga: 75000 },
    ],
    biayaKunjungan: 30000,
    jasaPerbaikan: 150000,
  })

  // Progress Checklists
  const progressChecklist = ref([
    { label: 'Diagnosis selesai', done: true },
    { label: 'Material disiapkan', done: true },
    { label: 'Perbaikan stop kontak', done: false },
    { label: 'Perbaikan MCB', done: false },
    { label: 'Testing', done: false },
  ])

  const testingChecklist = ref([
    { label: 'Stop kontak berfungsi', done: true },
    { label: 'MCB tidak turun lagi', done: true },
    { label: 'Listrik stabil', done: true },
  ])

  /*
   * Foto bukti pengerjaan dikirim tukang lewat aplikasinya, dan aplikasi itu
   * belum ada. Sebelumnya isinya dua foto Unsplash — ruangan milik orang lain
   * yang tampil sebagai bukti pekerjaan di rumah pemesan. Sampai fotonya benar
   * ada, layarnya menyediakan tempatnya dan menyebut bahwa fotonya belum ada.
   */
  const beforeAfterPhotos = ref<{ before: string | null; after: string | null }>({
    before: null,
    after: null,
  })

  const catatanTukang = ref(
    'Stop kontak dan MCB sudah diganti. Disarankan tidak menggunakan beban berlebih pada satu stop kontak.',
  )
  const garansiHari = ref<number>(30)

  // Voucher & Payment
  const promoCode = ref<string>('TUKANG20')
  const promoDiscount = ref<number>(30000)
  const paymentMethod = ref<'gopay' | 'transfer' | 'cc' | 'cash'>('gopay')
  const isPaid = ref<boolean>(false)

  // Review
  const ratingValue = ref<number>(5)
  const reviewComment = ref<string>('Pelayanan sangat cepat, tukang ramah dan rapi dalam bekerja!')
  const reviewTags = ref<string[]>([
    'Profesional',
    'Cepat',
    'Ramah',
    'Harga transparan',
    'Hasil rapi',
  ])
  const reviewSubmitted = ref<boolean>(false)
  const earnedPoints = ref<number>(150)

  // Tukang App Simulation State
  const tukangOnline = ref<boolean>(true)
  const tukangStatus = ref<'Available' | 'Busy' | 'Offline'>('Available')
  const hasIncomingOrder = ref<boolean>(false)
  const incomingTimer = ref<number>(30)
  const tukangStep = ref<
    | 'online'
    | 'order_request'
    | 'navigation'
    | 'arrival'
    | 'diagnosis'
    | 'customer_approval'
    | 'progress'
    | 'completion'
    | 'earnings'
  >('online')

  const tukangEarnings = ref({
    hariIni: 375000,
    mingguIni: 2150000,
    bulanIni: 8400000,
    avgRating: 4.8,
    bonus: 50000,
  })

  // Computed properties
  const currentCategory = computed(
    () =>
      KATEGORI_TUKANG.find((k) => k.id === selectedCategoryId.value) ?? KATEGORI_TUKANG[0],
  )

  const subTotal = computed(() => {
    const materialTotal = diagnosisData.value.materialItems.reduce(
      (sum, item) => sum + item.harga,
      0,
    )
    return (
      diagnosisData.value.biayaKunjungan +
      diagnosisData.value.jasaPerbaikan +
      materialTotal
    )
  })

  const grandTotal = computed(() => {
    return Math.max(0, subTotal.value - promoDiscount.value)
  })

  // Methods
  function setCategory(id: string) {
    selectedCategoryId.value = id
    const cat = KATEGORI_TUKANG.find((k) => k.id === id)
    if (cat && cat.subKategori.length > 0) {
      selectedSubCategory.value = cat.subKategori[0]
    }
  }

  function setSubCategory(sub: string) {
    selectedSubCategory.value = sub
  }

  function resetFlow() {
    customerStep.value = 1
    isPaid.value = false
    reviewSubmitted.value = false
  }

  return {
    appMode,
    customerStep,
    selectedCategoryId,
    selectedSubCategory,
    deskripsiMasalah,
    uploadedPhotos,
    uploadedVideos,
    lokasiMasalah,
    lokasiMasalahLainnya,
    kondisiAkses,
    modelKerja,
    tipeProperti,
    penyediaanMaterial,
    jadwalTipe,
    jadwalJam,
    jadwalTanggal,
    tukangInfo,
    diagnosisData,
    progressChecklist,
    testingChecklist,
    beforeAfterPhotos,
    catatanTukang,
    garansiHari,
    promoCode,
    promoDiscount,
    paymentMethod,
    isPaid,
    ratingValue,
    reviewComment,
    reviewTags,
    reviewSubmitted,
    earnedPoints,
    tukangOnline,
    tukangStatus,
    hasIncomingOrder,
    incomingTimer,
    tukangStep,
    tukangEarnings,
    currentCategory,
    subTotal,
    grandTotal,
    setCategory,
    setSubCategory,
    resetFlow,
  }
})
