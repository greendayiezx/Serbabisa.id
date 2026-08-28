/**
 * Pemeriksa promo BisaBersih Kantor.
 *
 * Menguji tiap voucher pada contoh nilai transaksi yang relevan, lalu memastikan:
 * - promo persentase tidak pernah melewati batas maksimalnya;
 * - potongan tidak pernah melebihi nilai transaksi;
 * - promo di bawah minimum transaksi benar-benar tidak memotong.
 *
 * Keluar dengan kode 1 kalau ada yang melanggar. Jalankan: npm run cek:promo:kantor
 */
import {
  GRUP_VOUCHER_KANTOR,
  hitungPromoKantor,
  semuaVoucherKantor,
} from '../src/lib/promo/promoBersihKantor'

const rp = (n: number) => 'Rp' + n.toLocaleString('id-ID')

let gagal = 0

function periksa(nama: string, lulus: boolean, pesan: string) {
  if (!lulus) {
    gagal++
    console.error(`  GAGAL  ${nama}: ${pesan}`)
  }
}

console.log('=== Promo BisaBersih Kantor ===\n')

for (const grup of GRUP_VOUCHER_KANTOR) {
  console.log(`${grup.judul}`)
  for (const v of grup.voucher) {
    // Persentase wajib punya batas — tagihan kantor bisa sangat besar.
    periksa(
      v.kode,
      !v.diskonPersen || typeof v.diskonMaks === 'number',
      'diskon persentase tanpa diskonMaks',
    )

    // Tepat di bawah minimum: tidak boleh memotong sama sekali.
    const dibawah = hitungPromoKantor(v, v.minTransaksi - 1)
    periksa(v.kode, dibawah.potongan === 0, 'memotong padahal di bawah minimum transaksi')

    // Tepat di minimum, dan pada nilai besar.
    const pas = hitungPromoKantor(v, v.minTransaksi)
    const besar = hitungPromoKantor(v, v.minTransaksi * 10)

    periksa(v.kode, pas.potongan <= v.minTransaksi, 'potongan melebihi nilai transaksi')
    if (v.diskonMaks) {
      periksa(v.kode, besar.potongan <= v.diskonMaks, 'potongan menembus batas maksimal')
    }

    const catatanBonus = v.bonus ? ` + ${v.bonus}` : ''
    console.log(
      `  ${v.kode.padEnd(15)} min ${rp(v.minTransaksi).padStart(12)}` +
        `  → potong ${rp(pas.potongan).padStart(10)}` +
        `  (pada 10x: ${rp(besar.potongan)})${catatanBonus}`,
    )
  }
  console.log('')
}

/* Contoh dari spesifikasi: TRYOFFIC20 pada transaksi Rp750.000. */
const trial = semuaVoucherKantor().find((v) => v.kode === 'TRYOFFIC20')!
const contoh = hitungPromoKantor(trial, 750_000)
console.log('Contoh TRYOFFIC20 pada Rp750.000:')
console.log(`  potongan ${rp(contoh.potongan)} → bayar ${rp(750_000 - contoh.potongan)}`)
periksa('TRYOFFIC20', contoh.potongan === 150_000, 'contoh spesifikasi tidak menghasilkan Rp150.000')

console.log('')
if (gagal > 0) {
  console.error(`${gagal} pemeriksaan gagal.`)
  process.exit(1)
}
console.log('Semua pemeriksaan lulus.')
