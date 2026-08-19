<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentConfirmTest extends TestCase
{
    use RefreshDatabase;

    private function pesananPending(User $customer): Payment
    {
        $kat = Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
        $task = Task::create([
            'customer_id' => $customer->id, 'category_id' => $kat->id, 'tipe' => 'fixed',
            'judul' => 'Pesanan', 'deskripsi' => '-', 'status' => 'pending',
            'lokasi_alamat' => 'Jl. Uji', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8, 'harga' => 56000,
        ]);

        return $task->payment()->create([
            'jumlah' => 56000, 'subtotal_barang' => 50000, 'ongkir' => 3500,
            'service_fee' => 2500, 'status' => 'pending',
        ]);
    }

    public function test_konfirmasi_menandai_pembayaran_lunas(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $payment = $this->pesananPending($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/payments/{$payment->id}/konfirmasi")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_konfirmasi_idempoten(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $payment = $this->pesananPending($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/payments/{$payment->id}/konfirmasi")->assertOk();
        $paidAt = $payment->fresh()->paid_at;

        // Callback diulang: tetap paid, waktu bayar tidak berubah.
        $this->postJson("/api/payments/{$payment->id}/konfirmasi")
            ->assertOk()
            ->assertJsonPath('status', 'paid');
        $this->assertEquals($paidAt->toString(), $payment->fresh()->paid_at->toString());
    }

    public function test_orang_lain_tidak_boleh_mengonfirmasi(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $penyusup = User::factory()->create(['role' => 'customer']);
        $payment = $this->pesananPending($customer);
        Sanctum::actingAs($penyusup);

        $this->postJson("/api/payments/{$payment->id}/konfirmasi")->assertStatus(403);
    }
}
