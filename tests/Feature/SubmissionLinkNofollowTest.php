<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SubmissionLinkNofollowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_static_submission_links_are_nofollow_on_the_homepage_and_account_dashboard(): void
    {
        $url = route('restaurant-submissions.create');

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertSame(3, substr_count($home, 'href="'.$url.'" rel="nofollow"'));

        $dashboard = $this->actingAs(User::factory()->create())
            ->get(route('account.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertSame(4, substr_count($dashboard, 'href="'.$url.'" rel="nofollow"'));
    }

    public function test_navigation_submission_links_are_nofollow_and_preserve_existing_rel_tokens(): void
    {
        $menu = Menu::where('location', 'header_main')->firstOrFail();
        MenuItem::where('menu_id', $menu->id)->delete();

        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'Proposer',
            'link_type' => 'external_url',
            'url' => 'https://top-halal.fr/ajouter-un-restaurant',
            'target_blank' => true,
        ]);
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Plus', 'link_type' => 'none']);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Ajouter', 'link_type' => 'internal_url', 'url' => '/ajouter-un-restaurant']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Blog', 'link_type' => 'internal_url', 'url' => '/blog']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="https://top-halal.fr/ajouter-un-restaurant"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);
        $this->assertStringContainsString('href="/ajouter-un-restaurant" rel="nofollow"', $html);
        $this->assertStringContainsString('href="/blog"', $html);
        $this->assertStringNotContainsString('href="/blog" rel="nofollow"', $html);
    }
}
