<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penawaran BisaBersih Kantor.
 *
 * Sengaja TIDAK menumpang tabel tasks. Sebuah task adalah satu pekerjaan;
 * penawaran adalah dokumen komersial yang punya masa berlaku, beberapa pilihan
 * paket, riwayat revisi, dan status persetujuan sendiri. Memaksakannya masuk ke
 * kolom JSON `tasks` akan membuat pertanyaan sesederhana "penawaran mana yang
 * kedaluwarsa minggu ini" jadi tidak bisa dijawab lewat query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penawaran', function (Blueprint $table) {
            $table->id();
            // Nomor yang disebut pelanggan, mis. OFF-000124.
            $table->string('nomor')->unique();
            // Permintaan penawaran yang melahirkannya. Boleh null kalau sales
            // membuatnya manual di luar aplikasi.
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();

            $table->string('nama_perusahaan');
            $table->string('nama_pic')->nullable();
            $table->string('telepon_pic')->nullable();
            $table->text('alamat');
            $table->text('ringkasan');

            $table->enum('status', [
                'ditinjau',      // data diterima, tim sedang meninjau
                'survei',        // survei lokasi dijadwalkan
                'dikirim',       // penawaran sudah bisa dilihat pelanggan
                'revisi',        // pelanggan minta perubahan
                'disetujui',
                'kedaluwarsa',
            ])->default('ditinjau');

            $table->date('berlaku_sampai')->nullable();
            $table->foreignId('paket_dipilih_id')->nullable();
            $table->timestamp('disetujui_pada')->nullable();

            /** Scope of work per area, biaya tambahan, dan pengecualian. */
            $table->json('scope')->nullable();
            $table->json('biaya_tambahan')->nullable();
            $table->json('pengecualian')->nullable();

            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('penawaran_paket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penawaran_id')->constrained('penawaran')->cascadeOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->text('ringkas')->nullable();
            $table->json('isi');
            $table->unsignedInteger('harga_per_kunjungan');
            $table->unsignedSmallInteger('kunjungan_per_bulan');
            $table->unsignedInteger('harga_bulanan');
            $table->boolean('disarankan')->default(false);
            $table->timestamps();
        });

        Schema::create('penawaran_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penawaran_id')->constrained('penawaran')->cascadeOnDelete();
            /** Jenis perubahan yang diminta, mis. 'ubah-frekuensi'. */
            $table->json('permintaan');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penawaran_revisi');
        Schema::dropIfExists('penawaran_paket');
        Schema::dropIfExists('penawaran');
    }
};
