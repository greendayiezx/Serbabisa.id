<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test untuk FR-16 (listing kategori publik).
 *
 * Menunjukkan dua teknik QA: (1) memakai seeder nyata sebagai fixture,
 * (2) menguji aturan bisnis "hanya kategori aktif yang tampil".
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_categories_mengembalikan_kategori_dari_seeder(): void
    {
        $this->seed(CategorySeeder::class);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(6)
            ->assertJsonFragment(['nama' => 'BisaBelanja']);
    }

    public function test_kategori_nonaktif_tidak_ikut_tampil(): void
    {
        $this->seed(CategorySeeder::class);
        Category::where('nama', 'BisaTukang')->update(['is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(5)
            ->assertJsonMissing(['nama' => 'BisaTukang']);
    }
}
