<?php

namespace App\Console\Commands;

use App\Models\MitraProfile;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * Menerima pesanan BisaBersih dari terminal.
 *
 * Aplikasi mitra belum punya layar "terima pesanan", jadi selama pengembangan
 * inilah cara memicu perpindahan halaman status customer dari layar tunggu ke
 * layar "Pesanan Diterima".
 *
 *   php artisan bersih:terima 8MVB8ZF
 *   php artisan bersih:terima 8MVB8ZF --cleaner=7
 *   php artisan bersih:terima --terakhir
 */
class BersihTerimaPesanan extends Command
{
    protected $signature = 'bersih:terima
        {nomor? : Nomor pesanan, boleh potongan belakang invoice}
        {--cleaner= : user_id mitra yang menerima; default cleaner pilihan customer atau mitra pertama}
        {--terakhir : Ambil pesanan BisaBersih terbaru yang masih menunggu}';

    protected $description = 'Tandai pesanan BisaBersih sebagai diterima cleaner (alat bantu pengembangan)';

    public function handle(): int
    {
        $task = $this->option('terakhir')
            ? $this->pesananTerakhir()
            : $this->pesananBernomor((string) $this->argument('nomor'));

        if (! $task) {
            return self::FAILURE;
        }

        if (in_array($task->status, ['accepted', 'in_progress', 'completed'], true)) {
            $this->warn("Pesanan {$task->nomor_invoice} sudah berstatus '{$task->status}'.");

            return self::SUCCESS;
        }

        $profil = $this->cleanerUntuk($task);
        if (! $profil) {
            return self::FAILURE;
        }

        $task->update([
            'mitra_id' => $profil->user_id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->info("Pesanan {$task->nomor_invoice} diterima oleh {$profil->user?->name}.");
        $this->line('Halaman status customer akan berganti sendiri dalam 4 detik.');

        return self::SUCCESS;
    }

    private function pesananTerakhir(): ?Task
    {
        $task = Task::where('judul', 'like', 'BisaBersih%')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $task) {
            $this->error('Tidak ada pesanan BisaBersih yang sedang menunggu.');
        }

        return $task;
    }

    private function pesananBernomor(string $nomor): ?Task
    {
        $nomor = strtoupper(trim($nomor));

        if ($nomor === '') {
            $this->error('Sebutkan nomor pesanan, atau pakai --terakhir.');

            return null;
        }

        $task = Task::where('judul', 'like', 'BisaBersih%')
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->first();

        if (! $task) {
            $this->error("Pesanan BisaBersih dengan nomor {$nomor} tidak ditemukan.");
        }

        return $task;
    }

    /**
     * Cleaner yang menerima.
     *
     * Kalau customer sudah memilih orangnya, dialah yang dipakai — memaksakan
     * mitra lain di sini akan menampilkan orang yang tidak dipesan.
     */
    private function cleanerUntuk(Task $task): ?MitraProfile
    {
        $id = $this->option('cleaner') ?: $task->mitra_id;

        $profil = $id
            ? MitraProfile::with('user')->where('user_id', $id)->first()
            : MitraProfile::with('user')->orderBy('user_id')->first();

        if (! $profil) {
            $this->error('Belum ada mitra terdaftar. Jalankan: php artisan db:seed --class=MitraBersihSeeder');
        }

        return $profil;
    }
}
