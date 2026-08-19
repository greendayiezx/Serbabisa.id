/**
 * Ekspor katalog BisaBelanja (src/lib/belanjaKatalog.ts) menjadi JSON datar
 * untuk diimpor ke tabel `products` di backend.
 *
 * Katalog tetap menjadi sumber kebenaran di frontend; skrip ini hanya
 * "membekukan" isinya ke JSON yang dibaca ProductSeeder. Jalankan ulang tiap
 * katalog berubah:
 *
 *   node scripts/export-katalog.ts
 *
 * Node 22 menjalankan TypeScript langsung (type-stripping) — tanpa dependency.
 */
import { writeFileSync, mkdirSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { KATALOG_BY_KATEGORI, hargaFinal, type KatalogItem } from '../src/lib/belanjaKatalog.ts'

const here = dirname(fileURLToPath(import.meta.url))
const tujuan = resolve(here, '../../backend/database/data/products.json')

interface BarisProduk {
  sku: string
  kategori: string
  nama: string
  slug: string
  satuan: string
  harga_dasar: number
  harga_jual: number
  is_active: boolean
}

const baris: BarisProduk[] = []

for (const [kategori, items] of Object.entries(KATALOG_BY_KATEGORI)) {
  for (const item of items as KatalogItem[]) {
    baris.push({
      sku: item.id,
      kategori,
      nama: item.nama,
      slug: item.id,
      satuan: item.satuan,
      harga_dasar: item.hargaDasar,
      harga_jual: hargaFinal(item, kategori),
      is_active: !item.habis,
    })
  }
}

mkdirSync(dirname(tujuan), { recursive: true })
writeFileSync(tujuan, JSON.stringify(baris, null, 2) + '\n', 'utf8')

console.log(`Diekspor ${baris.length} produk ke ${tujuan}`)
