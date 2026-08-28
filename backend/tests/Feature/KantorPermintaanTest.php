<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\PenyusunPenawaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Permintaan penawaran BisaBersih Kantor.
 *
 * Inti yang dijaga: tiap permintaan dapat NOMOR sendiri, dan spesifikasinya
 * tersimpan sebagai data — bukan hanya kalimat di deskripsi.
 */
class KantorPermintaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaBersih', 'slug' => 'bisabersih', 'basis_harga' => 'durasi']);
    }

    private function payload(array $ganti = []): array
    {
        return [
            'nama_perusahaan' => 'PT Uji Sejahtera',
            'nama_pic' => 'Rina Hartati',
            'telepon_pic' => '081234567890',
            'jenis_kantor' => 'besar',
            'paket' => 'professional',
            'frekuensi' => '3x-minggu',
            'luas_m2' => 820,
            'jumlah_lantai' => 3,
            'workstation' => 60,
            'ruang_meeting' => 4,
            'toilet' => 6,
            'pantry' => 2,
            'add_on' => ['karpet'],
            'catatan' => 'Ada ruang server.',
            'estimasi' => 527850,
            'lokasi_alamat' => 'Gedung Sudirman Tower',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_permintaan_mendapat_nomor_berurutan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())
            ->assertCreated()
            ->assertJsonPath('nomor', 'REQ-000001');

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())
            ->assertCreated()
            ->assertJsonPath('nomor', 'REQ-000002');
    }

    public function test_spesifikasi_tersimpan_sebagai_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        $spek = Task::first()->detail_layanan;
        $this->assertSame(820, $spek['luas_m2']);
        $this->assertSame(3, $spek['jumlah_lantai']);
        $this->assertSame(6, $spek['toilet']);
        $this->assertSame('besar', $spek['jenis_kantor']);
        $this->assertSame('3x-minggu', $spek['frekuensi']);
        $this->assertSame(['karpet'], $spek['add_on']);
        $this->assertTrue($spek['permintaan_penawaran']);
    }

    public function test_kantor_besar_boleh_minta_penawaran(): void
    {
        // Kebalikan dari checkout langsung: justru kantor besar yang butuh ini.
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload(['jenis_kantor' => 'besar']))
            ->assertCreated();
    }

    public function test_data_pic_wajib(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload([
            'nama_perusahaan' => '', 'nama_pic' => '', 'telepon_pic' => '',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['nama_perusahaan', 'nama_pic', 'telepon_pic']);
    }

    public function test_estimasi_masuk_sebagai_budget_bukan_harga(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        $task = Task::first();
        // Harga tetap kosong: belum ada yang ditagih sampai penawaran disetujui.
        $this->assertNull($task->harga);
        $this->assertSame('527850.00', $task->budget);
    }

    public function test_status_permintaan_bisa_dilihat(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        $this->getJson('/api/bersih/kantor/permintaan/REQ-000001')
            ->assertOk()
            ->assertJsonPath('nama_perusahaan', 'PT Uji Sejahtera')
            ->assertJsonPath('frekuensi', '3x / Minggu')
            // Belum ada penawaran yang disusun tim.
            ->assertJsonPath('nomor_penawaran', null);
    }

    public function test_nomor_penawaran_muncul_setelah_disusun(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        $task = Task::first();
        app(PenyusunPenawaran::class)->dariTask($task, $task->detail_layanan);

        $this->getJson('/api/bersih/kantor/permintaan/REQ-000001')
            ->assertOk()
            ->assertJsonPath('nomor_penawaran', 'OFF-000001');
    }

    public function test_permintaan_orang_lain_tidak_bisa_dilihat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson('/api/bersih/kantor/permintaan/REQ-000001')->assertNotFound();
    }

    /** PNG 1x1 sungguhan, sebagai pengganti coretan dari kanvas. */
    private function pngDataUrl(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function test_tanda_tangan_tersimpan_sebagai_berkas(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload([
            'tanda_tangan' => $this->pngDataUrl(),
        ]))->assertCreated()->assertJsonPath('bertanda_tangan', true);

        $task = Task::first();
        $jalur = $task->detail_layanan['tanda_tangan'];
        Storage::disk('public')->assertExists($jalur);
        // Disimpan sebagai berkas PNG, bukan teks base64 di database.
        $this->assertStringStartsWith('tanda-tangan/', $jalur);
        $this->assertNotNull($task->detail_layanan['ditandatangani_pada']);
        $this->assertSame('Rina Hartati', $task->detail_layanan['ditandatangani_oleh']);
    }

    public function test_data_url_bukan_png_diabaikan(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Isi yang menyamar sebagai PNG tidak boleh ditulis ke disk.
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload([
            'tanda_tangan' => 'data:image/png;base64,'.base64_encode('<script>alert(1)</script>'),
        ]))->assertCreated()->assertJsonPath('bertanda_tangan', false);

        $this->assertArrayNotHasKey('tanda_tangan', Task::first()->detail_layanan);
    }

    public function test_permintaan_tanpa_tanda_tangan_tetap_diterima(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())
            ->assertCreated()
            ->assertJsonPath('bertanda_tangan', false);
    }

    public function test_pdf_permintaan_bisa_diunduh(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload([
            'tanda_tangan' => $this->pngDataUrl(),
        ]))->assertCreated();

        $res = $this->get('/api/bersih/kantor/permintaan/REQ-000001/pdf');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_pdf_permintaan_orang_lain_ditolak(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->get('/api/bersih/kantor/permintaan/REQ-000001/pdf')->assertNotFound();
    }

    public function test_tautan_pdf_bertanda_tangan_bisa_dibuka_tanpa_token(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload([
            'tanda_tangan' => $this->pngDataUrl(),
        ]))->assertCreated();

        $url = $this->getJson('/api/bersih/kantor/permintaan/REQ-000001/tautan-pdf')
            ->assertOk()->json('url');

        // Relatif, bukan absolut — APP_URL bisa beda dari alamat server nyata.
        $this->assertStringStartsWith('/api/berkas/permintaan/', $url);
        $this->assertStringContainsString('signature=', $url);

        // Dibuka TANPA sesi: tanda tangan URL yang jadi bukti aksesnya.
        auth()->forgetGuards();
        $res = $this->get($url);
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        // inline, bukan attachment: peramban menampilkannya, tidak mengunduh.
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_tautan_tanpa_tanda_tangan_ditolak(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();
        $id = Task::first()->id;

        auth()->forgetGuards();
        $this->get('/api/berkas/permintaan/'.$id)->assertForbidden();
    }

    public function test_tautan_kedaluwarsa_ditolak(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertCreated();
        $url = $this->getJson('/api/bersih/kantor/permintaan/REQ-000001/tautan-pdf')->json('url');

        auth()->forgetGuards();
        // Lewat masa berlakunya 60 menit.
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/bersih/kantor/permintaan', $this->payload())->assertUnauthorized();
    }
}
