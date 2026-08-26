<?php

namespace Tests\Feature;

use App\Models\{Page, RedirectRule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_approved_technical_pages_without_deleting_migration_records(): void
    {
        foreach (['home' => 1, 'blog-2' => 2, 'payment-success-2' => 3, 'blog' => 4, 'mon-compte' => 5] as $slug => $legacyId) {
            Page::create(['legacy_wp_id' => $legacyId, 'original_title' => $slug, 'title' => $slug, 'slug' => $slug, 'status' => 'published', 'legacy_url' => '/'.$slug]);
        }
        $this->artisan('seo:apply-sitemap-cleanup')->assertSuccessful();
        $this->assertSame('redirected', Page::where('slug', 'home')->value('status'));
        $this->get('/home')->assertRedirect('/');
        $this->get('/blog-2')->assertRedirect('/blog');
        $this->assertSame('/', RedirectRule::where('source_path', '/payment-success-2')->value('destination'));
        $this->assertSame('published', Page::where('slug', 'mon-compte')->value('status'));
        $this->assertSame('noindex,follow', Page::where('slug', 'mon-compte')->value('seo_robots'));
        $this->assertNull(Page::where('slug', 'blog')->value('seo_robots'));
    }
}
