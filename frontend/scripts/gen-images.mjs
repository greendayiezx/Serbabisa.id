// Batch generate gambar produk via fal.ai (FLUX).
// Key dibaca dari .env — TIDAK ditulis di kode.
//
// Jalankan (dari folder frontend/scripts):
//   node --env-file=../.env gen-images.mjs --limit 15
//
// Opsi:
//   --limit N     jumlah gambar (default 15; sebar merata lintas kategori)
//   --model NAMA  default 'fal-ai/flux/dev'. Alternatif: 'fal-ai/flux/schnell', 'fal-ai/flux-pro'
//   --out DIR     folder output (default 'pilot-out')
//   --prompts F   sumber prompt (default 'prompts.json')
//   --all         generate SEMUA prompt (abaikan --limit)

import fs from 'node:fs'
import path from 'node:path'

const KEY = process.env.FAL_KEY
if (!KEY) {
  console.error('❌ FAL_KEY belum ada. Tambahkan ke frontend/.env:\n   FAL_KEY=xxxxxxxx:xxxxxxxx\nlalu jalankan dengan  node --env-file=../.env gen-images.mjs')
  process.exit(1)
}

const args = process.argv.slice(2)
const flag = (n) => args.includes('--' + n)
const opt = (n, d) => { const i = args.indexOf('--' + n); return i >= 0 ? args[i + 1] : d }

const LIMIT = parseInt(opt('limit', '15'))
const MODEL = opt('model', 'fal-ai/flux/dev')
const OUT = opt('out', 'pilot-out')
const PROMPTS = opt('prompts', 'prompts.json')

fs.mkdirSync(OUT, { recursive: true })
const all = JSON.parse(fs.readFileSync(PROMPTS, 'utf8'))

// Pilih daftar: semua, atau sebaran merata lintas kategori sebanyak LIMIT.
let list = all
if (!flag('all') && LIMIT && LIMIT < all.length) {
  const byKat = {}
  for (const p of all) (byKat[p.kategori] ??= []).push(p)
  const kats = Object.keys(byKat)
  list = []
  for (let i = 0; list.length < LIMIT && i < 100000; i++) {
    const arr = byKat[kats[i % kats.length]]
    if (arr && arr.length) list.push(arr.shift())
  }
}

async function genOne(p) {
  // Panggil endpoint sinkron fal.run — hasil langsung dibalas.
  const res = await fetch(`https://fal.run/${MODEL}`, {
    method: 'POST',
    headers: { Authorization: `Key ${KEY}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      prompt: p.prompt,
      image_size: 'square_hd', // 1024x1024
      num_images: 1,
      enable_safety_checker: true,
    }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${(await res.text()).slice(0, 300)}`)
  const data = await res.json()
  const img = data?.images?.[0]
  if (!img?.url) throw new Error('Tak ada gambar: ' + JSON.stringify(data).slice(0, 200))

  // fal balas URL gambar → unduh byte-nya.
  const imgRes = await fetch(img.url)
  if (!imgRes.ok) throw new Error(`unduh gambar HTTP ${imgRes.status}`)
  const buf = Buffer.from(await imgRes.arrayBuffer())
  const ext = (img.content_type || 'image/jpeg').split('/')[1].replace('jpeg', 'jpg')
  return { buf, ext }
}

console.log(`Generate ${list.length} gambar via "${MODEL}" → folder ${OUT}/\n`)
let ok = 0, fail = 0
for (const p of list) {
  process.stdout.write(`[${ok + fail + 1}/${list.length}] ${p.contohNama} ... `)
  try {
    const { buf, ext } = await genOne(p)
    fs.writeFileSync(path.join(OUT, `${p.idMewakili}.${ext}`), buf)
    ok++; console.log('✅')
  } catch (e) {
    fail++; console.log('❌ ' + e.message)
  }
}
console.log(`\nSelesai: ${ok} sukses, ${fail} gagal. Buka folder ${OUT}/ untuk lihat hasilnya.`)
