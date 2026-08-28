/**
 * Harga BisaBersih Deep Cleaning — salinan tampilan dari App\Services\DeepTarif.
 *
 * Angka di sini hanya untuk MENAMPILKAN estimasi. Yang menagih tetap server:
 * checkout mengirim pilihan, bukan harga. Kalau keduanya berbeda, yang benar
 * adalah server — dan itu berarti berkas ini yang harus diperbarui.
 *
 * Aturan yang paling mudah terlewat: layanan tambahan yang SUDAH termasuk paket
 * tidak boleh ditagih lagi. Harga paketnya memang sudah dinaikkan sebesar
 * layanan itu, jadi menagihnya dua kali membuat pelanggan membayar dua kali
 * untuk pekerjaan yang sama.
 */

export type PaketDeepId = 'move_in' | 'pasca_renovasi' | 'sanitasi_total'
export type AddOnDeepId = 'scrubbing' | 'tungau' | 'fogging'

export interface PaketDeep {
  id: PaketDeepId
  nama: string
  deskripsi: string
  harga: number
  ikon: string
  /** Dua-tiga kata isi pekerjaannya, untuk dibandingkan sekilas. */
  tag: string[]
  /** Layanan tambahan yang sudah termasuk harga paket. */
  termasuk: AddOnDeepId[]
  /** Sorotan "paling sering dipilih" — hanya boleh satu paket. */
  sorot?: string
}

export interface AddOnDeep {
  id: AddOnDeepId
  nama: string
  harga: number
  satuan: string
  /** Ditagih per ruangan, bukan sekali per pesanan. */
  perRuangan: boolean
  ikon: string
}

/** Lingkup yang sudah termasuk harga paket. */
export const LUAS_TERMASUK = 50
export const RUANGAN_TERMASUK = 3

/** Kelebihan di atas lingkup standar. */
export const TARIF_LUAS = 3_000
export const TARIF_RUANGAN = 25_000

export const PAKET_DEEP: PaketDeep[] = [
  {
    id: 'move_in',
    nama: 'Paket Move-In',
    deskripsi: 'Untuk rumah baru/pindahan. Fokus pada debu halus dan sanitasi.',
    // 550.000 + sedot tungau kasur (75.000)
    harga: 625_000,
    ikon: 'home_work',
    tag: ['Sanitasi Lemari', 'Sisa Cat/Semen'],
    termasuk: ['tungau'],
    sorot: 'Paling Populer',
  },
  {
    id: 'pasca_renovasi',
    nama: 'Paket Pasca Renovasi',
    deskripsi: 'Pembersihan sisa semen, cat, dan debu konstruksi.',
    // 750.000 + scrubbing lantai mesin untuk 3 ruangan (3 × 50.000)
    harga: 900_000,
    ikon: 'construction',
    tag: ['Debu Konstruksi', 'Poles Lantai'],
    termasuk: ['scrubbing'],
  },
  {
    id: 'sanitasi_total',
    nama: 'Paket Sanitasi Total',
    deskripsi: 'Fokus pada pembasmian bakteri dan tungau.',
    // 600.000 + fogging (100.000) + sedot tungau kasur (75.000)
    harga: 775_000,
    ikon: 'sanitizer',
    tag: ['Fogging', 'Anti Tungau'],
    termasuk: ['fogging', 'tungau'],
  },
]

export const ADD_ON_DEEP: AddOnDeep[] = [
  {
    id: 'scrubbing',
    nama: 'Scrubbing Lantai Mesin',
    harga: 50_000,
    satuan: 'ruangan',
    perRuangan: true,
    ikon: 'cleaning_services',
  },
  { id: 'tungau', nama: 'Sedot Tungau Kasur', harga: 75_000, satuan: 'kasur', perRuangan: false, ikon: 'bed' },
  { id: 'fogging', nama: 'Fogging Disinfektan', harga: 100_000, satuan: 'rumah', perRuangan: false, ikon: 'sanitizer' },
]

export function cariPaketDeep(id: string | null | undefined): PaketDeep {
  return PAKET_DEEP.find((p) => p.id === id) ?? PAKET_DEEP[0]
}

export interface BarisHargaDeep {
  label: string
  nilai: number
}

export interface RincianDeep {
  baris: BarisHargaDeep[]
  hargaPaket: number
  biayaLuas: number
  biayaRuangan: number
  addOn: number
  total: number
}

/**
 * Rincian estimasi. Total DITURUNKAN dari daftar barisnya, bukan dihitung
 * terpisah — dua perhitungan paralel untuk angka yang sama adalah cara paling
 * gampang membuat rincian dan total saling berbeda.
 */
export function hitungHargaDeep(opsi: {
  paketId: string
  luasM2: number
  jumlahRuangan: number
  addOn: AddOnDeepId[]
}): RincianDeep {
  const paket = cariPaketDeep(opsi.paketId)
  const luas = Math.max(1, opsi.luasM2 || 0)
  const ruangan = Math.max(1, opsi.jumlahRuangan || 0)

  const baris: BarisHargaDeep[] = [{ label: paket.nama, nilai: paket.harga }]

  const lebihLuas = Math.max(0, luas - LUAS_TERMASUK)
  const biayaLuas = lebihLuas * TARIF_LUAS
  if (biayaLuas) baris.push({ label: `Kelebihan luas ${lebihLuas} m²`, nilai: biayaLuas })

  const lebihRuangan = Math.max(0, ruangan - RUANGAN_TERMASUK)
  const biayaRuangan = lebihRuangan * TARIF_RUANGAN
  if (biayaRuangan) baris.push({ label: `Ruangan tambahan ${lebihRuangan}`, nilai: biayaRuangan })

  let addOn = 0
  for (const id of dipakai(paket, opsi.addOn)) {
    const a = ADD_ON_DEEP.find((x) => x.id === id)
    if (!a) continue
    const qty = a.perRuangan ? ruangan : 1
    const nilai = a.harga * qty
    addOn += nilai
    baris.push({ label: qty > 1 ? `${a.nama} × ${qty} ${a.satuan}` : a.nama, nilai })
  }

  return {
    baris,
    hargaPaket: paket.harga,
    biayaLuas,
    biayaRuangan,
    addOn,
    total: baris.reduce((jml, b) => jml + b.nilai, 0),
  }
}

/** Add-on yang benar-benar ditagih: yang dipilih, dikurangi yang sudah termasuk. */
export function dipakai(paket: PaketDeep, dipilih: AddOnDeepId[]): AddOnDeepId[] {
  return dipilih.filter((id) => !paket.termasuk.includes(id))
}
