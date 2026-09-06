<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\RuteJalan;
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

    /**
     * Armada dipisah menurut jenisnya.
     *
     * Pemesan motor harus dijemput motor. Sebelumnya kendaraannya diundi dari
     * satu daftar campur, jadi order motor bisa dijawab Toyota Avanza — dan
     * pelat yang dicocokkan penumpang di pinggir jalan menunjuk kendaraan yang
     * tidak pernah ia pesan.
     *
     * @var array<string, list<array<string, string>>>
     */
    private const ARMADA = [
        'motor' => [
            ['nama' => 'Budi Santoso', 'kendaraan' => 'Honda Vario', 'plat' => 'B 1234 XYZ', 'warna' => 'Hitam'],
            ['nama' => 'Agus Priyanto', 'kendaraan' => 'Yamaha NMAX', 'plat' => 'B 9012 DEF', 'warna' => 'Putih'],
        ],
        'mobil' => [
            ['nama' => 'Sri Wahyuni', 'kendaraan' => 'Toyota Avanza', 'plat' => 'B 5678 ABC', 'warna' => 'Silver'],
            ['nama' => 'Rina Kusuma', 'kendaraan' => 'Daihatsu Xenia', 'plat' => 'B 3344 KLM', 'warna' => 'Hitam Metalik'],
        ],
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
            // Jenis kendaraan mengikuti kelas yang DIPESAN, bukan diundi.
            $jenis = str_starts_with((string) ($d['kelas'] ?? 'motor'), 'motor') ? 'motor' : 'mobil';
            $armada = self::ARMADA[$jenis];
            $pilih = $armada[array_rand($armada)];
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

        /*
         * Posisi pengemudi ditulis DI SINI, bukan ditebak layar.
         *
         * Layar penantian menggambar mobil di peta dan menyebut "x,xx km lagi".
         * Kalau angkanya dikarang di sisi penumpang, yang tampil adalah jarak
         * yang tidak pernah diketahui siapa pun — dan orang menakar kapan harus
         * turun ke lobi berdasarkan angka itu. Jadi posisinya datang dari sini,
         * dan kalau tidak ada, layar tidak menyebut jarak sama sekali.
         *
         * Nilainya memang buatan: aplikasi pengemudi belum ada, dan perintah ini
         * yang menggantikannya selama pengembangan.
         */
        if ($pengemudi) {
            $pengemudi = [...$pengemudi, ...$this->posisi($d, $tahap)];
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

    /**
     * Di mana pengemudinya sekarang, dan menuju ke mana.
     *
     * - dijemput : masih di jalan menuju titik jemput, ditaruh di sekitarnya
     * - tiba     : sudah sampai di titik jemput
     * - jalan    : bergerak di sepanjang rute menuju tujuan
     * - selesai  : di tujuan
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function posisi(array $d, string $tahap): array
    {
        $jemput = $d['jemput'] ?? null;
        $tujuan = $d['tujuan'] ?? null;
        if (! $jemput || ! $tujuan) {
            return [];
        }

        $titik = match ($tahap) {
            // Sekitar 1–3 km dari titik jemput, arah acak. 1 derajat lintang
            // kira-kira 111 km, jadi jaraknya dibagi angka itu.
            'dijemput' => $this->geser($jemput, random_int(10, 30) / 10, random_int(0, 359)),
            'tiba' => ['lat' => $jemput['lat'], 'lng' => $jemput['lng']],
            'jalan' => $this->diRute($d, 0.35) ?? ['lat' => $jemput['lat'], 'lng' => $jemput['lng']],
            default => ['lat' => $tujuan['lat'], 'lng' => $tujuan['lng']],
        };

        $menuju = in_array($tahap, ['dijemput', 'tiba'], true) ? 'jemput' : 'tujuan';
        $sasaran = $menuju === 'jemput' ? $jemput : $tujuan;

        /*
         * Rute pengemudi menuju titik jemput dihitung DI SINI, lewat layanan
         * yang sama dengan rute perjalanan.
         *
         * Layar penumpang menggambar garis dari kendaraan ke titik jemput. Kalau
         * garis itu ditarik lurus di sisi klien sementara jaraknya dihitung
         * dengan cara lain, yang tampil adalah dua angka yang tidak pernah
         * sepakat. Jaraknya pun diambil dari rute ini, bukan dari garis lurus —
         * itulah jarak yang benar-benar ditempuh.
         *
         * Kalau layanan rutenya tidak menjawab, `rute` null dan layar menarik
         * garis lurus PUTUS-PUTUS: bentuk yang berbeda supaya tidak terbaca
         * sebagai jalan yang sungguh dilewati.
         */
        $rute = $tahap === 'dijemput'
            ? app(RuteJalan::class)->cari($titik['lat'], $titik['lng'], $sasaran['lat'], $sasaran['lng'])
            : null;

        return [
            'lat' => round($titik['lat'], 6),
            'lng' => round($titik['lng'], 6),
            'menuju' => $menuju,
            'rute' => $rute['geometri'] ?? null,
            'jarak_km' => round(
                $rute['km'] ?? $this->jarakKm($titik['lat'], $titik['lng'], $sasaran['lat'], $sasaran['lng']),
                2,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $titik
     * @return array{lat: float, lng: float}
     */
    private function geser(array $titik, float $km, int $derajat): array
    {
        $rad = deg2rad($derajat);
        $lat = (float) $titik['lat'];
        $lng = (float) $titik['lng'];

        return [
            'lat' => $lat + ($km / 111) * cos($rad),
            // Garis bujur menyempit mengikuti lintang; tanpa cos() ini,
            // pergeseran ke timur di Jakarta jadi ~10% lebih jauh dari maunya.
            'lng' => $lng + ($km / (111 * cos(deg2rad($lat)))) * sin($rad),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{lat: float, lng: float}|null
     */
    private function diRute(array $d, float $bagian): ?array
    {
        $rute = $d['geometri'] ?? null;
        if (! is_array($rute) || count($rute) < 2) {
            return null;
        }

        $i = (int) floor((count($rute) - 1) * $bagian);

        return ['lat' => (float) $rute[$i][0], 'lng' => (float) $rute[$i][1]];
    }

    private function jarakKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
