<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Models\MitraProfile;
use App\Models\Wallet;
use App\Services\BersihTarif;
use App\Services\LevelCleaner;
use App\Services\WalletLedger;
use App\Services\NomorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Checkout BisaBersih.
 *
 * Inti yang dijaga: klien hanya mengirim PILIHAN, dan server menentukan sendiri
 * seluruh angka — termasuk apakah promo pengguna baru berhak dipakai.
 */
class BersihCheckoutTest extends TestCase
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
            'kondisi' => 'normal',
            'durasi_jam' => 3,
            'jumlah_cleaner' => 1,
            'add_on' => [],
            'frekuensi' => 'sekali',
            'tipe_properti' => 'Rumah',
            'kamar_tidur' => 2,
            'kamar_mandi' => 1,
            'luas_m2' => 45,
            'ada_hewan' => false,
            'area' => ['Ruang Tamu', 'Dapur'],
            'akses_masuk' => 'Saya di rumah',
            'tanggal' => '2026-09-01',
            'waktu' => '10:00',
            'catatan' => 'fokus dapur',
            'lokasi_alamat' => 'Jl. Uji 1',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
            ...$ganti,
        ];
    }

    public function test_harga_dihitung_dari_pilihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload());

        // Level terendah Rp50.000/jam x 3 jam = 150.000, + perjalanan 20.000.
        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', 150000);
        $res->assertJsonPath('rincian.perjalanan', 20000);
        $res->assertJsonPath('rincian.nilai_transaksi', 170000);
    }

    public function test_pengali_kondisi_diterapkan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload([
            'kondisi' => 'sangat', 'durasi_jam' => 4, 'jumlah_cleaner' => 2,
        ]));

        // 50.000 x 4 jam x 2 cleaner x 1,3 (sangat kotor)
        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', (int) round(50000 * 4 * 2 * 1.3));
    }

    public function test_harga_kiriman_klien_diabaikan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload([
            'harga' => 1, 'total' => 1, 'potongan_promo' => 999999,
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.layanan', 150000);
        // Rp170.000 masih di bawah ambang promo terendah (Rp180.000),
        // jadi tidak ada potongan yang berlaku.
        $this->assertSame('170000.00', Task::first()->harga);
    }

    public function test_promo_pengguna_baru_hanya_untuk_pesanan_pertama(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $pertama = $this->postJson('/api/bersih/checkout', $this->payload());
        // 170.000 hanya melewati ambang BERSIHBARU40 (Rp180.000)? Tidak —
        // di bawahnya, jadi tidak ada promo pengguna baru yang layak.
        $pertama->assertCreated()->assertJsonPath('rincian.promo_kode', null);

        $kedua = $this->postJson('/api/bersih/checkout', $this->payload());
        $kedua->assertCreated()->assertJsonPath('rincian.promo_kode', null);
        $kedua->assertJsonPath('rincian.potongan_promo', 0);
    }

    public function test_promo_mengikuti_nilai_transaksi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // 4 jam x 2 cleaner = 400.000 + 20.000 = 420.000 → lolos BERSIHBARU60.
        $this->postJson('/api/bersih/checkout', $this->payload(['durasi_jam' => 4, 'jumlah_cleaner' => 2]))
            ->assertCreated()
            ->assertJsonPath('rincian.promo_kode', 'BERSIHBARU60');
    }

    public function test_add_on_ditambahkan_sebagai_baris_sendiri(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload(['add_on' => ['sofa', 'kaca']]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.add_on', 80000);
        // 1 baris layanan + 2 baris add-on
        $this->assertCount(3, $res->json('items'));
    }

    public function test_add_on_kembar_tidak_dihitung_dua_kali(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/checkout', $this->payload(['add_on' => ['sofa', 'sofa', 'sofa']]))
            ->assertCreated()
            ->assertJsonPath('rincian.add_on', 50000);
    }

    public function test_diskon_frekuensi_dihitung_dari_harga_layanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload(['frekuensi' => 'mingguan']));

        // 15% dari 150.000 = 22.500 — add-on & perjalanan tidak ikut didiskon.
        $res->assertCreated();
        $res->assertJsonPath('rincian.diskon_frekuensi', 22500);
    }

    public function test_detail_layanan_tersimpan_sebagai_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/checkout', $this->payload(['ada_hewan' => true]))->assertCreated();

        $detail = Task::first()->detail_layanan;
        $this->assertSame('Rumah', $detail['tipe_properti']);
        $this->assertSame(45, $detail['luas_m2']);
        $this->assertTrue($detail['ada_hewan']);
        $this->assertSame(['Ruang Tamu', 'Dapur'], $detail['area']);
    }

    public function test_nomor_invoice_diterbitkan_dan_valid(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $nomor = $this->postJson('/api/bersih/checkout', $this->payload())->json('nomor_invoice');

        $this->assertMatchesRegularExpression('/^SBB-\d{6}-[0-9A-Z]{7}$/', $nomor);
        $this->assertTrue(NomorInvoice::valid($nomor));
    }

    public function test_pilihan_tak_dikenal_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        foreach ([
            ['kondisi' => 'kinclong'],
            ['durasi_jam' => 99],
            ['jumlah_cleaner' => BersihTarif::MAKS_CLEANER + 1],
            ['add_on' => ['emas']],
            ['frekuensi' => 'tiap-jam'],
            ['area' => []],
            ['luas_m2' => 0],
        ] as $ganti) {
            $this->postJson('/api/bersih/checkout', $this->payload($ganti))->assertStatus(422);
        }
    }

    public function test_pesanan_tanpa_alamat_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $data = $this->payload();
        unset($data['lokasi_alamat'], $data['lokasi_lat'], $data['lokasi_lng']);

        $this->postJson('/api/bersih/checkout', $data)->assertStatus(422);
    }

    public function test_promo_pilihan_pengguna_dipakai_kalau_layak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // MERDEKA17 bukan promo pengguna baru: harus dipakai walau ini pesanan pertama.
        $this->postJson('/api/bersih/checkout', $this->payload(['durasi_jam' => 4, 'promo_kode' => 'MERDEKA17']))
            ->assertCreated()
            ->assertJsonPath('rincian.promo_kode', 'MERDEKA17')
            ->assertJsonPath('rincian.potongan_promo', 17000);
    }

    public function test_cashback_tidak_memotong_tagihan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload(['durasi_jam' => 4, 'jumlah_cleaner' => 2, 'promo_kode' => 'CASHBACK10']));

        // Cashback jadi saldo setelah pesanan selesai, bukan potongan sekarang.
        // Memperlakukannya sebagai potongan akan mengurangi tagihan dua kali.
        $res->assertCreated();
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $res->assertJsonPath('rincian.cashback', 30000);
        $res->assertJsonPath('rincian.total', 420000);
    }

    public function test_promo_belum_memenuhi_minimum_dilepas_bukan_menolak_pesanan(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // 2 jam = 190.000 + 20.000 = 210.000, di bawah minimum TAHUNBARU (300.000).
        $res = $this->postJson('/api/bersih/checkout', $this->payload([
            'durasi_jam' => 2, 'promo_kode' => 'TAHUNBARU',
        ]));

        $res->assertCreated();
        $res->assertJsonPath('rincian.promo_kode', null);
        $res->assertJsonPath('rincian.potongan_promo', 0);
        $this->assertStringContainsString('lagi', $res->json('rincian.promo_ditolak'));
    }

    public function test_promo_pengguna_baru_ditolak_di_pesanan_kedua(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/checkout', $this->payload())->assertCreated();

        $res = $this->postJson('/api/bersih/checkout', $this->payload(['durasi_jam' => 4, 'jumlah_cleaner' => 2, 'promo_kode' => 'BERSIHBARU50']));
        $res->assertCreated();
        $res->assertJsonPath('rincian.promo_kode', null);
        $this->assertStringContainsString('pertama', $res->json('rincian.promo_ditolak'));
    }

    public function test_kode_promo_asing_dilepas(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/checkout', $this->payload(['promo_kode' => 'GRATISSELAMANYA']))
            ->assertCreated()
            ->assertJsonPath('rincian.promo_kode', null)
            ->assertJsonPath('rincian.promo_ditolak', 'Kode promo tidak dikenal.');
    }

    public function test_tanpa_pilih_cleaner_dihargai_level_terendah(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $res = $this->postJson('/api/bersih/checkout', $this->payload());

        // Level 1 = Rp40.000 upah + Rp10.000 markup = Rp50.000/jam x 3 jam.
        $res->assertCreated();
        $res->assertJsonPath('rincian.level_cleaner', LevelCleaner::LEVEL_TERENDAH);
        $res->assertJsonPath('rincian.layanan', 150000);
        $res->assertJsonPath('rincian.upah_cleaner', 120000);
        $res->assertJsonPath('rincian.markup_platform', 30000);
    }

    public function test_markup_tetap_sama_di_semua_level(): void
    {
        // Naiknya level menambah bagian cleaner, bukan mengurangi bagian platform.
        foreach (LevelCleaner::jenjang() as $j) {
            $this->assertSame(
                LevelCleaner::MARKUP_PER_JAM,
                $j['harga'] - $j['tarif'],
                "markup level {$j['level']} berubah",
            );
        }
    }

    public function test_level_dihitung_dari_ulasan_bukan_diisi_manual(): void
    {
        // Cleaner baru: belum ada ulasan sama sekali.
        $this->assertSame(1, LevelCleaner::levelDari(0, 0.0));

        // Cukup order tapi ratingnya di bawah ambang → tidak naik.
        $this->assertSame(1, LevelCleaner::levelDari(50, 3.9));

        // Rating bagus tapi ulasan belum cukup → juga tidak naik.
        $this->assertSame(1, LevelCleaner::levelDari(10, 5.0));

        $this->assertSame(2, LevelCleaner::levelDari(20, 4.0));
        $this->assertSame(3, LevelCleaner::levelDari(60, 4.5));
        $this->assertSame(4, LevelCleaner::levelDari(150, 4.8));
    }

    public function test_cleaner_yang_dipilih_menentukan_tarif(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $mitra = User::factory()->create(['role' => 'mitra']);
        $profil = MitraProfile::create([
            'user_id' => $mitra->id,
            'no_ktp' => '327101'.$mitra->id,
            'foto_ktp' => 'ktp.jpg',
            'rating_avg' => 4.9,
            'rating_count' => 160,
        ]);

        $res = $this->postJson('/api/bersih/checkout', $this->payload(['cleaner_id' => $profil->user_id]));

        // 160 ulasan, rating 4,9 → level 4 (Rp70.000 + markup Rp10.000).
        $res->assertCreated();
        $res->assertJsonPath('rincian.level_cleaner', 4);
        $res->assertJsonPath('rincian.layanan', 240000);
        $res->assertJsonPath('rincian.upah_cleaner', 210000);
        // Markup tetap Rp30.000 walau harganya jauh lebih tinggi.
        $res->assertJsonPath('rincian.markup_platform', 30000);
    }

    public function test_cleaner_asing_ditolak(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/bersih/checkout', $this->payload(['cleaner_id' => 999999]))
            ->assertStatus(422);
    }

    public function test_bonus_naik_level_dibayar_sekali_per_tingkat(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $profil = MitraProfile::create([
            'user_id' => $mitra->id,
            'no_ktp' => '327102'.$mitra->id,
            'foto_ktp' => 'ktp.jpg',
            'rating_avg' => 4.6,
            'rating_count' => 70,
        ]);

        $level = app(LevelCleaner::class);
        $ledger = app(WalletLedger::class);

        // Level 3: melewati tingkat 2 dan 3 sekaligus → dua bonus.
        $this->assertSame(3, LevelCleaner::levelMitra($profil));
        $this->assertSame(2 * LevelCleaner::BONUS_NAIK_LEVEL, $level->bayarBonusNaikLevel($profil, $ledger));

        // Dipanggil ulang tidak menambah apa pun.
        $this->assertSame(0, $level->bayarBonusNaikLevel($profil, $ledger));
        $this->assertEquals(
            2 * LevelCleaner::BONUS_NAIK_LEVEL,
            Wallet::where('user_id', $mitra->id)->value('saldo'),
        );
    }

    public function test_daftar_cleaner_kosong_selama_belum_ada_mitra(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        // Tidak mengarang orang: belum ada mitra berarti daftarnya memang kosong.
        $this->getJson('/api/bersih/cleaner')
            ->assertOk()
            ->assertJsonCount(0, 'cleaner')
            ->assertJsonPath('markup_per_jam', LevelCleaner::MARKUP_PER_JAM);
    }

    public function test_tarif_level_sama_dengan_salinan_di_frontend(): void
    {
        // frontend/src/lib/bersih/hargaBersih.ts menyimpan salinan tarif ini supaya
        // `npm run cek:laba` bisa jalan tanpa menyalakan backend. Kalau tarif
        // di sini diubah, salinan itu HARUS ikut diubah — kalau tidak, laporan
        // labanya menghitung angka yang sudah tidak berlaku.
        $berkas = base_path('../frontend/src/lib/bersih/hargaBersih.ts');
        $this->assertFileExists($berkas);

        preg_match('/HARGA_PER_JAM_LEVEL = \[([^\]]+)\]/', file_get_contents($berkas), $m);
        $this->assertNotEmpty($m, 'HARGA_PER_JAM_LEVEL tidak ditemukan di hargaBersih.ts');

        $salinan = array_map(
            fn ($v) => (int) str_replace('_', '', trim($v)),
            explode(',', $m[1]),
        );

        $server = array_map(
            fn ($j) => $j['harga'],
            LevelCleaner::jenjang(),
        );

        $this->assertSame(
            $server,
            $salinan,
            'Tarif di LevelCleaner dan salinannya di frontend/src/lib/bersih/hargaBersih.ts berbeda.',
        );
    }

    public function test_butuh_login(): void
    {
        $this->postJson('/api/bersih/checkout', $this->payload())->assertUnauthorized();
    }
}
