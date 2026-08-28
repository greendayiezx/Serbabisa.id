<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\DeepTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Checkout BisaBersih Deep Cleaning.
 *
 * Yang dijaga: harga dihitung server dari pilihan, dan layanan tambahan yang
 * sudah termasuk paket tidak ditagih dua kali — harga paketnya memang sudah
 * dinaikkan sebesar layanan itu.
 */
class DeepCheckoutTest extends TestCase
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
            'metode' => 'bca',
            ...$ganti,
        ];
    }

    public function test_lingkup_standar_ditagih_harga_paket(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload());

        $res->assertCreated();
        $res->assertJsonPath('rincian.total', DeepTarif::PAKET['move_in']['harga']);
        $res->assertJsonPath('rincian.biaya_luas', 0);
        $res->assertJsonPath('rincian.biaya_ruangan', 0);
    }

    public function test_kelebihan_luas_dan_ruangan_ditambahkan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'luas_m2' => 80,
            'jumlah_ruangan' => 5,
        ]));

        $luas = 30 * DeepTarif::TARIF_LUAS;
        $ruangan = 2 * DeepTarif::TARIF_RUANGAN;

        $res->assertCreated();
        $res->assertJsonPath('rincian.biaya_luas', $luas);
        $res->assertJsonPath('rincian.biaya_ruangan', $ruangan);
        $res->assertJsonPath('rincian.total', DeepTarif::PAKET['move_in']['harga'] + $luas + $ruangan);
    }

    public function test_add_on_per_ruangan_dikali_jumlah_ruangan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Scrubbing tidak termasuk paket Move-In, jadi memang ditagih.
        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'jumlah_ruangan' => 4,
            'add_on' => ['scrubbing'],
        ]));

        $scrubbing = DeepTarif::ADD_ON['scrubbing']['harga'] * 4;
        $ruangan = 1 * DeepTarif::TARIF_RUANGAN;

        $res->assertCreated();
        $res->assertJsonPath('rincian.add_on', $scrubbing);
        $res->assertJsonPath('rincian.total', DeepTarif::PAKET['move_in']['harga'] + $ruangan + $scrubbing);
    }

    public function test_add_on_yang_sudah_termasuk_paket_tidak_ditagih_dua_kali(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Paket Sanitasi Total sudah termasuk fogging DAN sedot tungau.
        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'paket' => 'sanitasi_total',
            'add_on' => ['fogging', 'tungau'],
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.add_on', 0);
        $res->assertJsonPath('rincian.total', DeepTarif::PAKET['sanitasi_total']['harga']);
    }

    public function test_harga_tidak_diambil_dari_klien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Angka yang dikarang browser tidak punya jalan masuk ke tagihan.
        $res = $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'total' => 1000,
            'harga' => 1000,
            'est_price' => 1000,
        ]));

        $res->assertCreated();
        $task = Task::latest('id')->with('payment')->first();
        $this->assertSame((float) DeepTarif::PAKET['move_in']['harga'], (float) $task->harga);
        $this->assertSame((float) DeepTarif::PAKET['move_in']['harga'], (float) $task->payment->jumlah);
    }

    public function test_pesanan_tersimpan_lengkap(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/deep/checkout', $this->payload([
            'luas_m2' => 70,
            'add_on' => ['scrubbing'],
            'catatan' => 'Ada kucing di rumah',
        ]))->assertCreated();

        $task = Task::with(['items', 'payment'])->latest('id')->first();

        $this->assertStringStartsWith('BisaBersih — Deep Cleaning', $task->judul);
        $this->assertNotNull($task->nomor_invoice);
        $this->assertSame('deep', $task->detail_layanan['layanan']);
        $this->assertSame('Ada kucing di rumah', $task->catatan);
        $this->assertNotNull($task->dijadwalkan_pada);
        // Paket + kelebihan luas + scrubbing.
        $this->assertCount(3, $task->items);
        $this->assertGreaterThan(0, $task->detail_layanan['jumlah_cleaner']);
        $this->assertGreaterThan(0, $task->detail_layanan['durasi_jam']);
    }

    public function test_paket_asing_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/deep/checkout', $this->payload(['paket' => 'paket_karangan']))
            ->assertStatus(422);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/bersih/deep/checkout', $this->payload())->assertUnauthorized();
    }
}
