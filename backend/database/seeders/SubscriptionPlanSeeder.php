<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Paket langganan BisaBelanja. Angka disalin dari konstanta LANGGANAN di
 * frontend (src/lib/promo.ts) agar backend menjadi acuan tunggal.
 */
class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'kode' => 'basic',
                'nama' => 'Basic',
                'harga' => 29000,
                'diskon_per_transaksi' => 0,
                'kuota_diskon' => 0,
                'min_ongkir_gratis' => 100000,
                'kuota_ongkir' => 12,
                'benefit' => [
                    'Gratis ongkir 12x/bulan (min. belanja Rp100.000)',
                    'Tanpa biaya tambahan di jam sibuk',
                ],
            ],
            [
                'kode' => 'premium',
                'nama' => 'Premium',
                'harga' => 49000,
                'diskon_per_transaksi' => 1000,
                'kuota_diskon' => 20,
                'min_ongkir_gratis' => 75000,
                'kuota_ongkir' => 20,
                'benefit' => [
                    'Gratis ongkir 20x/bulan (min. belanja Rp75.000)',
                    'Potongan Rp1.000 tiap transaksi, sampai 20x/bulan',
                    'Prioritas shopper: pesanan dikerjakan lebih dulu',
                ],
            ],
            [
                'kode' => 'family',
                'nama' => 'Family',
                'harga' => 79000,
                'diskon_per_transaksi' => 1500,
                'kuota_diskon' => 30,
                'min_ongkir_gratis' => 0,
                'kuota_ongkir' => 30,
                'benefit' => [
                    'Gratis ongkir 30x/bulan, tanpa minimum belanja',
                    'Potongan Rp1.500 tiap transaksi, sampai 30x/bulan',
                    'Dedicated shopper',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['kode' => $plan['kode']], [...$plan, 'is_active' => true]);
        }
    }
}
