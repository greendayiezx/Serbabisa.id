<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

/**
 * Katalog promo BisaBelanja. Disalin dari konstanta PROMO di frontend
 * (src/lib/promo.ts). Aturan non-nominal (waktu/tanggal/status user) yang di
 * frontend berupa fungsi, di sini disimpan deklaratif pada kolom `syarat`
 * sebagai JSON agar backend bisa mengevaluasinya sendiri saat validasi:
 *   - {"transaksi_ke": 1}      → khusus transaksi pertama
 *   - {"jam_emas": "siang"}    → hanya slot jam tertentu
 *   - {"payday": "25-27"}      → hanya rentang tanggal gajian
 *   - {"weekend": true}        → hanya Sabtu/Minggu
 *   - {"min_kategori": 3}      → keranjang dari minimal 3 kategori
 */
class PromoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->promos() as $promo) {
            Promo::updateOrCreate(['slug' => $promo['slug']], [...$promo, 'is_active' => true]);
        }
    }

    private function promos(): array
    {
        return [
            // Diskon bertingkat
            ['slug' => 'belanja5', 'kode' => 'BELANJA2', 'judul' => 'Diskon Rp2.000', 'grup' => 'tier', 'emoji' => '🎫', 'ringkas' => 'Minimal belanja Rp50.000', 'min_belanja' => 50000, 'potongan_tetap' => 2000],
            ['slug' => 'belanja10', 'kode' => 'BELANJA3', 'judul' => 'Diskon Rp3.000', 'grup' => 'tier', 'emoji' => '🎫', 'ringkas' => 'Minimal belanja Rp100.000', 'min_belanja' => 100000, 'potongan_tetap' => 3000],
            ['slug' => 'belanja25', 'kode' => 'BELANJA5', 'judul' => 'Diskon Rp5.000', 'grup' => 'tier', 'emoji' => '🎫', 'ringkas' => 'Minimal belanja Rp200.000', 'min_belanja' => 200000, 'potongan_tetap' => 5000],
            ['slug' => 'belanja75', 'kode' => 'BELANJA12', 'judul' => 'Diskon Rp12.000', 'grup' => 'tier', 'emoji' => '🎫', 'ringkas' => 'Minimal belanja Rp500.000', 'min_belanja' => 500000, 'potongan_tetap' => 12000],

            // Pengguna baru
            ['slug' => 'new10', 'kode' => 'NEW2', 'judul' => 'Pengguna Baru: Diskon Rp2.000', 'grup' => 'first-order', 'emoji' => '🌟', 'ringkas' => 'Minimal belanja Rp50.000', 'min_belanja' => 50000, 'potongan_tetap' => 2000, 'kuota_per_user' => 1, 'syarat' => ['transaksi_ke' => 1]],
            ['slug' => 'new25', 'kode' => 'NEW3', 'judul' => 'Pengguna Baru: Diskon Rp3.000', 'grup' => 'first-order', 'emoji' => '🌟', 'ringkas' => 'Minimal belanja Rp100.000', 'min_belanja' => 100000, 'potongan_tetap' => 3000, 'kuota_per_user' => 1, 'syarat' => ['transaksi_ke' => 1]],
            ['slug' => 'new40', 'kode' => 'NEW4', 'judul' => 'Pengguna Baru: Diskon Rp4.000', 'grup' => 'first-order', 'emoji' => '🌟', 'ringkas' => 'Minimal belanja Rp150.000', 'min_belanja' => 150000, 'potongan_tetap' => 4000, 'kuota_per_user' => 1, 'syarat' => ['transaksi_ke' => 1]],

            // Gratis ongkir
            ['slug' => 'gratis5', 'kode' => 'GRATISONGKIR', 'judul' => 'Gratis Ongkir', 'grup' => 'gratis-ongkir', 'emoji' => '🚚', 'ringkas' => 'Minimal belanja Rp150.000', 'min_belanja' => 150000, 'gratis_ongkir' => true],
            ['slug' => 'gratis10', 'kode' => 'GRATIS2', 'judul' => 'Gratis Ongkir + Diskon Rp2.000', 'grup' => 'gratis-ongkir', 'emoji' => '🚚', 'ringkas' => 'Minimal belanja Rp250.000', 'min_belanja' => 250000, 'potongan_tetap' => 2000, 'gratis_ongkir' => true],
            ['slug' => 'gratis20', 'kode' => 'GRATIS3', 'judul' => 'Gratis Ongkir + Diskon Rp3.000', 'grup' => 'gratis-ongkir', 'emoji' => '🚚', 'ringkas' => 'Minimal belanja Rp400.000', 'min_belanja' => 400000, 'potongan_tetap' => 3000, 'gratis_ongkir' => true],

            // Jam emas
            ['slug' => 'jam-emas-siang', 'judul' => 'Jam Emas Siang: Diskon Rp3.000', 'grup' => 'jam-emas', 'emoji' => '⚡', 'ringkas' => 'Jam 12.00–14.00, minimal belanja Rp100.000', 'min_belanja' => 100000, 'potongan_tetap' => 3000, 'kuota_harian' => 500, 'syarat' => ['jam_emas' => 'siang']],
            ['slug' => 'jam-emas-malam', 'judul' => 'Jam Emas Malam: Diskon Rp4.000', 'grup' => 'jam-emas', 'emoji' => '⚡', 'ringkas' => 'Jam 18.00–20.00, minimal belanja Rp150.000', 'min_belanja' => 150000, 'potongan_tetap' => 4000, 'kuota_harian' => 300, 'syarat' => ['jam_emas' => 'malam']],

            // Payday
            ['slug' => 'payday-1', 'judul' => 'Payday: Diskon Rp4.000', 'grup' => 'payday', 'emoji' => '💸', 'ringkas' => 'Tanggal 25–27, minimal belanja Rp150.000', 'min_belanja' => 150000, 'potongan_tetap' => 4000, 'syarat' => ['payday' => '25-27']],
            ['slug' => 'payday-2', 'judul' => 'Payday: Diskon Rp5.000', 'grup' => 'payday', 'emoji' => '💸', 'ringkas' => 'Tanggal 28–30, minimal belanja Rp200.000', 'min_belanja' => 200000, 'potongan_tetap' => 5000, 'syarat' => ['payday' => '28-30']],
            ['slug' => 'payday-3', 'judul' => 'Payday: Diskon Rp7.000', 'grup' => 'payday', 'emoji' => '💸', 'ringkas' => 'Tanggal 31 & 1, minimal belanja Rp300.000', 'min_belanja' => 300000, 'potongan_tetap' => 7000, 'syarat' => ['payday' => '31-1']],

            // Weekend
            ['slug' => 'weekend-15', 'judul' => 'Weekend Super: Diskon Rp2.500', 'grup' => 'weekend', 'emoji' => '🎉', 'ringkas' => 'Sabtu–Minggu, minimal belanja Rp120.000', 'min_belanja' => 120000, 'potongan_tetap' => 2500, 'kuota_harian' => 1000, 'syarat' => ['weekend' => true]],
            ['slug' => 'weekend-35', 'judul' => 'Weekend Super: Diskon Rp6.000', 'grup' => 'weekend', 'emoji' => '🎉', 'ringkas' => 'Sabtu–Minggu, minimal belanja Rp250.000', 'min_belanja' => 250000, 'potongan_tetap' => 6000, 'kuota_harian' => 500, 'syarat' => ['weekend' => true]],

            // Bundle
            ['slug' => 'bundle-fresh', 'judul' => 'Bundle Fresh: Diskon Rp1.500', 'grup' => 'bundle', 'emoji' => '🥬', 'ringkas' => 'Belanja buah/sayur/daging/seafood minimal Rp75.000', 'min_belanja' => 75000, 'potongan_tetap' => 1500, 'kategori_bundle' => ['buah', 'sayur', 'daging', 'seafood']],
            ['slug' => 'bundle-grocery', 'judul' => 'Bundle Sembako: Diskon Rp3.000', 'grup' => 'bundle', 'emoji' => '🥛', 'ringkas' => 'Belanja sembako minimal Rp100.000', 'min_belanja' => 100000, 'potongan_tetap' => 3000, 'kategori_bundle' => ['pokok', 'telur', 'bumbu', 'susu']],
            ['slug' => 'bundle-lengkap', 'judul' => 'Belanja Lengkap: Diskon Rp5.000', 'grup' => 'bundle', 'emoji' => '🎁', 'ringkas' => 'Dari 3 kategori berbeda, minimal total Rp200.000', 'min_belanja' => 200000, 'potongan_tetap' => 5000, 'syarat' => ['min_kategori' => 3]],

            // Cashback
            ['slug' => 'cashback5', 'judul' => 'Cashback 2%', 'grup' => 'cashback', 'emoji' => '💰', 'ringkas' => 'Minimal belanja Rp100.000, maks Rp3.000', 'min_belanja' => 100000, 'cashback_persen' => 2, 'cashback_maks' => 3000],
            ['slug' => 'cashback7', 'judul' => 'Cashback 2,5%', 'grup' => 'cashback', 'emoji' => '💰', 'ringkas' => 'Minimal belanja Rp200.000, maks Rp6.000', 'min_belanja' => 200000, 'cashback_persen' => 2.5, 'cashback_maks' => 6000],
            ['slug' => 'cashback10', 'judul' => 'Cashback 3%', 'grup' => 'cashback', 'emoji' => '💰', 'ringkas' => 'Minimal belanja Rp500.000, maks Rp12.000', 'min_belanja' => 500000, 'cashback_persen' => 3, 'cashback_maks' => 12000],

            // Referral
            ['slug' => 'traktiran', 'kode' => 'TRAKTIR3', 'judul' => 'Traktiran Teman: Diskon Rp3.000', 'grup' => 'referral', 'emoji' => '🤝', 'ringkas' => 'Pakai kode teman, minimal belanja Rp200.000', 'min_belanja' => 200000, 'potongan_tetap' => 3000, 'kuota_per_user' => 1, 'syarat' => ['transaksi_ke' => 1]],
        ];
    }
}
