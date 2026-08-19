<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function wallet(float $saldo = 0): Wallet
    {
        $user = User::factory()->create(['role' => 'mitra']);

        return Wallet::create(['user_id' => $user->id, 'saldo' => $saldo]);
    }

    private function ledger(): WalletLedger
    {
        return app(WalletLedger::class);
    }

    public function test_credit_dan_debit_memutasi_saldo(): void
    {
        $wallet = $this->wallet();

        $this->ledger()->credit($wallet, 'topup', 100000);
        $this->assertEquals(100000, $wallet->fresh()->saldo);

        $this->ledger()->debit($wallet, 'payment', 30000);
        $this->assertEquals(70000, $wallet->fresh()->saldo);

        // Setiap mutasi mencatat saldo sebelum & sesudah.
        $terakhir = $wallet->transactions()->latest('id')->first();
        $this->assertEquals(100000, $terakhir->saldo_sebelum);
        $this->assertEquals(70000, $terakhir->saldo_sesudah);
    }

    public function test_idempotency_key_mencegah_dobel_posting(): void
    {
        $wallet = $this->wallet();

        $key = 'cashback:pesanan-1';
        $this->ledger()->credit($wallet, 'cashback', 5000, idempotencyKey: $key);
        $this->ledger()->credit($wallet, 'cashback', 5000, idempotencyKey: $key); // harus diabaikan

        $this->assertEquals(5000, $wallet->fresh()->saldo);
        $this->assertEquals(1, WalletTransaction::where('wallet_id', $wallet->id)->count());
    }

    public function test_debit_ditolak_saat_saldo_tidak_cukup(): void
    {
        $wallet = $this->wallet(20000);

        try {
            $this->ledger()->debit($wallet, 'payout', 50000);
            $this->fail('Seharusnya melempar InsufficientBalanceException.');
        } catch (InsufficientBalanceException $e) {
            $this->assertEquals(20000, $e->saldo);
            $this->assertEquals(50000, $e->diminta);
        }

        // Saldo tidak berubah dan tidak ada baris ledger yang tertulis.
        $this->assertEquals(20000, $wallet->fresh()->saldo);
        $this->assertEquals(0, WalletTransaction::where('wallet_id', $wallet->id)->count());
    }

    public function test_saldo_selalu_cocok_dengan_buku_besar(): void
    {
        $wallet = $this->wallet();
        $l = $this->ledger();

        $l->credit($wallet, 'topup', 100000);
        $l->debit($wallet, 'payment', 25000);
        $l->credit($wallet, 'cashback', 3000);
        $l->debit($wallet, 'payout', 40000);

        $kredit = WalletTransaction::where('wallet_id', $wallet->id)->where('arah', 'credit')->sum('jumlah');
        $debit = WalletTransaction::where('wallet_id', $wallet->id)->where('arah', 'debit')->sum('jumlah');

        $this->assertEquals($kredit - $debit, (float) $wallet->fresh()->saldo);
        $this->assertEquals(38000, $wallet->fresh()->saldo);
    }

    public function test_payout_reversal_mengembalikan_dana(): void
    {
        $wallet = $this->wallet(100000);
        $l = $this->ledger();

        // Debit saat pengajuan penarikan.
        $l->debit($wallet, 'payout', 60000, keterangan: 'Pengajuan penarikan');
        $this->assertEquals(40000, $wallet->fresh()->saldo);

        // Ditolak → dana kembali.
        $l->credit($wallet, 'payout_reversal', 60000, keterangan: 'Penarikan ditolak');
        $this->assertEquals(100000, $wallet->fresh()->saldo);
    }
}
