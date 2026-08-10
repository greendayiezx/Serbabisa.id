<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Kategori jasa tetap dari PRD bab 3.1.
     */
    public function run(): void
    {
        $categories = [
            ['nama' => 'BisaJemput', 'basis_harga' => 'jarak_waktu', 'ikon' => 'car', 'deskripsi' => 'Antar orang dari titik A ke B'],
            ['nama' => 'BisaKirim', 'basis_harga' => 'jarak_berat', 'ikon' => 'package', 'deskripsi' => 'Kirim paket, dokumen, kunci'],
            ['nama' => 'BisaBelanja', 'basis_harga' => 'ongkos_belanja', 'ikon' => 'shopping-cart', 'deskripsi' => 'Beli makanan, obat, kebutuhan harian'],
            ['nama' => 'BisaBersih', 'basis_harga' => 'durasi', 'ikon' => 'sparkles', 'deskripsi' => 'Bersihkan rumah/kos per jam'],
            ['nama' => 'BisaAngkut', 'basis_harga' => 'volume_jarak', 'ikon' => 'truck', 'deskripsi' => 'Pindah barang skala kecil'],
            ['nama' => 'BisaTukang', 'basis_harga' => 'kunjungan_jam', 'ikon' => 'wrench', 'deskripsi' => 'Perbaikan kecil (listrik, ledeng, dsb.)'],
        ];

        foreach ($categories as $category) {
            // Match by icon (stable per category type) rather than slug, so renaming
            // updates the existing row in place instead of inserting a duplicate.
            Category::updateOrCreate(
                ['ikon' => $category['ikon']],
                [...$category, 'slug' => Str::slug($category['nama']), 'is_active' => true]
            );
        }
    }
}
