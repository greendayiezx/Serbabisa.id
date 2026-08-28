<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskFotoTest extends TestCase
{
    use RefreshDatabase;

    private function tugas(User $customer): Task
    {
        $kategori = Category::create([
            'nama' => 'BisaBersih', 'slug' => 'bisabersih', 'basis_harga' => 'durasi',
        ]);

        return Task::create([
            'customer_id' => $customer->id,
            'category_id' => $kategori->id,
            'tipe' => 'custom',
            'judul' => 'Bersih Kantor — Penawaran',
            'deskripsi' => 'spesifikasi',
            'status' => 'pending',
            'lokasi_alamat' => 'Jl. Uji 1',
            'lokasi_lat' => -6.2,
            'lokasi_lng' => 106.8,
        ]);
    }

    public function test_pemilik_bisa_mengunggah_foto(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->tugas($customer);
        Sanctum::actingAs($customer);

        $res = $this->postJson("/api/tasks/{$task->id}/foto", [
            'foto' => [
                UploadedFile::fake()->image('area-1.jpg'),
                UploadedFile::fake()->image('area-2.png'),
            ],
        ]);

        $res->assertCreated()->assertJsonCount(2, 'foto');

        $tersimpan = $task->fresh()->foto;
        $this->assertCount(2, $tersimpan);
        foreach ($tersimpan as $jalur) {
            Storage::disk('public')->assertExists($jalur);
            // Nama asli dari klien tidak dipakai sebagai nama berkas.
            $this->assertStringStartsWith("tasks/{$task->id}/", $jalur);
        }
    }

    public function test_unggahan_kedua_menambah_bukan_mengganti(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->tugas($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/tasks/{$task->id}/foto", ['foto' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertCreated();
        $this->postJson("/api/tasks/{$task->id}/foto", ['foto' => [UploadedFile::fake()->image('b.jpg')]])
            ->assertCreated()
            ->assertJsonCount(2, 'foto');
    }

    public function test_jumlah_foto_dibatasi(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->tugas($customer);
        Sanctum::actingAs($customer);

        $tujuh = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 7));
        $this->postJson("/api/tasks/{$task->id}/foto", ['foto' => $tujuh])->assertStatus(422);
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->tugas($customer);
        Sanctum::actingAs($customer);

        $this->postJson("/api/tasks/{$task->id}/foto", [
            'foto' => [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
        ])->assertStatus(422);
    }

    public function test_orang_lain_tidak_bisa_menambah_foto(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer']);
        $task = $this->tugas($customer);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->postJson("/api/tasks/{$task->id}/foto", ['foto' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertStatus(403);
    }

    public function test_butuh_login(): void
    {
        Storage::fake('public');
        $task = $this->tugas(User::factory()->create(['role' => 'customer']));

        $this->postJson("/api/tasks/{$task->id}/foto", ['foto' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertUnauthorized();
    }
}
