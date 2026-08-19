<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pemakaian promo — satu baris per klaim.
 *
 * Ini sumber kebenaran untuk menegakkan kuota: "per user" dihitung dari jumlah
 * baris (promo_id, user_id), kuota harian dari (promo_id, redeemed_at) hari ini.
 * Nilai potongan/cashback disimpan apa adanya (snapshot) agar audit tagihan
 * lama tidak berubah walau aturan promo diperbarui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('potongan_barang', 12, 2)->default(0);
            $table->decimal('potongan_ongkir', 12, 2)->default(0);
            $table->decimal('cashback', 12, 2)->default(0);
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamps();

            $table->index(['promo_id', 'user_id']);
            $table->index(['promo_id', 'redeemed_at']);
            // Satu promo hanya boleh diklaim sekali per tugas (idempoten per checkout).
            $table->unique(['promo_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
    }
};
