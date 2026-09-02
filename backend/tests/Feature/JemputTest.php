<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\JemputTarif;
use App\Services\PermintaanJemput;
use App\Services\PromoJemput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        // Jam netral DI WIB: 03.00 UTC = 10.00 WIB, di luar semua jendela jam
        // sibuk dan promo waktu. Ditulis dalam UTC karena itu zona aplikasi;
        // yang dibaca aturan jam sibuk adalah padanannya di WIB.
        Carbon::setTestNow(Carbon::parse('2026-09-02 03:00:00'));

        Cache::flush();

        /*
         * Uji tidak boleh menyentuh jaringan. Bawaannya layanan rute dianggap
         * tidak terjangkau, jadi yang teruji di sebagian besar berkas ini
         * adalah jalur cadangan garis lurus — dan itu memang jalur yang harus
         * tetap benar saat penyedia rute mati.
         */
        Http::preventStrayRequests();

        /*
         * Satu stub yang membaca $ruteJawaban, bukan dua stub yang saling
         * menimpa: Http::fake() menggabungkan pendaftaran dan yang PERTAMA
         * cocok yang dipakai, jadi memanggilnya lagi di dalam uji tidak akan
         * menggantikan stub dari setUp ini.
         */
        Http::fake([
            'api.mapbox.com/*' => fn () => $this->ruteJawaban
                ? Http::response($this->ruteJawaban)
                : Http::response([], 500),
        ]);
    }

    /** @var array<string, mixed>|null */
    private ?array $ruteJawaban = null;

    /** Jawaban rute palsu: satu rute tiga titik dengan jarak tertentu. */
    private function fakeRute(float $meter): void
    {
        $this->ruteJawaban = [
            'routes' => [[
                'distance' => $meter,
                'duration' => 1500,
                'geometry' => [
                    'coordinates' => [
                        [106.8272, -6.1754],
                        [106.8150, -6.2100],
                        [106.8003, -6.2441],
                    ],
                ],
            ]],
        ];
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
        $hasil = $tarif->hitung('motor', 'cepat', 0.4, null);

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

    /**
     * Garis di peta dan angka di nota berasal dari SATU perhitungan.
     *
     * Rute jalan selalu lebih panjang daripada garis lurus; kalau petanya
     * digambar dari rute sementara tagihannya dari garis lurus, peta berkata
     * 9 km sambil nota berkata 7 km — dan yang dianggap salah selalu notanya.
     */
    public function test_jarak_yang_ditagih_sama_dengan_rute_yang_digambar(): void
    {
        $this->fakeRute(9_100);
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $estimasi = $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.2441, 'tujuan_lng' => 106.8003,
        ]);

        $estimasi->assertOk();
        $this->assertSame(9.1, $estimasi->json('km'));
        $this->assertTrue($estimasi->json('lewat_jalan'));
        $this->assertCount(3, $estimasi->json('geometri'));
        // GeoJSON menulis [lng, lat]; yang keluar harus sudah [lat, lng].
        $this->assertSame([-6.1754, 106.8272], $estimasi->json('geometri.0'));

        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
        $d = Task::latest('id')->first()->detail_layanan;

        $this->assertSame(9.1, $d['km']);
        $this->assertTrue($d['lewat_jalan']);
        $this->assertCount(3, $d['geometri']);
    }

    /**
     * Penyedia rute mati bukan alasan menolak pesanan: jaraknya kembali ke
     * perkiraan garis lurus, dan layar menyebutkannya sebagai perkiraan.
     */
    public function test_rute_gagal_diambil_jatuh_ke_perkiraan_garis_lurus(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.2441, 'tujuan_lng' => 106.8003,
        ]);

        $res->assertOk();
        $this->assertFalse($res->json('lewat_jalan'));
        $this->assertNull($res->json('geometri'));
        $this->assertGreaterThan(7, $res->json('km'));

        $this->postJson('/api/jemput/checkout', $this->payload())->assertCreated();
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
        $saat = null;

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
                    $h = $tarif->hitung($tipe, $varian, (float) $km, null);

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

    /** Promo berbasis jam dibaca di WIB, bukan di zona server yang UTC. */
    public function test_promo_pagi_berlaku_pada_pagi_wib(): void
    {
        $promo = new PromoJemput;
        $pagi = $promo->cari('PAGI');

        // 01.00 UTC = 08.00 WIB: masuk jendela pagi.
        $this->assertNull($promo->kenapaTidakBisa(
            $pagi, 50_000, false, new \DateTimeImmutable('2026-09-02 01:00:00', new \DateTimeZone('UTC')),
        ));

        // 06.00 UTC = 13.00 WIB: di luar jendela, meski jam servernya "06".
        $this->assertNotNull($promo->kenapaTidakBisa(
            $pagi, 50_000, false, new \DateTimeImmutable('2026-09-02 06:00:00', new \DateTimeZone('UTC')),
        ));
    }

    /* ==================== Permintaan & pengali tarif ==================== */

    /** @return array<string, mixed> */
    private function estimasi(): array
    {
        return $this->postJson('/api/jemput/estimasi', [
            'jemput_lat' => -6.1754, 'jemput_lng' => 106.8272,
            'tujuan_lat' => -6.2441, 'tujuan_lng' => 106.8003,
        ])->json();
    }

    /** Pesanan lain di sekitar titik jemput, dibuat berapa menit yang lalu. */
    private function permintaanSekitar(int $jumlah, float $lat = -6.1760, float $lng = 106.8280, int $menitLalu = 2): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $task = Task::create([
                'nomor_invoice' => 'UJI-'.$i.'-'.bin2hex(random_bytes(2)),
                'customer_id' => User::factory()->create(['role' => 'customer'])->id,
                'category_id' => Category::where('slug', 'bisajemput')->value('id'),
                'tipe' => 'fixed',
                'judul' => 'BisaJemput — uji permintaan',
                'deskripsi' => 'uji',
                'status' => 'pending',
                'lokasi_alamat' => 'Sekitar Monas',
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => 20_000,
                'detail_layanan' => ['layanan' => 'jemput', 'tahap' => 'mencari'],
            ]);

            // created_at tidak fillable, jadi ditulis setelahnya — kalau tidak,
            // "pesanan 20 menit lalu" tetap tercatat sebagai pesanan barusan
            // dan uji jendela waktunya tidak menguji apa pun.
            $task->forceFill(['created_at' => now()->subMinutes($menitLalu)])->saveQuietly();
        }
    }

    /**
     * Saat sepi, tarif TIDAK naik dan tidak ada banner apa pun.
     *
     * Ini keadaan bawaan, dan memang harus begitu: pengali yang muncul
     * sepanjang hari berhenti bermakna dan cuma jadi harga yang lebih mahal.
     */
    public function test_tarif_tidak_naik_saat_sepi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $hasil = $this->estimasi();

        $this->assertNull($hasil['sibuk']);
        $this->assertNull($hasil['sibuk_alasan']);
        $this->assertEqualsWithDelta(1.0, $hasil['sibuk_pengali'], 0.001);

        $baris = array_column($hasil['pilihan'][0]['baris'], 'label');
        $this->assertEmpty(array_filter($baris, fn ($l) => str_contains($l, 'Permintaan tinggi')));
    }

    /**
     * Yang menaikkan tarif adalah PERMINTAAN NYATA di sekitar titik jemput —
     * banyak orang berebut pengemudi yang sama — bukan jam pada kalender dan
     * bukan pula beban server.
     */
    public function test_tarif_naik_saat_banyak_permintaan_di_sekitar(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $sepi = $this->estimasi();
        $this->permintaanSekitar(16);
        $ramai = $this->estimasi();

        $this->assertSame('tinggi', $ramai['sibuk']);
        $this->assertSame(1.25, $ramai['sibuk_pengali']);
        $this->assertStringContainsString('16 orang', $ramai['sibuk_alasan']);
        $this->assertGreaterThan($sepi['pilihan'][0]['tarif'], $ramai['pilihan'][0]['tarif']);

        // Kenaikannya tercantum sebagai barisnya sendiri, bukan diselipkan ke
        // tarif dasar supaya tidak kelihatan.
        $baris = array_column($ramai['pilihan'][0]['baris'], 'label');
        $this->assertNotEmpty(array_filter($baris, fn ($l) => str_contains($l, 'Permintaan tinggi')));
    }

    public function test_pengali_permintaan_tidak_pernah_melewati_batas(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Jauh di atas ambang tertinggi: pengalinya tetap berhenti di 1,25.
        $this->permintaanSekitar(120);

        $this->assertSame(1.25, $this->estimasi()['sibuk_pengali']);
    }

    /** Permintaan di kota sebelah bukan alasan menaikkan tarif di sini. */
    public function test_permintaan_jauh_tidak_menaikkan_tarif(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->permintaanSekitar(20, -6.9175, 107.6191); // Bandung

        $this->assertNull($this->estimasi()['sibuk']);
    }

    /** Begitu pun permintaan yang sudah lewat: ramai tadi bukan ramai sekarang. */
    public function test_permintaan_lama_tidak_dihitung(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->permintaanSekitar(20, -6.1760, 106.8280, PermintaanJemput::JENDELA_MENIT + 5);

        $this->assertNull($this->estimasi()['sibuk']);
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
