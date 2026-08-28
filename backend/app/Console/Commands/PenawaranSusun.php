<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\PenyusunPenawaran;
use Illuminate\Console\Command;

/**
 * Susun penawaran dari sebuah permintaan (alat bantu pengembangan).
 *
 * Di produksi ini pekerjaan tim sales lewat panel admin. Panel itu belum ada,
 * jadi selama pengembangan penawaran dibuat dari terminal:
 *
 *   php artisan penawaran:susun --terakhir
 *   php artisan penawaran:susun 96
 */
class PenawaranSusun extends Command
{
    protected $signature = 'penawaran:susun
        {task? : ID task permintaan penawaran}
        {--terakhir : Ambil permintaan penawaran terbaru}';

    protected $description = 'Susun dokumen penawaran BisaBersih Kantor dari sebuah permintaan';

    public function handle(PenyusunPenawaran $penyusun): int
    {
        $task = $this->option('terakhir')
            ? Task::where('judul', 'like', 'Permintaan Penawaran%')->latest('id')->first()
            : Task::find((int) $this->argument('task'));

        if (! $task) {
            $this->error('Permintaan penawaran tidak ditemukan. Kirim satu dulu lewat aplikasi.');

            return self::FAILURE;
        }

        // Permintaan yang dikirim lewat aplikasi menyimpan spesifikasinya
        // sebagai data. Penguraian teks di bawah hanya cadangan untuk
        // permintaan lama yang dibuat sebelum endpoint itu ada.
        $spek = $task->detail_layanan
            ?: $this->bacaSpek($task->deskripsi ?? '');
        $penawaran = $penyusun->dariTask($task, $spek);

        $this->info("Penawaran {$penawaran->nomor} dibuat untuk {$penawaran->nama_perusahaan}.");
        $this->line("Berlaku sampai {$penawaran->berlaku_sampai->toDateString()}.");
        $this->newLine();

        $this->table(
            ['Paket', 'Per kunjungan', 'Per bulan'],
            $penawaran->paket->map(fn ($p) => [
                $p->nama.($p->disarankan ? ' (disarankan)' : ''),
                'Rp'.number_format($p->harga_per_kunjungan, 0, ',', '.'),
                'Rp'.number_format($p->harga_bulanan, 0, ',', '.'),
            ])->all(),
        );

        $this->line("Buka di aplikasi: /penawaran/{$penawaran->nomor}");

        return self::SUCCESS;
    }

    /**
     * Baca spesifikasi dari deskripsi task.
     *
     * Form penawaran menulis datanya sebagai "Kunci: nilai" baris demi baris,
     * jadi di sinilah teks itu dibaca kembali jadi angka. Kalau sebuah kunci
     * tidak ada, nilainya dibiarkan kosong — bukan ditebak.
     *
     * @return array<string,mixed>
     */
    private function bacaSpek(string $deskripsi): array
    {
        $baris = [];
        foreach (preg_split('/\r?\n/', $deskripsi) as $l) {
            if (! str_contains($l, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $l, 2);
            $baris[mb_strtolower(trim($k))] = trim($v);
        }

        $angka = fn (?string $v) => $v !== null && preg_match('/\d+/', $v, $m) ? (int) $m[0] : null;

        $jenis = match (true) {
            str_contains($baris['jenis kantor'] ?? '', 'Small') => 'kecil',
            str_contains($baris['jenis kantor'] ?? '', 'Large') => 'besar',
            default => 'sedang',
        };

        $frekuensi = match (true) {
            str_contains($baris['frekuensi'] ?? '', 'Hari Kerja') => 'harian',
            str_contains($baris['frekuensi'] ?? '', '3x') => '3x-minggu',
            str_contains($baris['frekuensi'] ?? '', '2x') => '2x-minggu',
            str_contains($baris['frekuensi'] ?? '', '1x') => 'mingguan',
            str_contains($baris['frekuensi'] ?? '', 'Sekali') => 'sekali',
            default => '2x-minggu',
        };

        return [
            'nama_perusahaan' => $baris['nama perusahaan'] ?? null,
            'nama_pic' => $baris['pic'] ?? null,
            'telepon_pic' => $baris['whatsapp'] ?? null,
            'jenis_kantor' => $jenis,
            'frekuensi' => $frekuensi,
            'luas_m2' => $angka($baris['luas area'] ?? null),
            'jumlah_lantai' => $angka($baris['jumlah lantai'] ?? null),
            'workstation' => $angka($baris['workstation'] ?? null) ?? 0,
            'ruang_meeting' => $angka($baris['ruang meeting'] ?? null) ?? 0,
            'toilet' => $angka($baris['toilet'] ?? null) ?? 0,
            'pantry' => $angka($baris['pantry'] ?? null) ?? 0,
        ];
    }
}
