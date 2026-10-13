<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_responses_receive_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-eval'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_admin_responses_allow_only_filament_required_eval(): void
    {
        $response = $this->get('/admin/login');

        $this->assertStringContainsString("'unsafe-eval'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('https://unpkg.com', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_non_canonical_hosts_stay_noindex(): void
    {
        config()->set('app.url', 'https://top-halal.fr');

        $this->get('https://prod-preview.top-halal.fr/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
