<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Penawaran pemasangan AC.
 *
 * Persetujuan di sini mengikat harga dan lingkup pekerjaan, jadi yang dijaga
 * bukan tampilannya melainkan syaratnya: tidak bisa terjadi tanpa pernyataan
 * setuju, tidak bisa dua kali, tidak bisa setelah lewat masa berlaku, dan
 * angkanya tidak boleh datang dari klien.
 */
class PenawaranACTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaTukang', 'slug' => 'bisatukang', 'basis_harga' => 'kunjungan_jam']);
        $this->user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($this->user);
    }

    private function permintaan(array $penawaran = []): Task
    {
        return Task::create([
            'nomor_invoice' => 'REQ-260829-ABC123',
            'customer_id' => $this->user->id,
            'category_id' => Category::where('slug', 'bisatukang')->value('id'),
            'tipe' => 'custom',
            'judul' => 'Permintaan Penawaran — Pasang/Pindah AC',
            'deskripsi' => 'uji',
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'detail_layanan' => [
                'layanan' => 'pasang-ac',
                'permintaan_penawaran' => true,
                'kapasitas' => '1',
                ...($penawaran ? ['penawaran' => $penawaran] : []),
            ],
        ]);
    }

    private function penawaranContoh(array $ganti = []): array
    {
        return [
            'nomor' => 'Q-AC-260829-AB',
            'berlaku_sampai' => now()->addDays(7)->toDateString(),
            'layanan' => 'Pasang AC Split 1 PK',
            'termasuk' => ['Pemasangan indoor & outdoor'],
            'tidak_termasuk' => ['Bobok tembok permanen'],
            'baris' => [
                ['nama' => 'Jasa pemasangan', 'kategori' => 'layanan', 'satuan' => 'paket', 'nilai' => 350_000],
                ['nama' => 'Pipa AC 3 meter', 'kategori' => 'material', 'satuan' => 'meter', 'nilai' => 210_000],
            ],
            'subtotal' => 560_000,
            'potongan' => 50_000,
            'total' => 510_000,
            'deposit' => 200_000,
            'jadwal' => [
                ['id' => 'slot-1', 'tanggal' => now()->addDay()->toDateString(), 'label' => 'Besok', 'jam' => '09:00-12:00'],
            ],
            'keputusan' => null,
            ...$ganti,
        ];
    }

    private function isiSetuju(array $ganti = []): array
    {
        return [
            'setuju' => true,
            'nama_penyetuju' => 'Budi Santoso',
            'jadwal_id' => 'slot-1',
            ...$ganti,
        ];
    }

    public function test_penawaran_belum_terbit_tidak_bisa_dibuka(): void
    {
        $task = $this->permintaan();

        $this->getJson("/api/servis-ac/penawaran/{$task->nomor_invoice}")->assertNotFound();
    }

    public function test_penawaran_bisa_dibaca(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $res = $this->getJson("/api/servis-ac/penawaran/{$task->nomor_invoice}");

        $res->assertOk();
        $res->assertJsonPath('penawaran.total', 510_000);
        $res->assertJsonPath('penawaran.kedaluwarsa', false);
    }

    /**
     * Membuka halaman tidak boleh menyetujui apa pun.
     *
     * Tanpa pernyataan setuju yang eksplisit, satu ketukan salah sudah cukup
     * mengikat harga dan lingkup kerja.
     */
    public function test_tanpa_pernyataan_setuju_ditolak(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju(['setuju' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('setuju');

        $this->assertNull($task->fresh()->harga);
    }

    public function test_nama_penyetuju_wajib(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju(['nama_penyetuju' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama_penyetuju');
    }

    /**
     * Angkanya diambil dari penawaran tersimpan, bukan dari badan permintaan.
     *
     * Kalau total ikut dikirim klien, siapa pun yang bisa memanggil API ini
     * bisa menyetujui pekerjaan dengan harga karangannya sendiri.
     */
    public function test_total_diambil_dari_penawaran_bukan_dari_klien(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson(
            "/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui",
            $this->isiSetuju(['total' => 1000, 'harga' => 1000]),
        )->assertOk();

        $this->assertSame(510000.0, (float) $task->fresh()->harga);
        $this->assertSame(510000.0, (float) $task->fresh()->payment->jumlah);
    }

    public function test_setuju_membuat_nomor_pekerjaan_dan_item(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $res = $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju());

        $res->assertOk();
        $this->assertStringStartsWith('JOB-', $res->json('nomor_pekerjaan'));

        $segar = $task->fresh()->load('items');
        $this->assertCount(2, $segar->items);
        $this->assertSame('disetujui', $segar->detail_layanan['penawaran']['keputusan']);
        $this->assertSame('Budi Santoso', $segar->detail_layanan['penawaran']['nama_penyetuju']);
        // Jadwal yang dipilih ikut tercatat pada pesanannya, bukan hanya di
        // dalam penawaran — di situlah teknisi membacanya.
        $this->assertNotNull($segar->dijadwalkan_pada);
    }

    public function test_tidak_bisa_disetujui_dua_kali(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju())->assertOk();
        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju())->assertStatus(422);

        // Item pekerjaannya tidak boleh berlipat karena tombol ditekan dua kali.
        $this->assertCount(2, $task->fresh()->load('items')->items);
    }

    public function test_penawaran_kedaluwarsa_tidak_bisa_disetujui(): void
    {
        $task = $this->permintaan($this->penawaranContoh([
            'berlaku_sampai' => now()->subDay()->toDateString(),
        ]));

        $this->getJson("/api/servis-ac/penawaran/{$task->nomor_invoice}")
            ->assertJsonPath('penawaran.kedaluwarsa', true);

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju())
            ->assertStatus(422);

        $this->assertNull($task->fresh()->harga);
    }

    public function test_jadwal_asing_ditolak(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson(
            "/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui",
            $this->isiSetuju(['jadwal_id' => 'slot-karangan']),
        )->assertStatus(422);
    }

    public function test_revisi_tercatat_tanpa_mengubah_harga(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $res = $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/revisi", [
            'kategori' => ['material', 'panjang-pipa'],
            'alasan' => 'menyesuaikan-anggaran',
            'catatan' => 'Pipa dikurangi jadi 2 meter dan jadwal digeser ke Selasa sore.',
            'per_item' => [
                ['item' => 'Pipa AC 3 meter', 'permintaan' => 'Ubah jadi 2 meter'],
            ],
        ]);

        $res->assertOk();
        $res->assertJsonPath('jumlah_revisi', 1);

        $segar = $task->fresh();
        $this->assertSame('revisi', $segar->detail_layanan['penawaran']['keputusan']);
        // Harga tetap milik penawaran; pelanggan mengajukan, bukan menetapkan.
        $this->assertSame(510_000, $segar->detail_layanan['penawaran']['total']);
        $this->assertNull($segar->harga);
    }

    public function test_revisi_butuh_kategori_dan_catatan(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/revisi", ['kategori' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kategori', 'catatan']);
    }

    public function test_penawaran_yang_sudah_disetujui_tidak_bisa_direvisi(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju())->assertOk();

        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/revisi", [
            'kategori' => ['material'],
            'catatan' => 'Berubah pikiran.',
        ])->assertStatus(422);
    }

    public function test_penawaran_orang_lain_tidak_bisa_dibuka(): void
    {
        $task = $this->permintaan($this->penawaranContoh());

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson("/api/servis-ac/penawaran/{$task->nomor_invoice}")->assertNotFound();
        $this->postJson("/api/servis-ac/penawaran/{$task->nomor_invoice}/setujui", $this->isiSetuju())
            ->assertNotFound();
    }
}
