<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog produk BisaBelanja.
 *
 * Sebelumnya katalog hidup sebagai blob JSON 593 KB di frontend. Dipindah ke DB
 * supaya harga bisa diubah tanpa deploy, stok bisa dilacak, dan produk bisa
 * dinonaktifkan. Harga dipisah dua: `harga_dasar` (modal/estimasi belanja) dan
 * `harga_jual` (yang ditagih ke customer, sudah termasuk markup) — margin
 * platform bisa dihitung dan diaudit per item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->string('satuan'); // kg, pcs, pack, sisir, cup
            $table->decimal('harga_dasar', 12, 2);
            $table->decimal('harga_jual', 12, 2);
            $table->string('gambar')->nullable();
            $table->unsignedInteger('berat_gram')->nullable();
            $table->integer('stok')->nullable(); // null = tidak dilacak / selalu tersedia
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
