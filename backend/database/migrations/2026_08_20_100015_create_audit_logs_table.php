<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit untuk aksi sensitif (ubah status tugas, setujui payout, resolusi
 * dispute, penyesuaian saldo, perubahan role user, dsb).
 *
 * `perubahan` menyimpan before/after sebagai JSON. `auditable_*` menautkan ke
 * entitas mana pun secara polimorfik. Wajib ada di sistem yang memegang uang &
 * data KYC agar setiap tindakan istimewa bisa ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // pelaku
            $table->string('aksi');
            $table->nullableMorphs('auditable'); // auditable_type + auditable_id
            $table->json('perubahan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
