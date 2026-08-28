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
 * Checkout Servis AC.
 *
 * Yang dijaga: harga dihitung server per unit, potongan bundling dan biaya
 * kunjungan mengikuti jumlah unit, dan promo tidak pernah diambil dari klien.
 */
class ACCheckoutTest extends TestCase
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
            'terakhir_cuci' => '3-6-bulan',
            'kondisi' => ['kurang-dingin'],
            'tanggal' => '2026-09-12',
            'waktu' => '10:00',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'metode' => 'bca',
            ...$ganti,
        ];
    }

    public function test_satu_unit_kena_biaya_kunjungan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/checkout', $this->payload());

        $harga = ACTarif::PAKET['standard']['harga'];
        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', $harga);
        $res->assertJsonPath('rincian.biaya_kunjungan', ACTarif::BIAYA_KUNJUNGAN);
        $res->assertJsonPath('rincian.diskon_bundling', 0);
        $res->assertJsonPath('rincian.total', $harga + ACTarif::BIAYA_KUNJUNGAN);
    }

    public function test_dua_unit_dapat_potongan_bundling(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/checkout', $this->payload(['unit' => 2]));

        $layanan = ACTarif::PAKET['standard']['harga'] * 2;
        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', $layanan);
        $res->assertJsonPath('rincian.diskon_bundling', ACTarif::DISKON_2_UNIT);
        $res->assertJsonPath('rincian.biaya_kunjungan', ACTarif::BIAYA_KUNJUNGAN);
        $res->assertJsonPath('rincian.total', $layanan + ACTarif::BIAYA_KUNJUNGAN - ACTarif::DISKON_2_UNIT);
    }

    public function test_tiga_unit_bebas_biaya_kunjungan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/checkout', $this->payload(['unit' => 3]));

        $layanan = ACTarif::PAKET['standard']['harga'] * 3;
        $res->assertCreated();
        $res->assertJsonPath('rincian.gratis_kunjungan', true);
        $res->assertJsonPath('rincian.biaya_kunjungan', 0);
        $res->assertJsonPath('rincian.total', $layanan - ACTarif::DISKON_2_UNIT);
    }

    public function test_promo_persen_dibatasi_maksimalnya(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Deep cleaning 3 unit = Rp750.000 − 20.000 = 730.000.
        // 20% darinya 146.000, tapi dibatasi Rp50.000.
        $res = $this->postJson('/api/servis-ac/checkout', $this->payload([
            'paket' => 'deep',
            'unit' => 3,
            'promo_kode' => 'GERCEPAC',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', PromoAC::VOUCHER['GERCEPAC']['maks']);
    }

    public function test_promo_butuh_jumlah_unit_minimum(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Deep 1 unit = Rp260.000: minimumnya lolos, jadi yang menolak
        // memang syarat jumlah unitnya — bukan nilai transaksinya.
        $res = $this->postJson('/api/servis-ac/checkout', $this->payload([
            'paket' => 'deep',
            'unit' => 1,
            'promo_kode' => 'ACHEMAT2',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $this->assertStringContainsString('2 unit', (string) $res->json('rincian.promo_ditolak'));
    }

    public function test_harga_tidak_diambil_dari_klien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/checkout', $this->payload([
            'total' => 1000,
            'harga' => 1000,
        ]))->assertCreated();

        $task = Task::latest('id')->with('payment')->first();
        $benar = ACTarif::PAKET['standard']['harga'] + ACTarif::BIAYA_KUNJUNGAN;
        $this->assertSame((float) $benar, (float) $task->harga);
        $this->assertSame((float) $benar, (float) $task->payment->jumlah);
    }

    public function test_pesanan_tersimpan_lengkap(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/checkout', $this->payload([
            'unit' => 2,
            'kondisi' => ['berbau', 'berdebu'],
            'rutin' => '3-bulan',
            'catatan' => 'AC kamar atas',
        ]))->assertCreated();

        $task = Task::with(['items', 'payment'])->latest('id')->first();

        $this->assertStringStartsWith('Servis AC —', $task->judul);
        $this->assertNotNull($task->nomor_invoice);
        $this->assertSame('servis-ac', $task->detail_layanan['layanan']);
        $this->assertSame(2, $task->detail_layanan['unit']);
        $this->assertSame('3-bulan', $task->detail_layanan['rutin']);
        $this->assertSame(['berbau', 'berdebu'], $task->detail_layanan['kondisi']);
        $this->assertNotNull($task->dijadwalkan_pada);
        // Baris layanan + baris biaya kunjungan.
        $this->assertCount(2, $task->items);
    }

    public function test_tipe_asing_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/checkout', $this->payload(['tipe' => 'ac-karangan']))
            ->assertStatus(422);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/servis-ac/checkout', $this->payload())->assertUnauthorized();
    }
}
