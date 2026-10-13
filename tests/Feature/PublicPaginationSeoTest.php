<?php

namespace Tests\Feature;

use App\Models\{Article, Category, CitySpecialtySeoPage, Restaurant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPaginationSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexable_collection_pages_are_self_canonical_and_page_one_stays_clean(): void
    {
        $burger = Category::create(['legacy_term_id' => 7001, 'name' => 'Burger', 'slug' => 'burger']);
        $this->restaurants(13, $burger);
        $this->articles(17);
        CitySpecialtySeoPage::create(['city_code' => '13055', 'category_id' => $burger->id, 'state' => 'open']);

        $this->get('/restaurants?page=1')
            ->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('restaurants.index').'"', false)
            ->assertDontSee('rel="canonical" href="'.route('restaurants.index').'?page=1"', false);

        $this->get('/restaurants?page=2')
            ->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('restaurants.index').'?page=2"', false)
            ->assertSee('<a href="'.route('restaurants.index').'?page=1" rel="prev">Précédent</a>', false);

        $this->get('/blog?page=2')
            ->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('blog.index').'?page=2"', false);

        $this->get('/specialites/burger?page=2')
            ->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('categories.show', 'burger').'?page=2"', false);

        $this->get('/restos/marseille/burger?page=2')
            ->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('rel="canonical" href="'.route('city-specialties.show', ['city' => 'marseille', 'facet' => 'burger']).'?page=2"', false);
    }

    public function test_paginated_filtered_directory_remains_noindex(): void
    {
        $burger = Category::create(['legacy_term_id' => 7101, 'name' => 'Burger', 'slug' => 'burger']);
        $this->restaurants(13, $burger);

        $this->get('/restaurants?categories[]=burger&page=2')
            ->assertOk()
            ->assertSee('content="noindex,follow"', false)
            ->assertSee('rel="canonical" href="'.route('restaurants.index').'"', false)
            ->assertDontSee('rel="canonical" href="'.route('restaurants.index').'?page=2"', false);
    }

    private function restaurants(int $count, Category $category): void
    {
        foreach (range(1, $count) as $number) {
            $restaurant = Restaurant::create([
                'legacy_wp_id' => 7200 + $number,
                'name' => "Restaurant {$number}",
                'slug' => "restaurant-{$number}",
                'status' => 'published',
                'city_name' => 'Marseille',
                'city_code' => '13206',
                'country_code' => 'FR',
            ]);
            $restaurant->categories()->attach($category);
        }
    }

    private function articles(int $count): void
    {
        foreach (range(1, $count) as $number) {
            Article::create([
                'legacy_wp_id' => 7300 + $number,
                'original_title' => "Article {$number}",
                'title' => "Article {$number}",
                'slug' => "article-{$number}",
                'legacy_url' => "/article-{$number}",
                'status' => 'published',
            ]);
        }
    }
}
