<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku besar dompet — APPEND-ONLY.
 *
 * Ini inti perbaikan kelas korporat: kolom `saldo` di `wallets` bukan lagi
 * angka yang diubah-ubah tanpa jejak, melainkan PROYEKSI dari penjumlahan
 * ledger ini. Setiap mutasi (topup, cashback, pembayaran, refund, payout,
 * biaya langganan, penyesuaian) menghasilkan satu baris dengan saldo sebelum &
 * sesudah, sehingga saldo bisa diaudit dan direkonsiliasi kapan saja.
 *
 * - `jumlah` selalu positif; arah kredit/debit yang menentukan tanda.
 * - `idempotency_key` mencegah double-posting saat webhook/retry.
 * - `referensi_type/id` menautkan mutasi ke sumbernya (Task, PayoutRequest, dst)
 *   secara polimorfik tanpa memaksa satu FK kaku.
 *
 * Tabel `wallets` juga diberi `saldo_tertahan` (dana pending, mis. escrow yang
 * belum cair) dan `mata_uang`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('saldo_tertahan', 12, 2)->default(0)->after('saldo');
            $table->char('mata_uang', 3)->default('IDR')->after('saldo_tertahan');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->enum('tipe', [
                'topup', 'payment', 'refund', 'cashback',
                'payout', 'payout_reversal', 'subscription_fee', 'adjustment',
            ]);
            $table->enum('arah', ['credit', 'debit']);
            $table->decimal('jumlah', 12, 2); // selalu positif
            $table->decimal('saldo_sebelum', 12, 2);
            $table->decimal('saldo_sesudah', 12, 2);
            $table->nullableMorphs('referensi'); // referensi_type + referensi_id
            $table->string('keterangan')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['saldo_tertahan', 'mata_uang']);
        });
    }
};
