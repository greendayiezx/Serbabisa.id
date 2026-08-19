<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\User;
use App\Services\PromoEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromoEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function eval(): PromoEvaluator
    {
        return app(PromoEvaluator::class);
    }

    private function ctx(array $override = []): array
    {
        return array_merge([
            'totalBarang' => 100000,
            'ongkir' => 3500,
            'subtotalKategori' => ['snack' => 100000],
            'transaksiKe' => 1,
            'waktu' => Carbon::create(2026, 8, 20, 10, 0), // Kamis, bukan jam emas/payday/weekend
        ], $override);
    }

    public function test_promo_tier_memberi_potongan_saat_minimum_terpenuhi(): void
    {
        $promo = Promo::create([
            'judul' => 'Diskon 2rb', 'grup' => 'tier', 'min_belanja' => 50000, 'potongan_tetap' => 2000,
        ]);

        $hasil = $this->eval()->evaluate($promo, User::factory()->create(), $this->ctx());

        $this->assertTrue($hasil->berlaku);
        $this->assertEquals(2000, $hasil->potonganBarang);
    }

    public function test_ditolak_saat_di_bawah_minimum(): void
    {
        $promo = Promo::create([
            'judul' => 'Diskon 5rb', 'grup' => 'tier', 'min_belanja' => 200000, 'potongan_tetap' => 5000,
        ]);

        $hasil = $this->eval()->evaluate($promo, User::factory()->create(), $this->ctx(['totalBarang' => 100000]));

        $this->assertFalse($hasil->berlaku);
        $this->assertStringContainsString('lagi', $hasil->alasan);
    }

    public function test_syarat_transaksi_pertama_ditegakkan(): void
    {
        $promo = Promo::create([
            'judul' => 'Pengguna Baru', 'grup' => 'first-order', 'min_belanja' => 50000,
            'potongan_tetap' => 2000, 'syarat' => ['transaksi_ke' => 1],
        ]);
        $user = User::factory()->create();

        $this->assertTrue($this->eval()->evaluate($promo, $user, $this->ctx(['transaksiKe' => 1]))->berlaku);
        $this->assertFalse($this->eval()->evaluate($promo, $user, $this->ctx(['transaksiKe' => 2]))->berlaku);
    }

    public function test_kuota_per_user_ditegakkan_lewat_redemptions(): void
    {
        $promo = Promo::create([
            'judul' => 'Sekali pakai', 'grup' => 'tier', 'min_belanja' => 50000,
            'potongan_tetap' => 2000, 'kuota_per_user' => 1,
        ]);
        $user = User::factory()->create();

        // Belum ada redemption → berlaku.
        $this->assertTrue($this->eval()->evaluate($promo, $user, $this->ctx())->berlaku);

        // Setelah dipakai sekali → ditolak.
        PromoRedemption::create([
            'promo_id' => $promo->id, 'user_id' => $user->id, 'redeemed_at' => now(),
        ]);
        $hasil = $this->eval()->evaluate($promo, $user, $this->ctx());
        $this->assertFalse($hasil->berlaku);
        $this->assertStringContainsString('sudah memakai', $hasil->alasan);
    }

    public function test_bundle_memakai_subtotal_kategori(): void
    {
        $promo = Promo::create([
            'judul' => 'Bundle Fresh', 'grup' => 'bundle', 'min_belanja' => 75000,
            'potongan_tetap' => 1500, 'kategori_bundle' => ['buah', 'sayur'],
        ]);
        $user = User::factory()->create();

        // Total besar tapi kategori bundle < minimum → ditolak.
        $ctxKurang = $this->ctx(['totalBarang' => 200000, 'subtotalKategori' => ['snack' => 200000]]);
        $this->assertFalse($this->eval()->evaluate($promo, $user, $ctxKurang)->berlaku);

        // Subtotal kategori bundle mencukupi → berlaku.
        $ctxCukup = $this->ctx(['subtotalKategori' => ['buah' => 40000, 'sayur' => 40000]]);
        $this->assertTrue($this->eval()->evaluate($promo, $user, $ctxCukup)->berlaku);
    }
}
