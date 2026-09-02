<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\JemputTarif;
use App\Services\PromoJemput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BisaJemput.
 *
 * Yang dijaga di sini: tarif tidak bisa diketik penumpang, titik jemput harus
 * dikonfirmasi, dan promo tidak boleh lebih besar daripada pendapatan platform
 * per perjalanan.
 */
class JemputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaJemput', 'slug' => 'bisajemput', 'basis_harga' => 'jarak_waktu']);

        // Jam netral: di luar semua jendela jam sibuk dan promo waktu, supaya
        // uji tarif tidak berubah-ubah menurut jam berapa ia dijalankan.
        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Monas ke Blok M, kira-kira 7 km lewat jalan. */
    private function payload(array $ganti = []): array
    {
        return [
            'tipe' => 'motor',
            'varian' => 'cepat',
            'titik_jemput_dikonfirmasi' => true,
            'jemput_alamat' => 'Monas, Jakarta Pusat',
            'jemput_lat' => -6.1754,
            'jemput_lng' => 106.8272,
            'tujuan_alamat' => 'Blok M, Jakarta Selatan',
            'tujuan_lat' => -6.2441,
            'tujuan_lng' => 106.8003,
            'penumpang' => 1,
            'metode' => 'gopay',
            ...$ganti,
        ];
    }

    /* ==================== Jarak & tarif ==================== */

    public function test_jarak_dihitung_server_bukan_dikirim_klien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Klien mencoba menyelundupkan jarak 1 km untuk rute yang jauh.
        $res = $this->postJson('/api/jemput/checkout', $this->payload(['km' => 1, 'tarif' => 500]));

        $res->assertCreated();
        $d = Task::latest('id')->first()->detail_layanan;

        $this->assertGreaterThan(7, $d['km']);
        $this->assertGreaterThan(10_000, $d['tarif']);
    }

    public function test_estimasi_menampilkan_semua_pilihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.2441, 'tujuan_lng' => 106.8003,
        ]);

        $res->assertOk();
        $pilihan = $res->json('pilihan');

        $this->assertCount(8, $pilihan);
        $this->assertSame('motor', $pilihan[0]['tipe']);
        // Yang hemat memang harus lebih murah daripada yang cepat.
        $this->assertLessThan($pilihan[0]['tarif'], $pilihan[1]['tarif']);
    }

    public function test_perjalanan_pendek_kena_tarif_minimum(): void
    {
        $tarif = new JemputTarif;
        $hasil = $tarif->hitung('motor', 'cepat', 0.4, new \DateTimeImmutable('2026-09-02 10:00:00'));

        $this->assertSame(10_000, $hasil['tarif']);
        $this->assertNotEmpty(array_filter($hasil['baris'], fn ($b) => str_contains($b['label'], 'minimum')));
    }

    public function test_rute_terlalu_jauh_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.9175, 'tujuan_lng' => 107.6191, // Bandung
        ])->assertStatus(422);
    }

    public function test_titik_jemput_sama_dengan_tujuan_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.1754, 'tujuan_lng' => 106.8272,
        ])->assertStatus(422);
    }

    /* ==================== Titik jemput ==================== */

    /**
     * Aturan yang tidak boleh hanya hidup di layar: pesanan tanpa konfirmasi
     * titik jemput mengirim pengemudi ke koordinat yang belum tentu benar.
     */
    public function test_pesanan_tanpa_konfirmasi_titik_jemput_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/checkout', $this->payload(['titik_jemput_dikonfirmasi' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('titik_jemput_dikonfirmasi');

        $payload = $this->payload();
        unset($payload['titik_jemput_dikonfirmasi']);
        $this->postJson('/api/jemput/checkout', $payload)->assertStatus(422);

        $this->assertSame(0, Task::count());
    }

    public function test_titik_jemput_jadi_lokasi_tugas(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();

        $task = Task::latest('id')->first();
        $this->assertSame('Monas, Jakarta Pusat', $task->lokasi_alamat);
        $this->assertSame('Blok M, Jakarta Selatan', $task->detail_layanan['tujuan']['alamat']);
    }

    /* ==================== Kapasitas ==================== */

    public function test_penumpang_melebihi_kapasitas_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/checkout', $this->payload(['penumpang' => 3]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('penumpang');

        $this->postJson('/api/jemput/checkout', $this->payload([
            'tipe' => 'van', 'varian' => 'cepat', 'penumpang' => 6,
        ]))->assertCreated();
    }

    public function test_varian_yang_tidak_ada_di_kendaraan_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // 'van' hanya punya varian cepat; 'hemat' ada di motor dan mobil saja.
        $this->postJson('/api/jemput/checkout', $this->payload(['tipe' => 'van', 'varian' => 'hemat']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('varian');
    }

    /* ==================== Uang ==================== */

    /**
     * Pengemudi menerima 80% tarif; sisanya seluruh pendapatan platform. Uji
     * ini memastikan yang tersisa setelah biaya masih positif untuk SETIAP
     * kendaraan pada rentang jarak yang wajar.
     */
    public function test_setiap_perjalanan_menyisakan_margin(): void
    {
        $tarif = new JemputTarif;
        $saat = new \DateTimeImmutable('2026-09-02 10:00:00');

        foreach (JemputTarif::idTipe() as $tipe) {
            foreach (array_keys(JemputTarif::TIPE[$tipe]['varian']) as $varian) {
                foreach ([0.3, 1, 3, 8, 15, 30, 55] as $km) {
                    $h = $tarif->hitung($tipe, $varian, $km, $saat);
                    $margin = $h['tarif'] - $h['biaya'];

                    $this->assertGreaterThan(
                        0,
                        $margin,
                        "{$tipe}/{$varian} {$km}km rugi Rp".number_format(-$margin, 0, ',', '.'),
                    );
                    $this->assertGreaterThan(
                        $h['tarif'] * 0.12,
                        $margin,
                        "{$tipe}/{$varian} {$km}km margin di bawah 12%",
                    );
                }
            }
        }
    }

    /**
     * Inti katalog promo: potongan berulang tidak boleh melebihi setengah
     * komisi. Kalau uji ini jebol, artinya ada promo yang membayar penumpang
     * dari kantong platform setiap hari — bukan sekali sebagai biaya akuisisi.
     */
    public function test_promo_berulang_tidak_pernah_melebihi_setengah_komisi(): void
    {
        $tarif = new JemputTarif;
        $promo = new PromoJemput;

        foreach (JemputTarif::idTipe() as $tipe) {
            foreach (array_keys(JemputTarif::TIPE[$tipe]['varian']) as $varian) {
                foreach ([1, 5, 12, 25, 45] as $km) {
                    $h = $tarif->hitung($tipe, $varian, (float) $km, new \DateTimeImmutable('2026-09-02 10:00:00'));

                    foreach (PromoJemput::KATALOG as $p) {
                        if ($p['jenis'] !== 'berulang') {
                            continue;
                        }

                        $potongan = $promo->potongan($p, $h['tarif'], $h['komisi']);
                        $this->assertLessThanOrEqual(
                            (int) floor($h['komisi'] * PromoJemput::BATAS_KOMISI),
                            $potongan,
                            "{$p['kode']} pada {$tipe}/{$varian} {$km}km memakan lebih dari setengah komisi",
                        );
                        // Dan yang tersisa setelah potongan tetap positif.
                        $this->assertGreaterThan(
                            0,
                            $h['tarif'] - $potongan - $h['biaya'],
                            "{$p['kode']} pada {$tipe}/{$varian} {$km}km membuat perjalanan rugi",
                        );
                    }
                }
            }
        }
    }

    public function test_promo_akuisisi_hanya_untuk_perjalanan_pertama(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $jauh = $this->payload(['tipe' => 'mobil', 'varian' => 'cepat', 'kode_promo' => 'JEMPUTBARU']);

        $pertama = $this->postJson('/api/jemput/checkout', $jauh);
        $pertama->assertCreated();
        $this->assertGreaterThan(0, $pertama->json('rincian.potongan'));

        // Perjalanan kedua: kode yang sama harus ditolak, bukan diam-diam Rp0.
        $this->postJson('/api/jemput/checkout', $jauh)
            ->assertStatus(422)
            ->assertJsonValidationErrors('kode_promo');
    }

    public function test_promo_di_bawah_minimum_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Motor jarak pendek: tarifnya di bawah minimum JEMPUT10 (Rp40.000).
        $this->postJson('/api/jemput/checkout', $this->payload([
            'tujuan_lat' => -6.1790, 'tujuan_lng' => 106.8290,
            'kode_promo' => 'JEMPUT10',
        ]))->assertStatus(422)->assertJsonValidationErrors('kode_promo');
    }

    public function test_kode_promo_karangan_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/checkout', $this->payload(['kode_promo' => 'GRATIS100']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kode_promo');
    }

    public function test_jam_sibuk_disebutkan_bukan_disembunyikan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 07:30:00')); // Rabu pagi
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.2441, 'tujuan_lng' => 106.8003,
        ]);

        $res->assertOk();
        $this->assertSame('pagi', $res->json('sibuk'));
        $this->assertNotNull($res->json('sibuk_alasan'));
        $this->assertGreaterThan(1.0, $res->json('sibuk_pengali'));
    }

    /* ==================== Perjalanan ==================== */

    public function test_tahap_perjalanan_maju_lewat_perintah_pengemudi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->getJson("/api/jemput/{$nomor}")->assertJsonPath('tahap', 'mencari')
            ->assertJsonPath('pengemudi', null);

        $this->artisan('jemput:pengemudi', ['nomor' => $nomor, '--tahap' => 'dijemput'])->assertSuccessful();

        $res = $this->getJson("/api/jemput/{$nomor}");
        $res->assertJsonPath('tahap', 'dijemput');
        $this->assertNotNull($res->json('pengemudi.plat'));
        $this->assertTrue($res->json('pengemudi.telepon_tersamar'));
    }

    public function test_tahap_tidak_bisa_melompat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->artisan('jemput:pengemudi', ['nomor' => $nomor, '--tahap' => 'selesai'])->assertFailed();
        $this->getJson("/api/jemput/{$nomor}")->assertJsonPath('tahap', 'mencari');
    }

    public function test_pembatalan_menyebutkan_pengemudi_sudah_jalan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->postJson("/api/jemput/{$nomor}/batal")
            ->assertOk()
            ->assertJsonPath('pengemudi_sudah_jalan', false);
    }

    public function test_perjalanan_berjalan_tidak_bisa_dibatalkan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        foreach (['dijemput', 'tiba', 'jalan'] as $tahap) {
            $this->artisan('jemput:pengemudi', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }

        $this->postJson("/api/jemput/{$nomor}/batal")->assertStatus(422);
    }

    public function test_penilaian_hanya_setelah_selesai_dan_sekali_saja(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        $this->postJson("/api/jemput/{$nomor}/nilai", ['bintang' => 5])->assertStatus(422);

        foreach (['dijemput', 'tiba', 'jalan', 'selesai'] as $tahap) {
            $this->artisan('jemput:pengemudi', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }

        $this->postJson("/api/jemput/{$nomor}/nilai", [
            'bintang' => 5, 'tag' => ['Ramah'], 'tip' => 5_000,
        ])->assertOk();

        $this->postJson("/api/jemput/{$nomor}/nilai", ['bintang' => 4])->assertStatus(422);
    }

    /** Tip seluruhnya milik pengemudi: platform tidak mengambil komisi darinya. */
    public function test_tip_menambah_tagihan_tanpa_menambah_komisi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $task = Task::latest('id')->first();
        $nomor = $task->nomor_invoice;
        $komisiAwal = (float) $task->payment->komisi_platform;
        $jumlahAwal = (float) $task->payment->jumlah;

        foreach (['dijemput', 'tiba', 'jalan', 'selesai'] as $tahap) {
            $this->artisan('jemput:pengemudi', ['nomor' => $nomor, '--tahap' => $tahap])->assertSuccessful();
        }
        $this->postJson("/api/jemput/{$nomor}/nilai", ['bintang' => 5, 'tip' => 10_000])->assertOk();

        $payment = $task->fresh()->payment;
        $this->assertSame($jumlahAwal + 10_000, (float) $payment->jumlah);
        $this->assertSame($komisiAwal, (float) $payment->komisi_platform);
    }

    public function test_perjalanan_orang_lain_tidak_bisa_dibuka(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/jemput/{$nomor}")->assertNotFound();
    }

    public function test_pesan_untuk_orang_lain_wajib_nama_dan_telepon(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/jemput/checkout', $this->payload(['untuk_orang_lain' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama_penumpang', 'telepon_penumpang']);

        $this->postJson('/api/jemput/checkout', $this->payload([
            'untuk_orang_lain' => true,
            'nama_penumpang' => 'Rina',
            'telepon_penumpang' => '081200003333',
        ]))->assertCreated();

        $task = Task::latest('id')->first();
        $this->assertSame('Rina', $task->nama_penerima);
        $this->assertTrue($task->detail_layanan['untuk_orang_lain']);
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/jemput/checkout', $this->payload())->assertUnauthorized();
    }
}
