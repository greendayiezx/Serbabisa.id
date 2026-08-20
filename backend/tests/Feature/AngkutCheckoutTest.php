<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AngkutCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaAngkut', 'slug' => 'bisaangkut', 'basis_harga' => 'volume_jarak']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'vehicle_id' => 'pickup_bak',
            'delivery_id' => 'instant',
            'protection_id' => 'silver',
            'helper_count' => 1,
            'berat_total' => 25,
            'tanggal' => '2026-08-25',
            'waktu' => '09:00',
            'catatan' => '2 kardus besar, 1 meja',
            'nama_penerima' => 'Budi',
            'telepon_penerima' => '+628123456789',
            'lokasi_alamat' => 'Jl. Uji 1',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'metode' => 'cash',
        ], $override);
    }

    public function test_checkout_menghitung_total_di_server(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        // pickup_bak instant 80.000 + helper 1×50.000 + silver 1.000 = 131.000
        $res = $this->postJson('/api/angkut/checkout', $this->payload());

        $res->assertCreated()
            ->assertJsonPath('payment.jumlah', '131000.00')
            ->assertJsonPath('payment.ongkir', '80000.00')
            ->assertJsonPath('payment.service_fee', '51000.00')
            ->assertJsonPath('kendaraan', 'Pickup Bak')
            ->assertJsonPath('jumlah_helper', 1)
            ->assertJsonPath('proteksi_label', 'Perlindungan Silver');
    }

    public function test_pesanan_muncul_sebagai_task_customer(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $this->postJson('/api/angkut/checkout', $this->payload())->assertCreated();

        // Muncul di daftar "Tugas Saya" (GET /tasks).
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(1);
        $this->assertDatabaseHas('tasks', [
            'customer_id' => $user->id,
            'kendaraan' => 'Pickup Bak',
            'fulfillment_status' => 'diproses',
        ]);
    }

    public function test_harga_klien_tidak_dipercaya(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        // Klien menyelipkan total/harga palsu — server tetap menghitung sendiri.
        $res = $this->postJson('/api/angkut/checkout', $this->payload([
            'total' => 1, 'harga' => 1, 'jumlah' => 1,
        ]));

        $res->assertCreated()->assertJsonPath('payment.jumlah', '131000.00');
    }

    public function test_field_wajib_divalidasi(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        // catatan, berat, jadwal, penerima kosong → 422 (tidak lagi "klik terus")
        $this->postJson('/api/angkut/checkout', [
            'vehicle_id' => 'pickup_bak', 'delivery_id' => 'instant', 'protection_id' => 'silver',
            'helper_count' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'berat_total', 'tanggal', 'waktu', 'catatan', 'nama_penerima', 'telepon_penerima',
        ]);
    }

    public function test_kendaraan_tak_dikenal_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $this->postJson('/api/angkut/checkout', $this->payload(['vehicle_id' => 'helikopter']))
            ->assertStatus(422)->assertJsonValidationErrors('vehicle_id');
    }
}
