<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapAndSeoTest extends TestCase
{
    use RefreshDatabase;
    public function test_sitemap_contains_only_canonical_public_urls(): void
    {
        Restaurant::create(['legacy_wp_id' => 1, 'name' => 'Le Test', 'slug' => 'le-test', 'status' => 'published']);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee(route('restaurants.show', 'le-test'), false);
    }
    public function test_404_is_not_indexable(): void { $this->get('/does-not-exist')->assertNotFound()->assertSee('noindex,follow', false); }
}
