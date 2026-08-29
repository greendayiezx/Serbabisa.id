<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\FreonTarif;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Menuliskan hasil pemeriksaan teknisi dari terminal.
 *
 * Aplikasi teknisi belum punya layar "isi hasil pemeriksaan", jadi selama
 * pengembangan inilah cara memunculkan halaman Hasil Pemeriksaan di sisi
 * pelanggan.
 *
 *   php artisan ac:diagnosa SBB-260829-XXXX
 *   php artisan ac:diagnosa SBB-260829-XXXX --pekerjaan=perbaikan-bocor,freon-r32
 *   php artisan ac:diagnosa --terakhir --bocor=tidak
 */
class ACDiagnosa extends Command
{
    protected $signature = 'ac:diagnosa
        {nomor? : Nomor pesanan, boleh potongan belakang invoice}
        {--terakhir : Ambil pesanan Cek & Tambah Freon terbaru}
        {--pekerjaan= : Daftar id pekerjaan, dipisah koma}
        {--freon=r32 : Jenis freon hasil pembacaan teknisi}
        {--bocor=ya : Ada indikasi kebocoran (ya/tidak)}';

    protected $description = 'Tulis hasil pemeriksaan AC pada pesanan Cek & Tambah Freon (alat bantu pengembangan)';

    public function handle(): int
    {
        $task = $this->option('terakhir')
            ? Task::where('judul', 'like', 'Servis AC — Cek & Tambah Freon%')->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Pesanan tidak ditemukan.');

            return self::FAILURE;
        }

        $detail = $task->detail_layanan ?? [];
        if (($detail['layanan'] ?? null) !== 'freon') {
            $this->error("Pesanan {$task->nomor_invoice} bukan Cek & Tambah Freon.");

            return self::FAILURE;
        }

        $bocor = $this->option('bocor') !== 'tidak';
        $pekerjaan = array_values(array_filter(explode(',', (string) $this->option('pekerjaan'))));

        if (! $pekerjaan) {
            // Rekomendasi bawaan mengikuti temuan: kebocoran diperbaiki dulu,
            // baru diisi — mengisi tanpa menambal hanya mengulang masalahnya.
            $pekerjaan = $bocor
                ? ['perbaikan-bocor', 'vakum', 'freon-'.$this->option('freon')]
                : ['freon-'.$this->option('freon')];
        }

        $galat = Validator::make(
            ['pekerjaan' => $pekerjaan, 'freon' => $this->option('freon')],
            [
                'pekerjaan.*' => [Rule::in(array_keys(FreonTarif::PEKERJAAN))],
                'freon' => [Rule::in(['r32', 'r410a', 'r22'])],
            ],
        )->errors();

        if ($galat->isNotEmpty()) {
            $this->error($galat->first());
            $this->line('Pekerjaan yang dikenal: '.implode(', ', array_keys(FreonTarif::PEKERJAAN)));

            return self::FAILURE;
        }

        $diagnosis = [
            'status_freon' => $bocor ? 'Tekanan di bawah standar' : 'Tekanan sedikit di bawah standar',
            'indikasi_kebocoran' => $bocor ? 'Ditemukan pada sambungan pipa' : 'Tidak ditemukan',
            'jenis_freon' => strtoupper((string) $this->option('freon')),
            'rekomendasi' => $bocor
                ? 'Perbaiki sambungan terlebih dahulu, kemudian isi freon sesuai kebutuhan agar tidak cepat habis lagi.'
                : 'Isi freon sesuai kebutuhan; sistem tidak menunjukkan tanda kebocoran.',
            'pekerjaan' => $pekerjaan,
            'diperiksa_pada' => now()->toIso8601String(),
            'keputusan' => null,
        ];

        $task->update(['detail_layanan' => [...$detail, 'diagnosis' => $diagnosis]]);

        $rekomendasi = (new FreonTarif)->rekomendasi($pekerjaan, (int) ($detail['biaya_pemeriksaan'] ?? 0));

        $this->info("Hasil pemeriksaan tersimpan untuk {$task->nomor_invoice}.");
        foreach ($rekomendasi['baris'] as $b) {
            $this->line('  · '.$b['nama'].' — Rp'.number_format($b['harga'], 0, ',', '.'));
        }
        $this->line('  Total setelah kredit pemeriksaan: Rp'.number_format($rekomendasi['total'], 0, ',', '.'));

        return self::SUCCESS;
    }

    private function cari(string $nomor): ?Task
    {
        $nomor = strtoupper(trim($nomor));

        return Task::query()
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->first();
    }
}
