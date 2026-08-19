<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori katalog BisaBelanja (Buah, Sayur, Daging, Sembako, dst).
 *
 * Berbeda dari tabel `categories` yang berisi jenis LAYANAN (BisaBelanja,
 * BisaAngkut, ...). Ini kategori PRODUK di dalam layanan BisaBelanja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('ikon')->nullable();
            $table->string('gambar')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
