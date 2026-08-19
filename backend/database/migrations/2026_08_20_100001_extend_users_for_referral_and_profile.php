<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi tabel users untuk kebutuhan kelas produksi:
 * - kode_referral: kode unik milik user untuk dibagikan (promo Traktiran Teman).
 * - direferal_oleh_id: siapa yang mengundang user ini (self-reference).
 * - foto_profil, last_login_at: kebutuhan profil & keamanan dasar.
 * - soft delete: akun tidak dihapus permanen agar jejak transaksi/finansial
 *   tetap utuh dan bisa diaudit (record keuangan tidak boleh hilang begitu saja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kode_referral')->nullable()->unique()->after('phone_verified_at');
            $table->foreignId('direferal_oleh_id')->nullable()->after('kode_referral')
                ->constrained('users')->nullOnDelete();
            $table->string('foto_profil')->nullable()->after('direferal_oleh_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direferal_oleh_id');
            $table->dropUnique(['kode_referral']);
            $table->dropColumn(['kode_referral', 'foto_profil', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
