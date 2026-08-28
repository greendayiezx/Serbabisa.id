<?php

namespace App\Services;

use App\Models\Penawaran;
use App\Models\PenawaranPaket;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun dokumen penawaran dari sebuah permintaan.
 *
 * Tiga pilihan paket dibuat agar pelanggan membandingkan CAKUPAN, bukan sekadar
 * mencari angka terkecil. Ketiganya dihitung dari data kantor lewat KantorTarif
 * — bukan angka yang diketik manual, supaya harga penawaran dan harga di
 * aplikasi tidak pernah berselisih diam-diam.
 */
class PenyusunPenawaran
{
    /** Berapa lama sebuah penawaran berlaku sejak dikirim. */
    public const HARI_BERLAKU = 14;

    public function __construct(private readonly KantorTarif $tarif) {}

    /**
     * Nomor penawaran berurutan: OFF-000124.
     *
     * Diambil dari nomor terakhir, bukan dari jumlah baris — baris yang terhapus
     * akan membuat hitungan mundur dan menabrak nomor yang sudah dipakai.
     */
    public function nomorBerikutnya(): string
    {
        $terakhir = Penawaran::orderByDesc('id')->value('nomor');
        $angka = $terakhir ? (int) substr($terakhir, 4) : 0;

        return 'OFF-'.str_pad((string) ($angka + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Susun penawaran dari task permintaan.
     *
     * @param  array<string,mixed>  $spek  jenis_kantor, workstation, dst.
     */
    public function dariTask(Task $task, array $spek): Penawaran
    {
        $jenis = $spek['jenis_kantor'] ?? 'sedang';
        $frekuensi = $spek['frekuensi'] ?? '2x-minggu';

        return DB::transaction(function () use ($task, $spek, $jenis, $frekuensi) {
            $penawaran = Penawaran::create([
                'nomor' => $this->nomorBerikutnya(),
                'task_id' => $task->id,
                'customer_id' => $task->customer_id,
                'nama_perusahaan' => $spek['nama_perusahaan'] ?? $task->nama_penerima ?? 'Perusahaan',
                'nama_pic' => $spek['nama_pic'] ?? null,
                'telepon_pic' => $spek['telepon_pic'] ?? null,
                'alamat' => $task->lokasi_alamat,
                'ringkasan' => $this->ringkasan($spek, $frekuensi),
                'status' => 'dikirim',
                'berlaku_sampai' => now()->addDays(self::HARI_BERLAKU)->toDateString(),
                'scope' => $this->scope($spek),
                'biaya_tambahan' => $this->biayaTambahan(),
                'pengecualian' => $this->pengecualian(),
            ]);

            foreach ($this->paket($jenis, $frekuensi, $spek) as $p) {
                PenawaranPaket::create(['penawaran_id' => $penawaran->id, ...$p]);
            }

            return $penawaran->load('paket');
        });
    }

    /** @return list<array<string,mixed>> */
    private function paket(string $jenis, string $frekuensi, array $spek): array
    {
        $kunjungan = KantorTarif::FREKUENSI[$frekuensi]['kunjungan_per_bulan']
            ?? KantorTarif::FREKUENSI['2x-minggu']['kunjungan_per_bulan'];

        $tingkat = [
            ['kode' => 'basic', 'nama' => 'Essential', 'disarankan' => false, 'ringkas' => 'Cleaning dasar area kerja, toilet, dan pantry.', 'isi' => [
                'Penyapuan & pengepelan area kerja',
                'Pembersihan toilet & wastafel',
                'Pembersihan pantry',
                'Pengosongan tempat sampah',
            ]],
            ['kode' => 'professional', 'nama' => 'Professional', 'disarankan' => true, 'ringkas' => 'Essential + supervisor + laporan berkala.', 'isi' => [
                'Semua cakupan Essential',
                'Pembersihan ruang meeting',
                'Checklist digital tiap kunjungan',
                'Supervisor berkala',
                'Laporan kebersihan bulanan',
            ]],
            ['kode' => 'executive', 'nama' => 'Executive', 'disarankan' => false, 'ringkas' => 'Professional + cleaner tetap + deep cleaning bulanan.', 'isi' => [
                'Semua cakupan Professional',
                'Cleaner tetap yang sama tiap kunjungan',
                'Deep cleaning menyeluruh bulanan',
                'Disinfeksi ruangan',
                'Garansi re-clean',
            ]],
        ];

        return array_map(function (array $t) use ($jenis, $frekuensi, $kunjungan, $spek) {
            $r = $this->tarif->hitung(
                $jenis,
                $t['kode'],
                (int) ($spek['workstation'] ?? 0),
                (int) ($spek['ruang_meeting'] ?? 0),
                (int) ($spek['toilet'] ?? 0),
                (int) ($spek['pantry'] ?? 0),
                // Add-on tidak dibundel ke paket: pelanggan memilihnya terpisah
                // supaya perbandingan antar paket tetap apel-ke-apel.
                [],
                $frekuensi,
                // Luas sebenarnya, kalau pelanggan menyebutkannya di form penawaran.
                isset($spek['luas_m2']) ? (int) $spek['luas_m2'] : null,
            );

            return [
                'kode' => $t['kode'],
                'nama' => $t['nama'],
                'ringkas' => $t['ringkas'],
                'isi' => $t['isi'],
                'harga_per_kunjungan' => $r['total_per_kunjungan'],
                'kunjungan_per_bulan' => $kunjungan,
                'harga_bulanan' => $r['total_per_kunjungan'] * $kunjungan,
                'disarankan' => $t['disarankan'],
            ];
        }, $tingkat);
    }

    private function ringkasan(array $spek, string $frekuensi): string
    {
        $jenis = KantorTarif::JENIS[$spek['jenis_kantor'] ?? 'sedang'] ?? KantorTarif::JENIS['sedang'];
        $label = KantorTarif::FREKUENSI[$frekuensi]['label'] ?? $frekuensi;
        $luas = $spek['luas_m2'] ?? $jenis['luas'];

        $bagian = array_filter([
            "kantor seluas ±{$luas} m²",
            ! empty($spek['jumlah_lantai']) ? "{$spek['jumlah_lantai']} lantai" : null,
            ! empty($spek['toilet']) ? "{$spek['toilet']} toilet" : null,
            ! empty($spek['pantry']) ? "{$spek['pantry']} pantry" : null,
            ! empty($spek['ruang_meeting']) ? "{$spek['ruang_meeting']} ruang meeting" : null,
        ]);

        return 'Pembersihan rutin '.implode(', ', $bagian).", dengan jadwal layanan {$label}.";
    }

    /** @return list<array<string,string>> */
    private function scope(array $spek): array
    {
        $baris = [
            ['area' => 'Ruang kerja', 'pekerjaan' => 'Vacuum, lap meja, buang sampah', 'frekuensi' => 'Setiap kunjungan'],
            ['area' => 'Toilet', 'pekerjaan' => 'Sanitasi toilet, wastafel, cermin, lantai', 'frekuensi' => 'Setiap kunjungan'],
        ];

        if (! empty($spek['pantry'])) {
            $baris[] = ['area' => 'Pantry', 'pekerjaan' => 'Lap meja, wastafel, lantai, sampah', 'frekuensi' => 'Setiap kunjungan'];
        }
        if (! empty($spek['ruang_meeting'])) {
            $baris[] = ['area' => 'Ruang meeting', 'pekerjaan' => 'Lap meja & kursi, vacuum, rapikan area', 'frekuensi' => 'Setiap kunjungan'];
        }

        $baris[] = ['area' => 'Kaca interior', 'pekerjaan' => 'Pembersihan kaca bagian dalam', 'frekuensi' => 'Mingguan'];
        $baris[] = ['area' => 'Deep cleaning', 'pekerjaan' => 'Pembersihan menyeluruh', 'frekuensi' => 'Bulanan'];

        return $baris;
    }

    /** @return list<string> */
    private function biayaTambahan(): array
    {
        return [
            'Tisu, sabun, dan plastik sampah disediakan pelanggan.',
            'Pembersihan kaca bagian luar gedung ditagih terpisah.',
            'Pemindahan meja & furnitur berat ditagih terpisah.',
            'Biaya parkir dan akses gedung mengikuti ketentuan pengelola.',
            'Pekerjaan di luar jam layanan dikenakan tarif lembur.',
        ];
    }

    /** @return list<string> */
    private function pengecualian(): array
    {
        return [
            'Pengangkutan puing dan sisa material renovasi.',
            'Pembersihan area berbahaya atau membutuhkan sertifikasi ketinggian.',
            'Perbaikan atau perawatan peralatan kantor.',
            'Harga belum termasuk pajak yang berlaku.',
        ];
    }
}
