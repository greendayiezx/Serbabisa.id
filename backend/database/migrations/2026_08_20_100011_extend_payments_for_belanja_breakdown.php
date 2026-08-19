<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi rincian pembayaran agar invoice BisaBelanja bisa direkonstruksi
 * penuh dari DB. `jumlah` tetap total akhir yang ditagih, dengan komponennya:
 *
 *   jumlah = subtotal_barang - potongan + service_fee + ongkir
 *
 * `cashback` bukan pengurang tagihan sekarang — ia masuk saldo untuk transaksi
 * berikutnya (lihat wallet_transactions), tapi dicatat di sini untuk pelaporan.
 * Soft delete ditambahkan karena record keuangan tidak boleh hilang permanen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->after('task_id')
                ->constrained('promos')->nullOnDelete();
            $table->decimal('subtotal_barang', 12, 2)->default(0)->after('jumlah');
            $table->decimal('ongkir', 12, 2)->default(0)->after('subtotal_barang');
            $table->decimal('potongan', 12, 2)->default(0)->after('ongkir');
            $table->decimal('cashback', 12, 2)->default(0)->after('potongan');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_id');
            $table->dropColumn(['subtotal_barang', 'ongkir', 'potongan', 'cashback']);
            $table->dropSoftDeletes();
        });
    }
};
