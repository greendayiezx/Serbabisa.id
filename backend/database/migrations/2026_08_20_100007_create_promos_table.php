<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master promo/voucher BisaBelanja.
 *
 * Aturan promo (tier, pengguna baru, gratis ongkir, jam emas, payday, weekend,
 * bundle, cashback, referral) dipindah dari konstanta frontend ke DB. Kuota
 * (total / harian / per user) disimpan di sini dan ditegakkan bersama tabel
 * promo_redemptions — tanpa ini, syarat "khusus transaksi pertama" atau kuota
 * harian mustahil dijaga dan rawan disalahgunakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable()->unique();
            $table->string('judul');
            $table->string('ringkas')->nullable();
            $table->json('detail')->nullable();
            $table->string('emoji')->nullable();
            $table->enum('grup', [
                'tier', 'first-order', 'gratis-ongkir', 'jam-emas',
                'payday', 'weekend', 'bundle', 'cashback', 'referral',
            ]);
            $table->decimal('min_belanja', 12, 2)->default(0);
            $table->decimal('potongan_tetap', 12, 2)->default(0);
            $table->boolean('gratis_ongkir')->default(false);
            $table->decimal('cashback_persen', 5, 2)->nullable();
            $table->decimal('cashback_maks', 12, 2)->nullable();
            $table->unsignedInteger('kuota_total')->nullable();   // null = tak terbatas
            $table->unsignedInteger('kuota_harian')->nullable();
            $table->unsignedInteger('kuota_per_user')->default(1);
            $table->unsignedInteger('kuota_terpakai')->default(0); // proyeksi cepat, sumber kebenaran tetap redemptions
            $table->json('kategori_bundle')->nullable();
            $table->json('syarat')->nullable(); // aturan waktu/tanggal/status deklaratif
            $table->timestamp('berlaku_mulai')->nullable();
            $table->timestamp('berlaku_sampai')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grup', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
