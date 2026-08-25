<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_returns_neutral_bootstrap_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Top Halal V2');
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
