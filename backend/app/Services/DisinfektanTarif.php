<?php

namespace App\Services;

/**
 * Tarif layanan Disinfektan.
 *
 * Dua hal yang dijaga katalog ini, dan keduanya soal janji:
 *
 * 1. DISINFEKSI BUKAN STERILISASI. Layanan ini mengurangi mikroba di permukaan
 *    yang sering disentuh; ia tidak membuat ruangan bebas penyakit, dan hasilnya
 *    bergantung kondisi permukaan, jenis produk, konsentrasi, serta waktu
 *    kontak. Karena itu tidak ada satu pun angka "waktu kontak" yang dipatok di
 *    sini — tiap produk punya labelnya sendiri, dan petugas mengikuti label
 *    beserta SDS-nya.
 * 2. PEKERJAAN BERISIKO TIDAK DITERIMA di layanan standar. Darah, cairan tubuh,
 *    limbah medis, dan area medis butuh SOP, personel, dan perlengkapan yang
 *    belum dimiliki — menerimanya lalu mengerjakannya seadanya jauh lebih
 *    berbahaya daripada menolak.
 */
class DisinfektanTarif
{
    /** @var list<string> */
    public const PROPERTI = ['rumah', 'apartemen', 'kos', 'kantor', 'toko'];

    /** Properti usaha ditagih lebih tinggi: areanya dilalui lebih banyak orang. */
    public const PROPERTI_USAHA = ['kantor', 'toko'];

    /** @var list<string> */
    public const LUAS = ['<50', '50-100', '101-300', '>300'];

    /**
     * Luas yang tidak bisa ditagih dari formulir.
     *
     * Di atas 300 m², selisih antara satu gedung dan gedung lain terlalu besar
     * untuk diwakili satu angka. Yang seperti ini lewat penawaran, bukan harga
     * pasang.
     */
    public const LUAS_PENAWARAN = '>300';

    /** @var list<string> */
    public const KONDISI = ['normal', 'banyak-orang', 'setelah-acara', 'setelah-sakit', 'sangat-kotor'];

    /**
     * Hal yang mengubah cara kerja petugas, bukan sekadar catatan.
     *
     * Anak kecil, hewan, dan alergi menentukan produk yang boleh dipakai;
     * elektronik menentukan cara aplikasinya; makanan terbuka harus diamankan
     * sebelum penyemprotan.
     */
    public const PERHATIAN = [
        'anak-kecil',
        'hewan-peliharaan',
        'alergi-bau',
        'makanan-terbuka',
        'elektronik-sensitif',
    ];

    /**
     * Penanda yang membuat pesanan DITOLAK, bukan disurcharge.
     *
     * Ini satu-satunya jawaban yang bukan soal harga: pekerjaannya memang di
     * luar kemampuan layanan ini, dan pelanggan diarahkan ke penyedia khusus.
     */
    public const PERHATIAN_DITOLAK = 'cairan-tubuh-berisiko';

    public const RUANGAN_TERMASUK = 3;

    public const TOILET_TERMASUK = 1;

    public const TARIF_RUANGAN_TAMBAHAN = 20_000;

    public const TARIF_TOILET_TAMBAHAN = 25_000;

    /**
     * Harga dasar menurut luas, dipisah hunian dan usaha.
     *
     * @var array<string, array<string, int>>
     */
    public const DASAR = [
        'hunian' => ['<50' => 120_000, '50-100' => 150_000, '101-300' => 300_000],
        'usaha' => ['<50' => 200_000, '50-100' => 250_000, '101-300' => 350_000],
    ];

    /**
     * Tambahan menurut kondisi area — bukan denda, melainkan kerja tambahan:
     * permukaan lebih banyak, pembersihan awal lebih lama, produk lebih banyak.
     *
     * @var array<string, array{tambahan:int, biaya:int, label:string}>
     */
    public const KONDISI_TAMBAHAN = [
        'normal' => ['tambahan' => 0, 'biaya' => 0, 'label' => 'Kondisi normal'],
        'banyak-orang' => ['tambahan' => 30_000, 'biaya' => 10_000, 'label' => 'Banyak orang beraktivitas'],
        'setelah-acara' => ['tambahan' => 30_000, 'biaya' => 10_000, 'label' => 'Setelah acara'],
        'setelah-sakit' => ['tambahan' => 60_000, 'biaya' => 20_000, 'label' => 'Setelah ada yang sakit'],
        'sangat-kotor' => ['tambahan' => 60_000, 'biaya' => 20_000, 'label' => 'Area sangat kotor'],
    ];

    /** Transport satu kunjungan. */
    public const BIAYA_TRANSPORT = 18_000;

    /**
     * Biaya nyata menurut luas: larutan disinfektan + waktu petugas.
     *
     * @var array<string, array{larutan:int, tenaga:int}>
     */
    public const BIAYA_LUAS = [
        '<50' => ['larutan' => 15_000, 'tenaga' => 60_000],
        '50-100' => ['larutan' => 25_000, 'tenaga' => 80_000],
        '101-300' => ['larutan' => 45_000, 'tenaga' => 140_000],
    ];

    public const BIAYA_RUANGAN_TAMBAHAN = 8_000;

    public const BIAYA_TOILET_TAMBAHAN = 10_000;

    /**
     * Area yang ditangani, per jenis properti.
     *
     * @var array<string, list<string>>
     */
    public const AREA = [
        'hunian' => [
            'Gagang pintu',
            'Sakelar lampu',
            'Meja dan kursi',
            'Remote',
            'Pegangan tangga',
            'Permukaan kamar mandi',
            'Dapur',
            'Area ruang keluarga',
        ],
        'usaha' => [
            'Meja kerja',
            'Keyboard dan mouse',
            'Gagang pintu',
            'Tombol lift',
            'Sakelar',
            'Meja meeting dan kursi',
            'Dispenser dan pantry',
            'Toilet dan resepsionis',
        ],
    ];

    /** @var list<string> */
    public const TIDAK_TERMASUK = [
        'Membersihkan jamur berat pada dinding',
        'Menghilangkan bau permanen',
        'Membersihkan darah atau cairan tubuh berisiko tinggi',
        'Menangani area medis',
        'Menyemprot makanan atau minuman',
        'Menjamin ruangan bebas virus setelah layanan selesai',
        'Pengasapan ruangan rutin',
    ];

    /** @var list<string> */
    public const LANGKAH = [
        'Area diperiksa',
        'Permukaan dibersihkan lebih dulu',
        'Permukaan yang sering disentuh ditentukan',
        'Disinfektan diaplikasikan',
        'Waktu kontak dipenuhi sesuai label produk',
        'Area diberi ventilasi',
        'Petugas melakukan pengecekan',
        'Laporan pekerjaan dikirim',
    ];

    public static function golongan(string $properti): string
    {
        return in_array($properti, self::PROPERTI_USAHA, true) ? 'usaha' : 'hunian';
    }

    /**
     * @return array<string, mixed>
     */
    public function hitung(string $properti, string $luas, int $ruangan, int $toilet, string $kondisi): array
    {
        $golongan = self::golongan($properti);
        $dasar = self::DASAR[$golongan][$luas] ?? 0;

        $ruanganTambahan = max(0, $ruangan - self::RUANGAN_TERMASUK);
        $toiletTambahan = max(0, $toilet - self::TOILET_TERMASUK);

        $biayaRuangan = $ruanganTambahan * self::TARIF_RUANGAN_TAMBAHAN;
        $biayaToilet = $toiletTambahan * self::TARIF_TOILET_TAMBAHAN;
        $kondisiInfo = self::KONDISI_TAMBAHAN[$kondisi];

        $baris = [
            ['label' => "Disinfektan {$luas} m²", 'nilai' => $dasar],
        ];

        if ($biayaRuangan > 0) {
            $baris[] = ['label' => "Ruangan tambahan ({$ruanganTambahan})", 'nilai' => $biayaRuangan];
        }
        if ($biayaToilet > 0) {
            $baris[] = ['label' => "Toilet tambahan ({$toiletTambahan})", 'nilai' => $biayaToilet];
        }
        if ($kondisiInfo['tambahan'] > 0) {
            $baris[] = ['label' => $kondisiInfo['label'], 'nilai' => $kondisiInfo['tambahan']];
        }

        $total = $dasar + $biayaRuangan + $biayaToilet + $kondisiInfo['tambahan'];

        $biayaLuas = self::BIAYA_LUAS[$luas] ?? ['larutan' => 0, 'tenaga' => 0];
        $biaya = self::BIAYA_TRANSPORT
            + $biayaLuas['larutan']
            + $biayaLuas['tenaga']
            + $ruanganTambahan * self::BIAYA_RUANGAN_TAMBAHAN
            + $toiletTambahan * self::BIAYA_TOILET_TAMBAHAN
            + $kondisiInfo['biaya'];

        return [
            'properti' => $properti,
            'golongan' => $golongan,
            'luas' => $luas,
            'ruangan' => $ruangan,
            'toilet' => $toilet,
            'kondisi' => $kondisi,
            'baris' => $baris,
            'total' => $total,
            'biaya' => $biaya,
        ];
    }
}
