<?php

namespace Tests\Feature;

use App\Models\{Article, Category, Menu, MenuItem, Page, Restaurant};
use App\Services\PublicNavigation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void { parent::setUp(); Cache::flush(); }

    public function test_header_renders_active_items_in_order_and_honours_surface_visibility(): void
    {
        $menu = Menu::where('location', 'header_main')->firstOrFail();
        MenuItem::where('menu_id', $menu->id)->delete();
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Deuxième', 'link_type' => 'internal_url', 'url' => '/blog', 'sort_order' => 2, 'visible_mobile' => false]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Premier', 'link_type' => 'internal_url', 'url' => '/restaurants', 'sort_order' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Inactif', 'link_type' => 'internal_url', 'url' => '/blog', 'sort_order' => 3, 'is_active' => false]);

        $this->get('/')->assertOk()->assertSeeInOrder(['Premier', 'Deuxième'])->assertDontSee('Inactif');
        $this->assertSame(['Premier'], array_column(app(PublicNavigation::class)->header()['mobile_menu']['items'], 'label'));
    }

    public function test_model_and_city_destinations_use_v2_routes_and_unavailable_content_is_ignored(): void
    {
        $menu = Menu::where('location', 'header_main')->firstOrFail();
        MenuItem::where('menu_id', $menu->id)->delete();
        $page = Page::create(['legacy_wp_id' => 901, 'original_title' => 'Page', 'title' => 'Page', 'slug' => 'page-navigation', 'legacy_url' => '/page-navigation', 'content_html' => '<p>ok</p>', 'status' => 'published']);
        $article = Article::create(['legacy_wp_id' => 902, 'original_title' => 'Article', 'title' => 'Article', 'slug' => 'article-navigation', 'legacy_url' => '/article-navigation', 'content_html' => '<p>ok</p>', 'status' => 'published']);
        $category = Category::create(['legacy_term_id' => 903, 'name' => 'Cuisine test', 'slug' => 'cuisine-test']);
        Restaurant::create(['legacy_wp_id' => 904, 'name' => 'Ville test', 'slug' => 'ville-test', 'status' => 'published', 'city_name' => 'Paris', 'city_code' => '75056']);
        foreach ([['Page', 'page', $page->id], ['Article', 'article', $article->id], ['Cuisine', 'category', $category->id]] as [$label, $type, $id]) MenuItem::create(['menu_id' => $menu->id, 'label' => $label, 'link_type' => $type, 'linkable_id' => $id]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Ville', 'link_type' => 'city', 'destination_key' => 'paris']);
        $draft = Page::create(['legacy_wp_id' => 905, 'original_title' => 'Brouillon', 'title' => 'Brouillon', 'slug' => 'brouillon-navigation', 'legacy_url' => '/brouillon-navigation', 'content_html' => '<p>no</p>', 'status' => 'draft']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Brouillon', 'link_type' => 'page', 'linkable_id' => $draft->id]);

        $this->get('/')->assertOk()->assertSee('/page-navigation', false)->assertSee('/article-navigation', false)->assertSee('/specialites/cuisine-test', false)->assertSee('/restos/paris', false)->assertDontSee('Brouillon');
    }

    public function test_submenus_and_link_security_are_rendered_without_broken_links(): void
    {
        $menu = Menu::where('location', 'header_main')->firstOrFail();
        MenuItem::where('menu_id', $menu->id)->delete();
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label' => 'Cuisines', 'link_type' => 'none']);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Burger', 'link_type' => 'external_url', 'url' => 'https://example.test', 'target_blank' => true, 'nofollow' => true]);

        $this->get('/')->assertOk()->assertSee('aria-controls="desktop-submenu-', false)->assertSee('noopener noreferrer nofollow', false);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Dangereux', 'link_type' => 'internal_url', 'url' => 'javascript:alert(1)']);
    }
}
