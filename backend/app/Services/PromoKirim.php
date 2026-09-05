<?php

namespace App\Services;

/**
 * Voucher BisaKirim.
 *
 * ================== DARI KANTONG MANA ==================
 *
 * Kurir menerima {@see KirimTarif::BAGI_MITRA} dari ongkir, dan bagiannya TIDAK
 * PERNAH ikut dipotong voucher — dialah yang mengeluarkan bensin dan waktunya.
 * Sisanya, 20% ongkir, adalah seluruh pendapatan platform per kiriman.
 *
 * Sebagian besar voucher di katalog ini lebih besar daripada 20% itu. Itu bukan
 * kekeliruan yang dibiarkan: voucher seperti itu memang dibayar dari ANGGARAN
 * PEMASARAN, bukan dari margin kiriman. Yang tidak boleh adalah kerugiannya
 * tidak terlihat — jadi setiap voucher menyebut sumbernya, dan berapa rupiah
 * yang keluar dari kantong dicatat di pesanan sebagai `beban_pemasaran`.
 *
 * Tiga sumber:
 *
 * - `komisi`     — diambil dari margin kiriman itu sendiri, dibatasi separuh
 *                  komisi ({@see PromoJemput::BATAS_KOMISI}). Kiriman tetap
 *                  untung.
 * - `akuisisi`   — sekali seumur akun, dari anggaran pengguna baru, dibatasi
 *                  {@see PromoJemput::BATAS_AKUISISI}.
 * - `pemasaran`  — boleh membuat satu kiriman rugi, TAPI ruginya dibatasi
 *                  {@see BATAS_RUGI_PER_KIRIMAN}. Tanpa batas ini, satu angka
 *                  yang diketik terlalu besar di katalog bisa menghabiskan
 *                  anggaran sebulan dalam sehari tanpa ada yang menyadarinya.
 *
 * ================== YANG TIDAK ADA DI SINI ==================
 *
 * Voucher untuk layanan yang belum ada sengaja TIDAK dimasukkan: Same-Day,
 * Next-Day, dan Cargo tidak ada di {@see KirimTarif::KENDARAAN} — BisaKirim
 * baru punya Instant motor dan mobil. Voucher yang tidak akan pernah bisa
 * dipakai lebih buruk daripada tidak ada: ia muncul di daftar, dicoba orang,
 * lalu ditolak tanpa alasan yang masuk akal.
 *
 * INSTANTGRATIS ("gratis biaya layanan") juga tidak ada: BisaKirim tidak
 * memungut biaya layanan terpisah — yang ada premi proteksi, dan premi itu dana
 * penggantian barang, bukan pendapatan yang boleh digratiskan.
 */
class PromoKirim
{
    /**
     * Rugi terbesar yang boleh ditanggung satu kiriman demi voucher pemasaran.
     *
     * Angkanya dipilih dari kerugian voucher terbesar di katalog ini pada
     * ambang minimumnya (WEEKENDKIRIM, sekitar Rp5.750) plus sedikit ruang.
     * Voucher yang melebihi batas ini akan dipotong otomatis — dan kalau itu
     * terjadi, yang salah katalognya, bukan batasnya.
     */
    public const BATAS_RUGI_PER_KIRIMAN = 6_000;

    /**
     * @var list<array<string, mixed>>
     */
    public const KATALOG = [
        /* ---------- Pengguna baru ---------- */
        [
            'kode' => 'KIRIMBARU20',
            'nama' => 'Kiriman pertama',
            'jenis' => 'akuisisi',
            'sumber' => 'akuisisi',
            'sekali_seumur_hidup' => true,
            'persen' => 20,
            'tetap' => 0,
            'maks' => 15_000,
            'minimum' => 25_000,
            'deskripsi' => 'Diskon 20% sampai Rp15.000 untuk kiriman pertama.',
        ],
        [
            'kode' => 'KIRIMBARU30',
            'nama' => 'Kiriman pertama, hemat lebih',
            'jenis' => 'akuisisi',
            'sumber' => 'akuisisi',
            'sekali_seumur_hidup' => true,
            'persen' => 30,
            'tetap' => 0,
            'maks' => 25_000,
            'minimum' => 50_000,
            'deskripsi' => 'Diskon 30% sampai Rp25.000 untuk kiriman pertama, mulai ongkir Rp50.000.',
        ],

        /* ---------- Tangga minimal transaksi ---------- */
        [
            'kode' => 'KIRIM5',
            'nama' => 'Hemat Rp5.000',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 0,
            'tetap' => 5_000,
            'maks' => 5_000,
            'minimum' => 25_000,
            'deskripsi' => 'Potongan Rp5.000, mulai ongkir Rp25.000.',
        ],
        [
            'kode' => 'KIRIM10',
            'nama' => 'Hemat Rp10.000',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 0,
            'tetap' => 10_000,
            'maks' => 10_000,
            'minimum' => 50_000,
            'deskripsi' => 'Potongan Rp10.000, mulai ongkir Rp50.000.',
        ],
        [
            'kode' => 'KIRIM15',
            'nama' => 'Hemat Rp15.000',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 0,
            'tetap' => 15_000,
            'maks' => 15_000,
            'minimum' => 75_000,
            'deskripsi' => 'Potongan Rp15.000, mulai ongkir Rp75.000.',
        ],
        [
            'kode' => 'KIRIM20',
            'nama' => 'Hemat Rp20.000',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 0,
            'tetap' => 20_000,
            'maks' => 20_000,
            'minimum' => 100_000,
            'deskripsi' => 'Potongan Rp20.000, mulai ongkir Rp100.000.',
        ],

        /* ---------- Menurut layanan yang MEMANG ADA ---------- */
        [
            'kode' => 'INSTANT15',
            'nama' => 'Instant lebih hemat',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 15,
            'tetap' => 0,
            'maks' => 20_000,
            'minimum' => 75_000,
            'deskripsi' => 'Diskon 15% sampai Rp20.000 untuk layanan Instant, mulai ongkir Rp75.000.',
        ],

        /* ---------- Menurut jam, dibaca di WIB ---------- */
        [
            'kode' => 'PAGIKIRIM',
            'nama' => 'Kirim pagi',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 20,
            'tetap' => 0,
            'maks' => 15_000,
            'minimum' => 50_000,
            'jam' => [6, 7, 8],
            'hari' => [1, 2, 3, 4, 5],
            'deskripsi' => 'Diskon 20% sampai Rp15.000, Senin–Jumat jam 06.00–09.00.',
        ],
        [
            'kode' => 'SIANGKIRIM',
            'nama' => 'Kirim siang',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 15,
            'tetap' => 0,
            'maks' => 12_000,
            'minimum' => 40_000,
            'jam' => [11, 12, 13],
            'deskripsi' => 'Diskon 15% sampai Rp12.000, setiap hari jam 11.00–14.00.',
        ],
        [
            'kode' => 'SOREKIRIM',
            'nama' => 'Kirim sore',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 20,
            'tetap' => 0,
            'maks' => 15_000,
            'minimum' => 50_000,
            'jam' => [16, 17, 18],
            'hari' => [1, 2, 3, 4, 5],
            'deskripsi' => 'Diskon 20% sampai Rp15.000, Senin–Jumat jam 16.00–19.00.',
        ],
        [
            'kode' => 'WEEKENDKIRIM',
            'nama' => 'Kirim akhir pekan',
            'jenis' => 'berulang',
            'sumber' => 'pemasaran',
            'sekali_seumur_hidup' => false,
            'persen' => 25,
            'tetap' => 0,
            'maks' => 20_000,
            'minimum' => 75_000,
            'hari' => [0, 6],
            'deskripsi' => 'Diskon 25% sampai Rp20.000, Sabtu dan Minggu.',
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function tersedia(
        int $ongkir,
        int $komisi,
        int $biaya,
        bool $kirimanPertama,
        ?\DateTimeInterface $saat = null,
    ): array {
        $saat ??= new \DateTimeImmutable;

        $hasil = [];
        foreach (self::KATALOG as $promo) {
            $alasan = $this->kenapaTidakBisa($promo, $ongkir, $kirimanPertama, $saat);

            $hasil[] = [
                ...$promo,
                'potongan' => $alasan ? 0 : $this->potongan($promo, $ongkir, $komisi, $biaya),
                'bisa_dipakai' => $alasan === null,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }

    /**
     * Potongan sesudah semua batas.
     *
     * Batas terakhirlah yang menahan. Berapa pun yang tertulis di katalog:
     * voucher berkomisi tidak pernah memakan lebih dari separuh komisi, voucher
     * akuisisi tidak melewati anggaran pengguna baru, dan voucher pemasaran
     * tidak pernah membuat satu kiriman rugi lebih dari
     * {@see BATAS_RUGI_PER_KIRIMAN}.
     */
    public function potongan(array $promo, int $ongkir, int $komisi, int $biaya): int
    {
        $nilai = $promo['tetap'] > 0
            ? (int) $promo['tetap']
            : (int) floor($ongkir * $promo['persen'] / 100);

        $nilai = min($nilai, (int) $promo['maks']);

        $batas = match ($promo['sumber']) {
            'akuisisi' => PromoJemput::BATAS_AKUISISI,
            'pemasaran' => max(0, $ongkir - $biaya + self::BATAS_RUGI_PER_KIRIMAN),
            default => (int) floor($komisi * PromoJemput::BATAS_KOMISI),
        };

        return max(0, min($nilai, $batas));
    }

    /**
     * Berapa rupiah yang benar-benar keluar dari kantong untuk voucher ini.
     *
     * Nol berarti potongannya masih tertutup margin kiriman. Di atas nol,
     * itulah beban pemasaran yang dicatat di pesanan — supaya kerugiannya
     * terlihat sebagai angka, bukan hilang begitu saja dari komisi.
     */
    public function bebanPemasaran(int $ongkir, int $biaya, int $potongan): int
    {
        return max(0, $potongan - ($ongkir - $biaya));
    }

    public function kenapaTidakBisa(
        array $promo,
        int $ongkir,
        bool $kirimanPertama,
        ?\DateTimeInterface $saat = null,
    ): ?string {
        if ($ongkir < $promo['minimum']) {
            return 'Berlaku mulai ongkir Rp'.number_format($promo['minimum'], 0, ',', '.').'.';
        }
        if (($promo['sekali_seumur_hidup'] ?? false) && ! $kirimanPertama) {
            return 'Hanya untuk kiriman pertama.';
        }

        /*
         * Jam dan hari dibaca di WIB, bukan di zona aplikasi yang UTC. Voucher
         * "kirim pagi" yang aktif pukul 13.00 bukan sekadar salah — ia
         * ditawarkan ke orang yang tidak bisa memakainya, lalu ditolak di layar
         * bayar.
         */
        $lokal = \DateTimeImmutable::createFromInterface($saat ?? new \DateTimeImmutable)
            ->setTimezone(new \DateTimeZone(JemputTarif::ZONA));

        if (isset($promo['jam']) && ! in_array((int) $lokal->format('G'), $promo['jam'], true)) {
            return 'Berlaku pada jam tertentu saja.';
        }
        if (isset($promo['hari']) && ! in_array((int) $lokal->format('w'), $promo['hari'], true)) {
            return 'Berlaku pada hari tertentu saja.';
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function cari(string $kode): ?array
    {
        foreach (self::KATALOG as $promo) {
            if ($promo['kode'] === strtoupper(trim($kode))) {
                return $promo;
            }
        }

        return null;
    }
}
