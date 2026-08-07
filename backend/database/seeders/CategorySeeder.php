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
            ['nama' => 'Antar-Jemput', 'basis_harga' => 'jarak_waktu', 'ikon' => 'car', 'deskripsi' => 'Antar orang dari titik A ke B'],
            ['nama' => 'Antar Barang / Kurir', 'basis_harga' => 'jarak_berat', 'ikon' => 'package', 'deskripsi' => 'Kirim paket, dokumen, kunci'],
            ['nama' => 'Belanja / Titip Beli', 'basis_harga' => 'ongkos_belanja', 'ikon' => 'shopping-cart', 'deskripsi' => 'Beli makanan, obat, kebutuhan harian'],
            ['nama' => 'Bersih-bersih', 'basis_harga' => 'durasi', 'ikon' => 'sparkles', 'deskripsi' => 'Bersihkan rumah/kos per jam'],
            ['nama' => 'Angkut / Pindahan Ringan', 'basis_harga' => 'volume_jarak', 'ikon' => 'truck', 'deskripsi' => 'Pindah barang skala kecil'],
            ['nama' => 'Jasa Tukang Ringan', 'basis_harga' => 'kunjungan_jam', 'ikon' => 'wrench', 'deskripsi' => 'Perbaikan kecil (listrik, ledeng, dsb.)'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['nama'])],
                [...$category, 'is_active' => true]
            );
        }
    }
}
