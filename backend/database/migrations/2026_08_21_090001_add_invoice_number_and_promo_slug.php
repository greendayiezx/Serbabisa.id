<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kunci gabung yang hilang saat frontend masih berjalan sendiri:
 *
 * - `tasks.nomor_invoice` — nomor invoice sebelumnya dibuat di browser, jadi
 *   tidak ada satu pun otoritas yang menjamin keunikannya. Sekarang server yang
 *   menerbitkan dan kolom unik ini yang menjaganya. Nullable karena tugas non-
 *   Belanja (jasa custom) belum memakai penomoran ini.
 *
 * - `promos.slug` — penghubung stabil ke id promo di frontend (`lib/promo.ts`).
 *   Tidak memakai `kode` yang sudah ada karena kode itu tampil ke pengguna
 *   sebagai kode voucher dan bisa berubah kapan saja demi kebutuhan pemasaran;
 *   slug tidak pernah tampil, jadi aman dijadikan kunci teknis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('nomor_invoice', 32)->nullable()->unique()->after('id');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->unique()->after('kode');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['nomor_invoice']);
            $table->dropColumn('nomor_invoice');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
