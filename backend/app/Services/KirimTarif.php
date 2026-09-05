<?php

namespace App\Services;

/**
 * Tarif BisaKirim — mengantar paket, bukan orang.
 *
 * Empat hal yang dijaga kelas ini:
 *
 * 1. JARAK DIHITUNG DI SERVER, seperti BisaJemput. Yang dikirim klien hanya dua
 *    koordinat; kalau jaraknya boleh dikirim sendiri, ongkirnya jadi angka yang
 *    bisa diketik pengirim.
 * 2. BATAS BERAT DAN UKURAN NYATA, bukan hiasan. Motor tidak bisa membawa 60 kg,
 *    dan menerima pesanan seperti itu berarti kurir datang lalu menolak di
 *    lokasi — waktu pengirim habis, kurir rugi jalan.
 * 3. BARANG TERLARANG DITOLAK, bukan ditambah biayanya. Uang tunai, barang
 *    mudah meledak, dan hewan hidup bukan soal harga: kurir motor tidak punya
 *    cara aman membawanya, dan tidak ada ganti rugi yang menutup akibatnya.
 * 4. PROTEKSI PUNYA PLAFON YANG DISEBUT. Ganti rugi tanpa angka adalah janji
 *    yang baru ketahuan batasnya saat barang benar-benar hilang.
 */
class KirimTarif
{
    /** Bagian kurir dari ongkir. Sisanya komisi platform. */
    public const BAGI_MITRA = 0.80;

    public const BIAYA_PROSES = 0.02;

    public const BIAYA_TETAP = 500;

    /** Jarak jalan dipakai kalau ada; ini pengali cadangan untuk garis lurus. */
    public const FAKTOR_JALAN = 1.35;

    public const JARAK_MAKS_KM = 40.0;

    /**
     * Kendaraan pengantar.
     *
     * `km_termasuk` sudah masuk tarif dasar. Tanpa itu, kiriman 800 meter
     * ditagih hampir nol padahal kurirnya tetap berangkat, menunggu, dan
     * kembali.
     *
     * @var array<string, array<string, mixed>>
     */
    public const KENDARAAN = [
        'motor' => [
            'label' => 'Instant — Motor',
            'catatan' => 'Langsung dijemput & dikirim',
            'estimasi' => '1-2 jam',
            'maks_berat' => 20,
            'maks_sisi_cm' => 50,
            'dasar' => 12_000,
            'km_termasuk' => 2.0,
            'per_km' => 2_200,
            'minimum' => 12_000,
        ],
        'mobil' => [
            'label' => 'Instant — Mobil',
            'catatan' => 'Muat lebih banyak dan lebih besar',
            'estimasi' => '1-2 jam',
            'maks_berat' => 100,
            'maks_sisi_cm' => 150,
            'dasar' => 25_000,
            'km_termasuk' => 2.0,
            'per_km' => 4_200,
            'minimum' => 25_000,
        ],
    ];

    /**
     * Golongan ukuran paket. Beratnya perkiraan pengirim, dan itu cukup —
     * yang menentukan penolakan adalah batas kendaraannya, bukan angka pastinya.
     *
     * @var array<string, array{label:string, berat:int, sisi:int, contoh:string}>
     */
    public const UKURAN = [
        'dokumen' => ['label' => 'Dokumen', 'berat' => 1, 'sisi' => 35, 'contoh' => 'Surat, berkas, map'],
        'kecil' => ['label' => 'Kecil', 'berat' => 5, 'sisi' => 40, 'contoh' => 'Kotak sepatu, makanan'],
        'sedang' => ['label' => 'Sedang', 'berat' => 20, 'sisi' => 50, 'contoh' => 'Kardus sedang, galon'],
        'besar' => ['label' => 'Besar', 'berat' => 100, 'sisi' => 150, 'contoh' => 'Kardus besar, sepeda lipat'],
    ];

    /**
     * Isi kiriman yang TIDAK diterima.
     *
     * Bukan daftar untuk menaikkan harga: tidak satu pun dari ini punya cara
     * pengiriman yang aman lewat kurir motor, dan tidak ada ganti rugi yang
     * sepadan kalau terjadi apa-apa.
     *
     * @var list<string>
     */
    public const DILARANG = [
        'uang-tunai',
        'barang-mudah-meledak',
        'cairan-mudah-terbakar',
        'hewan-hidup',
        'barang-terlarang',
    ];

    /**
     * Proteksi paket.
     *
     * Plafonnya disebut apa adanya. Angka besar yang tidak berdasar (mis.
     * "jaminan sampai 50 juta") hanya bertahan sampai ada klaim pertama — dan
     * yang menanggung kekecewaannya adalah pengirim yang percaya.
     */
    public const PROTEKSI_PLAFON = 2_000_000;

    /** Premi 1% dari nilai barang, dibulatkan ke atas kelipatan 500. */
    public const PROTEKSI_PERSEN = 1.0;

    public const PROTEKSI_MINIMUM = 2_000;

    /** @return list<string> */
    public static function idKendaraan(): array
    {
        return array_keys(self::KENDARAAN);
    }

    /** @return list<string> */
    public static function idUkuran(): array
    {
        return array_keys(self::UKURAN);
    }

    public function jarakKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)) * self::FAKTOR_JALAN, 2);
    }

    /**
     * Apakah kendaraan ini sanggup membawa paket sebesar itu.
     *
     * @return string|null alasan tidak sanggup, atau null kalau sanggup
     */
    public function tidakSanggup(string $kendaraan, string $ukuran): ?string
    {
        $k = self::KENDARAAN[$kendaraan];
        $u = self::UKURAN[$ukuran];

        if ($u['berat'] > $k['maks_berat']) {
            return "{$k['label']} hanya sampai {$k['maks_berat']} kg; paket {$u['label']} bisa sampai {$u['berat']} kg.";
        }
        if ($u['sisi'] > $k['maks_sisi_cm']) {
            return "{$k['label']} hanya muat sisi {$k['maks_sisi_cm']} cm; paket {$u['label']} sampai {$u['sisi']} cm.";
        }

        return null;
    }

    public function premiProteksi(int $nilaiBarang): int
    {
        $nilai = min(max(0, $nilaiBarang), self::PROTEKSI_PLAFON);
        if ($nilai <= 0) {
            return 0;
        }

        return max(
            self::PROTEKSI_MINIMUM,
            (int) (ceil($nilai * self::PROTEKSI_PERSEN / 100 / 500) * 500),
        );
    }

    /**
     * Hitung ongkir satu kendaraan.
     *
     * @return array<string, mixed>
     */
    public function hitung(string $kendaraan, float $km, string $ukuran, int $nilaiBarang = 0): array
    {
        $k = self::KENDARAAN[$kendaraan];

        $kmBayar = max(0.0, $km - $k['km_termasuk']);
        $jarak = (int) round($k['per_km'] * $kmBayar);
        $sebelumMinimum = $k['dasar'] + $jarak;
        $ongkir = (int) (ceil(max($sebelumMinimum, $k['minimum']) / 500) * 500);

        $premi = $this->premiProteksi($nilaiBarang);

        $baris = [
            ['label' => 'Ongkir dasar (sampai '.number_format($k['km_termasuk'], 0).' km)', 'nilai' => $k['dasar']],
        ];
        if ($jarak > 0) {
            $baris[] = [
                'label' => 'Jarak '.number_format($kmBayar, 1, ',', '.').' km berikutnya',
                'nilai' => $jarak,
            ];
        }
        if ($ongkir > $sebelumMinimum) {
            $baris[] = ['label' => 'Penyesuaian ongkir minimum', 'nilai' => $ongkir - $sebelumMinimum];
        }
        if ($premi > 0) {
            $baris[] = ['label' => 'Proteksi paket', 'nilai' => $premi];
        }

        return [
            'kendaraan' => $kendaraan,
            'label' => $k['label'],
            'catatan' => $k['catatan'],
            'estimasi' => $k['estimasi'],
            'maks_berat' => $k['maks_berat'],
            'maks_sisi_cm' => $k['maks_sisi_cm'],
            'km' => $km,
            'baris' => $baris,
            'ongkir' => $ongkir,
            'premi' => $premi,
            'total' => $ongkir + $premi,
            'sanggup' => $this->tidakSanggup($kendaraan, $ukuran) === null,
            'alasan' => $this->tidakSanggup($kendaraan, $ukuran),
            'komisi' => $this->komisi($ongkir),
            'biaya' => $this->biaya($ongkir),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function semuaKendaraan(float $km, string $ukuran, int $nilaiBarang = 0): array
    {
        return array_map(
            fn ($id) => $this->hitung($id, $km, $ukuran, $nilaiBarang),
            self::idKendaraan(),
        );
    }

    /**
     * Komisi dihitung dari ONGKIR saja, tidak dari premi proteksi.
     *
     * Premi bukan pendapatan jasa antar; ia dana penggantian. Menghitungnya
     * sebagai komisi berarti membelanjakan uang yang justru disiapkan untuk
     * mengganti barang orang.
     */
    public function komisi(int $ongkir): int
    {
        return $ongkir - (int) round($ongkir * self::BAGI_MITRA);
    }

    public function biaya(int $ongkir): int
    {
        return (int) round($ongkir * self::BAGI_MITRA)
            + (int) round($ongkir * self::BIAYA_PROSES)
            + self::BIAYA_TETAP;
    }
}
