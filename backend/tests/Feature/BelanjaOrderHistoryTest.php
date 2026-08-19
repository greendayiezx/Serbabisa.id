<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promo;
use App\Models\User;
use App\Services\NomorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup fitur checkout lanjutan: resolusi item via `sku`, promo via
 * `promo_slug`, penerbitan `nomor_invoice`, serta riwayat (index) dan detail
 * pesanan by invoice (show).
 */
class BelanjaOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
    }

    private function produk(float $hargaJual, string $sku): Product
    {
        $kat = ProductCategory::firstOrCreate(['slug' => 'snack'], ['nama' => 'Snack']);

        return Product::create([
            'product_category_id' => $kat->id, 'sku' => $sku, 'nama' => 'Keripik '.$sku,
            'slug' => $sku, 'satuan' => 'pack', 'harga_dasar' => $hargaJual - 1000, 'harga_jual' => $hargaJual,
        ]);
    }

    private function checkout(array $override = [])
    {
        return $this->postJson('/api/belanja/checkout', array_merge([
            'items' => [['sku' => 'kripik-1', 'qty' => 5]],
            'jarak_km' => 4.2, 'lokasi_alamat' => 'Jl. Uji', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8,
        ], $override));
    }

    public function test_checkout_via_sku_memakai_harga_katalog(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $this->produk(10000, 'kripik-1');

        // Klien hanya kirim sku + qty (tanpa product_id), harga tetap dari DB.
        $this->checkout()
            ->assertCreated()
            ->assertJsonPath('payment.subtotal_barang', '50000.00')
            ->assertJsonPath('items.0.sku', 'kripik-1');
    }

    public function test_checkout_via_promo_slug(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $this->produk(10000, 'kripik-1');
        Promo::create([
            'slug' => 'belanja5', 'judul' => 'Diskon 2rb', 'grup' => 'tier',
            'min_belanja' => 50000, 'potongan_tetap' => 2000,
        ]);

        // 50.000 − 2.000 + 2.500 + 3.500 = 54.000
        $this->checkout(['promo_slug' => 'belanja5'])
            ->assertCreated()
            ->assertJsonPath('payment.jumlah', '54000.00');
    }

    public function test_nomor_invoice_diterbitkan_dan_valid(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $this->produk(10000, 'kripik-1');

        $order = $this->checkout()->assertCreated()->json();

        $this->assertNotEmpty($order['nomor_invoice']);
        $this->assertTrue(NomorInvoice::valid($order['nomor_invoice']), 'nomor invoice harus lolos checksum');
    }

    public function test_index_hanya_menampilkan_pesanan_sendiri(): void
    {
        $this->produk(10000, 'kripik-1');

        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $this->checkout()->assertCreated();
        $this->checkout()->assertCreated();

        // Pesanan milik orang lain tidak boleh ikut.
        $lain = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($lain);
        $this->checkout()->assertCreated();

        Sanctum::actingAs($user);
        $this->getJson('/api/belanja/orders')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_show_menerima_invoice_penuh_dan_kode_pendek(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $this->produk(10000, 'kripik-1');

        $invoice = $this->checkout()->assertCreated()->json('nomor_invoice');
        $kodePendek = last(explode('-', $invoice)); // 7 karakter setelah tanggal

        $this->getJson('/api/belanja/orders/'.$invoice)->assertOk()->assertJsonPath('nomor_invoice', $invoice);
        $this->getJson('/api/belanja/orders/'.$kodePendek)->assertOk()->assertJsonPath('nomor_invoice', $invoice);

        // User lain tidak bisa mengintip pesanan ini.
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/belanja/orders/'.$invoice)->assertNotFound();
    }
}
