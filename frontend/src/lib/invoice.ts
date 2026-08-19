/**
 * Penomoran invoice & pesanan Serbabisa.
 *
 * Format: SBB-260818-7K4M2X
 *   SBB      Serbabisa BisaBelanja (layanan lain nanti pakai kode sendiri)
 *   260818   tanggal YYMMDD
 *   7K4M2X   5 karakter acak + 1 karakter checksum
 *
 * Sengaja dibuat pendek dan bisa dibacakan lewat telepon, bukan deret angka
 * panjang bergaris miring. Alfabetnya Crockford Base32 tanpa I, L, O, dan U —
 * huruf yang gampang tertukar dengan 1/0 saat dibaca atau diketik ulang, dan U
 * dibuang supaya tidak membentuk kata kasar tanpa sengaja.
 *
 * Karakter terakhir adalah checksum, jadi satu digit yang salah ketik langsung
 * ketahuan tanpa perlu memanggil server.
 */

const ALFABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ' // 32 karakter, tanpa I L O U
const PANJANG_ACAK = 6

/** Checksum sederhana berbobot posisi, hasilnya satu karakter dari ALFABET. */
function checksum(inti: string): string {
  let jumlah = 0
  for (let i = 0; i < inti.length; i++) {
    const nilai = ALFABET.indexOf(inti[i])
    // Karakter yang tidak dikenal (mis. '-') dilewati supaya tanggal ikut terhitung
    // lewat digitnya saja.
    if (nilai >= 0) jumlah += nilai * (i + 1)
  }
  return ALFABET[jumlah % ALFABET.length]
}

function acakBase32(panjang: number): string {
  let keluar = ''
  const buf = new Uint8Array(panjang)
  // crypto tersedia di semua browser target; Math.random dipakai hanya sebagai
  // cadangan agar kode ini tetap jalan di lingkungan uji tanpa Web Crypto.
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    crypto.getRandomValues(buf)
    for (const b of buf) keluar += ALFABET[b % ALFABET.length]
  } else {
    for (let i = 0; i < panjang; i++) {
      keluar += ALFABET[Math.floor(Math.random() * ALFABET.length)]
    }
  }
  return keluar
}

function tanggalPadat(d: Date): string {
  const yy = String(d.getFullYear()).slice(2)
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yy}${mm}${dd}`
}

export interface NomorPesanan {
  /** Nomor invoice lengkap, mis. SBB-260818-7K4M2X */
  invoice: string
  /** Bagian unik tanpa prefix & tanggal — dipakai di URL dan judul halaman. */
  pesanan: string
}

/**
 * Terbitkan nomor pesanan.
 *
 * TIDAK dipakai lagi untuk membuat pesanan sungguhan — sejak checkout tersambung
 * ke Laravel, nomor diterbitkan server (AppServicesNomorInvoice) supaya ada
 * satu otoritas yang menjamin keunikannya. Dipertahankan untuk pratinjau,
 * pengujian, dan sebagai pasangan referensi algoritma checksum di sisi PHP.
 */
export function buatNomorPesanan(waktu = new Date()): NomorPesanan {
  const tanggal = tanggalPadat(waktu)
  const acak = acakBase32(PANJANG_ACAK)
  const kode = acak + checksum(tanggal + acak)
  return { invoice: `SBB-${tanggal}-${kode}`, pesanan: kode }
}

/** Periksa checksum sebuah nomor invoice. */
export function invoiceValid(invoice: string): boolean {
  const m = /^SBB-(\d{6})-([0-9A-Z]{7})$/.exec(invoice.trim().toUpperCase())
  if (!m) return false
  const [, tanggal, kode] = m
  return checksum(tanggal + kode.slice(0, PANJANG_ACAK)) === kode[PANJANG_ACAK]
}

/** "18 Agustus 2026, 02.18" */
export function formatTanggalInvoice(iso: string): string {
  const d = new Date(iso)
  const bulan = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
  ]
  const jam = String(d.getHours()).padStart(2, '0')
  const menit = String(d.getMinutes()).padStart(2, '0')
  return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}, ${jam}.${menit}`
}

/** "02:43 - 03:03" — rentang estimasi tiba. */
export function rentangJam(mulaiIso: string, selesaiIso: string): string {
  const j = (iso: string) => {
    const d = new Date(iso)
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
  }
  return `${j(mulaiIso)} - ${j(selesaiIso)}`
}
