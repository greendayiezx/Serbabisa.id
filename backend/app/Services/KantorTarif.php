<?php

namespace App\Services;

/**
 * Perhitungan harga BisaBersih Kantor.
 *
 * BEDA MENDASAR dengan BersihTarif: rumah ditagih per JAM per cleaner, kantor
 * per KUNJUNGAN berdasarkan luas area dan jumlah fasilitas. Karena itu kelasnya
 * terpisah, bukan menumpang rumus rumah.
 *
 * Kembarannya ada di frontend/src/lib/hargaBersihKantor.ts untuk menampilkan
 * estimasi. Yang MENAGIH adalah kelas ini — angka dari browser tidak pernah
 * dipercaya. KantorCheckoutTest menjaga keduanya menghasilkan angka yang sama.
 */
class KantorTarif
{
    /** Tarif per meter persegi per kunjungan. */
    public const TARIF_PER_M2 = 1_200;

    /** Tagihan minimum sekali kunjungan — kru tetap harus berangkat. */
    public const MINIMUM_KUNJUNGAN = 250_000;

    public const TARIF_WORKSTATION = 3_000;
    public const TARIF_RUANG_MEETING = 25_000;
    public const TARIF_TOILET = 35_000;
    public const TARIF_PANTRY = 30_000;

    /** Lantai kedua dan seterusnya menambah waktu mobilisasi kru. */
    public const TARIF_LANTAI_TAMBAHAN = 50_000;

    /**
     * Jenis kantor menentukan luas yang dipakai menghitung.
     *
     * 'besar' sengaja TIDAK bisa dipesan langsung — luasnya tidak berbatas,
     * jadi menagihnya dari satu angka wakil berarti menagih terlalu murah untuk
     * kantor yang jauh lebih luas. Lihat bisaDipesanLangsung().
     */
    public const JENIS = [
        'kecil' => ['nama' => 'Small Office', 'luas' => 50, 'langsung' => true],
        'sedang' => ['nama' => 'Medium Office', 'luas' => 150, 'langsung' => true],
        'besar' => ['nama' => 'Large Office', 'luas' => 250, 'langsung' => false],
    ];

    public const PAKET = [
        'basic' => ['nama' => 'Basic', 'pengali' => 1.0],
        'professional' => ['nama' => 'Professional', 'pengali' => 1.15],
        'executive' => ['nama' => 'Executive', 'pengali' => 1.4],
    ];

    public const FREKUENSI = [
        'sekali' => ['label' => 'Sekali', 'diskon' => 0.0, 'kunjungan_per_bulan' => 1],
        'mingguan' => ['label' => '1x / Minggu', 'diskon' => 0.08, 'kunjungan_per_bulan' => 4],
        '2x-minggu' => ['label' => '2x / Minggu', 'diskon' => 0.10, 'kunjungan_per_bulan' => 8],
        '3x-minggu' => ['label' => '3x / Minggu', 'diskon' => 0.12, 'kunjungan_per_bulan' => 12],
        'harian' => ['label' => 'Setiap Hari Kerja', 'diskon' => 0.15, 'kunjungan_per_bulan' => 22],
    ];

    public const ADD_ON = [
        'kaca-gedung' => ['nama' => 'Cuci Kaca Gedung', 'harga' => 150_000, 'biaya' => 85_000],
        'karpet' => ['nama' => 'Cuci Karpet', 'harga' => 120_000, 'biaya' => 70_000],
        'poles-lantai' => ['nama' => 'Poles Lantai', 'harga' => 200_000, 'biaya' => 120_000],
        'high-dusting' => ['nama' => 'High Dusting', 'harga' => 100_000, 'biaya' => 55_000],
        'deep-toilet' => ['nama' => 'Deep Cleaning Toilet/Pantry', 'harga' => 150_000, 'biaya' => 85_000],
        'disinfeksi' => ['nama' => 'Disinfeksi Ruangan', 'harga' => 250_000, 'biaya' => 140_000],
    ];

    /** Bagian biaya nyata dari harga layanan (upah kru + bahan). */
    public const RASIO_BIAYA_LAYANAN = 0.62;

    /** Luas yang wajar dikerjakan satu orang dalam sekali kunjungan. */
    public const LUAS_PER_KRU = 100;

    /**
     * Berapa orang yang diberangkatkan untuk satu kunjungan.
     *
     * Dua orang adalah lantai bawahnya, bukan pembulatan: kantor sekecil apa
     * pun tetap punya toilet dan pantry yang dikerjakan bersamaan dengan area
     * kerja, dan satu orang sendirian akan melewati jam kantor. Paket Executive
     * menambah satu orang karena cakupannya (kaca dalam, detail furnitur)
     * memang pekerjaan tambahan, bukan pekerjaan yang sama dikerjakan lebih
     * rapi.
     *
     * Angka ini ikut disimpan pada pesanan supaya pelanggan melihat jumlah yang
     * sama dengan yang dipakai penjadwalan — bukan tebakan layar.
     */
    public static function jumlahKru(int $luas, string $paketId): int
    {
        $kru = max(2, (int) ceil($luas / self::LUAS_PER_KRU));
        if ($paketId === 'executive') {
            $kru++;
        }

        return min($kru, 8);
    }

    /**
     * Apakah jenis kantor ini boleh dipesan langsung tanpa survei.
     *
     * Kantor besar tidak: halaman hanya tahu "di atas 150 m²", dan menagih
     * kantor 800 m² dengan tarif 250 m² merugikan kedua pihak — pelanggan
     * dapat kru yang kurang, platform menanggung selisihnya.
     */
    public static function bisaDipesanLangsung(string $jenis): bool
    {
        return (bool) (self::JENIS[$jenis]['langsung'] ?? false);
    }

    /**
     * @param  list<string>  $addOnDipilih
     * @return array<string, mixed>
     */
    public function hitung(
        string $jenisId,
        string $paketId,
        int $workstation,
        int $ruangMeeting,
        int $toilet,
        int $pantry,
        array $addOnDipilih,
        string $frekuensiId,
        ?int $luasM2 = null,
    ): array {
        $jenis = self::JENIS[$jenisId] ?? self::JENIS['sedang'];
        $paket = self::PAKET[$paketId] ?? self::PAKET['basic'];
        $frekuensi = self::FREKUENSI[$frekuensiId] ?? self::FREKUENSI['sekali'];

        /*
         * Luas sebenarnya menang atas luas acuan jenis kantor.
         *
         * Jenis kantor hanya rentang; 'besar' berarti "di atas 150 m²" tanpa
         * batas atas. Saat pelanggan menyebutkan luas sesungguhnya di form
         * penawaran, angka itulah yang dipakai — kalau tidak, dokumen bisa
         * menuliskan "±820 m²" sambil menagih seperti 250 m².
         */
        $luas = $luasM2 !== null && $luasM2 > 0 ? $luasM2 : $jenis['luas'];

        $dariLuas = $luas * self::TARIF_PER_M2;
        $dariFasilitas =
            max(0, $workstation) * self::TARIF_WORKSTATION +
            max(0, $ruangMeeting) * self::TARIF_RUANG_MEETING +
            max(0, $toilet) * self::TARIF_TOILET +
            max(0, $pantry) * self::TARIF_PANTRY;

        // Jumlah lantai tidak ditanyakan di alur pesan langsung; dihitung satu
        // lantai. Kantor bertingkat memakai jalur penawaran.
        $dasar = (int) round(($dariLuas + $dariFasilitas) * $paket['pengali']);

        $layanan = max($dasar, self::MINIMUM_KUNJUNGAN);
        $penyesuaianMinimum = $layanan - $dasar;

        $terpilih = array_values(array_intersect($addOnDipilih, array_keys(self::ADD_ON)));
        $addOn = 0;
        $biayaAddOn = 0;
        $barisAddOn = [];
        foreach ($terpilih as $id) {
            $addOn += self::ADD_ON[$id]['harga'];
            $biayaAddOn += self::ADD_ON[$id]['biaya'];
            $barisAddOn[] = ['id' => $id, 'nama' => self::ADD_ON[$id]['nama'], 'harga' => self::ADD_ON[$id]['harga']];
        }

        $subtotal = $layanan + $addOn;
        $diskonFrekuensi = (int) round($layanan * $frekuensi['diskon']);
        $totalPerKunjungan = $subtotal - $diskonFrekuensi;

        return [
            'jenis_kantor' => $jenisId,
            'nama_jenis' => $jenis['nama'],
            'luas_acuan' => $luas,
            'paket' => $paketId,
            'nama_paket' => $paket['nama'],
            'frekuensi' => $frekuensiId,
            'label_frekuensi' => $frekuensi['label'],
            'layanan' => $layanan,
            'penyesuaian_minimum' => $penyesuaianMinimum,
            'add_on' => $addOn,
            'baris_add_on' => $barisAddOn,
            'subtotal' => $subtotal,
            'diskon_frekuensi' => $diskonFrekuensi,
            'total_per_kunjungan' => $totalPerKunjungan,
            'total_per_bulan' => $totalPerKunjungan * $frekuensi['kunjungan_per_bulan'],
            'jumlah_kru' => self::jumlahKru($luas, $paketId),
            'biaya' => (int) round($layanan * self::RASIO_BIAYA_LAYANAN + $biayaAddOn),
        ];
    }
}
