<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Impor katalog produk BisaBelanja dari database/data/products.json.
 *
 * File JSON dihasilkan dari katalog frontend oleh `frontend/scripts/export-katalog.ts`
 * (jalankan `node scripts/export-katalog.ts` bila katalog berubah). Katalog tetap
 * jadi sumber kebenaran; seeder ini hanya menyinkronkannya ke DB.
 *
 * Upsert berdasarkan `sku` (= id katalog) sehingga menjalankan ulang memperbarui
 * harga/nama di tempat, bukan menduplikasi.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/products.json');

        if (! is_file($path)) {
            $this->command?->warn("products.json tidak ditemukan — lewati. Jalankan: cd frontend && node scripts/export-katalog.ts");

            return;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (! is_array($rows)) {
            $this->command?->warn('products.json tidak valid — dilewati.');

            return;
        }

        $kategori = ProductCategory::pluck('id', 'slug');
        $now = Carbon::now();
        $seen = [];
        $batch = [];

        foreach ($rows as $r) {
            $sku = $r['sku'] ?? null;
            $catId = $kategori[$r['kategori'] ?? ''] ?? null;
            if (! $sku || ! $catId || isset($seen[$sku])) {
                continue;
            }
            $seen[$sku] = true;

            $batch[] = [
                'product_category_id' => $catId,
                'sku' => $sku,
                'nama' => $r['nama'],
                'slug' => $r['slug'] ?? $sku,
                'satuan' => $r['satuan'] ?? 'pcs',
                'harga_dasar' => $r['harga_dasar'] ?? 0,
                'harga_jual' => $r['harga_jual'] ?? 0,
                'is_active' => $r['is_active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        collect($batch)->chunk(500)->each(function ($chunk) {
            Product::upsert(
                $chunk->all(),
                ['sku'],
                ['product_category_id', 'nama', 'slug', 'satuan', 'harga_dasar', 'harga_jual', 'is_active', 'updated_at'],
            );
        });

        $this->command?->info('Diimpor '.count($batch).' produk ke tabel products.');
    }
}
