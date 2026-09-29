<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Restaurant;
use App\Services\ContentSanitizer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EditorialRestaurantsTableTest extends TestCase
{
    use DatabaseMigrations;

    public function test_one_restaurant_is_rendered_as_a_server_rendered_desktop_table_and_mobile_card(): void
    {
        $restaurant = $this->restaurant();
        $article = $this->article('[restaurants_table ids="'.$restaurant->id.'"]');

        $this->get('/'.$article->slug)
            ->assertOk()
            ->assertSee('editorial-restaurants-table', false)
            ->assertSee('<th scope="col">Restaurant</th>', false)
            ->assertSee('<th scope="col">Ville</th>', false)
            ->assertSee('<th scope="col">Adresse</th>', false)
            ->assertSee('<th scope="col">Certification</th>', false)
            ->assertSee(route('restaurants.show', $restaurant->slug), false)
            ->assertSee('10 rue du Test, 75001 Paris')
            ->assertSee('Halal ✓')
            ->assertSee('editorial-restaurants-table-mobile', false)
            ->assertDontSee('[restaurants_table', false);
    }

    public function test_several_restaurants_keep_the_shortcode_order_and_deduplicate_ids(): void
    {
        $first = $this->restaurant(['name' => 'Premier restaurant', 'slug' => 'premier-restaurant']);
        $second = $this->restaurant(['name' => 'Deuxième restaurant', 'slug' => 'deuxieme-restaurant']);
        $third = $this->restaurant(['name' => 'Troisième restaurant', 'slug' => 'troisieme-restaurant']);
        $article = $this->article('[restaurants_table ids="'.$third->id.','.$first->id.','.$second->id.','.$first->id.'"]');

        $response = $this->get('/'.$article->slug)->assertOk();

        $response->assertSeeInOrder([$third->name, $first->name, $second->name]);
        $this->assertSame(2, substr_count($response->getContent(), $first->name));
    }

    public function test_a_large_unbounded_list_renders_every_valid_restaurant(): void
    {
        $restaurants = collect(range(1, 80))->map(fn (int $number) => $this->restaurant([
            'name' => 'Restaurant '.$number,
            'slug' => 'restaurant-'.$number,
        ]));
        $article = $this->article('[restaurants_table ids="'.$restaurants->pluck('id')->implode(',').'"]');

        $response = $this->get('/'.$article->slug)->assertOk();

        foreach ($restaurants as $restaurant) $response->assertSee($restaurant->name);
        $this->assertSame(160, substr_count($response->getContent(), 'Halal ✓'));
    }

    public function test_current_restaurant_name_city_and_structured_address_are_read_at_render_time(): void
    {
        $restaurant = $this->restaurant(['address' => 'Adresse legacy interdite']);
        $article = $this->article('[restaurants_table ids="'.$restaurant->id.'"]');
        $restaurant->update([
            'name' => 'Nom mis à jour',
            'city_name' => 'Lyon',
            'address_line1' => '55 rue actuelle',
            'postal_code' => '69001',
        ]);

        $this->get('/'.$article->slug)
            ->assertOk()
            ->assertSee('Nom mis à jour')
            ->assertSee('Lyon')
            ->assertSee('55 rue actuelle, 69001 Lyon')
            ->assertDontSee('Adresse legacy interdite');
    }

    public function test_unpublished_unknown_archived_and_soft_deleted_restaurants_are_ignored_without_replacement(): void
    {
        $published = $this->restaurant(['name' => 'Visible', 'slug' => 'visible']);
        $pending = $this->restaurant(['name' => 'En attente', 'slug' => 'en-attente', 'status' => 'pending']);
        $archived = $this->restaurant(['name' => 'Archivé', 'slug' => 'archive', 'status' => 'archived']);
        $deleted = $this->restaurant(['name' => 'Supprimé', 'slug' => 'supprime']);
        $deleted->delete();
        $article = $this->article('[restaurants_table ids="'.$pending->id.',999999,'.$published->id.','.$archived->id.','.$deleted->id.'"]');

        $this->get('/'.$article->slug)
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('En attente')
            ->assertDontSee('Archivé')
            ->assertDontSee('Supprimé');
    }

    public function test_malformed_or_empty_shortcodes_render_nothing_and_do_not_leak_as_text(): void
    {
        $article = $this->article('<p>[restaurants_table ids=1,2]</p><p>[restaurants_table ids="0,-2,nope"]</p><p>[restaurants_table ids="1,2"</p>');

        $this->get('/'.$article->slug)
            ->assertOk()
            ->assertDontSee('editorial-restaurants-table', false)
            ->assertDontSee('[restaurants_table', false);
    }

    public function test_shortcode_is_preserved_by_editorial_sanitization_before_ssr_rendering(): void
    {
        $shortcode = '[restaurants_table ids="1,2,3"]';

        $this->assertSame($shortcode, app(ContentSanitizer::class)->sanitize($shortcode)['html']);
    }

    private function article(string $content): Article
    {
        return Article::create([
            'legacy_wp_id' => random_int(1, 999999999),
            'original_title' => 'Article tableau restaurants',
            'title' => 'Article tableau restaurants',
            'slug' => 'article-tableau-'.str()->random(10),
            'legacy_url' => '/article-tableau',
            'content_html' => $content,
            'status' => 'published',
        ]);
    }

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::create($attributes + [
            'legacy_wp_id' => random_int(1, 999999999),
            'name' => 'Restaurant de test',
            'slug' => 'restaurant-'.str()->random(10),
            'status' => 'published',
            'address' => 'Adresse legacy',
            'address_line1' => '10 rue du Test',
            'postal_code' => '75001',
            'city_name' => 'Paris',
        ]);
    }
}
