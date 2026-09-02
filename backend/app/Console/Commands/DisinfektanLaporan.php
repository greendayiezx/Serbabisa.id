<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\DisinfektanTarif;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menulis laporan pekerjaan disinfeksi dari terminal.
 *
 * Sisi petugas belum punya layar "tutup pekerjaan", jadi selama pengembangan
 * inilah cara memunculkan halaman Laporan di sisi pelanggan.
 *
 *   php artisan bersih:laporan SBB-260831-XXXXXXX
 *   php artisan bersih:laporan --terakhir
 *   php artisan bersih:laporan --terakhir --produk=alkohol --sesudah=foto/a.jpg
 *
 * Perhatikan daftar PRODUK di bawah: waktu kontaknya BERBEDA-BEDA, dan itulah
 * alasan aplikasi ini tidak pernah memasang satu angka waktu kontak di katalog.
 * Angka yang benar untuk satu produk adalah angka yang salah untuk produk lain,
 * dan yang menentukan adalah label produk yang benar-benar dipakai di lokasi.
 */
class DisinfektanLaporan extends Command
{
    protected $signature = 'bersih:laporan
        {nomor? : Nomor pesanan, boleh potongan belakangnya}
        {--terakhir : Ambil pesanan disinfektan terbaru}
        {--produk=benzalkonium : Produk yang dipakai; lihat daftar di kelas ini}
        {--ventilasi=30 : Lama ventilasi setelah aplikasi, dalam menit}
        {--petugas=Petugas BisaBersih : Nama petugas yang mengerjakan}
        {--registrasi= : Nomor izin edar, disalin dari kemasan di lokasi}
        {--sesudah=* : Berkas foto "sesudah" dari komputer ini}
        {--sebelum=* : Berkas foto "sebelum"; kosong berarti pakai foto pesanan}';

    protected $description = 'Tulis laporan pekerjaan disinfeksi pada sebuah pesanan (alat bantu pengembangan)';

    /**
     * Katalog produk beserta waktu kontak MASING-MASING.
     *
     * Rentangnya sengaja jauh — 30 detik sampai 10 menit. Satu angka wakil
     * untuk semuanya berarti tiga dari empat produk dikerjakan dengan prosedur
     * yang salah.
     *
     * @var array<string, array<string, string>>
     */
    private const PRODUK = [
        'benzalkonium' => [
            'nama' => 'Disinfektan permukaan berbahan benzalkonium chloride',
            'bahan_aktif' => 'Benzalkonium chloride',
            'konsentrasi' => '0,2%',
            'waktu_kontak' => '10 menit',
            'catatan' => 'Tidak dibilas pada permukaan yang tidak bersentuhan dengan makanan.',
        ],
        'hidrogen-peroksida' => [
            'nama' => 'Disinfektan permukaan berbahan hidrogen peroksida',
            'bahan_aktif' => 'Hidrogen peroksida',
            'konsentrasi' => '0,5%',
            'waktu_kontak' => '1 menit',
            'catatan' => 'Dihindari pada logam yang mudah berkarat.',
        ],
        'natrium-hipoklorit' => [
            'nama' => 'Larutan natrium hipoklorit',
            'bahan_aktif' => 'Natrium hipoklorit',
            'konsentrasi' => '0,1%',
            'waktu_kontak' => '5 menit',
            'catatan' => 'Tidak dipakai pada logam dan kain berwarna; area diberi ventilasi.',
        ],
        'alkohol' => [
            'nama' => 'Etanol 70%',
            'bahan_aktif' => 'Etanol',
            'konsentrasi' => '70%',
            'waktu_kontak' => '30 detik',
            'catatan' => 'Untuk permukaan kecil dan elektronik; mudah terbakar, dijauhkan dari api.',
        ],
    ];

    public function handle(): int
    {
        $kunci = (string) $this->option('produk');
        if (! isset(self::PRODUK[$kunci])) {
            $this->error('Produk tidak dikenal. Pilihan: '.implode(', ', array_keys(self::PRODUK)));

            return self::FAILURE;
        }

        $task = $this->option('terakhir')
            ? Task::where('detail_layanan->layanan', 'disinfektan')
                ->whereNull('detail_layanan->permintaan_penawaran')
                ->latest('id')->first()
            : $this->cari((string) $this->argument('nomor'));

        if (! $task) {
            $this->error('Pesanan tidak ditemukan.');

            return self::FAILURE;
        }

        $detail = $task->detail_layanan ?? [];
        if (($detail['layanan'] ?? null) !== 'disinfektan' || ($detail['permintaan_penawaran'] ?? false)) {
            $this->error("Pesanan {$task->nomor_invoice} bukan pesanan disinfektan.");

            return self::FAILURE;
        }

        $produk = self::PRODUK[$kunci];
        $ventilasi = max(1, (int) $this->option('ventilasi'));
        $golongan = DisinfektanTarif::golongan($detail['properti'] ?? 'rumah');

        /*
         * Foto pesanan adalah foto "sebelum" yang sesungguhnya — diambil
         * pelanggan sebelum petugas datang. Memakainya lagi di sini bukan
         * mengarang: itu memang berkas yang sama, dan label barunya menyebut
         * apa adanya.
         */
        $sebelum = $this->option('sebelum')
            ? $this->salin($task->id, 'sebelum', $this->option('sebelum'))
            : array_map(
                fn ($f) => ['label' => 'Sebelum — '.$f['label'], 'jalur' => $f['jalur']],
                $detail['foto'] ?? [],
            );

        $laporan = [
            'nomor' => 'LAP-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
            'selesai_pada' => now()->toIso8601String(),
            'petugas' => (string) $this->option('petugas'),

            'produk' => [
                ...$produk,
                // Kosong sampai betul-betul disalin dari kemasan. Nomor izin
                // edar karangan lebih buruk daripada kolom yang kosong.
                'registrasi' => $this->option('registrasi') ?: null,
            ],

            'metode' => 'Permukaan dibersihkan lebih dulu, lalu disinfektan diaplikasikan dengan lap '.
                'dan disemprot pada titik sentuh. Tanpa pengasapan.',
            'area_dikerjakan' => DisinfektanTarif::AREA[$golongan],
            'ventilasi_menit' => $ventilasi,
            'aman_dimasuki_pada' => now()->addMinutes($ventilasi)->toIso8601String(),

            'catatan' => 'Permukaan elektronik dilap, tidak disemprot langsung.',

            'sebelum' => $sebelum,
            'sesudah' => $this->salin($task->id, 'sesudah', $this->option('sesudah')),
        ];

        $task->update([
            'detail_layanan' => [
                ...$detail,
                // Baru sekarang keduanya terisi: yang tahu produk apa yang
                // dipakai adalah petugas di lokasi, bukan formulir pemesanan.
                'produk' => $produk['nama'],
                'waktu_kontak' => $produk['waktu_kontak'],
                'laporan' => $laporan,
            ],
            'fulfillment_status' => 'selesai',
        ]);

        $this->info("Laporan {$laporan['nomor']} tercatat untuk {$task->nomor_invoice}.");
        $this->line("  Produk       : {$produk['nama']}");
        $this->line("  Waktu kontak : {$produk['waktu_kontak']} (dari label produk ini)");
        $this->line("  Ventilasi    : {$ventilasi} menit");
        $this->line('  Buka         : /tasks/bersih/disinfektan/laporan/'.$task->nomor_invoice);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $berkas
     * @return list<array{label:string, jalur:string}>
     */
    private function salin(int $taskId, string $tahap, array $berkas): array
    {
        $hasil = [];
        foreach (array_values($berkas) as $i => $sumber) {
            if (! is_file($sumber)) {
                $this->warn("Foto dilewati, berkasnya tidak ada: {$sumber}");

                continue;
            }

            $ext = strtolower(pathinfo($sumber, PATHINFO_EXTENSION)) ?: 'jpg';
            $jalur = "bersih/laporan/{$taskId}-{$tahap}-{$i}.{$ext}";
            Storage::disk('public')->put($jalur, (string) file_get_contents($sumber));

            $hasil[] = ['label' => ucfirst($tahap).' — foto '.($i + 1), 'jalur' => $jalur];
        }

        return $hasil;
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
