<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\FreonTarif;
use App\Services\PromoAC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cek & Tambah Freon.
 *
 * Inti yang dijaga: yang ditagih di muka HANYA pemeriksaan, dan pekerjaan
 * lanjutan tidak pernah masuk tagihan tanpa persetujuan pelanggan.
 */
class FreonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaTukang', 'slug' => 'bisatukang', 'basis_harga' => 'kunjungan_jam']);
    }

    private function payload(array $ganti = []): array
    {
        return [
            'unit' => 1,
            'keluhan' => ['kurang-dingin'],
            'menyala' => true,
            'tipe' => 'split',
            'kapasitas' => '1',
            'merek' => 'daikin',
            'jenis_freon' => 'tidak-tahu',
            'tanggal' => '2026-09-15',
            'slot' => '09:00-11:00',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'metode' => 'bca',
            ...$ganti,
        ];
    }

    private function pesan(array $ganti = []): Task
    {
        $this->postJson('/api/servis-ac/freon/checkout', $this->payload($ganti))->assertCreated();

        return Task::with(['items', 'payment'])->latest('id')->first();
    }

    /** Tulis hasil pemeriksaan seperti yang dilakukan teknisi. */
    private function diagnosa(Task $task, array $pekerjaan): void
    {
        $task->update([
            'detail_layanan' => [
                ...$task->detail_layanan,
                'diagnosis' => [
                    'status_freon' => 'Tekanan di bawah standar',
                    'indikasi_kebocoran' => 'Ditemukan pada sambungan pipa',
                    'jenis_freon' => 'R32',
                    'rekomendasi' => 'Perbaiki sambungan lalu isi freon.',
                    'pekerjaan' => $pekerjaan,
                    'diperiksa_pada' => now()->toIso8601String(),
                    'keputusan' => null,
                ],
            ],
        ]);
    }

    public function test_yang_ditagih_di_muka_hanya_pemeriksaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();

        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $task->harga);
        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $task->payment->jumlah);
        $this->assertCount(1, $task->items);
        // Belum ada hasil pemeriksaan: layar hasil belum boleh menampilkan apa pun.
        $this->assertNull($task->detail_layanan['diagnosis']);
    }

    public function test_unit_tambahan_menambah_biaya_pemeriksaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan(['unit' => 3]);

        $benar = FreonTarif::BIAYA_PEMERIKSAAN + 2 * FreonTarif::BIAYA_UNIT_TAMBAHAN;
        $this->assertSame((float) $benar, (float) $task->harga);
        $this->assertCount(2, $task->items);
    }

    public function test_jenis_freon_boleh_tidak_tahu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan(['jenis_freon' => 'tidak-tahu']);

        $this->assertSame('tidak-tahu', $task->detail_layanan['jenis_freon']);
        $this->assertStringContainsString('belum diketahui', $task->deskripsi);
    }

    public function test_promo_pemeriksaan_memotong_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/freon/checkout', $this->payload(['promo_kode' => 'CEKAC20']));

        $potongan = PromoAC::VOUCHER['CEKAC20']['potongan'];
        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', $potongan);
        $res->assertJsonPath('rincian.total_ditagih', FreonTarif::BIAYA_PEMERIKSAAN - $potongan);
    }

    /**
     * Pemeriksaan harus untung pada SETIAP jumlah unit.
     *
     * Unit tambahan dijual Rp25.000. Kalau biaya nyatanya dihitung penuh per
     * unit, kunjungan banyak-unit justru merugi — padahal transport hanya
     * dibayar sekali. Uji ini yang menahan tarif dan model biaya tetap sejalan.
     */
    public function test_pemeriksaan_untung_pada_tiap_jumlah_unit(): void
    {
        $tarif = new FreonTarif;

        for ($unit = 1; $unit <= 10; $unit++) {
            $r = $tarif->pemeriksaan($unit);
            $margin = $r['total'] - $r['biaya'];

            $this->assertGreaterThan(
                0.15,
                $margin / $r['total'],
                "Pemeriksaan {$unit} unit menyisakan margin terlalu tipis.",
            );
        }
    }

    /**
     * Promo cuci AC tidak boleh menempel pada pemeriksaan freon.
     *
     * ACHEMAT2 dirancang untuk tagihan cuci beberapa unit. Pada pemeriksaan,
     * tagihan sebesar minimumnya baru tercapai di 7 unit — dan potongan
     * Rp30.000 di situ melahap seluruh marginnya.
     */
    public function test_promo_cuci_ditolak_pada_pemeriksaan_freon(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/freon/checkout', $this->payload([
            'unit' => 7,
            'promo_kode' => 'ACHEMAT2',
        ]));

        $tarif = (new FreonTarif)->pemeriksaan(7);

        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.total_ditagih', $tarif['total']);
        $this->assertStringContainsString('Cuci AC', (string) $res->json('rincian.promo_ditolak'));
    }

    public function test_pekerjaan_lanjutan_tidak_masuk_tagihan_tanpa_persetujuan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();
        $this->diagnosa($task, ['perbaikan-bocor', 'freon-r32']);

        // Hasil pemeriksaan ada, tapi belum dijawab: tagihan tidak bergerak.
        $segar = Task::with('payment')->find($task->id);
        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $segar->harga);
        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $segar->payment->jumlah);
    }

    public function test_menyetujui_rekomendasi_menambah_tagihan_dan_mengkredit_pemeriksaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();
        $this->diagnosa($task, ['perbaikan-bocor', 'freon-r32']);

        $res = $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/setujui");

        $subtotal = FreonTarif::PEKERJAAN['perbaikan-bocor']['harga'] + FreonTarif::PEKERJAAN['freon-r32']['harga'];

        $res->assertOk();
        $res->assertJsonPath('rekomendasi.subtotal', $subtotal);
        $res->assertJsonPath('rekomendasi.kredit_pemeriksaan', FreonTarif::BIAYA_PEMERIKSAAN);
        $res->assertJsonPath('rekomendasi.total', $subtotal - FreonTarif::BIAYA_PEMERIKSAAN);

        $segar = Task::with(['items', 'payment'])->find($task->id);
        $this->assertSame('disetujui', $segar->detail_layanan['diagnosis']['keputusan']);
        // Baris pemeriksaan + dua pekerjaan lanjutan.
        $this->assertCount(3, $segar->items);
        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $segar->payment->potongan);
    }

    public function test_harga_pekerjaan_dihitung_server_bukan_dari_klien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();
        $this->diagnosa($task, ['freon-r32']);

        // Klien mengirim harga karangan; tidak ada jalan masuknya.
        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/setujui", [
            'total' => 1000,
            'pekerjaan' => ['freon-r22'],
        ])->assertOk();

        $segar = Task::find($task->id);
        $this->assertSame((float) FreonTarif::PEKERJAAN['freon-r32']['harga'], (float) $segar->harga);
    }

    public function test_menolak_menyisakan_tagihan_pemeriksaan_saja(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();
        $this->diagnosa($task, ['perbaikan-bocor', 'freon-r32']);

        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/tolak")->assertOk();

        $segar = Task::with('payment')->find($task->id);
        $this->assertSame('ditolak', $segar->detail_layanan['diagnosis']['keputusan']);
        $this->assertSame((float) FreonTarif::BIAYA_PEMERIKSAAN, (float) $segar->harga);
    }

    public function test_tidak_bisa_menjawab_dua_kali(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();
        $this->diagnosa($task, ['freon-r32']);

        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/setujui")->assertOk();
        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/tolak")->assertStatus(422);
    }

    public function test_tidak_bisa_menyetujui_sebelum_ada_hasil_pemeriksaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $task = $this->pesan();

        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/setujui")->assertStatus(422);
    }

    public function test_pesanan_orang_lain_tidak_bisa_dijawab(): void
    {
        $pemilik = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($pemilik);
        $task = $this->pesan();
        $this->diagnosa($task, ['freon-r32']);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson("/api/servis-ac/freon/{$task->nomor_invoice}/setujui")->assertNotFound();
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/servis-ac/freon/checkout', $this->payload())->assertUnauthorized();
    }
}
