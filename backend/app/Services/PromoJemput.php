<?php

namespace App\Services;

/**
 * Promo BisaJemput.
 *
 * Katalog ini menahan satu godaan yang selalu muncul di layanan antar orang:
 * memasang diskon yang lebih besar daripada pendapatan platform per perjalanan.
 *
 * Pengemudi menerima {@see JemputTarif::BAGI_MITRA} dari tarif — bagiannya
 * tidak boleh ikut dipotong promo, karena dialah yang mengeluarkan bensin dan
 * waktunya. Jadi seluruh potongan diambil dari komisi, yang besarnya hanya 20%
 * dari tarif. "Diskon 20%" pada tarif berarti komisi habis; "diskon 25%" berarti
 * platform membayar penumpang untuk naik.
 *
 * Karena itu ada dua jenis promo di sini, dan bedanya bukan besar potongannya
 * melainkan DARI KANTONG MANA:
 *
 * - Promo BERULANG diambil dari komisi perjalanan itu sendiri, jadi dibatasi
 *   {@see BATAS_KOMISI} dari komisi. Ini kenapa tidak ada promo berulang di
 *   atas 10% tarif: 10% tarif tepat setengah komisi.
 * - Promo AKUISISI hanya berlaku sekali seumur akun, diambil dari anggaran
 *   mendapatkan pengguna baru, dan boleh melebihi komisi satu perjalanan
 *   selama masih di bawah {@see BATAS_AKUISISI}. Perjalanan pertama memang
 *   dibuat rugi dengan sengaja; yang tidak boleh adalah rugi itu berulang tiap
 *   hari tanpa ada yang menyadarinya.
 */
class PromoJemput
{
    /** Bagian komisi terbesar yang boleh dibagikan pada promo berulang. */
    public const BATAS_KOMISI = 0.50;

    /** Batas anggaran akuisisi per pengguna baru. */
    public const BATAS_AKUISISI = 25_000;

    /**
     * @var list<array<string, mixed>>
     */
    public const KATALOG = [
        [
            'kode' => 'JEMPUTBARU',
            'nama' => 'Perjalanan pertama',
            'jenis' => 'akuisisi',
            'sekali_seumur_hidup' => true,
            'persen' => 25,
            'maks' => 20_000,
            'minimum' => 25_000,
            'deskripsi' => 'Diskon 25% sampai Rp20.000 untuk perjalanan pertama.',
        ],
        [
            'kode' => 'JEMPUT10',
            'nama' => 'Hemat setiap hari',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 10,
            'maks' => 12_000,
            'minimum' => 40_000,
            'deskripsi' => 'Diskon 10% sampai Rp12.000, mulai tarif Rp40.000.',
        ],
        [
            'kode' => 'PAGI',
            'nama' => 'Berangkat kerja',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 8,
            'maks' => 6_000,
            'minimum' => 30_000,
            'jam' => [6, 7, 8],
            'hari' => [1, 2, 3, 4, 5],
            'deskripsi' => 'Diskon 8% sampai Rp6.000, Senin–Jumat jam 06.00–09.00.',
        ],
        [
            'kode' => 'SORE',
            'nama' => 'Pulang kerja',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 8,
            'maks' => 6_000,
            'minimum' => 30_000,
            'jam' => [17, 18, 19],
            'hari' => [1, 2, 3, 4, 5],
            'deskripsi' => 'Diskon 8% sampai Rp6.000, Senin–Jumat jam 17.00–20.00.',
        ],
        [
            'kode' => 'AKHIRPEKAN',
            'nama' => 'Jalan-jalan akhir pekan',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 10,
            'maks' => 15_000,
            'minimum' => 50_000,
            'hari' => [0, 6],
            'deskripsi' => 'Diskon 10% sampai Rp15.000, Sabtu dan Minggu.',
        ],
    ];

    /**
     * Promo yang berlaku untuk satu perjalanan.
     *
     * @return list<array<string, mixed>>
     */
    public function tersedia(int $tarif, int $komisi, bool $perjalananPertama, \DateTimeInterface $saat): array
    {
        $hasil = [];
        foreach (self::KATALOG as $promo) {
            $alasan = $this->kenapaTidakBisa($promo, $tarif, $perjalananPertama, $saat);
            $potongan = $alasan ? 0 : $this->potongan($promo, $tarif, $komisi);

            $hasil[] = [
                ...$promo,
                'potongan' => $potongan,
                'bisa_dipakai' => $alasan === null,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }

    /**
     * Potongan yang benar-benar diberikan, sesudah semua batas.
     *
     * Batas terakhir inilah yang penting: berapa pun yang tertulis di katalog,
     * promo berulang tidak pernah boleh memakan lebih dari setengah komisi.
     * Kalau suatu hari ada yang menaikkan angka di katalog tanpa menghitung
     * ulang, batas ini yang menahannya.
     */
    public function potongan(array $promo, int $tarif, int $komisi): int
    {
        $nilai = min((int) floor($tarif * $promo['persen'] / 100), (int) $promo['maks']);

        $batas = $promo['jenis'] === 'akuisisi'
            ? self::BATAS_AKUISISI
            : (int) floor($komisi * self::BATAS_KOMISI);

        return max(0, min($nilai, $batas));
    }

    /** Alasan promo tidak bisa dipakai, atau null kalau bisa. */
    public function kenapaTidakBisa(array $promo, int $tarif, bool $perjalananPertama, \DateTimeInterface $saat): ?string
    {
        if ($tarif < $promo['minimum']) {
            return 'Berlaku mulai tarif Rp'.number_format($promo['minimum'], 0, ',', '.').'.';
        }

        if (($promo['sekali_seumur_hidup'] ?? false) && ! $perjalananPertama) {
            return 'Hanya untuk perjalanan pertama.';
        }

        $jam = (int) $saat->format('G');
        $hari = (int) $saat->format('w');

        if (isset($promo['jam']) && ! in_array($jam, $promo['jam'], true)) {
            return 'Berlaku pada jam tertentu saja.';
        }
        if (isset($promo['hari']) && ! in_array($hari, $promo['hari'], true)) {
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
