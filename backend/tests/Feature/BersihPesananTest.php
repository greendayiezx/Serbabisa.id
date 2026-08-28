<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MitraProfile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Status pesanan BisaBersih dan aksi "terima" dari sisi cleaner.
 *
 * Inti yang dijaga: halaman status hanya berpindah ke tampilan "diterima"
 * kalau STATUS DI DATABASE memang sudah berubah — bukan karena browser
 * menunggu cukup lama.
 */
class BersihPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBersih', 'slug' => 'bisabersih', 'basis_harga' => 'durasi']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    /** Nomor telepon dibuat unik per mitra: kolomnya unique di tabel users. */
    private int $urutanMitra = 0;

    private function mitra(array $ganti = []): MitraProfile
    {
        $this->urutanMitra++;
        $user = User::factory()->create([
            'role' => 'mitra',
            'phone' => '0813999000'.str_pad((string) $this->urutanMitra, 2, '0', STR_PAD_LEFT),
        ]);

        return MitraProfile::create([
            'user_id' => $user->id,
            'no_ktp' => '3271'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
            'foto_ktp' => 'ktp.jpg',
            'gender' => 'wanita',
            'rating_avg' => 0,
            'rating_count' => 0,
            ...$ganti,
        ]);
    }

    private function pesanan(User $customer, array $ganti = []): Task
    {
        return Task::create([
            'nomor_invoice' => 'SBB-260901-ABC1234',
            'customer_id' => $customer->id,
            'category_id' => Category::first()->id,
            'tipe' => 'fixed',
            'judul' => 'BisaBersih — Bersih Rumah',
            'deskripsi' => 'area: Dapur',
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => 'Jl. Uji 1',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'harga' => 170000,
            'jumlah_helper' => 1,
            'dijadwalkan_pada' => '2026-09-01 10:00:00',
            'detail_layanan' => [
                'durasi_jam' => 3,
                'jumlah_cleaner' => 1,
                'nama_level' => 'Pemula',
                'area' => ['Dapur'],
            ],
            ...$ganti,
        ]);
    }

    public function test_selama_belum_ada_yang_menerima_status_tetap_menunggu(): void
    {
        $customer = $this->customer();
        $this->pesanan($customer);
        Sanctum::actingAs($customer);

        $this->getJson('/api/bersih/pesanan/ABC1234')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('diterima', false)
            ->assertJsonPath('cleaner', null);
    }

    public function test_nomor_potongan_invoice_bisa_dipakai(): void
    {
        $customer = $this->customer();
        $this->pesanan($customer);
        Sanctum::actingAs($customer);

        // URL memakai potongan belakang; nomor penuh juga harus diterima.
        $this->getJson('/api/bersih/pesanan/ABC1234')->assertOk();
        $this->getJson('/api/bersih/pesanan/SBB-260901-ABC1234')->assertOk();
    }

    public function test_pesanan_orang_lain_tidak_bisa_dilihat(): void
    {
        $this->pesanan($this->customer());
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/bersih/pesanan/ABC1234')->assertNotFound();
    }

    public function test_cleaner_menerima_lalu_status_berubah(): void
    {
        $customer = $this->customer();
        $task = $this->pesanan($customer);
        $profil = $this->mitra();

        Sanctum::actingAs($profil->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')
            ->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('diterima', true)
            ->assertJsonPath('cleaner.id', (string) $profil->user_id);

        $task->refresh();
        $this->assertSame('accepted', $task->status);
        $this->assertSame($profil->user_id, $task->mitra_id);
        $this->assertNotNull($task->accepted_at);
    }

    public function test_pesanan_yang_sudah_diambil_tidak_bisa_diambil_lagi(): void
    {
        $this->pesanan($this->customer());
        $pertama = $this->mitra();
        $kedua = $this->mitra();

        Sanctum::actingAs($pertama->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertOk();

        Sanctum::actingAs($kedua->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertStatus(422);
    }

    public function test_cleaner_yang_ditunjuk_customer_tidak_bisa_diambil_orang_lain(): void
    {
        $customer = $this->customer();
        $ditunjuk = $this->mitra();
        $penyerobot = $this->mitra();

        $this->pesanan($customer, ['mitra_id' => $ditunjuk->user_id]);

        Sanctum::actingAs($penyerobot->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertStatus(403);

        Sanctum::actingAs($ditunjuk->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertOk();
    }

    public function test_customer_tidak_bisa_menerima_pesanannya_sendiri(): void
    {
        $customer = $this->customer();
        $this->pesanan($customer);

        Sanctum::actingAs($customer);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertStatus(403);
    }

    public function test_telepon_cleaner_baru_keluar_setelah_diterima(): void
    {
        $customer = $this->customer();
        $profil = $this->mitra();
        $this->pesanan($customer, ['mitra_id' => $profil->user_id]);

        // Masih menunggu: nomornya belum jadi urusan customer.
        Sanctum::actingAs($customer);
        $this->getJson('/api/bersih/pesanan/ABC1234')
            ->assertOk()
            ->assertJsonPath('diterima', false)
            ->assertJsonMissingPath('cleaner.telepon');

        Sanctum::actingAs($profil->user);
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertOk();

        Sanctum::actingAs($customer);
        $this->getJson('/api/bersih/pesanan/ABC1234')
            ->assertOk()
            ->assertJsonPath('cleaner.telepon', '081399900001');
    }

    public function test_rating_nol_dikirim_apa_adanya(): void
    {
        $customer = $this->customer();
        $profil = $this->mitra();
        $this->pesanan($customer, ['mitra_id' => $profil->user_id, 'status' => 'accepted']);

        Sanctum::actingAs($customer);
        $this->getJson('/api/bersih/pesanan/ABC1234')
            ->assertOk()
            // Nol berarti belum ada ulasan; halaman yang mengubahnya jadi "-".
            ->assertJsonPath('cleaner.rating', 0)
            ->assertJsonPath('cleaner.jumlah_ulasan', 0)
            ->assertJsonPath('cleaner.order_selesai', 0);
    }

    public function test_butuh_login(): void
    {
        $this->pesanan($this->customer());

        $this->getJson('/api/bersih/pesanan/ABC1234')->assertUnauthorized();
        $this->postJson('/api/bersih/pesanan/ABC1234/terima')->assertUnauthorized();
    }
}
