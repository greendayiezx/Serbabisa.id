<?php

namespace App\Services;

/**
 * Tarif "Cek & Tambah Freon".
 *
 * Yang dijual di muka HANYA PEMERIKSAAN. AC yang kurang dingin belum tentu
 * kekurangan freon: bisa filter kotor, kapasitor lemah, atau kebocoran pipa —
 * dan freon berada di sistem tertutup, jadi tekanan yang turun berarti ada yang
 * bocor, bukan sekadar "habis". Menagih pengisian sebelum diperiksa berarti
 * menjual pekerjaan yang belum tentu dibutuhkan.
 *
 * Pekerjaan lanjutan (PEKERJAAN di bawah) baru ditawarkan SETELAH diagnosis,
 * dan harus disetujui pelanggan. Biaya pemeriksaan dikreditkan ke total kalau
 * pekerjaannya jadi dikerjakan — lihat DIKREDITKAN.
 */
class FreonTarif
{
    /** Kunjungan pertama sudah termasuk pemeriksaan satu unit. */
    public const BIAYA_PEMERIKSAAN = 50_000;

    /** Unit kedua dan seterusnya dalam satu kunjungan. */
    public const BIAYA_UNIT_TAMBAHAN = 25_000;

    /** Bagian biaya nyata pemeriksaan: transport + waktu teknisi. */
    public const BIAYA_NYATA_PEMERIKSAAN = 28_000;

    /**
     * Biaya pemeriksaan dipotong dari total kalau pelanggan menyetujui
     * rekomendasi servis pada kunjungan yang sama.
     */
    public const DIKREDITKAN = true;

    /**
     * Pekerjaan lanjutan yang mungkin direkomendasikan.
     *
     * @var array<string, array{nama:string, harga:int, biaya:int, satuan:string}>
     */
    public const PEKERJAAN = [
        'perbaikan-bocor' => ['nama' => 'Perbaikan titik kebocoran (las/flare)', 'harga' => 150_000, 'biaya' => 85_000, 'satuan' => 'titik'],
        'vakum' => ['nama' => 'Vakum sistem', 'harga' => 100_000, 'biaya' => 55_000, 'satuan' => 'unit'],
        'freon-r32' => ['nama' => 'Pengisian freon R32 (full)', 'harga' => 300_000, 'biaya' => 175_000, 'satuan' => 'unit'],
        'freon-r410a' => ['nama' => 'Pengisian freon R410A (full)', 'harga' => 350_000, 'biaya' => 205_000, 'satuan' => 'unit'],
        'freon-r22' => ['nama' => 'Pengisian freon R22 (full)', 'harga' => 250_000, 'biaya' => 145_000, 'satuan' => 'unit'],
        'ganti-kapasitor' => ['nama' => 'Ganti kapasitor', 'harga' => 175_000, 'biaya' => 100_000, 'satuan' => 'unit'],
    ];

    public const KELUHAN = [
        'kurang-dingin',
        'tidak-dingin',
        'bunga-es',
        'pernah-bocor',
        'freon-lama',
        'hanya-cek',
        'tidak-tahu',
    ];

    public const MEREK = ['daikin', 'panasonic', 'sharp', 'lg', 'samsung', 'polytron', 'lainnya'];

    /**
     * Jenis freon boleh "tidak-tahu".
     *
     * Memaksa pelanggan menebak R32/R410A/R22 hanya menghasilkan data yang
     * salah — dan teknisi tetap harus membaca label unitnya di lokasi.
     */
    public const JENIS_FREON = ['r32', 'r410a', 'r22', 'tidak-tahu'];

    /** Slot kunjungan; teknisi butuh rentang, bukan jam persis. */
    public const SLOT = ['09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-17:00'];

    /**
     * @return array<string, mixed>
     */
    public function pemeriksaan(int $unit): array
    {
        $jumlah = max(1, $unit);
        $tambahan = ($jumlah - 1) * self::BIAYA_UNIT_TAMBAHAN;
        $total = self::BIAYA_PEMERIKSAAN + $tambahan;

        return [
            'unit' => $jumlah,
            'biaya_pemeriksaan' => self::BIAYA_PEMERIKSAAN,
            'biaya_unit_tambahan' => $tambahan,
            'total' => $total,
            'biaya' => self::BIAYA_NYATA_PEMERIKSAAN * $jumlah,
        ];
    }

    /**
     * Total pekerjaan lanjutan hasil diagnosis.
     *
     * @param  list<string>  $pekerjaan
     * @return array<string, mixed>
     */
    public function rekomendasi(array $pekerjaan, int $biayaPemeriksaan): array
    {
        $dipilih = array_values(array_intersect($pekerjaan, array_keys(self::PEKERJAAN)));

        $baris = [];
        $subtotal = 0;
        $biaya = 0;

        foreach ($dipilih as $id) {
            $p = self::PEKERJAAN[$id];
            $baris[] = ['id' => $id, 'nama' => $p['nama'], 'harga' => $p['harga'], 'satuan' => $p['satuan']];
            $subtotal += $p['harga'];
            $biaya += $p['biaya'];
        }

        // Pemeriksaan yang sudah dibayar dikreditkan ke total, bukan ditagih
        // dua kali.
        $kredit = self::DIKREDITKAN ? min($biayaPemeriksaan, $subtotal) : 0;

        return [
            'pekerjaan' => $dipilih,
            'baris' => $baris,
            'subtotal' => $subtotal,
            'kredit_pemeriksaan' => $kredit,
            'total' => $subtotal - $kredit,
            'biaya' => $biaya,
        ];
    }
}
