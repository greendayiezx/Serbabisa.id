<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Penawaran;
use App\Models\Task;
use App\Models\User;
use App\Services\PenyusunPenawaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Penawaran BisaBersih Kantor.
 *
 * Inti yang dijaga: tiga paket dihitung dari data (bukan diketik), dan
 * persetujuan selalu menyebut paket mana — penawaran berisi tiga pilihan.
 */
class PenawaranTest extends TestCase
{
    use RefreshDatabase;

    private function permintaan(User $customer): Task
    {
        Category::firstOrCreate(
            ['slug' => 'bisabersih'],
            ['nama' => 'BisaBersih', 'basis_harga' => 'durasi'],
        );

        return Task::create([
            'customer_id' => $customer->id,
            'tipe' => 'custom',
            'judul' => 'Permintaan Penawaran — Bersih Kantor (PT Uji)',
            'deskripsi' => implode("\n", [
                'Nama perusahaan: PT Uji',
                'PIC: Rina',
                'WhatsApp: 08123',
                'Jenis kantor: Medium Office (51 – 150 m²)',
                'Luas area: 140 m²',
                'Jumlah lantai: 2',
                'Ruang meeting: 2',
                'Workstation: 20',
                'Toilet: 2',
                'Pantry: 1',
                'Frekuensi: 2x / Minggu',
            ]),
            'status' => 'pending',
            'lokasi_alamat' => 'Gedung Uji',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
        ]);
    }

    private function susun(User $customer): Penawaran
    {
        $task = $this->permintaan($customer);

        return app(PenyusunPenawaran::class)->dariTask($task, [
            'nama_perusahaan' => 'PT Uji',
            'nama_pic' => 'Rina',
            'telepon_pic' => '08123',
            'jenis_kantor' => 'sedang',
            'frekuensi' => '2x-minggu',
            'workstation' => 20,
            'ruang_meeting' => 2,
            'toilet' => 2,
            'pantry' => 1,
        ]);
    }

    public function test_penawaran_punya_tiga_paket_berjenjang(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);

        $this->assertCount(3, $p->paket);

        $harga = $p->paket->pluck('harga_per_kunjungan')->all();
        // Essential < Professional < Executive — jenjangnya harus naik.
        $this->assertSame($harga, array_values(array_unique($harga)));
        $this->assertTrue($harga[0] < $harga[1] && $harga[1] < $harga[2]);

        // Harga bulanan = per kunjungan x jumlah kunjungan, bukan angka lepas.
        foreach ($p->paket as $k) {
            $this->assertSame($k->harga_per_kunjungan * $k->kunjungan_per_bulan, $k->harga_bulanan);
        }
    }

    public function test_nomor_penawaran_berurutan(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->assertSame('OFF-000001', $this->susun($customer)->nomor);
        $this->assertSame('OFF-000002', $this->susun($customer)->nomor);
    }

    public function test_pelanggan_bisa_melihat_penawarannya(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        Sanctum::actingAs($customer);

        $this->getJson("/api/penawaran/{$p->nomor}")
            ->assertOk()
            ->assertJsonPath('nomor', $p->nomor)
            ->assertJsonCount(3, 'paket')
            ->assertJsonPath('status', 'dikirim');
    }

    public function test_penawaran_orang_lain_tidak_bisa_dilihat(): void
    {
        $p = $this->susun(User::factory()->create(['role' => 'customer']));

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/penawaran/{$p->nomor}")->assertNotFound();
    }

    public function test_setuju_harus_menyebut_paket(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/penawaran/{$p->nomor}/setujui", [])->assertStatus(422);
    }

    public function test_paket_dari_penawaran_lain_ditolak(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $satu = $this->susun($customer);
        $dua = $this->susun($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/penawaran/{$satu->nomor}/setujui", [
            'paket_id' => $dua->paket->first()->id,
        ])->assertStatus(422);

        $this->assertSame('dikirim', $satu->fresh()->status);
    }

    public function test_menyetujui_mengunci_paket_terpilih(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        $paket = $p->paket->firstWhere('kode', 'professional');
        Sanctum::actingAs($customer);

        $this->postJson("/api/penawaran/{$p->nomor}/setujui", ['paket_id' => $paket->id])
            ->assertOk()
            ->assertJsonPath('status', 'disetujui')
            ->assertJsonPath('paket_dipilih_id', $paket->id);

        $this->assertNotNull($p->fresh()->disetujui_pada);

        // Menyetujui dua kali tidak boleh mengganti paketnya diam-diam.
        $this->postJson("/api/penawaran/{$p->nomor}/setujui", [
            'paket_id' => $p->paket->firstWhere('kode', 'basic')->id,
        ])->assertStatus(422);
        $this->assertSame($paket->id, $p->fresh()->paket_dipilih_id);
    }

    public function test_penawaran_kedaluwarsa_tidak_bisa_disetujui(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        $p->update(['berlaku_sampai' => now()->subDay()->toDateString()]);
        Sanctum::actingAs($customer);

        $this->getJson("/api/penawaran/{$p->nomor}")
            ->assertOk()
            ->assertJsonPath('status', 'kedaluwarsa');

        $this->postJson("/api/penawaran/{$p->nomor}/setujui", ['paket_id' => $p->paket->first()->id])
            ->assertStatus(422);
    }

    public function test_ajukan_revisi_tercatat(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/penawaran/{$p->nomor}/revisi", [
            'permintaan' => ['ubah-frekuensi', 'tambah-supervisor'],
            'catatan' => 'Jadi 3x seminggu ya.',
        ])->assertOk()->assertJsonPath('status', 'revisi');

        $revisi = $p->fresh('revisi')->revisi->first();
        $this->assertSame(['ubah-frekuensi', 'tambah-supervisor'], $revisi->permintaan);
        $this->assertSame('Jadi 3x seminggu ya.', $revisi->catatan);
    }

    public function test_permintaan_revisi_asing_ditolak(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/penawaran/{$p->nomor}/revisi", ['permintaan' => ['gratiskan-semua']])
            ->assertStatus(422);
    }

    public function test_pdf_bisa_diunduh(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $p = $this->susun($customer);
        Sanctum::actingAs($customer);

        $res = $this->get("/api/penawaran/{$p->nomor}/pdf");

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_luas_sebenarnya_menang_atas_luas_acuan_jenis(): void
    {
        // Kantor 'besar' berarti "di atas 150 m²" tanpa batas atas. Kalau
        // pelanggan menyebut 820 m², dokumen tidak boleh menagih seperti 250 m².
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->permintaan($customer);

        $acuan = app(PenyusunPenawaran::class)->dariTask($task, [
            'jenis_kantor' => 'besar', 'frekuensi' => 'sekali',
            'workstation' => 0, 'ruang_meeting' => 0, 'toilet' => 0, 'pantry' => 0,
        ]);
        $nyata = app(PenyusunPenawaran::class)->dariTask($task, [
            'jenis_kantor' => 'besar', 'frekuensi' => 'sekali', 'luas_m2' => 820,
            'workstation' => 0, 'ruang_meeting' => 0, 'toilet' => 0, 'pantry' => 0,
        ]);

        $hargaAcuan = $acuan->paket->firstWhere('kode', 'basic')->harga_per_kunjungan;
        $hargaNyata = $nyata->paket->firstWhere('kode', 'basic')->harga_per_kunjungan;

        // 250 m² x Rp1.200 = 300.000 ; 820 m² x Rp1.200 = 984.000
        $this->assertSame(300000, $hargaAcuan);
        $this->assertSame(984000, $hargaNyata);
        $this->assertStringContainsString('820 m²', $nyata->ringkasan);
    }

    public function test_butuh_login(): void
    {
        $p = $this->susun(User::factory()->create(['role' => 'customer']));

        $this->getJson("/api/penawaran/{$p->nomor}")->assertUnauthorized();
        $this->postJson("/api/penawaran/{$p->nomor}/setujui", ['paket_id' => 1])->assertUnauthorized();
    }
}
