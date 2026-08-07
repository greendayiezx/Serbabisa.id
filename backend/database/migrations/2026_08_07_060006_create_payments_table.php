<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('jumlah', 12, 2);
            $table->decimal('komisi_platform', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'held', 'released', 'refunded', 'failed'])->default('pending');
            $table->string('metode')->nullable();
            $table->string('referensi_midtrans')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
