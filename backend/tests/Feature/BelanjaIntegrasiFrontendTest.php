<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promo;
use App\Models\Task;
use App\Models\User;
use App\Services\NomorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Jembatan antara katalog frontend dan endpoint checkout.
 *
 * Aplikasi mengirim `sku` (id katalog di lib/belanjaKatalog.ts) dan `promo_slug`
 * (id promo di lib/promo.ts), bukan id numerik baris DB — id numerik berbeda
 * antar lingkungan, slug dan SKU tidak. Tes ini yang menjaga jembatan itu tetap
 * nyambung kalau salah satu sisi diubah.
 */
class BelanjaIntegrasiFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
    }

    private function produk(string $sku, float $hargaJual): Product
    {
        $kat = ProductCategory::firstOrCreate(['slug' => 'snack'], ['nama' => 'Snack']);

        return Product::create([
            'product_category_id' => $kat->id,
            'sku' => $sku,
            'nama' => 'Keripik '.$sku,
            'slug' => $sku,
            'satuan' => 'pack',
            'harga_dasar' => $hargaJual - 1000,
            'harga_jual' => $hargaJual,
        ]);
    }

    private function lokasi(): array
    {
        return ['jarak_km' => 4.2, 'lokasi_alamat' => 'Jl. Uji 1', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8];
    }

    public function test_item_diresolusi_lewat_sku_katalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-keripik-singkong-200gr', 12000);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'nama dari klien', 'qty' => 3]],
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('items.0.product_id', $produk->id);
        $res->assertJsonPath('items.0.sku', $produk->sku);
        // Nama ikut diambil dari DB, bukan dari yang dikirim klien.
        $res->assertJsonPath('items.0.nama', $produk->nama);
        $res->assertJsonPath('payment.subtotal_barang', '36000.00');
    }

    public function test_harga_kiriman_klien_diabaikan_untuk_item_berkatalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-mahal', 90000);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'Keripik', 'qty' => 1, 'harga' => 1]],
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('items.0.harga_satuan', '90000.00');
        $res->assertJsonPath('payment.subtotal_barang', '90000.00');
    }

    public function test_item_ketik_manual_tersimpan_tanpa_sku(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['nama' => 'Cabai rawit seikat', 'qty' => 1, 'catatan' => 'yang merah']],
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('items.0.sku', null);
        $res->assertJsonPath('items.0.nama', 'Cabai rawit seikat');
        $res->assertJsonPath('items.0.catatan', 'yang merah');
    }

    public function test_promo_dipilih_lewat_slug(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-a', 60000);
        Promo::create([
            'slug' => 'belanja5', 'kode' => 'BELANJA2', 'judul' => 'Diskon Rp2.000',
            'grup' => 'tier', 'min_belanja' => 50000, 'potongan_tetap' => 2000, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            'promo_slug' => 'belanja5',
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('payment.potongan', '2000.00');
    }

    public function test_ongkir_normal_tersimpan_walau_ongkir_digratiskan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-b', 200000);
        Promo::create([
            'slug' => 'gratis5', 'judul' => 'Gratis Ongkir', 'grup' => 'gratis-ongkir',
            'min_belanja' => 150000, 'gratis_ongkir' => true, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            'promo_slug' => 'gratis5',
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('payment.ongkir', '0.00');
        // Tanpa ini nota tidak bisa menampilkan tarif asli yang dicoret.
        $res->assertJsonPath('payment.ongkir_normal', '3500.00');
    }

    public function test_setiap_pesanan_dapat_nomor_invoice_unik(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-c', 10000);

        $nomor = [];
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/belanja/checkout', [
                'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
                ...$this->lokasi(),
            ]);
            $res->assertCreated();
            $nomor[] = $res->json('nomor_invoice');
        }

        $this->assertCount(5, array_unique($nomor));
        foreach ($nomor as $n) {
            $this->assertMatchesRegularExpression('/^SBB-\d{6}-[0-9A-Z]{7}$/', $n);
            $this->assertTrue(NomorInvoice::valid($n), "checksum $n tidak valid");
        }
    }

    public function test_checksum_menangkap_salah_ketik_satu_digit(): void
    {
        $nomor = (new NomorInvoice)->buat()['invoice'];
        $posisi = 11; // digit pertama blok kode
        $lain = $nomor[$posisi] === 'A' ? 'B' : 'A';

        $this->assertTrue(NomorInvoice::valid($nomor));
        $this->assertFalse(NomorInvoice::valid(substr_replace($nomor, $lain, $posisi, 1)));
    }

    public function test_pesanan_bisa_dibaca_lewat_nomor_utuh_maupun_kode_pendek(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-d', 15000);

        $invoice = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 2]],
            ...$this->lokasi(),
        ])->json('nomor_invoice');

        $pendek = explode('-', $invoice)[2];

        $this->getJson("/api/belanja/orders/{$invoice}")
            ->assertOk()->assertJsonPath('nomor_invoice', $invoice);

        $this->getJson("/api/belanja/orders/{$pendek}")
            ->assertOk()->assertJsonPath('nomor_invoice', $invoice);
    }

    public function test_pesanan_orang_lain_tidak_bisa_dibaca(): void
    {
        $pemilik = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($pemilik);
        $produk = $this->produk('snack-e', 15000);

        $invoice = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            ...$this->lokasi(),
        ])->json('nomor_invoice');

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/belanja/orders/{$invoice}")->assertNotFound();
    }

    public function test_riwayat_hanya_berisi_pesanan_sendiri(): void
    {
        $produk = $this->produk('snack-f', 15000);

        $a = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($a);
        $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            ...$this->lokasi(),
        ])->assertCreated();

        $b = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($b);
        $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            ...$this->lokasi(),
        ])->assertCreated();

        $this->getJson('/api/belanja/orders')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($a);
        $this->getJson('/api/belanja/orders')->assertOk()->assertJsonCount(1);
    }

    public function test_toko_tersimpan_di_kolom_sendiri(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk('snack-g', 15000);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'x', 'qty' => 1]],
            'toko' => 'Superindo Dago',
            ...$this->lokasi(),
        ]);

        $res->assertCreated()->assertJsonPath('toko', 'Superindo Dago');
        $this->assertSame('Superindo Dago', Task::first()->toko);
    }

    public function test_checkout_ditolak_tanpa_login(): void
    {
        $this->postJson('/api/belanja/checkout', ['items' => [], ...$this->lokasi()])
            ->assertUnauthorized();
        $this->getJson('/api/belanja/orders')->assertUnauthorized();
    }
}
