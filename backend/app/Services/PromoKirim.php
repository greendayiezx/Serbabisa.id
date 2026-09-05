<?php

namespace App\Services;

/**
 * Voucher BisaKirim.
 *
 * Aturan pembatasnya sengaja MEMINJAM {@see PromoJemput::BATAS_KOMISI}, bukan
 * menyalin angkanya: kalau suatu hari batas itu dinilai ulang, ia harus
 * berubah di kedua layanan sekaligus. Dua katalog yang menyimpan angka sendiri
 * akan mulai berbeda pada perubahan pertama, dan yang ketinggalan tidak akan
 * memberi tanda apa pun.
 *
 * Alasannya sama pula: kurir menerima {@see KirimTarif::BAGI_MITRA} dari
 * ongkir, dan bagiannya tidak boleh ikut dipotong voucher. Seluruh potongan
 * diambil dari komisi.
 *
 * Premi proteksi TIDAK pernah ikut dipotong. Uang itu disiapkan untuk mengganti
 * barang orang; mendiskonnya berarti mendiskon dana penggantian.
 */
class PromoKirim
{
    /**
     * @var list<array<string, mixed>>
     */
    public const KATALOG = [
        [
            'kode' => 'KIRIMBARU',
            'nama' => 'Kiriman pertama',
            'jenis' => 'akuisisi',
            'sekali_seumur_hidup' => true,
            'persen' => 30,
            'maks' => 10_000,
            'minimum' => 12_000,
            'deskripsi' => 'Diskon 30% sampai Rp10.000 untuk kiriman pertama.',
        ],
        [
            'kode' => 'KIRIM10',
            'nama' => 'Hemat kirim',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 10,
            'maks' => 8_000,
            'minimum' => 25_000,
            'deskripsi' => 'Diskon 10% sampai Rp8.000, mulai ongkir Rp25.000.',
        ],
        [
            'kode' => 'KIRIMJAUH',
            'nama' => 'Kiriman jauh',
            'jenis' => 'berulang',
            'sekali_seumur_hidup' => false,
            'persen' => 10,
            'maks' => 15_000,
            'minimum' => 60_000,
            'deskripsi' => 'Diskon 10% sampai Rp15.000, mulai ongkir Rp60.000.',
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function tersedia(int $ongkir, int $komisi, bool $kirimanPertama): array
    {
        $hasil = [];
        foreach (self::KATALOG as $promo) {
            $alasan = $this->kenapaTidakBisa($promo, $ongkir, $kirimanPertama);

            $hasil[] = [
                ...$promo,
                'potongan' => $alasan ? 0 : $this->potongan($promo, $ongkir, $komisi),
                'bisa_dipakai' => $alasan === null,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }

    /**
     * Potongan sesudah semua batas.
     *
     * Batas terakhir yang menahan: berapa pun yang tertulis di katalog, promo
     * berulang tidak pernah memakan lebih dari separuh komisi.
     */
    public function potongan(array $promo, int $ongkir, int $komisi): int
    {
        $nilai = min((int) floor($ongkir * $promo['persen'] / 100), (int) $promo['maks']);

        $batas = $promo['jenis'] === 'akuisisi'
            ? PromoJemput::BATAS_AKUISISI
            : (int) floor($komisi * PromoJemput::BATAS_KOMISI);

        return max(0, min($nilai, $batas));
    }

    public function kenapaTidakBisa(array $promo, int $ongkir, bool $kirimanPertama): ?string
    {
        if ($ongkir < $promo['minimum']) {
            return 'Berlaku mulai ongkir Rp'.number_format($promo['minimum'], 0, ',', '.').'.';
        }
        if (($promo['sekali_seumur_hidup'] ?? false) && ! $kirimanPertama) {
            return 'Hanya untuk kiriman pertama.';
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
