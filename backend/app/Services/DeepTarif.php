<?php

namespace App\Services;

/**
 * Tarif BisaBersih Deep Cleaning.
 *
 * Berbeda dari BersihTarif (rumah, per jam per cleaner) dan KantorTarif (per m²
 * dan fasilitas): deep cleaning dijual per PAKET, dengan luas dan jumlah ruangan
 * standar yang sudah termasuk. Kelebihannya baru ditagih.
 *
 * Layanan tambahan yang SUDAH termasuk paket tidak pernah ditagih dua kali —
 * lihat hitung(). Itu bukan sekadar kerapian tampilan: harga paketnya memang
 * sudah dinaikkan sebesar layanan itu, jadi menagihnya lagi berarti pelanggan
 * membayar dua kali untuk pekerjaan yang sama.
 */
class DeepTarif
{
    /**
     * Harga paket sudah termasuk layanan pada 'termasuk'.
     *
     * @var array<string, array{nama:string, harga:int, deskripsi:string, termasuk:list<string>}>
     */
    public const PAKET = [
        'move_in' => [
            'nama' => 'Paket Move-In',
            'harga' => 625_000,
            'deskripsi' => 'Untuk rumah baru/pindahan. Fokus pada debu halus dan sanitasi.',
            'termasuk' => ['tungau'],
        ],
        'pasca_renovasi' => [
            'nama' => 'Paket Pasca Renovasi',
            'harga' => 900_000,
            'deskripsi' => 'Pembersihan sisa semen, cat, dan debu konstruksi.',
            'termasuk' => ['scrubbing'],
        ],
        'sanitasi_total' => [
            'nama' => 'Paket Sanitasi Total',
            'harga' => 775_000,
            'deskripsi' => 'Fokus pada pembasmian bakteri dan tungau.',
            'termasuk' => ['fogging', 'tungau'],
        ],
    ];

    /** Lingkup yang sudah termasuk harga paket. */
    public const LUAS_TERMASUK = 50;
    public const RUANGAN_TERMASUK = 3;

    /** Kelebihan di atas lingkup standar. */
    public const TARIF_LUAS = 3_000;
    public const TARIF_RUANGAN = 25_000;

    /**
     * @var array<string, array{nama:string, harga:int, satuan:string, per_ruangan:bool, biaya:int}>
     */
    public const ADD_ON = [
        'scrubbing' => ['nama' => 'Scrubbing Lantai Mesin', 'harga' => 50_000, 'satuan' => 'ruangan', 'per_ruangan' => true, 'biaya' => 28_000],
        'tungau' => ['nama' => 'Sedot Tungau Kasur', 'harga' => 75_000, 'satuan' => 'kasur', 'per_ruangan' => false, 'biaya' => 40_000],
        'fogging' => ['nama' => 'Fogging Disinfektan', 'harga' => 100_000, 'satuan' => 'rumah', 'per_ruangan' => false, 'biaya' => 55_000],
    ];

    /** Bagian biaya nyata dari harga paket (upah kru + bahan). */
    public const RASIO_BIAYA_PAKET = 0.62;

    /** Berapa orang diberangkatkan; deep cleaning tidak pernah satu orang. */
    public static function jumlahKru(int $luas, int $ruangan): int
    {
        return min(6, max(2, (int) ceil(max($luas / 60, $ruangan / 2))));
    }

    /** Perkiraan lama pengerjaan dalam jam. */
    public static function durasiJam(int $luas, int $ruangan): int
    {
        return min(10, max(4, (int) ceil($luas / 25 + $ruangan / 2)));
    }

    /**
     * @param  list<string>  $addOnDipilih
     * @return array<string, mixed>
     */
    public function hitung(string $paketId, int $luasM2, int $jumlahRuangan, array $addOnDipilih): array
    {
        $paket = self::PAKET[$paketId] ?? self::PAKET['move_in'];
        $luas = max(1, $luasM2);
        $ruangan = max(1, $jumlahRuangan);

        $lebihLuas = max(0, $luas - self::LUAS_TERMASUK);
        $lebihRuangan = max(0, $ruangan - self::RUANGAN_TERMASUK);

        $biayaLuas = $lebihLuas * self::TARIF_LUAS;
        $biayaRuangan = $lebihRuangan * self::TARIF_RUANGAN;

        /*
         * Yang sudah termasuk paket dibuang dari daftar pilihan — bukan ditolak.
         * Klien lama yang masih mengirimkannya tidak perlu gagal; cukup tidak
         * ditagih dua kali.
         */
        $dipilih = array_values(array_unique(array_intersect($addOnDipilih, array_keys(self::ADD_ON))));
        $dipilih = array_values(array_diff($dipilih, $paket['termasuk']));

        $addOn = 0;
        $biayaAddOn = 0;
        $barisAddOn = [];
        foreach ($dipilih as $id) {
            $a = self::ADD_ON[$id];
            $qty = $a['per_ruangan'] ? $ruangan : 1;
            $subtotal = $a['harga'] * $qty;

            $addOn += $subtotal;
            $biayaAddOn += $a['biaya'] * $qty;
            $barisAddOn[] = [
                'id' => $id,
                'nama' => $a['nama'],
                'harga_satuan' => $a['harga'],
                'qty' => $qty,
                'satuan' => $a['satuan'],
                'subtotal' => $subtotal,
            ];
        }

        $total = $paket['harga'] + $biayaLuas + $biayaRuangan + $addOn;

        return [
            'paket' => $paketId,
            'nama_paket' => $paket['nama'],
            'deskripsi_paket' => $paket['deskripsi'],
            'termasuk' => $paket['termasuk'],
            'luas_m2' => $luas,
            'jumlah_ruangan' => $ruangan,
            'harga_paket' => $paket['harga'],
            'kelebihan_luas' => $lebihLuas,
            'biaya_luas' => $biayaLuas,
            'kelebihan_ruangan' => $lebihRuangan,
            'biaya_ruangan' => $biayaRuangan,
            'add_on' => $addOn,
            'baris_add_on' => $barisAddOn,
            'add_on_dipakai' => $dipilih,
            'total' => $total,
            'jumlah_kru' => self::jumlahKru($luas, $ruangan),
            'durasi_jam' => self::durasiJam($luas, $ruangan),
            'biaya' => (int) round($paket['harga'] * self::RASIO_BIAYA_PAKET) + $biayaAddOn,
        ];
    }
}
