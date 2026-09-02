<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\DisinfektanTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Layanan Disinfektan.
 *
 * Yang dijaga di sini bukan cuma angka. Dua penolakan justru lebih penting
 * daripada pesanan yang berhasil dibuat: pekerjaan berisiko biologis dan area
 * yang terlalu besar untuk ditagih dari formulir.
 */
class DisinfektanTest extends TestCase
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
            'properti' => 'rumah',
            'luas' => '50-100',
            'ruangan' => 3,
            'toilet' => 1,
            'kondisi' => 'normal',
            'tanggal' => '2026-09-20',
            'waktu' => '09:00',
            'nama_penerima' => 'Budi Uji',
            'telepon_penerima' => '081200005555',
            'lokasi_alamat' => 'Rumah Uji, Jakarta',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            'metode' => 'bca',
            ...$ganti,
        ];
    }

    public function test_harga_dasar_rumah_sesuai_katalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload());

        $res->assertCreated();
        $res->assertJsonPath('rincian.total', DisinfektanTarif::DASAR['hunian']['50-100']);
        $this->assertSame(150000.0, (float) Task::latest('id')->first()->harga);
    }

    public function test_properti_usaha_lebih_mahal_dari_hunian(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $rumah = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->json('rincian.total');
        $kantor = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload(['properti' => 'kantor']))
            ->json('rincian.total');

        $this->assertGreaterThan($rumah, $kantor);
    }

    public function test_ruangan_dan_toilet_tambahan_menambah_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'ruangan' => 5,
            'toilet' => 3,
        ]));

        $benar = DisinfektanTarif::DASAR['hunian']['50-100']
            + 2 * DisinfektanTarif::TARIF_RUANGAN_TAMBAHAN
            + 2 * DisinfektanTarif::TARIF_TOILET_TAMBAHAN;

        $res->assertCreated();
        $res->assertJsonPath('rincian.total', $benar);
    }

    /**
     * Pekerjaan berisiko biologis ditolak, bukan disurcharge.
     *
     * Darah dan cairan tubuh butuh SOP, personel, dan perlengkapan yang belum
     * dimiliki. Menerimanya lalu mengerjakannya seadanya membahayakan petugas
     * sekaligus pelanggan — jadi jawabannya bukan harga yang lebih tinggi.
     */
    public function test_cairan_tubuh_berisiko_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'perhatian' => ['anak-kecil', DisinfektanTarif::PERHATIAN_DITOLAK],
        ]));

        $res->assertStatus(422)->assertJsonValidationErrors('perhatian');
        $this->assertStringContainsString('dekontaminasi khusus', (string) $res->json('message'));
        $this->assertSame(0, Task::count());
    }

    /**
     * Area di atas 300 m² tidak bisa ditagih dari formulir.
     *
     * Selisih antara satu gedung dan gedung lain terlalu besar untuk diwakili
     * satu angka; menagihnya berarti menagih terlalu murah untuk yang besar.
     */
    public function test_luas_di_atas_300_diarahkan_ke_penawaran(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload(['luas' => '>300']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('luas');

        $this->assertSame(0, Task::count());
    }

    public function test_perhatian_biasa_tidak_menolak_pesanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'perhatian' => ['anak-kecil', 'hewan-peliharaan', 'elektronik-sensitif'],
        ]))->assertCreated();

        $task = Task::latest('id')->first();
        $this->assertSame(
            ['anak-kecil', 'hewan-peliharaan', 'elektronik-sensitif'],
            $task->detail_layanan['perhatian'],
        );
    }

    /**
     * Waktu kontak TIDAK boleh dipatok satu angka.
     *
     * Tiap produk punya labelnya sendiri. Menyimpan satu angka di pesanan
     * berarti menjanjikan prosedur yang belum tentu benar untuk produk yang
     * dipakai teknisi di lokasi.
     */
    public function test_waktu_kontak_tidak_dipatok_saat_pemesanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();

        $detail = Task::latest('id')->first()->detail_layanan;
        $this->assertNull($detail['waktu_kontak']);
        $this->assertNull($detail['produk']);
    }

    public function test_setiap_kombinasi_menyisakan_margin(): void
    {
        $tarif = new DisinfektanTarif;

        foreach (DisinfektanTarif::PROPERTI as $properti) {
            foreach (['<50', '50-100', '101-300'] as $luas) {
                foreach (DisinfektanTarif::KONDISI as $kondisi) {
                    foreach ([1, 3, 8, 20] as $ruangan) {
                        foreach ([0, 1, 5] as $toilet) {
                            $r = $tarif->hitung($properti, $luas, $ruangan, $toilet, $kondisi);
                            $margin = $r['total'] - $r['biaya'];

                            $this->assertGreaterThan(
                                0.15,
                                $margin / $r['total'],
                                "Margin terlalu tipis: {$properti} {$luas} {$kondisi} {$ruangan} ruangan {$toilet} toilet.",
                            );
                        }
                    }
                }
            }
        }
    }

    public function test_kontak_penerima_wajib(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload(['telepon_penerima' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('telepon_penerima');
    }

    public function test_permintaan_penawaran_tidak_membuat_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/permintaan', [
            'properti' => 'kantor',
            'luas' => '>300',
            'ruangan' => 25,
            'toilet' => 8,
            'kondisi' => 'banyak-orang',
            'frekuensi' => 'mingguan',
            'nama_penerima' => 'Budi Uji',
            'telepon_penerima' => '081200005555',
            'lokasi_alamat' => 'Gedung Uji, Jakarta',
        ]);

        $res->assertCreated();
        $task = Task::with('payment')->latest('id')->first();

        $this->assertNull($task->payment);
        $this->assertNull($task->harga);
        $this->assertStringStartsWith('REQ-', $task->nomor_invoice);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertUnauthorized();
    }

    /* ==================== Foto area ==================== */

    public function test_foto_area_tersimpan_bersama_pesanan(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'foto' => [['label' => 'Ruang utama', 'data' => $this->pngSatuPiksel()]],
        ]));

        $res->assertCreated();
        $foto = Task::latest('id')->first()->detail_layanan['foto'];

        $this->assertCount(1, $foto);
        $this->assertSame('Ruang utama', $foto[0]['label']);
        Storage::disk('public')->assertExists($foto[0]['jalur']);
    }

    /**
     * Berkas yang mengaku gambar tapi bukan gambar tidak boleh ikut tersimpan —
     * dan tidak boleh membatalkan pesanannya juga. Pekerjaannya tetap bisa
     * dikerjakan tanpa lampiran.
     */
    public function test_foto_palsu_dilewati_tanpa_membatalkan_pesanan(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'foto' => [
                ['label' => 'Ruang utama', 'data' => $this->pngSatuPiksel()],
                ['label' => 'Dapur', 'data' => 'data:image/png;base64,'.base64_encode('ini teks biasa')],
            ],
        ]));

        $res->assertCreated();
        $task = Task::latest('id')->first();

        $this->assertCount(1, $task->detail_layanan['foto']);
        $this->assertSame('Ruang utama', $task->detail_layanan['foto'][0]['label']);
    }

    /* ==================== Laporan pekerjaan ==================== */

    public function test_laporan_belum_ada_sebelum_petugas_menutup_pekerjaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();

        $nomor = Task::latest('id')->first()->nomor_invoice;
        $res = $this->getJson("/api/bersih/disinfektan/laporan/{$nomor}");

        $res->assertOk();
        $res->assertJsonPath('laporan', null);
    }

    public function test_laporan_pesanan_orang_lain_tidak_bisa_dibuka(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/bersih/disinfektan/laporan/{$nomor}")->assertNotFound();
    }

    /**
     * Inti seluruh layanan ini: waktu kontak MENGIKUTI PRODUK.
     *
     * Dua pesanan yang sama persis, dikerjakan dengan produk berbeda, harus
     * melaporkan waktu kontak berbeda. Kalau uji ini bisa lolos dengan satu
     * angka tetap, berarti aplikasinya sedang menjanjikan prosedur yang belum
     * tentu benar untuk produk yang dipakai di lokasi.
     */
    public function test_waktu_kontak_di_laporan_mengikuti_produk_yang_dipakai(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();
        $pertama = Task::latest('id')->first();
        $this->artisan('bersih:laporan', ['nomor' => $pertama->nomor_invoice, '--produk' => 'alkohol'])
            ->assertSuccessful();

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();
        $kedua = Task::latest('id')->first();
        $this->artisan('bersih:laporan', ['nomor' => $kedua->nomor_invoice, '--produk' => 'benzalkonium'])
            ->assertSuccessful();

        $a = $this->getJson("/api/bersih/disinfektan/laporan/{$pertama->nomor_invoice}")->json('laporan');
        $b = $this->getJson("/api/bersih/disinfektan/laporan/{$kedua->nomor_invoice}")->json('laporan');

        $this->assertNotSame($a['produk']['waktu_kontak'], $b['produk']['waktu_kontak']);
        $this->assertSame('30 detik', $a['produk']['waktu_kontak']);
        $this->assertSame('10 menit', $b['produk']['waktu_kontak']);
    }

    public function test_laporan_mengisi_produk_dan_menutup_pekerjaan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();
        $task = Task::latest('id')->first();

        // Sebelum petugas datang, keduanya memang belum boleh terisi.
        $this->assertNull($task->detail_layanan['produk']);
        $this->assertNull($task->detail_layanan['waktu_kontak']);

        $this->artisan('bersih:laporan', ['nomor' => $task->nomor_invoice])->assertSuccessful();

        $task->refresh();
        $this->assertNotNull($task->detail_layanan['produk']);
        $this->assertSame('10 menit', $task->detail_layanan['waktu_kontak']);
        $this->assertSame('selesai', $task->fulfillment_status);

        $laporan = $this->getJson("/api/bersih/disinfektan/laporan/{$task->nomor_invoice}")->json('laporan');
        $this->assertNotEmpty($laporan['area_dikerjakan']);
        $this->assertStringStartsWith('LAP-', $laporan['nomor']);
    }

    /**
     * Nomor izin edar dibiarkan kosong sampai betul-betul disalin dari kemasan.
     * Angka karangan di kolom ini lebih buruk daripada kolom yang kosong.
     */
    public function test_nomor_izin_edar_kosong_kalau_tidak_dicatat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->artisan('bersih:laporan', ['nomor' => $nomor])->assertSuccessful();

        $this->getJson("/api/bersih/disinfektan/laporan/{$nomor}")
            ->assertJsonPath('laporan.produk.registrasi', null);
    }

    /**
     * Foto pesanan adalah foto "sebelum" yang sesungguhnya, jadi laporannya
     * memakai berkas yang sama dan menandainya begitu.
     */
    public function test_laporan_memakai_foto_pesanan_sebagai_foto_sebelum(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/disinfektan/checkout', $this->payload([
            'foto' => [['label' => 'Ruang utama', 'data' => $this->pngSatuPiksel()]],
        ]))->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->artisan('bersih:laporan', ['nomor' => $nomor])->assertSuccessful();

        $laporan = $this->getJson("/api/bersih/disinfektan/laporan/{$nomor}")->json('laporan');

        $this->assertCount(1, $laporan['sebelum']);
        $this->assertSame('Sebelum — Ruang utama', $laporan['sebelum'][0]['label']);
        $this->assertSame([], $laporan['sesudah']);
    }

    private function pngSatuPiksel(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ'.
            'AAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }
}
