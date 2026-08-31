<?php

namespace Tests\Feature;

use App\Models\{Page, Restaurant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapAndSeoTest extends TestCase
{
    use RefreshDatabase;
    public function test_sitemap_contains_only_canonical_public_urls(): void
    {
        Restaurant::create(['legacy_wp_id' => 1, 'name' => 'Le Test', 'slug' => 'le-test', 'status' => 'published']);
        Restaurant::create(['legacy_wp_id' => 3, 'name' => 'Masqué', 'slug' => 'masque', 'status' => 'published', 'seo_robots' => 'noindex,nofollow']);
        Restaurant::create(['legacy_wp_id' => 5, 'name' => 'Aucun', 'slug' => 'aucun', 'status' => 'published', 'seo_robots' => 'none']);
        Page::create(['legacy_wp_id' => 2, 'original_title' => 'Private', 'title' => 'Private', 'slug' => 'private', 'status' => 'published', 'legacy_url' => '/private', 'seo_robots' => 'noindex,follow']);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee(route('restaurants.show', 'le-test'), false)->assertDontSee(route('restaurants.show', 'masque'), false)->assertDontSee(route('restaurants.show', 'aucun'), false)->assertDontSee(route('editorial.show', 'private'), false);
    }
    public function test_404_is_not_indexable(): void { $this->get('/does-not-exist')->assertNotFound()->assertSee('noindex,follow', false); }
    public function test_restaurant_page_has_one_visible_aggregate_rating_schema(): void
    {
        Restaurant::create(['legacy_wp_id' => 2, 'name' => 'Le Test', 'slug' => 'le-test', 'status' => 'published']);
        $this->get('/resto/le-test')->assertOk()->assertSee('application/ld+json', false)->assertSee('"@type":"Restaurant"', false)->assertDontSee('AggregateRating', false);
    }

    public function test_restaurant_page_renders_configured_robots_directives(): void
    {
        Restaurant::create([
            'legacy_wp_id' => 4,
            'name' => 'Directive Test',
            'slug' => 'directive-test',
            'status' => 'published',
            'seo_robots' => 'noindex,nosnippet,max-invalid',
            'seo_max_snippet' => 120,
            'seo_max_image_preview' => 'large',
            'seo_max_video_preview' => 0,
            'seo_unavailable_after' => '2030-01-02 03:04:05',
        ]);

        $this->get('/resto/directive-test')->assertOk()->assertSee('content="noindex,nosnippet,max-snippet:120,max-image-preview:large,max-video-preview:0,unavailable_after: 2030-01-02T03:04:05+00:00"', false);
    }
}
