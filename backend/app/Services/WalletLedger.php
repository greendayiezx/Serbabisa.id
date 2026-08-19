<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalan untuk memutasi saldo dompet.
 *
 * Semua perubahan saldo WAJIB lewat sini — jangan pernah menyentuh
 * `wallets.saldo` langsung. Dengan begitu:
 *   - Tiap mutasi menghasilkan satu baris buku besar (append-only) dengan saldo
 *     sebelum & sesudah, sehingga saldo bisa diaudit dan direkonsiliasi.
 *   - Baris dompet dikunci (lockForUpdate) selama transaksi, mencegah lost
 *     update saat dua permintaan datang bersamaan.
 *   - `idempotency_key` mencegah dobel-posting saat webhook/registri diulang.
 */
class WalletLedger
{
    /** Tipe mutasi yang menambah saldo. */
    public const KREDIT = ['topup', 'cashback', 'refund', 'payout_reversal'];

    /** Tipe mutasi yang mengurangi saldo. */
    public const DEBIT = ['payment', 'payout', 'subscription_fee'];

    /**
     * Catat satu mutasi saldo dan kembalikan baris buku besarnya.
     *
     * @param  Wallet       $wallet          Dompet yang dimutasi.
     * @param  string       $tipe            Kategori mutasi (lihat KREDIT/DEBIT).
     * @param  string       $arah            'credit' (menambah) atau 'debit' (mengurangi).
     * @param  float|int|string $jumlah      Nominal positif.
     * @param  Model|null   $referensi       Sumber mutasi (Task, PayoutRequest, ...).
     * @param  string|null  $idempotencyKey  Kunci unik agar tidak dobel-posting.
     * @param  bool         $bolehMinus      Izinkan saldo negatif (mis. koreksi admin).
     */
    public function post(
        Wallet $wallet,
        string $tipe,
        string $arah,
        float|int|string $jumlah,
        ?Model $referensi = null,
        ?string $keterangan = null,
        ?string $idempotencyKey = null,
        ?int $dibuatOleh = null,
        bool $bolehMinus = false,
    ): WalletTransaction {
        $jumlah = round((float) $jumlah, 2);

        if (! in_array($arah, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException("Arah mutasi tidak valid: {$arah}.");
        }
        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah mutasi harus lebih besar dari nol.');
        }

        return DB::transaction(function () use (
            $wallet, $tipe, $arah, $jumlah, $referensi, $keterangan, $idempotencyKey, $dibuatOleh, $bolehMinus
        ) {
            // Idempotensi: kalau kunci sudah pernah tercatat, kembalikan yang lama
            // tanpa memutasi apa pun.
            if ($idempotencyKey !== null) {
                $lama = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($lama !== null) {
                    return $lama;
                }
            }

            // Kunci baris dompet sampai transaksi selesai agar mutasi paralel
            // dijalankan berurutan, bukan bertabrakan.
            $terkunci = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $sebelum = (float) $terkunci->saldo;
            $delta = $arah === 'credit' ? $jumlah : -$jumlah;
            $sesudah = round($sebelum + $delta, 2);

            if ($sesudah < 0 && ! $bolehMinus) {
                throw new InsufficientBalanceException($sebelum, $jumlah);
            }

            $trx = new WalletTransaction([
                'wallet_id' => $terkunci->id,
                'tipe' => $tipe,
                'arah' => $arah,
                'jumlah' => $jumlah,
                'saldo_sebelum' => $sebelum,
                'saldo_sesudah' => $sesudah,
                'keterangan' => $keterangan,
                'idempotency_key' => $idempotencyKey,
                'dibuat_oleh' => $dibuatOleh,
            ]);

            if ($referensi !== null) {
                $trx->referensi()->associate($referensi);
            }

            try {
                $trx->save();
            } catch (UniqueConstraintViolationException) {
                // Balapan idempotensi: proses lain menang duluan dengan kunci yang
                // sama. Kembalikan hasilnya, jangan memutasi dua kali.
                return WalletTransaction::where('idempotency_key', $idempotencyKey)->firstOrFail();
            }

            $terkunci->saldo = $sesudah;
            $terkunci->save();

            // Sinkronkan instance yang dipegang pemanggil supaya nilainya terkini.
            $wallet->saldo = $sesudah;

            return $trx;
        }, attempts: 3);
    }

    /** Mutasi menambah saldo. */
    public function credit(
        Wallet $wallet,
        string $tipe,
        float|int|string $jumlah,
        ?Model $referensi = null,
        ?string $keterangan = null,
        ?string $idempotencyKey = null,
        ?int $dibuatOleh = null,
    ): WalletTransaction {
        return $this->post($wallet, $tipe, 'credit', $jumlah, $referensi, $keterangan, $idempotencyKey, $dibuatOleh);
    }

    /** Mutasi mengurangi saldo (ditolak bila saldo tak cukup, kecuali $bolehMinus). */
    public function debit(
        Wallet $wallet,
        string $tipe,
        float|int|string $jumlah,
        ?Model $referensi = null,
        ?string $keterangan = null,
        ?string $idempotencyKey = null,
        ?int $dibuatOleh = null,
        bool $bolehMinus = false,
    ): WalletTransaction {
        return $this->post($wallet, $tipe, 'debit', $jumlah, $referensi, $keterangan, $idempotencyKey, $dibuatOleh, $bolehMinus);
    }
}
