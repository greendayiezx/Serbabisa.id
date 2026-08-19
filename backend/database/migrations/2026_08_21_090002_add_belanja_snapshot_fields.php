<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga field yang dibutuhkan agar nota pesanan bisa direkonstruksi utuh dari DB,
 * tanpa menebak atau menghitung ulang:
 *
 * - `tasks.toko` — sebelumnya nama gerai hanya menempel di `judul` sebagai
 *   "Pesanan BisaBelanja — Toko X", jadi membacanya kembali berarti membelah
 *   string. Kalau formatnya berubah, riwayat lama ikut rusak.
 *
 * - `payments.ongkir_normal` — ongkir sebelum digratiskan. `ongkir` menyimpan
 *   yang benar-benar ditagih (0 kalau gratis), sehingga tanpa kolom ini nota
 *   tidak bisa menampilkan tarif asli yang dicoret, dan nilai promo gratis
 *   ongkir tidak bisa diaudit.
 *
 * - `task_items.sku` — penghubung ke katalog frontend. `product_id` sudah ada,
 *   tapi tampilan butuh id katalog untuk mencari emoji/gambar item, dan item
 *   ketik manual ("Lainnya") memang tidak punya product_id sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('toko')->nullable()->after('deskripsi');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('ongkir_normal', 12, 2)->default(0)->after('ongkir');
        });

        Schema::table('task_items', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('product_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('toko');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('ongkir_normal');
        });

        Schema::table('task_items', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropColumn('sku');
        });
    }
};
