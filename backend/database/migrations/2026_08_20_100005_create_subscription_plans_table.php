<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master paket langganan (Basic / Premium / Family).
 *
 * Angka benefit (potongan per transaksi, kuota diskon, kuota ongkir gratis)
 * dipindah dari konstanta frontend ke DB agar bisa diubah tanpa deploy dan
 * dijadikan acuan tunggal saat menghitung tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // basic, premium, family
            $table->string('nama');
            $table->decimal('harga', 10, 2);
            $table->decimal('diskon_per_transaksi', 10, 2)->default(0);
            $table->unsignedSmallInteger('kuota_diskon')->default(0);
            $table->decimal('min_ongkir_gratis', 10, 2)->default(0);
            $table->unsignedSmallInteger('kuota_ongkir')->default(0);
            $table->json('benefit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
