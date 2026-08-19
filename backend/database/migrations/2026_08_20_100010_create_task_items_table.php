<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris rincian pesanan (line items) untuk BisaBelanja & pesanan berbasis item.
 *
 * Kolom nama/kategori/satuan/harga disimpan sebagai SNAPSHOT saat checkout,
 * bukan diambil ulang dari `products`. Prinsip akuntansi: invoice historis tidak
 * boleh berubah kalau harga katalog naik atau produk dihapus. `product_id`
 * boleh null untuk item yang diketik manual oleh customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama');          // snapshot
            $table->string('kategori')->nullable(); // snapshot
            $table->string('satuan')->nullable();   // snapshot
            $table->decimal('harga_satuan', 12, 2)->nullable(); // null = item ketik manual tanpa harga
            $table->unsignedInteger('qty')->default(1);
            $table->string('catatan')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable(); // harga_satuan * qty
            $table->timestamps();

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
    }
};
