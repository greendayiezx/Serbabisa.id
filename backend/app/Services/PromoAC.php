<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

/**
 * Promo Servis AC — sisi server.
 *
 * Katalognya salinan dari frontend/src/lib/promoAC.ts; ACPromoTest menjaga
 * keduanya tetap sama. Seperti promo lain, potongan dihitung ULANG di sini:
 * klien hanya mengirim kodenya.
 */
class PromoAC
{
    /**
     * @var array<string, array{min:int, persen?:int, maks?:int, potongan?:int, min_unit?:int}>
     */
    public const VOUCHER = [
        // Akuisisi: sekali seumur akun, ditagih dari pesanan Servis AC pertama.
        'ACBARU25' => ['min' => 100_000, 'potongan' => 25_000, 'pengguna_baru' => true],

        // Banner "Cuci AC diskon 20%". Dibatasi Rp50.000 supaya paket termahal
        // tidak memotong margin lebih dalam daripada paket termurah.
        'GERCEPAC' => ['min' => 100_000, 'persen' => 20, 'maks' => 50_000],

        // Bertingkat menurut jumlah unit: satu kunjungan teknisi mengerjakan
        // beberapa AC, jadi biaya perjalanannya makin terbagi.
        'ACHEMAT2' => ['min' => 200_000, 'potongan' => 30_000, 'min_unit' => 2],
        // Minimum 250rb, bukan 300rb: tiga unit paket termurah bertagihan
        // Rp280.000 setelah potongan bundling — minimum yang lebih tinggi
        // membuat promo ini justru tidak berlaku pada kasus yang ia tuju.
        'ACHEMAT3' => ['min' => 250_000, 'potongan' => 50_000, 'min_unit' => 3],
    ];

    /**
     * @return array{potongan:int, berlaku:bool, alasan:?string}
     */
    public function hitung(?string $kode, int $nilaiTransaksi, int $unit, ?User $user = null): array
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

        if (isset($v['min_unit']) && $unit < $v['min_unit']) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => "Promo ini butuh minimal {$v['min_unit']} unit AC.",
            ];
        }

        /*
         * "Pengguna baru" diperiksa dari RIWAYAT SERVIS AC, bukan tanggal daftar
         * akun: pelanggan lama yang baru pertama kali mencuci AC memang baru
         * bagi layanan ini, dan itulah yang promo ini tuju.
         */
        if (($v['pengguna_baru'] ?? false) && $user && ! self::penggunaBaru($user)) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => 'Promo ini hanya untuk pesanan Servis AC pertama.',
            ];
        }

        $potongan = $v['potongan'] ?? 0;
        if (isset($v['persen'])) {
            $kasar = (int) round($nilaiTransaksi * $v['persen'] / 100);
            $potongan = min($kasar, $v['maks'] ?? $kasar);
        }

        $potongan = min($potongan, $nilaiTransaksi);

        return ['potongan' => $potongan, 'berlaku' => $potongan > 0, 'alasan' => null];
    }

    /** Belum pernah memesan Servis AC sama sekali. */
    public static function penggunaBaru(User $user): bool
    {
        return ! Task::where('customer_id', $user->id)
            ->where('judul', 'like', 'Servis AC%')
            ->exists();
    }
}
