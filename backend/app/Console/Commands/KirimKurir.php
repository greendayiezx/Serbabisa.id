<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\RuteJalan;
use Illuminate\Console\Command;

/**
 * Menjalankan sisi kurir dari terminal.
 *
 * Sepola dengan jemput:pengemudi, dan alasannya sama: aplikasi kurir belum ada,
 * jadi selama pengembangan inilah yang memajukan tahap kiriman di layar
 * pengirim.
 *
 *   php artisan kirim:kurir --terakhir                  # cari -> kurir dapat
 *   php artisan kirim:kurir --terakhir --tahap=diantar
 *   php artisan kirim:kurir --terakhir --tahap=selesai
 *   php artisan kirim:kurir --terakhir --maju=0.3       # kurir mendekat
 *
 * Urutannya dijaga: tahap tidak bisa mundur dan tidak bisa melompat. Layar
 * pengirim menggantungkan seluruh isinya pada tahap ini — kiriman yang tiba-tiba
 * "selesai" tanpa pernah ada kurir akan tampil sebagai tagihan tanpa asal-usul.
 */
class KirimKurir extends Command
{
    protected $signature = 'kirim:kurir
        {nomor? : Nomor kiriman, boleh potongan belakangnya}
        {--terakhir : Ambil kiriman BisaKirim terbaru}
        {--tahap=menjemput : menjemput, diantar, atau selesai}
        {--nama= : Nama kurir}
        {--maju= : Majukan kurir sekian bagian rute (0-1), tanpa ganti tahap}';

    protected $description = 'Majukan tahap kiriman BisaKirim (alat bantu pengembangan)';

    /** @var list<string> */
    private const URUTAN = ['mencari', 'menjemput', 'diantar', 'selesai'];

    /**
     * Armada dipisah menurut kendaraan yang DIPESAN.
     *
     * Paket yang dipesan lewat motor harus dijemput motor: pengirim menunggu di
     * pinggir jalan sambil mencocokkan pelat, dan kendaraan yang salah jenis
     * membuatnya membiarkan kurir yang benar lewat begitu saja.
     *
     * @var array<string, list<array<string, string>>>
     */
    private const ARMADA = [
        'motor' => [
            ['nama' => 'Dedi Kurniawan', 'kendaraan' => 'Honda Beat', 'plat' => 'B 2211 KRM', 'warna' => 'Merah'],
            ['nama' => 'Eko Prasetyo', 'kendaraan' => 'Yamaha Mio', 'plat' => 'B 4455 PKT', 'warna' => 'Biru'],
        ],
        'mobil' => [
            ['nama' => 'Hendra Wijaya', 'kendaraan' => 'Daihatsu Gran Max', 'plat' => 'B 7788 BOX', 'warna' => 'Putih'],
            ['nama' => 'Lina Marlina', 'kendaraan' => 'Suzuki Carry', 'plat' => 'B 9911 CRY', 'warna' => 'Silver'],
        ],
    ];

    public function handle(): int
    {
        $tahap = (string) $this->option('tahap');
        if (! in_array($tahap, self::URUTAN, true) || $tahap === 'mencari') {
            $this->error('Tahap harus salah satu dari: menjemput, diantar, selesai.');

            return self::FAILURE;
        }

        $task = $this->option('terakhir')
            ? Task::where('detail_layanan->layanan', 'kirim')->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Kiriman tidak ditemukan.');

            return self::FAILURE;
        }

        $d = $task->detail_layanan ?? [];
        if (($d['layanan'] ?? null) !== 'kirim') {
            $this->error("Pesanan {$task->nomor_invoice} bukan kiriman BisaKirim.");

            return self::FAILURE;
        }

        // Memajukan posisi tidak mengubah tahap, jadi jalurnya berhenti di sini.
        if ($this->option('maju') !== null) {
            return $this->majukanPosisi($task, $d, (float) $this->option('maju'));
        }

        $sekarang = $d['tahap'] ?? 'mencari';
        $iSekarang = array_search($sekarang, self::URUTAN, true);
        $iTujuan = array_search($tahap, self::URUTAN, true);

        if ($iTujuan <= $iSekarang) {
            $this->error("Kiriman sudah di tahap '{$sekarang}'; tidak bisa mundur ke '{$tahap}'.");

            return self::FAILURE;
        }
        if ($iTujuan > $iSekarang + 1) {
            $this->error("Dari '{$sekarang}' tahap berikutnya adalah '".self::URUTAN[$iSekarang + 1]."', bukan '{$tahap}'.");

            return self::FAILURE;
        }

        $kurir = $d['kurir'] ?? null;
        if ($tahap === 'menjemput') {
            $jenis = ($d['kendaraan'] ?? 'motor') === 'mobil' ? 'mobil' : 'motor';
            $armada = self::ARMADA[$jenis];
            $pilih = $armada[array_rand($armada)];

            $kurir = [
                'nama' => $this->option('nama') ?: $pilih['nama'],
                'kendaraan' => $pilih['kendaraan'],
                'plat' => $pilih['plat'],
                'warna' => $pilih['warna'],
                'bintang' => 4.8,
                'kiriman' => random_int(300, 4000),
                // Nomor kurir TIDAK ditaruh apa adanya: yang dipakai layar adalah
                // panggilan lewat aplikasi, bukan nomor pribadi.
                'telepon_tersamar' => true,
                'tiba_menit' => random_int(3, 9),
            ];
        }

        if ($kurir) {
            $kurir = [...$kurir, ...$this->posisi($d, $tahap)];
        }

        $task->update([
            'detail_layanan' => [...$d, 'tahap' => $tahap, 'kurir' => $kurir],
            'fulfillment_status' => $tahap === 'selesai' ? 'selesai' : 'diproses',
            'completed_at' => $tahap === 'selesai' ? now() : $task->completed_at,
        ]);

        $this->info("{$task->nomor_invoice}: {$sekarang} -> {$tahap}");
        if ($kurir) {
            $this->line("  Kurir : {$kurir['nama']} · {$kurir['kendaraan']} {$kurir['plat']}");
        }
        $this->line('  Buka  : /tasks/kirim/'.$task->nomor_invoice);

        return self::SUCCESS;
    }

    /**
     * Majukan kurir menyusuri rutenya sendiri, tanpa mengubah tahap.
     *
     * Rutenya DIPOTONG, bukan dihitung ulang: sisa jalan yang belum ditempuh
     * memang bagian belakang rute yang sama, jadi jaraknya berkurang menyusuri
     * jalan yang sama persis — bukan melompat ke rute lain.
     *
     * @param  array<string, mixed>  $d
     */
    private function majukanPosisi(Task $task, array $d, float $bagian): int
    {
        if (($d['tahap'] ?? null) !== 'menjemput') {
            $this->error('Memajukan posisi hanya berlaku saat tahap "menjemput".');

            return self::FAILURE;
        }

        $k = $d['kurir'] ?? null;
        $rute = $k['rute'] ?? null;
        if (! is_array($rute) || count($rute) < 2) {
            $this->error('Rute menjemput belum ada. Buka layar kirimannya sekali dulu supaya rutenya dilengkapi.');

            return self::FAILURE;
        }

        $bagian = max(0.01, min(1.0, $bagian));
        $lompat = max(1, (int) round((count($rute) - 1) * $bagian));
        $sisa = array_slice($rute, $lompat);

        if (count($sisa) < 2) {
            $ambil = $d['ambil'];
            $k = [...$k, 'lat' => (float) $ambil['lat'], 'lng' => (float) $ambil['lng'], 'rute' => null, 'jarak_km' => 0.0, 'tiba_menit' => 0];
            $task->update(['detail_layanan' => [...$d, 'kurir' => $k]]);
            $this->info("{$task->nomor_invoice}: kurir sampai di titik ambil.");

            return self::SUCCESS;
        }

        $km = $this->panjangKm($sisa);
        $k = [
            ...$k,
            'lat' => round((float) $sisa[0][0], 6),
            'lng' => round((float) $sisa[0][1], 6),
            'rute' => $sisa,
            'jarak_km' => round($km, 2),
            'tiba_menit' => max(1, (int) ceil($km / 20 * 60)),
        ];
        $task->update(['detail_layanan' => [...$d, 'kurir' => $k]]);

        $this->info("{$task->nomor_invoice}: sisa {$k['jarak_km']} km, {$k['tiba_menit']} menit lagi.");

        return self::SUCCESS;
    }

    /**
     * Di mana kurirnya sekarang, dan menuju ke mana.
     *
     * - menjemput : masih di jalan menuju titik ambil
     * - diantar   : bergerak di sepanjang rute menuju penerima
     * - selesai   : di titik antar
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function posisi(array $d, string $tahap): array
    {
        $ambil = $d['ambil'] ?? null;
        $antar = $d['antar'] ?? null;
        if (! $ambil || ! $antar) {
            return [];
        }

        $titik = match ($tahap) {
            'menjemput' => $this->geser($ambil, random_int(10, 30) / 10, random_int(0, 359)),
            'diantar' => $this->diRute($d, 0.35) ?? ['lat' => $ambil['lat'], 'lng' => $ambil['lng']],
            default => ['lat' => $antar['lat'], 'lng' => $antar['lng']],
        };

        $menuju = $tahap === 'menjemput' ? 'ambil' : 'antar';
        $sasaran = $menuju === 'ambil' ? $ambil : $antar;

        /*
         * Rute kurir menuju titik ambil dihitung lewat layanan yang sama dengan
         * rute kiriman, dan jaraknya diambil dari situ. Kalau ditarik lurus di
         * sisi klien sementara jaraknya dihitung dengan cara lain, yang tampil
         * adalah dua angka yang tidak pernah sepakat.
         */
        $rute = $tahap === 'menjemput'
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

    /**
     * @param  list<array{0:float,1:float}>  $rute
     */
    private function panjangKm(array $rute): float
    {
        $km = 0.0;
        for ($i = 1, $n = count($rute); $i < $n; $i++) {
            $km += $this->jarakKm(
                (float) $rute[$i - 1][0],
                (float) $rute[$i - 1][1],
                (float) $rute[$i][0],
                (float) $rute[$i][1],
            );
        }

        return $km;
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
