<?php

namespace Tests\Feature;

use App\Filament\Resources\RestaurantResource\Pages\EditRestaurant;
use App\Models\RedirectRule;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\RestaurantSlugService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RestaurantSlugManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_generator_uses_readable_location_candidates_then_a_stable_numeric_suffix_and_includes_trashed_records(): void
    {
        $this->restaurant('le-safran');
        $this->restaurant('le-safran-paris');
        $trashed = $this->restaurant('le-safran-paris-75011');
        $trashed->delete();
        $this->restaurant('le-safran-paris-75011-2');

        $slug = app(RestaurantSlugService::class)->generate('Le Safran', 'Paris', '75011');

        $this->assertSame('le-safran-paris-75011-3', $slug);
    }

    public function test_a_public_submission_uses_the_deterministic_name_slug_without_a_random_suffix(): void
    {
        $this->assertSame('restaurant-de-test', app(RestaurantSlugService::class)->generate('Restaurant de test', 'Paris', '75011'));
    }

    public function test_editing_a_restaurant_name_in_filament_keeps_its_slug_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = $this->restaurant('restaurant-historique', ['name' => 'Restaurant historique']);

        Livewire::actingAs($admin)
            ->test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
            ->fillForm(['name' => 'Restaurant renommé'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('restaurant-historique', $restaurant->fresh()->slug);
        $this->assertDatabaseCount('redirect_rules', 0);
    }

    public function test_a_slug_change_creates_an_exact_301_to_the_new_restaurant_url(): void
    {
        $restaurant = $this->restaurant('ancien-slug');

        $restaurant->update(['slug' => 'nouveau-slug']);

        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/resto/ancien-slug',
            'match_type' => 'exact',
            'destination' => '/resto/nouveau-slug',
            'status_code' => 301,
            'is_active' => true,
            'origin' => 'restaurant_slug_change',
        ]);
        $this->get('/resto/ancien-slug')->assertRedirect('/resto/nouveau-slug')->assertStatus(301);
        $this->get('/resto/nouveau-slug')->assertOk();
    }

    public function test_an_administrator_can_manually_change_a_slug_in_filament(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = $this->restaurant('ancien-slug');

        Livewire::actingAs($admin)
            ->test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
            ->fillForm(['slug' => 'nouveau-slug'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('nouveau-slug', $restaurant->fresh()->slug);
        $this->assertDatabaseHas('redirect_rules', ['source_path' => '/resto/ancien-slug', 'destination' => '/resto/nouveau-slug']);
    }

    public function test_an_incompatible_existing_redirect_blocks_the_slug_change_without_overwriting_it(): void
    {
        $restaurant = $this->restaurant('ancien-slug');
        RedirectRule::create(['source_path' => '/resto/ancien-slug', 'match_type' => 'exact', 'destination' => '/autre-destination', 'status_code' => 301]);

        try {
            $restaurant->update(['slug' => 'nouveau-slug']);
            $this->fail('The incompatible redirect must block the slug change.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $this->assertSame('ancien-slug', $restaurant->fresh()->slug);
        $this->assertDatabaseHas('redirect_rules', ['source_path' => '/resto/ancien-slug', 'destination' => '/autre-destination']);
    }

    public function test_a_redirect_loop_blocks_the_slug_change(): void
    {
        $restaurant = $this->restaurant('ancien-slug');
        RedirectRule::create(['source_path' => '/resto/nouveau-slug', 'match_type' => 'exact', 'destination' => '/resto/ancien-slug', 'status_code' => 301]);

        $this->expectException(ValidationException::class);
        $restaurant->update(['slug' => 'nouveau-slug']);
    }

    public function test_a_regex_redirect_loop_also_blocks_the_slug_change(): void
    {
        $restaurant = $this->restaurant('ancien-slug');
        RedirectRule::create(['source_path' => '^resto/nouveau-slug$', 'match_type' => 'regex', 'destination' => '/resto/ancien-slug', 'status_code' => 301]);

        $this->expectException(ValidationException::class);
        $restaurant->update(['slug' => 'nouveau-slug']);
    }

    private function restaurant(string $slug, array $attributes = []): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => random_int(1, 999999999),
            'name' => 'Restaurant '.$slug,
            'slug' => $slug,
            'status' => 'published',
            ...$attributes,
        ]);
    }
}
