<?php

namespace App\Services;

use App\Models\User;

/**
 * Promo BisaBersih Deep Cleaning — sisi server.
 *
 * Katalognya salinan dari frontend/src/lib/promoBersihDeep.ts, hanya bidang
 * yang dibutuhkan untuk MENGHITUNG. DeepPromoTest menjaga keduanya tetap sama.
 *
 * Seperti promo kantor, potongan dihitung ULANG di sini: klien hanya mengirim
 * kodenya. Layar boleh menampilkan estimasi, tapi yang menagih adalah ini.
 */
class PromoDeep
{
    /**
     * @var array<string, array{min:int, potongan:int, pengguna_baru?:bool, paket?:list<string>}>
     */
    public const VOUCHER = [
        'DEEPBARU50' => ['min' => 400_000, 'potongan' => 50_000, 'pengguna_baru' => true],
        'DEEP60' => ['min' => 600_000, 'potongan' => 60_000],
        'DEEP100' => ['min' => 1_000_000, 'potongan' => 100_000],
        'PINDAHBERSIH' => [
            'min' => 1_000_000,
            'potongan' => 150_000,
            'paket' => ['move_in', 'pasca_renovasi'],
        ],
    ];

    /**
     * Hitung potongan untuk satu kode.
     *
     * Mengembalikan ALASAN penolakan, bukan sekadar nol — pemanggilnya perlu
     * bisa memberi tahu pelanggan mengapa promonya tidak terpakai.
     *
     * @return array{potongan:int, berlaku:bool, alasan:?string}
     */
    public function hitung(?string $kode, int $nilaiTransaksi, string $paket, ?User $user = null): array
    {
        if (! $kode) {
            return ['potongan' => 0, 'berlaku' => false, 'alasan' => null];
        }

        $kode = strtoupper(trim($kode));
        $v = self::VOUCHER[$kode] ?? null;

        if (! $v) {
            return ['potongan' => 0, 'berlaku' => false, 'alasan' => 'Kode promo tidak dikenal.'];
        }

        if ($nilaiTransaksi < $v['min']) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => 'Transaksi belum mencapai minimum Rp'.number_format($v['min'], 0, ',', '.').'.',
            ];
        }

        if (isset($v['paket']) && ! in_array($paket, $v['paket'], true)) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => 'Promo ini hanya untuk paket Move-In dan Pasca Renovasi.',
            ];
        }

        /*
         * "Pengguna baru" diperiksa dari RIWAYAT PESANAN BisaBersih — aturan
         * yang sama dengan promo kantor, jadi logikanya dipakai ulang alih-alih
         * ditulis dua versi yang bisa menyimpang.
         */
        if (($v['pengguna_baru'] ?? false) && $user && ! PromoKantor::penggunaBaru($user)) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => 'Promo ini hanya untuk pesanan BisaBersih pertama.',
            ];
        }

        // Potongan tidak boleh melebihi tagihannya sendiri.
        $potongan = min($v['potongan'], $nilaiTransaksi);

        return ['potongan' => $potongan, 'berlaku' => $potongan > 0, 'alasan' => null];
    }
}
