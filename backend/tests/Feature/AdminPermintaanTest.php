<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tahap permintaan penawaran dari sisi tim.
 *
 * Inti yang dijaga: tahap hanya bisa MAJU, dan hanya admin yang boleh
 * menggerakkannya — layar status pelanggan bergantung padanya.
 */
class AdminPermintaanTest extends TestCase
{
    use RefreshDatabase;

    private function permintaan(?User $customer = null): Task
    {
        Category::firstOrCreate(
            ['slug' => 'bisabersih'],
            ['nama' => 'BisaBersih', 'basis_harga' => 'durasi'],
        );

        $customer ??= User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $this->postJson('/api/bersih/kantor/permintaan', [
            'nama_perusahaan' => 'PT Uji',
            'nama_pic' => 'Rina',
            'telepon_pic' => '081234567890',
            'jenis_kantor' => 'sedang',
            'frekuensi' => '2x-minggu',
            'lokasi_alamat' => 'Gedung Uji',
        ])->assertCreated();

        return Task::where('nomor_invoice', 'like', 'REQ-%')->latest('id')->first();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_permintaan_baru_mulai_dari_tahap_ditinjau(): void
    {
        $this->permintaan();
        $this->admin();

        $this->getJson('/api/admin/permintaan')
            ->assertOk()
            ->assertJsonCount(1, 'permintaan')
            ->assertJsonPath('permintaan.0.tahap', 'ditinjau')
            ->assertJsonPath('jumlah.ditinjau', 1);
    }

    public function test_admin_memajukan_tahap_dan_pelanggan_melihatnya(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->permintaan($customer);
        $this->admin();

        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'dihubungi'])
            ->assertOk()
            ->assertJsonPath('tahap', 'dihubungi');

        // Layar status pelanggan ikut berubah.
        Sanctum::actingAs($customer);
        $this->getJson('/api/bersih/kantor/permintaan/REQ-000001')
            ->assertOk()
            ->assertJsonPath('tahap', 'dihubungi');
    }

    public function test_tahap_tidak_bisa_mundur(): void
    {
        $this->permintaan();
        $this->admin();

        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'survei'])->assertOk();
        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'dihubungi'])
            ->assertStatus(422);

        $this->getJson('/api/admin/permintaan/REQ-000001')->assertJsonPath('tahap', 'survei');
    }

    public function test_tahap_yang_sama_juga_ditolak(): void
    {
        $this->permintaan();
        $this->admin();

        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'ditinjau'])
            ->assertStatus(422);
    }

    public function test_riwayat_tahap_tercatat_beserta_pelakunya(): void
    {
        $this->permintaan();
        $admin = $this->admin();

        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', [
            'tahap' => 'dihubungi',
            'catatan' => 'PIC dihubungi lewat WA.',
        ])->assertOk();

        $riwayat = Task::first()->detail_layanan['riwayat_tahap'];
        $this->assertCount(1, $riwayat);
        $this->assertSame('dihubungi', $riwayat[0]['tahap']);
        $this->assertSame($admin->name, $riwayat[0]['oleh']);
        $this->assertSame('PIC dihubungi lewat WA.', $riwayat[0]['catatan']);
    }

    public function test_tahap_asing_ditolak(): void
    {
        $this->permintaan();
        $this->admin();

        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'selesai'])
            ->assertStatus(422);
    }

    public function test_bukan_admin_tidak_boleh_menggerakkan_tahap(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->permintaan($customer);

        // Masih sebagai customer.
        $this->getJson('/api/admin/permintaan')->assertForbidden();
        $this->patchJson('/api/admin/permintaan/REQ-000001/tahap', ['tahap' => 'dihubungi'])
            ->assertForbidden();
    }
}
