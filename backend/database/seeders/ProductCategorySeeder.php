<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Kategori produk BisaBelanja. Slug disamakan dengan kunci kategori di frontend
 * (dipakai promo Bundle: buah, sayur, daging, seafood, pokok, telur, bumbu, susu).
 */
class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'buah', 'nama' => 'Buah Segar', 'ikon' => 'apple'],
            ['slug' => 'sayur', 'nama' => 'Sayur Segar', 'ikon' => 'carrot'],
            ['slug' => 'daging', 'nama' => 'Daging & Unggas', 'ikon' => 'drumstick'],
            ['slug' => 'seafood', 'nama' => 'Seafood', 'ikon' => 'fish'],
            ['slug' => 'pokok', 'nama' => 'Bahan Pokok', 'ikon' => 'wheat'],
            ['slug' => 'telur', 'nama' => 'Telur, Tahu & Tempe', 'ikon' => 'egg'],
            ['slug' => 'bumbu', 'nama' => 'Bumbu & Bahan Masak', 'ikon' => 'pepper'],
            ['slug' => 'susu', 'nama' => 'Susu & Olahan', 'ikon' => 'milk'],
            ['slug' => 'frozen', 'nama' => 'Frozen Food', 'ikon' => 'snowflake'],
            ['slug' => 'snack', 'nama' => 'Snack & Cemilan', 'ikon' => 'cookie'],
            ['slug' => 'minuman', 'nama' => 'Minuman', 'ikon' => 'cup'],
            ['slug' => 'roti', 'nama' => 'Roti & Kue', 'ikon' => 'bread'],
        ];

        foreach ($categories as $i => $category) {
            ProductCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'urutan' => $i + 1, 'is_active' => true],
            );
        }
    }
}
