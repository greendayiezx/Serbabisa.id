<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\KantorTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pesan langsung BisaBersih Kantor.
 *
 * Inti yang dijaga: klien hanya mengirim pilihan, dan kantor besar tidak bisa
 * menembus jalur ini.
 */
class KantorCheckoutTest extends TestCase
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
            'jenis_kantor' => 'kecil',
            'paket' => 'basic',
            'frekuensi' => 'sekali',
            'workstation' => 0,
            'ruang_meeting' => 0,
            'toilet' => 0,
            'pantry' => 0,
            'add_on' => [],
            'tanggal' => '2026-09-01',
            'waktu' => '09:00',
            'lokasi_alamat' => 'Gedung Uji',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_harga_dihitung_dari_pilihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Small Office 50 m² x Rp1.200 = 60.000 → di bawah minimum Rp250.000.
        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload());

        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', KantorTarif::MINIMUM_KUNJUNGAN);
        $res->assertJsonPath('rincian.penyesuaian_minimum', KantorTarif::MINIMUM_KUNJUNGAN - 60000);
        $res->assertJsonPath('rincian.total_per_kunjungan', KantorTarif::MINIMUM_KUNJUNGAN);
    }

    public function test_fasilitas_paket_dan_frekuensi_ikut_dihitung(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'jenis_kantor' => 'sedang',
            'paket' => 'professional',
            'frekuensi' => '2x-minggu',
            'workstation' => 20,
            'ruang_meeting' => 2,
            'toilet' => 2,
            'pantry' => 1,
        ]));

        // (150x1200 + 20x3000 + 2x25000 + 2x35000 + 1x30000) x 1,15
        $dasar = (int) round((180000 + 60000 + 50000 + 70000 + 30000) * 1.15);
        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', $dasar);
        $res->assertJsonPath('rincian.diskon_frekuensi', (int) round($dasar * 0.10));
        $res->assertJsonPath('rincian.total_per_kunjungan', $dasar - (int) round($dasar * 0.10));
    }

    public function test_add_on_menambah_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'add_on' => ['karpet', 'disinfeksi'],
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.add_on', 120000 + 250000);
        $res->assertJsonCount(2, 'rincian.baris_add_on');
        // Add-on TIDAK ikut didiskon frekuensi — diskon hanya atas jasa dasar.
        $res->assertJsonPath('rincian.total_per_kunjungan', 250000 + 370000);
    }

    public function test_kantor_besar_tidak_bisa_dipesan_langsung(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/checkout', $this->payload(['jenis_kantor' => 'besar']))
            ->assertStatus(422)
            ->assertJsonPath('errors.jenis_kantor.0', 'Kantor besar tidak bisa dipesan langsung.');

        $this->assertSame(0, Task::count());
    }

    public function test_harga_dari_klien_diabaikan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'harga' => 1,
            'total' => 1,
            'total_per_kunjungan' => 1,
        ]));

        $res->assertCreated();
        $this->assertSame('250000.00', Task::first()->harga);
    }

    public function test_add_on_asing_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/checkout', $this->payload(['add_on' => ['diskon-99']]))
            ->assertStatus(422);
    }

    public function test_pesanan_tersimpan_lengkap(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/checkout', $this->payload([
            'add_on' => ['karpet'],
            'lainnya' => 'gudang',
            'catatan' => 'lift barang sampai jam 18',
        ]))->assertCreated();

        $task = Task::with(['items', 'payment'])->first();
        $this->assertSame('kantor', $task->detail_layanan['layanan']);
        $this->assertSame('gudang', $task->detail_layanan['lainnya']);
        $this->assertNotNull($task->nomor_invoice);
        // Satu baris jasa + satu baris add-on.
        $this->assertCount(2, $task->items);
        $this->assertSame('370000.00', $task->payment->jumlah);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/bersih/kantor/checkout', $this->payload())->assertUnauthorized();
    }
}
