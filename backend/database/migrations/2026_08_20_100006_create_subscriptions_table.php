<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langganan aktif milik user beserta sisa kuotanya.
 *
 * Kuota (diskon & ongkir gratis) disimpan sebagai sisa yang menurun tiap
 * pemakaian, supaya benefit "gratis ongkir 12x/bulan" bisa ditegakkan dan tidak
 * bisa dipakai melebihi jatah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->decimal('harga_dibayar', 10, 2)->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('berakhir_pada')->nullable();
            $table->unsignedSmallInteger('sisa_kuota_diskon')->default(0);
            $table->unsignedSmallInteger('sisa_kuota_ongkir')->default(0);
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
