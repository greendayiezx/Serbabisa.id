<?php

namespace App\Services;

/**
 * Tarif BisaAngkut — port dari frontend (AngkutDetailView/AngkutConfirmView).
 *
 * Sumber kebenaran harga ada di server: klien hanya mengirim pilihan (id
 * kendaraan/layanan/proteksi + jumlah helper), total dihitung ulang di sini
 * supaya tidak bisa dimanipulasi dari browser.
 */
class AngkutTarif
{
    public const HELPER_PER_ORANG = 50000;

    public const MAKS_HELPER = 4;

    /** id kendaraan => label + harga per layanan. */
    private const KENDARAAN = [
        'motor_box' => ['label' => 'Motor Box', 'harga' => ['instant' => 35000, 'instant_hemat' => 30000, 'sameday' => 25000]],
        'pickup_bak' => ['label' => 'Pickup Bak', 'harga' => ['instant' => 80000, 'instant_hemat' => 70000, 'sameday' => 55000]],
        'blind_van' => ['label' => 'Blind Van', 'harga' => ['instant' => 120000, 'instant_hemat' => 105000, 'sameday' => 85000]],
    ];

    private const LAYANAN = [
        'instant' => 'Instant',
        'instant_hemat' => 'Instant Hemat',
        'sameday' => 'SameDay',
    ];

    private const PROTEKSI = [
        'silver' => ['label' => 'Perlindungan Silver', 'harga' => 1000],
        'gold' => ['label' => 'Perlindungan Gold', 'harga' => 2000],
        'platinum' => ['label' => 'Perlindungan Platinum', 'harga' => 5000],
    ];

    /** @return list<string> */
    public static function idKendaraan(): array
    {
        return array_keys(self::KENDARAAN);
    }

    /** @return list<string> */
    public static function idLayanan(): array
    {
        return array_keys(self::LAYANAN);
    }

    /** @return list<string> */
    public static function idProteksi(): array
    {
        return array_keys(self::PROTEKSI);
    }

    public function hargaTransport(string $kendaraan, string $layanan): int
    {
        return self::KENDARAAN[$kendaraan]['harga'][$layanan] ?? 0;
    }

    public function labelKendaraan(string $kendaraan): string
    {
        return self::KENDARAAN[$kendaraan]['label'] ?? $kendaraan;
    }

    public function labelLayanan(string $layanan): string
    {
        return self::LAYANAN[$layanan] ?? $layanan;
    }

    /** @return array{label:string,harga:int} */
    public function proteksi(string $id): array
    {
        return self::PROTEKSI[$id] ?? ['label' => 'Tanpa Perlindungan', 'harga' => 0];
    }

    public function hargaHelper(int $jumlah): int
    {
        return max(0, min($jumlah, self::MAKS_HELPER)) * self::HELPER_PER_ORANG;
    }
}
