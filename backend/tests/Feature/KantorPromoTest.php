<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\PromoKantor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Promo BisaBersih Kantor pada jalur "Pesan Sekarang".
 *
 * Kelas ini lahir dari cacat nyata: kode promo tidak pernah dikirim ke server,
 * jadi layar konfirmasi memperlihatkan total setelah potongan sementara yang
 * ditagih adalah harga penuh. Tes di sini menjaga tiga hal:
 *
 * 1. Potongan benar-benar mengurangi ANGKA YANG DITAGIH, bukan hanya tampilan.
 * 2. Syarat promo (minimum transaksi, pengguna baru) diperiksa di server.
 * 3. Katalog PHP dan katalog TypeScript tidak berjalan sendiri-sendiri.
 */
class KantorPromoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBersih', 'slug' => 'bisabersih', 'basis_harga' => 'durasi']);
    }

    /** Pesanan yang cukup besar untuk melewati minimum Rp500.000. */
    private function payload(array $ganti = []): array
    {
        return [
            'jenis_kantor' => 'sedang',
            'paket' => 'executive',
            'frekuensi' => 'sekali',
            'workstation' => 30,
            'ruang_meeting' => 3,
            'toilet' => 3,
            'pantry' => 2,
            'add_on' => [],
            'tanggal' => '2026-09-01',
            'waktu' => '09:00',
            'lokasi_alamat' => 'Gedung Uji',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_promo_mengurangi_angka_yang_ditagih(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload(['promo_kode' => 'BISABARU']));
        $res->assertCreated();

        $total = $res->json('rincian.total_per_kunjungan');
        $this->assertGreaterThanOrEqual(500_000, $total, 'Contoh pesanan harus melewati minimum promo.');

        $potongan = min((int) round($total * 0.15), 100_000);
        $res->assertJsonPath('rincian.potongan_promo', $potongan);
        $res->assertJsonPath('rincian.promo_kode', 'BISABARU');
        $res->assertJsonPath('rincian.total', $total - $potongan);

        // Yang paling penting: baris tagihannya, bukan hanya blok rincian.
        $task = Task::latest('id')->with('payment')->first();
        $this->assertSame((float) ($total - $potongan), (float) $task->harga);
        $this->assertSame((float) ($total - $potongan), (float) $task->payment->jumlah);
    }

    public function test_tanpa_kode_tagihan_utuh(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload());
        $res->assertCreated();

        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_kode', null);
        $res->assertJsonPath('rincian.promo_ditolak', null);
        $this->assertSame(
            (float) $res->json('rincian.total_per_kunjungan'),
            (float) Task::latest('id')->first()->harga,
        );
    }

    public function test_kode_asing_tidak_memotong_apa_pun(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload(['promo_kode' => 'GRATIS100PERSEN']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Kode promo tidak dikenal.');
        $this->assertSame(
            (float) $res->json('rincian.total_per_kunjungan'),
            (float) Task::latest('id')->first()->harga,
        );
    }

    public function test_bisabaru_ditolak_untuk_pelanggan_yang_sudah_pernah_pesan(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Task::create([
            'nomor_invoice' => 'SBB-260901-LAMA001',
            'customer_id' => $user->id,
            'category_id' => Category::first()->id,
            'tipe' => 'fixed',
            'judul' => 'BisaBersih — Bersih Rumah',
            'deskripsi' => 'pesanan sebelumnya',
            'status' => 'selesai',
            'fulfillment_status' => 'selesai',
            'lokasi_alamat' => 'Jl. Uji 1',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'harga' => 170000,
        ]);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload(['promo_kode' => 'BISABARU']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Promo ini hanya untuk pesanan BisaBersih pertama.');
    }

    public function test_promo_ditolak_di_bawah_minimum(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Kantor kecil tanpa fasilitas jatuh ke minimum kunjungan Rp250.000.
        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'jenis_kantor' => 'kecil',
            'paket' => 'basic',
            'workstation' => 0,
            'ruang_meeting' => 0,
            'toilet' => 0,
            'pantry' => 0,
            'promo_kode' => 'BISABARU',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $this->assertStringContainsString('minimum', (string) $res->json('rincian.promo_ditolak'));
    }

    public function test_kantor_besar_tetap_ditolak_walau_membawa_promo(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'jenis_kantor' => 'besar',
            'promo_kode' => 'BISABARU',
        ]))->assertStatus(422);

        $this->assertSame(0, Task::count());
    }

    /**
     * Katalog PHP dan TypeScript harus menyebut angka yang sama.
     *
     * Dua salinan tidak bisa dihindari — halaman promo butuh judul dan syarat,
     * server hanya butuh angkanya — tapi keduanya boleh berbeda hanya pada
     * materi tampilan. Begitu angkanya berbeda, pelanggan melihat satu potongan
     * dan ditagih potongan lain: persis cacat yang membuat kelas ini ada.
     */
    public function test_katalog_php_dan_typescript_sinkron(): void
    {
        $berkas = base_path('../frontend/src/lib/promo/promoBersihKantor.ts');
        if (! is_file($berkas)) {
            $this->markTestSkipped('Katalog frontend tidak ada di lingkungan ini.');
        }

        $ts = file_get_contents($berkas);
        preg_match_all("/kode: '([A-Z0-9]+)',/", $ts, $m, PREG_OFFSET_CAPTURE);

        $angka = fn (string $s) => (int) str_replace('_', '', $s);
        $dariTs = [];

        foreach ($m[1] as $i => $cocok) {
            $mulai = $m[0][$i][1];
            $akhir = $m[0][$i + 1][1] ?? strlen($ts);
            $blok = substr($ts, $mulai, $akhir - $mulai);

            $ambil = function (string $pola) use ($blok, $angka): ?int {
                return preg_match($pola, $blok, $c) ? $angka($c[1]) : null;
            };

            $v = ['min' => $ambil('/minTransaksi: ([\d_]+),/')];
            if ($p = $ambil('/\n\s+potongan: ([\d_]+),/')) {
                $v['potongan'] = $p;
            }
            if ($p = $ambil('/diskonPersen: ([\d_]+),/')) {
                $v['persen'] = $p;
                $v['maks'] = $ambil('/diskonMaks: ([\d_]+),/');
            }
            if (str_contains($blok, 'penggunaBaru: true')) {
                $v['pengguna_baru'] = true;
            }

            $dariTs[$cocok[0]] = $v;
        }

        $this->assertNotEmpty($dariTs, 'Katalog TypeScript gagal dibaca.');

        // Urutan kunci tidak penting; isinya yang harus sama.
        $rapikan = function (array $katalog): array {
            ksort($katalog);
            foreach ($katalog as $k => $v) {
                ksort($v);
                $katalog[$k] = $v;
            }

            return $katalog;
        };

        $this->assertSame($rapikan(PromoKantor::VOUCHER), $rapikan($dariTs));
    }
}
