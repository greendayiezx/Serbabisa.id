<?php

namespace App\Services;

/**
 * Tarif BisaTukang.
 *
 * Dua model kerja dengan perilaku uang yang berbeda, dan itu sengaja tidak
 * disatukan — persis pemisahan yang sudah dipakai Servis AC:
 *
 * 1. HARIAN menagih KUNJUNGANNYA saja di muka. Berapa biaya perbaikannya baru
 *    diketahui setelah tukang melihat sendiri kerusakannya, dan angka itu harus
 *    disetujui pemesan sebelum dikerjakan. Menyebut harga perbaikan dari
 *    formulir berarti menebak, lalu menagih tebakan itu.
 *
 * 2. BORONGAN tidak menagih apa pun. Harganya bergantung luas, material,
 *    ketinggian, dan akses lokasi — hal yang tidak bisa dibaca dari layar. Yang
 *    tercatat PERMINTAAN SURVEI; RAB-nya menyusul setelah lokasi dilihat.
 *
 * Semua angka di sini milik server. Sebelumnya katalog kategori dan biayanya
 * hidup sebagai tetapan di sisi klien, yang berarti siapa pun bisa memesan
 * dengan tarif karangannya sendiri.
 */
class TukangTarif
{
    /** @var list<string> */
    public const MODEL_KERJA = ['harian', 'borongan'];

    /** @var list<string> */
    public const TIPE_PROPERTI = ['rumah', 'ruko', 'apartemen', 'kantor'];

    /** @var list<string> */
    public const PENYEDIAAN_MATERIAL = ['pelanggan', 'tukang'];

    /** @var list<string> */
    public const LOKASI_MASALAH = [
        'Dalam rumah',
        'Luar rumah',
        'Atap',
        'Lantai atas',
        'Kamar mandi',
        'Dapur',
    ];

    /** @var list<string> */
    public const JADWAL_TIPE = ['secepatnya', 'hari_ini', 'besok', 'lainnya'];

    /**
     * Rentang yang boleh disebut di layar untuk borongan.
     *
     * Ditulis sebagai RENTANG, bukan satu angka: pekerjaan borongan bergerak
     * jauh tergantung luas dan materialnya. Satu angka tunggal akan dibaca
     * sebagai harga pasti, dan RAB yang datang kemudian terasa seperti harga
     * yang naik diam-diam.
     */
    public const BORONGAN_MULAI = 2_500_000;

    public const BORONGAN_SAMPAI = 35_000_000;

    /** Berapa hari penawaran berlaku sejak diterbitkan. */
    public const PENAWARAN_BERLAKU_HARI = 7;

    /** Garansi pengerjaan, dihitung sejak pekerjaan selesai. */
    public const GARANSI_HARI = 30;

    /**
     * Bagian platform dari biaya kunjungan.
     *
     * Sisanya milik tukang. Ditulis sebagai bagian ongkos yang benar-benar
     * ditanggung platform — pencocokan, dukungan, dan penjaminan garansi —
     * bukan angka yang ditarik sebesar-besarnya dari satu kunjungan.
     */
    public const KOMISI_KUNJUNGAN = 0.20;

    /**
     * Katalog keahlian.
     *
     * `kunjungan` adalah yang benar-benar ditagih saat memesan model harian.
     * `perbaikan_mulai`/`perbaikan_sampai` hanya RENTANG untuk ditampilkan;
     * angka yang mengikat baru ada di penawaran setelah tukang memeriksa.
     *
     * @var array<string, array<string, mixed>>
     */
    public const KATEGORI = [
        'listrik' => [
            'nama' => 'Listrik',
            'deskripsi' => 'Perbaikan instalasi listrik, stop kontak, MCB & pencahayaan',
            'kunjungan' => 30_000,
            'perbaikan_mulai' => 100_000,
            'perbaikan_sampai' => 350_000,
            'sub' => [
                'Listrik mati total',
                'MCB sering turun',
                'Stop kontak rusak',
                'Instalasi lampu baru',
                'Kipas angin tidak menyala',
                'Water heater bermasalah',
                'Instalasi baru',
                'Pindah instalasi',
                'Ganti kabel',
                'Panel listrik',
                'Lainnya',
            ],
        ],
        'pipa' => [
            'nama' => 'Pipa & Plumbing',
            'deskripsi' => 'Atasi kebocoran pipa, keran rusak & saluran mampet',
            'kunjungan' => 30_000,
            'perbaikan_mulai' => 100_000,
            'perbaikan_sampai' => 400_000,
            'sub' => [
                'Pipa bocor',
                'Keran rusak',
                'Saluran mampet',
                'Kloset mampet',
                'Pompa air bermasalah',
                'Ganti toren air',
                'Pasang kloset',
                'Wastafel bocor',
                'Lainnya',
            ],
        ],
        'ac' => [
            'nama' => 'AC & Pendingin',
            'deskripsi' => 'Cuci AC, isi freon, bongkar pasang & servis rutin',
            'kunjungan' => 40_000,
            'perbaikan_mulai' => 150_000,
            'perbaikan_sampai' => 600_000,
            'sub' => [
                'AC tidak dingin',
                'AC bocor',
                'AC berisik',
                'Cuci AC',
                'Isi freon',
                'Bongkar pasang AC',
                'Kulkas tidak dingin',
                'Lainnya',
            ],
        ],
        'bangunan' => [
            'nama' => 'Bangunan & Renovasi',
            'deskripsi' => 'Perbaikan atap bocor, tembok retak & plafon rusak',
            'kunjungan' => 50_000,
            'perbaikan_mulai' => 200_000,
            'perbaikan_sampai' => 1_500_000,
            'sub' => [
                'Atap bocor',
                'Tembok retak',
                'Plafon rusak',
                'Lantai keramik pecah',
                'Cat ulang tembok',
                'Waterproofing dak',
                'Plester & acian',
                'Lainnya',
            ],
        ],
        'pintu' => [
            'nama' => 'Pintu, Jendela, Kunci',
            'deskripsi' => 'Servis engsel, ganti silinder kunci & rolling door',
            'kunjungan' => 30_000,
            'perbaikan_mulai' => 80_000,
            'perbaikan_sampai' => 400_000,
            'sub' => [
                'Engsel rusak',
                'Kunci macet',
                'Ganti silinder kunci',
                'Pintu tidak rapat',
                'Kusen lapuk',
                'Rolling door macet',
                'Lainnya',
            ],
        ],
        'cat' => [
            'nama' => 'Cat & Dinding',
            'deskripsi' => 'Pengecatan interior, eksterior & perbaikan plafon',
            'kunjungan' => 40_000,
            'perbaikan_mulai' => 150_000,
            'perbaikan_sampai' => 2_000_000,
            'sub' => [
                'Cat interior',
                'Cat eksterior',
                'Cat plafon',
                'Cat pagar & teralis',
                'Tambal dinding mengelupas',
                'Wallpaper',
                'Lainnya',
            ],
        ],
        'elektronik' => [
            'nama' => 'Elektronik Rumah Tangga',
            'deskripsi' => 'Servis mesin cuci, pompa air & peralatan rumah',
            'kunjungan' => 40_000,
            'perbaikan_mulai' => 120_000,
            'perbaikan_sampai' => 500_000,
            'sub' => [
                'Mesin cuci bermasalah',
                'Pompa air mati',
                'Water heater rusak',
                'Dispenser rusak',
                'Kompor listrik rusak',
                'Lainnya',
            ],
        ],
        'renovasi' => [
            'nama' => 'Renovasi Rumah',
            'deskripsi' => 'Renovasi ruangan, dapur, kamar mandi & tambah kamar',
            'kunjungan' => 50_000,
            'perbaikan_mulai' => 2_500_000,
            'perbaikan_sampai' => 35_000_000,
            'sub' => [
                'Renovasi kamar mandi',
                'Renovasi dapur',
                'Tambah kamar',
                'Renovasi total',
                'Perluasan bangunan',
                'Lainnya',
            ],
        ],
        'lainnya' => [
            'nama' => 'Lainnya',
            'deskripsi' => 'Pekerjaan tukang lain yang belum ada di daftar',
            'kunjungan' => 40_000,
            'perbaikan_mulai' => 100_000,
            'perbaikan_sampai' => 1_000_000,
            'sub' => ['Belum tahu', 'Lainnya'],
        ],
    ];

    /** @return list<string> */
    public static function idKategori(): array
    {
        return array_keys(self::KATEGORI);
    }

    /**
     * Katalog untuk layar; tanpa angka yang tidak perlu diketahui klien.
     *
     * @return list<array<string, mixed>>
     */
    public function katalog(): array
    {
        $keluar = [];
        foreach (self::KATEGORI as $id => $k) {
            $keluar[] = [
                'id' => $id,
                'nama' => $k['nama'],
                'deskripsi' => $k['deskripsi'],
                'sub' => $k['sub'],
                'kunjungan' => $k['kunjungan'],
                'perbaikan_mulai' => $k['perbaikan_mulai'],
                'perbaikan_sampai' => $k['perbaikan_sampai'],
            ];
        }

        return $keluar;
    }

    /**
     * Biaya kunjungan model harian.
     *
     * Satu angka, bukan rentang: inilah yang benar-benar ditagih hari itu, dan
     * pemesan berhak tahu persisnya sebelum menekan tombol.
     *
     * @return array<string, mixed>
     */
    public function kunjungan(string $kategoriId): array
    {
        $k = self::KATEGORI[$kategoriId] ?? self::KATEGORI['lainnya'];
        $biaya = (int) $k['kunjungan'];

        return [
            'kategori' => $kategoriId,
            'nama_kategori' => $k['nama'],
            'biaya_kunjungan' => $biaya,
            'perbaikan_mulai' => (int) $k['perbaikan_mulai'],
            'perbaikan_sampai' => (int) $k['perbaikan_sampai'],
            'total' => $biaya,
            'komisi' => (int) round($biaya * self::KOMISI_KUNJUNGAN),
        ];
    }

    /** Sub-kategori yang sah untuk satu keahlian. */
    public function subSah(string $kategoriId, string $sub): bool
    {
        return in_array($sub, self::KATEGORI[$kategoriId]['sub'] ?? [], true);
    }
}
