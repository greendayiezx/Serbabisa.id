<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relasi referral (promo Traktiran Teman).
 *
 * Satu user hanya bisa direferal sekali (unique referred_id). Reward ke pengundang
 * baru diberikan setelah pesanan pertama yang direferal SELESAI — karena itu ada
 * status pending → qualified → rewarded, dengan `task_id` sebagai pemicunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'qualified', 'rewarded'])->default('pending');
            $table->decimal('reward_cashback', 10, 2)->default(0);
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
