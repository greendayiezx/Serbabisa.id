<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
    }

    private function daftar(array $override = []): array
    {
        return $this->postJson('/api/register', array_merge([
            'name' => 'Uji '.uniqid(),
            'email' => uniqid().'@test.id',
            'phone' => '08'.random_int(100000000, 999999999),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer',
        ], $override))->json();
    }

    public function test_register_menghasilkan_kode_referral(): void
    {
        $hasil = $this->daftar();
        $this->assertNotEmpty($hasil['user']['kode_referral']);
    }

    public function test_register_dengan_kode_tak_dikenal_ditolak(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Uji', 'email' => 'x@test.id', 'phone' => '081234567890',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'customer', 'kode_referral' => 'NGACO999',
        ])->assertStatus(422)->assertJsonValidationErrors('kode_referral');
    }

    public function test_bonus_referral_cair_saat_pesanan_pertama_selesai(): void
    {
        // Pengundang mendaftar, dapat kode.
        $pengundang = $this->daftar(['name' => 'Pengundang']);
        $kode = $pengundang['user']['kode_referral'];

        // Teman mendaftar pakai kode itu.
        $teman = $this->daftar(['name' => 'Teman', 'kode_referral' => $kode]);
        $temanUser = User::find($teman['user']['id']);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $pengundang['user']['id'],
            'referred_id' => $temanUser->id,
            'status' => 'pending',
        ]);

        // Teman checkout & pesanan diselesaikan.
        $kat = ProductCategory::create(['nama' => 'Snack', 'slug' => 'snack']);
        $produk = Product::create([
            'product_category_id' => $kat->id, 'nama' => 'Keripik', 'slug' => 'kripik',
            'satuan' => 'pack', 'harga_dasar' => 9000, 'harga_jual' => 10000,
        ]);

        Sanctum::actingAs($temanUser);
        $order = $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 3]],
            'jarak_km' => 4.2, 'lokasi_alamat' => 'Jl. Uji', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8,
        ])->assertCreated()->json();

        $this->patchJson("/api/belanja/orders/{$order['id']}/selesai")->assertOk();

        // Pengundang menerima bonus Rp2.000 sekali.
        $walletPengundang = User::find($pengundang['user']['id'])->wallet()->first();
        $this->assertEquals(Referral::DEFAULT_REWARD, $walletPengundang->saldo);
        $this->assertEquals(1, $walletPengundang->transactions()->count());
        $this->assertDatabaseHas('referrals', [
            'referred_id' => $temanUser->id,
            'status' => 'rewarded',
            'task_id' => $order['id'],
        ]);
    }
}
