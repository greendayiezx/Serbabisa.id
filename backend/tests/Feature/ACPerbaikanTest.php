<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\PerbaikanTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Perbaikan & Pasang AC.
 *
 * Yang dijaga ada dua, dan keduanya soal uang:
 *
 * 1. Perbaikan hanya menagih kunjungan diagnosisnya — harga perbaikan tidak
 *    pernah muncul sebelum teknisi melihat unitnya.
 * 2. Pasang/pindah tidak menagih apa pun. Ia permintaan penawaran, dan tidak
 *    boleh punya pembayaran maupun harga.
 */
class ACPerbaikanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaTukang', 'slug' => 'bisatukang', 'basis_harga' => 'kunjungan_jam']);
    }

    private function payloadPerbaikan(array $ganti = []): array
    {
        return [
            'unit' => 1,
            'keluhan' => ['tidak-dingin'],
            'menyala' => true,
            'mulai_terjadi' => '1-7-hari',
            'merek' => 'daikin',
            'tipe' => 'split',
            'kapasitas' => '1',
            'tanggal' => '2026-09-20',
            'slot' => '09:00-11:00',
            'nama_penerima' => 'Budi Uji',
            'telepon_penerima' => '081200004444',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'metode' => 'bca',
            ...$ganti,
        ];
    }

    private function payloadPasang(array $ganti = []): array
    {
        return [
            'jenis_pekerjaan' => 'pasang-baru',
            'unit' => 1,
            'ketersediaan_unit' => 'sudah-ada',
            'kebutuhan' => 'jasa-saja',
            'merek' => 'daikin',
            'kapasitas' => '1',
            'lokasi_indoor' => 'kamar-tidur',
            'lokasi_outdoor' => 'dinding-luar',
            'material' => ['bracket-outdoor'],
            'cara_penawaran' => 'estimasi-foto',
            'nama_penerima' => 'Budi Uji',
            'telepon_penerima' => '081200004444',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            ...$ganti,
        ];
    }

    public function test_perbaikan_hanya_menagih_pemeriksaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/perbaikan/checkout', $this->payloadPerbaikan())
            ->assertCreated();

        $task = Task::with(['items', 'payment'])->latest('id')->first();

        $this->assertSame((float) PerbaikanTarif::BIAYA_PEMERIKSAAN, (float) $task->harga);
        $this->assertSame((float) PerbaikanTarif::BIAYA_PEMERIKSAAN, (float) $task->payment->jumlah);
        $this->assertCount(1, $task->items);
        // Belum diperiksa: tidak boleh ada rekomendasi apa pun.
        $this->assertNull($task->detail_layanan['diagnosis']);
    }

    public function test_perbaikan_unit_tambahan_menambah_biaya(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/perbaikan/checkout', $this->payloadPerbaikan(['unit' => 3]))
            ->assertCreated();

        $benar = PerbaikanTarif::BIAYA_PEMERIKSAAN + 2 * PerbaikanTarif::BIAYA_UNIT_TAMBAHAN;
        $this->assertSame((float) $benar, (float) Task::latest('id')->first()->harga);
    }

    public function test_perbaikan_untung_pada_tiap_jumlah_unit(): void
    {
        $tarif = new PerbaikanTarif;

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

    public function test_perbaikan_butuh_kontak_penerima(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/perbaikan/checkout', $this->payloadPerbaikan(['telepon_penerima' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('telepon_penerima');
    }

    /**
     * Permintaan penawaran tidak boleh punya tagihan.
     *
     * Harganya bergantung panjang pipa, jalur kabel, dan akses lokasi — hal
     * yang tidak bisa dibaca dari formulir. Angka apa pun yang tercatat sebagai
     * harga di sini akan terbaca pelanggan sebagai janji.
     */
    public function test_pasang_tidak_membuat_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/pasang/permintaan', $this->payloadPasang());

        $res->assertCreated();
        $task = Task::with('payment')->latest('id')->first();

        $this->assertNull($task->payment);
        $this->assertNull($task->harga);
        $this->assertStringStartsWith('REQ-', $task->nomor_invoice);
        $this->assertTrue($task->detail_layanan['permintaan_penawaran']);
    }

    /**
     * Pekerjaan yang tidak bisa dinilai dari foto dinaikkan jadi survei.
     *
     * Pindah AC berarti membongkar di satu tempat dan memasang di tempat lain;
     * foto tidak menunjukkan jarak, ketinggian, maupun jalur pipanya.
     */
    public function test_pindah_ac_dipaksa_survei_meski_minta_estimasi_foto(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/servis-ac/pasang/permintaan', $this->payloadPasang([
            'jenis_pekerjaan' => 'pindah-lokasi',
            'cara_penawaran' => 'estimasi-foto',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('cara_penawaran', 'survei-lokasi');
        $res->assertJsonPath('survei_diwajibkan', true);
    }

    public function test_pasang_baru_boleh_estimasi_foto(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/pasang/permintaan', $this->payloadPasang())
            ->assertCreated()
            ->assertJsonPath('cara_penawaran', 'estimasi-foto')
            ->assertJsonPath('survei_diwajibkan', false);
    }

    /** Data URL yang bukan gambar tidak boleh tersimpan sebagai berkas. */
    public function test_foto_palsu_ditolak_tanpa_membatalkan_pesanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/servis-ac/perbaikan/checkout', $this->payloadPerbaikan([
            'foto' => [
                ['label' => 'Indoor', 'data' => 'data:image/png;base64,'.base64_encode('bukan gambar sama sekali')],
            ],
        ]))->assertCreated();

        // Pesanannya tetap jadi, lampirannya saja yang tidak ikut.
        $task = Task::latest('id')->first();
        $this->assertArrayNotHasKey('foto', $task->detail_layanan);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/servis-ac/perbaikan/checkout', $this->payloadPerbaikan())->assertUnauthorized();
        $this->postJson('/api/servis-ac/pasang/permintaan', $this->payloadPasang())->assertUnauthorized();
    }
}
