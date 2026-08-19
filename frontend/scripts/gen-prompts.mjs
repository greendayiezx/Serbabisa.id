// Baca katalog TS → ekstrak (id, nama, kategori, grup) → dedup per-jenis → prompt FLUX.
// Dijalankan dengan: node gen-prompts.mjs <path-ke-belanjaKatalog.ts>
import fs from 'node:fs'

const file = process.argv[2]
const src = fs.readFileSync(file, 'utf8')
const lines = src.split(/\r?\n/)

// Peta suffix header blok → id kategori (dari KATALOG_BY_KATEGORI)
const BLOCK_TO_KAT = {
  BUAH: 'buah', SAYUR: 'sayur', SUSU: 'susu', TELUR: 'telur', DAGING: 'daging',
  SEAFOOD: 'seafood', SNACK: 'snack', MINUMAN: 'minuman', POKOK: 'pokok',
  BUMBU: 'bumbu', ROTI: 'roti', FROZEN: 'frozen',
}

const KAT_CTX = {
  buah: 'fresh whole fruit', sayur: 'fresh vegetable', susu: 'dairy product packaging',
  telur: 'eggs, tofu or tempeh', daging: 'raw meat or poultry', seafood: 'fresh raw seafood',
  snack: 'packaged snack food', minuman: 'beverage bottle or carton', pokok: 'grocery staple food',
  bumbu: 'cooking spice or seasoning', roti: 'bread or pastry', frozen: 'frozen food package',
}

// --- Ekstraksi ---
let currentKat = null
const items = []
const headerRe = /export const KATALOG_(\w+)\s*:/
const idRe = /id:\s*'([^']+)'/
const namaRe = /nama:\s*"([^"]+)"|nama:\s*'([^']+)'/ // blok daging pakai kutip tunggal
const grupRe = /grup:\s*'([^']+)'/

for (const line of lines) {
  const h = line.match(headerRe)
  if (h && BLOCK_TO_KAT[h[1]]) { currentKat = BLOCK_TO_KAT[h[1]]; continue }
  const id = line.match(idRe)
  const nama = line.match(namaRe)
  if (id && nama && currentKat) {
    const grup = line.match(grupRe)
    items.push({ id: id[1], nama: nama[2] ?? nama[1], kategori: currentKat, grup: grup ? grup[1] : '' })
  }
}

// --- Dedup per-jenis: buang penanda ukuran/jumlah/kemasan, sisakan nama deskriptif ---
function jenisKey(nama) {
  let s = ' ' + nama.toLowerCase() + ' '
  // rentang & angka + satuan berat/volume
  s = s.replace(/\d+([.,]\d+)?\s*[-–]\s*\d+([.,]\d+)?\s*(gram|gr|kg|ml|liter|lt|l|cc|g)\b/g, ' ')
  s = s.replace(/\d+([.,]\d+)?\s*(gram|gr|kg|ml|liter|lt|l|cc|g)\b/g, ' ')
  // jumlah pcs/butir/sisir/ekor/isi
  s = s.replace(/\d+\s*[-–]\s*\d+\s*(pcs|pc|butir|sisir|ekor|buah|biji|ikat|pack)?\b/g, ' ')
  s = s.replace(/\bisi\s*\d+\b/g, ' ')
  s = s.replace(/\d+\s*(pcs|pc|butir|sisir|ekor|biji|ikat)\b/g, ' ')
  s = s.replace(/\(\s*\d+[^)]*\)/g, ' ') // (3pcs) dsb
  // kata kemasan
  s = s.replace(/\b(pack|pak|pouch|botol|kaleng|sachet|renceng|cup|box|dus|karton|jerigen|refill|isi)\b/g, ' ')
  // penanda sumber
  s = s.replace(/\bastro farm\b/g, ' ').replace(/\bastro\b/g, ' ')
  // sisa angka lepas + rapikan
  s = s.replace(/\b\d+([.,]\d+)?\b/g, ' ')
  s = s.replace(/\s+'?s\b/g, ' ')          // sisa "5's" → buang "'s"
  s = s.replace(/[-–]+/g, ' ')             // strip dash sisa ("kentang -")
  s = s.replace(/[^a-z0-9&+%\s]/g, ' ')    // buang tanda baca lepas
  s = s.replace(/\s+/g, ' ').trim()
  return s
}

const jenisMap = new Map()
for (const it of items) {
  const key = it.kategori + '|' + jenisKey(it.nama)
  if (!jenisMap.has(key)) {
    jenisMap.set(key, { kategori: it.kategori, jenis: jenisKey(it.nama) || it.nama.toLowerCase(), contoh: it.nama, ids: [] })
  }
  jenisMap.get(key).ids.push(it.id)
}
const jenisList = [...jenisMap.values()]

// --- Prompt FLUX seragam per jenis ---
function buatPrompt(j) {
  const ctx = KAT_CTX[j.kategori] || 'grocery product'
  // pakai contoh nama (lebih deskriptif) tapi tanpa penanda ukuran sudah dibuang di jenis
  const subjek = j.jenis.replace(/\b\w/g, (c) => c.toUpperCase())
  return `professional e-commerce product photo of ${subjek}, ${ctx}, centered on pure white seamless background, soft even studio lighting, high detail, sharp focus, square 1:1 composition, no text, no watermark, no people`
}

const out = jenisList.map((j) => ({
  kategori: j.kategori,
  jenis: j.jenis,
  contohNama: j.contoh,
  jumlahVarian: j.ids.length,
  idMewakili: j.ids[0], // file foto diberi nama id ini, sisanya varian ikut fallback ke sini nanti
  semuaIds: j.ids,
  prompt: buatPrompt(j),
}))

// --- Statistik ---
const perKat = {}
for (const it of items) perKat[it.kategori] = (perKat[it.kategori] || 0) + 1
const perKatJenis = {}
for (const j of jenisList) perKatJenis[j.kategori] = (perKatJenis[j.kategori] || 0) + 1

console.log('=== RINGKASAN ===')
console.log('Total produk       :', items.length)
console.log('Total jenis unik   :', jenisList.length)
console.log('Rasio hemat        :', (items.length / jenisList.length).toFixed(2) + 'x lebih sedikit gambar')
console.log('')
console.log('Kategori   | Produk | Jenis unik')
console.log('-----------|--------|-----------')
for (const k of Object.keys(perKat)) {
  console.log(k.padEnd(10), '|', String(perKat[k]).padStart(6), '|', String(perKatJenis[k] || 0).padStart(6))
}

fs.writeFileSync('prompts.json', JSON.stringify(out, null, 2))
console.log('\nDitulis: prompts.json  (' + out.length + ' prompt)')
console.log('\n=== 8 CONTOH PROMPT ===')
for (const s of [out[0], out[1], out.find(x=>x.kategori==='snack'), out.find(x=>x.kategori==='minuman'), out.find(x=>x.kategori==='daging'), out.find(x=>x.kategori==='sayur'), out.find(x=>x.jumlahVarian>3), out.find(x=>x.kategori==='susu')].filter(Boolean)) {
  console.log(`\n[${s.kategori}] "${s.contohNama}" (${s.jumlahVarian} varian)`)
  console.log('  →', s.prompt)
}
