/**
 * Apakah ambang minimum promo kantor bisa dijangkau harga yang ada?
 *
 * Promo dengan minimum di atas tagihan tertinggi yang mungkin adalah promo mati:
 * ia tampil di katalog, tidak pernah bisa dipakai, dan hanya membuat pengguna
 * bertanya-tanya. Jalankan tiap kali tarif atau ambang promo diubah:
 *
 *   npx tsx scripts/cek-jangkauan-promo-kantor.ts
 */

import {
  ADD_ON_KANTOR,
  FREKUENSI_KANTOR,
  JENIS_KANTOR,
  PAKET_KANTOR,
  hitungHargaKantor,
  type PaketKantorId,
} from '../src/lib/bersih/hargaBersihKantor'
import { semuaVoucherKantor } from '../src/lib/promo/promoBersihKantor'

const rp = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID')

/** Fasilitas khas per jenis kantor — dipakai memperkirakan rentang tagihan. */
const FASILITAS: Record<string, { workstation: number; ruangMeeting: number; toilet: number; pantry: number }> = {
  kecil: { workstation: 8, ruangMeeting: 1, toilet: 1, pantry: 1 },
  sedang: { workstation: 20, ruangMeeting: 2, toilet: 2, pantry: 1 },
  besar: { workstation: 60, ruangMeeting: 4, toilet: 6, pantry: 2 },
}

function tagihan(
  jenisId: string,
  paketId: PaketKantorId,
  frekuensiId: string,
  addOn: string[],
): number {
  const jenis = JENIS_KANTOR.find((j) => j.id === jenisId)!
  const f = FASILITAS[jenisId]

  return hitungHargaKantor({
    paketId,
    luasM2: jenis.luasAcuan,
    jumlahLantai: 1,
    workstation: f.workstation,
    ruangMeeting: f.ruangMeeting,
    toilet: f.toilet,
    pantry: f.pantry,
    addOnDipilih: addOn,
    frekuensiId,
  }).totalPerKunjungan
}

/* ------------------------------------------------------------------ *
 * 1. Rentang tagihan yang benar-benar mungkin
 * ------------------------------------------------------------------ */
const semuaAddOn = ADD_ON_KANTOR.map((a) => a.id)
const nilai: { label: string; total: number }[] = []

for (const j of JENIS_KANTOR) {
  for (const p of PAKET_KANTOR) {
    for (const f of FREKUENSI_KANTOR) {
      nilai.push({
        label: `${j.nama} · ${p.nama} · ${f.label} · tanpa add-on`,
        total: tagihan(j.id, p.id, f.id, []),
      })
      nilai.push({
        label: `${j.nama} · ${p.nama} · ${f.label} · SEMUA add-on`,
        total: tagihan(j.id, p.id, f.id, semuaAddOn),
      })
    }
  }
}

nilai.sort((a, b) => a.total - b.total)
const terendah = nilai[0]
const tertinggi = nilai[nilai.length - 1]

console.log('=== Rentang tagihan per kunjungan ===')
console.log(`  terendah  ${rp(terendah.total).padStart(12)}  ${terendah.label}`)
console.log(`  tertinggi ${rp(tertinggi.total).padStart(12)}  ${tertinggi.label}`)

/* Tagihan tertinggi yang bisa dicapai TANPA add-on — add-on itu opsional,
   jadi promo yang hanya terjangkau dengan memborong add-on praktis mati. */
const tanpaAddOn = nilai.filter((n) => n.label.includes('tanpa add-on'))
const maksTanpaAddOn = tanpaAddOn[tanpaAddOn.length - 1]
console.log(`  tertinggi tanpa add-on ${rp(maksTanpaAddOn.total)}  (${maksTanpaAddOn.label})`)

/* ------------------------------------------------------------------ *
 * 2. Jangkauan tiap promo
 * ------------------------------------------------------------------ */
console.log('\n=== Jangkauan minimum tiap promo ===')
console.log(
  '  ' + 'kode'.padEnd(16) + 'minimum'.padStart(12) + '  status',
)

let mati = 0
let sempit = 0

for (const v of semuaVoucherKantor().sort((a, b) => a.minTransaksi - b.minTransaksi)) {
  const bisa = nilai.filter((n) => n.total >= v.minTransaksi).length
  const persen = (bisa / nilai.length) * 100

  let status: string
  if (bisa === 0) {
    status = 'TIDAK PERNAH BISA DIPAKAI'
    mati++
  } else if (v.minTransaksi > maksTanpaAddOn.total) {
    status = `hanya dengan add-on (${persen.toFixed(0)}% kombinasi)`
    sempit++
  } else {
    status = `${persen.toFixed(0)}% kombinasi`
  }

  console.log('  ' + v.kode.padEnd(16) + rp(v.minTransaksi).padStart(12) + '  ' + status)
}

/* ------------------------------------------------------------------ *
 * 3. Kesimpulan
 * ------------------------------------------------------------------ */
console.log('\n=== Ringkas ===')
console.log(`  ${nilai.length} kombinasi harga diuji.`)
if (mati > 0) {
  console.log(`  ${mati} promo TIDAK PERNAH bisa dipakai — minimumnya di atas tagihan tertinggi.`)
}
if (sempit > 0) {
  console.log(`  ${sempit} promo hanya terjangkau kalau pelanggan menambah layanan tambahan.`)
}
if (mati === 0 && sempit === 0) {
  console.log('  Semua promo bisa dijangkau tanpa memaksa pelanggan menambah add-on.')
}

process.exit(mati > 0 ? 1 : 0)
