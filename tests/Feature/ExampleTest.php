<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseMigrations;

    public function test_homepage_returns_public_search_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Trouvez votre restaurant halal, simplement.');
    }

    public function test_health_endpoint_returns_success(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'service' => 'top-halal-v2',
        ]);
    }
}
