<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\TukangTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BisaTukang.
 *
 * Yang dijaga di sini adalah janji layanannya, satu per satu:
 *
 * - Biaya kunjungan tidak bisa diketik pemesan; angkanya dari katalog server.
 * - Harga perbaikan tidak pernah ada sebelum tukang memeriksa, dan tidak pernah
 *   masuk tagihan sebelum pemesan menyetujuinya.
 * - Persetujuan menuntut pernyataan setuju yang eksplisit, dan penawaran yang
 *   lewat masa berlaku tidak bisa disetujui.
 * - Tip menambah tagihan, tidak menambah komisi.
 * - Borongan tidak menagih apa pun dan tidak pernah menyebut harga final.
 */
class TukangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaTukang', 'slug' => 'bisatukang', 'basis_harga' => 'kunjungan_jam']);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function payload(array $ganti = []): array
    {
        return [
            'kategori' => 'listrik',
            'sub_kategori' => 'Stop kontak rusak',
            'tipe_properti' => 'rumah',
            'penyediaan_material' => 'tukang',
            'lokasi_masalah' => 'Dalam rumah',
            'deskripsi_masalah' => 'Stop kontak kamar mandi memercik dan MCB turun tiap water heater dinyalakan.',
            'jadwal_tipe' => 'secepatnya',
            'nama_penerima' => 'Faras',
            'telepon_penerima' => '081200001111',
            'lokasi_alamat' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'lokasi_lat' => -6.2088,
            'lokasi_lng' => 106.8456,
            'metode' => 'gopay',
            ...$ganti,
        ];
    }

    private function pesan(array $ganti = []): string
    {
        $this->postJson('/api/tukang/checkout', $this->payload($ganti))->assertCreated();

        return Task::latest('id')->first()->nomor_invoice;
    }

    /* ==================== Katalog ==================== */

    public function test_katalog_memuat_kategori_beserta_biaya_kunjungannya(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $r = $this->getJson('/api/tukang/katalog')->assertOk();

        $r->assertJsonCount(count(TukangTarif::KATEGORI), 'kategori');
        $r->assertJsonPath('kategori.0.id', 'listrik');
        $r->assertJsonPath('kategori.0.kunjungan', TukangTarif::KATEGORI['listrik']['kunjungan']);
        $r->assertJsonPath('garansi_hari', TukangTarif::GARANSI_HARI);
    }

    /* ==================== Harian ==================== */

    public function test_biaya_kunjungan_diambil_dari_katalog_bukan_dari_pemesan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Harga yang diselipkan pemesan tidak pernah dibaca.
        $this->postJson('/api/tukang/checkout', $this->payload([
            'harga' => 1,
            'biaya_kunjungan' => 1,
            'total' => 1,
        ]))->assertCreated();

        $task = Task::latest('id')->first();
        $tarif = TukangTarif::KATEGORI['listrik']['kunjungan'];

        $this->assertSame((float) $tarif, (float) $task->harga);
        $this->assertSame((float) $tarif, (float) $task->payment->jumlah);
    }

    public function test_harga_perbaikan_belum_ada_sebelum_tukang_memeriksa(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan();

        $r = $this->getJson("/api/tukang/{$nomor}")->assertOk();

        $r->assertJsonPath('tahap', 'mencari');
        $r->assertJsonPath('penawaran', null);
        $r->assertJsonPath('tukang', null);
        // Yang ditagih baru kunjungannya.
        $r->assertJsonPath('total', TukangTarif::KATEGORI['listrik']['kunjungan']);
    }

    public function test_sub_kategori_harus_milik_kategorinya(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // "Atap bocor" ada di katalog, tapi bukan di bawah Listrik. Mengirim
        // tukang listrik untuk atap bocor adalah orang yang salah di pintu.
        $this->postJson('/api/tukang/checkout', $this->payload(['sub_kategori' => 'Atap bocor']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sub_kategori');
    }

    public function test_deskripsi_terlalu_pendek_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/tukang/checkout', $this->payload(['deskripsi_masalah' => 'rusak']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('deskripsi_masalah');
    }

    /* ==================== Tahap ==================== */

    public function test_tahap_tidak_bisa_mundur_atau_melompat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan();

        // Melompat dari 'mencari' langsung ke 'tiba'.
        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'tiba'])->assertFailed();
        $this->getJson("/api/tukang/{$nomor}")->assertJsonPath('tahap', 'mencari');

        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'menuju'])->assertSuccessful();
        // Mundur.
        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'menuju'])->assertFailed();
    }

    /**
     * Pekerjaan hanya dimulai setelah pemesan menyetujui harganya. Alat bantu
     * pengembangan pun tidak boleh jadi jalan memutarnya — kalau bisa, janji
     * "harganya disetujui dulu" hanya berlaku selama tidak ada yang mencoba.
     */
    public function test_tahap_dikerjakan_tidak_bisa_dicapai_dari_terminal(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan();

        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'dikerjakan'])->assertFailed();
    }

    public function test_tukang_yang_ditugaskan_sesuai_keahlian(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan(['kategori' => 'pipa', 'sub_kategori' => 'Pipa bocor']);

        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'menuju'])->assertSuccessful();

        $this->getJson("/api/tukang/{$nomor}")
            ->assertOk()
            ->assertJsonPath('tukang.keahlian', TukangTarif::KATEGORI['pipa']['nama']);
    }

    /* ==================== Penawaran ==================== */

    private function sampaiPenawaran(array $ganti = []): string
    {
        $nomor = $this->pesan($ganti);
        foreach (['menuju', 'tiba', 'penawaran'] as $tahap) {
            $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }

        return $nomor;
    }

    public function test_penawaran_masuk_tagihan_hanya_setelah_disetujui(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $kunjungan = TukangTarif::KATEGORI['listrik']['kunjungan'];
        $task = Task::where('nomor_invoice', $nomor)->first();
        $total = (int) $task->detail_layanan['penawaran']['total'];

        // Terbit, tapi belum menagih.
        $this->assertSame((float) $kunjungan, (float) $task->harga);
        $this->assertSame((float) $kunjungan, (float) $task->payment->jumlah);

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true,
            'nama_penyetuju' => 'Faras',
        ])->assertOk()->assertJsonPath('tahap', 'dikerjakan');

        $task->refresh();
        $this->assertSame((float) ($kunjungan + $total), (float) $task->harga);
        $this->assertSame((float) ($kunjungan + $total), (float) $task->payment->jumlah);
    }

    public function test_persetujuan_menuntut_pernyataan_setuju_yang_eksplisit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        // Halaman yang kebetulan terbuka tidak boleh mengikat harga.
        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", ['nama_penyetuju' => 'Faras'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('setuju');

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => false,
            'nama_penyetuju' => 'Faras',
        ])->assertStatus(422);

        $this->getJson("/api/tukang/{$nomor}")->assertJsonPath('tahap', 'penawaran');
    }

    public function test_penawaran_kedaluwarsa_tidak_bisa_disetujui(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $task = Task::where('nomor_invoice', $nomor)->first();
        $d = $task->detail_layanan;
        $d['penawaran']['berlaku_sampai'] = now()->subDay()->toDateString();
        $task->update(['detail_layanan' => $d]);

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true,
            'nama_penyetuju' => 'Faras',
        ])->assertStatus(422);

        $this->getJson("/api/tukang/{$nomor}")
            ->assertJsonPath('tahap', 'penawaran')
            ->assertJsonPath('penawaran.kedaluwarsa', true);
    }

    public function test_revisi_tidak_mengubah_angka_apa_pun(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $semula = (float) Task::where('nomor_invoice', $nomor)->first()->harga;

        $this->postJson("/api/tukang/{$nomor}/penawaran/revisi", [
            'catatan' => 'Tolong pakai material yang lebih murah, dan jasanya dirinci per titik.',
        ])->assertOk()->assertJsonPath('jumlah_revisi', 1);

        $task = Task::where('nomor_invoice', $nomor)->first();
        $this->assertSame($semula, (float) $task->harga);
        $this->assertSame('revisi', $task->detail_layanan['penawaran']['keputusan']);
    }

    public function test_penawaran_yang_sudah_disetujui_tidak_bisa_direvisi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true, 'nama_penyetuju' => 'Faras',
        ])->assertOk();

        $this->postJson("/api/tukang/{$nomor}/penawaran/revisi", ['catatan' => 'Berubah pikiran soal materialnya.'])
            ->assertStatus(422);
    }

    /**
     * Material yang sudah dibeli pemesan tidak boleh ikut ditagih — kalau ikut,
     * barang yang sama dibayar dua kali.
     */
    public function test_material_tidak_ditagih_kalau_pemesan_yang_menyiapkan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran(['penyediaan_material' => 'pelanggan']);

        $penawaran = Task::where('nomor_invoice', $nomor)->first()->detail_layanan['penawaran'];

        $this->assertSame('pelanggan', $penawaran['material_ditanggung']);
        $this->assertSame(
            [],
            array_values(array_filter($penawaran['baris'], fn ($b) => $b['kategori'] === 'material')),
        );
    }

    /* ==================== Pembatalan ==================== */

    public function test_batal_hanya_sebelum_tukang_berangkat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan();

        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'menuju'])->assertSuccessful();

        $this->postJson("/api/tukang/{$nomor}/batal")
            ->assertStatus(422)
            ->assertJsonPath('tukang_sudah_jalan', true);

        $nomorLain = $this->pesan();
        $this->postJson("/api/tukang/{$nomorLain}/batal")
            ->assertOk()
            ->assertJsonPath('dibatalkan', true);
    }

    /* ==================== Tip & penilaian ==================== */

    public function test_tip_menambah_tagihan_tanpa_menambah_komisi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->pesan();

        // Belum ada tukang: tidak ada yang bisa diberi tip.
        $this->postJson("/api/tukang/{$nomor}/tip", ['tip' => 10000])->assertStatus(422);

        foreach (['menuju', 'tiba'] as $tahap) {
            $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }

        $task = Task::where('nomor_invoice', $nomor)->first();
        $semula = (int) $task->payment->jumlah;
        $komisiSemula = (int) $task->payment->komisi_platform;

        $this->postJson("/api/tukang/{$nomor}/tip", ['tip' => 10000])->assertOk()->assertJsonPath('tip', 10000);
        // Tip kedua MENAMBAH, bukan mengganti.
        $this->postJson("/api/tukang/{$nomor}/tip", ['tip' => 5000])->assertOk()->assertJsonPath('tip', 15000);

        $task->refresh();
        $this->assertSame($semula + 15000, (int) $task->payment->jumlah);
        $this->assertSame($komisiSemula, (int) $task->payment->komisi_platform);

        $this->getJson("/api/tukang/{$nomor}")->assertJsonPath('tip', 15000);
    }

    public function test_penilaian_hanya_setelah_selesai_dan_sekali_saja(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $this->postJson("/api/tukang/{$nomor}/nilai", ['bintang' => 5])->assertStatus(422);

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true, 'nama_penyetuju' => 'Faras',
        ])->assertOk();
        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'selesai'])->assertSuccessful();

        $this->postJson("/api/tukang/{$nomor}/nilai", ['bintang' => 5, 'tag' => ['Rapi']])->assertOk();
        $this->postJson("/api/tukang/{$nomor}/nilai", ['bintang' => 4])->assertStatus(422);
    }

    public function test_garansi_dicatat_saat_pekerjaan_selesai(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true, 'nama_penyetuju' => 'Faras',
        ])->assertOk();
        $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => 'selesai'])->assertSuccessful();

        $d = Task::where('nomor_invoice', $nomor)->first()->detail_layanan;
        $this->assertSame(
            now()->addDays(TukangTarif::GARANSI_HARI)->toDateString(),
            $d['garansi_sampai'],
        );
    }

    /* ==================== Borongan ==================== */

    public function test_borongan_tidak_menagih_dan_tidak_menyebut_harga_final(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $r = $this->postJson('/api/tukang/permintaan', $this->payload([
            'kategori' => 'renovasi',
            'sub_kategori' => 'Renovasi kamar mandi',
        ]))->assertCreated();

        $nomor = $r->json('nomor');
        $this->assertStringStartsWith('REQ-', $nomor);

        $task = Task::latest('id')->first();
        $this->assertNull($task->payment);
        $this->assertNull($task->harga);
        // Rentang masuk sebagai budget, bukan harga.
        $this->assertSame((float) TukangTarif::BORONGAN_MULAI, (float) $task->budget);

        $this->getJson("/api/tukang/{$nomor}")
            ->assertOk()
            ->assertJsonPath('model_kerja', 'borongan')
            ->assertJsonPath('biaya_kunjungan', 0)
            ->assertJsonPath('penawaran', null);
    }

    /**
     * Borongan belum punya pembayaran saat RAB-nya disetujui, jadi jalur
     * persetujuannya harus membuatnya — bukan gagal diam-diam dan meninggalkan
     * pekerjaan yang disepakati tanpa tagihan sama sekali.
     */
    public function test_persetujuan_borongan_membuat_tagihannya(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $nomor = $this->postJson('/api/tukang/permintaan', $this->payload([
            'kategori' => 'renovasi',
            'sub_kategori' => 'Renovasi kamar mandi',
        ]))->assertCreated()->json('nomor');

        foreach (['menuju', 'tiba', 'penawaran'] as $tahap) {
            $this->artisan('tukang:mitra', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }

        $total = (int) Task::latest('id')->first()->detail_layanan['penawaran']['total'];

        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true, 'nama_penyetuju' => 'Faras',
        ])->assertOk();

        $task = Task::latest('id')->first();
        $this->assertNotNull($task->payment);
        $this->assertSame((float) $total, (float) $task->payment->jumlah);
        $this->assertSame((float) $total, (float) $task->harga);
    }

    /* ==================== Kepemilikan ==================== */

    public function test_pekerjaan_orang_lain_tidak_bisa_dibuka_atau_disetujui(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $nomor = $this->sampaiPenawaran();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/tukang/{$nomor}")->assertNotFound();
        $this->postJson("/api/tukang/{$nomor}/penawaran/setujui", [
            'setuju' => true, 'nama_penyetuju' => 'Orang lain',
        ])->assertNotFound();
        $this->postJson("/api/tukang/{$nomor}/tip", ['tip' => 5000])->assertNotFound();
    }

    public function test_butuh_login(): void
    {
        $this->getJson('/api/tukang/katalog')->assertUnauthorized();
        $this->postJson('/api/tukang/checkout', $this->payload())->assertUnauthorized();
    }
}
