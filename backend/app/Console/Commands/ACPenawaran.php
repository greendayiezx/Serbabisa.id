<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\PerbaikanTarif;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Menerbitkan penawaran pemasangan dari terminal.
 *
 * Sisi tim belum punya layar "susun penawaran", jadi selama pengembangan
 * inilah cara memunculkan halaman Setujui Penawaran di sisi pelanggan.
 *
 *   php artisan ac:penawaran REQ-260829-XXXXXX
 *   php artisan ac:penawaran --terakhir
 *   php artisan ac:penawaran --terakhir --pipa=5 --berlaku=3
 *
 * Angkanya contoh yang masuk akal, bukan tarif resmi: tarif pemasangan
 * memang belum ada di katalog karena harganya ditentukan per lokasi.
 */
class ACPenawaran extends Command
{
    protected $signature = 'ac:penawaran
        {nomor? : Nomor permintaan REQ-, boleh potongan belakangnya}
        {--terakhir : Ambil permintaan pemasangan terbaru}
        {--pipa=3 : Panjang pipa yang ditawarkan, dalam meter}
        {--berlaku=7 : Masa berlaku penawaran, dalam hari}
        {--deposit=200000 : Deposit yang diminta setelah disetujui}';

    protected $description = 'Terbitkan penawaran pemasangan AC pada permintaan REQ- (alat bantu pengembangan)';

    public function handle(): int
    {
        $task = $this->option('terakhir')
            ? Task::where('judul', 'like', 'Permintaan Penawaran — Pasang%')->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Permintaan tidak ditemukan.');

            return self::FAILURE;
        }

        $detail = $task->detail_layanan ?? [];
        if (($detail['layanan'] ?? null) !== 'pasang-ac') {
            $this->error("Pesanan {$task->nomor_invoice} bukan permintaan pemasangan AC.");

            return self::FAILURE;
        }

        $pipa = max(1, (int) $this->option('pipa'));
        $hargaPipa = $pipa * 70_000;

        $baris = [
            ['nama' => 'Jasa pemasangan', 'kategori' => 'layanan', 'satuan' => 'paket', 'nilai' => 350_000],
            ['nama' => "Pipa AC {$pipa} meter", 'kategori' => 'material', 'satuan' => 'meter', 'nilai' => $hargaPipa],
            ['nama' => 'Bracket outdoor', 'kategori' => 'material', 'satuan' => 'set', 'nilai' => 80_000],
            ['nama' => 'Kabel & drain hose', 'kategori' => 'material', 'satuan' => 'paket', 'nilai' => 75_000],
            ['nama' => 'Biaya kunjungan', 'kategori' => 'layanan', 'satuan' => 'kunjungan', 'nilai' => 25_000],
        ];

        $subtotal = array_sum(array_column($baris, 'nilai'));
        $potongan = 50_000;

        $penawaran = [
            'nomor' => 'Q-AC-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
            'terbit_pada' => now()->toIso8601String(),
            'berlaku_sampai' => now()->addDays(max(1, (int) $this->option('berlaku')))->toDateString(),
            'layanan' => 'Pasang AC Split '.($detail['kapasitas'] ?? '1').' PK',
            'durasi' => '1 hari kerja',

            'termasuk' => [
                'Pemasangan unit indoor & outdoor',
                "Instalasi pipa maksimal {$pipa} meter",
                'Instalasi kabel kontrol',
                'Pemasangan bracket outdoor',
                'Tes fungsi & pendinginan',
                'Pembersihan area kerja',
            ],
            /*
             * Pengecualian ditulis sejelas lingkupnya. Yang tidak disebut di
             * sini akan dianggap termasuk oleh pelanggan — dan selisih itulah
             * yang berubah jadi perselisihan di lokasi.
             */
            'tidak_termasuk' => [
                "Penambahan pipa di atas {$pipa} meter",
                'Bobok tembok permanen',
                'Penarikan jalur listrik baru',
                'Pekerjaan di ketinggian yang butuh perancah',
                'Material di luar paket ini',
            ],

            'baris' => $baris,
            'subtotal' => $subtotal,
            'potongan' => $potongan,
            'nama_potongan' => 'Promo pemasangan',
            'total' => $subtotal - $potongan,
            'deposit' => max(0, (int) $this->option('deposit')),

            'jadwal' => $this->slotJadwal(),
            'catatan' =>
                'Harga berdasarkan informasi dan foto yang dikirim. Kalau kondisi lokasi berbeda, '.
                'pekerjaan tambahan harus disetujui lebih dulu sebelum dikerjakan.',

            'keputusan' => null,
        ];

        $task->update(['detail_layanan' => [...$detail, 'penawaran' => $penawaran]]);

        $this->info("Penawaran {$penawaran['nomor']} terbit untuk {$task->nomor_invoice}.");
        $this->line("  Total    : Rp".number_format($penawaran['total'], 0, ',', '.'));
        $this->line("  Berlaku  : sampai {$penawaran['berlaku_sampai']}");
        $this->line('  Buka     : /tasks/servis-ac/penawaran/'.$task->nomor_invoice);

        return self::SUCCESS;
    }

    /**
     * Tiga slot terdekat, melewati hari ini.
     *
     * @return list<array<string, string>>
     */
    private function slotJadwal(): array
    {
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        $slot = [];
        foreach ([['1', '09:00-12:00'], ['1', '13:00-16:00'], ['2', '09:00-12:00']] as $i => [$tambah, $jam]) {
            $t = Carbon::now()->addDays((int) $tambah);
            $slot[] = [
                'id' => 'slot-'.($i + 1),
                'tanggal' => $t->toDateString(),
                'label' => $hari[(int) $t->format('w')].', '.$t->day.' '.$bulan[(int) $t->month],
                'jam' => $jam,
            ];
        }

        return $slot;
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
