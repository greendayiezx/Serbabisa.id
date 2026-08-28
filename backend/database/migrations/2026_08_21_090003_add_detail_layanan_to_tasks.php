<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian khas tiap layanan, disimpan sebagai JSON.
 *
 * BisaAngkut sebelumnya mendapat kolom sendiri (kendaraan, jumlah_helper,
 * berat_total, proteksi_*). Pola itu tidak bisa diteruskan: BisaBersih saja
 * butuh tipe properti, jumlah kamar tidur & mandi, luas, kondisi, hewan
 * peliharaan, daftar area, frekuensi, dan akses masuk — sembilan kolom yang
 * selalu NULL untuk enam layanan lainnya, dan akan bertambah tiap layanan baru.
 *
 * Satu kolom JSON menampung semuanya tanpa membuat tabel `tasks` melebar. Yang
 * TIDAK boleh masuk ke sini: apa pun yang perlu dijumlah, difilter, atau
 * diurutkan lintas pesanan — itu tetap harus jadi kolom sungguhan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('detail_layanan')->nullable()->after('proteksi_harga');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('detail_layanan');
        });
    }
};
