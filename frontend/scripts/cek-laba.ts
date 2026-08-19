/**
 * Pemeriksa kelayakan promo.
 *
 * Jalankan tiap kali nilai promo atau paket langganan diubah:
 *   npm run cek:laba
 *
 * Skrip keluar dengan kode 1 kalau ada satu saja promo yang membuat pesanan
 * merugi, sehingga tidak perlu menghitung manual tiap kali menambah promo.
 */

import { BIAYA_LAYANAN, ongkirUntuk } from '../src/lib/ongkir'
import {
  LABA_MINIMUM_PER_PESANAN,
  MARKUP_RATA_PER_ITEM,
  labaBersih,
  pendapatanKotor,
  perkiraanItem,
} from '../src/lib/ekonomi'
import { LANGGANAN, PROMO, hitungPromo, type KonteksPromo } from '../src/lib/promo'

const rp = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID')
const ongkir = ongkirUntuk(4)
let gagal = 0

/** Beberapa waktu uji supaya promo bersyarat tanggal/jam tetap bisa dievaluasi. */
const WAKTU_UJI = [
  new Date(2026, 7, 1, 12, 0), // tanggal 1, jam emas siang
  new Date(2026, 7, 1, 19, 0), // jam emas malam
  new Date(2026, 7, 26, 12, 0), // payday 25–27
  new Date(2026, 7, 29, 12, 0), // payday 28–30
  new Date(2026, 7, 22, 12, 0), // Sabtu
]

console.log('=== Laba bersih tiap promo pada belanja minimumnya ===')
console.log(
  'promo'.padEnd(36) + 'min'.padStart(10) + 'pendapatan'.padStart(12) + 'biaya'.padStart(10) + 'laba'.padStart(11),
)

for (const p of PROMO) {
  const min = p.minBelanja
  const dasar: KonteksPromo = {
    totalBarang: min,
    biayaLayanan: BIAYA_LAYANAN,
    ongkir,
    transaksiKe: 1,
    waktu: WAKTU_UJI[0],
    // Semua kategori diisi supaya syarat Bundle ikut terpenuhi.
    subtotalKategori: {
      buah: min, sayur: 0, daging: 0, seafood: 0,
      pokok: min, telur: 0, bumbu: 0, susu: 0,
      a: min / 3, b: min / 3, c: min / 3,
    },
  }

  let hasil = null
  for (const waktu of WAKTU_UJI) {
    const r = hitungPromo(p, { ...dasar, waktu })
    if (r.berlaku) { hasil = r; break }
  }
  if (!hasil) {
    console.log(p.judul.slice(0, 35).padEnd(36) + '  (syarat tidak terpenuhi di waktu uji mana pun)')
    continue
  }

  const biaya = hasil.potonganBarang + hasil.potonganOngkir + hasil.cashback
  const laba = labaBersih(min, biaya, ongkir)
  const lolos = laba >= LABA_MINIMUM_PER_PESANAN
  if (!lolos) gagal++
  console.log(
    (lolos ? '' : '!! ') + p.judul.slice(0, 35).padEnd(lolos ? 36 : 33) +
    rp(min).padStart(10) + rp(pendapatanKotor(min)).padStart(12) +
    rp(biaya).padStart(10) + rp(laba).padStart(11) + (lolos ? '' : '   RUGI'),
  )
}

/**
 * Langganan diuji pada keranjang KECIL sekaligus, karena itulah kasus
 * terburuknya: iuran tetap sama tapi markup yang terkumpul paling sedikit,
 * sementara ongkir gratis tetap harus dibayar penuh.
 */
console.log('\n=== Laba bulanan tiap paket langganan ===')
for (const keranjang of [150000, 75000]) {
  const pendapatanPerOrder = perkiraanItem(keranjang) * MARKUP_RATA_PER_ITEM + BIAYA_LAYANAN
  console.log(`\n--- keranjang ${rp(keranjang)} (${rp(pendapatanPerOrder)}/order) ---`)
  for (const l of LANGGANAN) {
    const nilaiBenefit = l.kuotaOngkir * ongkir + l.kuotaDiskon * l.diskonPerTransaksi
    const layak = nilaiBenefit > l.harga
    if (!layak) gagal++
    console.log(
      `\n${l.nama} — ${rp(l.harga)}/bulan, nilai benefit maks ${rp(nilaiBenefit)}` +
      (layak ? '' : '   !! IURAN LEBIH MAHAL DARI MANFAATNYA, tidak akan dibeli'),
    )
    console.log('  order/bln'.padEnd(12) + 'dari order'.padStart(13) + 'iuran'.padStart(10) + 'biaya benefit'.padStart(15) + 'laba'.padStart(12))
    for (const freq of [2, 4, 8, 12, 20, 30]) {
      const dariOrder = pendapatanPerOrder * freq
      const biaya = Math.min(freq, l.kuotaOngkir) * ongkir + Math.min(freq, l.kuotaDiskon) * l.diskonPerTransaksi
      const laba = dariOrder + l.harga - biaya
      const lolos = laba > 0
      if (!lolos) gagal++
      console.log(
        ('  ' + freq + 'x').padEnd(12) + rp(dariOrder).padStart(13) + rp(l.harga).padStart(10) +
        ('-' + rp(biaya)).padStart(15) + rp(laba).padStart(12) + (lolos ? '' : '   RUGI'),
      )
    }
  }
}

if (gagal) {
  console.error(`\n${gagal} skenario merugi. Turunkan nilai promo atau naikkan minimum belanjanya.`)
  process.exit(1)
}
console.log('\nSemua promo & paket langganan menyisakan laba.')
