<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat tersimpan milik user (Rumah / Kantor / lainnya).
 *
 * Sebelumnya alamat hanya disimpan di localStorage frontend, jadi hilang saat
 * ganti perangkat. Di sini alamat menjadi first-class: bisa dipakai ulang di
 * BisaBelanja, BisaAngkut, dan layanan lain, serta ditunjuk oleh tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->enum('jenis', ['rumah', 'kantor', 'lainnya'])->default('lainnya');
            $table->string('penerima')->nullable();
            $table->string('telepon')->nullable();
            $table->string('alamat');
            $table->string('detail')->nullable(); // patokan, nomor unit, catatan kurir
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
