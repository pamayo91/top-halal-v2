<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_robots_blocks_claim_urls_without_exposing_a_sitemap_directive(): void
    {
        $this->asProduction(function (): void {
            $response = $this->get('https://top-halal.fr/robots.txt');

            $response->assertOk()
                ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
                ->assertSeeText('User-agent: *')
                ->assertSeeText('Allow: /')
                ->assertSeeText('Disallow: */claim')
                ->assertDontSee('Sitemap:', false);

            $this->assertSame("User-agent: *\nAllow: /\nDisallow: */claim\nDisallow: /login\nDisallow: /forgot-password\nDisallow: /*near_me=*\n", $response->getContent());
        });
    }

    public function test_sitemap_stays_publicly_available_in_production(): void
    {
        $this->asProduction(function (): void {
            $this->get('https://top-halal.fr/sitemap.xml')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->assertSee('<urlset', false);
        });
    }

    public function test_preproduction_keeps_disallow_all_and_noindex_headers(): void
    {
        config()->set('app.url', 'https://top-halal.fr');

        $this->get('https://dev.top-halal.fr/robots.txt')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSeeText('User-agent: *')
            ->assertSeeText('Disallow: /')
            ->assertDontSee('Allow: /', false)
            ->assertDontSee('Sitemap:', false);
    }

    private function asProduction(callable $callback): void
    {
        $environment = $this->app->environment();
        $url = config('app.url');

        try {
            $this->app->instance('env', 'production');
            config()->set('app.url', 'https://top-halal.fr');

            $callback();
        } finally {
            $this->app->instance('env', $environment);
            config()->set('app.url', $url);
        }
    }
}
