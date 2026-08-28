<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Invoice PDF pesanan.
 *
 * Yang dijaga di sini bukan tampilan dokumennya, melainkan siapa yang boleh
 * membukanya: peramban tidak bisa membawa header Authorization saat berpindah
 * halaman, jadi aksesnya bersandar pada tanda tangan URL — dan tanda tangan itu
 * harus benar-benar diperiksa.
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function pesanan(User $customer): Task
    {
        Category::firstOrCreate(
            ['slug' => 'bisabersih'],
            ['nama' => 'BisaBersih', 'basis_harga' => 'durasi'],
        );

        $task = Task::create([
            'nomor_invoice' => 'SBB-260901-INV0001',
            'customer_id' => $customer->id,
            'category_id' => Category::first()->id,
            'tipe' => 'fixed',
            'judul' => 'BisaBersih — Bersih Kantor (Medium Office)',
            'deskripsi' => 'Medium Office (~150 m²) · paket Basic',
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => 'Gedung Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'harga' => 225000,
            'dijadwalkan_pada' => now()->addDays(3),
        ]);

        $task->items()->create([
            'nama' => 'Bersih Kantor · Basic · Medium Office',
            'kategori' => 'layanan',
            'satuan' => 'kunjungan',
            'harga_satuan' => 250000,
            'qty' => 1,
            'subtotal' => 250000,
        ]);

        $task->payment()->create([
            'jumlah' => 225000,
            'subtotal_barang' => 250000,
            'ongkir' => 0,
            'ongkir_normal' => 0,
            'potongan' => 25000,
            'cashback' => 0,
            'service_fee' => 0,
            'komisi_platform' => 60000,
            'status' => 'pending',
            'metode' => 'bca',
        ]);

        return $task;
    }

    public function test_tautan_bertanda_tangan_membuka_pdf(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->pesanan($customer);
        Sanctum::actingAs($customer);

        $url = $this->getJson('/api/invoice/'.$task->nomor_invoice.'/tautan')
            ->assertOk()
            ->json('url');

        // Relatif, bukan absolut — APP_URL bisa beda dari alamat server nyata.
        $this->assertStringStartsWith('/api/berkas/invoice/', $url);
        $this->assertStringContainsString('signature=', $url);

        auth()->forgetGuards();
        $res = $this->get($url);

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        // inline, bukan attachment: peramban menampilkannya, tidak mengunduh.
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_nomor_potongan_juga_diterima(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->pesanan($customer);
        Sanctum::actingAs($customer);

        $this->getJson('/api/invoice/INV0001/tautan')->assertOk();
    }

    public function test_tanpa_tanda_tangan_ditolak(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->pesanan($customer);

        auth()->forgetGuards();
        $this->get('/api/berkas/invoice/'.$task->id)->assertForbidden();
    }

    public function test_tanda_tangan_kedaluwarsa_ditolak(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->pesanan($customer);
        Sanctum::actingAs($customer);

        $url = $this->getJson('/api/invoice/'.$task->nomor_invoice.'/tautan')->json('url');

        auth()->forgetGuards();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_invoice_orang_lain_tidak_bisa_diminta(): void
    {
        $pemilik = User::factory()->create(['role' => 'customer']);
        $task = $this->pesanan($pemilik);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/invoice/'.$task->nomor_invoice.'/tautan')->assertNotFound();
    }

    public function test_butuh_login(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->pesanan($customer);

        $this->getJson('/api/invoice/'.$task->nomor_invoice.'/tautan')->assertUnauthorized();
    }
}
