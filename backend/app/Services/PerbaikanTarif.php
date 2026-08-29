<?php

namespace App\Services;

/**
 * Tarif Perbaikan & Pasang AC.
 *
 * Dua jalur dengan sifat uang yang berbeda, dan itu sengaja tidak disatukan:
 *
 * 1. PERBAIKAN ditagih seperti pemeriksaan freon — yang dibayar di muka hanya
 *    kunjungan diagnosisnya. Harga perbaikan baru muncul setelah teknisi
 *    melihat unitnya, dan harus disetujui pelanggan. Menyebut angka perbaikan
 *    sebelum diagnosis berarti menebak, lalu menagih tebakan itu.
 *
 * 2. PASANG / PINDAH tidak ditagih sama sekali di aplikasi. Harganya bergantung
 *    panjang pipa, jalur kabel, bracket, ketinggian, dan akses lokasi — hal-hal
 *    yang tidak bisa dibaca dari formulir. Yang dikirim adalah PERMINTAAN
 *    PENAWARAN; angkanya menyusul setelah foto diperiksa atau lokasi disurvei.
 */
class PerbaikanTarif
{
    /** Kunjungan diagnosis; sama dengan pemeriksaan freon karena kerjanya sama. */
    public const BIAYA_PEMERIKSAAN = FreonTarif::BIAYA_PEMERIKSAAN;

    public const BIAYA_UNIT_TAMBAHAN = FreonTarif::BIAYA_UNIT_TAMBAHAN;

    /**
     * Rentang yang boleh disebut di layar untuk pemasangan.
     *
     * Ditulis sebagai RENTANG, bukan satu angka: paket lengkap bergerak antara
     * keduanya tergantung material dan kesulitan lokasi. Satu angka tunggal di
     * layar akan dibaca sebagai harga pasti, dan penawaran yang datang kemudian
     * akan terasa seperti harga naik.
     */
    public const PASANG_MULAI = 890_000;

    public const PASANG_SAMPAI = 1_500_000;

    /** @var list<string> */
    public const KELUHAN = [
        'tidak-dingin',
        'kurang-dingin',
        'bocor',
        'berisik',
        'mati-total',
        'tidak-bisa-menyala',
        'outdoor-tidak-berputar',
        'mengeluarkan-bau',
        'kode-error',
        'lainnya',
    ];

    /** @var list<string> */
    public const MULAI_TERJADI = ['hari-ini', '1-7-hari', 'lebih-1-minggu', 'tidak-tahu'];

    /** @var list<string> */
    public const JENIS_PEKERJAAN = [
        'pasang-baru',
        'bongkar-pasang',
        'pindah-lokasi',
        'ganti-unit',
        'beberapa-unit',
    ];

    /** @var list<string> */
    public const KETERSEDIAAN_UNIT = ['sudah-ada', 'butuh-rekomendasi'];

    /** @var list<string> */
    public const KEBUTUHAN = ['jasa-saja', 'jasa-material', 'rekomendasi-unit'];

    /** @var list<string> */
    public const LOKASI_INDOOR = ['kamar-tidur', 'ruang-tamu', 'ruang-kantor', 'toko', 'lainnya'];

    /** @var list<string> */
    public const LOKASI_OUTDOOR = ['balkon', 'dinding-luar', 'atap', 'lantai', 'area-khusus', 'tidak-tahu'];

    /** @var list<string> */
    public const MATERIAL = [
        'pipa-tambahan',
        'kabel-tambahan',
        'bracket-outdoor',
        'selang-pembuangan',
        'stop-kontak',
        'bobok-tembok',
        'penutup-jalur-pipa',
        'tangga-alat-khusus',
    ];

    /**
     * Cara pelanggan ingin menerima penawaran.
     *
     * Survei dianjurkan untuk pekerjaan yang tidak bisa dinilai dari foto —
     * lantai tinggi, jalur pipa panjang, outdoor sulit dijangkau, pindah AC,
     * banyak unit, bobok tembok, atau pekerjaan listrik.
     */
    public const CARA_PENAWARAN = ['estimasi-foto', 'survei-lokasi', 'konsultasi'];

    /** Pekerjaan yang perlu disurvei, bukan cukup difoto. */
    public const WAJIB_SURVEI = ['pindah-lokasi', 'beberapa-unit'];

    /**
     * @return array<string, mixed>
     */
    public function pemeriksaan(int $unit): array
    {
        $jumlah = max(1, $unit);
        $tambahan = ($jumlah - 1) * self::BIAYA_UNIT_TAMBAHAN;

        return [
            'unit' => $jumlah,
            'biaya_pemeriksaan' => self::BIAYA_PEMERIKSAAN,
            'biaya_unit_tambahan' => $tambahan,
            'total' => self::BIAYA_PEMERIKSAAN + $tambahan,
            'biaya' => FreonTarif::BIAYA_TRANSPORT + FreonTarif::BIAYA_PERIKSA_UNIT * $jumlah,
        ];
    }
}
