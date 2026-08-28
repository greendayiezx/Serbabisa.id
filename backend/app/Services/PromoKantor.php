<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

/**
 * Promo BisaBersih Kantor — sisi server.
 *
 * Sebelum ini promo kantor hanya hidup di frontend: layar konfirmasi
 * menampilkan total setelah potongan, tapi checkout tidak pernah mengirim kode
 * promonya dan server menagih harga penuh. Pelanggan melihat satu angka dan
 * dibebani angka lain.
 *
 * Katalognya salinan dari frontend/src/lib/promoBersihKantor.ts — hanya bidang
 * yang dibutuhkan untuk MENGHITUNG, bukan seluruh materi tampilan.
 * KantorPromoTest menjaga keduanya tetap sama.
 */
class PromoKantor
{
    /**
     * @var array<string, array{min:int, potongan?:int, persen?:int, maks?:int, pengguna_baru?:bool}>
     */
    public const VOUCHER = [
        'BISABARU' => ['min' => 500_000, 'persen' => 15, 'maks' => 100_000, 'pengguna_baru' => true],
        'KANTORBARU' => ['min' => 500_000, 'potongan' => 100_000, 'pengguna_baru' => true],
        'TRYOFFIC20' => ['min' => 500_000, 'persen' => 20, 'maks' => 150_000],
        'NEWOFFICE15' => ['min' => 500_000, 'persen' => 15, 'maks' => 300_000],
        'OFFICE50' => ['min' => 500_000, 'potongan' => 50_000],
        'SOFAVAC' => ['min' => 500_000, 'potongan' => 50_000],
        'PANTRYTOILET' => ['min' => 700_000, 'potongan' => 100_000],
        'REFERALKANTOR' => ['min' => 750_000, 'potongan' => 150_000],
        'OFFICE3BULAN' => ['min' => 1_000_000, 'persen' => 10, 'maks' => 250_000],
        'OFFICE100' => ['min' => 1_000_000, 'potongan' => 100_000],
        'MERDEKAKANTOR' => ['min' => 1_000_000, 'potongan' => 178_000],
    ];

    /**
     * Hitung potongan untuk satu kode.
     *
     * Mengembalikan alasan penolakan, bukan sekadar nol — pemanggilnya perlu
     * bisa memberi tahu pelanggan mengapa promonya tidak terpakai.
     *
     * @return array{potongan:int, berlaku:bool, alasan:?string}
     */
    public function hitung(?string $kode, int $nilaiTransaksi, ?User $user = null): array
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

        if (($v['pengguna_baru'] ?? false) && $user && ! self::penggunaBaru($user)) {
            return [
                'potongan' => 0,
                'berlaku' => false,
                'alasan' => 'Promo ini hanya untuk pesanan BisaBersih pertama.',
            ];
        }

        $potongan = $v['potongan'] ?? 0;
        if (isset($v['persen'])) {
            $kasar = (int) round($nilaiTransaksi * $v['persen'] / 100);
            $potongan = min($kasar, $v['maks'] ?? $kasar);
        }

        // Potongan tidak boleh melebihi tagihannya sendiri.
        $potongan = min($potongan, $nilaiTransaksi);

        return ['potongan' => $potongan, 'berlaku' => $potongan > 0, 'alasan' => null];
    }

    /**
     * Belum pernah memesan BisaBersih sama sekali.
     *
     * Diperiksa dari RIWAYAT PESANAN, bukan tanggal daftar akun: pelanggan lama
     * yang baru pertama kali mencoba layanan kebersihan memang pengguna baru
     * bagi BisaBersih, dan itulah yang promo ini tuju.
     *
     * Permintaan penawaran (REQ-) tidak dihitung — ia belum jadi pesanan.
     */
    public static function penggunaBaru(User $user): bool
    {
        return ! Task::where('customer_id', $user->id)
            ->where('judul', 'like', 'BisaBersih%')
            ->exists();
    }
}
