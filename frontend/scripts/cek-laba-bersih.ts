/**
 * Pemeriksa kelayakan promo BisaBersih.
 *
 * Jalankan tiap kali nilai promo, bundling, atau paket langganan diubah:
 *   npm run cek:laba
 *
 * Keluar dengan kode 1 kalau ada satu saja promo yang menyisakan laba di bawah
 * LABA_MINIMUM, sehingga angka promo tidak perlu dihitung manual tiap diubah.
 */

import {
  BIAYA_DEEP_CLEAN,
  BIAYA_PELUANG_DEEP_CLEAN,
  BIAYA_PER_KUNJUNGAN,
  LABA_MINIMUM,
  MARJIN_KOTOR,
  RASIO_BIAYA,
  RASIO_POIN,
  marjinBersihRumah,
  marjinKotor,
  potonganMaksimalBersihRumah,
} from '../src/lib/bersih/ekonomiBersih'
import {
  BUNDLE_BERSIH,
  FIRST_CLEAN,
  GRUP_VOUCHER,
  LANGGANAN_BERSIH,
  hargaPerKunjungan,
} from '../src/lib/promo/promoBersih'
import {
  ADD_ON,
  BONUS_NAIK_LEVEL,
  FREKUENSI,
  HARGA_PER_JAM_LEVEL,
  HARGA_PER_JAM_TERTINGGI,
  KONDISI,
  MARKUP_PER_JAM,
  PILIHAN_DURASI,
  hitungHarga,
} from '../src/lib/bersih/hargaBersih'

const rp = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID')
const pct = (n: number) => (n * 100).toFixed(1) + '%'
let gagal = 0

const tandai = (laba: number) => {
  if (laba < 0) { gagal++; return ' RUGI' }
  if (laba < LABA_MINIMUM) { gagal++; return ' TIPIS' }
  return ''
}

console.log('DUA model pendapatan yang berbeda diperiksa terpisah:\n')

console.log('A. Bersih Rumah (pesanan per jam) — pendapatan HANYA markup cleaner')
console.log(`  markup platform       ${rp(MARKUP_PER_JAM)} / jam kerja`)
console.log(`  tarif customer        ${HARGA_PER_JAM_LEVEL.map((h) => rp(h)).join(' · ')} per jam`)
console.log(`  bonus naik level      ${rp(BONUS_NAIK_LEVEL)} sekali tiap tingkat`)
console.log(
  `  → dihitung pada tarif tertinggi ${rp(HARGA_PER_JAM_TERTINGGI)}/jam (kasus terburuk:\n` +
  '    jam paling sedikit untuk nilai transaksi yang sama, jadi markup paling tipis)\n',
)

console.log('B. Bundling & langganan (harga paket tetap) — masih model rasio biaya')
console.log(`  biaya per kunjungan   ${rp(BIAYA_PER_KUNJUNGAN)}`)
console.log(`  rasio biaya           ${pct(RASIO_BIAYA)}  →  marjin kotor ${pct(MARJIN_KOTOR)}`)
console.log('  CATATAN: rasio ini TIDAK berlaku untuk pesanan per jam di bagian A.\n')

console.log(`BisaPoints              ${pct(RASIO_POIN)} dari tiap transaksi`)
console.log(`Laba minimum sehat      ${rp(LABA_MINIMUM)}\n`)

/* ------------------------------------------------------------------ *
 * 1. Voucher — diuji pada nilai transaksi minimumnya (kasus terburuk
 *    untuk potongan nominal tetap).
 * ------------------------------------------------------------------ */
console.log('=== Voucher pada transaksi minimum (dipakai di Bersih Rumah) ===')
console.log(
  'kode'.padEnd(16) + 'min'.padStart(11) + 'marjin'.padStart(11) +
  'potongan'.padStart(11) + 'poin'.padStart(10) + 'laba'.padStart(12),
)

for (const grup of GRUP_VOUCHER) {
  for (const v of grup.voucher) {
    const min = v.minTransaksi
    const marjin = marjinBersihRumah(min)
    const potongan = v.potongan ?? Math.min(min * ((v.cashbackPersen ?? 0) / 100), v.cashbackMaks ?? Infinity)
    const poin = min * RASIO_POIN
    // Referral dibayar dua kali: diskon untuk yang diajak + hadiah pengajak.
    const ekstra = v.hadiahPengajak ?? 0
    const laba = marjin - potongan - poin - ekstra

    console.log(
      v.kode.padEnd(16) + rp(min).padStart(11) + rp(marjin).padStart(11) +
      rp(-(potongan + ekstra)).padStart(11) + rp(-poin).padStart(10) +
      (rp(laba) + tandai(laba)).padStart(12),
    )
  }
}

/* ------------------------------------------------------------------ *
 * 2. Cashback persentase juga diuji di atas ambang — di sinilah promo
 *    persentase paling berisiko kalau batas maksimumnya kebesaran.
 * ------------------------------------------------------------------ */
console.log('\n=== Cashback persentase pada nilai transaksi lebih besar ===')
console.log('kode'.padEnd(16) + 'transaksi'.padStart(12) + 'cashback'.padStart(11) + 'laba'.padStart(12))

for (const grup of GRUP_VOUCHER) {
  for (const v of grup.voucher) {
    if (!v.cashbackPersen) continue
    for (const nilai of [v.minTransaksi, v.minTransaksi * 2, v.minTransaksi * 4]) {
      const cashback = Math.min(nilai * (v.cashbackPersen / 100), v.cashbackMaks ?? Infinity)
      const laba = marjinBersihRumah(nilai) - cashback - nilai * RASIO_POIN
      console.log(
        v.kode.padEnd(16) + rp(nilai).padStart(12) + rp(-cashback).padStart(11) +
        (rp(laba) + tandai(laba)).padStart(12),
      )
    }
  }
}

/* ------------------------------------------------------------------ *
 * 2b. Kombinasi terburuk yang bisa dicapai pengguna nyata: pelanggan
 *     langganan (poin GANDA) memesan satuan sambil memakai voucher.
 * ------------------------------------------------------------------ */
console.log('\n=== Voucher + poin ganda (pelanggan langganan) ===')
console.log('kode'.padEnd(16) + 'min'.padStart(11) + 'total potongan'.padStart(16) + 'laba'.padStart(12))

for (const grup of GRUP_VOUCHER) {
  for (const v of grup.voucher) {
    const min = v.minTransaksi
    const potongan =
      (v.potongan ?? Math.min(min * ((v.cashbackPersen ?? 0) / 100), v.cashbackMaks ?? Infinity)) +
      (v.hadiahPengajak ?? 0)
    const poin = min * RASIO_POIN * 2
    const laba = marjinBersihRumah(min) - potongan - poin
    console.log(
      v.kode.padEnd(16) + rp(min).padStart(11) + rp(-(potongan + poin)).padStart(16) +
      (rp(laba) + tandai(laba)).padStart(12),
    )
  }
}

/* ------------------------------------------------------------------ *
 * 3. Bundling — biaya dihitung dari harga NORMAL, karena pekerjaannya
 *    tidak ikut mengecil saat harganya didiskon.
 * ------------------------------------------------------------------ */
console.log('\n=== Bundling ===')
console.log('bundle'.padEnd(20) + 'normal'.padStart(11) + 'promo'.padStart(11) + 'biaya'.padStart(11) + 'laba'.padStart(12))

for (const b of BUNDLE_BERSIH) {
  const biaya = b.hargaNormal * RASIO_BIAYA
  const poin = b.hargaPromo * RASIO_POIN
  const laba = b.hargaPromo - biaya - poin
  console.log(
    b.nama.padEnd(20) + rp(b.hargaNormal).padStart(11) + rp(b.hargaPromo).padStart(11) +
    rp(-biaya).padStart(11) + (rp(laba) + tandai(laba)).padStart(12),
  )
}

/* ------------------------------------------------------------------ *
 * 4. Langganan — biaya dihitung per kunjungan, plus benefit yang
 *    berbentuk layanan gratis.
 * ------------------------------------------------------------------ */
console.log('\n=== Paket langganan (per bulan) ===')
console.log(
  'paket'.padEnd(14) + 'harga'.padStart(12) + 'kunjungan'.padStart(11) +
  'biaya'.padStart(12) + 'benefit'.padStart(12) + 'laba'.padStart(13),
)

for (const p of LANGGANAN_BERSIH) {
  const biaya = p.kunjunganPerBulan * BIAYA_PER_KUNJUNGAN
  const perBulan = p.deepCleanGratisPerBulan ?? 0
  const benefit = perBulan * BIAYA_DEEP_CLEAN
  const poin = p.hargaBulanan * RASIO_POIN * 2 // pelanggan langganan dapat poin ganda
  const laba = p.hargaBulanan - biaya - benefit - poin

  console.log(
    p.nama.padEnd(14) + rp(p.hargaBulanan).padStart(12) +
    `${p.kunjunganPerBulan}x`.padStart(11) + rp(-biaya).padStart(12) +
    rp(-benefit).padStart(12) + (rp(laba) + tandai(laba)).padStart(13),
  )

  if (perBulan > 0) {
    // Skenario kedua: cleaner penuh, jadi deep clean gratis berarti menolak
    // pesanan berbayar. Biaya efektifnya jadi harga jual, bukan biaya produksi.
    const peluang = perBulan * BIAYA_PELUANG_DEEP_CLEAN
    const labaPenuh = p.hargaBulanan - biaya - peluang - poin
    console.log(
      '  ^ kapasitas penuh'.padEnd(14 + 12 + 11) + rp(-peluang).padStart(24) +
      (rp(labaPenuh) + tandai(labaPenuh)).padStart(13),
    )
  }
}

/* ------------------------------------------------------------------ *
 * 4b. Mesin harga Bersih Rumah.
 *
 *     Diuji menyeluruh: SETIAP kombinasi layanan x kondisi x durasi x jumlah
 *     cleaner x frekuensi, tanpa add-on (add-on selalu menambah marjin, jadi
 *     kasus terburuknya adalah tanpa add-on). Promo pengguna baru terbesar yang
 *     memenuhi syarat ikut dipasang, karena itulah yang dilakukan halamannya.
 * ------------------------------------------------------------------ */
console.log('\n=== Bersih Rumah: kombinasi paling tipis ===')
console.log(
  '  diuji di SEMUA level cleaner, karena markup platform tetap sama\n' +
  '  sementara nilai transaksinya berbeda tiap level\n',
)

interface Baris { label: string; nilai: number; promo: number; laba: number }
const semua: Baris[] = []

for (const hargaPerJam of HARGA_PER_JAM_LEVEL) {
  for (const k of KONDISI) {
    for (const durasiJam of PILIHAN_DURASI) {
      for (const jumlahCleaner of [1, 2]) {
        for (const f of FREKUENSI) {
          const konfig = {
            hargaPerJam,
            kondisiId: k.id,
            durasiJam,
            jumlahCleaner,
            addOnDipilih: [] as string[],
            frekuensiId: f.id,
          }
          const tanpaPromo = hitungHarga(konfig)
          const promo = [...FIRST_CLEAN.voucher]
            .filter((v) => tanpaPromo.nilaiTransaksi >= v.minTransaksi)
            .sort((a, b) => (b.potongan ?? 0) - (a.potongan ?? 0))[0]
          const h = hitungHarga(konfig, promo?.potongan ?? 0)
          const laba = h.nilaiTransaksi - h.biaya - h.potonganPromo - h.total * RASIO_POIN
          semua.push({
            label: `${rp(hargaPerJam)}/j · ${k.label} · ${durasiJam}j · ${jumlahCleaner} cleaner · ${f.label}`,
            nilai: h.nilaiTransaksi,
            promo: h.potonganPromo,
            laba,
          })
        }
      }
    }
  }
}

semua.sort((a, b) => a.laba - b.laba)
console.log(`  ${semua.length} kombinasi diuji, lima paling tipis:`)
for (const b of semua.slice(0, 5)) {
  console.log(
    '  ' + b.label.padEnd(52) + rp(b.nilai).padStart(11) + rp(-b.promo).padStart(11) +
    (rp(b.laba) + tandai(b.laba)).padStart(12),
  )
}

/* Add-on diperiksa terpisah: masing-masing harus untung sendiri. */
/*
 * Add-on dinilai dengan MARJIN PERSENTASE, bukan ambang rupiah.
 *
 * LABA_MINIMUM Rp20.000 dirancang untuk satu pesanan utuh yang memakan waktu
 * berjam-jam. Add-on hanyalah marjin tambahan di atas pesanan yang sudah
 * untung, jadi menuntut Rp20.000 dari layanan seharga Rp30.000 tidak masuk
 * akal — yang relevan apakah persentasenya sebanding dengan marjin platform.
 */
const MARJIN_MIN_ADDON = 0.3
console.log(`\n  layanan tambahan (ambang marjin ${pct(MARJIN_MIN_ADDON)}):`)
for (const a of ADD_ON) {
  const laba = a.harga - a.biaya - a.harga * RASIO_POIN
  const rasio = laba / a.harga
  if (rasio < MARJIN_MIN_ADDON) gagal++
  console.log(
    '  ' + a.nama.padEnd(24) + rp(a.harga).padStart(10) + rp(-a.biaya).padStart(10) +
    rp(laba).padStart(11) + (pct(rasio) + (rasio < MARJIN_MIN_ADDON ? ' TIPIS' : '')).padStart(10),
  )
}

/* ------------------------------------------------------------------ *
 * 5. Saran angka aman
 * ------------------------------------------------------------------ */
console.log('\n=== Potongan maksimal yang masih sehat (Bersih Rumah) ===')
for (const nilai of [180_000, 200_000, 250_000, 300_000, 400_000]) {
  console.log(
    `  transaksi ${rp(nilai).padStart(10)}  →  potongan maks ${rp(potonganMaksimalBersihRumah(nilai, true)).padStart(10)} (sudah menghitung BisaPoints)`,
  )
}

/* ------------------------------------------------------------------ *
 * 6. Bonus naik level — beban yang tidak melekat pada satu pesanan.
 *
 *    Markup per jam adalah satu-satunya pemasukan, jadi tiap bonus harus
 *    terbayar dari jam kerja cleaner yang menerimanya.
 * ------------------------------------------------------------------ */
console.log('\n=== Bonus naik level ===')
const jamMenutupBonus = Math.ceil(BONUS_NAIK_LEVEL / MARKUP_PER_JAM)
console.log(`  bonus per tingkat        ${rp(BONUS_NAIK_LEVEL)}`)
console.log(`  markup per jam           ${rp(MARKUP_PER_JAM)}`)
console.log(`  → tertutup setelah       ${jamMenutupBonus} jam kerja cleaner itu`)

// Naik dari level 1 ke level tertinggi berarti membayar bonus beberapa kali.
const totalBonus = BONUS_NAIK_LEVEL * (HARGA_PER_JAM_LEVEL.length - 1)
console.log(
  `  seorang cleaner yang menembus level tertinggi menerima ${rp(totalBonus)}\n` +
  `  → butuh ${Math.ceil(totalBonus / MARKUP_PER_JAM)} jam kerja untuk menutupnya`,
)

console.log('\n=== Harga per kunjungan ===')
for (const p of LANGGANAN_BERSIH) {
  console.log(`  ${p.nama.padEnd(12)} ${rp(hargaPerKunjungan(p)).padStart(10)} / kunjungan`)
}

if (gagal > 0) {
  console.log(`\n${gagal} baris di bawah laba minimum ${rp(LABA_MINIMUM)}.`)
  process.exit(1)
}
console.log('\nSemua promo, bundling & paket langganan BisaBersih menyisakan laba sehat.')
