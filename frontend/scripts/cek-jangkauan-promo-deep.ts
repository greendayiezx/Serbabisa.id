/**
 * Pemeriksa jangkauan promo Deep Cleaning.
 *
 * Menjawab satu pertanyaan yang mudah dilewatkan saat menyusun promo: apakah
 * minimum transaksinya BISA dicapai mesin harga kita sendiri? Promo dengan
 * minimum di atas tagihan tertinggi yang mungkin bukan promo — ia hanya
 * memenuhi katalog dan membuat pengguna bertanya-tanya.
 *
 *   npx tsx scripts/cek-jangkauan-promo-deep.ts
 */
import {
  ADD_ON_DEEP,
  PAKET_DEEP,
  hitungHargaDeep,
  type AddOnDeepId,
} from '../src/lib/bersih/hargaBersihDeep'
import { PROMO_DEEP, hitungPromoDeep } from '../src/lib/promo/promoBersihDeep'

/** Lingkup yang masuk akal untuk rumah; bukan batas validasi (1.000 m²). */
const LUAS = [30, 50, 80, 120, 160, 200, 250]
const RUANGAN = [1, 2, 3, 4, 6, 8, 10]

function kombinasiAddOn(): AddOnDeepId[][] {
  const id = ADD_ON_DEEP.map((a) => a.id)
  const hasil: AddOnDeepId[][] = []
  for (let mask = 0; mask < 1 << id.length; mask++) {
    hasil.push(id.filter((_, i) => mask & (1 << i)))
  }
  return hasil
}

const semua: { total: number; label: string }[] = []

for (const paket of PAKET_DEEP) {
  for (const luas of LUAS) {
    for (const ruangan of RUANGAN) {
      for (const addOn of kombinasiAddOn()) {
        const r = hitungHargaDeep({ paketId: paket.id, luasM2: luas, jumlahRuangan: ruangan, addOn })
        semua.push({
          total: r.total,
          label: `${paket.nama} · ${luas} m² · ${ruangan} ruangan · ${addOn.length} add-on`,
        })
      }
    }
  }
}

semua.sort((a, b) => a.total - b.total)
const rp = (n: number) => 'Rp' + n.toLocaleString('id-ID')

console.log('=== Rentang tagihan Deep Cleaning ===')
console.log('  terendah  ', rp(semua[0].total).padStart(12), ' ', semua[0].label)
console.log('  tertinggi ', rp(semua[semua.length - 1].total).padStart(12), ' ', semua[semua.length - 1].label)

const tanpaAddOn = semua.filter((s) => s.label.endsWith('0 add-on'))
console.log(
  '  tertinggi tanpa add-on',
  rp(tanpaAddOn[tanpaAddOn.length - 1].total),
  ` (${tanpaAddOn[tanpaAddOn.length - 1].label})`,
)

console.log('\n=== Jangkauan tiap promo ===')
console.log('  kode              minimum   potongan   terjangkau')
let adaMasalah = false

for (const p of PROMO_DEEP) {
  const bisa = semua.filter((s) => s.total >= p.minTransaksi)
  const persen = Math.round((bisa.length / semua.length) * 100)

  // Bagian potongan terhadap tagihan terkecil yang memenuhi syarat — di
  // situlah margin paling tertekan.
  const terkecil = bisa[0]
  const potongan = terkecil ? hitungPromoDeep(p, terkecil.total).potongan : 0
  const bagian = terkecil ? Math.round((potongan / terkecil.total) * 100) : 0

  const status = persen === 0 ? 'TIDAK PERNAH' : `${persen}% kombinasi`
  if (persen === 0 || bagian > 20) adaMasalah = true

  console.log(
    '  ' +
      p.kode.padEnd(16) +
      rp(p.minTransaksi).padStart(10) +
      rp(potongan).padStart(11) +
      '   ' +
      status +
      (terkecil ? ` · ${bagian}% dari tagihan terkecil yang memenuhi` : ''),
  )
}

console.log('\n=== Ringkas ===')
console.log(`  ${semua.length} kombinasi harga diuji.`)
if (adaMasalah) {
  console.log('  ADA PROMO BERMASALAH: tidak terjangkau, atau potongannya di atas 20% tagihan.')
  process.exit(1)
}
console.log('  Semua promo terjangkau dan potongannya di bawah 20% tagihan.')
