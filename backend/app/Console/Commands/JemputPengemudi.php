<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

/**
 * Menjalankan sisi pengemudi dari terminal.
 *
 * Aplikasi pengemudi belum ada, jadi selama pengembangan inilah yang
 * memajukan tahap perjalanan di layar penumpang:
 *
 *   php artisan jemput:pengemudi --terakhir            # cari -> pengemudi dapat
 *   php artisan jemput:pengemudi --terakhir --tahap=tiba
 *   php artisan jemput:pengemudi --terakhir --tahap=jalan
 *   php artisan jemput:pengemudi --terakhir --tahap=selesai
 *
 * Urutannya dijaga: tahap tidak bisa melompat mundur, dan tidak bisa loncat ke
 * 'selesai' dari 'mencari'. Layar penumpang menggantungkan seluruh isinya pada
 * tahap ini — perjalanan yang tiba-tiba "selesai" tanpa pernah ada pengemudi
 * akan tampil sebagai tagihan tanpa asal-usul.
 */
class JemputPengemudi extends Command
{
    protected $signature = 'jemput:pengemudi
        {nomor? : Nomor pesanan, boleh potongan belakangnya}
        {--terakhir : Ambil perjalanan BisaJemput terbaru}
        {--tahap=dijemput : dijemput, tiba, jalan, atau selesai}
        {--nama= : Nama pengemudi}';

    protected $description = 'Majukan tahap perjalanan BisaJemput (alat bantu pengembangan)';

    /** @var list<string> */
    private const URUTAN = ['mencari', 'dijemput', 'tiba', 'jalan', 'selesai'];

    /** @var list<array<string, string>> */
    private const ARMADA = [
        ['nama' => 'Budi Santoso', 'kendaraan' => 'Honda Vario', 'plat' => 'B 1234 XYZ', 'warna' => 'Hitam'],
        ['nama' => 'Sri Wahyuni', 'kendaraan' => 'Toyota Avanza', 'plat' => 'B 5678 ABC', 'warna' => 'Silver'],
        ['nama' => 'Agus Priyanto', 'kendaraan' => 'Yamaha NMAX', 'plat' => 'B 9012 DEF', 'warna' => 'Putih'],
    ];

    public function handle(): int
    {
        $tahap = (string) $this->option('tahap');
        if (! in_array($tahap, self::URUTAN, true) || $tahap === 'mencari') {
            $this->error('Tahap harus salah satu dari: dijemput, tiba, jalan, selesai.');

            return self::FAILURE;
        }

        $task = $this->option('terakhir')
            ? Task::where('detail_layanan->layanan', 'jemput')->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Perjalanan tidak ditemukan.');

            return self::FAILURE;
        }

        $d = $task->detail_layanan ?? [];
        if (($d['layanan'] ?? null) !== 'jemput') {
            $this->error("Pesanan {$task->nomor_invoice} bukan perjalanan BisaJemput.");

            return self::FAILURE;
        }

        $sekarang = $d['tahap'] ?? 'mencari';
        $iSekarang = array_search($sekarang, self::URUTAN, true);
        $iTujuan = array_search($tahap, self::URUTAN, true);

        if ($iTujuan <= $iSekarang) {
            $this->error("Perjalanan sudah di tahap '{$sekarang}'; tidak bisa mundur ke '{$tahap}'.");

            return self::FAILURE;
        }
        if ($iTujuan > $iSekarang + 1) {
            $this->error("Dari '{$sekarang}' tahap berikutnya adalah '".self::URUTAN[$iSekarang + 1]."', bukan '{$tahap}'.");

            return self::FAILURE;
        }

        $pengemudi = $d['pengemudi'] ?? null;
        if ($tahap === 'dijemput') {
            $pilih = self::ARMADA[array_rand(self::ARMADA)];
            $pengemudi = [
                'nama' => $this->option('nama') ?: $pilih['nama'],
                'kendaraan' => $pilih['kendaraan'],
                'plat' => $pilih['plat'],
                'warna' => $pilih['warna'],
                'bintang' => 4.9,
                'perjalanan' => random_int(400, 3000),
                // Nomor pengemudi TIDAK ditaruh apa adanya: yang dipakai layar
                // adalah panggilan lewat aplikasi, bukan nomor pribadi.
                'telepon_tersamar' => true,
                'tiba_menit' => random_int(2, 7),
            ];
        }

        $task->update([
            'detail_layanan' => [...$d, 'tahap' => $tahap, 'pengemudi' => $pengemudi],
            'fulfillment_status' => $tahap === 'selesai' ? 'selesai' : 'diproses',
            'completed_at' => $tahap === 'selesai' ? now() : $task->completed_at,
        ]);

        $this->info("{$task->nomor_invoice}: {$sekarang} -> {$tahap}");
        if ($pengemudi) {
            $this->line("  Pengemudi : {$pengemudi['nama']} · {$pengemudi['kendaraan']} {$pengemudi['plat']}");
        }
        $this->line('  Buka      : /tasks/jemput/'.$task->nomor_invoice);

        return self::SUCCESS;
    }

    private function cari(string $nomor): ?Task
    {
        $nomor = strtoupper(trim($nomor));
        if ($nomor === '') {
            return null;
        }

        return Task::where('nomor_invoice', $nomor)
            ->orWhere('nomor_invoice', 'like', '%-'.$nomor)
            ->first();
    }
}
