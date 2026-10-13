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
        $this->assertStringContainsString('https://ui-avatars.com', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_production_preview_redirects_to_the_canonical_site(): void
    {
        config()->set('app.url', 'https://top-halal.fr');

        $this->get('https://prod-preview.top-halal.fr/contact?source=preview')
            ->assertRedirect('https://top-halal.fr/contact?source=preview');
    }

    public function test_dev_host_is_not_redirected_to_production(): void
    {
        config()->set('app.url', 'https://top-halal.fr');

        $this->get('https://dev.top-halal.fr/admin/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
