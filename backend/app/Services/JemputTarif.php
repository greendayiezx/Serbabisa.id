<?php

namespace App\Services;

/**
 * Tarif BisaJemput — mengantar orang dari titik jemput ke tujuan.
 *
 * Tiga hal yang dijaga kelas ini:
 *
 * 1. JARAK DAN WAKTU DIHITUNG DI SINI, bukan dikirim klien. Kalau klien boleh
 *    mengirim "2 km" untuk perjalanan 20 km, seluruh tarif jadi angka yang bisa
 *    diketik sendiri oleh penumpang. Yang dikirim hanya dua titik koordinat.
 * 2. ADA TARIF MINIMUM. Perjalanan 300 meter tetap memakan waktu pengemudi
 *    untuk datang, dan tarif yang jatuh di bawah minimum membuat pengemudi rugi
 *    justru pada order yang paling sering muncul.
 * 3. KOMISI PLATFORM TERBATAS. Pengemudi menerima {@see BAGI_MITRA} dari tarif.
 *    Sisanya-lah seluruh pendapatan platform, dan dari situ pula potongan promo
 *    diambil — jadi promo yang lebih besar dari komisi bukan "promo agresif",
 *    melainkan membayar orang untuk naik kendaraan.
 */
class JemputTarif
{
    /** Bagian pengemudi dari tarif. Sisanya komisi platform. */
    public const BAGI_MITRA = 0.80;

    /** Biaya penyedia pembayaran, dihitung dari tarif. */
    public const BIAYA_PROSES = 0.02;

    /** Biaya tetap per perjalanan: dukungan, asuransi perjalanan, peta. */
    public const BIAYA_TETAP = 500;

    /**
     * Perjalanan kota lewat jalan, bukan garis lurus.
     *
     * Haversine memberi jarak burung terbang; jalan sebenarnya selalu lebih
     * panjang. Tanpa pengali ini, setiap perjalanan ditagih terlalu murah dan
     * pengemudi yang menanggung selisihnya.
     */
    public const FAKTOR_JALAN = 1.35;

    /** Batas wajar satu perjalanan dalam kota. */
    public const JARAK_MAKS_KM = 60.0;

    /**
     * Katalog kendaraan.
     *
     * `varian` memisahkan pilihan cepat dan hemat di kelas yang sama: yang
     * hemat lebih murah karena penumpang bersedia menunggu penjemputan lebih
     * lama, bukan karena pengemudinya dibayar lebih sedikit — bagian pengemudi
     * tetap {@see BAGI_MITRA}.
     *
     * @var array<string, array<string, mixed>>
     */
    public const TIPE = [
        'motor' => [
            'label' => 'BisaJemput Motor',
            'kelas' => 'motor',
            'keterangan' => 'Untuk 1 penumpang',
            'kapasitas' => 1,
            'kecepatan' => 22.0,
            'fitur' => ['Helm penumpang', 'Jas hujan', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'CEPAT', 'catatan' => 'Dijemput pengemudi terdekat', 'dasar' => 6_000, 'per_km' => 2_300, 'per_menit' => 150, 'minimum' => 10_000, 'jemput' => [2, 5]],
                'hemat' => ['label' => 'HEMAT', 'catatan' => 'Lebih murah, tunggu sebentar', 'dasar' => 5_000, 'per_km' => 1_900, 'per_menit' => 120, 'minimum' => 8_500, 'jemput' => [5, 9]],
            ],
        ],
        'motor_comfort' => [
            'label' => 'BisaJemput Motor Comfort',
            'kelas' => 'motor',
            'keterangan' => 'Motor unit terbaru',
            'kapasitas' => 1,
            'kecepatan' => 22.0,
            'fitur' => ['Unit keluaran baru', 'Helm penumpang', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'COMFORT', 'catatan' => 'Unit lebih baru', 'dasar' => 7_500, 'per_km' => 2_700, 'per_menit' => 180, 'minimum' => 12_000, 'jemput' => [4, 9]],
            ],
        ],
        'mobil' => [
            'label' => 'BisaJemput Mobil',
            'kelas' => 'mobil',
            'keterangan' => 'Muat 4 orang',
            'kapasitas' => 4,
            'kecepatan' => 18.0,
            'fitur' => ['AC', 'Air minum', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'CEPAT', 'catatan' => 'Dijemput pengemudi terdekat', 'dasar' => 12_000, 'per_km' => 4_200, 'per_menit' => 400, 'minimum' => 22_000, 'jemput' => [3, 7]],
                'hemat' => ['label' => 'HEMAT', 'catatan' => 'Lebih murah, tunggu sebentar', 'dasar' => 10_000, 'per_km' => 3_600, 'per_menit' => 340, 'minimum' => 18_000, 'jemput' => [7, 12]],
            ],
        ],
        'mobil_comfort' => [
            'label' => 'BisaJemput Mobil Comfort',
            'kelas' => 'mobil',
            'keterangan' => 'Avanza, Xpander, dll.',
            'kapasitas' => 4,
            'kecepatan' => 18.0,
            'fitur' => ['AC', 'Air minum', 'Pengisi daya', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'COMFORT', 'catatan' => 'Unit lebih lega', 'dasar' => 15_000, 'per_km' => 5_000, 'per_menit' => 450, 'minimum' => 27_000, 'jemput' => [5, 12]],
            ],
        ],
        'mobil_premium' => [
            'label' => 'BisaJemput Premium',
            'kelas' => 'mobil',
            'keterangan' => 'Innova, dll.',
            'kapasitas' => 4,
            'kecepatan' => 18.0,
            'fitur' => ['Pengemudi berseragam', 'AC', 'Air minum', 'Pengisi daya', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'PREMIUM', 'catatan' => 'Unit premium, pengemudi berseragam', 'dasar' => 22_000, 'per_km' => 7_000, 'per_menit' => 600, 'minimum' => 45_000, 'jemput' => [6, 14]],
            ],
        ],
        'van' => [
            'label' => 'BisaJemput Van',
            'kelas' => 'mobil',
            'keterangan' => 'Muat 6 orang',
            'kapasitas' => 6,
            'kecepatan' => 17.0,
            'fitur' => ['AC', 'Bagasi luas', 'Asuransi perjalanan'],
            'varian' => [
                'cepat' => ['label' => 'VAN', 'catatan' => 'Buat pergi rame-rame', 'dasar' => 25_000, 'per_km' => 7_500, 'per_menit' => 650, 'minimum' => 50_000, 'jemput' => [8, 18]],
            ],
        ],
    ];

    /**
     * Pengali jam sibuk, ditampilkan APA ADANYA ke penumpang.
     *
     * Tarif yang naik diam-diam terbaca sebagai tagihan yang salah. Yang naik
     * disebut kenaikannya, alasannya, dan berapa besarnya sebelum dipesan.
     *
     * @var array<string, array{jam:list<int>, hari:list<int>, pengali:float, alasan:string}>
     */
    public const SIBUK = [
        'pagi' => [
            'jam' => [6, 7, 8],
            'hari' => [1, 2, 3, 4, 5],
            'pengali' => 1.25,
            'alasan' => 'Jam berangkat kerja, permintaan sedang tinggi',
        ],
        'sore' => [
            'jam' => [17, 18, 19],
            'hari' => [1, 2, 3, 4, 5],
            'pengali' => 1.25,
            'alasan' => 'Jam pulang kerja, permintaan sedang tinggi',
        ],
        'malam' => [
            'jam' => [23, 0, 1, 2, 3, 4],
            'hari' => [0, 1, 2, 3, 4, 5, 6],
            'pengali' => 1.15,
            'alasan' => 'Larut malam, pengemudi yang aktif lebih sedikit',
        ],
    ];

    /** @var list<string> */
    public const METODE_BAYAR = ['gopay', 'ovo', 'dana', 'tunai', 'va'];

    /** @return list<string> */
    public static function idTipe(): array
    {
        return array_keys(self::TIPE);
    }

    /** @return list<string> */
    public static function idVarian(): array
    {
        $id = [];
        foreach (self::TIPE as $t) {
            $id = [...$id, ...array_keys($t['varian'])];
        }

        return array_values(array_unique($id));
    }

    /**
     * Jarak jalan perkiraan antara dua koordinat, dalam kilometer.
     *
     * Haversine lalu dikali {@see FAKTOR_JALAN}. Ini perkiraan, dan disebut
     * perkiraan di layar — bukan janji jarak tempuh yang pasti.
     */
    public function jarakKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)) * self::FAKTOR_JALAN, 2);
    }

    /** Lama perjalanan perkiraan dalam menit, menurut kecepatan kota tiap kelas. */
    public function menit(string $tipe, float $km): int
    {
        $kecepatan = self::TIPE[$tipe]['kecepatan'] ?? 20.0;

        return max(1, (int) ceil($km / $kecepatan * 60));
    }

    /**
     * Pengali jam sibuk yang berlaku pada satu waktu.
     *
     * @return array{pengali:float, nama:string|null, alasan:string|null}
     */
    public function sibuk(\DateTimeInterface $saat): array
    {
        $jam = (int) $saat->format('G');
        $hari = (int) $saat->format('w');

        foreach (self::SIBUK as $nama => $aturan) {
            if (in_array($jam, $aturan['jam'], true) && in_array($hari, $aturan['hari'], true)) {
                return ['pengali' => $aturan['pengali'], 'nama' => $nama, 'alasan' => $aturan['alasan']];
            }
        }

        return ['pengali' => 1.0, 'nama' => null, 'alasan' => null];
    }

    /**
     * Hitung tarif satu pilihan.
     *
     * @return array<string, mixed>
     */
    public function hitung(string $tipe, string $varian, float $km, ?\DateTimeInterface $saat = null): array
    {
        $info = self::TIPE[$tipe];
        $v = $info['varian'][$varian];

        $menit = $this->menit($tipe, $km);
        $sibuk = $this->sibuk($saat ?? new \DateTimeImmutable);

        $jarak = (int) round($v['per_km'] * $km);
        $waktu = $v['per_menit'] * $menit;
        $sebelumMinimum = $v['dasar'] + $jarak + $waktu;

        // Minimum diterapkan SEBELUM pengali jam sibuk: kalau tidak, perjalanan
        // pendek di jam sibuk naik dua kali — sekali oleh minimum, sekali lagi
        // oleh pengali.
        $dasarTerpakai = max($sebelumMinimum, $v['minimum']);
        $tarif = (int) (ceil($dasarTerpakai * $sibuk['pengali'] / 500) * 500);

        $baris = [
            ['label' => 'Tarif dasar', 'nilai' => $v['dasar']],
            ['label' => 'Jarak '.number_format($km, 1, ',', '.').' km', 'nilai' => $jarak],
            ['label' => 'Waktu '.$menit.' menit', 'nilai' => $waktu],
        ];

        if ($dasarTerpakai > $sebelumMinimum) {
            $baris[] = ['label' => 'Penyesuaian tarif minimum', 'nilai' => $dasarTerpakai - $sebelumMinimum];
        }
        if ($sibuk['pengali'] > 1.0) {
            $baris[] = ['label' => 'Jam sibuk ×'.number_format($sibuk['pengali'], 2, ',', '.'), 'nilai' => $tarif - $dasarTerpakai];
        }

        return [
            'tipe' => $tipe,
            'varian' => $varian,
            'kelas' => $info['kelas'],
            'label' => $info['label'],
            'label_varian' => $v['label'],
            'catatan' => $v['catatan'],
            'keterangan' => $info['keterangan'],
            'kapasitas' => $info['kapasitas'],
            'fitur' => $info['fitur'],
            'km' => $km,
            'menit' => $menit,
            'jemput_menit' => $v['jemput'],
            'baris' => $baris,
            'tarif' => $tarif,
            'sibuk' => $sibuk['nama'],
            'sibuk_alasan' => $sibuk['alasan'],
            'sibuk_pengali' => $sibuk['pengali'],
            'komisi' => $this->komisi($tarif),
            'biaya' => $this->biaya($tarif),
        ];
    }

    /**
     * Semua pilihan untuk satu perjalanan.
     *
     * @return list<array<string, mixed>>
     */
    public function semuaPilihan(float $km, ?\DateTimeInterface $saat = null): array
    {
        $hasil = [];
        foreach (self::TIPE as $tipe => $info) {
            foreach (array_keys($info['varian']) as $varian) {
                $hasil[] = $this->hitung($tipe, $varian, $km, $saat);
            }
        }

        return $hasil;
    }

    /** Pendapatan platform sebelum biaya: tarif dikurangi bagian pengemudi. */
    public function komisi(int $tarif): int
    {
        return $tarif - (int) round($tarif * self::BAGI_MITRA);
    }

    /** Seluruh uang yang keluar untuk satu perjalanan. */
    public function biaya(int $tarif): int
    {
        return (int) round($tarif * self::BAGI_MITRA)
            + (int) round($tarif * self::BIAYA_PROSES)
            + self::BIAYA_TETAP;
    }
}
