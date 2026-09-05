<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Services\KirimTarif;
use App\Services\PromoJemput;
use App\Services\PromoKirim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BisaKirim.
 *
 * Yang dijaga di sini: ongkir tidak bisa diketik pengirim, paket yang tidak
 * muat ditolak sebelum kurir berangkat, isi terlarang ditolak sama sekali, dan
 * premi proteksi tidak pernah ikut jadi komisi atau ikut didiskon.
 */
class KirimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['nama' => 'BisaKirim', 'slug' => 'bisakirim', 'basis_harga' => 'jarak_berat']);

        Cache::flush();
        Http::preventStrayRequests();
        // Bawaannya layanan rute dianggap tidak terjangkau: yang teruji di
        // sebagian besar berkas ini adalah jalur cadangan garis lurus.
        Http::fake(['api.mapbox.com/*' => Http::response([], 500)]);
    }

    /** Monas ke Blok M. */
    private function payload(array $ganti = []): array
    {
        return [
            'kendaraan' => 'motor',
            'ukuran' => 'kecil',
            'isi' => 'Kotak sepatu',
            'ambil_alamat' => 'Monas, Jakarta Pusat',
            'ambil_lat' => -6.1754,
            'ambil_lng' => 106.8272,
            'ambil_nama' => 'Faras',
            'ambil_telepon' => '081200001111',
            'antar_alamat' => 'Blok M, Jakarta Selatan',
            'antar_lat' => -6.2441,
            'antar_lng' => 106.8003,
            'antar_nama' => 'Rina',
            'antar_telepon' => '081200002222',
            'metode' => 'gopay',
            ...$ganti,
        ];
    }

    /* ==================== Ongkir ==================== */

    public function test_ongkir_dihitung_server_bukan_dikirim_klien(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/kirim/checkout', $this->payload(['km' => 1, 'ongkir' => 1000]));

        $res->assertCreated();
        $d = Task::latest('id')->first()->detail_layanan;

        $this->assertGreaterThan(7, $d['km']);
        $this->assertGreaterThan(12_000, $d['ongkir']);
    }

    public function test_estimasi_memberi_dua_kendaraan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/kirim/estimasi', [
            'ambil_lat' => -6.1754, 'ambil_lng' => 106.8272,
            'antar_lat' => -6.2441, 'antar_lng' => 106.8003,
            'ukuran' => 'kecil',
        ]);

        $res->assertOk();
        $pilihan = $res->json('pilihan');

        $this->assertCount(2, $pilihan);
        $this->assertSame('motor', $pilihan[0]['kendaraan']);
        $this->assertLessThan($pilihan[1]['ongkir'], $pilihan[0]['ongkir']);
    }

    public function test_kiriman_dekat_kena_ongkir_minimum(): void
    {
        $tarif = new KirimTarif;
        $hasil = $tarif->hitung('motor', 0.8, 'dokumen');

        $this->assertSame(12_000, $hasil['ongkir']);
    }

    public function test_rute_terlalu_jauh_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/kirim/estimasi', [
            'ambil_lat' => -6.1754, 'ambil_lng' => 106.8272,
            'antar_lat' => -6.9175, 'antar_lng' => 107.6191, // Bandung
            'ukuran' => 'kecil',
        ])->assertStatus(422);
    }

    /* ==================== Batas kendaraan ==================== */

    /**
     * Motor tidak bisa membawa paket besar, dan itu harus ketahuan SEBELUM
     * kurir berangkat — bukan saat ia sudah berdiri di depan pintu.
     */
    public function test_paket_besar_tidak_bisa_pakai_motor(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/kirim/checkout', $this->payload(['ukuran' => 'besar']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kendaraan');

        $this->postJson('/api/kirim/checkout', $this->payload([
            'ukuran' => 'besar', 'kendaraan' => 'mobil',
        ]))->assertCreated();
    }

    public function test_estimasi_menandai_kendaraan_yang_tidak_sanggup(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $pilihan = $this->postJson('/api/kirim/estimasi', [
            'ambil_lat' => -6.1754, 'ambil_lng' => 106.8272,
            'antar_lat' => -6.2441, 'antar_lng' => 106.8003,
            'ukuran' => 'besar',
        ])->json('pilihan');

        $this->assertFalse($pilihan[0]['sanggup']);
        $this->assertNotNull($pilihan[0]['alasan']);
        $this->assertTrue($pilihan[1]['sanggup']);
    }

    /* ==================== Isi terlarang ==================== */

    /**
     * Jawabannya bukan harga yang lebih tinggi. Tidak ada cara aman membawa
     * barang-barang ini dengan kurir motor, dan tidak ada ganti rugi yang
     * sepadan kalau terjadi apa-apa.
     */
    public function test_isi_terlarang_ditolak_bukan_disurcharge(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        foreach (KirimTarif::DILARANG as $larangan) {
            $this->postJson('/api/kirim/checkout', $this->payload(['dilarang' => [$larangan]]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('dilarang');
        }

        $this->assertSame(0, Task::count());
    }

    /* ==================== Proteksi ==================== */

    public function test_premi_proteksi_muncul_dan_punya_minimum(): void
    {
        $tarif = new KirimTarif;

        $this->assertSame(0, $tarif->premiProteksi(0));
        // 1% dari 100.000 = 1.000, di bawah minimum -> naik ke minimum.
        $this->assertSame(KirimTarif::PROTEKSI_MINIMUM, $tarif->premiProteksi(100_000));
        $this->assertSame(10_000, $tarif->premiProteksi(1_000_000));
    }

    public function test_nilai_barang_di_atas_plafon_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/kirim/checkout', $this->payload([
            'nilai_barang' => KirimTarif::PROTEKSI_PLAFON + 1,
        ]))->assertStatus(422)->assertJsonValidationErrors('nilai_barang');
    }

    /**
     * Premi disiapkan untuk mengganti barang orang. Menghitungnya sebagai
     * komisi berarti membelanjakan uang penggantian itu.
     */
    public function test_premi_tidak_ikut_jadi_komisi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $tanpa = $this->postJson('/api/kirim/checkout', $this->payload())->json();
        $dengan = $this->postJson('/api/kirim/checkout', $this->payload(['nilai_barang' => 1_000_000]))->json();

        $this->assertSame(
            (float) $tanpa['payment']['komisi_platform'],
            (float) $dengan['payment']['komisi_platform'],
        );
        $this->assertGreaterThan(0, (float) $dengan['payment']['service_fee']);
        $this->assertSame(0.0, (float) $tanpa['payment']['service_fee']);
    }

    /* ==================== Uang ==================== */

    public function test_setiap_kiriman_menyisakan_margin(): void
    {
        $tarif = new KirimTarif;

        foreach (KirimTarif::idKendaraan() as $kendaraan) {
            foreach ([0.4, 1, 3, 8, 15, 30, 39] as $km) {
                $h = $tarif->hitung($kendaraan, (float) $km, 'dokumen');
                $margin = $h['ongkir'] - $h['biaya'];

                $this->assertGreaterThan(0, $margin, "{$kendaraan} {$km}km rugi");
                $this->assertGreaterThan(
                    $h['ongkir'] * 0.12,
                    $margin,
                    "{$kendaraan} {$km}km margin di bawah 12%",
                );
            }
        }
    }

    /**
     * Voucher pemasaran BOLEH membuat satu kiriman rugi — itu memang dibayar
     * dari anggaran pemasaran, bukan dari margin. Yang dijaga: ruginya
     * berbatas, dan batasnya berlaku untuk setiap kendaraan pada setiap jarak.
     *
     * Tanpa batas ini, satu angka yang diketik terlalu besar di katalog bisa
     * menghabiskan anggaran sebulan dalam sehari tanpa ada yang menyadarinya.
     */
    public function test_rugi_per_kiriman_tidak_pernah_melewati_batas(): void
    {
        $tarif = new KirimTarif;
        $promo = new PromoKirim;
        // Sabtu pagi WIB, supaya voucher berjam dan berhari ikut terhitung.
        $saat = new \DateTimeImmutable('2026-09-05 01:00:00', new \DateTimeZone('UTC'));

        foreach (KirimTarif::idKendaraan() as $kendaraan) {
            foreach ([1, 5, 12, 25, 38] as $km) {
                $h = $tarif->hitung($kendaraan, (float) $km, 'dokumen');

                foreach (PromoKirim::KATALOG as $p) {
                    $potongan = $promo->potongan($p, $h['ongkir'], $h['komisi'], $h['biaya']);
                    $rugi = $h['biaya'] - ($h['ongkir'] - $potongan);

                    $batas = $p['sumber'] === 'akuisisi'
                        ? PromoJemput::BATAS_AKUISISI
                        : PromoKirim::BATAS_RUGI_PER_KIRIMAN;

                    $this->assertLessThanOrEqual(
                        $batas,
                        $rugi,
                        "{$p['kode']} pada {$kendaraan} {$km}km rugi Rp".number_format($rugi, 0, ',', '.'),
                    );
                }
            }
        }
    }

    /**
     * Bagian kurir tidak pernah ikut dipotong voucher.
     *
     * Dialah yang mengeluarkan bensin dan waktunya; potongan yang mengurangi
     * bagiannya berarti mendiskon pekerjaan orang lain.
     */
    public function test_bagian_kurir_tidak_ikut_dipotong(): void
    {
        $tarif = new KirimTarif;
        $promo = new PromoKirim;
        $saat = new \DateTimeImmutable('2026-09-05 01:00:00', new \DateTimeZone('UTC'));

        $h = $tarif->hitung('mobil', 20.0, 'dokumen');
        $bagianKurir = (int) round($h['ongkir'] * KirimTarif::BAGI_MITRA);

        foreach ($promo->tersedia($h['ongkir'], $h['komisi'], $h['biaya'], true, $saat) as $v) {
            // Yang dibayar pelanggan boleh turun sampai di bawah bagian kurir;
            // yang tidak boleh adalah bagian kurirnya sendiri ikut mengecil.
            $this->assertSame(
                $bagianKurir,
                (int) round($h['ongkir'] * KirimTarif::BAGI_MITRA),
                "{$v['kode']} mengubah bagian kurir",
            );
        }
    }

    /** Beban pemasaran dicatat di pesanan, bukan hilang begitu saja. */
    public function test_beban_pemasaran_tercatat_di_pesanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Mobil jarak jauh: ongkirnya di atas Rp50.000, jadi KIRIM10 berlaku.
        $res = $this->postJson('/api/kirim/checkout', $this->payload([
            'kendaraan' => 'mobil',
            'ukuran' => 'besar',
            'antar_lat' => -6.3600,
            'antar_lng' => 106.8300,
            'kode_promo' => 'KIRIM10',
        ]));

        $res->assertCreated();
        $promo = Task::latest('id')->first()->detail_layanan['promo'];

        $this->assertSame('KIRIM10', $promo['kode']);
        $this->assertSame('pemasaran', $promo['sumber']);
        $this->assertArrayHasKey('beban_pemasaran', $promo);
        $this->assertGreaterThanOrEqual(0, $promo['beban_pemasaran']);
    }

    /**
     * Voucher berjam dibaca di WIB, bukan di zona aplikasi yang UTC.
     *
     * "Kirim pagi" yang aktif pukul 13.00 bukan sekadar salah — ia ditawarkan
     * ke orang yang tidak bisa memakainya, lalu ditolak di layar bayar.
     */
    public function test_voucher_berjam_dibaca_di_wib(): void
    {
        $promo = new PromoKirim;
        $pagi = $promo->cari('PAGIKIRIM');

        // 01.00 UTC = 08.00 WIB, Rabu: masuk jendela pagi.
        $this->assertNull($promo->kenapaTidakBisa(
            $pagi, 60_000, false, new \DateTimeImmutable('2026-09-02 01:00:00', new \DateTimeZone('UTC')),
        ));

        // 06.00 UTC = 13.00 WIB: di luar jendela, meski jam servernya "06".
        $this->assertNotNull($promo->kenapaTidakBisa(
            $pagi, 60_000, false, new \DateTimeImmutable('2026-09-02 06:00:00', new \DateTimeZone('UTC')),
        ));
    }

    /**
     * Katalog tidak memuat voucher untuk layanan yang belum ada.
     *
     * BisaKirim baru punya Instant motor dan mobil; voucher Same-Day,
     * Next-Day, atau Cargo akan muncul di daftar, dicoba orang, lalu ditolak
     * tanpa alasan yang masuk akal.
     */
    public function test_tidak_ada_voucher_untuk_layanan_yang_belum_ada(): void
    {
        $kode = array_column(PromoKirim::KATALOG, 'kode');

        foreach (['SAMEDAY20', 'SAMEDAYHEMAT', 'NEXTDAY10', 'CARGO25', 'INSTANTGRATIS'] as $tidakAda) {
            $this->assertNotContains($tidakAda, $kode);
        }
    }

    public function test_voucher_akuisisi_hanya_untuk_kiriman_pertama(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $pertama = $this->postJson('/api/kirim/checkout', $this->payload(['kode_promo' => 'KIRIMBARU20']));
        $pertama->assertCreated();
        $this->assertGreaterThan(0, $pertama->json('rincian.potongan'));

        $this->postJson('/api/kirim/checkout', $this->payload(['kode_promo' => 'KIRIMBARU20']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kode_promo');
    }

    /* ==================== Kode terima ==================== */

    /**
     * Kode dibuat SERVER. Kode yang dibuat di peramban bisa dibaca siapa pun
     * yang melihat layar pengirim, padahal gunanya justru memastikan yang
     * menerima memang orang yang dituju.
     */
    public function test_kode_terima_dibuat_server_dan_hanya_bila_diminta(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $tanpa = $this->postJson('/api/kirim/checkout', $this->payload());
        $tanpa->assertCreated();
        $this->assertNull($tanpa->json('kode_terima'));

        $dengan = $this->postJson('/api/kirim/checkout', $this->payload(['pakai_kode_terima' => true]));
        $dengan->assertCreated();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $dengan->json('kode_terima'));
    }

    /* ==================== Pesanan ==================== */

    public function test_titik_ambil_jadi_lokasi_tugas(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/kirim/checkout', $this->payload())->assertCreated();

        $task = Task::latest('id')->first();
        $this->assertSame('Monas, Jakarta Pusat', $task->lokasi_alamat);
        $this->assertSame('Blok M, Jakarta Selatan', $task->detail_layanan['antar']['alamat']);
        $this->assertSame('Rina', $task->nama_penerima);
    }

    public function test_kiriman_orang_lain_tidak_bisa_dibuka(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/kirim/checkout', $this->payload())->assertCreated();
        $nomor = Task::latest('id')->first()->nomor_invoice;

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/kirim/{$nomor}")->assertNotFound();
    }

    /**
     * Katalog voucher untuk layar beranda: syaratnya saja, tanpa angka
     * potongan — di layar itu rutenya belum ada, jadi potongannya memang belum
     * bisa dihitung.
     */
    public function test_katalog_voucher_tanpa_angka_potongan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->getJson('/api/kirim/voucher');

        $res->assertOk();
        $res->assertJsonPath('kiriman_pertama', true);
        $this->assertCount(count(PromoKirim::KATALOG), $res->json('voucher'));
        $this->assertArrayNotHasKey('potongan', $res->json('voucher.0'));
        $this->assertSame(count(PromoKirim::KATALOG), $res->json('jumlah'));
    }

    /** Voucher sekali pakai yang sudah terpakai tetap tampil, tapi ditandai. */
    public function test_voucher_terpakai_ditandai_bukan_dihilangkan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson('/api/kirim/checkout', $this->payload())->assertCreated();

        $res = $this->getJson('/api/kirim/voucher');

        $res->assertJsonPath('kiriman_pertama', false);
        $this->assertCount(count(PromoKirim::KATALOG), $res->json('voucher'));
        $this->assertTrue(
            collect($res->json('voucher'))->firstWhere('kode', 'KIRIMBARU20')['terpakai'],
        );
        $sekaliPakai = count(array_filter(
            PromoKirim::KATALOG,
            fn ($p) => $p['sekali_seumur_hidup'] ?? false,
        ));
        $this->assertSame(count(PromoKirim::KATALOG) - $sekaliPakai, $res->json('jumlah'));
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/kirim/checkout', $this->payload())->assertUnauthorized();
    }
}
