<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\DeepTarif;
use App\Services\PromoDeep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Promo BisaBersih Deep Cleaning.
 *
 * Yang dijaga: potongan benar-benar mengurangi ANGKA YANG DITAGIH, syaratnya
 * diperiksa di server, dan katalog PHP tidak menyimpang dari katalog
 * TypeScript yang dipakai layar.
 */
class DeepPromoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBersih', 'slug' => 'bisabersih', 'basis_harga' => 'durasi']);
    }

    private function payload(array $ganti = []): array
    {
        return [
            'paket' => 'move_in',
            'luas_m2' => 50,
            'jumlah_ruangan' => 3,
            'add_on' => [],
            'tanggal' => '2026-09-10',
            'waktu' => '10:00',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_promo_mengurangi_angka_yang_ditagih(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload(['promo_kode' => 'DEEP60']));
        $res->assertCreated();

        $penuh = DeepTarif::PAKET['move_in']['harga'];
        $potongan = PromoDeep::VOUCHER['DEEP60']['potongan'];

        $res->assertJsonPath('rincian.potongan_promo', $potongan);
        $res->assertJsonPath('rincian.total_ditagih', $penuh - $potongan);

        $task = Task::latest('id')->with('payment')->first();
        $this->assertSame((float) ($penuh - $potongan), (float) $task->harga);
        $this->assertSame((float) ($penuh - $potongan), (float) $task->payment->jumlah);
        $this->assertSame((float) $potongan, (float) $task->payment->potongan);
    }

    public function test_pengguna_baru_ditolak_kalau_sudah_pernah_pesan(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Task::create([
            'nomor_invoice' => 'SBB-260901-LAMA002',
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

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload(['promo_kode' => 'DEEPBARU50']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Promo ini hanya untuk pesanan BisaBersih pertama.');
    }

    public function test_promo_di_bawah_minimum_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Pesanan termurah (Rp625.000) belum mencapai minimum DEEP100.
        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload(['promo_kode' => 'DEEP100']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $this->assertStringContainsString('minimum', (string) $res->json('rincian.promo_ditolak'));
    }

    public function test_pindahbersih_hanya_untuk_paket_tertentu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Sanitasi Total dengan tagihan di atas Rp1.000.000 — minimumnya lolos,
        // tapi paketnya memang bukan sasaran promo pindahan.
        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'paket' => 'sanitasi_total',
            'luas_m2' => 150,
            'promo_kode' => 'PINDAHBERSIH',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Promo ini hanya untuk paket Move-In dan Pasca Renovasi.');
    }

    public function test_pindahbersih_berlaku_untuk_pasca_renovasi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'paket' => 'pasca_renovasi',
            'luas_m2' => 120,
            'promo_kode' => 'PINDAHBERSIH',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', PromoDeep::VOUCHER['PINDAHBERSIH']['potongan']);
    }

    public function test_kode_asing_tidak_memotong(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload(['promo_kode' => 'GRATISSEMUA']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Kode promo tidak dikenal.');
        $this->assertSame(
            (float) DeepTarif::PAKET['move_in']['harga'],
            (float) Task::latest('id')->first()->harga,
        );
    }

    /**
     * Katalog PHP dan TypeScript harus menyebut angka yang sama.
     *
     * Dua salinan tidak bisa dihindari — layar butuh judul dan syarat, server
     * hanya butuh angkanya — tapi begitu angkanya berbeda, pelanggan melihat
     * satu potongan dan ditagih potongan lain.
     */
    public function test_katalog_php_dan_typescript_sinkron(): void
    {
        $berkas = base_path('../frontend/src/lib/promo/promoBersihDeep.ts');
        if (! is_file($berkas)) {
            $this->markTestSkipped('Katalog frontend tidak ada di lingkungan ini.');
        }

        $ts = file_get_contents($berkas);
        preg_match_all("/kode: '([A-Z0-9]+)',/", $ts, $m, PREG_OFFSET_CAPTURE);

        $angka = fn (string $x) => (int) str_replace('_', '', $x);
        $dariTs = [];

        foreach ($m[1] as $i => $cocok) {
            $mulai = $m[0][$i][1];
            $akhir = $m[0][$i + 1][1] ?? strlen($ts);
            $blok = substr($ts, $mulai, $akhir - $mulai);

            $v = [];
            if (preg_match('/minTransaksi: ([\d_]+),/', $blok, $c)) {
                $v['min'] = $angka($c[1]);
            }
            if (preg_match('/\n\s+potongan: ([\d_]+),/', $blok, $c)) {
                $v['potongan'] = $angka($c[1]);
            }
            if (str_contains($blok, 'penggunaBaru: true')) {
                $v['pengguna_baru'] = true;
            }

            $dariTs[$cocok[0]] = $v;
        }

        $this->assertNotEmpty($dariTs, 'Katalog TypeScript gagal dibaca.');

        // Batasan paket hanya ada di server; dibandingkan tanpa bidang itu.
        $dariPhp = [];
        foreach (PromoDeep::VOUCHER as $kode => $v) {
            unset($v['paket']);
            ksort($v);
            $dariPhp[$kode] = $v;
        }
        ksort($dariPhp);

        foreach ($dariTs as $k => $v) {
            ksort($v);
            $dariTs[$k] = $v;
        }
        ksort($dariTs);

        $this->assertSame($dariPhp, $dariTs);
    }
}
