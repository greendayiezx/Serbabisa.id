<?php

namespace App\Services;

/**
 * Tarif Servis AC.
 *
 * Dijual PER UNIT: satu rumah bisa punya beberapa AC, dan yang menentukan
 * pekerjaan adalah jumlah unitnya, bukan luas ruangan.
 *
 * Dua potongan bawaan yang bukan kode promo, melainkan bagian dari harga:
 *
 * - Dua unit atau lebih dapat Rp20.000 — teknisi datang sekali untuk dua
 *   pekerjaan, jadi biaya perjalanannya memang terbagi.
 * - Tiga unit atau lebih membebaskan biaya kunjungan sekalian.
 *
 * Kapasitas (PK) dan tipe AC TIDAK mengubah harga. Keduanya direkam untuk
 * teknisi supaya ia datang dengan alat yang benar; menagihkannya tanpa dasar
 * biaya yang jelas hanya akan membuat estimasi meleset dari tagihan.
 */
class ACTarif
{
    /**
     * @var array<string, array{nama:string, harga:int, deskripsi:string, biaya:int}>
     */
    public const PAKET = [
        'standard' => [
            'nama' => 'Cuci Standard',
            'harga' => 100_000,
            'deskripsi' => 'Pembersihan filter, unit indoor & outdoor, serta pengecekan drainase.',
            'biaya' => 58_000,
        ],
        'premium' => [
            'nama' => 'Cuci Premium',
            'harga' => 150_000,
            'deskripsi' => 'Cuci menyeluruh dengan perlindungan area kerja ekstra aman.',
            'biaya' => 86_000,
        ],
        'deep' => [
            'nama' => 'Deep Cleaning AC',
            'harga' => 250_000,
            'deskripsi' => 'Pembersihan intensif untuk AC sangat kotor atau berbau tidak sedap.',
            'biaya' => 145_000,
        ],
    ];

    /** Sekali per kunjungan, bukan per unit. */
    public const BIAYA_KUNJUNGAN = 10_000;

    /** Potongan bundling dua unit atau lebih. */
    public const DISKON_2_UNIT = 20_000;
    public const MIN_UNIT_DISKON = 2;

    /** Mulai jumlah ini, biaya kunjungan dibebaskan. */
    public const MIN_UNIT_GRATIS_KUNJUNGAN = 3;

    public const TIPE = ['split', 'inverter', 'cassette', 'standing', 'tidak-tahu'];
    public const KAPASITAS = ['0.5', '1', '1.5', '2', 'tidak-tahu'];
    public const TERAKHIR_CUCI = ['<3-bulan', '3-6-bulan', '>6-bulan', 'belum-pernah'];
    public const KONDISI = ['berbau', 'kurang-dingin', 'bocor', 'berdebu', 'tidak-ada-keluhan', 'lainnya'];

    /** Jadwal rutin: potongannya berlaku untuk kunjungan BERIKUTNYA, bukan ini. */
    public const RUTIN = ['3-bulan', '6-bulan'];
    public const DISKON_RUTIN_PERSEN = 20;

    /**
     * @return array<string, mixed>
     */
    public function hitung(string $paketId, int $unit): array
    {
        $paket = self::PAKET[$paketId] ?? self::PAKET['standard'];
        $jumlah = max(1, $unit);

        $layanan = $paket['harga'] * $jumlah;

        $gratisKunjungan = $jumlah >= self::MIN_UNIT_GRATIS_KUNJUNGAN;
        $biayaKunjungan = $gratisKunjungan ? 0 : self::BIAYA_KUNJUNGAN;

        $diskonBundling = $jumlah >= self::MIN_UNIT_DISKON ? self::DISKON_2_UNIT : 0;

        $total = $layanan + $biayaKunjungan - $diskonBundling;

        return [
            'paket' => $paketId,
            'nama_paket' => $paket['nama'],
            'deskripsi_paket' => $paket['deskripsi'],
            'harga_per_unit' => $paket['harga'],
            'unit' => $jumlah,
            'layanan' => $layanan,
            'biaya_kunjungan' => $biayaKunjungan,
            'gratis_kunjungan' => $gratisKunjungan,
            'diskon_bundling' => $diskonBundling,
            'total' => $total,
            'biaya' => $paket['biaya'] * $jumlah,
        ];
    }
}
