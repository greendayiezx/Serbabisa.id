<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\TukangController;
use App\Models\Task;
use App\Services\TukangTarif;
use Illuminate\Console\Command;

/**
 * Menjalankan sisi tukang dari terminal.
 *
 * Sepola dengan jemput:pengemudi dan kirim:kurir, dan alasannya sama: aplikasi
 * mitra belum ada, jadi selama pengembangan inilah yang memajukan tahap
 * pekerjaan di layar pemesan.
 *
 *   php artisan tukang:mitra --terakhir                     # mencari -> menuju
 *   php artisan tukang:mitra --terakhir --tahap=tiba
 *   php artisan tukang:mitra --terakhir --tahap=penawaran   # RAB terbit
 *   php artisan tukang:mitra --terakhir --tahap=dikerjakan
 *   php artisan tukang:mitra --terakhir --tahap=selesai
 *
 * Urutannya dijaga: tahap tidak bisa mundur dan tidak bisa melompat. Layar
 * pemesan menggantungkan seluruh isinya pada tahap ini — pekerjaan yang
 * tiba-tiba "selesai" tanpa pernah ada penawaran akan tampil sebagai tagihan
 * tanpa asal-usul.
 *
 * Satu tahap punya penjagaan tambahan: `dikerjakan` tidak bisa dicapai dari
 * terminal. Pekerjaan hanya boleh dimulai setelah PEMESAN menyetujui
 * penawarannya lewat API — itulah inti janji "harganya disetujui dulu", dan
 * alat bantu pengembangan tidak boleh jadi jalan memutarnya.
 */
class TukangMitra extends Command
{
    protected $signature = 'tukang:mitra
        {nomor? : Nomor pekerjaan, boleh potongan belakangnya}
        {--terakhir : Ambil pekerjaan BisaTukang terbaru}
        {--tahap=menuju : menuju, tiba, penawaran, atau selesai}
        {--nama= : Nama tukang}
        {--total= : Total penawaran; hanya untuk --tahap=penawaran}';

    protected $description = 'Majukan tahap pekerjaan BisaTukang (alat bantu pengembangan)';

    /**
     * Tukang contoh; keahliannya dicocokkan dengan kategori pesanan.
     *
     * Yang salah keahlian bukan sekadar tidak enak dibaca: pemesan mencocokkan
     * nama dan keahlian di layar dengan orang yang mengetuk pintunya.
     *
     * @var list<array<string, mixed>>
     */
    private const MITRA = [
        ['nama' => 'Madrohim', 'keahlian' => ['bangunan', 'cat', 'renovasi'], 'tahun' => 7],
        ['nama' => 'Suhendar', 'keahlian' => ['listrik', 'elektronik'], 'tahun' => 10],
        ['nama' => 'Danang', 'keahlian' => ['bangunan', 'renovasi'], 'tahun' => 5],
        ['nama' => 'Yusuf', 'keahlian' => ['pipa', 'ac'], 'tahun' => 8],
        ['nama' => 'Bagas', 'keahlian' => ['pintu', 'lainnya'], 'tahun' => 4],
    ];

    public function handle(): int
    {
        $tahap = (string) $this->option('tahap');
        $urutan = TukangController::URUTAN;

        if (! in_array($tahap, $urutan, true) || $tahap === 'mencari') {
            $this->error('Tahap harus salah satu dari: menuju, tiba, penawaran, selesai.');

            return self::FAILURE;
        }
        if ($tahap === 'dikerjakan') {
            $this->error('Tahap "dikerjakan" hanya bisa dicapai lewat persetujuan penawaran oleh pemesan.');

            return self::FAILURE;
        }

        $task = $this->option('terakhir')
            ? Task::where('detail_layanan->layanan', 'tukang')->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Pekerjaan tidak ditemukan.');

            return self::FAILURE;
        }

        $d = $task->detail_layanan ?? [];
        if (($d['layanan'] ?? null) !== 'tukang') {
            $this->error("Pesanan {$task->nomor_invoice} bukan pekerjaan BisaTukang.");

            return self::FAILURE;
        }

        $sekarang = $d['tahap'] ?? 'mencari';
        $iSekarang = array_search($sekarang, $urutan, true);
        $iTujuan = array_search($tahap, $urutan, true);

        if ($iSekarang === false) {
            $this->error("Pekerjaan ini ada di tahap '{$sekarang}' yang tidak bisa dimajukan.");

            return self::FAILURE;
        }
        if ($iTujuan <= $iSekarang) {
            $this->error("Pekerjaan sudah di tahap '{$sekarang}'; tidak bisa mundur ke '{$tahap}'.");

            return self::FAILURE;
        }

        /*
         * Dari 'penawaran' langkah berikutnya 'dikerjakan', dan itu milik
         * pemesan. Jadi lompatan penawaran -> selesai diizinkan HANYA kalau
         * penawarannya memang sudah disetujui — yang berarti tahapnya sudah
         * 'dikerjakan' dan bukan lompatan lagi.
         */
        if ($iTujuan > $iSekarang + 1) {
            $berikut = $urutan[$iSekarang + 1];
            $this->error("Dari '{$sekarang}' tahap berikutnya adalah '{$berikut}', bukan '{$tahap}'.");

            return self::FAILURE;
        }

        $d = $this->terapkan($d, $tahap, $task);

        $task->update([
            'detail_layanan' => $d,
            'fulfillment_status' => $tahap === 'selesai' ? 'selesai' : 'diproses',
            'completed_at' => $tahap === 'selesai' ? now() : $task->completed_at,
        ]);

        $this->info("{$task->nomor_invoice}: {$sekarang} -> {$tahap}");
        if (! empty($d['tukang'])) {
            $t = $d['tukang'];
            $this->line("  Tukang : {$t['nama']} · {$t['keahlian']} · {$t['tahun']} tahun");
        }
        if ($tahap === 'penawaran') {
            $this->line('  RAB    : Rp'.number_format((int) $d['penawaran']['total'], 0, ',', '.'));
        }
        $this->line('  Buka   : /tasks/tukang/'.$task->nomor_invoice);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function terapkan(array $d, string $tahap, Task $task): array
    {
        $d['tahap'] = $tahap;

        if ($tahap === 'menuju') {
            $d['tukang'] = $this->pilihMitra($d);
        }

        if ($tahap === 'tiba' && ! empty($d['tukang'])) {
            $d['tukang'] = [...$d['tukang'], 'tiba_menit' => 0];
        }

        if ($tahap === 'penawaran') {
            $d['penawaran'] = $this->susunPenawaran($d);
        }

        if ($tahap === 'selesai') {
            $d['selesai_pada'] = now()->toIso8601String();
            $d['garansi_sampai'] = now()->addDays(TukangTarif::GARANSI_HARI)->toDateString();
            $task->payment()?->update(['status' => 'paid']);
        }

        return $d;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function pilihMitra(array $d): array
    {
        $kategori = $d['kategori'] ?? 'lainnya';
        $cocok = array_values(array_filter(
            self::MITRA,
            fn ($m) => in_array($kategori, $m['keahlian'], true),
        )) ?: self::MITRA;

        $pilih = $cocok[array_rand($cocok)];

        return [
            'nama' => $this->option('nama') ?: $pilih['nama'],
            'keahlian' => TukangTarif::KATEGORI[$kategori]['nama'] ?? 'Umum',
            'tahun' => $pilih['tahun'],
            'bintang' => round(4.7 + random_int(0, 25) / 100, 2),
            'pekerjaan' => random_int(60, 900),
            // Nomor tukang TIDAK ditaruh apa adanya: yang dipakai layar adalah
            // panggilan lewat aplikasi, bukan nomor pribadinya.
            'telepon_tersamar' => true,
            'tiba_menit' => random_int(15, 60),
        ];
    }

    /**
     * Susun RAB.
     *
     * Angkanya diturunkan dari rentang kategori, bukan diarang bebas: penawaran
     * yang jatuh di luar rentang yang sudah ditunjukkan ke pemesan membuat
     * rentang itu jadi omong kosong.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function susunPenawaran(array $d): array
    {
        $kategori = $d['kategori'] ?? 'lainnya';
        $k = TukangTarif::KATEGORI[$kategori] ?? TukangTarif::KATEGORI['lainnya'];

        $total = $this->option('total') !== null
            ? max(0, (int) $this->option('total'))
            : random_int((int) $k['perbaikan_mulai'], (int) $k['perbaikan_sampai']);

        // Material hanya masuk RAB kalau tukang yang membelikan; kalau pemesan
        // sudah menyiapkannya, menagihkan material berarti menagih dua kali.
        $materialDitagih = ($d['penyediaan_material'] ?? 'tukang') === 'tukang';
        $material = $materialDitagih ? (int) round($total * 0.35) : 0;
        $jasa = $total - $material;

        $baris = [[
            'nama' => 'Jasa pengerjaan — '.($d['sub_kategori'] ?? $k['nama']),
            'kategori' => 'layanan',
            'satuan' => 'paket',
            'nilai' => $jasa,
        ]];

        if ($material > 0) {
            $baris[] = [
                'nama' => 'Material & suku cadang',
                'kategori' => 'material',
                'satuan' => 'paket',
                'nilai' => $material,
            ];
        }

        return [
            'baris' => $baris,
            'subtotal' => $total,
            'potongan' => 0,
            'total' => $total,
            'material_ditanggung' => $materialDitagih ? 'tukang' : 'pelanggan',
            'garansi_hari' => TukangTarif::GARANSI_HARI,
            'diterbitkan_pada' => now()->toIso8601String(),
            'berlaku_sampai' => now()->addDays(TukangTarif::PENAWARAN_BERLAKU_HARI)->toDateString(),
            'keputusan' => null,
        ];
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
