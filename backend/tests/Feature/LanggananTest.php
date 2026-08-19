<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Langganan BisaBelanja.
 *
 * Alasan endpoint ini ada: sebelumnya paket aktif hanya ditandai di localStorage
 * browser, jadi checkout memotong Rp1.500 + menggratiskan ongkir untuk langganan
 * yang tidak diketahui server. Layar menampilkan Rp44.000, server menagih
 * Rp49.000. Tes terakhir di bawah yang menjaga selisih itu tidak kembali.
 */
class LanggananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBelanja', 'slug' => 'bisabelanja', 'basis_harga' => 'ongkos_belanja']);
        SubscriptionPlan::create([
            'kode' => 'family', 'nama' => 'Family', 'harga' => 79000,
            'diskon_per_transaksi' => 1500, 'kuota_diskon' => 30,
            'min_ongkir_gratis' => 0, 'kuota_ongkir' => 30, 'is_active' => true,
        ]);
        SubscriptionPlan::create([
            'kode' => 'basic', 'nama' => 'Basic', 'harga' => 29000,
            'diskon_per_transaksi' => 0, 'kuota_diskon' => 0,
            'min_ongkir_gratis' => 100000, 'kuota_ongkir' => 12, 'is_active' => true,
        ]);
    }

    private function produk(float $hargaJual): Product
    {
        $kat = ProductCategory::firstOrCreate(['slug' => 'pokok'], ['nama' => 'Bahan Pokok']);

        return Product::create([
            'product_category_id' => $kat->id, 'sku' => 'pokok-uji-'.uniqid(),
            'nama' => 'Gula', 'slug' => 'gula-'.uniqid(), 'satuan' => 'kg',
            'harga_dasar' => $hargaJual - 2000, 'harga_jual' => $hargaJual,
        ]);
    }

    private function lokasi(): array
    {
        return ['jarak_km' => 4.2, 'lokasi_alamat' => 'Jl. Uji 1', 'lokasi_lat' => -6.2, 'lokasi_lng' => 106.8];
    }

    public function test_berlangganan_membuat_baris_aktif_dengan_kuota_penuh(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/belanja/langganan', ['kode' => 'family']);

        $res->assertCreated();
        $res->assertJsonPath('status', 'active');
        $res->assertJsonPath('plan.kode', 'family');
        $res->assertJsonPath('sisa_kuota_diskon', 30);
        $res->assertJsonPath('sisa_kuota_ongkir', 30);
    }

    public function test_berlangganan_lagi_membatalkan_yang_lama(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $lama = $this->postJson('/api/belanja/langganan', ['kode' => 'basic'])->json('id');
        $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->assertCreated();

        // Kuota tidak boleh bisa digandakan dengan berlangganan berkali-kali.
        $this->assertSame('cancelled', Subscription::find($lama)->status);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_paket_tak_dikenal_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/belanja/langganan', ['kode' => 'platinum'])->assertStatus(422);
    }

    public function test_langganan_kedaluwarsa_tidak_ikut_terbaca_aktif(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->json('id');
        Subscription::find($id)->update(['berakhir_pada' => now()->subDay()]);

        $this->getJson('/api/belanja/langganan')->assertOk()->assertJsonPath('aktif', null);
        $this->assertSame('expired', Subscription::find($id)->status);
    }

    public function test_berhenti_langganan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->assertCreated();

        $this->deleteJson('/api/belanja/langganan')->assertOk();
        $this->getJson('/api/belanja/langganan')->assertOk()->assertJsonPath('aktif', null);
    }

    public function test_langganan_orang_lain_tidak_ikut_terbaca(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/belanja/langganan')->assertOk()->assertJsonPath('aktif', null);
    }

    /**
     * Ini inti masalahnya: angka yang ditampilkan checkout harus sama persis
     * dengan yang ditagih server.
     */
    public function test_tagihan_dengan_langganan_family_cocok_dengan_yang_ditampilkan(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);
        $produk = $this->produk(43000);

        $langganan = $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->json('id');

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'Gula', 'qty' => 1]],
            'subscription_id' => $langganan,
            ...$this->lokasi(),
        ]);

        // 43.000 barang − 1.500 diskon Family + 2.500 layanan + 0 ongkir = 44.000
        $res->assertCreated();
        $res->assertJsonPath('payment.potongan', '1500.00');
        $res->assertJsonPath('payment.ongkir', '0.00');
        $res->assertJsonPath('payment.ongkir_normal', '3500.00');
        $res->assertJsonPath('payment.jumlah', '44000.00');
    }

    public function test_kuota_langganan_berkurang_tiap_pemakaian(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $produk = $this->produk(43000);
        $id = $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->json('id');

        $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'Gula', 'qty' => 1]],
            'subscription_id' => $id,
            ...$this->lokasi(),
        ])->assertCreated();

        $langganan = Subscription::find($id);
        $this->assertSame(29, $langganan->sisa_kuota_diskon);
        $this->assertSame(29, $langganan->sisa_kuota_ongkir);
    }

    public function test_langganan_milik_orang_lain_tidak_memberi_potongan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $id = $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->json('id');

        $penyusup = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($penyusup);
        $produk = $this->produk(43000);

        $res = $this->postJson('/api/belanja/checkout', [
            'items' => [['sku' => $produk->sku, 'nama' => 'Gula', 'qty' => 1]],
            'subscription_id' => $id,
            ...$this->lokasi(),
        ]);

        $res->assertCreated();
        $res->assertJsonPath('payment.potongan', '0.00');
        $res->assertJsonPath('payment.jumlah', '49000.00');
    }

    public function test_endpoint_langganan_butuh_login(): void
    {
        $this->getJson('/api/belanja/langganan')->assertUnauthorized();
        $this->postJson('/api/belanja/langganan', ['kode' => 'family'])->assertUnauthorized();
        $this->deleteJson('/api/belanja/langganan')->assertUnauthorized();
    }
}
