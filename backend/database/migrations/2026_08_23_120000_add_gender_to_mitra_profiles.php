<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gender mitra — dipakai memilih ilustrasi avatar di halaman BisaBersih.
 *
 * Disimpan sebagai data, bukan ditebak dari nama: menebak jenis kelamin dari
 * nama sering salah dan bukan sesuatu yang boleh diputuskan tampilan. Boleh
 * kosong; kalau tidak diisi, halaman menampilkan avatar netral berisi inisial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra_profiles', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('foto_selfie');
        });
    }

    public function down(): void
    {
        Schema::table('mitra_profiles', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
