<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\ACTarif;
use App\Services\PromoAC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Promo Servis AC.
 *
 * Yang dijaga: potongan mengurangi ANGKA YANG DITAGIH, syaratnya diperiksa di
 * server, dan katalog PHP tidak menyimpang dari katalog TypeScript yang dipakai
 * layar.
 */
class ACPromoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaTukang', 'slug' => 'bisatukang', 'basis_harga' => 'kunjungan_jam']);
    }

    private function payload(array $ganti = []): array
    {
        return [
            'paket' => 'standard',
            'unit' => 1,
            'tipe' => 'split',
            'kapasitas' => '1',
            'tanggal' => '2026-09-12',
            'waktu' => '10:00',
            'nama_penerima' => 'Budi Uji',
            'telepon_penerima' => '081200003333',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_promo_pengguna_baru_dipakai_pada_pesanan_pertama(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/checkout', $this->payload(['promo_kode' => 'ACBARU25']));

        $penuh = ACTarif::PAKET['standard']['harga'] + ACTarif::BIAYA_KUNJUNGAN;
        $potongan = PromoAC::VOUCHER['ACBARU25']['potongan'];

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', $potongan);
        $res->assertJsonPath('rincian.total_ditagih', $penuh - $potongan);
        $this->assertSame((float) ($penuh - $potongan), (float) Task::latest('id')->first()->harga);
    }

    public function test_promo_pengguna_baru_ditolak_pada_pesanan_kedua(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        // Pesanan pertama memakai promonya.
        $this->postJson('/api/servis-ac/checkout', $this->payload(['promo_kode' => 'ACBARU25']))
            ->assertCreated();

        // Yang kedua tidak lagi berhak.
        $res = $this->postJson('/api/servis-ac/checkout', $this->payload(['promo_kode' => 'ACBARU25']));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.promo_ditolak', 'Promo ini hanya untuk pesanan Servis AC pertama.');
    }

    public function test_promo_tiga_unit_butuh_tiga_unit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Deep 2 unit = Rp490.000: minimumnya lolos, jumlah unitnya belum.
        $res = $this->postJson('/api/servis-ac/checkout', $this->payload([
            'paket' => 'deep',
            'unit' => 2,
            'promo_kode' => 'ACHEMAT3',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $this->assertStringContainsString('3 unit', (string) $res->json('rincian.promo_ditolak'));
    }

    public function test_promo_tiga_unit_berlaku_pada_tiga_unit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/checkout', $this->payload([
            'unit' => 3,
            'promo_kode' => 'ACHEMAT3',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', PromoAC::VOUCHER['ACHEMAT3']['potongan']);
    }

    /**
     * Tiap promo harus menyisakan margin.
     *
     * Diuji pada kombinasi paling menekan untuk masing-masing: tagihan terkecil
     * yang masih memenuhi syaratnya. Di situlah potongan menggerus bagian
     * terbesar dari selisih harga dan biaya.
     */
    public function test_setiap_promo_menyisakan_margin(): void
    {
        $tarif = new ACTarif;
        $promo = new PromoAC;

        foreach (PromoAC::VOUCHER as $kode => $v) {
            // Promo khusus pemeriksaan freon diuji di FreonTest — katalog harga
            // cuci AC di sini memang bukan lahannya.
            if (($v['layanan'] ?? 'cuci') !== 'cuci') {
                continue;
            }

            $terburuk = null;

            foreach (array_keys(ACTarif::PAKET) as $paket) {
                for ($unit = 1; $unit <= 6; $unit++) {
                    $r = $tarif->hitung($paket, $unit);
                    $hasil = $promo->hitung($kode, $r['total'], $unit);
                    if (! $hasil['berlaku']) {
                        continue;
                    }

                    $margin = $r['total'] - $r['biaya'] - $hasil['potongan'];
                    $rasio = $margin / $r['total'];
                    if ($terburuk === null || $rasio < $terburuk['rasio']) {
                        $terburuk = ['rasio' => $rasio, 'paket' => $paket, 'unit' => $unit, 'margin' => $margin];
                    }
                }
            }

            $this->assertNotNull($terburuk, "Promo {$kode} tidak pernah bisa dipakai.");
            $this->assertGreaterThan(
                0.15,
                $terburuk['rasio'],
                "Promo {$kode} menyisakan margin terlalu tipis pada {$terburuk['paket']} × {$terburuk['unit']} unit.",
            );
        }
    }

    /**
     * Katalog PHP dan TypeScript harus menyebut angka yang sama.
     *
     * Begitu berbeda, pelanggan melihat satu potongan dan ditagih potongan lain.
     */
    public function test_katalog_php_dan_typescript_sinkron(): void
    {
        $berkas = base_path('../frontend/src/lib/promo/promoAC.ts');
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
            if (preg_match('/diskonPersen: ([\d_]+),/', $blok, $c)) {
                $v['persen'] = $angka($c[1]);
                if (preg_match('/diskonMaks: ([\d_]+),/', $blok, $d)) {
                    $v['maks'] = $angka($d[1]);
                }
            }
            if (preg_match('/\n\s+potongan: ([\d_]+),/', $blok, $c)) {
                $v['potongan'] = $angka($c[1]);
            }
            if (preg_match('/minUnit: ([\d_]+),/', $blok, $c)) {
                $v['min_unit'] = $angka($c[1]);
            }
            if (str_contains($blok, 'penggunaBaru: true')) {
                $v['pengguna_baru'] = true;
            }
            if (preg_match("/layanan: '([a-z]+)',/", $blok, $c)) {
                $v['layanan'] = $c[1];
            }

            $dariTs[$cocok[0]] = $v;
        }

        $this->assertNotEmpty($dariTs, 'Katalog TypeScript gagal dibaca.');

        $rapikan = function (array $katalog): array {
            ksort($katalog);
            foreach ($katalog as $k => $v) {
                ksort($v);
                $katalog[$k] = $v;
            }

            return $katalog;
        };

        $this->assertSame($rapikan(PromoAC::VOUCHER), $rapikan($dariTs));
    }
}
