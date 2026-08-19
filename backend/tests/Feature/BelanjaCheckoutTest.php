<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BelanjaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
    }

    private function produk(float $hargaJual): Product
    {
        $kat = ProductCategory::create(['nama' => 'Snack', 'slug' => 'snack']);

        return Product::create([
            'product_category_id' => $kat->id,
            'nama' => 'Keripik', 'slug' => 'keripik-'.uniqid(), 'satuan' => 'pack',
            'harga_dasar' => $hargaJual - 1000, 'harga_jual' => $hargaJual,
        ]);
    }

    private function payloadLokasi(): array
    {
        return ['jarak_km' => 4.2, 'lokasi_alamat' => 'Jl. Uji 1', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8];
    }

    public function test_checkout_menghitung_tagihan_dari_harga_katalog(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(10000);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            ...$this->payloadLokasi(),
        ]);

        $res->assertCreated();
        // 5 × 10.000 = 50.000 barang + 2.500 layanan + 3.500 ongkir = 56.000
        $res->assertJsonPath('payment.subtotal_barang', '50000.00');
        $res->assertJsonPath('payment.ongkir', '3500.00');
        $res->assertJsonPath('payment.service_fee', '2500.00');
        $res->assertJsonPath('payment.jumlah', '56000.00');
        $this->assertCount(1, $res->json('items'));
    }

    public function test_harga_kiriman_klien_diabaikan_untuk_item_berkatalog(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(10000);

        // Klien mencoba menurunkan harga jadi 1 rupiah — harus diabaikan.
        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'harga' => 1]],
            ...$this->payloadLokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('payment.subtotal_barang', '50000.00');
    }

    public function test_promo_valid_diterapkan_dan_dicatat(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(10000);
        $promo = Promo::create([
            'judul' => 'Diskon 2rb', 'grup' => 'tier', 'min_belanja' => 50000,
            'potongan_tetap' => 2000, 'kuota_per_user' => 1,
        ]);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'promo_id' => $promo->id,
            ...$this->payloadLokasi(),
        ]);

        $res->assertCreated();
        // 50.000 − 2.000 + 2.500 + 3.500 = 54.000
        $res->assertJsonPath('payment.jumlah', '54000.00');
        $res->assertJsonPath('payment.potongan', '2000.00');
        $this->assertEquals(1, PromoRedemption::where('promo_id', $promo->id)->where('user_id', $user->id)->count());
        $this->assertEquals(1, $promo->fresh()->kuota_terpakai);
    }

    public function test_promo_habis_kuota_ditolak_422(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(10000);
        $promo = Promo::create([
            'judul' => 'Sekali', 'grup' => 'tier', 'min_belanja' => 50000,
            'potongan_tetap' => 2000, 'kuota_per_user' => 1,
        ]);
        PromoRedemption::create(['promo_id' => $promo->id, 'user_id' => $user->id, 'redeemed_at' => now()]);

        $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'promo_id' => $promo->id,
            ...$this->payloadLokasi(),
        ])->assertStatus(422)->assertJsonValidationErrors('promo_id');
    }

    public function test_selesai_mengkreditkan_cashback_ke_saldo(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(20000);
        $promo = Promo::create([
            'judul' => 'Cashback 2%', 'grup' => 'cashback', 'min_belanja' => 100000,
            'cashback_persen' => 2, 'cashback_maks' => 3000, 'kuota_per_user' => 1,
        ]);

        // 5 × 20.000 = 100.000 → cashback 2% = 2.000
        $checkout = $this->postJson('/api/belanja/checkout', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'promo_id' => $promo->id,
            ...$this->payloadLokasi(),
        ])->assertCreated();

        $this->assertEquals('2000.00', $checkout->json('payment.cashback'));

        $taskId = $checkout->json('id');
        $this->patchJson("/api/belanja/orders/{$taskId}/selesai")->assertOk();

        $wallet = $user->wallet()->first();
        $this->assertEquals(2000, $wallet->saldo);
        $this->assertEquals(1, $wallet->transactions()->where('tipe', 'cashback')->count());

        // Menandai selesai lagi ditolak (idempotensi lifecycle).
        $this->patchJson("/api/belanja/orders/{$taskId}/selesai")->assertStatus(422);
        $this->assertEquals(2000, $wallet->fresh()->saldo);
    }
}
