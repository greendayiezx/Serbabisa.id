<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mitra_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('tipe', ['fixed', 'custom']);
            $table->string('judul');
            $table->text('deskripsi');
            $table->enum('status', ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('lokasi_alamat');
            $table->decimal('lokasi_lat', 10, 7);
            $table->decimal('lokasi_lng', 10, 7);
            $table->decimal('harga', 12, 2)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->json('foto')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index(['lokasi_lat', 'lokasi_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
