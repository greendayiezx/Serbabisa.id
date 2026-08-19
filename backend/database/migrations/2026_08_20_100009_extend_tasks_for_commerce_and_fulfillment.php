<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menjadikan `tasks` tulang punggung transaksi untuk SEMUA layanan, bukan hanya
 * jasa custom/fixed. Ditambah:
 * - Relasi ke alamat tersimpan, promo, dan langganan yang dipakai.
 * - `fulfillment_status`: alur pemenuhan BisaBelanja/BisaAngkut
 *   (diproses → dibelanjakan → diantar → selesai) yang beda dari `status` umum.
 * - Field BisaAngkut: kendaraan, jumlah helper, berat, proteksi/asuransi.
 * - Penjadwalan & data penerima.
 *
 * Kenapa memperluas tasks, bukan bikin tabel `orders` terpisah: menghindari
 * duplikasi customer_id/mitra_id/status/pembayaran. Satu tulang punggung
 * transaksi, dengan baris rincian di `task_items`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->after('mitra_id')
                ->constrained('addresses')->nullOnDelete();
            $table->foreignId('promo_id')->nullable()->after('category_id')
                ->constrained('promos')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->after('promo_id')
                ->constrained('subscriptions')->nullOnDelete();

            $table->enum('fulfillment_status', [
                'diproses', 'dibelanjakan', 'diantar', 'selesai',
            ])->nullable()->after('status');

            // Atribut BisaAngkut
            $table->string('kendaraan')->nullable()->after('catatan');
            $table->unsignedTinyInteger('jumlah_helper')->default(0)->after('kendaraan');
            $table->decimal('berat_total', 10, 2)->nullable()->after('jumlah_helper');
            $table->string('proteksi_label')->nullable()->after('berat_total');
            $table->decimal('proteksi_harga', 10, 2)->default(0)->after('proteksi_label');

            // Penjadwalan & penerima
            $table->timestamp('dijadwalkan_pada')->nullable()->after('proteksi_harga');
            $table->string('nama_penerima')->nullable()->after('dijadwalkan_pada');
            $table->string('telepon_penerima')->nullable()->after('nama_penerima');

            $table->softDeletes();

            $table->index('fulfillment_status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_id');
            $table->dropConstrainedForeignId('promo_id');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropIndex(['fulfillment_status']);
            $table->dropColumn([
                'fulfillment_status', 'kendaraan', 'jumlah_helper', 'berat_total',
                'proteksi_label', 'proteksi_harga', 'dijadwalkan_pada',
                'nama_penerima', 'telepon_penerima',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
