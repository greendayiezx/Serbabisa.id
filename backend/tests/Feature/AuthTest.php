<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test untuk FR-01 (Auth).
 *
 * RefreshDatabase memigrasikan skema ke SQLite in-memory sebelum tiap test,
 * lalu me-rollback sesudahnya — jadi tiap test berjalan di DB bersih & cepat.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_bisa_register_dan_mendapat_token(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'customer',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        // Verifikasi efek samping: user & wallet benar-benar tersimpan.
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
        $this->assertDatabaseHas('wallets', ['user_id' => $response->json('user.id')]);

        // Password tidak boleh bocor di response.
        $response->assertJsonMissingPath('user.password');
    }

    public function test_register_menolak_email_duplikat(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Orang Baru',
            'email' => 'ada@example.com',
            'phone' => '081200000000',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_berhasil_dengan_kredensial_benar(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password-salah',
        ]);

        $response->assertStatus(401);
    }

    public function test_endpoint_me_butuh_autentikasi(): void
    {
        // Tanpa token → harus ditolak 401 (bukan 200/500).
        $this->getJson('/api/me')->assertStatus(401);
    }
}
