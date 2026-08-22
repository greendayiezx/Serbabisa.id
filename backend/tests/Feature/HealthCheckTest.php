<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke test paling dasar: memastikan API hidup.
 *
 * Ini "canary" untuk CI — kalau ini gagal, biasanya ada yang rusak di
 * bootstrap aplikasi (config, routing, .env), bukan di logika bisnis.
 */
class HealthCheckTest extends TestCase
{
    public function test_ping_endpoint_mengembalikan_status_ok(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
